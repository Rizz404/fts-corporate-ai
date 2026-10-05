{{--
    Ticket conversation + actions for requester (staff=false) or team staff (staff=true).
    All text is rendered with x-text; endpoints are server routes.
--}}
@props([
    'ticket',
    'staff' => false,
    'assigneeOptions' => [],
])

@php
    $base = $staff ? '/api/v1/staff/tickets/'.$ticket->public_id : '/api/v1/tickets/'.$ticket->public_id;
    $threadConfig = [
        'messagesUrl' => $base.'/messages',
        'postUrl' => $base.'/messages',
        'statusUrl' => $base.'/status',
        'assignUrl' => $staff ? $base.'/assignment' : null,
        'staff' => $staff,
        'ticket' => [
            'status' => $ticket->status->value,
            'version' => $ticket->version,
            'assignee_id' => $staff ? $ticket->assignee_id : null,
        ],
        'labels' => [
            'session' => __('ui.chat.errors.session'),
            'network' => __('ui.chat.errors.network'),
            'rateLimited' => __('ui.chat.errors.rate_limited'),
            'generic' => __('ui.chat.errors.generic'),
            'changed' => __('ui.tickets.errors.changed'),
            'closed' => __('ui.tickets.errors.closed'),
        ],
    ];
@endphp

<div x-data="ticketThread(@js($threadConfig))" class="space-y-4">
    <x-ui.card :title="__('ui.tickets.conversation')">
        <div x-ref="log" class="max-h-112 space-y-3 overflow-y-auto" role="log" aria-live="polite" aria-relevant="additions">
            <p x-show="messages.length === 0" class="text-body text-muted">{{ __('ui.tickets.no_messages') }}</p>
            <template x-for="message in messages" x-bind:key="message.id">
                <div
                    class="rounded-card border px-3 py-2"
                    x-bind:class="message.visibility === 'internal' ? 'border-warning/40 bg-warning-soft' : (message.mine ? 'ml-8 border-primary/30 bg-surface' : 'mr-8 border-border bg-surface-muted')"
                >
                    <div class="flex flex-wrap items-center gap-2 text-caption text-muted">
                        <span class="font-semibold text-text" x-text="message.author_name"></span>
                        <span x-text="formatTime(message.created_at)"></span>
                        <span x-show="message.visibility === 'internal'" class="font-semibold text-warning">{{ __('ui.tickets.internal_note') }}</span>
                    </div>
                    <p class="mt-1 text-body break-words whitespace-pre-line text-text" x-text="message.text"></p>
                </div>
            </template>
        </div>

        <div x-show="error" x-cloak role="alert" class="mt-3 rounded-control bg-danger-soft px-3 py-2 text-caption text-danger" x-text="error"></div>

        <form x-show="! closed" x-on:submit.prevent="send()" class="mt-4 space-y-2 border-t border-border pt-4">
            <label for="ticket-reply" class="text-body font-medium text-text">{{ __('ui.tickets.reply') }}</label>
            <textarea id="ticket-reply" x-model="text" rows="3" maxlength="5000" class="form-textarea {{ \App\Support\Ui\ComponentStyles::control() }}"></textarea>
            <div class="flex flex-wrap items-center justify-between gap-3">
                @if ($staff)
                    <div class="flex items-center gap-4" role="radiogroup" aria-label="{{ __('ui.tickets.visibility') }}">
                        <label class="inline-flex items-center gap-2 text-body">
                            <input type="radio" value="public" x-model="visibility" class="form-radio text-primary focus:ring-focus">
                            {{ __('ui.tickets.visibility_public') }}
                        </label>
                        <label class="inline-flex items-center gap-2 text-body">
                            <input type="radio" value="internal" x-model="visibility" class="form-radio text-primary focus:ring-focus">
                            {{ __('ui.tickets.visibility_internal') }}
                        </label>
                    </div>
                @else
                    <span></span>
                @endif
                <x-ui.button type="submit" size="sm" x-bind:disabled="pending || ! text.trim()">
                    <x-ui.icon name="paper-airplane" size="sm" />
                    {{ __('ui.chat.send') }}
                </x-ui.button>
            </div>
        </form>
        <p x-show="closed" x-cloak class="mt-4 text-caption text-muted">{{ __('ui.tickets.closed_note') }}</p>
    </x-ui.card>

    <x-ui.card :title="__('ui.tickets.actions_title')" x-show="! closed">
        @if ($staff)
            <div class="space-y-4">
                <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
                    <x-ui.field name="assignee_id" id="ticket-assignee" :label="__('ui.tickets.fields.assignee')">
                        <x-ui.select name="assignee_id" id="ticket-assignee" :options="$assigneeOptions" :value="$ticket->assignee_id" :placeholder="__('ui.choose')" x-model="assigneeId" />
                    </x-ui.field>
                    <x-ui.button variant="outline" x-on:click="assign()" x-bind:disabled="pending">{{ __('ui.tickets.assign') }}</x-ui.button>
                </div>

                <x-ui.field name="note" id="ticket-note" :label="__('ui.tickets.fields.note')" :help="__('ui.tickets.note_help')">
                    <x-ui.textarea name="note" id="ticket-note" rows="2" maxlength="5000" x-model="note" />
                </x-ui.field>

                <div class="flex flex-wrap gap-2">
                    <x-ui.button x-show="ticket.status === 'open'" x-on:click="changeStatus('in_progress')" x-bind:disabled="pending">{{ __('ui.tickets.start') }}</x-ui.button>
                    <x-ui.button x-show="ticket.status === 'in_progress'" x-on:click="changeStatus('resolved')" x-bind:disabled="pending">{{ __('ui.tickets.resolve') }}</x-ui.button>
                    <x-ui.button variant="outline" x-show="ticket.status === 'resolved'" x-on:click="changeStatus('in_progress')" x-bind:disabled="pending">{{ __('ui.tickets.reopen') }}</x-ui.button>
                    <x-ui.button variant="secondary" x-show="ticket.status === 'resolved'" x-on:click="changeStatus('closed')" x-bind:disabled="pending">{{ __('ui.tickets.close') }}</x-ui.button>
                </div>
            </div>
        @else
            <p x-show="ticket.status !== 'resolved'" class="text-body text-muted">{{ __('ui.tickets.requester_wait') }}</p>
            <div x-show="ticket.status === 'resolved'" x-cloak class="space-y-2">
                <p class="text-body text-text">{{ __('ui.tickets.requester_resolved') }}</p>
                <x-ui.button variant="secondary" x-on:click="changeStatus('closed')" x-bind:disabled="pending">{{ __('ui.tickets.close') }}</x-ui.button>
            </div>
        @endif
    </x-ui.card>
</div>
