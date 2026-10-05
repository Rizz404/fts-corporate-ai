{{--
    Draft fields shared by create and edit. $draft is null on create.
    $slugLocked: slug can no longer change after first publication.
--}}
@php
    $tz = config('corporate.timezone');
    $toInput = fn ($date) => $date?->timezone($tz)->format('Y-m-d\TH:i');
    $audience = old('audience', $draft?->audience?->value ?? 'common');
    $selectedDepartments = array_map('intval', old('department_ids', $draft?->departments->pluck('id')->all() ?? []));
    $creating = $draft === null && ! isset($article);
@endphp

<div x-data="{ audience: @js($audience) }" class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2">
        @if ($creating || ! $slugLocked)
            <x-ui.field name="slug" id="knowledge-slug" :label="__('ui.knowledge.fields.slug')" :help="__('ui.knowledge.slug_help')" required>
                <x-ui.input name="slug" id="knowledge-slug" :value="$article->slug ?? null" maxlength="120" autocomplete="off" required />
            </x-ui.field>
        @else
            <div>
                <p class="text-body font-medium text-text">{{ __('ui.knowledge.fields.slug') }}</p>
                <p class="mt-1"><code class="text-caption">{{ $article->slug }}</code></p>
                <p class="mt-1 text-caption text-muted">{{ __('ui.knowledge.slug_locked') }}</p>
            </div>
        @endif

        @if ($creating)
            <x-ui.field name="owner_department_id" id="knowledge-owner" :label="__('ui.knowledge.fields.owner_department')" :help="__('ui.knowledge.owner_help')" required>
                <x-ui.select name="owner_department_id" id="knowledge-owner" :options="$ownerDepartmentOptions" :placeholder="__('ui.choose')" required />
            </x-ui.field>
        @else
            <div>
                <p class="text-body font-medium text-text">{{ __('ui.knowledge.fields.owner_department') }}</p>
                <p class="mt-1 text-body">{{ $article->ownerDepartment->name }}</p>
            </div>
        @endif

        <x-ui.field name="category" id="knowledge-category" :label="__('ui.knowledge.fields.category')" required>
            <x-ui.select name="category" id="knowledge-category" :options="$categoryOptions" :value="$draft?->category?->value ?? 'general'" required />
        </x-ui.field>

        <x-ui.field name="audience" id="knowledge-audience" :label="__('ui.knowledge.fields.audience')" required>
            <x-ui.select name="audience" id="knowledge-audience" :options="$audienceOptions" :value="$audience" x-model="audience" required />
        </x-ui.field>
    </div>

    <fieldset x-show="audience === 'departments'" @if ($audience !== 'departments') x-cloak @endif class="space-y-2">
        <legend class="text-body font-medium text-text">{{ __('ui.knowledge.fields.departments') }}</legend>
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            @foreach ($departmentOptions as $value => $label)
                <x-ui.checkbox name="department_ids[]" id="knowledge-department-{{ $value }}" :value="$value" :checked="in_array((int) $value, $selectedDepartments, true)">
                    {{ $label }}
                </x-ui.checkbox>
            @endforeach
        </div>
        <p class="text-caption text-muted">{{ __('ui.knowledge.departments_help') }}</p>
        @error('department_ids')
            <p class="text-caption text-danger">{{ $message }}</p>
        @enderror
    </fieldset>

    <div class="grid gap-4 md:grid-cols-2">
        <x-ui.field name="effective_from" id="knowledge-effective-from" :label="__('ui.knowledge.fields.effective_from')" :help="__('ui.knowledge.effective_from_help', ['tz' => $tz])">
            <x-ui.input name="effective_from" id="knowledge-effective-from" type="datetime-local" :value="$toInput($draft?->effective_from)" />
        </x-ui.field>
        <x-ui.field name="effective_until" id="knowledge-effective-until" :label="__('ui.knowledge.fields.effective_until')" :help="__('ui.knowledge.effective_until_help')">
            <x-ui.input name="effective_until" id="knowledge-effective-until" type="datetime-local" :value="$toInput($draft?->effective_until)" />
        </x-ui.field>
    </div>

    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-body font-medium text-text">{{ __('ui.knowledge.completeness') }}</span>
            @foreach (config('corporate.locale_names') as $code => $name)
                <x-ui.badge :tone="($completeness[$code] ?? false) ? 'success' : 'warning'">
                    {{ $name }}: {{ ($completeness[$code] ?? false) ? __('ui.knowledge.complete') : __('ui.knowledge.incomplete') }}
                </x-ui.badge>
            @endforeach
        </div>

        <x-ui.tabs
            id="knowledge-translations"
            :items="collect(config('corporate.locale_names'))->map(fn ($name, $code) => ['id' => $code, 'label' => $name])->values()->all()"
            :selected="collect(config('corporate.locales'))->first(fn ($code) => $errors->has('translations.'.$code.'.*') || $errors->has('translations.'.$code.'.title') || $errors->has('translations.'.$code.'.body'))"
        >
            @foreach (config('corporate.locale_names') as $code => $name)
                @php $translation = $draft?->translations->firstWhere('locale', $code); @endphp
                <x-ui.tab-panel :name="$code" tabs-id="knowledge-translations">
                    <div class="grid gap-4" lang="{{ $code }}">
                        <x-ui.field name="translations[{{ $code }}][title]" id="knowledge-{{ $code }}-title" :label="__('ui.knowledge.fields.title').' ('.$name.')'">
                            <x-ui.input name="translations[{{ $code }}][title]" id="knowledge-{{ $code }}-title" :value="$translation?->title" maxlength="180" />
                        </x-ui.field>
                        <x-ui.field name="translations[{{ $code }}][body]" id="knowledge-{{ $code }}-body" :label="__('ui.knowledge.fields.body').' ('.$name.')'" :help="__('ui.knowledge.body_help')">
                            <x-ui.textarea name="translations[{{ $code }}][body]" id="knowledge-{{ $code }}-body" :value="$translation?->body" rows="12" maxlength="20000" />
                        </x-ui.field>
                        <div class="grid gap-4 md:grid-cols-2">
                            <x-ui.field name="translations[{{ $code }}][tags]" id="knowledge-{{ $code }}-tags" :label="__('ui.knowledge.fields.tags')" :help="__('ui.knowledge.list_help')">
                                <x-ui.input name="translations[{{ $code }}][tags]" id="knowledge-{{ $code }}-tags" :value="implode(', ', $translation?->tags ?? [])" />
                            </x-ui.field>
                            <x-ui.field name="translations[{{ $code }}][aliases]" id="knowledge-{{ $code }}-aliases" :label="__('ui.knowledge.fields.aliases')" :help="__('ui.knowledge.aliases_help')">
                                <x-ui.input name="translations[{{ $code }}][aliases]" id="knowledge-{{ $code }}-aliases" :value="implode(', ', $translation?->aliases ?? [])" />
                            </x-ui.field>
                        </div>
                    </div>
                </x-ui.tab-panel>
            @endforeach
        </x-ui.tabs>
    </div>
</div>
