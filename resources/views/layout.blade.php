<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Backups</title>
    @if (! empty($faviconHtml))
        {!! $faviconHtml !!}
    @elseif (! empty($faviconPath))
        @php
            $faviconType = match (true) {
                str_ends_with($faviconPath, '.svg') => 'image/svg+xml',
                str_ends_with($faviconPath, '.png') => 'image/png',
                str_ends_with($faviconPath, '.ico') => 'image/x-icon',
                default => null,
            };
        @endphp
        <link rel="icon"@if ($faviconType) type="{{ $faviconType }}"@endif href="{{ asset(ltrim($faviconPath, '/')) }}">
    @endif
    {!! \Webhub\BackupViewer\BackupViewer::css() !!}
    {!! \Webhub\BackupViewer\BackupViewer::js() !!}
</head>
<body class="ls-backup">
    <div class="ls-page">
        @yield('content')
    </div>
</body>
</html>
