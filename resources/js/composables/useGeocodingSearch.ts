import mapboxgl from 'mapbox-gl';
import type { Ref } from 'vue';
import { ref, watch } from 'vue';
import type { GeocodingResult } from '@/types/incident';

// Bias results toward Butuan City, Agusan del Norte so local searches
// ("Robinsons", "JC Aquino Ave") resolve to the correct Mindanao city
// instead of a higher-population match elsewhere in the Philippines.
const PROXIMITY_LNG = 125.5406;
const PROXIMITY_LAT = 8.9475;
const COUNTRY = 'ph';

function useDebounce(fn: () => void, delay: number): () => void {
    let timeout: ReturnType<typeof setTimeout> | null = null;

    return () => {
        if (timeout) {
            clearTimeout(timeout);
        }

        timeout = setTimeout(() => {
            fn();
            timeout = null;
        }, delay);
    };
}

type MapboxFeature = {
    center: [number, number];
    place_name: string;
};

type MapboxForwardResponse = {
    features?: MapboxFeature[];
};

export function useGeocodingSearch(query: Ref<string>) {
    const results = ref<GeocodingResult[]>([]);
    const isLoading = ref(false);
    let abortController: AbortController | null = null;

    async function fetchResults(): Promise<void> {
        const q = query.value.trim();

        if (q.length < 3) {
            results.value = [];

            return;
        }

        const token = mapboxgl.accessToken;

        if (!token) {
            results.value = [];

            return;
        }

        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();
        isLoading.value = true;

        try {
            const params = new URLSearchParams({
                access_token: token,
                country: COUNTRY,
                proximity: `${PROXIMITY_LNG},${PROXIMITY_LAT}`,
                limit: '5',
                autocomplete: 'true',
            });
            const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(q)}.json?${params.toString()}`;

            const response = await fetch(url, {
                signal: abortController.signal,
            });

            if (response.ok) {
                const data = (await response.json()) as MapboxForwardResponse;

                results.value = (data.features ?? []).map((feature) => ({
                    lat: feature.center[1],
                    lng: feature.center[0],
                    display_name: feature.place_name,
                }));
            }
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }
        } finally {
            isLoading.value = false;
        }
    }

    const debouncedFetch = useDebounce(fetchResults, 300);

    watch(query, () => {
        debouncedFetch();
    });

    return {
        results,
        isLoading,
    };
}
