import { ref } from 'vue';

export interface OsrmRoute {
    coordinates: [number, number][];
    distanceKm: number;
    durationMin: number;
}

const OSRM_BASE = 'https://router.project-osrm.org/route/v1/driving';

const routeCache = new Map<string, OsrmRoute>();
const pendingRequests = new Map<string, Promise<OsrmRoute | null>>();

function cacheKey(from: [number, number], to: [number, number]): string {
    return `${from[0].toFixed(5)},${from[1].toFixed(5)};${to[0].toFixed(5)},${to[1].toFixed(5)}`;
}

function straightLineFallback(
    from: [number, number],
    to: [number, number],
): OsrmRoute {
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
    };
}

async function fetchRoute(
    from: [number, number],
    to: [number, number],
): Promise<OsrmRoute | null> {
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
            const url = `${OSRM_BASE}/${from[0]},${from[1]};${to[0]},${to[1]}?overview=full&geometries=geojson`;
            const response = await fetch(url);

            if (!response.ok) {
                return null;
            }

            const data = await response.json();

            if (data.code !== 'Ok' || !data.routes?.[0]) {
                return null;
            }

            const route = data.routes[0];
            const result: OsrmRoute = {
                coordinates: route.geometry.coordinates,
                distanceKm: route.distance / 1000,
                durationMin: Math.max(1, Math.round(route.duration / 60)),
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

export function useOsrmRoute() {
    const isLoading = ref(false);

    async function getRoute(
        from: [number, number],
        to: [number, number],
    ): Promise<OsrmRoute> {
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
    ): OsrmRoute | null {
        return routeCache.get(cacheKey(from, to)) ?? null;
    }

    return {
        getRoute,
        getRouteSync,
        isLoading,
    };
}
