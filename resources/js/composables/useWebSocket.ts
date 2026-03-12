import { useConnectionStatus } from '@laravel/echo-vue';
import { ref, watch } from 'vue';
import { stateSync } from '@/routes';
import type { StateSyncResponse } from '@/types/incident';

export type BannerLevel = 'none' | 'amber' | 'red' | 'green';

const stateSyncCallbacks: Array<(data: StateSyncResponse) => void> = [];

export function useWebSocket() {
    const status = useConnectionStatus();
    const bannerLevel = ref<BannerLevel>('none');
    const isSyncing = ref(false);
    let disconnectTimer: ReturnType<typeof setTimeout> | null = null;
    let greenDismissTimer: ReturnType<typeof setTimeout> | null = null;
    let wasDisconnected = false;

    watch(status, (newStatus) => {
        if (disconnectTimer) {
            clearTimeout(disconnectTimer);
            disconnectTimer = null;
        }

        if (greenDismissTimer) {
            clearTimeout(greenDismissTimer);
            greenDismissTimer = null;
        }

        switch (newStatus) {
            case 'connected':
                if (wasDisconnected) {
                    bannerLevel.value = 'green';
                    isSyncing.value = true;
                    syncState().finally(() => {
                        isSyncing.value = false;
                        greenDismissTimer = setTimeout(() => {
                            bannerLevel.value = 'none';
                        }, 2000);
                    });
                }

                wasDisconnected = false;
                break;

            case 'reconnecting':
            case 'connecting':
                wasDisconnected = true;
                bannerLevel.value = 'amber';
                disconnectTimer = setTimeout(() => {
                    bannerLevel.value = 'red';
                }, 30_000);
                break;

            case 'disconnected':
            case 'failed':
                wasDisconnected = true;
                bannerLevel.value = 'red';
                break;
        }
    });

    function onStateSync(callback: (data: StateSyncResponse) => void): void {
        stateSyncCallbacks.push(callback);
    }

    async function syncState(): Promise<void> {
        try {
            const response = await fetch(stateSync.url());
            const data: StateSyncResponse = await response.json();
            stateSyncCallbacks.forEach((cb) => cb(data));
        } catch {
            // Silent fail -- data will refresh on next event
        }
    }

    return { status, bannerLevel, isSyncing, onStateSync };
}

let audioContext: AudioContext | null = null;
let audioUnlocked = false;

function initAudio(): void {
    if (audioContext) {
        return;
    }

    audioContext = new AudioContext();

    const unlock = () => {
        if (audioContext && !audioUnlocked) {
            audioContext.resume();
            audioUnlocked = true;
        }
    };

    document.addEventListener('click', unlock, { once: true });
    document.addEventListener('keydown', unlock, { once: true });
}

export function playAlertSound(): void {
    initAudio();

    if (!audioContext || audioContext.state !== 'running') {
        return;
    }

    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();
    oscillator.connect(gain);
    gain.connect(audioContext.destination);
    oscillator.frequency.value = 880;
    gain.gain.value = 0.3;
    oscillator.start();
    gain.gain.exponentialRampToValueAtTime(
        0.01,
        audioContext.currentTime + 0.3,
    );
    oscillator.stop(audioContext.currentTime + 0.3);
}
