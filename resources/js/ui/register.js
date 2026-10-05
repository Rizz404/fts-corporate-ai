import { adminShell } from './admin-shell.js';
import { userShell } from './user-shell.js';
import { modal } from './modal.js';
import { dropdown } from './dropdown.js';
import { tabs } from './tabs.js';
import { createToastStore, listenForToasts } from './toasts.js';
import { officeStage, sceneImage } from '../office-stage.js';
import { corporateChat, clearChatStorage } from '../chat.js';
import { ticketWizard } from '../ticket-wizard.js';
import { ticketThread } from '../ticket-thread.js';
import { notificationBell } from '../notifications.js';

/**
 * Registers shared Alpine data providers and the `ui` store.
 * Called once from app.js before Alpine.start().
 */
export function registerUi(Alpine) {
    Alpine.store('ui', {
        toasts: createToastStore(),
        clearChatStorage,
    });

    Alpine.data('adminShell', adminShell);
    Alpine.data('userShell', userShell);
    Alpine.data('modal', modal);
    Alpine.data('dropdown', dropdown);
    Alpine.data('tabs', tabs);
    Alpine.data('officeStage', officeStage);
    Alpine.data('sceneImage', sceneImage);
    Alpine.data('corporateChat', corporateChat);
    Alpine.data('ticketWizard', ticketWizard);
    Alpine.data('ticketThread', ticketThread);
    Alpine.data('notificationBell', notificationBell);

    listenForToasts(() => Alpine.store('ui').toasts);
}
