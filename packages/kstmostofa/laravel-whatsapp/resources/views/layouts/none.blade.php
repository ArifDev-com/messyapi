<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'WhatsApp' }}</title>

    @php
        $cssMode = config('laravel-whatsapp.ui.css_mode', 'vite');
    @endphp
    @if ($cssMode === 'vite')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @elseif ($cssMode === 'standalone')
        <link rel="stylesheet" href="{{ route('whatsapp.ui.assets.css') }}">
    @endif
    {{-- @fluxAppearance --}}
    @livewireStyles
    <style>[x-cloak]{display:none!important}</style>

    <script>
        localStorage.setItem('flux-appearance', 'light');
    </script>
</head>
<body class="min-h-screen">

@php
    $prefix = config('laravel-whatsapp.ui.route_prefix', 'whatsapp');
@endphp

<flux:main container class="!max-w-screen-2xl">
    {{ $slot }}
</flux:main>


@fluxScripts
@livewireScripts
</body>
</html>
