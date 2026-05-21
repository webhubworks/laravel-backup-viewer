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
            ? ['tone' => 'ok', 'label' => __('backup-viewer::messages.badges.healthy')]
            : ['tone' => 'warning', 'label' => __('backup-viewer::messages.badges.not_healthy')];
    } else {
        $statusBadge = null;
    }
@endphp

<div class="ls-card">
    <div class="ls-card__header">
        <h2 class="ls-card__title">{{ __('backup-viewer::messages.health.title') }}</h2>
    </div>

    <div class="ls-card__body">
        @if (! $monitorEnabled)
            @include('backup-viewer::_partials.notice', [
                'tone' => 'info',
                'title' => __('backup-viewer::messages.health.monitoring_not_configured_title'),
                'body' => __('backup-viewer::messages.health.monitoring_not_configured_body_html'),
            ])
        @endif

        <dl class="ls-rows">
            @if ($monitorEnabled)
                <dt>{{ __('backup-viewer::messages.health.status') }}</dt>
                <dd>
                    @if ($statusBadge)
                        <div class="ls-status-line">
                            <span class="ls-badge ls-badge--{{ $statusBadge['tone'] }}">{{ $statusBadge['label'] }}</span>
                        </div>
                    @else
                        <span class="ls-muted">{!! __('backup-viewer::messages.health.no_monitor_data_html') !!}</span>
                    @endif
                </dd>

                <dt>{{ __('backup-viewer::messages.health.last_monitor_run') }}</dt>
                <dd>
                    @if (is_int($lastMonitor['at'] ?? null))
                        {{ Format::relativeTime((int) $lastMonitor['at']) }}
                    @else
                        <span class="ls-muted">{{ __('backup-viewer::messages.common.never') }}</span>
                    @endif
                </dd>
            @endif

            <dt>{{ __('backup-viewer::messages.health.last_run') }}</dt>
            <dd>
                @if ($lastRun)
                    <div class="ls-run-line">
                        <span>{{ Format::relativeTime((int) $lastRun['at']) }}</span>
                        @if (($lastRun['status'] ?? null) === 'ok')
                            <span class="ls-badge ls-badge--ok">{{ __('backup-viewer::messages.badges.ok') }}</span>
                        @else
                            <span class="ls-badge ls-badge--failed">{{ __('backup-viewer::messages.badges.failed') }}</span>
                        @endif
                    </div>
                @else
                    <span class="ls-muted">{!! __('backup-viewer::messages.health.no_run_yet_html') !!}</span>
                @endif
            </dd>

            <dt>{{ __('backup-viewer::messages.health.last_successful_run') }}</dt>
            <dd>
                @if ($lastSuccessful)
                    {{ Format::relativeTime((int) $lastSuccessful['at']) }}
                @else
                    <span class="ls-muted">{{ __('backup-viewer::messages.common.never') }}</span>
                @endif
            </dd>

            @if ($lastRun && ($lastRun['status'] ?? null) !== 'ok' && ! empty($lastRun['errors']))
                <dt>{{ __('backup-viewer::messages.health.failure_reason') }}</dt>
                <dd>
                    @foreach ($lastRun['errors'] as $error)
                        <div class="ls-error-line">{{ $error }}</div>
                    @endforeach
                </dd>
            @endif

            @foreach ([
                'backup:run' => __('backup-viewer::messages.health.backup_schedule'),
                'backup:monitor' => __('backup-viewer::messages.health.monitor_schedule'),
            ] as $needle => $rowLabel)
                @php
                    $entries = collect($schedule ?? [])->where('command', $needle)->values();
                @endphp
                <dt>{{ $rowLabel }}</dt>
                <dd>
                    @if ($entries->isEmpty())
                        <span class="ls-muted">{{ __('backup-viewer::messages.common.not_scheduled') }}</span>
                    @else
                        <ul class="ls-schedule">
                            @foreach ($entries as $s)
                                <li class="ls-schedule__row">
                                    <code class="ls-schedule__cmd">{{ $s['fullCommand'] }}</code>
                                    <span class="ls-schedule__sep" aria-hidden="true">·</span>
                                    <span class="ls-schedule__human">{{ $s['human'] }}</span>
                                    @if ($s['human'] !== $s['cron'])
                                        <span class="ls-schedule__cron" title="{{ __('backup-viewer::messages.common.cron_expression') }}">({{ $s['cron'] }})</span>
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
