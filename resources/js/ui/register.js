import { adminShell } from './admin-shell.js';
import { userShell } from './user-shell.js';
import { modal } from './modal.js';
import { dropdown } from './dropdown.js';
import { tabs } from './tabs.js';
import { createToastStore, listenForToasts } from './toasts.js';
import { officeStage, sceneImage } from '../office-stage.js';

/**
 * Registers shared Alpine data providers and the `ui` store.
 * Called once from app.js before Alpine.start().
 */
export function registerUi(Alpine) {
    Alpine.store('ui', {
        toasts: createToastStore(),
    });

    Alpine.data('adminShell', adminShell);
    Alpine.data('userShell', userShell);
    Alpine.data('modal', modal);
    Alpine.data('dropdown', dropdown);
    Alpine.data('tabs', tabs);
    Alpine.data('officeStage', officeStage);
    Alpine.data('sceneImage', sceneImage);

    listenForToasts(() => Alpine.store('ui').toasts);
}
