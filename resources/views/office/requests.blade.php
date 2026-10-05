<x-office-stage :scene="$scene" :admin-href="$adminHref">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-section-title font-semibold text-on-primary">{{ __('ui.tickets.my_requests') }}</h2>
        <x-ui.link-button :href="route('office.requests.create')">
            <x-ui.icon name="plus" size="sm" />
            {{ __('ui.office.requests.new') }}
        </x-ui.link-button>
    </div>

    @if ($tickets->isEmpty())
        <x-ui.empty-state
            icon="clipboard-document-list"
            :title="__('ui.office.requests.empty_title')"
            :description="__('ui.office.requests.empty_description')"
        />
    @else
        <ul class="space-y-3">
            @foreach ($tickets as $ticket)
                <li>
                    <a
                        href="{{ route('office.requests.show', $ticket) }}"
                        class="block rounded-card border border-border bg-surface p-4 shadow-card transition-colors duration-150 hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus motion-reduce:transition-none"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :tone="$ticket->status->tone()">{{ $ticket->status->label() }}</x-ui.badge>
                            <span class="text-caption text-muted">{{ $ticket->reference }} · {{ __('ui.teams.'.$ticket->team->code->value) }}</span>
                        </div>
                        <p class="mt-2 text-body font-semibold text-text">{{ $ticket->subject }}</p>
                        <p class="text-caption text-muted">{{ __('ui.tickets.updated_at', ['date' => $ticket->updated_at->timezone(config('corporate.timezone'))->format('Y-m-d H:i')]) }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
        @if ($tickets->hasPages())
            <div class="rounded-card bg-surface p-3 shadow-card">
                <x-ui.pagination :paginator="$tickets" />
            </div>
        @endif
    @endif
</x-office-stage>
