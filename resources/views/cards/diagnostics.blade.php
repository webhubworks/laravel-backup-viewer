@php
    use Webhub\BackupViewer\Support\Format;

    $state = $health['state'] ?? [];
    $lastMonitor = is_array($state['lastMonitor'] ?? null) ? $state['lastMonitor'] : null;
    $monitorEnabled = (bool) ($health['monitorEnabled'] ?? false);

    $rows = [
        'backup:run' => __('backup-viewer::messages.health.backup_schedule'),
        'backup:monitor' => __('backup-viewer::messages.health.monitor_schedule'),
    ];
@endphp

<details class="ls-diagnostics">
    <summary class="ls-diagnostics__summary">
        <span>{{ __('backup-viewer::messages.diagnostics.title') }}</span>
        <span class="ls-card__chevron" aria-hidden="true"></span>
    </summary>

    <dl class="ls-diagnostics__rows">
        @foreach ($rows as $needle => $rowLabel)
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

        @if ($monitorEnabled)
            <dt>{{ __('backup-viewer::messages.health.last_monitor_run') }}</dt>
            <dd>
                @if (is_int($lastMonitor['at'] ?? null))
                    {{ Format::relativeTime((int) $lastMonitor['at']) }}
                @else
                    <span class="ls-muted">{{ __('backup-viewer::messages.common.never') }}</span>
                @endif
            </dd>
        @endif
    </dl>
</details>
