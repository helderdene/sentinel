import type { IncidentPriority } from '@/types/dispatch';

interface PriorityTone {
    frequencies: number[];
    durations: number[];
}

const PRIORITY_TONES: Record<IncidentPriority, PriorityTone> = {
    P1: {
        frequencies: [880, 660, 880, 660, 880, 660],
        durations: [0.25, 0.25, 0.25, 0.25, 0.25, 0.25],
    },
    P2: {
        frequencies: [700, 700],
        durations: [0.3, 0.3],
    },
    P3: {
        frequencies: [550],
        durations: [0.3],
    },
    P4: {
        frequencies: [440],
        durations: [0.2],
    },
};

let audioContext: AudioContext | null = null;
let audioUnlocked = false;

function ensureAudioContext(): AudioContext | null {
    if (!audioContext) {
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

    return audioContext;
}

export function useAlertSystem() {
    function playPriorityTone(priority: IncidentPriority): void {
        const ctx = ensureAudioContext();

        if (!ctx || ctx.state !== 'running') {
            return;
        }

        const tone = PRIORITY_TONES[priority];

        if (!tone) {
            return;
        }

        let offset = 0;

        for (let i = 0; i < tone.frequencies.length; i++) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = tone.frequencies[i];
            gain.gain.value = 0.3;
            osc.start(ctx.currentTime + offset);
            gain.gain.exponentialRampToValueAtTime(
                0.01,
                ctx.currentTime + offset + tone.durations[i],
            );
            osc.stop(ctx.currentTime + offset + tone.durations[i]);
            offset += tone.durations[i];
        }
    }

    function playAckExpiredTone(): void {
        const ctx = ensureAudioContext();

        if (!ctx || ctx.state !== 'running') {
            return;
        }

        for (let i = 0; i < 2; i++) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 600;
            gain.gain.value = 0.3;
            osc.start(ctx.currentTime + i * 0.2);
            gain.gain.exponentialRampToValueAtTime(
                0.01,
                ctx.currentTime + i * 0.2 + 0.15,
            );
            osc.stop(ctx.currentTime + i * 0.2 + 0.15);
        }
    }

    function triggerP1Flash(): void {
        document.body.classList.add('p1-flash-active');

        const handler = () => {
            document.body.classList.remove('p1-flash-active');
            document.body.removeEventListener('animationend', handler);
        };

        document.body.addEventListener('animationend', handler);
    }

    function playMessageTone(): void {
        const ctx = ensureAudioContext();

        if (!ctx || ctx.state !== 'running') {
            return;
        }

        const notes = [523, 659];
        const duration = 0.1;

        for (let i = 0; i < notes.length; i++) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.value = notes[i];
            gain.gain.value = 0.12;
            osc.start(ctx.currentTime + i * 0.12);
            gain.gain.exponentialRampToValueAtTime(
                0.01,
                ctx.currentTime + i * 0.12 + duration,
            );
            osc.stop(ctx.currentTime + i * 0.12 + duration);
        }
    }

    return {
        playPriorityTone,
        playAckExpiredTone,
        triggerP1Flash,
        playMessageTone,
    };
}
