import { ref } from 'vue';

export interface DirectionsStep {
    instruction: string;
    type: string;
    modifier: string | null;
    distance_meters: number;
    location: [number, number];
}

export interface DirectionsRoute {
    coordinates: [number, number][];
    distanceKm: number;
    durationMin: number;
    steps: DirectionsStep[];
}

const routeCache = new Map<string, DirectionsRoute>();
const pendingRequests = new Map<string, Promise<DirectionsRoute | null>>();

function cacheKey(from: [number, number], to: [number, number]): string {
    return `${from[0].toFixed(5)},${from[1].toFixed(5)};${to[0].toFixed(5)},${to[1].toFixed(5)}`;
}

function straightLineFallback(
    from: [number, number],
    to: [number, number],
): DirectionsRoute {
    const R = 6371;
    const dLat = ((to[1] - from[1]) * Math.PI) / 180;
    const dLng = ((to[0] - from[0]) * Math.PI) / 180;
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos((from[1] * Math.PI) / 180) *
            Math.cos((to[1] * Math.PI) / 180) *
            Math.sin(dLng / 2) ** 2;
    const distanceKm = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return {
        coordinates: [from, to],
        distanceKm,
        durationMin: Math.max(1, Math.round((distanceKm / 30) * 60)),
        steps: [],
    };
}

async function fetchRoute(
    from: [number, number],
    to: [number, number],
): Promise<DirectionsRoute | null> {
    const key = cacheKey(from, to);
    const cached = routeCache.get(key);

    if (cached) {
        return cached;
    }

    const pending = pendingRequests.get(key);

    if (pending) {
        return pending;
    }

    const request = (async () => {
        try {
            const params = new URLSearchParams({
                from_lat: String(from[1]),
                from_lng: String(from[0]),
                to_lat: String(to[1]),
                to_lng: String(to[0]),
            });
            const response = await fetch(`/api/directions?${params}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return null;
            }

            const data = await response.json();

            if (
                !Array.isArray(data.coordinates) ||
                data.coordinates.length === 0
            ) {
                return null;
            }

            const result: DirectionsRoute = {
                coordinates: data.coordinates,
                distanceKm: Number(data.distance_km ?? 0),
                durationMin: Math.max(1, Number(data.duration_min ?? 1)),
                steps: Array.isArray(data.steps) ? data.steps : [],
            };

            routeCache.set(key, result);

            return result;
        } catch {
            return null;
        } finally {
            pendingRequests.delete(key);
        }
    })();

    pendingRequests.set(key, request);

    return request;
}

export function useDirections() {
    const isLoading = ref(false);

    async function getRoute(
        from: [number, number],
        to: [number, number],
    ): Promise<DirectionsRoute> {
        isLoading.value = true;

        try {
            const route = await fetchRoute(from, to);

            return route ?? straightLineFallback(from, to);
        } finally {
            isLoading.value = false;
        }
    }

    function getRouteSync(
        from: [number, number],
        to: [number, number],
    ): DirectionsRoute | null {
        return routeCache.get(cacheKey(from, to)) ?? null;
    }

    return {
        getRoute,
        getRouteSync,
        isLoading,
    };
}
