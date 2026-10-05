<x-layouts.admin
    :title="__('ui.knowledge.create')"
    :heading="__('ui.knowledge.create')"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.knowledge'), 'href' => route('admin.knowledge.index')],
        ['label' => __('ui.knowledge.create')],
    ]"
>
    <form method="POST" action="{{ route('admin.knowledge.store') }}" novalidate>
        @csrf

        <x-ui.card>
            <p class="mb-6 text-body text-muted">{{ __('ui.knowledge.create_intro') }}</p>

            @include('admin.knowledge._form', ['draft' => null, 'slugLocked' => false, 'completeness' => []])

            <x-slot:footer>
                <x-ui.link-button :href="route('admin.knowledge.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.link-button>
                <x-ui.button type="submit">{{ __('ui.knowledge.save_draft') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
