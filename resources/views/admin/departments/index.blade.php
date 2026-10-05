<x-layouts.admin
    :title="__('ui.nav.departments')"
    :heading="__('ui.nav.departments')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.departments')]]"
>
    <x-slot:actions>
        <x-ui.link-button :href="route('admin.departments.create')">
            <x-ui.icon name="plus" size="sm" />
            {{ __('ui.departments.create') }}
        </x-ui.link-button>
    </x-slot:actions>

    <x-ui.table :headers="[__('ui.name'), __('ui.departments.slug'), __('ui.departments.members'), __('ui.status'), ['label' => __('ui.actions'), 'sr_only' => true]]">
        @foreach ($departments as $department)
            <x-ui.table.row>
                <x-ui.table.cell header>{{ $department->name }}</x-ui.table.cell>
                <x-ui.table.cell><code class="text-caption">{{ $department->slug }}</code></x-ui.table.cell>
                <x-ui.table.cell>{{ number_format($department->users_count) }}</x-ui.table.cell>
                <x-ui.table.cell>
                    <x-ui.badge :tone="$department->is_active ? 'success' : 'neutral'">
                        {{ $department->is_active ? __('ui.users.active') : __('ui.users.inactive') }}
                    </x-ui.badge>
                </x-ui.table.cell>
                <x-ui.table.cell class="text-right">
                    <x-ui.link-button :href="route('admin.departments.edit', $department)" variant="ghost" size="sm">{{ __('ui.edit') }}</x-ui.link-button>
                </x-ui.table.cell>
            </x-ui.table.row>
        @endforeach
    </x-ui.table>
    <x-ui.pagination :paginator="$departments" class="mt-4" />
</x-layouts.admin>
