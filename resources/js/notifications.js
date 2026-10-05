import { apiRequest } from './shared/api.js';

const REFRESH_MS = 60000;

/**
 * In-app notification bell. Items carry a server-computed path the user may
 * open; access is checked again when the ticket page loads.
 */
export function notificationBell() {
    return {
        items: [],
        unread: 0,
        timer: null,
        onVisibility: null,

        init() {
            this.onVisibility = () => {
                if (document.visibilityState === 'visible') this.refresh();
            };
            document.addEventListener('visibilitychange', this.onVisibility);
            this.refresh();
        },

        destroy() {
            window.clearTimeout(this.timer);
            document.removeEventListener('visibilitychange', this.onVisibility);
        },

        async refresh() {
            window.clearTimeout(this.timer);
            try {
                const envelope = await apiRequest('/api/v1/notifications');
                this.items = Array.isArray(envelope.data) ? envelope.data : [];
                this.unread = Number(envelope.meta?.unread_count ?? 0);
            } catch {
                // Keep the previous state; try again later.
            }
            if (document.visibilityState === 'visible') {
                this.timer = window.setTimeout(() => this.refresh(), REFRESH_MS);
            }
        },

        async open(item) {
            if (!item.read) {
                try {
                    const envelope = await apiRequest(`/api/v1/notifications/${item.id}/read`, { method: 'PATCH' });
                    this.unread = Number(envelope.meta?.unread_count ?? this.unread);
                } catch {
                    // Navigation still works if marking fails.
                }
            }
            if (typeof item.path === 'string' && item.path.startsWith('/') && !item.path.startsWith('//')) {
                window.location.assign(item.path);
            }
        },
    };
}
