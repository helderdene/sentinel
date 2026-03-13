import { ref } from 'vue';

export interface ApiValidationErrors {
    message: string;
    errors: Record<string, string[]>;
}

const baseUrl = import.meta.env.VITE_API_URL ?? '';

export function useApi() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function get<T>(path: string): Promise<T> {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch(`${baseUrl}${path}`, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                throw new Error(
                    body?.message ?? `Request failed with status ${response.status}`
                );
            }

            return (await response.json()) as T;
        } catch (err) {
            const message =
                err instanceof Error ? err.message : 'An unexpected error occurred';
            error.value = message;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function post<T>(
        path: string,
        body: Record<string, unknown>
    ): Promise<T> {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch(`${baseUrl}${path}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify(body),
            });

            if (response.status === 422) {
                const validationError =
                    (await response.json()) as ApiValidationErrors;
                error.value = validationError.message;
                throw validationError;
            }

            if (!response.ok) {
                const errorBody = await response.json().catch(() => null);
                throw new Error(
                    errorBody?.message ??
                        `Request failed with status ${response.status}`
                );
            }

            return (await response.json()) as T;
        } catch (err) {
            if (
                !(err as ApiValidationErrors).errors
            ) {
                const message =
                    err instanceof Error
                        ? err.message
                        : 'An unexpected error occurred';
                error.value = message;
            }
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return { get, post, loading, error };
}
