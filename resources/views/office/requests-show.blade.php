<x-office-stage :scene="$scene" :admin-href="$adminHref">
    <div>
        <x-ui.link-button :href="route('office.requests')" variant="secondary" size="sm">
            <x-ui.icon name="chevron-left" size="sm" />
            {{ __('ui.tickets.back_to_requests') }}
        </x-ui.link-button>
    </div>

    @include('tickets._summary', ['ticket' => $ticket, 'staff' => false])

    <x-ticket-thread :ticket="$ticket" />
</x-office-stage>
