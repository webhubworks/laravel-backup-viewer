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
 * destination disk; each event independently updates the state — latest
 * event wins, which is fine for the TLDR shown on the page.
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
        $this->store->recordBackupSuccess($event->diskName, $event->backupName);
    }

    public function onBackupFailure(BackupHasFailed $event): void
    {
        $this->store->recordBackupFailure(
            $event->diskName,
            $event->backupName,
            $event->exception->getMessage(),
        );
    }

    public function onHealthyDestination(HealthyBackupWasFound $event): void
    {
        $this->store->recordMonitorResult(
            $event->diskName,
            $event->backupName,
            array_merge(['isHealthy' => true, 'failures' => []], $this->stats($event->diskName, $event->backupName)),
        );
    }

    public function onUnhealthyDestination(UnhealthyBackupWasFound $event): void
    {
        $failures = $event->failureMessages
            ->map(static fn ($entry): array => [
                'check' => (string) ($entry['check'] ?? ''),
                'message' => (string) ($entry['message'] ?? ''),
            ])
            ->values()
            ->all();

        $this->store->recordMonitorResult(
            $event->diskName,
            $event->backupName,
            array_merge(['isHealthy' => false, 'failures' => $failures], $this->stats($event->diskName, $event->backupName)),
        );
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
