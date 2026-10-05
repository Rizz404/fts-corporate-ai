{{-- Ticket header shared by requester and staff pages. --}}
@php
    $tz = config('corporate.timezone');
    $team = $ticket->team->code->value;
@endphp

<x-ui.card>
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.badge :tone="$ticket->status->tone()">{{ $ticket->status->label() }}</x-ui.badge>
        <span class="text-caption text-muted">{{ $ticket->reference }}</span>
    </div>
    <h2 class="mt-2 text-page-title font-semibold break-words text-text">{{ $ticket->subject }}</h2>

    <dl class="mt-3 grid gap-3 text-body sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-caption text-muted">{{ __('ui.tickets.fields.team') }}</dt><dd class="font-medium">{{ __('ui.teams.'.$team) }}</dd></div>
        <div><dt class="text-caption text-muted">{{ __('ui.tickets.fields.category') }}</dt><dd class="font-medium">{{ \App\Modules\Support\Enums\TicketCategory::label($team, $ticket->category) }}</dd></div>
        <div><dt class="text-caption text-muted">{{ __('ui.tickets.fields.assignee') }}</dt><dd class="font-medium">{{ $ticket->assignee?->name ?? __('ui.tickets.unassigned') }}</dd></div>
        <div><dt class="text-caption text-muted">{{ __('ui.tickets.fields.created') }}</dt><dd class="font-medium">{{ $ticket->created_at->timezone($tz)->format('Y-m-d H:i') }}</dd></div>
        @if ($staff)
            <div><dt class="text-caption text-muted">{{ __('ui.tickets.fields.requester') }}</dt><dd class="font-medium break-all">{{ $ticket->requester?->name }} · {{ $ticket->requester?->email }}</dd></div>
        @endif
    </dl>

    <div class="mt-4 border-t border-border pt-4">
        <p class="text-caption text-muted">{{ __('ui.tickets.fields.description') }}</p>
        <p class="mt-1 text-body break-words whitespace-pre-line text-text">{{ $ticket->description }}</p>
    </div>
</x-ui.card>
