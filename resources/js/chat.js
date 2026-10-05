import { apiRequest } from './shared/api.js';
import { normalizeActions, normalizeCard, normalizeCards } from './safe-card-renderer.js';
import { uuid } from './shared/uuid.js';

const STORAGE_PREFIX = 'fts-chat:';
const POLL_INTERVAL_MS = 2000;
const MAX_INPUT = 2000;

function storageKey(userId) {
    return `${STORAGE_PREFIX}${userId}:conversation`;
}

function readStorage(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(key, value) {
    try {
        if (value === null) {
            window.localStorage.removeItem(key);
        } else {
            window.localStorage.setItem(key, value);
        }
    } catch {
        // Storage unavailable: the conversation just won't resume after reload.
    }
}

/** Removes every stored conversation id (called on logout). */
export function clearChatStorage() {
    try {
        Object.keys(window.localStorage)
            .filter((key) => key.startsWith(STORAGE_PREFIX))
            .forEach((key) => window.localStorage.removeItem(key));
    } catch {
        // Nothing to clear.
    }
}

/**
 * AI chat dock. One active submit per tab (the server also allows one per
 * user). A retry reuses the same client_turn_id, so the server returns the
 * stored result instead of calling the model again. Text is rendered with
 * x-text; cards and actions go through safe-card-renderer.
 *
 * A ticket draft card is only confirmed by the explicit two-step button
 * (expected_revision + idempotency key, Plan 6 API); chat text such as "yes"
 * never confirms anything. The confirmation card comes from the API result.
 *
 * config: { userId, locale, scene, articleId, ticketPublicId, clientTimeoutMs, badgeClasses, labels }
 */
export function corporateChat(config = {}) {
    const labels = config.labels ?? {};

    return {
        messages: [],
        draft: '',
        pending: false,
        error: '',
        canRetry: false,
        conversationId: null,
        pendingTurn: null,
        maxInput: MAX_INPUT,
        nextKey: 1,
        pollTimer: null,
        pollDeadline: 0,
        onAsk: null,

        init() {
            this.conversationId = readStorage(storageKey(config.userId));
            if (this.conversationId) {
                this.loadHistory();
            }

            this.onAsk = (event) => {
                const message = typeof event.detail?.message === 'string' ? event.detail.message : '';
                if (message) {
                    window.dispatchEvent(new CustomEvent('chat:open'));
                    this.send(message);
                }
            };
            window.addEventListener('chat:ask', this.onAsk);
        },

        destroy() {
            window.removeEventListener('chat:ask', this.onAsk);
            window.clearTimeout(this.pollTimer);
        },

        cardOptions() {
            return { locale: config.locale, versionLabel: labels.version };
        },

        badge(tone) {
            const classes = config.badgeClasses ?? {};

            return classes[tone] ?? classes.neutral ?? '';
        },

        toView(message) {
            const cards = normalizeCards(message.ui, this.cardOptions());
            // A draft card already has its own review button.
            const draftPaths = cards.filter((card) => card.type === 'ticket_draft').map((card) => card.path);

            return {
                key: message.id ? `m${message.id}` : `local${this.nextKey++}`,
                role: message.role === 'user' ? 'user' : 'assistant',
                text: typeof message.text === 'string' ? message.text : '',
                cards,
                actions: normalizeActions(message.actions).filter((action) => !(action.type === 'review_ticket_draft' && draftPaths.includes(action.path))),
                stale: message.stale === true,
            };
        },

        async loadHistory() {
            let afterId = 0;
            const loaded = [];
            try {
                for (let page = 0; page < 4; page++) {
                    const envelope = await apiRequest(`/api/v1/conversations/${this.conversationId}/messages?after_id=${afterId}`);
                    loaded.push(...envelope.data);
                    if (!envelope.meta?.next_after_id) {
                        break;
                    }
                    afterId = envelope.meta.next_after_id;
                }
                this.messages = loaded.map((message) => this.toView(message));
                this.scrollToEnd();
            } catch (error) {
                if (error.status === 404) {
                    this.resetConversation();
                }
            }
        },

        resetConversation() {
            this.conversationId = null;
            writeStorage(storageKey(config.userId), null);
        },

        async ensureConversation() {
            if (this.conversationId) {
                return;
            }
            const envelope = await apiRequest('/api/v1/conversations', {
                method: 'POST',
                body: { locale: config.locale, scene: config.scene },
            });
            this.conversationId = envelope.data.conversation_id;
            writeStorage(storageKey(config.userId), this.conversationId);
        },

        send(text = this.draft) {
            const message = String(text ?? '').trim();
            if (!message || this.pending) {
                return;
            }
            if (message.length > MAX_INPUT) {
                this.error = labels.tooLong;

                return;
            }

            this.draft = '';
            this.messages.push(this.toView({ role: 'user', text: message }));
            this.pendingTurn = {
                client_turn_id: uuid(),
                message,
                locale: config.locale,
                context: { scene: config.scene, article_id: config.articleId ?? null, ticket_public_id: config.ticketPublicId ?? null },
            };
            this.scrollToEnd();
            this.submit();
        },

        async submit() {
            if (!this.pendingTurn) {
                return;
            }
            this.pending = true;
            this.error = '';
            this.canRetry = false;

            try {
                await this.ensureConversation();
                const envelope = await apiRequest(`/api/v1/conversations/${this.conversationId}/messages`, {
                    method: 'POST',
                    body: this.pendingTurn,
                    signal: AbortSignal.timeout(config.clientTimeoutMs ?? 70000),
                });
                this.handleTurn(envelope.data);
            } catch (error) {
                this.handleError(error);
            }
        },

        handleTurn(turn) {
            if (turn.status === 'processing') {
                this.pollDeadline = Date.now() + (config.clientTimeoutMs ?? 70000);
                this.poll(turn.turn_id);

                return;
            }

            if (turn.message) {
                this.messages.push(this.toView({
                    id: turn.message.id,
                    role: 'assistant',
                    text: turn.message.text,
                    ui: turn.ui,
                    actions: turn.actions,
                }));
            }
            this.pendingTurn = null;
            this.pending = false;
            this.scrollToEnd();
        },

        poll(turnId) {
            window.clearTimeout(this.pollTimer);
            this.pollTimer = window.setTimeout(async () => {
                try {
                    const envelope = await apiRequest(`/api/v1/conversations/${this.conversationId}/turns/${turnId}`);
                    if (envelope.data.status === 'processing' && Date.now() < this.pollDeadline) {
                        this.poll(turnId);

                        return;
                    }
                    if (envelope.data.status === 'processing') {
                        this.fail(labels.timeout, true);

                        return;
                    }
                    this.handleTurn(envelope.data);
                } catch (error) {
                    this.handleError(error);
                }
            }, POLL_INTERVAL_MS);
        },

        handleError(error) {
            if (error.status === 404 && this.conversationId) {
                // Conversation no longer accessible: start a new one on retry.
                this.resetConversation();
                this.fail(labels.generic, true);

                return;
            }

            const byCode = {
                turn_in_progress: [labels.busy, true],
                idempotency_conflict: [labels.generic, false],
                validation_failed: [labels.invalid, false],
                invalid_context: [labels.invalid, false],
                password_change_required: [labels.auth, false],
            };
            if (error.code && byCode[error.code]) {
                this.fail(...byCode[error.code]);

                return;
            }

            if (error.isAbort) {
                this.fail(labels.timeout, true);
            } else if (error.isNetwork) {
                this.fail(labels.network, true);
            } else if (error.status === 401) {
                this.fail(labels.auth, false);
            } else if (error.status === 419) {
                this.fail(labels.session, false);
            } else if (error.status === 429) {
                this.fail(labels.rateLimited, true);
            } else {
                this.fail(labels.generic, true);
            }
        },

        fail(message, canRetry) {
            this.error = message ?? '';
            this.canRetry = canRetry && this.pendingTurn !== null;
            this.pending = false;
            if (!this.canRetry) {
                this.pendingTurn = null;
            }
        },

        retry() {
            if (this.pendingTurn && !this.pending) {
                this.submit();
            }
        },

        askConfirm(card) {
            if (card.expired || card.busy) {
                return;
            }
            card.error = '';
            card.confirming = true;
        },

        cancelConfirm(card) {
            if (!card.busy) {
                card.confirming = false;
                card.error = '';
            }
        },

        replaceCard(message, card, replacement) {
            const index = message.cards.findIndex((item) => item.key === card.key);
            if (index !== -1 && replacement) {
                message.cards.splice(index, 1, replacement);
            }
        },

        async confirmDraft(message, card) {
            if (card.busy || card.expired || !card.confirming) {
                return;
            }
            card.busy = true;
            card.error = '';
            // Stable per draft revision so a retry after a lost response returns the same ticket.
            card.confirmKey ??= uuid();

            try {
                const envelope = await apiRequest(`/api/v1/ticket-drafts/${card.draftId}/confirm`, {
                    method: 'POST',
                    body: { expected_revision: card.revision, idempotency_key: card.confirmKey },
                });
                this.replaceCard(message, card, normalizeCard({ type: 'ticket_confirmation', ...envelope.data }, this.cardOptions()));
                message.actions = message.actions.filter((action) => action.type !== 'review_ticket_draft');
            } catch (error) {
                card.busy = false;
                if (error.code === 'draft_expired') {
                    card.expired = true;
                    card.confirming = false;
                    card.error = labels.draftExpired;
                } else if (error.code === 'revision_conflict' || error.code === 'draft_consumed') {
                    await this.reloadDraft(message, card);
                } else if (error.isNetwork || error.isAbort || error.status >= 500) {
                    card.error = labels.network;
                } else if (error.status === 419 || error.status === 401) {
                    card.error = labels.session;
                } else if (error.status === 429) {
                    card.error = labels.rateLimited;
                } else {
                    card.error = labels.generic;
                }
            }
        },

        async reloadDraft(message, card) {
            try {
                const envelope = await apiRequest(`/api/v1/ticket-drafts/${card.draftId}`);
                const fresh = normalizeCard({ type: 'ticket_draft', ...envelope.data, path: card.path }, this.cardOptions());
                if (fresh) {
                    fresh.error = labels.draftChanged;
                    this.replaceCard(message, card, fresh);
                }
            } catch {
                card.error = labels.generic;
            }
        },

        onEnter(event) {
            if (!event.shiftKey && !event.isComposing) {
                event.preventDefault();
                this.send();
            }
        },

        scrollToEnd() {
            this.$nextTick(() => {
                const log = this.$refs.log;
                if (log) {
                    log.scrollTop = log.scrollHeight;
                }
            });
        },
    };
}
