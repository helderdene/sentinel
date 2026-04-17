import type { Ref } from 'vue';
import { onUnmounted, ref, watch } from 'vue';
import { updateLocation } from '@/actions/App/Http/Controllers/ResponderController';
import type { IncidentStatus } from '@/types/responder';

const STANDBY_INTERVAL_MS = 30_000;
const EN_ROUTE_INTERVAL_MS = 10_000;
const ON_SCENE_INTERVAL_MS = 60_000;

function getBroadcastInterval(status: IncidentStatus | null): number {
    switch (status) {
        case 'ACKNOWLEDGED':
        case 'EN_ROUTE':
            return EN_ROUTE_INTERVAL_MS;
        case 'ON_SCENE':
        case 'RESOLVING':
            return ON_SCENE_INTERVAL_MS;
        default:
            return STANDBY_INTERVAL_MS;
    }
}

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

export function useGpsTracking(
    unitId: string,
    status: Ref<IncidentStatus | null>,
) {
    const position = ref<{ lat: number; lng: number } | null>(null);
    const isTracking = ref(false);

    let watchId: number | null = null;
    let lastBroadcastTime = 0;
    let broadcastTimer: ReturnType<typeof setTimeout> | null = null;

    function broadcastPosition(lat: number, lng: number): void {
        const now = Date.now();
        const interval = getBroadcastInterval(status.value);

        if (now - lastBroadcastTime < interval) {
            return;
        }

        lastBroadcastTime = now;

        fetch(updateLocation.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                latitude: lat,
                longitude: lng,
            }),
        }).catch(() => {
            // Silent fail -- GPS broadcast is best-effort
        });
    }

    function scheduleBroadcast(): void {
        if (broadcastTimer) {
            clearTimeout(broadcastTimer);
        }

        if (!position.value || !isTracking.value) {
            return;
        }

        const interval = getBroadcastInterval(status.value);

        broadcastTimer = setTimeout(() => {
            if (position.value && isTracking.value) {
                broadcastPosition(position.value.lat, position.value.lng);
                scheduleBroadcast();
            }
        }, interval);
    }

    function start(): void {
        if (isTracking.value || !navigator.geolocation) {
            return;
        }

        isTracking.value = true;

        // Get an immediate position fix
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                if (!position.value) {
                    position.value = {
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                    };
                }
            },
            () => {},
            { enableHighAccuracy: true, maximumAge: 30000, timeout: 10000 },
        );

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;

                position.value = { lat, lng };
                broadcastPosition(lat, lng);
            },
            () => {
                // Geolocation error -- continue tracking, will retry
            },
            {
                enableHighAccuracy: true,
                maximumAge: 5000,
            },
        );

        scheduleBroadcast();
    }

    function stop(): void {
        isTracking.value = false;

        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        if (broadcastTimer) {
            clearTimeout(broadcastTimer);
            broadcastTimer = null;
        }
    }

    watch(status, () => {
        if (isTracking.value) {
            scheduleBroadcast();
        }
    });

    onUnmounted(() => {
        stop();
    });

    return {
        position,
        isTracking,
        start,
        stop,
    };
}
