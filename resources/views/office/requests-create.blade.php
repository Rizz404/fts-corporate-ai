@php
    $wizardConfig = [
        'initialTeam' => $initialTeam,
        'initialDraft' => $initialDraft ?? null,
        'categories' => $categoryOptions,
        'labels' => [
            'required' => __('ui.tickets.errors.required'),
            'expired' => __('ui.tickets.errors.draft_expired'),
            'changed' => __('ui.tickets.errors.changed'),
            'invalid' => __('ui.errors.validation_failed'),
            'rateLimited' => __('ui.chat.errors.rate_limited'),
            'session' => __('ui.chat.errors.session'),
            'network' => __('ui.chat.errors.network'),
            'generic' => __('ui.chat.errors.generic'),
        ],
    ];
    $teams = ['hr' => __('ui.teams.hr'), 'it' => __('ui.teams.it')];
@endphp

<x-office-stage :scene="$scene" :admin-href="$adminHref">
    <x-ui.card :title="__('ui.tickets.wizard.title')" x-data="ticketWizard(@js($wizardConfig))">
        <ol class="mb-6 flex flex-wrap gap-2 text-caption" aria-label="{{ __('ui.tickets.wizard.steps') }}">
            @foreach ([1 => 'step_team', 2 => 'step_details', 3 => 'step_review'] as $number => $key)
                <li
                    class="rounded-full px-3 py-1 font-medium"
                    x-bind:class="step === {{ $number }} ? 'bg-primary text-on-primary' : 'bg-surface-muted text-muted'"
                    x-bind:aria-current="step === {{ $number }} ? 'step' : null"
                >{{ $number }}. {{ __('ui.tickets.wizard.'.$key) }}</li>
            @endforeach
        </ol>

        <div x-show="error" x-cloak role="alert" class="mb-4 flex items-start justify-between gap-2 rounded-control bg-danger-soft px-3 py-2 text-caption text-danger">
            <span x-text="error"></span>
            <button type="button" x-show="canRetry" x-on:click="confirm()" class="font-semibold underline">{{ __('ui.chat.retry') }}</button>
        </div>

        {{-- Step 1: team and category --}}
        <div x-show="step === 1" class="space-y-4">
            <fieldset>
                <legend class="text-body font-medium text-text">{{ __('ui.tickets.fields.team') }}</legend>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    @foreach ($teams as $code => $label)
                        <button
                            type="button"
                            x-on:click="chooseTeam(@js($code))"
                            x-bind:aria-pressed="team === @js($code)"
                            class="flex items-center gap-3 rounded-card border p-4 text-left transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus motion-reduce:transition-none"
                            x-bind:class="team === @js($code) ? 'border-primary bg-surface-muted' : 'border-border bg-surface hover:bg-surface-muted'"
                        >
                            <x-ui.icon :name="$code === 'hr' ? 'users' : 'computer-desktop'" class="text-primary" />
                            <span class="text-body font-semibold text-text">{{ $label }}</span>
                        </button>
                    @endforeach
                </div>
                <p x-show="fieldError('team_code')" x-text="fieldError('team_code')" class="mt-1 text-caption text-danger"></p>
            </fieldset>

            <div x-show="team" x-cloak>
                <label for="wizard-category" class="text-body font-medium text-text">{{ __('ui.tickets.fields.category') }}</label>
                <select id="wizard-category" x-model="category" class="form-select mt-1 {{ \App\Support\Ui\ComponentStyles::control() }}">
                    <option value="">{{ __('ui.choose') }}</option>
                    <template x-for="option in categories" x-bind:key="option.value">
                        <option x-bind:value="option.value" x-text="option.label"></option>
                    </template>
                </select>
                <p x-show="fieldError('category')" x-text="fieldError('category')" class="mt-1 text-caption text-danger"></p>
            </div>
        </div>

        {{-- Step 2: details --}}
        <div x-show="step === 2" x-cloak class="space-y-4">
            <div>
                <label for="wizard-subject" class="text-body font-medium text-text">{{ __('ui.tickets.fields.subject') }}</label>
                <input id="wizard-subject" type="text" x-model="subject" maxlength="160" class="form-input mt-1 {{ \App\Support\Ui\ComponentStyles::control() }}">
                <p x-show="fieldError('subject')" x-text="fieldError('subject')" class="mt-1 text-caption text-danger"></p>
            </div>
            <div>
                <label for="wizard-description" class="text-body font-medium text-text">{{ __('ui.tickets.fields.description') }}</label>
                <textarea id="wizard-description" x-model="description" rows="6" maxlength="5000" class="form-textarea mt-1 {{ \App\Support\Ui\ComponentStyles::control() }}"></textarea>
                <p class="mt-1 text-caption text-muted">{{ __('ui.tickets.wizard.description_help') }}</p>
                <p x-show="fieldError('description')" x-text="fieldError('description')" class="mt-1 text-caption text-danger"></p>
            </div>
        </div>

        {{-- Step 3: review (values come from the saved server draft) --}}
        <div x-show="step === 3" x-cloak class="space-y-3">
            <p class="text-body text-muted">{{ __('ui.tickets.wizard.review_intro') }}</p>
            <dl class="grid gap-3 rounded-card bg-surface-muted p-4 text-body">
                <div><dt class="text-caption text-muted">{{ __('ui.tickets.fields.team') }}</dt><dd class="font-medium" x-text="draft?.team_label"></dd></div>
                <div><dt class="text-caption text-muted">{{ __('ui.tickets.fields.category') }}</dt><dd class="font-medium" x-text="draft?.category_label"></dd></div>
                <div><dt class="text-caption text-muted">{{ __('ui.tickets.fields.subject') }}</dt><dd class="font-medium break-words" x-text="draft?.subject"></dd></div>
                <div><dt class="text-caption text-muted">{{ __('ui.tickets.fields.description') }}</dt><dd class="break-words whitespace-pre-line" x-text="draft?.description"></dd></div>
            </dl>
        </div>

        <x-slot:footer>
            <x-ui.button variant="ghost" x-show="step > 1" x-cloak x-on:click="back()" x-bind:disabled="pending">{{ __('ui.tickets.wizard.back') }}</x-ui.button>
            <x-ui.link-button :href="route('office.requests')" variant="ghost" x-show="step === 1">{{ __('ui.cancel') }}</x-ui.link-button>
            <x-ui.button x-show="step < 3" x-on:click="next()" x-bind:disabled="pending">{{ __('ui.tickets.wizard.next') }}</x-ui.button>
            <x-ui.button x-show="step === 3" x-cloak x-on:click="confirm()" x-bind:disabled="pending || ! draft || draft.expired">
                <x-ui.icon name="check-circle" size="sm" />
                {{ __('ui.tickets.wizard.confirm') }}
            </x-ui.button>
        </x-slot:footer>
    </x-ui.card>
</x-office-stage>
