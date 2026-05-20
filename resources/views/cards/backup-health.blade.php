@php
    use Webhub\BackupViewer\Support\Format;

    $monitorEnabled = (bool) ($health['monitorEnabled'] ?? false);
    $state = $health['state'] ?? [];
    $lastRun = is_array($state['lastRun'] ?? null) ? $state['lastRun'] : null;
    $lastSuccessful = is_array($state['lastSuccessfulRun'] ?? null) ? $state['lastSuccessfulRun'] : null;
    $lastMonitor = is_array($state['lastMonitor'] ?? null) ? $state['lastMonitor'] : null;

    $destinations = $health['destinations'] ?? [];
    $hasAnyMonitorRecord = collect($destinations)->contains(fn ($d) => $d['hasRecord'] ?? false);

    if ($monitorEnabled && $hasAnyMonitorRecord) {
        $allHealthy = collect($destinations)
            ->filter(fn ($d) => $d['hasRecord'] ?? false)
            ->every(fn ($d) => ($d['isHealthy'] ?? false) === true);
        $statusBadge = $allHealthy
            ? ['tone' => 'ok', 'label' => 'Healthy']
            : ['tone' => 'warning', 'label' => 'Not healthy'];
    } else {
        $statusBadge = null;
    }
@endphp

<div class="ls-card">
    <div class="ls-card__header">
        <h2 class="ls-card__title">Backup health</h2>
    </div>

    <div class="ls-card__body">
        @if (! $monitorEnabled)
            @include('backup-viewer::_partials.notice', [
                'tone' => 'info',
                'title' => 'Monitoring is not configured',
                'body' => 'Add a <code>monitor_backups</code> entry in <code>config/backup.php</code> to enable per-target health checks.',
            ])
        @endif

        <dl class="ls-rows">
            @if ($monitorEnabled)
                <dt>Status</dt>
                <dd>
                    @if ($statusBadge)
                        <div class="ls-status-line">
                            <span class="ls-badge ls-badge--{{ $statusBadge['tone'] }}">{{ $statusBadge['label'] }}</span>
                        </div>
                    @else
                        <span class="ls-muted">No monitor data yet &mdash; run <code>php artisan backup:monitor</code>.</span>
                    @endif
                </dd>

                <dt>Last monitor run</dt>
                <dd>
                    @if (is_int($lastMonitor['at'] ?? null))
                        {{ Format::relativeTime((int) $lastMonitor['at']) }}
                    @else
                        <span class="ls-muted">Never</span>
                    @endif
                </dd>
            @endif

            <dt>Last run</dt>
            <dd>
                @if ($lastRun)
                    <div class="ls-run-line">
                        <span>{{ Format::relativeTime((int) $lastRun['at']) }}</span>
                        @if (($lastRun['status'] ?? null) === 'ok')
                            <span class="ls-badge ls-badge--ok">OK</span>
                        @else
                            <span class="ls-badge ls-badge--failed">Failed</span>
                        @endif
                    </div>
                @else
                    <span class="ls-muted">No backup has run yet &mdash; run <code>php artisan backup:run</code>.</span>
                @endif
            </dd>

            <dt>Last successful run</dt>
            <dd>
                @if ($lastSuccessful)
                    {{ Format::relativeTime((int) $lastSuccessful['at']) }}
                @else
                    <span class="ls-muted">Never</span>
                @endif
            </dd>

            @if ($lastRun && ($lastRun['status'] ?? null) !== 'ok' && ! empty($lastRun['errors']))
                <dt>Failure reason</dt>
                <dd>
                    @foreach ($lastRun['errors'] as $error)
                        <div class="ls-error-line">{{ $error }}</div>
                    @endforeach
                </dd>
            @endif

            @foreach ([
                'backup:run' => 'Backup schedule',
                'backup:monitor' => 'Monitor schedule',
            ] as $needle => $rowLabel)
                @php
                    $entries = collect($schedule ?? [])->where('command', $needle)->values();
                @endphp
                <dt>{{ $rowLabel }}</dt>
                <dd>
                    @if ($entries->isEmpty())
                        <span class="ls-muted">Not scheduled</span>
                    @else
                        <ul class="ls-schedule">
                            @foreach ($entries as $s)
                                <li class="ls-schedule__row">
                                    <code class="ls-schedule__cmd">{{ $s['fullCommand'] }}</code>
                                    <span class="ls-schedule__sep" aria-hidden="true">·</span>
                                    <span class="ls-schedule__human">{{ $s['human'] }}</span>
                                    @if ($s['human'] !== $s['cron'])
                                        <span class="ls-schedule__cron" title="cron expression">({{ $s['cron'] }})</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </dd>
            @endforeach
        </dl>

    </div>
</div>
