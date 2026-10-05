{{--
    Office scene composed inside x-layouts.user. All data comes from SceneData.
    Default slot = scene content panel. Optional slot: chat (until Plan 5 a notice is shown).
--}}
@props([
    'scene',
    'adminHref' => null,
])

@php
    $stageConfig = [
        'userId' => auth()->id(),
        'intro' => $scene->intro,
        'lang' => $scene->speechLang(),
    ];
@endphp

<x-layouts.user :title="$scene->title" :nav-items="$scene->menu" :admin-href="$adminHref">
    <x-slot:backdrop>
        <div x-data="sceneImage" class="relative h-full w-full bg-surface-muted">
            <img
                x-ref="image"
                src="{{ $scene->backdropUrl }}"
                alt=""
                decoding="async"
                referrerpolicy="no-referrer"
                x-show="! failed"
                x-on:error="failed = true"
                class="h-full w-full object-cover"
            >
            <div class="absolute inset-0 bg-overlay opacity-60"></div>
        </div>
    </x-slot:backdrop>

    <x-slot:character>
        <figure class="overflow-hidden rounded-card border border-border bg-surface shadow-card">
            <div x-data="sceneImage" class="relative flex aspect-3/4 items-center justify-center bg-surface-muted">
                <img
                    x-ref="image"
                    src="{{ $scene->characterUrl }}"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    referrerpolicy="no-referrer"
                    x-show="! failed"
                    x-on:error="failed = true"
                    class="h-full w-full object-cover"
                >
                <x-ui.icon name="sparkles" size="lg" class="absolute text-primary" x-show="failed" x-cloak />
            </div>
            <figcaption class="px-4 py-3 text-body font-semibold text-text">{{ $scene->characterName }}</figcaption>
        </figure>
    </x-slot:character>

    <div x-data="officeStage(@js($stageConfig))" class="space-y-4">
        <section class="rounded-card border border-border bg-surface p-4 shadow-card md:p-6" aria-labelledby="scene-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h1 id="scene-title" class="text-page-title font-semibold text-text">{{ $scene->title }}</h1>

                <div class="flex items-center gap-2">
                    <x-ui.button
                        variant="outline"
                        size="sm"
                        x-show="narratorSupported"
                        x-cloak
                        x-on:click="toggleNarrator()"
                        aria-pressed="false"
                        x-bind:aria-pressed="speaking"
                    >
                        <x-ui.icon name="chat-bubble-left-right" size="sm" />
                        <span x-text="speaking ? @js(__('ui.office.narrator_stop')) : @js(__('ui.office.narrator_play'))">{{ __('ui.office.narrator_play') }}</span>
                    </x-ui.button>
                    <x-ui.button
                        variant="ghost"
                        size="sm"
                        x-on:click="toggleSound()"
                        aria-pressed="false"
                        x-bind:aria-pressed="soundOn"
                    >
                        <span x-text="soundOn ? @js(__('ui.office.sound_on')) : @js(__('ui.office.sound_off'))">{{ __('ui.office.sound_off') }}</span>
                    </x-ui.button>
                </div>
            </div>

            <p class="mt-2 max-w-3xl text-body text-text whitespace-pre-line">{{ $scene->intro }}</p>

            @if ($scene->suggestedTopics !== [])
                <div class="mt-4">
                    <h2 class="text-caption font-semibold tracking-wide text-muted uppercase">{{ __('ui.office.suggested_topics') }}</h2>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($scene->suggestedTopics as $topic)
                            <li><x-ui.badge tone="info">{{ $topic }}</x-ui.badge></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        {{ $slot }}
    </div>

    <x-slot:chat>
        @isset($chat)
            {{ $chat }}
        @else
            <div class="p-4">
                <x-ui.empty-state icon="chat-bubble-left-right" :title="__('ui.chat.title')" :description="__('ui.office.chat_coming')" class="border-0" />
            </div>
        @endisset
    </x-slot:chat>

    <x-slot:head>
        <script type="application/json" id="office-context">@json($scene->context)</script>
    </x-slot:head>
</x-layouts.user>
