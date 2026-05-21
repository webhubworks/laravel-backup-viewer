<div class="ls-card ls-card--full">
    <div class="ls-card__header">
        <h2 class="ls-card__title">{{ __('backup-viewer::messages.notifications.title') }}</h2>
    </div>

    <div class="ls-card__body">
        @if (empty($events))
            @include('backup-viewer::_partials.notice', [
                'tone' => 'info',
                'title' => __('backup-viewer::messages.notifications.none_title'),
                'body' => __('backup-viewer::messages.notifications.none_body_html'),
            ])
        @else
            <div class="ls-table-wrap">
                <table class="ls-table">
                    <thead>
                        <tr>
                            <th>{{ __('backup-viewer::messages.notifications.col_event') }}</th>
                            <th>{{ __('backup-viewer::messages.notifications.col_channels') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr>
                                <td>
                                    <div>{{ $event['label'] }}</div>
                                    <div class="ls-mono ls-muted">{{ $event['class'] }}</div>
                                </td>
                                <td>
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
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
