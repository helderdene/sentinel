import { ref } from 'vue';

export type GeolocationStatus = 'idle' | 'requesting' | 'granted' | 'denied';

export function useGeolocation() {
    const latitude = ref<number | null>(null);
    const longitude = ref<number | null>(null);
    const status = ref<GeolocationStatus>('idle');
    const error = ref<string | null>(null);

    async function requestLocation(): Promise<boolean> {
        if (!('geolocation' in navigator)) {
            status.value = 'denied';
            error.value = 'Geolocation is not available on this device';
            return false;
        }

        status.value = 'requesting';
        error.value = null;

        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    latitude.value = position.coords.latitude;
                    longitude.value = position.coords.longitude;
                    status.value = 'granted';
                    resolve(true);
                },
                (err) => {
                    status.value = 'denied';
                    error.value = err.message;
                    resolve(false);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000,
                }
            );
        });
    }

    return { latitude, longitude, status, error, requestLocation };
}
