import { ref } from 'vue';

const STORAGE_KEY = 'responder.voice.enabled';

function readStoredEnabled(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    const raw = window.localStorage.getItem(STORAGE_KEY);

    return raw === null ? true : raw === 'true';
}

const isSupported = ref<boolean>(
    typeof window !== 'undefined' && 'speechSynthesis' in window,
);

const isEnabled = ref<boolean>(readStoredEnabled());

const lastSpokenText = ref<string | null>(null);
const lastSpokenAt = ref<number>(0);
let isPrimed = false;

/**
 * Browsers (notably Safari / Chrome) require speechSynthesis to be invoked
 * from within a user gesture at least once per page. A status-advance button
 * click may trigger speech from an async callback where the gesture context
 * is already lost — so we prime the engine with a silent utterance on the
 * very first pointer/key event.
 */
function attachPrimer(): void {
    if (typeof window === 'undefined' || isPrimed) {
        return;
    }

    if (!('speechSynthesis' in window)) {
        return;
    }

    const prime = (): void => {
        if (isPrimed) {
            return;
        }

        try {
            const silent = new SpeechSynthesisUtterance('');
            silent.volume = 0;
            window.speechSynthesis.speak(silent);
            isPrimed = true;
        } catch {
            // ignore
        }

        window.removeEventListener('pointerdown', prime, true);
        window.removeEventListener('keydown', prime, true);
        window.removeEventListener('touchstart', prime, true);
    };

    window.addEventListener('pointerdown', prime, true);
    window.addEventListener('keydown', prime, true);
    window.addEventListener('touchstart', prime, true);
}

attachPrimer();

/**
 * Wrapper around window.speechSynthesis that:
 * - debounces identical announcements within 4 seconds
 * - cancels any pending utterance when a new one arrives
 * - persists enabled state to localStorage
 */
export function useSpeech() {
    function speak(text: string): void {
        if (!isSupported.value || !isEnabled.value || !text) {
            return;
        }

        const now = Date.now();
        const isRepeat =
            lastSpokenText.value === text && now - lastSpokenAt.value < 4000;

        if (isRepeat) {
            return;
        }

        try {
            window.speechSynthesis.cancel();

            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = 0.65;
            utterance.pitch = 1;
            utterance.volume = 1;
            utterance.lang = 'en-US';

            window.speechSynthesis.speak(utterance);

            lastSpokenText.value = text;
            lastSpokenAt.value = now;
        } catch {
            // Speech synthesis unavailable — fail silently
        }
    }

    function cancel(): void {
        if (!isSupported.value) {
            return;
        }

        try {
            window.speechSynthesis.cancel();
        } catch {
            // no-op
        }

        lastSpokenText.value = null;
    }

    function setEnabled(value: boolean): void {
        isEnabled.value = value;

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, String(value));
        }

        if (!value) {
            cancel();
        }
    }

    function toggle(): void {
        setEnabled(!isEnabled.value);
    }

    return {
        isSupported,
        isEnabled,
        speak,
        cancel,
        setEnabled,
        toggle,
    };
}
