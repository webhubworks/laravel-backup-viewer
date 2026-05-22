<?php

namespace Webhub\BackupViewer\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

class RunDbBackupController
{
    /**
     * Run a database-only backup and stream the console output back to the
     * browser line-by-line via `response()->stream()`. The very last line of
     * the body is a structured trailer the client uses to either trigger the
     * subsequent download request or surface a failure:
     *
     *   __EOF__ exit=0 target=<disk> file=<name.zip>
     *   __EOF__ exit=<code>
     */
    public function __invoke(): StreamedResponse
    {
        if (! (bool) config('backup-viewer.actions.run_db_backup.enabled', true)) {
            throw new BadRequestHttpException('DB backup action is disabled.');
        }

        return response()->stream(function () {
            @set_time_limit(0);
            ignore_user_abort(true);

            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            $flush = static function (string $message, bool $newline): void {
                echo $message.($newline ? "\n" : '');
                @ob_flush();
                @flush();
            };

            $output = new class($flush) extends Output {
                /** @var callable */
                private $flush;

                public function __construct(callable $flush)
                {
                    parent::__construct();
                    $this->flush = $flush;
                }

                protected function doWrite(string $message, bool $newline): void
                {
                    ($this->flush)($message, $newline);
                }
            };

            $startedAt = time() - 1;

            try {
                $exitCode = Artisan::call('backup:run', [
                    '--only-db' => true,
                    '--disable-notifications' => true,
                ], $output);
            } catch (Throwable $e) {
                $flush('', true);
                $flush($e->getMessage(), true);
                $flush('__EOF__ exit=1', true);

                return;
            }

            if ($exitCode !== 0) {
                $flush('__EOF__ exit='.$exitCode, true);

                return;
            }

            $backup = $this->findNewestBackupSince($startedAt);

            if ($backup === null) {
                $flush('__EOF__ exit=0', true);

                return;
            }

            $flush(sprintf('__EOF__ exit=0 target=%s file=%s', $backup['target'], $backup['name']), true);
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Walk configured local destinations and return the newest .zip whose
     * mtime is at or after the request start, along with the disk name it
     * was found on. Falls back to null when none is found.
     *
     * @return array{target: string, name: string}|null
     */
    private function findNewestBackupSince(int $startedAt): ?array
    {
        $folder = (string) config('backup.backup.name', config('app.name', 'laravel-backup'));
        $diskNames = (array) config('backup.backup.destination.disks', []);

        $candidates = [];

        foreach ($diskNames as $diskName) {
            $diskName = (string) $diskName;

            if (config("filesystems.disks.{$diskName}.driver") !== 'local') {
                continue;
            }

            try {
                $disk = Storage::disk($diskName);
            } catch (Throwable) {
                continue;
            }

            if (! $disk instanceof FilesystemAdapter) {
                continue;
            }

            $files = [];
            try {
                $files = $disk->files($folder);
            } catch (Throwable) {
                continue;
            }

            foreach ($files as $relativePath) {
                $name = basename((string) $relativePath);
                if (! preg_match('/\.zip$/i', $name)) {
                    continue;
                }

                $absolute = $disk->path($relativePath);
                $real = realpath($absolute);
                if ($real === false || ! is_file($real)) {
                    continue;
                }

                $mtime = filemtime($real);
                if ($mtime === false || $mtime < $startedAt) {
                    continue;
                }

                $candidates[] = [
                    'target' => $diskName,
                    'name' => $name,
                    'mtime' => $mtime,
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return [
            'target' => $candidates[0]['target'],
            'name' => $candidates[0]['name'],
        ];
    }
}
