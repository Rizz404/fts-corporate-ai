<x-office-stage :scene="$scene" :admin-href="$adminHref">
    @if (session('status'))
        <x-ui.alert tone="success" dismissible>{{ session('status') }}</x-ui.alert>
    @endif

    <section aria-labelledby="rooms-title" class="space-y-3">
        <h2 id="rooms-title" class="sr-only">{{ __('ui.nav.office') }}</h2>
        <ul class="grid gap-3 sm:grid-cols-2">
            @foreach ($scene->menu as $room)
                @continue($room['active'])
                <li>
                    <a
                        href="{{ $room['href'] }}"
                        class="flex h-full items-start gap-3 rounded-card border border-border bg-surface p-4 shadow-card transition-colors duration-150 hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus motion-reduce:transition-none"
                    >
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-control bg-primary text-on-primary">
                            <x-ui.icon :name="$room['icon']" />
                        </span>
                        <span>
                            <span class="block text-body font-semibold text-text">{{ $room['label'] }}</span>
                            <span class="block text-caption text-muted">{{ __('ui.office.scenes.'.$room['code'].'.summary') }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
</x-office-stage>
