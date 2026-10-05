<x-layouts.admin
    :title="$department->name"
    :heading="$department->name"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.departments'), 'href' => route('admin.departments.index')],
        ['label' => $department->name],
    ]"
>
    <form method="POST" action="{{ route('admin.departments.update', $department) }}" novalidate class="max-w-2xl">
        @csrf
        @method('PUT')

        <x-ui.card>
            <div class="grid gap-4">
                <x-ui.field name="name" id="department-name" :label="__('ui.name')" required>
                    <x-ui.input name="name" id="department-name" :value="$department->name" maxlength="160" required />
                </x-ui.field>

                <div>
                    <p class="text-body font-medium text-text">{{ __('ui.departments.slug') }}</p>
                    <p class="mt-1"><code class="text-caption">{{ $department->slug }}</code></p>
                    <p class="mt-1 text-caption text-muted">{{ __('ui.departments.slug_locked') }}</p>
                </div>

                <div>
                    <x-ui.checkbox name="is_active" id="department-is-active" :checked="$department->is_active" describedby="department-is-active-help">
                        {{ __('ui.departments.is_active') }}
                    </x-ui.checkbox>
                    <p id="department-is-active-help" class="mt-1 text-caption text-muted">{{ __('ui.departments.is_active_help') }}</p>
                </div>
            </div>

            <x-slot:footer>
                <x-ui.link-button :href="route('admin.departments.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.link-button>
                <x-ui.button type="submit">{{ __('ui.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
