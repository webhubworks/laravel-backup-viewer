@extends('backup-viewer::layout')

@section('content')
    @unless ($hasBackupPackage)
        @include('backup-viewer::cards.missing-package')
    @else
        <div class="ls-grid">
            @include('backup-viewer::cards.backup-health', ['health' => $health, 'schedule' => $schedule])
            @include('backup-viewer::cards.per-target-checks', ['health' => $health])
            @include('backup-viewer::cards.backups-by-target', [
                'byTarget' => $byTarget,
                'downloadMaxBytes' => $downloadMaxBytes,
                'lowDiskSpaceThreshold' => $lowDiskSpaceThreshold,
                'downloadRouteName' => $downloadRouteName,
            ])
            @include('backup-viewer::cards.notifications', [
                'events' => $notificationEvents,
            ])
        </div>

        <footer class="ls-page__footer">
            <p>
                This page is a read-only view of the
                <a href="https://github.com/spatie/laravel-backup" target="_blank" rel="noopener">spatie/laravel-backup</a>
                package's state &mdash; every backup, retention rule and notification setting
                shown here is driven by that package.
            </p>
            <p>
                To change anything &mdash; targets, schedule, encryption password, monitor
                thresholds, notifications &mdash; edit <code>config/backup.php</code> in your
                application. This page reflects those values on the next request.
            </p>
        </footer>
    @endunless
@endsection
