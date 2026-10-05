<x-office-stage :scene="$scene" :admin-href="$adminHref">
    <x-ui.empty-state
        icon="clipboard-document-list"
        :title="__('ui.office.requests.empty_title')"
        :description="__('ui.office.requests.empty_description')"
    >
        <x-slot:action>
            {{-- Ticket forms arrive in Plan 6; no endpoint is called here. --}}
            <x-ui.link-button disabled>
                <x-ui.icon name="plus" size="sm" />
                {{ __('ui.office.requests.new') }}
            </x-ui.link-button>
        </x-slot:action>
    </x-ui.empty-state>
</x-office-stage>
