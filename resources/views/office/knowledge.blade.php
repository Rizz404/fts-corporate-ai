<x-office-stage :scene="$scene" :admin-href="$adminHref">
    <x-ui.card :title="__('ui.knowledge.search_title')">
        <form method="GET" action="{{ route('office.knowledge') }}" role="search" class="grid gap-3 md:grid-cols-[1fr_12rem_auto] md:items-end">
            <x-ui.field name="q" id="knowledge-q" :label="__('ui.search')">
                <x-ui.input name="q" id="knowledge-q" type="search" :value="$query" maxlength="500" :placeholder="__('ui.knowledge.search_placeholder')" />
            </x-ui.field>
            <x-ui.field name="category" id="knowledge-category" :label="__('ui.knowledge.fields.category')">
                <x-ui.select name="category" id="knowledge-category" :options="$categoryOptions" :value="$category" :placeholder="__('ui.all')" />
            </x-ui.field>
            <x-ui.button type="submit">
                <x-ui.icon name="magnifying-glass" size="sm" />
                {{ __('ui.search') }}
            </x-ui.button>
        </form>
    </x-ui.card>

    @if ($results !== null)
        <section aria-labelledby="knowledge-results-title" class="space-y-3">
            <h2 id="knowledge-results-title" class="text-section-title font-semibold text-text">
                {{ trans_choice('ui.knowledge.results_count', count($results), ['count' => count($results), 'query' => $query]) }}
            </h2>

            @forelse ($results as $result)
                @include('office._knowledge-item', [
                    'href' => route('office.knowledge.show', $result->slug),
                    'title' => $result->title,
                    'excerpt' => \App\Modules\Knowledge\Data\KnowledgeEvidence::excerpt($result->excerpt, \App\Modules\Knowledge\Data\KnowledgeEvidence::UI_EXCERPT),
                    'category' => $result->category,
                    'version' => $result->version,
                    'effectiveFrom' => $result->effectiveFrom,
                ])
            @empty
                <x-ui.empty-state icon="magnifying-glass" :title="__('ui.knowledge.no_results_title')" :description="__('ui.knowledge.no_results_description')">
                    <x-slot:action>
                        <x-ui.link-button :href="route('office.requests')" variant="outline">{{ __('ui.office.desk.my_requests') }}</x-ui.link-button>
                    </x-slot:action>
                </x-ui.empty-state>
            @endforelse
        </section>
    @else
        <section aria-labelledby="knowledge-latest-title" class="space-y-3">
            <h2 id="knowledge-latest-title" class="text-section-title font-semibold text-text">{{ __('ui.knowledge.latest_title') }}</h2>

            @forelse ($latest as $revision)
                @php $translation = $revision->translation(app()->getLocale()); @endphp
                @include('office._knowledge-item', [
                    'href' => route('office.knowledge.show', $revision->article->slug),
                    'title' => $translation?->title ?? '',
                    'excerpt' => \App\Modules\Knowledge\Data\KnowledgeEvidence::excerpt($translation?->body ?? '', \App\Modules\Knowledge\Data\KnowledgeEvidence::UI_EXCERPT),
                    'category' => $revision->category->value,
                    'version' => $revision->version,
                    'effectiveFrom' => $revision->effective_from,
                ])
            @empty
                <x-ui.empty-state icon="book-open" :title="__('ui.office.knowledge.empty_title')" :description="__('ui.office.knowledge.empty_description')" />
            @endforelse

            @if ($latest->hasPages())
                <div class="rounded-card bg-surface p-3 shadow-card">
                    <x-ui.pagination :paginator="$latest" />
                </div>
            @endif
        </section>
    @endif
</x-office-stage>
