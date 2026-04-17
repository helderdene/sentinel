import { onMounted, ref } from 'vue';

import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/PushSubscriptionController';

const VAPID_PUBLIC_KEY = import.meta.env.VITE_VAPID_PUBLIC_KEY as string;

function getXsrfToken(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

export function usePushSubscription() {
    const isSubscribed = ref(false);
    const isSupported = ref(false);

    onMounted(() => {
        isSupported.value =
            'serviceWorker' in navigator && 'PushManager' in window;

        if (isSupported.value) {
            checkExistingSubscription();
        }
    });

    async function subscribe(): Promise<void> {
        if (!isSupported.value) {
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const applicationServerKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey.buffer as ArrayBuffer,
        });

        const key = subscription.getKey('p256dh');
        const auth = subscription.getKey('auth');

        await fetch(store.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                endpoint: subscription.endpoint,
                public_key: key
                    ? btoa(String.fromCharCode(...new Uint8Array(key)))
                    : null,
                auth_token: auth
                    ? btoa(String.fromCharCode(...new Uint8Array(auth)))
                    : null,
                content_encoding: 'aesgcm',
            }),
        });

        isSubscribed.value = true;
        localStorage.setItem('push-subscribed', 'true');
    }

    async function unsubscribe(): Promise<void> {
        if (!isSupported.value) {
            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            return;
        }

        await subscription.unsubscribe();

        await fetch(destroy.url(), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({
                endpoint: subscription.endpoint,
            }),
        });

        isSubscribed.value = false;
        localStorage.removeItem('push-subscribed');
    }

    async function checkExistingSubscription(): Promise<void> {
        if (!isSupported.value) {
            return;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription =
                await registration.pushManager.getSubscription();

            if (subscription) {
                isSubscribed.value = true;
            }
        } catch {
            // Service worker not ready yet, ignore
        }
    }

    return {
        isSubscribed,
        isSupported,
        subscribe,
        unsubscribe,
        checkExistingSubscription,
    };
}
