import { allowedToken, isSafeUrl } from './shared/dom.js';

/**
 * Normalizes server UI descriptors before Alpine renders them. Only
 * whitelisted card/action types and fields survive; every value becomes a
 * bounded string/number rendered with x-text (textContent), and links must
 * be same-origin relative paths built by the server. Card markup and classes
 * live in Blade templates (x-office-chat), not here.
 */
export const CARD_TYPES = ['source_card', 'ticket_list', 'ticket_detail', 'ticket_draft', 'ticket_confirmation'];
export const ACTION_TYPES = ['open_article', 'open_ticket', 'open_scene', 'review_ticket_draft'];
export const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];
export const TONES = ['info', 'warning', 'success', 'neutral'];

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const MAX_LIST_ITEMS = 20;
const MAX_CARD_MESSAGES = 3;

export function safePath(value) {
    const path = typeof value === 'string' ? value : '';

    return path.startsWith('/') && !path.startsWith('//') && !path.startsWith('/\\') && isSafeUrl(path) ? path : null;
}

export function uuidOrNull(value) {
    return typeof value === 'string' && UUID.test(value) ? value.toLowerCase() : null;
}

function text(value, max = 300) {
    return typeof value === 'string' ? value.slice(0, max) : '';
}

function formatDate(iso, locale, withTime = false) {
    if (typeof iso !== 'string' || iso === '') {
        return '';
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    try {
        return new Intl.DateTimeFormat(locale, withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' }).format(date);
    } catch {
        return date.toISOString().slice(0, 10);
    }
}

function ticketSummary(item, locale) {
    const status = allowedToken(item?.status, STATUSES);
    const ticketId = uuidOrNull(item?.ticket_id);
    if (!status || !ticketId) {
        return null;
    }

    return {
        ticketId,
        reference: text(item.reference, 40),
        subject: text(item.subject, 160),
        status,
        statusLabel: text(item.status_label, 40),
        tone: allowedToken(item.status_tone, TONES, 'neutral'),
        updated: formatDate(item.updated_at, locale),
        path: safePath(item.path),
    };
}

const NORMALIZERS = {
    source_card(card, { locale, versionLabel }) {
        const version = Number.isInteger(card.version) ? card.version : null;
        const title = text(card.title, 180);
        if (title === '') {
            return null;
        }

        return {
            key: `source_card-${Number.isInteger(card.revision_id) ? card.revision_id : title}`,
            title,
            meta: [version !== null ? `${versionLabel} ${version}` : '', formatDate(card.effective_from, locale)].filter(Boolean).join(' · '),
            path: safePath(card.path),
        };
    },

    ticket_list(card, { locale }) {
        const items = (Array.isArray(card.items) ? card.items : [])
            .slice(0, MAX_LIST_ITEMS)
            .map((item) => ticketSummary(item, locale))
            .filter(Boolean);
        if (items.length === 0) {
            return null;
        }

        return {
            key: `ticket_list-${items.map((item) => item.ticketId).join(',')}`,
            items,
            total: Number.isInteger(card.total) ? card.total : items.length,
        };
    },

    ticket_detail(card, { locale }) {
        const summary = ticketSummary(card, locale);
        if (!summary) {
            return null;
        }
        const messages = (Array.isArray(card.messages) ? card.messages : [])
            .slice(-MAX_CARD_MESSAGES)
            .filter((message) => Number.isInteger(message?.id))
            .map((message) => ({
                key: `tm-${message.id}`,
                author: text(message.author_name, 80),
                mine: message.mine === true,
                text: text(message.text, 300),
                when: formatDate(message.created_at, locale, true),
            }));

        return {
            ...summary,
            key: `ticket_detail-${summary.ticketId}`,
            teamLabel: text(card.team_label, 40),
            categoryLabel: text(card.category_label, 60),
            assignee: text(card.assignee_name, 80),
            messages,
        };
    },

    ticket_draft(card, { locale }) {
        const draftId = uuidOrNull(card.draft_id);
        if (!draftId || !Number.isInteger(card.revision) || card.revision < 1) {
            return null;
        }

        return {
            key: `ticket_draft-${draftId}`,
            draftId,
            revision: card.revision,
            teamLabel: text(card.team_label, 40),
            categoryLabel: text(card.category_label, 60),
            subject: text(card.subject, 160),
            description: text(card.description, 300),
            expires: formatDate(card.expires_at, locale, true),
            expired: card.expired === true,
            path: safePath(card.path),
            // Client-only state for the explicit confirm step.
            confirming: false,
            busy: false,
            error: '',
            confirmKey: null,
        };
    },

    ticket_confirmation(card, { locale }) {
        const summary = ticketSummary(card, locale);

        return summary ? { ...summary, key: `ticket_confirmation-${summary.ticketId}` } : null;
    },
};

export function normalizeCard(card, options = {}) {
    const type = allowedToken(card?.type, CARD_TYPES);
    if (!type) {
        return null;
    }
    const normalized = NORMALIZERS[type](card, { locale: 'id', versionLabel: 'v', ...options });

    return normalized ? { type, ...normalized } : null;
}

export function normalizeCards(cards, options = {}) {
    if (!Array.isArray(cards)) {
        return [];
    }

    return cards.map((card) => normalizeCard(card, options)).filter(Boolean);
}

export function normalizeActions(actions) {
    if (!Array.isArray(actions)) {
        return [];
    }

    return actions
        .filter((action) => action && allowedToken(action.type, ACTION_TYPES))
        .map((action, index) => ({
            key: `${action.type}-${index}`,
            type: action.type,
            label: text(action.label, 80),
            path: safePath(action.path),
        }))
        .filter((action) => action.label !== '' && action.path !== null);
}
