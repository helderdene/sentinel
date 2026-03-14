declare module 'virtual:pwa-register/vue' {
    import type { Ref } from 'vue';
    export function useRegisterSW(options?: {
        immediate?: boolean;
        onRegistered?: (
            registration: ServiceWorkerRegistration | undefined,
        ) => void;
        onRegisterError?: (error: Error) => void;
        onNeedRefresh?: () => void;
        onOfflineReady?: () => void;
    }): {
        needRefresh: Ref<boolean>;
        offlineReady: Ref<boolean>;
        updateServiceWorker: (reloadPage?: boolean) => Promise<void>;
    };
}
