{{--
    AI chat dock content (Plan 5/7). Card markup lives in the templates below
    (one per whitelisted type, normalized by safe-card-renderer); every
    message and card field is rendered with x-text and server-built paths
    only. Config comes from the current scene.
--}}
@props([
    'scene',
])

@php
    $chatConfig = [
        'userId' => auth()->id(),
        'locale' => $scene->locale,
        'scene' => $scene->context['scene'],
        'articleId' => $scene->context['article_id'],
        'ticketPublicId' => $scene->context['ticket_public_id'],
        'badgeClasses' => collect(['info', 'warning', 'success', 'neutral'])
            ->mapWithKeys(fn (string $tone) => [$tone => \App\Support\Ui\ComponentStyles::badge($tone)])
            ->all(),
        'clientTimeoutMs' => (int) config('assistant.client_timeout_seconds') * 1000,
        'labels' => [
            'version' => __('ui.chat.version'),
            'tooLong' => __('ui.chat.errors.too_long'),
            'busy' => __('ui.chat.errors.busy'),
            'invalid' => __('ui.chat.errors.invalid'),
            'auth' => __('ui.chat.errors.auth'),
            'session' => __('ui.chat.errors.session'),
            'rateLimited' => __('ui.chat.errors.rate_limited'),
            'timeout' => __('ui.chat.errors.timeout'),
            'network' => __('ui.chat.errors.network'),
            'generic' => __('ui.chat.errors.generic'),
            'draftExpired' => __('ui.chat.cards.expired'),
            'draftChanged' => __('ui.tickets.errors.changed'),
        ],
    ];
@endphp

<div x-data="corporateChat(@js($chatConfig))" class="flex h-full min-h-0 flex-col">
    <div x-ref="log" class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4" role="log" aria-live="polite" aria-relevant="additions">
        <p x-show="messages.length === 0" class="text-body text-muted">{{ __('ui.chat.intro') }}</p>

        <template x-for="message in messages" x-bind:key="message.key">
            <div
                class="rounded-card px-3 py-2 text-body"
                x-bind:class="message.role === 'user' ? 'ml-8 bg-primary text-on-primary' : 'mr-4 border border-border bg-surface-muted text-text'"
            >
                <span class="sr-only" x-text="message.role === 'user' ? @js(__('ui.chat.you')) : @js(__('ui.chat.title'))"></span>
                <p class="break-words whitespace-pre-line" x-text="message.text"></p>
                <p x-show="message.stale" class="mt-1 text-caption text-warning">{{ __('ui.assistant.history_stale') }}</p>

                <div x-show="message.cards.length" class="mt-2 space-y-2">
                    <template x-for="card in message.cards" x-bind:key="card.key">
                        <div>
                            {{-- Knowledge source --}}
                            <template x-if="card.type === 'source_card'">
                                <a
                                    x-bind:href="card.path"
                                    class="block rounded-control border border-border bg-surface px-3 py-2 hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                >
                                    <span class="sr-only">{{ __('ui.chat.sources') }}:</span>
                                    <span class="block text-body font-medium text-text" x-text="card.title"></span>
                                    <span class="block text-caption text-muted" x-text="card.meta"></span>
                                </a>
                            </template>

                            {{-- Own requests --}}
                            <template x-if="card.type === 'ticket_list'">
                                <ul class="divide-y divide-border rounded-control border border-border bg-surface" aria-label="{{ __('ui.tickets.my_requests') }}">
                                    <template x-for="item in card.items" x-bind:key="item.ticketId">
                                        <li>
                                            <a x-bind:href="item.path" class="block px-3 py-2 hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
                                                <span class="flex items-center justify-between gap-2">
                                                    <span class="text-caption text-muted" x-text="item.reference"></span>
                                                    <span x-bind:class="badge(item.tone)" x-text="item.statusLabel"></span>
                                                </span>
                                                <span class="block break-words text-body font-medium text-text" x-text="item.subject"></span>
                                            </a>
                                        </li>
                                    </template>
                                </ul>
                            </template>

                            {{-- One own request --}}
                            <template x-if="card.type === 'ticket_detail'">
                                <div class="rounded-control border border-border bg-surface px-3 py-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-caption text-muted" x-text="card.reference"></span>
                                        <span x-bind:class="badge(card.tone)" x-text="card.statusLabel"></span>
                                    </div>
                                    <p class="break-words text-body font-medium text-text" x-text="card.subject"></p>
                                    <p class="text-caption text-muted" x-text="[card.teamLabel, card.categoryLabel, card.assignee].filter(Boolean).join(' · ')"></p>
                                    <p class="mt-2 text-caption font-semibold text-text">{{ __('ui.chat.cards.latest_replies') }}</p>
                                    <p x-show="card.messages.length === 0" class="text-caption text-muted">{{ __('ui.chat.cards.no_replies') }}</p>
                                    <ul class="mt-1 space-y-1">
                                        <template x-for="reply in card.messages" x-bind:key="reply.key">
                                            <li class="rounded-control bg-surface-muted px-2 py-1">
                                                <span class="block text-caption text-muted" x-text="[reply.author, reply.when].filter(Boolean).join(' · ')"></span>
                                                <span class="block break-words whitespace-pre-line text-caption text-text" x-text="reply.text"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                            {{-- Draft prepared by the assistant: only these buttons confirm it --}}
                            <template x-if="card.type === 'ticket_draft'">
                                <div class="rounded-control border border-primary bg-surface px-3 py-2">
                                    <p class="text-caption font-semibold text-primary">{{ __('ui.chat.cards.draft_title') }}</p>
                                    <p class="text-caption text-muted" x-text="[card.teamLabel, card.categoryLabel].filter(Boolean).join(' · ')"></p>
                                    <p class="break-words text-body font-medium text-text" x-text="card.subject"></p>
                                    <p class="mt-1 break-words whitespace-pre-line text-caption text-text" x-text="card.description"></p>
                                    <p x-show="! card.expired" class="mt-1 text-caption text-muted">{{ __('ui.chat.cards.draft_note') }}</p>
                                    <p x-show="card.expired" class="mt-1 text-caption text-warning">{{ __('ui.chat.cards.expired') }}</p>
                                    <p x-show="card.error" x-text="card.error" role="alert" class="mt-1 text-caption text-danger"></p>

                                    <p x-show="card.confirming" class="mt-2 text-caption font-semibold text-text">{{ __('ui.chat.cards.confirm_question') }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <a x-bind:href="card.path" x-show="card.path" class="{{ \App\Support\Ui\ComponentStyles::button('outline', 'sm') }}">{{ __('ui.chat.cards.review_draft') }}</a>
                                        <button
                                            type="button"
                                            x-show="! card.confirming"
                                            x-on:click="askConfirm(card)"
                                            x-bind:disabled="card.expired || card.busy"
                                            class="{{ \App\Support\Ui\ComponentStyles::button('primary', 'sm') }}"
                                        >{{ __('ui.chat.cards.confirm') }}</button>
                                        <button
                                            type="button"
                                            x-show="card.confirming"
                                            x-on:click="confirmDraft(message, card)"
                                            x-bind:disabled="card.expired || card.busy"
                                            x-bind:aria-busy="card.busy ? 'true' : 'false'"
                                            class="{{ \App\Support\Ui\ComponentStyles::button('primary', 'sm') }}"
                                        >{{ __('ui.chat.cards.confirm_yes') }}</button>
                                        <button
                                            type="button"
                                            x-show="card.confirming"
                                            x-on:click="cancelConfirm(card)"
                                            x-bind:disabled="card.busy"
                                            class="{{ \App\Support\Ui\ComponentStyles::button('ghost', 'sm') }}"
                                        >{{ __('ui.cancel') }}</button>
                                    </div>
                                </div>
                            </template>

                            {{-- Ticket created by the confirm API (canonical result, not the model) --}}
                            <template x-if="card.type === 'ticket_confirmation'">
                                <a
                                    x-bind:href="card.path"
                                    class="block rounded-control border border-success bg-success-soft px-3 py-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                                >
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="text-caption font-semibold text-success">{{ __('ui.chat.cards.confirmed_title') }}</span>
                                        <span x-bind:class="badge(card.tone)" x-text="card.statusLabel"></span>
                                    </span>
                                    <span class="block text-caption text-muted">{{ __('ui.chat.cards.reference') }}: <span x-text="card.reference"></span></span>
                                    <span class="block break-words text-body font-medium text-text" x-text="card.subject"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>

                <div x-show="message.actions.length" class="mt-2 flex flex-wrap gap-2">
                    <template x-for="action in message.actions" x-bind:key="action.key">
                        <a x-bind:href="action.path" x-text="action.label" class="{{ \App\Support\Ui\ComponentStyles::button('outline', 'sm') }}"></a>
                    </template>
                </div>
            </div>
        </template>

        <div x-show="pending" x-cloak class="flex items-center gap-2 text-caption text-muted">
            <x-ui.spinner size="sm" />
            <span>{{ __('ui.chat.thinking') }}</span>
        </div>
    </div>

    <div x-show="error" x-cloak role="alert" class="mx-4 mb-2 flex items-start justify-between gap-2 rounded-control bg-danger-soft px-3 py-2 text-caption text-danger">
        <span x-text="error"></span>
        <button type="button" x-show="canRetry" x-on:click="retry()" class="shrink-0 font-semibold underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus">
            {{ __('ui.chat.retry') }}
        </button>
    </div>

    <form x-on:submit.prevent="send()" class="border-t border-border p-3">
        <label for="chat-input" class="sr-only">{{ __('ui.chat.input_label') }}</label>
        <textarea
            id="chat-input"
            x-model="draft"
            x-on:keydown.enter="onEnter($event)"
            x-bind:maxlength="maxInput"
            rows="2"
            placeholder="{{ __('ui.chat.placeholder') }}"
            class="form-textarea {{ \App\Support\Ui\ComponentStyles::control() }} resize-none"
        ></textarea>
        <div class="mt-2 flex items-center justify-between gap-2">
            <span class="text-caption text-muted" x-text="draft.length + ' / ' + maxInput"></span>
            <x-ui.button type="submit" size="sm" x-bind:disabled="pending || ! draft.trim()">
                <x-ui.icon name="paper-airplane" size="sm" />
                {{ __('ui.chat.send') }}
            </x-ui.button>
        </div>
    </form>
</div>
