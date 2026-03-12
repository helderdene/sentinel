import type { Ref } from 'vue';
import { ref, watch } from 'vue';
import { suggestPriority } from '@/actions/App/Http/Controllers/IncidentController';
import type { PrioritySuggestion } from '@/types/incident';

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

export function usePrioritySuggestion(
    incidentTypeId: Ref<number | null>,
    notes: Ref<string>,
) {
    const suggestion = ref<PrioritySuggestion | null>(null);
    const isLoading = ref(false);
    let abortController: AbortController | null = null;

    async function fetchSuggestion(): Promise<void> {
        const typeId = incidentTypeId.value;

        if (!typeId) {
            suggestion.value = null;

            return;
        }

        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();
        isLoading.value = true;

        try {
            const url = suggestPriority.url({
                query: {
                    incident_type_id: String(typeId),
                    notes: notes.value || '',
                },
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
                suggestion.value = await response.json();
            }
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }
        } finally {
            isLoading.value = false;
        }
    }

    const debouncedFetch = useDebounce(fetchSuggestion, 500);

    watch(incidentTypeId, () => {
        fetchSuggestion();
    });

    watch(notes, () => {
        if (incidentTypeId.value) {
            debouncedFetch();
        }
    });

    return {
        suggestion,
        isLoading,
    };
}
