<x-layouts.admin
    :title="__('ui.dev.admin_title')"
    :heading="__('ui.dev.admin_title')"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.preview'), 'href' => route('dev.ui')],
        ['label' => __('ui.nav.overview')],
    ]"
>
    <x-slot:actions>
        <x-ui.button variant="outline" x-on:click="$dispatch('ui:toast', { message: @js(__('ui.dev.toast_message')), tone: 'info' })">
            {{ __('ui.dev.toast_button') }}
        </x-ui.button>
        <x-ui.button>
            <x-ui.icon name="plus" size="sm" />
            {{ __('ui.dev.admin_action') }}
        </x-ui.button>
    </x-slot:actions>

    <div class="space-y-6">
        @include('dev._banner')

        <p class="text-body text-muted">{{ __('ui.dev.admin_intro') }}</p>

        <div class="grid gap-4 md:grid-cols-3">
            <x-ui.card :title="__('ui.nav.employees')" padding="sm">
                <x-ui.skeleton count="2" />
            </x-ui.card>
            <x-ui.card :title="__('ui.nav.knowledge')" padding="sm">
                <x-ui.skeleton count="2" />
            </x-ui.card>
            <x-ui.card :title="__('ui.nav.tickets')" padding="sm">
                <x-ui.skeleton count="2" />
            </x-ui.card>
        </div>

        <x-ui.table
            :caption="__('ui.dev.table_caption')"
            :headers="[__('ui.name'), __('ui.department'), __('ui.status'), __('ui.dev.synthetic_note')]"
        >
            @foreach ($employees as $employee)
                <x-ui.table.row>
                    <x-ui.table.cell header>{{ $employee['name'] }}</x-ui.table.cell>
                    <x-ui.table.cell>{{ $employee['department'] }}</x-ui.table.cell>
                    <x-ui.table.cell><x-ui.badge :tone="$employee['tone']">{{ __('ui.dev.tones.'.$employee['tone']) }}</x-ui.badge></x-ui.table.cell>
                    <x-ui.table.cell class="max-w-xs truncate text-muted">{{ $unsafeSample }}</x-ui.table.cell>
                </x-ui.table.row>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$employees" />
    </div>
</x-layouts.admin>
