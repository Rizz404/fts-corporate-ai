import { apiRequest } from './shared/api.js';
import { uuid } from './shared/uuid.js';

const POLL_MS = 5000;

/**
 * Ticket conversation for requester or staff pages. Polls every 5 s only
 * while the page is visible and online, using the last message id as
 * cursor and de-duplicating by id. Messages render with x-text.
 *
 * config: { messagesUrl, postUrl, statusUrl, assignUrl, staff, ticket: {status, version}, labels }
 */
export function ticketThread(config = {}) {
    const labels = config.labels ?? {};

    return {
        messages: [],
        seen: new Set(),
        lastId: 0,
        ticket: { ...(config.ticket ?? {}) },
        text: '',
        visibility: 'public',
        note: '',
        assigneeId: config.ticket?.assignee_id ?? '',
        pending: false,
        error: '',
        messageKey: null,
        actionKey: null,
        pollTimer: null,
        onVisibility: null,

        init() {
            this.onVisibility = () => this.schedule(0);
            document.addEventListener('visibilitychange', this.onVisibility);
            window.addEventListener('online', this.onVisibility);
            this.load();
        },

        destroy() {
            window.clearTimeout(this.pollTimer);
            document.removeEventListener('visibilitychange', this.onVisibility);
            window.removeEventListener('online', this.onVisibility);
        },

        get closed() {
            return this.ticket.status === 'closed';
        },

        canPoll() {
            return document.visibilityState === 'visible' && navigator.onLine !== false;
        },

        schedule(delay = POLL_MS) {
            window.clearTimeout(this.pollTimer);
            if (!this.canPoll()) {
                return;
            }
            this.pollTimer = window.setTimeout(() => this.load(), delay);
        },

        async load() {
            try {
                let afterId = this.lastId;
                for (let page = 0; page < 5; page++) {
                    const envelope = await apiRequest(`${config.messagesUrl}?after_id=${afterId}`);
                    this.append(envelope.data);
                    if (envelope.meta?.ticket) {
                        this.ticket = { ...this.ticket, ...envelope.meta.ticket };
                    }
                    if (!envelope.meta?.next_after_id) {
                        break;
                    }
                    afterId = envelope.meta.next_after_id;
                }
            } catch (error) {
                if (error.status === 401 || error.status === 419) {
                    this.error = labels.session;

                    return;
                }
            }
            this.schedule();
        },

        append(items) {
            let added = false;
            for (const item of items ?? []) {
                if (!Number.isInteger(item.id) || this.seen.has(item.id)) {
                    continue;
                }
                this.seen.add(item.id);
                this.messages.push(item);
                this.lastId = Math.max(this.lastId, item.id);
                added = true;
            }
            if (added) {
                this.$nextTick(() => {
                    const log = this.$refs.log;
                    if (log) log.scrollTop = log.scrollHeight;
                });
            }
        },

        async send() {
            const text = this.text.trim();
            if (!text || this.pending || this.closed) return;
            this.pending = true;
            this.error = '';
            this.messageKey ??= uuid();
            try {
                const body = { text, idempotency_key: this.messageKey };
                if (config.staff) body.visibility = this.visibility;
                const envelope = await apiRequest(config.postUrl, { method: 'POST', body });
                this.append([envelope.data]);
                this.text = '';
                this.messageKey = null;
            } catch (error) {
                this.fail(error);
            } finally {
                this.pending = false;
            }
        },

        async changeStatus(status) {
            if (this.pending) return;
            this.pending = true;
            this.error = '';
            this.actionKey ??= uuid();
            try {
                await apiRequest(config.statusUrl, {
                    method: 'PATCH',
                    body: { status, expected_version: this.ticket.version, note: this.note.trim() || null, idempotency_key: this.actionKey },
                });
                window.location.reload();
            } catch (error) {
                this.actionKey = error.isNetwork || error.isAbort ? this.actionKey : null;
                this.fail(error);
                this.pending = false;
            }
        },

        async assign() {
            if (this.pending || !this.assigneeId) return;
            this.pending = true;
            this.error = '';
            this.actionKey ??= uuid();
            try {
                await apiRequest(config.assignUrl, {
                    method: 'PATCH',
                    body: { assignee_id: Number(this.assigneeId), expected_version: this.ticket.version, idempotency_key: this.actionKey },
                });
                window.location.reload();
            } catch (error) {
                this.actionKey = error.isNetwork || error.isAbort ? this.actionKey : null;
                this.fail(error);
                this.pending = false;
            }
        },

        fail(error) {
            const field = error.fields ? Object.values(error.fields)[0]?.[0] : '';
            const byCode = {
                version_conflict: labels.changed,
                ticket_closed: labels.closed,
                rate_limited: labels.rateLimited,
            };
            if (error.isNetwork || error.isAbort) {
                this.error = labels.network;
            } else if (error.status === 401 || error.status === 419) {
                this.error = labels.session;
            } else if (error.status === 429) {
                this.error = labels.rateLimited;
            } else {
                this.error = byCode[error.code] || field || error.message || labels.generic;
            }
        },

        formatTime(iso) {
            try {
                return new Intl.DateTimeFormat(document.documentElement.lang || undefined, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso));
            } catch {
                return iso ?? '';
            }
        },
    };
}
