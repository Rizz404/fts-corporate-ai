@props([
    'variant' => 'light',
    'href' => null,
])

@php
    $nameClasses = $variant === 'dark' ? 'text-on-primary' : 'text-text';
    $tag = $href !== null ? 'a' : 'span';
@endphp

<{{ $tag }} @if ($href !== null) href="{{ $href }}" @endif {{ $attributes->class('inline-flex min-w-0 items-center gap-2 rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus') }}>
    <span class="flex size-8 shrink-0 items-center justify-center rounded-control bg-primary text-caption font-bold text-on-primary" aria-hidden="true">
        {{ config('corporate.brand.short_name') }}
    </span>
    <span class="truncate text-body font-semibold {{ $nameClasses }}">{{ config('corporate.brand.name') }}</span>
</{{ $tag }}>
