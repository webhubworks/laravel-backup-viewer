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
            <p>{!! __('backup-viewer::messages.footer.intro_html') !!}</p>
            <p>{!! __('backup-viewer::messages.footer.config_html') !!}</p>
        </footer>
    @endunless
@endsection
