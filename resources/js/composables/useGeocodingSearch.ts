import type { Ref } from 'vue';
import { ref, watch } from 'vue';
import { geocodingSearch } from '@/actions/App/Http/Controllers/IncidentController';
import type { GeocodingResult } from '@/types/incident';

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

        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();
        isLoading.value = true;

        try {
            const url = geocodingSearch.url({
                query: { query: q },
            });

            const response = await fetch(url, {
                signal: abortController.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                results.value = await response.json();
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
