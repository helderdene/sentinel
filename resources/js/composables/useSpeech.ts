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
let voicesReadyPromise: Promise<void> | null = null;

/**
 * Resolve once Chrome's voice list is populated. The first call to
 * `speechSynthesis.getVoices()` returns `[]` on Chrome until the engine
 * fires `voiceschanged`; an utterance issued before that can be silently
 * dropped. Caps the wait at 1.5s so a missing event never hangs speak().
 */
function ensureVoicesReady(): Promise<void> {
    if (!isSupported.value) {
        return Promise.resolve();
    }

    if (voicesReadyPromise) {
        return voicesReadyPromise;
    }

    voicesReadyPromise = new Promise<void>((resolve) => {
        const synth = window.speechSynthesis;

        if (synth.getVoices().length > 0) {
            resolve();

            return;
        }

        const onChange = (): void => {
            synth.removeEventListener('voiceschanged', onChange);
            resolve();
        };

        synth.addEventListener('voiceschanged', onChange);

        setTimeout(() => {
            synth.removeEventListener('voiceschanged', onChange);
            resolve();
        }, 1500);
    });

    return voicesReadyPromise;
}

/**
 * Browsers (notably Safari / Chrome) require speechSynthesis to be invoked
 * from within a user gesture at least once per page. A status-advance click
 * may trigger speech from an async callback where the gesture context is
 * already lost — so we prime the engine with a silent utterance on the
 * first pointer/key event. Idempotent: re-calling after a successful prime
 * is a no-op; if the listeners were never triggered (page loaded inactive)
 * the gesture handler is re-attached on every useSpeech() mount.
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
 * - waits for Chrome's voice list before the first speak (avoids drop)
 * - logs to console.warn when an utterance is suppressed or silently
 *   dropped by the browser autoplay policy, so prod issues surface in
 *   DevTools without server-side instrumentation
 */
export function useSpeech() {
    // Re-attach the primer on every mount in case the module-load attempt
    // missed all user gestures (page loaded in a background tab, or the
    // composable was code-split and only loaded mid-session).
    attachPrimer();

    function speak(text: string): void {
        if (!isSupported.value) {
            console.warn(
                '[useSpeech] speak() suppressed: speechSynthesis not supported in this browser',
            );

            return;
        }

        if (!isEnabled.value || !text) {
            return;
        }

        const now = Date.now();
        const isRepeat =
            lastSpokenText.value === text && now - lastSpokenAt.value < 4000;

        if (isRepeat) {
            return;
        }

        lastSpokenText.value = text;
        lastSpokenAt.value = now;

        if (!isPrimed) {
            console.warn(
                '[useSpeech] speak() may be blocked: no user gesture has primed speechSynthesis on this page yet. Tap the screen or the voice toggle once.',
                { text: text.substring(0, 60) },
            );
        }

        ensureVoicesReady().then(() => {
            try {
                window.speechSynthesis.cancel();

                const utterance = new SpeechSynthesisUtterance(text);
                utterance.rate = 0.65;
                utterance.pitch = 1;
                utterance.volume = 1;
                utterance.lang = 'en-US';

                let started = false;
                utterance.onstart = () => {
                    started = true;
                };
                utterance.onerror = (event) => {
                    console.warn(
                        `[useSpeech] utterance.onerror: ${event.error}`,
                        { text: text.substring(0, 60) },
                    );
                };

                window.speechSynthesis.speak(utterance);

                // Detect silent drops: if onstart never fires within 800ms,
                // the browser refused the utterance without raising an
                // error event (common iOS Safari autoplay-policy behaviour).
                setTimeout(() => {
                    if (!started) {
                        console.warn(
                            '[useSpeech] utterance never started — likely blocked by browser autoplay policy. Tap the voice toggle or any UI control to prime.',
                            { text: text.substring(0, 60) },
                        );
                    }
                }, 800);
            } catch (err) {
                console.warn('[useSpeech] speak() threw', err);
            }
        });
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
