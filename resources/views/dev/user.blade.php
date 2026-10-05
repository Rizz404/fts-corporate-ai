<x-layouts.user :title="__('ui.dev.user_title')" :nav-items="$navItems">
    <x-slot:backdrop>
        <div class="h-full w-full bg-surface-muted"></div>
    </x-slot:backdrop>

    <x-slot:character>
        <div class="flex aspect-[3/4] items-center justify-center rounded-card border border-dashed border-border bg-surface/80 text-center text-caption text-muted">
            <span class="flex flex-col items-center gap-2">
                <x-ui.icon name="sparkles" size="lg" class="text-primary" />
                {{ __('ui.dev.user_character') }}
            </span>
        </div>
    </x-slot:character>

    <div class="space-y-4">
        @include('dev._banner')

        <x-ui.card :title="__('ui.dev.user_greeting')">
            <p>{{ __('ui.dev.user_intro') }}</p>
            <p class="mt-2 text-muted">{{ __('ui.dev.synthetic_note') }}: {{ $unsafeSample }}</p>
            <x-slot:footer>
                <x-ui.link-button variant="outline" disabled>{{ __('ui.nav.rooms.knowledge') }}</x-ui.link-button>
                <x-ui.link-button variant="outline" disabled>{{ __('ui.nav.rooms.requests') }}</x-ui.link-button>
            </x-slot:footer>
        </x-ui.card>
    </div>

    <x-slot:chat>
        <div class="p-4">
            <x-ui.empty-state icon="chat-bubble-left-right" :title="__('ui.chat.title')" :description="__('ui.dev.user_chat_body')" class="border-0" />
        </div>
    </x-slot:chat>
</x-layouts.user>
