@php
    $turns = $metrics['turns'];
    $tickets = $metrics['tickets'];
    $seconds = fn (?int $ms) => $ms === null ? '–' : number_format($ms / 1000, 1).' s';
    $statusTone = fn (string $status) => match ($status) {
        'ok', 'not_required' => 'success',
        'unavailable', 'stale' => 'warning',
        default => 'danger',
    };
    $tiles = [
        ['label' => __('ui.operations.turns_completed'), 'value' => number_format($turns['completed'])],
        ['label' => __('ui.operations.turns_failed'), 'value' => number_format($turns['failed']).' ('.number_format($turns['error_rate'] * 100, 1).'%)'],
        ['label' => __('ui.operations.latency_p50'), 'value' => $seconds($turns['p50_ms'])],
        ['label' => __('ui.operations.latency_p95'), 'value' => $seconds($turns['p95_ms'])],
        ['label' => __('ui.operations.timeouts'), 'value' => number_format($turns['timeouts'])],
        ['label' => __('ui.operations.no_match'), 'value' => number_format($turns['no_match'])],
        ['label' => __('ui.operations.tool_validation_failures'), 'value' => number_format($metrics['tool_validation_failures'])],
        ['label' => __('ui.operations.tickets_open'), 'value' => number_format($tickets['open'])],
        ['label' => __('ui.operations.tickets_unassigned'), 'value' => number_format($tickets['unassigned'])],
        ['label' => __('ui.operations.oldest_open'), 'value' => $tickets['oldest_open_at'] ? \Carbon\CarbonImmutable::parse($tickets['oldest_open_at'])->timezone(config('corporate.timezone'))->format('Y-m-d H:i') : '–'],
    ];
    $activeAlerts = array_keys(array_filter($metrics['alerts']));
@endphp

<x-layouts.admin
    :title="__('ui.nav.operations')"
    :heading="__('ui.operations.title')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.operations')]]"
>
    <div class="space-y-6">
        <p class="text-body text-muted">{{ __('ui.operations.intro', ['days' => $metrics['window_days']]) }}</p>

        @if ($activeAlerts === [])
            <x-ui.alert tone="success">{{ __('ui.operations.alerts_none') }}</x-ui.alert>
        @else
            @foreach ($activeAlerts as $alert)
                <x-ui.alert tone="warning">{{ __('ui.operations.alerts.'.$alert) }}</x-ui.alert>
            @endforeach
        @endif

        <section aria-labelledby="ops-metrics">
            <h2 id="ops-metrics" class="mb-3 text-section-title font-semibold text-text">{{ __('ui.operations.metrics') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($tiles as $tile)
                    <x-ui.card padding="sm">
                        <p class="text-caption text-muted">{{ $tile['label'] }}</p>
                        <p class="mt-1 text-section-title font-semibold text-text">{{ $tile['value'] }}</p>
                    </x-ui.card>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="ops-readiness">
            <h2 id="ops-readiness" class="mb-3 text-section-title font-semibold text-text">{{ __('ui.operations.readiness') }}</h2>
            <x-ui.table :headers="[__('ui.operations.check'), __('ui.status'), __('ui.operations.required')]">
                @foreach ($readiness['checks'] as $name => $check)
                    <x-ui.table.row>
                        <x-ui.table.cell header>{{ __('ui.operations.checks.'.$name) }}</x-ui.table.cell>
                        <x-ui.table.cell><x-ui.badge :tone="$statusTone($check['status'])">{{ __('ui.operations.statuses.'.$check['status']) }}</x-ui.badge></x-ui.table.cell>
                        <x-ui.table.cell class="text-muted">{{ $check['required'] ? __('ui.yes') : __('ui.no') }}</x-ui.table.cell>
                    </x-ui.table.row>
                @endforeach
            </x-ui.table>
        </section>
    </div>
</x-layouts.admin>
