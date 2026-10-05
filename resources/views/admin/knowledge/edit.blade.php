@php
    $locale = app()->getLocale();
    $tz = config('corporate.timezone');
    $titleSource = $draft ?? $published;
    $heading = $titleSource?->translation($locale)?->title;
@endphp

<x-layouts.admin
    :title="filled($heading) ? $heading : $article->slug"
    :heading="filled($heading) ? $heading : $article->slug"
    :nav-items="$navItems"
    :breadcrumbs="[
        ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard')],
        ['label' => __('ui.nav.knowledge'), 'href' => route('admin.knowledge.index')],
        ['label' => $article->slug],
    ]"
>
    <x-slot:actions>
        <x-ui.link-button :href="route('admin.knowledge.revisions.index', $article)" variant="outline">
            <x-ui.icon name="clipboard-document-list" size="sm" />
            {{ __('ui.knowledge.history') }}
        </x-ui.link-button>
    </x-slot:actions>

    <div class="space-y-6">
        {{-- Published state --}}
        <x-ui.card :title="__('ui.knowledge.published_section')">
            @if ($published)
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.badge tone="success">{{ __('ui.knowledge.status.published') }} · v{{ $published->version }}</x-ui.badge>
                    <span class="text-caption text-muted">
                        {{ __('ui.knowledge.published_meta', [
                            'date' => $published->published_at?->timezone($tz)->format('Y-m-d H:i'),
                            'name' => $published->publisher?->name,
                        ]) }}
                    </span>
                </div>
                <p class="mt-2 text-body text-muted">
                    {{ __('ui.knowledge.fields.effective_from') }}: {{ $published->effective_from?->timezone($tz)->format('Y-m-d H:i') }}
                    @if ($published->effective_until)
                        · {{ __('ui.knowledge.fields.effective_until') }}: {{ $published->effective_until->timezone($tz)->format('Y-m-d H:i') }}
                    @endif
                    · {{ $published->audience->label() }}
                </p>

                <x-slot:footer>
                    <x-ui.link-button :href="route('office.knowledge.show', $article->slug)" variant="ghost">
                        <x-ui.icon name="eye" size="sm" />
                        {{ __('ui.knowledge.view_published') }}
                    </x-ui.link-button>
                    @if ($canPublish)
                        <x-ui.button variant="outline" x-data x-on:click="$dispatch('ui:modal-open', { name: 'archive-article' })">
                            {{ __('ui.knowledge.archive') }}
                        </x-ui.button>
                    @endif
                    @unless ($draft)
                        <form method="POST" action="{{ route('admin.knowledge.revisions.store', $article) }}">
                            @csrf
                            <x-ui.button type="submit">
                                <x-ui.icon name="pencil-square" size="sm" />
                                {{ __('ui.knowledge.new_draft') }}
                            </x-ui.button>
                        </form>
                    @endunless
                </x-slot:footer>
            @else
                <p class="text-body text-muted">{{ __('ui.knowledge.not_published') }}</p>
                @unless ($draft)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.knowledge.revisions.store', $article) }}">
                            @csrf
                            <x-ui.button type="submit">{{ __('ui.knowledge.new_draft') }}</x-ui.button>
                        </form>
                    </x-slot:footer>
                @endunless
            @endif
        </x-ui.card>

        {{-- Draft editor --}}
        @if ($draft)
            <form method="POST" action="{{ route('admin.knowledge.draft.update', $article) }}" novalidate>
                @csrf
                @method('PATCH')

                <x-ui.card :title="__('ui.knowledge.draft_section', ['version' => $draft->version])">
                    <x-slot:header>
                        <x-ui.badge tone="warning">{{ __('ui.knowledge.status.draft') }}</x-ui.badge>
                    </x-slot:header>

                    @include('admin.knowledge._form', ['draft' => $draft])

                    <x-slot:footer>
                        <x-ui.button type="submit">{{ __('ui.knowledge.save_draft') }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>

            @if ($canPublish)
                <x-ui.card :title="__('ui.knowledge.publish')">
                    <p class="text-body text-muted">{{ __('ui.knowledge.publish_intro') }}</p>
                    <x-slot:footer>
                        <form
                            method="POST"
                            action="{{ route('admin.knowledge.publication.store', $article) }}"
                            x-data
                            x-on:ui:confirmed.window="if ($event.detail.name === 'publish-article') $el.submit()"
                        >
                            @csrf
                            <x-ui.button x-on:click="$dispatch('ui:modal-open', { name: 'publish-article' })">
                                <x-ui.icon name="check-circle" size="sm" />
                                {{ __('ui.knowledge.publish') }}
                            </x-ui.button>
                        </form>
                    </x-slot:footer>
                </x-ui.card>

                <x-ui.confirm-dialog name="publish-article" :title="__('ui.knowledge.publish_confirm_title')" :confirm-label="__('ui.knowledge.publish')">
                    {{ __('ui.knowledge.publish_confirm_body', ['version' => $draft->version]) }}
                </x-ui.confirm-dialog>
            @else
                <x-ui.alert tone="info">{{ __('ui.knowledge.editor_publish_note') }}</x-ui.alert>
            @endif
        @endif

        @if ($published && $canPublish)
            <form
                method="POST"
                action="{{ route('admin.knowledge.publication.destroy', $article) }}"
                x-data
                x-on:ui:confirmed.window="if ($event.detail.name === 'archive-article') $el.submit()"
            >
                @csrf
                @method('DELETE')
            </form>
            <x-ui.confirm-dialog name="archive-article" :title="__('ui.knowledge.archive_confirm_title')" :confirm-label="__('ui.knowledge.archive')" variant="danger">
                {{ __('ui.knowledge.archive_confirm_body') }}
            </x-ui.confirm-dialog>
        @endif
    </div>
</x-layouts.admin>
