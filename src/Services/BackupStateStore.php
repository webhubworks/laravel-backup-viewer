<?php

namespace Webhub\BackupViewer\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Persists a small JSON record of backup activity so the /backups view can
 * surface "last run", "last successful run", and per-destination monitor
 * results without rerunning checks on every request.
 *
 * Storage location, in priority order:
 *   1. First local disk listed in config('backup.backup.destination.disks'),
 *      written to <backupName>/laravel-backup-viewer-state.json so the file lives
 *      next to the .zip backups it describes.
 *   2. storage_path('app/backup-viewer/backup-state.json') fallback when
 *      no local backup disk is configured (remote-only setups).
 *
 * Writes are atomic (tmp + rename) so concurrent listeners can never leave
 * the file half-written.
 *
 * Shape:
 * {
 *   "lastRun":           {"at": int, "status": "ok"|"failed", "diskName": string|null, "backupName": string|null, "errors": string[]} | null,
 *   "lastSuccessfulRun": {"at": int, "diskName": string|null, "backupName": string|null} | null,
 *   "lastMonitor": {
 *     "at": int,
 *     "destinations": {
 *       "<disk>|<name>": {
 *         "diskName": string,
 *         "backupName": string,
 *         "isHealthy": bool,
 *         "amountOfBackups": int|null,
 *         "newestBackupAt": int|null,
 *         "usedStorageBytes": int|null,
 *         "failures": [{"check": string, "message": string}, ...],
 *         "checkedAt": int
 *       }
 *     }
 *   } | null
 * }
 */
class BackupStateStore
{
    public const FILENAME = 'laravel-backup-viewer-state.json';

    public function read(): array
    {
        $path = $this->absolutePath();

        if (! is_file($path)) {
            return $this->empty();
        }

        try {
            $data = json_decode((string) @file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->empty();
        }

        if (! is_array($data)) {
            return $this->empty();
        }

        return array_replace($this->empty(), $data);
    }

    public function recordBackupSuccess(string $diskName, string $backupName, ?int $at = null): void
    {
        $at ??= time();
        $state = $this->read();

        $state['lastRun'] = [
            'at' => $at,
            'status' => 'ok',
            'diskName' => $diskName,
            'backupName' => $backupName,
            'errors' => [],
        ];

        $state['lastSuccessfulRun'] = [
            'at' => $at,
            'diskName' => $diskName,
            'backupName' => $backupName,
        ];

        $this->write($state);
    }

    public function recordBackupFailure(?string $diskName, ?string $backupName, string $error, ?int $at = null): void
    {
        $at ??= time();
        $state = $this->read();

        $state['lastRun'] = [
            'at' => $at,
            'status' => 'failed',
            'diskName' => $diskName,
            'backupName' => $backupName,
            'errors' => [$error],
        ];

        $this->write($state);
    }

    /**
     * @param  array{
     *     isHealthy: bool,
     *     amountOfBackups: int|null,
     *     newestBackupAt: int|null,
     *     usedStorageBytes: int|null,
     *     failures: array<int, array{check:string, message:string}>
     * }  $result
     */
    public function recordMonitorResult(string $diskName, string $backupName, array $result, ?int $at = null): void
    {
        $at ??= time();
        $state = $this->read();

        $key = $this->destinationKey($diskName, $backupName);

        $monitor = $state['lastMonitor'] ?? ['at' => $at, 'destinations' => []];
        $monitor['at'] = $at;
        $monitor['destinations'][$key] = array_merge($result, [
            'diskName' => $diskName,
            'backupName' => $backupName,
            'checkedAt' => $at,
        ]);

        $state['lastMonitor'] = $monitor;
        $this->write($state);
    }

    public function destinationKey(string $diskName, string $backupName): string
    {
        return $diskName.'|'.$backupName;
    }

    /**
     * Absolute filesystem path where the state JSON lives. Public for tests
     * and to surface the location to operators when debugging.
     */
    public function absolutePath(): string
    {
        [$disk, $relative] = $this->resolveLocation();

        if ($disk instanceof FilesystemAdapter) {
            return $disk->path($relative);
        }

        return storage_path('app/backup-viewer/backup-state.json');
    }

    /**
     * @return array{0: FilesystemAdapter|null, 1: string} [disk, relativePath]
     */
    private function resolveLocation(): array
    {
        $disks = (array) config('backup.backup.destination.disks', []);
        $backupName = (string) config('backup.backup.name', config('app.name', 'laravel-backup'));

        foreach ($disks as $diskName) {
            $diskName = (string) $diskName;
            if (config("filesystems.disks.{$diskName}.driver") !== 'local') {
                continue;
            }

            try {
                $disk = Storage::disk($diskName);
            } catch (Throwable) {
                continue;
            }

            if ($disk instanceof FilesystemAdapter) {
                return [$disk, $backupName.'/'.self::FILENAME];
            }
        }

        return [null, ''];
    }

    private function empty(): array
    {
        return [
            'lastRun' => null,
            'lastSuccessfulRun' => null,
            'lastMonitor' => null,
        ];
    }

    private function write(array $state): void
    {
        $path = $this->absolutePath();
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return;
        }

        $tmp = $path.'.tmp.'.bin2hex(random_bytes(4));
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        if (@file_put_contents($tmp, $json) === false) {
            return;
        }

        if (! @rename($tmp, $path)) {
            @unlink($tmp);
        }
    }
}
