/**
 * Employee (office) layout state: compact room menu and chat dock.
 * `chat:open` (window) opens the dock, e.g. when a suggested topic is asked.
 */
export function userShell() {
    return {
        menuOpen: false,
        chatOpen: false,
        onChatOpen: null,

        init() {
            this.onChatOpen = () => {
                this.chatOpen = true;
            };
            window.addEventListener('chat:open', this.onChatOpen);
        },

        destroy() {
            window.removeEventListener('chat:open', this.onChatOpen);
        },

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
