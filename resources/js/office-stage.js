import { createNarrator } from './narrator.js';
import { createSound } from './sound.js';
import { sceneLinkFromClick } from './shared/navigation.js';

const EXIT_MS = 200;
const LEAVING_CLASSES = ['opacity-0', 'translate-y-1'];

function storageKey(userId, name) {
    return `fts-office:${userId ?? 'guest'}:${name}`;
}

function readPreference(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writePreference(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Private mode or blocked storage: the preference just isn't remembered.
    }
}

/**
 * Office scene behaviour: narrator and sound toggles, plus an eased exit
 * before moving to another office scene. Links keep working without JS.
 * Only UI preferences (sound) are stored, namespaced per user; never
 * messages, tickets, or personal data.
 */
export function officeStage(config = {}) {
    return {
        narratorSupported: false,
        speaking: false,
        soundOn: false,
        narrator: null,
        sound: null,
        onClick: null,
        onPageShow: null,

        init() {
            this.narrator = createNarrator();
            this.sound = createSound();
            this.narratorSupported = this.narrator.supported;
            this.soundOn = this.sound.supported && readPreference(storageKey(config.userId, 'sound')) === 'on';

            this.onClick = (event) => this.handleClick(event);
            this.onPageShow = (event) => {
                if (event.persisted) {
                    this.resetLeaving();
                }
            };
            document.addEventListener('click', this.onClick);
            window.addEventListener('pageshow', this.onPageShow);
        },

        destroy() {
            document.removeEventListener('click', this.onClick);
            window.removeEventListener('pageshow', this.onPageShow);
            this.narrator?.stop();
            this.sound?.close();
        },

        toggleNarrator() {
            if (this.speaking) {
                this.narrator.stop();
                this.speaking = false;

                return;
            }
            this.speaking = this.narrator.speak(config.intro ?? '', config.lang ?? 'id-ID', {
                onEnd: () => {
                    this.speaking = false;
                },
            });
        },

        toggleSound() {
            this.soundOn = !this.soundOn;
            writePreference(storageKey(config.userId, 'sound'), this.soundOn ? 'on' : 'off');
            if (this.soundOn) {
                this.sound.unlock();
                this.sound.play('toggle');
            }
        },

        handleClick(event) {
            const url = sceneLinkFromClick(event);
            if (!url) {
                return;
            }
            event.preventDefault();
            this.narrator.stop();

            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (this.soundOn) {
                this.sound.play('enter');
            }
            if (!reducedMotion) {
                document.getElementById('main-content')?.classList.add(...LEAVING_CLASSES);
            }
            window.setTimeout(() => window.location.assign(url.href), reducedMotion ? 0 : EXIT_MS);
        },

        resetLeaving() {
            document.getElementById('main-content')?.classList.remove(...LEAVING_CLASSES);
        },
    };
}

/**
 * Decorative image with a fallback: flags failure even when the error
 * happened before Alpine initialised.
 */
export function sceneImage() {
    return {
        failed: false,

        init() {
            const image = this.$refs.image;
            if (image && image.complete && image.naturalWidth === 0) {
                this.failed = true;
            }
        },
    };
}
