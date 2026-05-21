<?php

namespace Webhub\BackupViewer\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Backup\Tasks\Monitor\HealthChecks\IsReachable;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Throwable;

/**
 * Composes the persisted state + spatie's monitor_backups config + live
 * free-disk-space probe into a single view-data shape consumed by the
 * Backup health + Per-target checks cards.
 */
class BackupHealthInspector
{
    public function __construct(
        private readonly BackupStateStore $state,
    ) {}

    /**
     * @return array{
     *     monitorEnabled: bool,
     *     state: array<string, mixed>,
     *     destinations: array<int, array<string, mixed>>,
     *     statePath: string,
     * }
     */
    public function inspect(): array
    {
        $monitorConfig = $this->normalizedMonitorConfig();
        $state = $this->state->read();
        $destinations = $this->destinations($monitorConfig, $state);

        return [
            'monitorEnabled' => $monitorConfig !== [],
            'state' => $state,
            'destinations' => $destinations,
            'statePath' => $this->state->absolutePath(),
        ];
    }

    /**
     * @return array<int, array{name:string, disks:array<int,string>, healthChecks: array<int, array{class:string, name:string, label:string, arg:int|null}>}>
     */
    private function normalizedMonitorConfig(): array
    {
        $monitorConfig = (array) config('backup.monitor_backups', []);

        $result = [];
        foreach ($monitorConfig as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $disks = array_values(array_filter((array) ($entry['disks'] ?? []), 'is_string'));
            if ($disks === []) {
                continue;
            }

            $name = (string) ($entry['name'] ?? config('backup.backup.name', ''));

            $checks = [];
            foreach ((array) ($entry['health_checks'] ?? []) as $key => $value) {
                $class = is_string($key) ? $key : (is_string($value) ? $value : null);
                $arg = is_string($key) ? (is_int($value) ? $value : null) : null;
                if ($class === null || ! class_exists($class)) {
                    continue;
                }
                $checks[] = [
                    'class' => $class,
                    'name' => Str::title(class_basename($class)),
                    'label' => $this->labelFor($class, $arg),
                    'arg' => $arg,
                ];
            }

            $result[] = [
                'name' => $name,
                'disks' => $disks,
                'healthChecks' => $checks,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array{name:string, disks:array<int,string>, healthChecks: array<int, array{class:string,name:string,label:string,arg:int|null}>}>  $monitorConfig
     * @param  array<string, mixed>  $state
     * @return array<int, array<string, mixed>>
     */
    private function destinations(array $monitorConfig, array $state): array
    {
        $monitor = is_array($state['lastMonitor'] ?? null) ? $state['lastMonitor'] : null;
        $monitorAt = is_int($monitor['at'] ?? null) ? (int) $monitor['at'] : null;
        $records = is_array($monitor['destinations'] ?? null) ? $monitor['destinations'] : [];
        $threshold = (float) config('backup-viewer.low_disk_space_threshold', 0.15);
        $staleAfterSeconds = (int) (config('backup-viewer.monitor_stale_after_minutes', 1440) * 60);

        $out = [];
        foreach ($monitorConfig as $group) {
            foreach ($group['disks'] as $diskName) {
                $key = $this->state->destinationKey($diskName, $group['name']);
                $record = is_array($records[$key] ?? null) ? $records[$key] : null;
                $driver = (string) (config("filesystems.disks.{$diskName}.driver") ?? '');
                $isLocal = $driver === 'local';

                $items = $this->checkItems($group['healthChecks'], $record);
                if ($isLocal) {
                    $items[] = $this->freeDiskCheck($diskName, $threshold);
                }

                $checkedAt = is_int($record['checkedAt'] ?? null) ? (int) $record['checkedAt'] : null;
                $isStale = $checkedAt !== null && (time() - $checkedAt) > $staleAfterSeconds;

                $out[] = [
                    'diskName' => $diskName,
                    'backupName' => $group['name'],
                    'driver' => $driver !== '' ? $driver : null,
                    'isLocal' => $isLocal,
                    'isHealthy' => is_array($record) ? (bool) ($record['isHealthy'] ?? false) : null,
                    'amountOfBackups' => $record['amountOfBackups'] ?? null,
                    'newestBackupAt' => $record['newestBackupAt'] ?? null,
                    'usedStorageBytes' => $record['usedStorageBytes'] ?? null,
                    'failures' => is_array($record['failures'] ?? null) ? $record['failures'] : [],
                    'checkedAt' => $checkedAt,
                    'isStale' => $isStale,
                    'hasRecord' => $record !== null,
                    'checkItems' => $items,
                ];
            }
        }

        return $out;
    }

    /**
     * Translate spatie's configured health checks into displayable rows,
     * resolving pass/fail/skipped from the latest monitor record.
     *
     * @param  array<int, array{class:string,name:string,label:string,arg:int|null}>  $checks
     * @param  array<string, mixed>|null  $record
     * @return array<int, array{label:string, status:'ok'|'failure'|'skipped', detail:?string}>
     */
    private function checkItems(array $checks, ?array $record): array
    {
        $failuresByName = [];
        if (is_array($record['failures'] ?? null)) {
            foreach ($record['failures'] as $f) {
                if (is_array($f) && isset($f['check'])) {
                    $failuresByName[(string) $f['check']] = (string) ($f['message'] ?? '');
                }
            }
        }

        $items = [];

        // IsReachable is always added by spatie at runtime, even if not
        // listed in health_checks — surface it explicitly so the panel
        // mirrors what the monitor actually evaluates.
        $items[] = $this->checkRow(
            'Target is reachable',
            IsReachable::class,
            $failuresByName,
            $record,
        );

        foreach ($checks as $check) {
            $items[] = $this->checkRow($check['label'], $check['class'], $failuresByName, $record, $check['name']);
        }

        return $items;
    }

    /**
     * @param  array<string, string>  $failuresByName
     * @param  array<string, mixed>|null  $record
     */
    private function checkRow(string $label, string $class, array $failuresByName, ?array $record, ?string $spatieName = null): array
    {
        if ($record === null) {
            return ['label' => $label, 'status' => 'skipped', 'detail' => null];
        }

        $name = $spatieName ?? Str::title(class_basename($class));
        if (isset($failuresByName[$name])) {
            return ['label' => $label, 'status' => 'failure', 'detail' => $failuresByName[$name]];
        }

        return ['label' => $label, 'status' => 'ok', 'detail' => null];
    }

    /**
     * @return array{label:string, status:'ok'|'failure'|'skipped', detail:?string}
     */
    private function freeDiskCheck(string $diskName, float $threshold): array
    {
        $simpleLabel = (string) __('backup-viewer::messages.checks.free_disk_space');

        try {
            $disk = Storage::disk($diskName);
        } catch (Throwable) {
            return ['label' => $simpleLabel, 'status' => 'skipped', 'detail' => null];
        }

        if (! $disk instanceof FilesystemAdapter) {
            return ['label' => $simpleLabel, 'status' => 'skipped', 'detail' => null];
        }

        $root = $disk->path('');
        if (! is_string($root) || ! is_dir($root)) {
            return ['label' => $simpleLabel, 'status' => 'skipped', 'detail' => null];
        }

        $total = @disk_total_space($root);
        $free = @disk_free_space($root);
        if (! is_numeric($total) || ! is_numeric($free) || $total <= 0) {
            return ['label' => $simpleLabel, 'status' => 'skipped', 'detail' => null];
        }

        $ratio = (float) $free / (float) $total;
        $percent = number_format($ratio * 100, 1).'%';
        $thresholdPct = number_format($threshold * 100, 0).'%';

        $label = (string) __('backup-viewer::messages.checks.free_disk_space_above', ['threshold' => $thresholdPct]);

        if ($ratio < $threshold) {
            return [
                'label' => $label,
                'status' => 'failure',
                'detail' => (string) __('backup-viewer::messages.checks.only_x_free', ['percent' => $percent]),
            ];
        }

        return [
            'label' => $label,
            'status' => 'ok',
            'detail' => (string) __('backup-viewer::messages.checks.x_free', ['percent' => $percent]),
        ];
    }

    private function labelFor(string $class, ?int $arg): string
    {
        return match ($class) {
            IsReachable::class => (string) __('backup-viewer::messages.checks.target_reachable'),
            MaximumAgeInDays::class => $arg !== null
                ? (string) trans_choice('backup-viewer::messages.checks.newest_within_days', $arg, ['count' => $arg])
                : (string) __('backup-viewer::messages.checks.newest_within_configured_age'),
            MaximumStorageInMegabytes::class => $arg !== null
                ? (string) __('backup-viewer::messages.checks.total_under_mb', ['mb' => $arg])
                : (string) __('backup-viewer::messages.checks.total_under_configured'),
            default => Str::title(class_basename($class)),
        };
    }
}
