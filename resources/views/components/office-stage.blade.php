{{--
    Office scene composed inside x-layouts.user (the AI presenter). All data comes from SceneData.
    The reception is a slide deck: the character introduces the app (welcome, one slide per room, then "ask me")
    with typed subtitles, optional voice, autoplay, keyboard and swipe navigation.
    Every other room is a slide canvas: the character presents the room beside the page content (default slot).
    Optional slot: chat.
--}}
@props([
    'scene',
    'adminHref' => null,
])

@php
    $accents = ['reception' => 'iris', 'knowledge' => 'mint', 'hr' => 'coral', 'it' => 'primary', 'requests' => 'iris'];
    $code = $scene->code->value;
    $isReception = $scene->code === \App\Modules\Experience\Enums\SceneCode::Reception;
    $firstName = auth()->user() ? \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ') : null;
    $brand = config('corporate.brand.name');
    $placeholder = app(\App\Modules\Experience\Support\SceneImages::class)->source() === \App\Modules\Experience\Support\SceneImages::SOURCE_PLACEHOLDER;
    $rooms = array_values(array_filter($scene->menu, fn (array $room) => ! $room['active']));

    $slides = [];
    if ($isReception) {
        $slides[] = ['kind' => 'welcome', 'accent' => 'iris', 'title' => $scene->title, 'text' => $scene->intro, 'label' => $scene->title, 'icon' => 'sparkles', 'image' => $scene->backdropUrl];
        foreach ($rooms as $room) {
            $roomCode = $room['code'];
            $points = __('ui.office.scenes.'.$roomCode.'.services');
            $topics = __('ui.office.scenes.'.$roomCode.'.topics');
            $topics = is_array($topics) ? array_values($topics) : [];
            $slides[] = [
                'kind' => 'room',
                'accent' => $accents[$roomCode] ?? 'iris',
                'title' => $room['label'],
                'text' => __('ui.office.scenes.'.$roomCode.'.intro'),
                'label' => $room['label'],
                'icon' => $room['icon'],
                'image' => $room['image'],
                'href' => $room['href'],
                'summary' => __('ui.office.scenes.'.$roomCode.'.summary'),
                'points' => is_array($points) ? array_values($points) : $topics,
                'ask' => $topics[0] ?? null,
            ];
        }
        $slides[] = ['kind' => 'ask', 'accent' => 'mint', 'title' => __('ui.office.ask_prompt'), 'text' => __('ui.office.tour.finale'), 'label' => __('ui.office.tour.ask_label'), 'icon' => 'chat-bubble-left-right', 'image' => null];
    }
    $total = count($slides);

    $stageConfig = [
        'userId' => auth()->id(),
        'intro' => $scene->intro,
        'lang' => $scene->speechLang(),
        'accent' => $accents[$code] ?? 'iris',
        'slides' => array_map(fn (array $slide) => ['text' => $slide['text'], 'title' => $slide['title'], 'accent' => $slide['accent']], $slides),
    ];

    $position = 1;
    foreach ($scene->menu as $i => $item) {
        if ($item['active']) {
            $position = $i + 1;
        }
    }
@endphp

<x-layouts.user
    :title="$scene->title"
    :nav-items="$scene->menu"
    :admin-href="$adminHref"
    :mode="$isReception ? 'deck' : 'room'"
    :accent="$accents[$code] ?? 'iris'"
    :scene="$code"
    :scene-label="$scene->title"
    :avatar="$scene->characterUrl"
    :stage="$stageConfig"
>
    @if (session('status'))
        <x-slot:flash>
            <div class="ws-flash">
                <x-ui.alert tone="success" dismissible>{{ session('status') }}</x-ui.alert>
            </div>
        </x-slot:flash>
    @endif

    @if ($isReception)
        <x-slot:topbar>
            <p class="slide-counter" aria-hidden="true">
                <b x-text="pad(index + 1)">01</b><span>/</span><span x-text="pad(count)">{{ sprintf('%02d', $total) }}</span>
            </p>
        </x-slot:topbar>

        <x-slot:navigation>
            <nav class="sorter-nav" aria-label="{{ __('ui.office.tour.slides') }}">
                <ul>
                    @foreach ($slides as $i => $slide)
                        <li>
                            <button
                                type="button"
                                class="thumb focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-iris"
                                x-on:click="go({{ $i }})"
                                @if ($i === 0) aria-current="true" @endif
                                x-bind:aria-current="index === {{ $i }} ? 'true' : null"
                            >
                                @if ($slide['image'])
                                    <img src="{{ $slide['image'] }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                                @endif
                                <span class="thumb-num" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</span>
                                <span class="thumb-icon"><x-ui.icon :name="$slide['icon']" size="sm" /></span>
                                <span class="thumb-label">{{ $slide['label'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </x-slot:navigation>
    @endif

    @if ($isReception)
        <section
            class="deck"
            data-dir="next"
            data-char="right"
            x-bind:data-dir="direction"
            x-bind:data-char="index % 2 === 0 ? 'right' : 'left'"
            x-bind:class="{ 'is-typing': typing, 'is-speaking': speaking }"
            x-on:touchstart.passive="touchStart($event)"
            x-on:touchend.passive="touchEnd($event)"
            aria-roledescription="carousel"
            aria-label="{{ __('ui.office.tour.label') }}"
        >
            <x-office-character :url="$scene->characterUrl" :placeholder="$placeholder" />

            <div class="slides">
                @foreach ($slides as $i => $slide)
                    <article
                        class="slide {{ $i === 0 ? 'is-active' : '' }}"
                        data-char="{{ $i % 2 === 0 ? 'right' : 'left' }}"
                        x-bind:class="slideClass({{ $i }})"
                        aria-roledescription="slide"
                        aria-label="{{ $i + 1 }} / {{ $total }}"
                    >
                        @if ($slide['kind'] === 'welcome')
                            <p class="slide-eyebrow slide-in" style="--n: 0"><span class="status-dot" aria-hidden="true"></span>{{ $firstName ? __('ui.office.welcome', ['name' => $firstName]) : __('ui.brand.subtitle') }}</p>
                            <h1 id="scene-title" class="slide-title slide-in" style="--n: 1"><span class="sr-only">{{ $scene->title }}: </span><span class="grad">{{ $brand }}</span></h1>
                            <p class="slide-sub slide-in" style="--n: 2">{{ __('ui.tagline') }}</p>
                            <ul class="agenda slide-in" style="--n: 3" aria-label="{{ __('ui.office.tour.agenda') }}">
                                @foreach ($rooms as $room)
                                    <li>
                                        <a href="{{ $room['href'] }}" class="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                                            <span class="agenda-num">{{ sprintf('%02d', $loop->iteration + 1) }}</span>
                                            <span class="min-w-0">
                                                <span class="agenda-title">{{ $room['label'] }}</span>
                                                <span class="agenda-sub">{{ __('ui.office.scenes.'.$room['code'].'.summary') }}</span>
                                            </span>
                                            <x-ui.icon name="arrow-right" size="sm" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="slide-cta slide-in" style="--n: 4">
                                <x-ui.button variant="ai" size="lg" x-on:click="next()">
                                    {{ __('ui.office.tour.start') }}
                                    <x-ui.icon name="arrow-right" size="sm" />
                                </x-ui.button>
                            </div>
                        @elseif ($slide['kind'] === 'room')
                            <p class="slide-num slide-in" style="--n: 0" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</p>
                            <p class="slide-eyebrow slide-in" style="--n: 1"><x-ui.icon :name="$slide['icon']" size="sm" />{{ __('ui.office.explore') }}</p>
                            <h2 class="slide-title slide-in" style="--n: 2">{{ $slide['title'] }}</h2>
                            <p class="slide-sub slide-in" style="--n: 3">{{ $slide['summary'] }}</p>
                            @if ($slide['points'] !== [])
                                <ul class="points slide-in" style="--n: 4" aria-label="{{ __('ui.office.tour.points') }}">
                                    @foreach ($slide['points'] as $point)
                                        <li><span aria-hidden="true"><x-ui.icon name="check" size="sm" class="size-3.5!" /></span><span>{{ $point }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="slide-cta slide-in" style="--n: 5">
                                <x-ui.link-button :href="$slide['href']" variant="ai" size="lg">
                                    {{ __('ui.office.tour.enter', ['room' => $slide['label']]) }}
                                    <x-ui.icon name="arrow-right" size="sm" />
                                </x-ui.link-button>
                                @if ($slide['ask'])
                                    <button
                                        type="button"
                                        class="prompt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                        x-on:click="$dispatch('chat:ask', { message: {{ \Illuminate\Support\Js::from($slide['ask']) }} })"
                                    >
                                        <x-ui.icon name="sparkles" size="sm" />
                                        <span>{{ __('ui.office.tour.ask_here') }}</span>
                                    </button>
                                @endif
                            </div>
                        @else
                            <p class="slide-num slide-in" style="--n: 0" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</p>
                            <p class="slide-eyebrow slide-in" style="--n: 1"><x-ui.icon name="sparkles" size="sm" />{{ $slide['label'] }}</p>
                            <h2 class="slide-title slide-in" style="--n: 2"><span class="grad">{{ $slide['title'] }}</span></h2>
                            <p class="slide-sub slide-in" style="--n: 3">{{ __('ui.office.tour.finale') }}</p>
                            <form
                                class="cmd slide-in"
                                style="--n: 4"
                                role="search"
                                x-data="{ q: '' }"
                                x-on:submit.prevent="if (q.trim()) { $dispatch('chat:ask', { message: q.trim() }); q = ''; }"
                            >
                                <div class="cmd-inner">
                                    <x-ui.icon name="sparkles" class="text-primary" />
                                    <label for="hero-command" class="sr-only">{{ __('ui.chat.input_label') }}</label>
                                    <input
                                        id="hero-command"
                                        type="text"
                                        x-model="q"
                                        maxlength="2000"
                                        autocomplete="off"
                                        placeholder="{{ __('ui.office.command_placeholder') }}"
                                    >
                                    <button type="submit" class="send-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 focus-visible:ring-offset-surface" x-bind:disabled="! q.trim()">
                                        <x-ui.icon name="paper-airplane" />
                                        <span class="sr-only">{{ __('ui.chat.send') }}</span>
                                    </button>
                                </div>
                            </form>
                            @if ($scene->suggestedTopics !== [])
                                <ul class="prompts slide-in" style="--n: 5" aria-label="{{ __('ui.office.suggested_topics') }}">
                                    @foreach ($scene->suggestedTopics as $topic)
                                        <li>
                                            <button
                                                type="button"
                                                class="prompt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                                x-on:click="$dispatch('chat:ask', { message: {{ \Illuminate\Support\Js::from($topic) }} })"
                                            >
                                                <x-ui.icon name="sparkles" size="sm" />
                                                <span>{{ $topic }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="caption" role="group" aria-label="{{ __('ui.office.tour.captions') }}">
                <span x-data="sceneImage" class="caption-avatar" aria-hidden="true">
                    <img
                        x-ref="image"
                        src="{{ $scene->characterUrl }}"
                        alt=""
                        decoding="async"
                        referrerpolicy="no-referrer"
                        x-show="! failed"
                        x-on:error="failed = true"
                    >
                    <x-ui.icon name="sparkles" x-show="failed" x-cloak />
                </span>

                <div class="min-w-0">
                    <p class="caption-name" aria-hidden="true">
                        <span>{{ $scene->characterName }}</span>
                        <span class="thinking-dots" x-show="thinking" x-cloak><i style="--i: 0"></i><i style="--i: 1"></i><i style="--i: 2"></i></span>
                    </p>
                    {{-- Screen readers get the full line when the slide changes; the typed copy is visual only. --}}
                    <p class="sr-only" aria-live="polite" x-text="titleNow + ': ' + textNow">{{ $scene->intro }}</p>
                    <p class="caption-text ai-text" aria-hidden="true" data-pending x-init="$el.removeAttribute('data-pending')" x-text="typed">{{ $scene->intro }}</p>
                </div>

                <div class="caption-controls">
                    <div class="dots" role="group" aria-label="{{ __('ui.office.tour.slides') }}">
                        @foreach ($slides as $i => $slide)
                            <button
                                type="button"
                                class="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                aria-label="{{ $i + 1 }}: {{ $slide['label'] }}"
                                x-on:click="go({{ $i }})"
                                @if ($i === 0) aria-current="true" @endif
                                x-bind:aria-current="index === {{ $i }} ? 'true' : null"
                            ></button>
                        @endforeach
                    </div>

                    <div class="caption-buttons">
                        <button type="button" class="bubble-action focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-show="thinking || typing" x-cloak x-on:click="skipTyping()">{{ __('ui.office.skip') }}</button>

                        <button
                            type="button"
                            class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                            x-show="narratorSupported"
                            x-cloak
                            x-on:click="toggleNarrator()"
                            aria-pressed="false"
                            x-bind:aria-pressed="voice"
                        >
                            <x-ui.icon name="speaker-wave" size="sm" />
                            <span class="sr-only" x-text="voice ? @js(__('ui.office.narrator_stop')) : @js(__('ui.office.narrator_play'))">{{ __('ui.office.narrator_play') }}</span>
                        </button>

                        <button
                            type="button"
                            class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                            x-on:click="toggleAutoplay()"
                            aria-pressed="false"
                            x-bind:aria-pressed="autoplay"
                        >
                            <x-ui.icon name="play" size="sm" x-show="! autoplay" />
                            <x-ui.icon name="pause" size="sm" x-show="autoplay" x-cloak />
                            <span class="sr-only" x-text="autoplay ? @js(__('ui.office.tour.pause')) : @js(__('ui.office.tour.autoplay'))">{{ __('ui.office.tour.autoplay') }}</span>
                        </button>

                        <button type="button" class="icon-btn focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-on:click="prev()" x-bind:disabled="index === 0" disabled>
                            <x-ui.icon name="arrow-left" size="sm" />
                            <span class="sr-only">{{ __('ui.office.tour.prev') }}</span>
                        </button>
                        <button type="button" class="icon-btn icon-btn-ai focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-on:click="next()" x-bind:disabled="index === count - 1">
                            <x-ui.icon name="arrow-right" size="sm" />
                            <span class="sr-only">{{ __('ui.office.tour.next') }}</span>
                        </button>
                    </div>
                </div>

                <div class="progress" aria-hidden="true" x-bind:class="{ 'is-running': dwelling }" x-bind:style="'--dwell: ' + dwellMs + 'ms'"><i></i></div>
            </div>
        </section>
    @else
        <div class="room">
            <aside class="presenter" aria-label="{{ $scene->characterName }}" x-bind:class="{ 'is-typing': typing, 'is-speaking': speaking }">
                <x-office-character :url="$scene->characterUrl" :placeholder="$placeholder" />

                <div class="bubble">
                    <span x-data="sceneImage" class="caption-avatar" aria-hidden="true">
                        <img
                            x-ref="image"
                            src="{{ $scene->characterUrl }}"
                            alt=""
                            decoding="async"
                            referrerpolicy="no-referrer"
                            x-show="! failed"
                            x-on:error="failed = true"
                        >
                        <x-ui.icon name="sparkles" x-show="failed" x-cloak />
                    </span>

                    <div class="min-w-0">
                        <p class="caption-name" aria-hidden="true">
                            <span>{{ $scene->characterName }}</span>
                            <span class="thinking-dots" x-show="thinking" x-cloak><i style="--i: 0"></i><i style="--i: 1"></i><i style="--i: 2"></i></span>
                        </p>

                        <p class="sr-only">{{ $scene->intro }}</p>
                        <p class="bubble-text ai-text" aria-hidden="true" data-pending x-init="$el.removeAttribute('data-pending')" x-text="typed">{{ $scene->intro }}</p>

                        @if ($scene->suggestedTopics !== [])
                            <ul class="prompts mt-3" aria-label="{{ __('ui.office.suggested_topics') }}">
                                @foreach ($scene->suggestedTopics as $topic)
                                    <li>
                                        <button
                                            type="button"
                                            class="prompt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                            x-on:click="$dispatch('chat:ask', { message: {{ \Illuminate\Support\Js::from($topic) }} })"
                                        >
                                            <x-ui.icon name="sparkles" size="sm" />
                                            <span>{{ $topic }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="bubble-actions">
                            <button type="button" class="bubble-action focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus" x-show="thinking || typing" x-cloak x-on:click="skipTyping()">{{ __('ui.office.skip') }}</button>
                            <button
                                type="button"
                                class="bubble-action focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                x-show="narratorSupported"
                                x-cloak
                                x-on:click="toggleNarrator()"
                                aria-pressed="false"
                                x-bind:aria-pressed="voice"
                            >
                                <x-ui.icon name="speaker-wave" size="sm" />
                                <span x-text="voice ? @js(__('ui.office.narrator_stop')) : @js(__('ui.office.narrator_play'))">{{ __('ui.office.narrator_play') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>

            <section class="canvas" aria-labelledby="scene-title">
                <header class="canvas-head">
                    <img src="{{ $scene->backdropUrl }}" alt="" decoding="async" referrerpolicy="no-referrer">
                    <p class="slide-num" aria-hidden="true">{{ sprintf('%02d', $position) }}</p>
                    <div class="min-w-0 pb-1">
                        <p class="slide-eyebrow">{{ __('ui.office.explore') }}</p>
                        <h1 id="scene-title" class="slide-title">{{ $scene->title }}</h1>
                    </div>
                </header>
                <div class="canvas-body">
                    <div class="space-y-4">{{ $slot }}</div>
                </div>
            </section>
        </div>
    @endif

    <x-slot:chat>
        @isset($chat)
            {{ $chat }}
        @else
            <x-office-chat :scene="$scene" />
        @endisset
    </x-slot:chat>

    <x-slot:head>
        <script type="application/json" id="office-context">@json($scene->context)</script>
        <noscript><style>.ai-text[data-pending] { opacity: 1; }</style></noscript>
    </x-slot:head>
</x-layouts.user>
