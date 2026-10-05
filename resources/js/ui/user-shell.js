/**
 * Employee (office) layout state: compact room menu and chat dock.
 * Plan 3 adds scene behaviour in its own module; this stays UI-only.
 */
export function userShell() {
    return {
        menuOpen: false,
        chatOpen: false,

        toggleMenu() {
            this.menuOpen = !this.menuOpen;
        },

        closeMenu() {
            this.menuOpen = false;
        },

        toggleChat() {
            this.chatOpen = !this.chatOpen;
        },

        closeChat() {
            this.chatOpen = false;
        },
    };
}
