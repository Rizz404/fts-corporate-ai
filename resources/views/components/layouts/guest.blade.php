{{-- Centered frame for sign-in, password change, and other guest pages. --}}
@props([
    'title' => null,
    'heading' => null,
    'description' => null,
    'width' => 'md',
])

@php
    $widthClass = $width === 'lg' ? 'max-w-2xl' : 'max-w-md';
@endphp

<x-layouts.base :title="$title">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <div class="flex min-h-dvh flex-col">
        <header class="flex items-center justify-between gap-4 px-4 py-4 md:px-8">
            <x-navigation.brand />
            <x-navigation.language-switch />
        </header>

        <main id="main-content" tabindex="-1" class="flex flex-1 items-center justify-center px-4 py-8 focus:outline-none">
            <div class="w-full {{ $widthClass }} space-y-4">
                @if (session('status'))
                    <x-ui.alert tone="info">{{ session('status') }}</x-ui.alert>
                @endif

                {{ $before ?? '' }}

                <div class="rounded-card border border-border bg-surface p-6 shadow-card md:p-8">
                    @if ($heading !== null)
                        <div class="mb-6 space-y-1">
                            <h1 class="text-page-title font-semibold text-text">{{ $heading }}</h1>
                            @if ($description !== null)
                                <p class="text-body text-muted">{{ $description }}</p>
                            @endif
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </div>
        </main>

        <footer class="px-4 py-6 text-center text-caption text-muted">
            &copy; {{ now()->year }} {{ config('corporate.brand.name') }} · {{ __('ui.footer.internal') }}
        </footer>
    </div>
</x-layouts.base>
