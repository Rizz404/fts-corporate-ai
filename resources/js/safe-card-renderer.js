import { allowedToken, isSafeUrl } from './shared/dom.js';

/**
 * Normalizes server UI descriptors before Alpine renders them with x-text
 * and :href. Unknown types and unknown fields are dropped; links must be
 * same-origin relative paths built by the server.
 */
export const CARD_TYPES = ['source_card'];
export const ACTION_TYPES = ['open_article', 'open_scene'];

function safePath(value) {
    const path = typeof value === 'string' ? value : '';

    return path.startsWith('/') && !path.startsWith('//') && isSafeUrl(path) ? path : null;
}

function text(value, max = 300) {
    return typeof value === 'string' ? value.slice(0, max) : '';
}

function formatDate(iso, locale) {
    if (typeof iso !== 'string' || iso === '') {
        return '';
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    try {
        return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(date);
    } catch {
        return date.toISOString().slice(0, 10);
    }
}

export function normalizeCards(cards, { locale = 'id', versionLabel = 'v' } = {}) {
    if (!Array.isArray(cards)) {
        return [];
    }

    return cards
        .filter((card) => card && allowedToken(card.type, CARD_TYPES))
        .map((card, index) => {
            const version = Number.isInteger(card.version) ? card.version : null;
            const from = formatDate(card.effective_from, locale);

            return {
                key: `${card.type}-${Number.isInteger(card.revision_id) ? card.revision_id : index}`,
                type: card.type,
                title: text(card.title, 180),
                meta: [version !== null ? `${versionLabel} ${version}` : '', from].filter(Boolean).join(' · '),
                path: safePath(card.path),
            };
        })
        .filter((card) => card.title !== '');
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
