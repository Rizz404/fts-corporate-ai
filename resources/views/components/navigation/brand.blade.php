@props([
    'variant' => 'light',
    'href' => null,
])

@php
    $nameClasses = $variant === 'dark' ? 'text-on-primary' : 'text-text';
    $tag = $href !== null ? 'a' : 'span';
@endphp

<{{ $tag }} @if ($href !== null) href="{{ $href }}" @endif {{ $attributes->class('inline-flex min-w-0 items-center gap-2 rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus') }}>
    {{-- Brand mark (public/assets/corporate/logo.svg) inlined so it follows theme tokens. --}}
    <svg class="size-8 shrink-0" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <rect width="64" height="64" rx="16" class="fill-primary" />
        <path d="M18 16h28a6 6 0 0 1 6 6v18a6 6 0 0 1-6 6H30l-9 7v-7h-3a6 6 0 0 1-6-6V22a6 6 0 0 1 6-6Z" class="fill-on-primary" />
        <g class="fill-primary">
            <rect x="21" y="23" width="6" height="6" rx="1.5" /><rect x="29" y="23" width="6" height="6" rx="1.5" /><rect x="37" y="23" width="6" height="6" rx="1.5" />
            <rect x="21" y="32" width="6" height="6" rx="1.5" /><rect x="29" y="32" width="6" height="6" rx="1.5" /><rect x="37" y="32" width="6" height="6" rx="1.5" />
        </g>
    </svg>
    <span class="truncate text-body font-semibold {{ $nameClasses }}">{{ config('corporate.brand.name') }}</span>
</{{ $tag }}>
