@php
    $channelNames = collect($events ?? [])
        ->flatMap(fn ($event) => collect($event['channels'])->pluck('name'))
        ->unique()
        ->values();
@endphp

<details class="ls-card ls-card--full ls-card--collapsible">
    <summary class="ls-card__header ls-card__header--summary">
        <h2 class="ls-card__title">{{ __('backup-viewer::messages.notifications.title') }}</h2>
        <span class="ls-card__header-meta">
            @if (empty($events))
                <span class="ls-muted">{{ __('backup-viewer::messages.notifications.none_short') }}</span>
            @else
                <span>{{ __('backup-viewer::messages.notifications.summary', [
                    'count' => count($events),
                    'channels' => $channelNames->join(', '),
                ]) }}</span>
            @endif
            <span class="ls-card__chevron" aria-hidden="true"></span>
        </span>
    </summary>

    <div class="ls-card__body">
        @if (empty($events))
            @include('backup-viewer::_partials.notice', [
                'tone' => 'info',
                'title' => __('backup-viewer::messages.notifications.none_title'),
                'body' => __('backup-viewer::messages.notifications.none_body_html'),
            ])
        @else
            <ul class="ls-notifications">
                @foreach ($events as $event)
                    <li class="ls-notifications__row">
                        <div class="ls-notifications__event">
                            <div class="ls-notifications__label">{{ $event['label'] }}</div>
                            <div class="ls-notifications__class ls-mono">{{ $event['class'] }}</div>
                        </div>
                        <ul class="ls-channels">
                            @foreach ($event['channels'] as $channel)
                                <li class="ls-channels__row">
                                    <span class="ls-channel ls-channel--{{ Illuminate\Support\Str::slug($channel['name']) }}">{{ $channel['name'] }}</span>
                                    @if ($channel['status'] !== 'unknown')
                                        <span class="ls-channel__arrow" aria-hidden="true">→</span>
                                        @if ($channel['status'] === 'configured')
                                            <span class="ls-channel__target">{{ $channel['target'] }}</span>
                                        @elseif ($channel['status'] === 'fallback')
                                            <span class="ls-channel__target ls-channel__target--fallback">{{ __('backup-viewer::messages.notifications.default_channel') }}</span>
                                        @elseif ($channel['status'] === 'missing')
                                            <span class="ls-channel__target ls-channel__target--missing">{{ __('backup-viewer::messages.notifications.missing_recipient') }}</span>
                                        @endif
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</details>
