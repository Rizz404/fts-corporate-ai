<x-layouts.admin
    :title="__('ui.nav.settings')"
    :heading="__('ui.settings.title')"
    :nav-items="$navItems"
    :breadcrumbs="[['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')], ['label' => __('ui.nav.settings')]]"
>
    <form method="POST" action="{{ route('admin.settings.update') }}" novalidate class="max-w-3xl">
        @csrf
        @method('PATCH')

        <x-ui.card :title="__('ui.settings.branding')">
            <p class="mb-4 text-body text-muted">{{ __('ui.settings.intro') }}</p>

            <div class="grid gap-4">
                <x-ui.field name="brand_name" id="settings-brand-name" :label="__('ui.settings.brand_name')" required>
                    <x-ui.input name="brand_name" id="settings-brand-name" :value="$settings->brand_name" maxlength="160" required />
                </x-ui.field>

                <x-ui.tabs
                    id="welcome-tabs"
                    :items="collect(config('corporate.locale_names'))->map(fn ($name, $code) => ['id' => $code, 'label' => $name])->values()->all()"
                    :selected="collect(config('corporate.locales'))->first(fn ($code) => $errors->has('welcome_text.'.$code))"
                >
                    @foreach (config('corporate.locale_names') as $code => $name)
                        <x-ui.tab-panel :name="$code" tabs-id="welcome-tabs">
                            <x-ui.field
                                name="welcome_text[{{ $code }}]"
                                id="settings-welcome-{{ $code }}"
                                :label="__('ui.settings.welcome_text').' ('.$name.')'"
                                :help="__('ui.settings.welcome_help')"
                            >
                                <x-ui.textarea
                                    name="welcome_text[{{ $code }}]"
                                    id="settings-welcome-{{ $code }}"
                                    :value="$settings->welcome_text[$code] ?? ''"
                                    rows="4"
                                    maxlength="1000"
                                    lang="{{ $code }}"
                                />
                            </x-ui.field>
                        </x-ui.tab-panel>
                    @endforeach
                </x-ui.tabs>
            </div>

            <x-slot:footer>
                <x-ui.button type="submit">{{ __('ui.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.card>
    </form>
</x-layouts.admin>
