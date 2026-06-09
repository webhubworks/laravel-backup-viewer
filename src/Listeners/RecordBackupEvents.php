<?php

namespace Webhub\BackupViewer\Listeners;

use Illuminate\Events\Dispatcher;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Throwable;
use Webhub\BackupViewer\Services\BackupStateStore;

/**
 * Single subscriber that records every spatie/laravel-backup event we care
 * about into the BackupStateStore so the /backups page can render without
 * touching the backup disks itself.
 *
 * `backup:run` typically fires BackupWasSuccessful once per configured
 * destination disk; each event independently updates the state - latest
 * event wins, which is fine for the TLDR shown on the page.
 *
 * spatie/laravel-backup changed its event payloads between v9 and v10. v10
 * carries diskName/backupName as plain string properties (and unhealthy
 * failures as a Collection); v9 carries a BackupDestination, or a
 * BackupDestinationStatus wrapping one. We support both since the package
 * declares "^9.0 || ^10.0".
 */
class RecordBackupEvents
{
    public function __construct(private readonly BackupStateStore $store) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(BackupWasSuccessful::class, [$this, 'onBackupSuccess']);
        $events->listen(BackupHasFailed::class, [$this, 'onBackupFailure']);
        $events->listen(HealthyBackupWasFound::class, [$this, 'onHealthyDestination']);
        $events->listen(UnhealthyBackupWasFound::class, [$this, 'onUnhealthyDestination']);
    }

    public function onBackupSuccess(BackupWasSuccessful $event): void
    {
        [$diskName, $backupName] = $this->resolveNames($event);

        $this->store->recordBackupSuccess($diskName, $backupName);
    }

    public function onBackupFailure(BackupHasFailed $event): void
    {
        [$diskName, $backupName] = $this->resolveNames($event);

        $this->store->recordBackupFailure($diskName, $backupName, $event->exception->getMessage());
    }

    public function onHealthyDestination(HealthyBackupWasFound $event): void
    {
        [$diskName, $backupName] = $this->resolveNames($event);

        $this->store->recordMonitorResult(
            $diskName,
            $backupName,
            array_merge(['isHealthy' => true, 'failures' => []], $this->stats($diskName, $backupName)),
        );
    }

    public function onUnhealthyDestination(UnhealthyBackupWasFound $event): void
    {
        [$diskName, $backupName] = $this->resolveNames($event);
        $failures = $this->resolveFailures($event);

        $this->store->recordMonitorResult(
            $diskName,
            $backupName,
            array_merge(['isHealthy' => false, 'failures' => $failures], $this->stats($diskName, $backupName)),
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveNames(object $event): array
    {
        if (property_exists($event, 'diskName')) {
            return [$event->diskName, $event->backupName];
        }

        $destination = match (true) {
            property_exists($event, 'backupDestination') => $event->backupDestination,
            property_exists($event, 'backupDestinationStatus') => $event->backupDestinationStatus->backupDestination(),
            default => null,
        };

        return [$destination?->diskName(), $destination?->backupName()];
    }

    /**
     * @return array<int, array{check: string, message: string}>
     */
    private function resolveFailures(UnhealthyBackupWasFound $event): array
    {
        if (property_exists($event, 'failureMessages')) {
            return $event->failureMessages
                ->map(static fn ($entry): array => [
                    'check' => (string) ($entry['check'] ?? ''),
                    'message' => (string) ($entry['message'] ?? ''),
                ])
                ->values()
                ->all();
        }

        $failure = $event->backupDestinationStatus->getHealthCheckFailure();

        if ($failure === null) {
            return [];
        }

        return [[
            'check' => $failure->healthCheck()->name(),
            'message' => $failure->exception()->getMessage(),
        ]];
    }

    /**
     * @return array{amountOfBackups: int|null, newestBackupAt: int|null, usedStorageBytes: int|null}
     */
    private function stats(string $diskName, string $backupName): array
    {
        try {
            $destination = BackupDestination::create($diskName, $backupName);
        } catch (Throwable) {
            return ['amountOfBackups' => null, 'newestBackupAt' => null, 'usedStorageBytes' => null];
        }

        try {
            $newest = $destination->newestBackup();
            $newestAt = $newest?->date()->getTimestamp();
            $count = $destination->backups()->count();
            $used = (int) $destination->usedStorage();
        } catch (Throwable) {
            return ['amountOfBackups' => null, 'newestBackupAt' => null, 'usedStorageBytes' => null];
        }

        return [
            'amountOfBackups' => $count,
            'newestBackupAt' => $newestAt,
            'usedStorageBytes' => $used,
        ];
    }
}
