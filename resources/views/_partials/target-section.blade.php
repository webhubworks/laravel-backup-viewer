@php
    use Webhub\BackupViewer\Support\Format;

    $items = $d['checkItems'] ?? [];
    $total = count($items);
    $passed = collect($items)->where('status', 'ok')->count();
    $failed = collect($items)->where('status', 'failure')->count();
    $skipped = collect($items)->where('status', 'skipped')->count();
    $hasRecord = (bool) ($d['hasRecord'] ?? false);
    $isHealthy = $hasRecord && ($d['isHealthy'] ?? false) === true && $failed === 0;
    $flat = $flat ?? false;
    $HeaderTag = $flat ? 'div' : 'summary';
    $headerClass = $flat ? 'ls-target-header' : 'ls-check-group__summary';
@endphp

<{{ $HeaderTag }} class="{{ $headerClass }}">
    @if (! $hasRecord)
        <span class="ls-badge ls-badge--muted">{{ __('backup-viewer::messages.badges.no_data') }}</span>
    @elseif ($isHealthy)
        <span class="ls-badge ls-badge--ok">{{ __('backup-viewer::messages.badges.ok') }}</span>
    @else
        <span class="ls-badge ls-badge--warning">{{ __('backup-viewer::messages.badges.warning') }}</span>
    @endif

    <span class="ls-check-group__target">{{ $d['diskName'] }}</span>

    <span class="ls-check-group__count">
        @if (! $hasRecord)
            {!! __('backup-viewer::messages.per_target.awaiting_monitor_html') !!}
        @elseif ($failed > 0)
            {{ __('backup-viewer::messages.per_target.checks_failing', ['failed' => $failed, 'total' => $total]) }}
        @elseif ($skipped > 0)
            {{ __('backup-viewer::messages.per_target.checks_partial', ['passed' => $passed, 'total' => $total, 'skipped' => $skipped]) }}
        @else
            {{ __('backup-viewer::messages.per_target.checks_passed', ['passed' => $passed, 'total' => $total]) }}
        @endif

        @if ($d['isStale'] ?? false)
            <span class="ls-badge ls-badge--muted" title="{{ __('backup-viewer::messages.badges.stale_title') }}">{{ __('backup-viewer::messages.badges.stale') }}</span>
        @endif
    </span>

    @unless ($flat)
        <span class="ls-check-group__chevron" aria-hidden="true"></span>
    @endunless
</{{ $HeaderTag }}>

@if ($hasRecord)
    <div class="ls-check-group__meta">
        @if (is_int($d['amountOfBackups']))
            <span><strong>{{ $d['amountOfBackups'] }}</strong> {{ __('backup-viewer::messages.per_target.backups_suffix') }}</span>
        @endif
        @if (is_int($d['newestBackupAt']))
            <span>{{ __('backup-viewer::messages.per_target.newest_prefix') }} {{ Format::relativeTime($d['newestBackupAt']) }}</span>
        @endif
        @if (is_int($d['usedStorageBytes']))
            <span>{{ Format::bytes($d['usedStorageBytes']) }} {{ __('backup-viewer::messages.per_target.stored_suffix') }}</span>
        @endif
        @if (is_int($d['checkedAt']))
            <span class="ls-muted">{{ __('backup-viewer::messages.per_target.checked_prefix') }} {{ Format::relativeTime($d['checkedAt']) }}</span>
        @endif
    </div>
@endif

@if (! empty($items))
    <ul class="ls-check-list">
        @foreach ($items as $item)
            <li class="ls-check-list__item ls-check-list__item--{{ $item['status'] }}">
                <span class="ls-check-icon ls-check-icon--{{ $item['status'] }}" aria-hidden="true">
                    @if ($item['status'] === 'ok') ✓
                    @elseif ($item['status'] === 'failure') ✕
                    @else –
                    @endif
                </span>
                <span class="ls-check-list__label">{{ $item['label'] }}</span>
                @if (! empty($item['detail']))
                    <span class="ls-check-list__detail">{{ $item['detail'] }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
