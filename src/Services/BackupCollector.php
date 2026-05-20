<?php

namespace Webhub\BackupViewer\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackupCollector
{
    public function __construct(
        private readonly EncryptionDetector $encryption = new EncryptionDetector,
    ) {}

    /**
     * Collect a row of backups per disk.
     *
     * Returned shape:
     *   [
     *     <diskName> => [
     *       'driver'        => 'local'|'s3'|'sftp'|...|null,
     *       'isLocal'       => bool,
     *       'backups'       => [['name','size','modified','encrypted'], ...],   // newest first
     *       'backupsBytes'  => int,
     *       'diskUsage'     => ['total' => int, 'free' => int]|null,            // local only
     *     ],
     *   ]
     *
     * @return array<string, array{driver:?string, isLocal:bool, backups:array<int, array{name:string, size:int, modified:int, encrypted:?bool}>, backupsBytes:int, diskUsage:array{total:int, free:int}|null}>
     */
    public function collect(): array
    {
        /** @var array<int, string> $diskNames */
        $diskNames = (array) config('backup.backup.destination.disks', []);
        $backupName = (string) config('backup.backup.name', config('app.name', 'laravel-backup'));

        $result = [];

        foreach ($diskNames as $diskName) {
            $diskName = (string) $diskName;
            $driver = (string) (config("filesystems.disks.{$diskName}.driver") ?? '');
            $isLocal = $driver === 'local';

            try {
                $disk = Storage::disk($diskName);
            } catch (Throwable) {
                $result[$diskName] = [
                    'driver' => $driver !== '' ? $driver : null,
                    'isLocal' => $isLocal,
                    'backups' => [],
                    'backupsBytes' => 0,
                    'diskUsage' => null,
                ];

                continue;
            }

            $rows = $this->listBackups($disk, $diskName, $backupName, $isLocal);

            $bytes = array_sum(array_column($rows, 'size'));

            $result[$diskName] = [
                'driver' => $driver !== '' ? $driver : null,
                'isLocal' => $isLocal,
                'backups' => $rows,
                'backupsBytes' => $bytes,
                'diskUsage' => $isLocal ? $this->localDiskUsage($disk) : null,
                'location' => $this->describeLocation($diskName, $driver, $backupName),
            ];
        }

        return $result;
    }

    /**
     * Build a human-readable location string for a disk so operators can
     * see at a glance where backups are physically stored.
     */
    private function describeLocation(string $diskName, string $driver, string $backupName): ?string
    {
        $cfg = (array) config("filesystems.disks.{$diskName}", []);
        $root = trim((string) ($cfg['root'] ?? ''), '/');
        $suffix = $root === '' ? $backupName : $root.'/'.$backupName;

        $host = (string) ($cfg['host'] ?? '');
        $port = $cfg['port'] ?? null;
        $username = (string) ($cfg['username'] ?? '');

        $hostPort = $host !== ''
            ? ($username !== '' ? $username.'@' : '').$host.($port ? ':'.$port : '')
            : '';

        return match ($driver) {
            'local' => '/'.$suffix,
            'sftp' => $hostPort !== '' ? 'sftp://'.$hostPort.'/'.$suffix : 'sftp:/'.$suffix,
            'ftp' => $hostPort !== '' ? 'ftp://'.$hostPort.'/'.$suffix : 'ftp:/'.$suffix,
            's3' => 's3://'.((string) ($cfg['bucket'] ?? '')).'/'.$suffix,
            default => $driver !== '' ? $driver.':/'.$suffix : '/'.$suffix,
        };
    }

    /**
     * @return array<int, array{name:string, size:int, modified:int, encrypted:?bool}>
     */
    private function listBackups(Filesystem $disk, string $diskName, string $folder, bool $isLocal): array
    {
        try {
            $files = $disk->files($folder);
        } catch (Throwable) {
            return [];
        }

        $rows = [];
        foreach ($files as $path) {
            $name = basename((string) $path);

            if (! preg_match('/\.zip$/i', $name)) {
                continue;
            }

            try {
                $size = (int) $disk->size($path);
                $modified = (int) $disk->lastModified($path);
            } catch (Throwable) {
                continue;
            }

            $encrypted = null;
            if ($isLocal && $disk instanceof FilesystemAdapter) {
                try {
                    $encrypted = $this->encryption->isEncrypted($disk->path($path));
                } catch (Throwable) {
                    $encrypted = null;
                }
            }

            $rows[] = [
                'name' => $name,
                'size' => $size,
                'modified' => $modified,
                'encrypted' => $encrypted,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $rows;
    }

    /**
     * @return array{total:int, free:int}|null
     */
    private function localDiskUsage(Filesystem $disk): ?array
    {
        if (! $disk instanceof FilesystemAdapter) {
            return null;
        }

        try {
            $root = $disk->path('');
        } catch (Throwable) {
            return null;
        }

        if (! is_string($root) || $root === '' || ! is_dir($root)) {
            return null;
        }

        $total = @disk_total_space($root);
        $free = @disk_free_space($root);

        if ($total === false || $free === false || $total === null || $free === null) {
            return null;
        }

        return ['total' => (int) $total, 'free' => (int) $free];
    }
}
