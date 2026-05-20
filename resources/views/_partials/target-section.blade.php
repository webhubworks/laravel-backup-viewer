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
        <span class="ls-badge ls-badge--muted">NO DATA</span>
    @elseif ($isHealthy)
        <span class="ls-badge ls-badge--ok">OK</span>
    @else
        <span class="ls-badge ls-badge--warning">WARNING</span>
    @endif

    <span class="ls-check-group__target">{{ $d['diskName'] }}</span>

    <span class="ls-check-group__count">
        @if (! $hasRecord)
            Awaiting first <code>backup:monitor</code> run
        @elseif ($failed > 0)
            {{ $failed }} of {{ $total }} checks failing
        @elseif ($skipped > 0)
            {{ $passed }} of {{ $total }} checks passed, {{ $skipped }} skipped
        @else
            {{ $passed }} of {{ $total }} checks passed
        @endif

        @if ($d['isStale'] ?? false)
            <span class="ls-badge ls-badge--muted" title="Monitor data is stale">stale</span>
        @endif
    </span>

    @unless ($flat)
        <span class="ls-check-group__chevron" aria-hidden="true"></span>
    @endunless
</{{ $HeaderTag }}>

@if ($hasRecord)
    <div class="ls-check-group__meta">
        @if (is_int($d['amountOfBackups']))
            <span><strong>{{ $d['amountOfBackups'] }}</strong> backups</span>
        @endif
        @if (is_int($d['newestBackupAt']))
            <span>newest: {{ Format::relativeTime($d['newestBackupAt']) }}</span>
        @endif
        @if (is_int($d['usedStorageBytes']))
            <span>{{ Format::bytes($d['usedStorageBytes']) }} stored</span>
        @endif
        @if (is_int($d['checkedAt']))
            <span class="ls-muted">checked {{ Format::relativeTime($d['checkedAt']) }}</span>
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
