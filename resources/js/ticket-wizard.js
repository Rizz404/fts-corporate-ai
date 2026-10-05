import { apiRequest } from './shared/api.js';
import { uuid } from './shared/uuid.js';

/**
 * Three-step ticket request: team/category → details → review/confirm.
 * The draft lives on the server (revisioned, 15-minute expiry). The browser
 * keeps only form state in memory, no description or personal data in
 * storage. The confirm key is stable per draft revision, so a retry after a
 * lost response returns the same ticket.
 *
 * config: { initialTeam, categories: {team: {code: label}}, labels }
 */
export function ticketWizard(config = {}) {
    const labels = config.labels ?? {};

    return {
        step: config.initialTeam ? 1 : 1,
        team: config.initialTeam ?? '',
        category: '',
        subject: '',
        description: '',
        draft: null,
        confirmKey: null,
        errors: {},
        error: '',
        pending: false,
        canRetry: false,

        get categories() {
            return Object.entries(config.categories?.[this.team] ?? {}).map(([value, label]) => ({ value, label }));
        },

        chooseTeam(team) {
            this.team = team;
            this.category = '';
            this.errors = {};
        },

        next() {
            this.errors = {};
            if (this.step === 1) {
                if (!this.team) this.errors.team_code = [labels.required];
                if (!this.category) this.errors.category = [labels.required];
                if (Object.keys(this.errors).length === 0) this.step = 2;

                return;
            }
            if (this.step === 2) {
                this.saveDraft();
            }
        },

        back() {
            this.error = '';
            this.step = Math.max(1, this.step - 1);
        },

        payload() {
            return {
                team_code: this.team,
                category: this.category,
                subject: this.subject.trim(),
                description: this.description.trim(),
            };
        },

        unchanged() {
            const d = this.draft;
            const p = this.payload();

            return d && !d.expired && d.team_code === p.team_code && d.category === p.category && d.subject === p.subject && d.description === p.description;
        },

        async saveDraft() {
            if (this.pending) return;
            if (this.unchanged()) {
                this.step = 3;

                return;
            }
            this.pending = true;
            this.error = '';
            try {
                const envelope = this.draft && !this.draft.expired
                    ? await apiRequest(`/api/v1/ticket-drafts/${this.draft.draft_id}`, {
                        method: 'PATCH',
                        body: { ...this.payload(), expected_revision: this.draft.revision },
                    })
                    : await apiRequest('/api/v1/ticket-drafts', { method: 'POST', body: this.payload() });
                this.draft = envelope.data;
                this.confirmKey = uuid();
                this.step = 3;
            } catch (error) {
                if (error.code === 'draft_expired' || error.code === 'draft_consumed') {
                    this.draft = null;
                    this.pending = false;

                    return this.saveDraft();
                }
                this.showError(error);
            } finally {
                this.pending = false;
            }
        },

        async confirm() {
            if (this.pending || !this.draft) return;
            this.pending = true;
            this.error = '';
            this.canRetry = false;
            try {
                const envelope = await apiRequest(`/api/v1/ticket-drafts/${this.draft.draft_id}/confirm`, {
                    method: 'POST',
                    body: { expected_revision: this.draft.revision, idempotency_key: this.confirmKey },
                });
                window.location.assign(envelope.data.path);
            } catch (error) {
                if (error.code === 'draft_expired') {
                    this.draft = { ...this.draft, expired: true };
                    this.error = labels.expired;
                    this.step = 2;
                } else if (error.code === 'revision_conflict') {
                    await this.reloadDraft();
                    this.error = labels.changed;
                } else {
                    this.showError(error);
                    this.canRetry = error.isNetwork || error.isAbort || error.status >= 500;
                }
                this.pending = false;
            }
        },

        async reloadDraft() {
            try {
                const envelope = await apiRequest(`/api/v1/ticket-drafts/${this.draft.draft_id}`);
                this.draft = envelope.data;
                this.confirmKey = uuid();
            } catch {
                this.draft = null;
                this.step = 2;
            }
        },

        showError(error) {
            if (error.status === 422 && error.fields) {
                this.errors = error.fields;
                this.step = this.errors.team_code || this.errors.category ? 1 : 2;
                this.error = labels.invalid;
            } else if (error.status === 429) {
                this.error = labels.rateLimited;
            } else if (error.status === 419 || error.status === 401) {
                this.error = labels.session;
            } else if (error.isNetwork || error.isAbort) {
                this.error = labels.network;
            } else {
                this.error = error.message || labels.generic;
            }
        },

        fieldError(name) {
            return this.errors?.[name]?.[0] ?? '';
        },
    };
}
