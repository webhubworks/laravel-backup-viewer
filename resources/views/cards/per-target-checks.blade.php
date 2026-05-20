@php
    use Webhub\BackupViewer\Support\Format;

    $monitorEnabled = (bool) ($health['monitorEnabled'] ?? false);
    $destinations = $health['destinations'] ?? [];
    $hasAnyMonitorRecord = collect($destinations)->contains(fn ($d) => $d['hasRecord'] ?? false);
@endphp

<div class="ls-card">
    <div class="ls-card__header">
        <h2 class="ls-card__title">Per-target checks</h2>
    </div>

    <div class="ls-card__body">
        @if (! $monitorEnabled)
            @include('backup-viewer::_partials.notice', [
                'tone' => 'info',
                'title' => 'Monitoring is not configured',
                'body' => 'Add a <code>monitor_backups</code> entry in <code>config/backup.php</code> with one or more disks and health checks to populate this card.',
            ])
        @elseif (! $hasAnyMonitorRecord)
            @include('backup-viewer::_partials.notice', [
                'tone' => 'info',
                'title' => 'No data yet',
                'body' => 'Run <code>php artisan backup:monitor</code> (or schedule it) to populate this card. The page will show the result on the next request.',
            ])
        @elseif (count($destinations) === 1)
            @include('backup-viewer::_partials.target-section', ['d' => $destinations[0], 'flat' => true])
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
