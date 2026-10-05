<x-layouts.admin
    :title="$ticket->reference"
    :heading="$ticket->reference"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.tickets'), 'href' => route('admin.tickets.index')],
        ['label' => $ticket->reference],
    ]"
>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-6">
            @include('tickets._summary', ['ticket' => $ticket, 'staff' => true])
            <x-ticket-thread :ticket="$ticket" staff :assignee-options="$assigneeOptions" />
        </div>

        <x-ui.card :title="__('ui.tickets.history')" padding="sm" class="h-fit">
            <ol class="space-y-3">
                @foreach ($ticket->events->sortByDesc('id') as $event)
                    <li class="text-caption">
                        <p class="font-medium text-text">
                            {{ __('ui.notifications.events.'.$event->event_code) }}
                            @if ($event->next_status)
                                → {{ \App\Modules\Support\Enums\TicketStatus::from($event->next_status)->label() }}
                            @endif
                        </p>
                        <p class="text-muted">{{ $event->actor?->name }} · {{ $event->created_at->timezone(config('corporate.timezone'))->format('Y-m-d H:i') }}</p>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    </div>
</x-layouts.admin>
