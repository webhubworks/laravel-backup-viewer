@extends('backup-viewer::layout')

@section('content')
    @unless ($hasBackupPackage)
        @include('backup-viewer::cards.missing-package')
    @else
        <div class="ls-stack">
            @include('backup-viewer::cards.hero', ['health' => $health, 'schedule' => $schedule])

            @include('backup-viewer::cards.metrics', [
                'health' => $health,
                'schedule' => $schedule,
                'byTarget' => $byTarget,
                'lowDiskSpaceThreshold' => $lowDiskSpaceThreshold,
            ])

            <div class="ls-sidebar-grid">
                <div class="ls-sidebar-grid__main">
                    @include('backup-viewer::cards.per-target-checks', ['health' => $health])
                </div>
                <div class="ls-sidebar-grid__aside">
                    @include('backup-viewer::cards.db-backup', [
                        'byTarget' => $byTarget,
                        'runDbRouteName' => $runDbRouteName,
                    ])
                </div>
            </div>

            @include('backup-viewer::cards.backups-by-target', [
                'byTarget' => $byTarget,
                'downloadMaxBytes' => $downloadMaxBytes,
                'lowDiskSpaceThreshold' => $lowDiskSpaceThreshold,
                'downloadRouteName' => $downloadRouteName,
            ])

            @include('backup-viewer::cards.notifications', [
                'events' => $notificationEvents,
            ])

            @include('backup-viewer::cards.diagnostics', [
                'health' => $health,
                'schedule' => $schedule,
            ])
        </div>

        <footer class="ls-page__footer">
            <p>{!! __('backup-viewer::messages.footer.intro_html') !!}</p>
            <p>{!! __('backup-viewer::messages.footer.config_html') !!}</p>
        </footer>
    @endunless
@endsection
