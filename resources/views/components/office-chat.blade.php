{{--
    AI chat dock content (Plan 5). Messages and card fields are rendered with
    x-text and server-built paths only. Config comes from the current scene.
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

                <ul x-show="message.cards.length" class="mt-2 space-y-1" aria-label="{{ __('ui.chat.sources') }}">
                    <template x-for="card in message.cards" x-bind:key="card.key">
                        <li>
                            <a
                                x-bind:href="card.path"
                                class="block rounded-control border border-border bg-surface px-3 py-2 hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
                            >
                                <span class="block text-body font-medium text-text" x-text="card.title"></span>
                                <span class="block text-caption text-muted" x-text="card.meta"></span>
                            </a>
                        </li>
                    </template>
                </ul>

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
