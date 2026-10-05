<x-layouts.admin
    :title="__('ui.nav.tickets')"
    :heading="__('ui.tickets.queue_title')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.tickets')]]"
>
    <form method="GET" action="{{ route('admin.tickets.index') }}" class="mb-4 grid gap-3 md:grid-cols-[12rem_12rem_auto_auto] md:items-end">
        <x-ui.field name="team" id="queue-team" :label="__('ui.tickets.fields.team')">
            <x-ui.select name="team" id="queue-team" :options="$teamOptions" :value="$filters['team']" :placeholder="__('ui.all')" />
        </x-ui.field>
        <x-ui.field name="status" id="queue-status" :label="__('ui.status')">
            <x-ui.select name="status" id="queue-status" :options="$statusOptions" :value="$filters['status']" :placeholder="__('ui.tickets.all_active')" />
        </x-ui.field>
        <x-ui.checkbox name="mine" id="queue-mine" :checked="$filters['mine']" class="md:mb-2">{{ __('ui.tickets.assigned_to_me') }}</x-ui.checkbox>
        <x-ui.button type="submit" variant="outline">
            <x-ui.icon name="magnifying-glass" size="sm" />
            {{ __('ui.filter') }}
        </x-ui.button>
    </form>

    @if ($tickets->isEmpty())
        <x-ui.empty-state icon="inbox" :title="__('ui.tickets.queue_empty_title')" :description="__('ui.tickets.queue_empty_description')" />
    @else
        <x-ui.table :headers="[__('ui.tickets.fields.subject'), __('ui.tickets.fields.team'), __('ui.status'), __('ui.tickets.fields.assignee'), __('ui.updated'), ['label' => __('ui.actions'), 'sr_only' => true]]">
            @foreach ($tickets as $ticket)
                <x-ui.table.row>
                    <x-ui.table.cell header>
                        <span class="block break-words">{{ $ticket->subject }}</span>
                        <span class="block text-caption font-normal text-muted">{{ $ticket->reference }} · {{ $ticket->requester?->name }}</span>
                    </x-ui.table.cell>
                    <x-ui.table.cell>{{ __('ui.teams.'.$ticket->team->code->value) }}</x-ui.table.cell>
                    <x-ui.table.cell><x-ui.badge :tone="$ticket->status->tone()">{{ $ticket->status->label() }}</x-ui.badge></x-ui.table.cell>
                    <x-ui.table.cell class="text-muted">{{ $ticket->assignee?->name ?? __('ui.tickets.unassigned') }}</x-ui.table.cell>
                    <x-ui.table.cell class="whitespace-nowrap text-muted">{{ $ticket->updated_at->timezone(config('corporate.timezone'))->format('Y-m-d H:i') }}</x-ui.table.cell>
                    <x-ui.table.cell class="text-right">
                        <x-ui.link-button :href="route('admin.tickets.show', $ticket)" variant="ghost" size="sm">{{ __('ui.tickets.open') }}</x-ui.link-button>
                    </x-ui.table.cell>
                </x-ui.table.row>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$tickets" class="mt-4" />
    @endif
</x-layouts.admin>
