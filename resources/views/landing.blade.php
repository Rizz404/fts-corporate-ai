<x-layouts.guest
    :heading="__('ui.landing.heading')"
    :description="__('ui.landing.description')"
    width="lg"
>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge tone="info">{{ __('ui.landing.status') }}</x-ui.badge>
            <p class="text-body text-muted">{{ __('ui.tagline') }}</p>
        </div>

        @if ($showPreviews)
            <div class="space-y-3">
                <h2 class="text-section-title font-semibold text-text">{{ __('ui.landing.previews') }}</h2>
                <ul class="grid gap-2 sm:grid-cols-2">
                    @foreach ([
                        ['route' => 'dev.ui', 'label' => __('ui.landing.preview_gallery'), 'icon' => 'squares-2x2'],
                        ['route' => 'dev.admin', 'label' => __('ui.landing.preview_admin'), 'icon' => 'cog-6-tooth'],
                        ['route' => 'dev.user', 'label' => __('ui.landing.preview_user'), 'icon' => 'building-office-2'],
                        ['route' => 'dev.guest', 'label' => __('ui.landing.preview_guest'), 'icon' => 'user-circle'],
                    ] as $preview)
                        <li>
                            <x-ui.link-button :href="route($preview['route'])" variant="outline" class="w-full justify-start">
                                <x-ui.icon :name="$preview['icon']" class="text-primary" />
                                {{ $preview['label'] }}
                            </x-ui.link-button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-layouts.guest>
