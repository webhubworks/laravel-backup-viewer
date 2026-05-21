<?php

namespace Webhub\BackupViewer\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

class RunDbBackupController
{
    /**
     * Run a database-only backup synchronously and stream the newly created
     * file back as a download. Designed to be called from the action card via
     * a fetch() request that consumes the binary response.
     */
    public function __invoke(): BinaryFileResponse|JsonResponse
    {
        if (! (bool) config('backup-viewer.actions.run_db_backup.enabled', true)) {
            throw new BadRequestHttpException('DB backup action is disabled.');
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        $startedAt = time() - 1;

        try {
            $exitCode = Artisan::call('backup:run', [
                '--only-db' => true,
                '--disable-notifications' => true,
            ]);
        } catch (Throwable $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], 422);
        }

        $output = trim((string) Artisan::output());

        if ($exitCode !== 0) {
            return new JsonResponse([
                'message' => $output !== '' ? $this->lastLine($output) : 'Backup failed.',
                'output' => $output,
            ], 422);
        }

        $backup = $this->findNewestBackupSince($startedAt);

        if ($backup === null) {
            return new JsonResponse([
                'message' => 'Backup ran, but no resulting file could be located on a local destination.',
                'output' => $output,
            ], 422);
        }

        return response()
            ->download($backup['path'], $backup['name'], [
                'Content-Type' => 'application/zip',
                'X-Backup-Filename' => $backup['name'],
            ])
            ->deleteFileAfterSend(false);
    }

    /**
     * Walk configured local destinations and return the newest .zip whose
     * mtime is at or after the request start. Falls back to null when none
     * is found (e.g. only remote destinations are configured).
     *
     * @return array{path: string, name: string}|null
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
                    'path' => $real,
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
            'path' => $candidates[0]['path'],
            'name' => $candidates[0]['name'],
        ];
    }

    private function lastLine(string $output): string
    {
        $lines = preg_split('/\R/', trim($output)) ?: [];
        $lines = array_values(array_filter($lines, static fn ($l) => trim($l) !== ''));

        return $lines === [] ? $output : (string) end($lines);
    }
}
