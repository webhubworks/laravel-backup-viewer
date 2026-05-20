<?php

namespace Webhub\BackupViewer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Spatie\Backup\BackupServiceProvider;
use Webhub\BackupViewer\Services\BackupCollector;
use Webhub\BackupViewer\Services\BackupHealthInspector;
use Webhub\BackupViewer\Services\BackupNotificationsInspector;
use Webhub\BackupViewer\Services\BackupScheduleInspector;

class BackupController
{
    public function show(
        BackupCollector $collector,
        BackupHealthInspector $inspector,
        BackupNotificationsInspector $notifications,
        BackupScheduleInspector $scheduleInspector,
    ): View {
        $hasBackupPackage = class_exists(BackupServiceProvider::class);

        $byTarget = $hasBackupPackage ? $collector->collect() : [];
        $health = $hasBackupPackage ? $inspector->inspect() : null;
        $notificationEvents = $hasBackupPackage ? $notifications->events() : [];
        $schedule = $hasBackupPackage ? $scheduleInspector->find() : [];

        return view('backup-viewer::index', [
            'hasBackupPackage' => $hasBackupPackage,
            'byTarget' => $byTarget,
            'health' => $health,
            'schedule' => $schedule,
            'notificationEvents' => $notificationEvents,
            'downloadMaxBytes' => config('backup-viewer.download.max_bytes'),
            'lowDiskSpaceThreshold' => (float) config('backup-viewer.low_disk_space_threshold', 0.15),
            'downloadRouteName' => config('backup-viewer.route.name', 'backup-viewer.index').'.download',
            'faviconHtml' => $this->resolveFaviconHtml(),
            'faviconPath' => $this->resolveFaviconPath(),
        ]);
    }

    private function resolveFaviconHtml(): ?string
    {
        $html = config('backup-viewer.favicon.html');

        return is_string($html) && $html !== '' ? $html : null;
    }

    private function resolveFaviconPath(): ?string
    {
        if ($this->resolveFaviconHtml() !== null) {
            return null;
        }

        $configured = config('backup-viewer.favicon.path');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        foreach (['favicon.svg', 'favicon.png', 'favicon.ico'] as $name) {
            if (is_file(public_path($name))) {
                return '/'.$name;
            }
        }

        return null;
    }
}
