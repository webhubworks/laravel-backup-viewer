{{-- $title, $body. Body can include HTML (uses {!! !!}). --}}
<div class="ls-inline-notice ls-inline-notice--{{ $tone ?? 'info' }}" role="status">
    @if (! empty($title))
        <div class="ls-inline-notice__title">{{ $title }}</div>
    @endif
    <div class="ls-inline-notice__body">{!! $body !!}</div>
</div>
