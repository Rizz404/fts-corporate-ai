import { test } from 'node:test';
import assert from 'node:assert/strict';

import { normalizeActions, normalizeCard, normalizeCards, safePath } from '../../resources/js/safe-card-renderer.js';

const DRAFT_ID = '3f2b8c1e-4a5d-4e6f-8a9b-0c1d2e3f4a5b';
const TICKET_ID = '9a8b7c6d-5e4f-4a3b-8c2d-1e0f9a8b7c6d';

test('unknown card types and malformed cards are dropped', () => {
    const cards = normalizeCards([
        { type: 'html', html: '<img src=x onerror=alert(1)>' },
        { type: 'source_card', title: '' },
        { type: 'ticket_draft', draft_id: 'not-a-uuid', revision: 1 },
        { type: 'ticket_draft', draft_id: DRAFT_ID, revision: 0 },
        { type: 'ticket_confirmation', ticket_id: TICKET_ID, status: 'hacked' },
        null,
        'source_card',
    ]);

    assert.deepEqual(cards, []);
});

test('non-array input yields empty lists', () => {
    assert.deepEqual(normalizeCards(undefined), []);
    assert.deepEqual(normalizeCards({ type: 'source_card' }), []);
    assert.deepEqual(normalizeActions('open_scene'), []);
});

test('source card keeps only whitelisted fields and a same-origin path', () => {
    const [card] = normalizeCards([{
        type: 'source_card',
        revision_id: 7,
        version: 2,
        title: '<b>Leave</b>',
        effective_from: '2026-01-01T00:00:00+00:00',
        path: 'javascript:alert(1)',
        onclick: 'steal()',
    }], { locale: 'en', versionLabel: 'Version' });

    assert.equal(card.type, 'source_card');
    assert.equal(card.title, '<b>Leave</b>', 'text is kept verbatim and rendered with textContent');
    assert.equal(card.path, null);
    assert.match(card.meta, /^Version 2 · /);
    assert.equal('onclick' in card, false);
});

test('safePath accepts only relative same-origin paths', () => {
    assert.equal(safePath('/office/requests'), '/office/requests');
    assert.equal(safePath('//evil.example/x'), null);
    assert.equal(safePath('/\\evil.example'), null);
    assert.equal(safePath('https://evil.example/'), null);
    assert.equal(safePath('javascript:alert(1)'), null);
    assert.equal(safePath(42), null);
});

test('ticket draft card exposes confirm state and bounded text', () => {
    const card = normalizeCard({
        type: 'ticket_draft',
        draft_id: DRAFT_ID.toUpperCase(),
        revision: 3,
        team_label: 'IT',
        category_label: 'Network',
        subject: 's'.repeat(500),
        description: 'd'.repeat(5000),
        expired: true,
        path: `/office/requests/create?draft=${DRAFT_ID}`,
    });

    assert.equal(card.draftId, DRAFT_ID);
    assert.equal(card.revision, 3);
    assert.equal(card.subject.length, 160);
    assert.equal(card.description.length, 300);
    assert.equal(card.expired, true);
    assert.equal(card.confirming, false);
    assert.equal(card.busy, false);
    assert.equal(card.confirmKey, null);
});

test('ticket list keeps valid items only and maps unknown tones to neutral', () => {
    const [card] = normalizeCards([{
        type: 'ticket_list',
        total: 3,
        items: [
            { ticket_id: TICKET_ID, reference: 'CORP-1', subject: 'A', status: 'open', status_label: 'Open', status_tone: 'info', path: '/office/requests/x' },
            { ticket_id: TICKET_ID, reference: 'CORP-2', subject: 'B', status: 'resolved', status_label: 'Resolved', status_tone: 'bg-red-500', path: '/x' },
            { ticket_id: 'bad', status: 'open' },
            { ticket_id: TICKET_ID, status: 'deleted' },
        ],
    }]);

    assert.equal(card.items.length, 2);
    assert.equal(card.items[0].tone, 'info');
    assert.equal(card.items[1].tone, 'neutral');
    assert.equal(card.total, 3);
});

test('ticket detail shows at most three messages', () => {
    const card = normalizeCard({
        type: 'ticket_detail',
        ticket_id: TICKET_ID,
        status: 'in_progress',
        status_tone: 'warning',
        messages: [1, 2, 3, 4, 5].map((id) => ({ id, author_name: 'Staff', text: `m${id}` })).concat([{ id: 'x', text: 'bad' }]),
    });

    assert.deepEqual(card.messages.map((message) => message.text), ['m4', 'm5']);
});

test('confirmation card is built from the API ticket shape', () => {
    const card = normalizeCard({ type: 'ticket_confirmation', ticket_id: TICKET_ID, reference: 'CORP-20261005-ABCDEFGH', status: 'open', status_label: 'Open', status_tone: 'info', path: `/office/requests/${TICKET_ID}` });

    assert.equal(card.reference, 'CORP-20261005-ABCDEFGH');
    assert.equal(card.path, `/office/requests/${TICKET_ID}`);
});

test('actions require a whitelisted type, a label, and a safe path', () => {
    const actions = normalizeActions([
        { type: 'open_scene', label: 'HR', path: '/office/hr' },
        { type: 'review_ticket_draft', label: 'Review', path: `/office/requests/create?draft=${DRAFT_ID}` },
        { type: 'open_ticket', label: 'Open', path: 'https://evil.example' },
        { type: 'run_script', label: 'Run', path: '/x' },
        { type: 'open_article', label: '', path: '/office/knowledge/a' },
    ]);

    assert.deepEqual(actions.map((action) => action.type), ['open_scene', 'review_ticket_draft']);
});
