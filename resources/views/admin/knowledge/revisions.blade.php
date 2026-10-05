<x-layouts.admin
    :title="__('ui.knowledge.history')"
    :heading="__('ui.knowledge.history')"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.knowledge'), 'href' => route('admin.knowledge.index')],
        ['label' => $article->slug, 'href' => route('admin.knowledge.edit', $article)],
        ['label' => __('ui.knowledge.history')],
    ]"
>
    <x-ui.table :headers="[__('ui.knowledge.version_column'), __('ui.status'), __('ui.knowledge.fields.title'), __('ui.knowledge.created_by'), __('ui.knowledge.published_column')]">
        @foreach ($revisions as $revision)
            <x-ui.table.row>
                <x-ui.table.cell header>v{{ $revision->version }}</x-ui.table.cell>
                <x-ui.table.cell><x-ui.badge :tone="$revision->status->tone()">{{ $revision->status->label() }}</x-ui.badge></x-ui.table.cell>
                <x-ui.table.cell>{{ $revision->translation(app()->getLocale())?->title }}</x-ui.table.cell>
                <x-ui.table.cell class="text-muted">{{ $revision->creator?->name }}</x-ui.table.cell>
                <x-ui.table.cell class="whitespace-nowrap text-muted">
                    @if ($revision->published_at)
                        {{ $revision->published_at->timezone(config('corporate.timezone'))->format('Y-m-d H:i') }} · {{ $revision->publisher?->name }}
                    @else
                        —
                    @endif
                </x-ui.table.cell>
            </x-ui.table.row>
        @endforeach
    </x-ui.table>
</x-layouts.admin>
