@php
    use Webhub\BackupViewer\Support\Format;

    $monitorEnabled = (bool) ($health['monitorEnabled'] ?? false);
    $destinations = $health['destinations'] ?? [];
    $hasAnyMonitorRecord = collect($destinations)->contains(fn ($d) => $d['hasRecord'] ?? false);
@endphp

<div class="ls-card">
    <div class="ls-card__header">
        <h2 class="ls-card__title">{{ __('backup-viewer::messages.per_target.title') }}</h2>
    </div>

    <div class="ls-card__body">
        @if (! $monitorEnabled)
            @include('backup-viewer::_partials.notice', [
                'tone' => 'info',
                'title' => __('backup-viewer::messages.per_target.monitoring_not_configured_title'),
                'body' => __('backup-viewer::messages.per_target.monitoring_not_configured_body_html'),
            ])
        @elseif (! $hasAnyMonitorRecord)
            @include('backup-viewer::_partials.notice', [
                'tone' => 'info',
                'title' => __('backup-viewer::messages.per_target.no_data_title'),
                'body' => __('backup-viewer::messages.per_target.no_data_body_html'),
            ])
        @else
            <div class="ls-checks">
                @foreach ($destinations as $d)
                    @php
                        $items = $d['checkItems'] ?? [];
                        $hasRecord = (bool) ($d['hasRecord'] ?? false);
                        $failed = collect($items)->where('status', 'failure')->count();
                        $isHealthy = $hasRecord && ($d['isHealthy'] ?? false) === true && $failed === 0;
                        $detailsOpenByDefault = ! $isHealthy || ! $hasRecord;
                    @endphp

                    <details class="ls-check-group" @if ($detailsOpenByDefault) open @endif>
                        @include('backup-viewer::_partials.target-section', ['d' => $d, 'flat' => false])
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</div>
