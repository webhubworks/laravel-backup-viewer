@php
    use Webhub\BackupViewer\Support\Format;

    $monitorEnabled = (bool) ($health['monitorEnabled'] ?? false);
    $state = $health['state'] ?? [];
    $lastRun = is_array($state['lastRun'] ?? null) ? $state['lastRun'] : null;
    $lastSuccessful = is_array($state['lastSuccessfulRun'] ?? null) ? $state['lastSuccessfulRun'] : null;

    $destinations = $health['destinations'] ?? [];
    $hasAnyMonitorRecord = collect($destinations)->contains(fn ($d) => $d['hasRecord'] ?? false);
    $allHealthy = $monitorEnabled && $hasAnyMonitorRecord
        ? collect($destinations)
            ->filter(fn ($d) => $d['hasRecord'] ?? false)
            ->every(fn ($d) => ($d['isHealthy'] ?? false) === true)
        : null;

    $lastRunFailed = $lastRun && ($lastRun['status'] ?? null) !== 'ok';

    if ($lastRunFailed) {
        $tone = 'error';
        $heading = __('backup-viewer::messages.hero.heading_last_run_failed');
    } elseif ($monitorEnabled && $hasAnyMonitorRecord && $allHealthy === false) {
        $tone = 'warning';
        $heading = __('backup-viewer::messages.hero.heading_unhealthy');
    } elseif ($monitorEnabled && $hasAnyMonitorRecord && $allHealthy === true) {
        $tone = 'ok';
        $heading = __('backup-viewer::messages.hero.heading_healthy');
    } elseif (! $lastSuccessful) {
        $tone = 'idle';
        $heading = __('backup-viewer::messages.hero.heading_no_backups_yet');
    } else {
        $tone = 'idle';
        $heading = __('backup-viewer::messages.hero.heading_running');
    }

    $nextRunEntry = collect($schedule ?? [])
        ->firstWhere('command', 'backup:run');
@endphp

<div class="ls-hero ls-hero--{{ $tone }}">
    <div class="ls-hero__icon" aria-hidden="true">
        @if ($tone === 'ok')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
        @elseif ($tone === 'warning' || $tone === 'error')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <line x1="12" y1="8" x2="12" y2="13"/>
                <line x1="12" y1="16.5" x2="12" y2="16.51"/>
            </svg>
        @else
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        @endif
    </div>

    <div class="ls-hero__body">
        <h1 class="ls-hero__heading">{{ $heading }}</h1>
        <p class="ls-hero__summary">
            @if ($lastSuccessful)
                {!! __('backup-viewer::messages.hero.summary_last_success_html', [
                    'time' => Format::relativeTime((int) $lastSuccessful['at'])->toHtml(),
                ]) !!}
            @else
                {{ __('backup-viewer::messages.hero.summary_no_successful_run') }}
            @endif

            @if ($nextRunEntry && is_int($nextRunEntry['nextRunAt'] ?? null))
                <span class="ls-hero__separator" aria-hidden="true">·</span>
                {!! __('backup-viewer::messages.hero.summary_next_run_html', [
                    'time' => Format::relativeTime((int) $nextRunEntry['nextRunAt'])->toHtml(),
                ]) !!}
            @endif
        </p>

        @if ($lastRunFailed && ! empty($lastRun['errors']))
            <div class="ls-hero__detail">
                <strong>{{ __('backup-viewer::messages.health.failure_reason') }}:</strong>
                <span>{{ collect($lastRun['errors'])->first() }}</span>
            </div>
        @endif

        @if (! $monitorEnabled)
            <p class="ls-hero__hint">
                {!! __('backup-viewer::messages.hero.monitoring_off_html') !!}
            </p>
        @endif
    </div>
</div>
