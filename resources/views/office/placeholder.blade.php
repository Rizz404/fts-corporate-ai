{{-- Temporary signed-in page; Plan 3 replaces it with the office scenes. --}}
<x-layouts.user :title="__('ui.nav.rooms.reception')" :admin-href="$adminHref">
    <div class="space-y-4">
        @if (session('status'))
            <x-ui.alert tone="success" dismissible>{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card :title="__('ui.office.welcome', ['name' => $user->name])">
            <p>{{ __('ui.office.placeholder') }}</p>
            <dl class="mt-4 grid gap-2 text-body sm:grid-cols-2">
                <div>
                    <dt class="text-caption text-muted">{{ __('ui.department') }}</dt>
                    <dd class="font-medium">{{ $user->department->name }}</dd>
                </div>
                <div>
                    <dt class="text-caption text-muted">{{ __('ui.email') }}</dt>
                    <dd class="font-medium break-all">{{ $user->email }}</dd>
                </div>
            </dl>
            @if ($adminHref !== null)
                <x-slot:footer>
                    <x-ui.link-button :href="$adminHref" variant="outline">
                        <x-ui.icon name="cog-6-tooth" size="sm" />
                        {{ __('ui.account.admin') }}
                    </x-ui.link-button>
                </x-slot:footer>
            @endif
        </x-ui.card>
    </div>
</x-layouts.user>
