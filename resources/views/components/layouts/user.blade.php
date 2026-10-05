{{--
    Employee office frame. Plan 3 fills the stage slots (backdrop, character,
    scene content); Plan 5 fills the chat slot.
    Slots: default (scene content), backdrop, character, chat, navigation (overrides navItems).
--}}
@props([
    'title' => null,
    'navItems' => [],
])

<x-layouts.base :title="$title">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <div x-data="userShell" class="flex min-h-dvh flex-col">
        <header class="sticky top-0 z-30 bg-secondary text-on-primary">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 md:px-6">
                <x-navigation.brand variant="dark" />

                <div class="ml-auto hidden md:block">
                    @isset($navigation)
                        {{ $navigation }}
                    @else
                        <x-navigation.user-menu :items="$navItems" />
                    @endisset
                </div>

                <x-navigation.language-switch variant="dark" class="ml-auto md:ml-0" />

                <button
                    type="button"
                    x-ref="menuButton"
                    x-on:click="toggleMenu()"
                    aria-controls="office-menu"
                    aria-expanded="false"
                    x-bind:aria-expanded="menuOpen"
                    class="rounded-control p-2 text-on-primary/80 hover:text-on-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-on-primary md:hidden"
                >
                    <x-ui.icon name="bars-3" :label="__('ui.nav.open_menu')" />
                </button>
            </div>

            <div
                id="office-menu"
                x-show="menuOpen"
                x-collapse
                x-cloak
                x-on:keydown.escape.prevent.stop="closeMenu(); $refs.menuButton.focus()"
                class="border-t border-secondary-hover md:hidden"
            >
                <div class="px-4 py-3">
                    @isset($navigation)
                        {{ $navigation }}
                    @else
                        <x-navigation.user-menu :items="$navItems" orientation="vertical" />
                    @endisset
                </div>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="relative isolate flex-1 focus:outline-none">
            @isset($backdrop)
                <div class="absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">{{ $backdrop }}</div>
            @endisset

            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 md:flex-row md:items-end md:px-6 md:py-10">
                @isset($character)
                    <div class="shrink-0 md:w-64">{{ $character }}</div>
                @endisset
                <div class="min-w-0 flex-1">{{ $slot }}</div>
            </div>
        </main>

        @isset($chat)
            <div class="fixed right-4 bottom-4 z-40 flex flex-col items-end gap-3">
                <section
                    id="chat-dock"
                    x-show="chatOpen"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    x-on:keydown.escape.prevent.stop="closeChat(); $refs.chatButton.focus()"
                    aria-labelledby="chat-dock-title"
                    class="fixed inset-0 flex flex-col bg-surface sm:static sm:inset-auto sm:h-128 sm:w-96 sm:rounded-card sm:border sm:border-border sm:shadow-overlay"
                >
                    <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <h2 id="chat-dock-title" class="text-section-title font-semibold text-text">{{ __('ui.chat.title') }}</h2>
                        <button
                            type="button"
                            x-on:click="closeChat(); $refs.chatButton.focus()"
                            class="-m-1 rounded-control p-1 text-muted hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                        >
                            <x-ui.icon name="x-mark" :label="__('ui.chat.close')" />
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto">{{ $chat }}</div>
                </section>

                <button
                    type="button"
                    x-ref="chatButton"
                    x-on:click="toggleChat()"
                    aria-controls="chat-dock"
                    aria-expanded="false"
                    x-bind:aria-expanded="chatOpen"
                    x-bind:class="chatOpen ? 'max-sm:hidden' : ''"
                    class="{{ \App\Support\Ui\ComponentStyles::button('primary', 'lg') }} shadow-overlay"
                >
                    <x-ui.icon name="chat-bubble-left-right" />
                    <span>{{ __('ui.chat.open') }}</span>
                </button>
            </div>
        @endisset
    </div>
</x-layouts.base>
