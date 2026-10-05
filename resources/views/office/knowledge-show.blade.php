<x-office-stage :scene="$scene" :admin-href="$adminHref">
    <div>
        <x-ui.link-button :href="route('office.knowledge')" variant="secondary" size="sm">
            <x-ui.icon name="chevron-left" size="sm" />
            {{ __('ui.knowledge.back_to_search') }}
        </x-ui.link-button>
    </div>

    <article class="rounded-card border border-border bg-surface p-4 shadow-card md:p-6" lang="{{ $translation?->locale }}">
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.badge tone="info">{{ $revision->category->label() }}</x-ui.badge>
            <x-ui.badge>{{ __('ui.knowledge.version', ['version' => $revision->version]) }}</x-ui.badge>
        </div>

        <h2 class="mt-3 text-page-title font-semibold text-text">{{ $translation?->title }}</h2>

        <dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-caption text-muted">
            <div class="flex gap-1">
                <dt>{{ __('ui.knowledge.fields.effective_from') }}:</dt>
                <dd>{{ $revision->effective_from?->timezone(config('corporate.timezone'))->format('Y-m-d H:i') }}</dd>
            </div>
            @if ($revision->effective_until)
                <div class="flex gap-1">
                    <dt>{{ __('ui.knowledge.fields.effective_until') }}:</dt>
                    <dd>{{ $revision->effective_until->timezone(config('corporate.timezone'))->format('Y-m-d H:i') }}</dd>
                </div>
            @endif
        </dl>

        <div class="mt-6 max-w-none text-body leading-7 text-text whitespace-pre-line break-words">{{ $translation?->body }}</div>

        @if (! empty($translation?->tags))
            <ul class="mt-6 flex flex-wrap gap-2" aria-label="{{ __('ui.knowledge.fields.tags') }}">
                @foreach ($translation->tags as $tag)
                    <li><x-ui.badge>{{ $tag }}</x-ui.badge></li>
                @endforeach
            </ul>
        @endif
    </article>
</x-office-stage>
