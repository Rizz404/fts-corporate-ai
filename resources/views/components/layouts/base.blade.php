{{-- The only document shell: one Vite bundle, CSRF meta, skip link, toast container. --}}
@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('corporate.brand.name') : config('corporate.brand.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body {{ $attributes->class('min-h-dvh bg-canvas font-sans text-body text-text antialiased') }}>
    <a href="#main-content" class="skip-link">{{ __('ui.skip_to_content') }}</a>

    {{ $slot }}

    <x-ui.toast-container />
</body>
</html>
