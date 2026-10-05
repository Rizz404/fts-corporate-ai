<x-layouts.admin
    :title="__('ui.departments.create')"
    :heading="__('ui.departments.create')"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.departments'), 'href' => route('admin.departments.index')],
        ['label' => __('ui.departments.create')],
    ]"
>
    <form method="POST" action="{{ route('admin.departments.store') }}" novalidate class="max-w-2xl">
        @csrf

        <x-ui.card>
            <div class="grid gap-4">
                <x-ui.field name="name" id="department-name" :label="__('ui.name')" required>
                    <x-ui.input name="name" id="department-name" maxlength="160" required />
                </x-ui.field>

                <x-ui.field name="slug" id="department-slug" :label="__('ui.departments.slug')" :help="__('ui.departments.slug_help')" required>
                    <x-ui.input name="slug" id="department-slug" maxlength="64" autocomplete="off" required />
                </x-ui.field>
            </div>

            <x-slot:footer>
                <x-ui.link-button :href="route('admin.departments.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.link-button>
                <x-ui.button type="submit">{{ __('ui.departments.create') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
