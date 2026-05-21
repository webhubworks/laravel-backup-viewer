@php
    use Webhub\BackupViewer\Support\Format;

    $state = $health['state'] ?? [];
    $lastRun = is_array($state['lastRun'] ?? null) ? $state['lastRun'] : null;
    $lastSuccessful = is_array($state['lastSuccessfulRun'] ?? null) ? $state['lastSuccessfulRun'] : null;

    $nextRunEntry = collect($schedule ?? [])->firstWhere('command', 'backup:run');

    $totalBackupCount = 0;
    $totalBackupBytes = 0;
    foreach ($byTarget ?? [] as $target) {
        $totalBackupCount += count($target['backups'] ?? []);
        $totalBackupBytes += (int) ($target['backupsBytes'] ?? 0);
    }

    $diskTarget = collect($byTarget ?? [])
        ->first(fn ($t) => is_array($t['diskUsage'] ?? null));
    $diskUsage = $diskTarget['diskUsage'] ?? null;
    $diskTotal = is_array($diskUsage) ? (int) ($diskUsage['total'] ?? 0) : 0;
    $diskFree = is_array($diskUsage) ? (int) ($diskUsage['free'] ?? 0) : 0;
    $diskFreePct = $diskTotal > 0 ? ($diskFree / $diskTotal) * 100 : null;
    $diskLow = $diskFreePct !== null
        && $lowDiskSpaceThreshold > 0
        && ($diskFreePct / 100) < $lowDiskSpaceThreshold;
@endphp

<div class="ls-metrics">
    <div class="ls-metric">
        <div class="ls-metric__label">{{ __('backup-viewer::messages.metrics.last_successful') }}</div>
        <div class="ls-metric__value">
            @if ($lastSuccessful)
                {{ Format::relativeTime((int) $lastSuccessful['at']) }}
            @else
                <span class="ls-muted">{{ __('backup-viewer::messages.common.never') }}</span>
            @endif
        </div>
        @if ($lastRun)
            <div class="ls-metric__footnote">
                @if (($lastRun['status'] ?? null) === 'ok')
                    <span class="ls-badge ls-badge--ok ls-badge--sm">{{ __('backup-viewer::messages.badges.ok') }}</span>
                @else
                    <span class="ls-badge ls-badge--failed ls-badge--sm">{{ __('backup-viewer::messages.badges.failed') }}</span>
                @endif
                <span>{{ __('backup-viewer::messages.metrics.last_run_prefix') }} {{ Format::relativeTime((int) $lastRun['at']) }}</span>
            </div>
        @endif
    </div>

    <div class="ls-metric">
        <div class="ls-metric__label">{{ __('backup-viewer::messages.metrics.next_run') }}</div>
        <div class="ls-metric__value">
            @if ($nextRunEntry && is_int($nextRunEntry['nextRunAt'] ?? null))
                {{ Format::relativeTime((int) $nextRunEntry['nextRunAt']) }}
            @else
                <span class="ls-muted">{{ __('backup-viewer::messages.common.not_scheduled') }}</span>
            @endif
        </div>
        @if ($nextRunEntry)
            <div class="ls-metric__footnote">
                <span>{{ $nextRunEntry['human'] }}</span>
            </div>
        @endif
    </div>

    <div class="ls-metric">
        <div class="ls-metric__label">{{ __('backup-viewer::messages.metrics.backups') }}</div>
        <div class="ls-metric__value">
            {{ $totalBackupCount }}
        </div>
        <div class="ls-metric__footnote">
            @if ($totalBackupBytes > 0)
                <span>{{ Format::bytes($totalBackupBytes) }} {{ __('backup-viewer::messages.metrics.total_size_suffix') }}</span>
            @else
                <span class="ls-muted">—</span>
            @endif
        </div>
    </div>

    <div class="ls-metric @if ($diskLow) ls-metric--alert @endif">
        <div class="ls-metric__label">{{ __('backup-viewer::messages.metrics.free_disk') }}</div>
        <div class="ls-metric__value">
            @if ($diskFreePct !== null)
                {{ Format::bytes($diskFree) }}
            @else
                <span class="ls-muted">—</span>
            @endif
        </div>
        @if ($diskFreePct !== null)
            <div class="ls-metric__footnote">
                <span>{{ number_format($diskFreePct, 1) }}% {{ __('backup-viewer::messages.metrics.free_suffix') }}</span>
                <span class="ls-muted">{{ __('backup-viewer::messages.disk.of') }} {{ Format::bytes($diskTotal) }}</span>
            </div>
        @endif
    </div>
</div>
