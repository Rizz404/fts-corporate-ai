{{-- Shared HR/IT desk content. $team is 'hr' or 'it'. Service descriptions are general, not official policy. --}}
<x-ui.card :title="__('ui.office.desk.services_title')">
    <ul class="list-inside list-disc space-y-1 text-body text-text">
        @foreach (__('ui.office.scenes.'.$team.'.services') as $service)
            <li>{{ $service }}</li>
        @endforeach
    </ul>
    <p class="mt-4 text-caption text-muted">{{ __('ui.office.desk.official_note') }}</p>

    <x-slot:footer>
        <x-ui.link-button :href="route('office.knowledge')" variant="outline">
            <x-ui.icon name="book-open" size="sm" />
            {{ __('ui.office.desk.browse_knowledge') }}
        </x-ui.link-button>
        <x-ui.link-button :href="route('office.requests')">
            <x-ui.icon name="clipboard-document-list" size="sm" />
            {{ __('ui.office.desk.my_requests') }}
        </x-ui.link-button>
    </x-slot:footer>
</x-ui.card>
