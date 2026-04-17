# Phase 13: PWA Setup - Research

**Researched:** 2026-03-15
**Domain:** Progressive Web App (service worker, manifest, Web Push notifications)
**Confidence:** HIGH

## Summary

Phase 13 transforms the main IRMS application into an installable Progressive Web App with service worker caching, a web app manifest, offline app shell support, and Web Push notifications for critical events. The stack centers on `vite-plugin-pwa` v1.2.0 (which supports Vite 7) for manifest/service worker generation, and `laravel-notification-channels/webpush` v10.5.0 for server-side VAPID push notification delivery via Laravel's Notification system.

The primary complexity lies in vite-plugin-pwa's integration with Laravel's build output structure. Laravel builds assets to `public/build/` while the service worker must operate at scope `/`. This requires explicit `buildBase`, `scope`, and `base` configuration in vite.config.ts, plus a `Service-Worker-Allowed: /` response header from the web server. The `injectManifest` strategy is required (not `generateSW`) because the service worker must handle `push` and `notificationclick` events for Web Push.

The existing codebase provides strong integration points: the User model already has the `Notifiable` trait, `AssignmentPushed` and `IncidentCreated` events already broadcast to the correct channels, `ConnectionBanner.vue` + `useWebSocket` composable handle online/offline state, and the CDRRMO shield SVG in `AppLogo.vue` can be exported as PWA icons.

**Primary recommendation:** Use `vite-plugin-pwa` with `injectManifest` strategy and a custom TypeScript service worker (`resources/js/sw.ts`) that handles precaching, push events, and notification clicks. Use `laravel-notification-channels/webpush` for server-side push delivery hooked into existing broadcast events via Laravel Notification classes.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Main IRMS app only (dispatch, intake, responder, admin) -- single PWA
- Citizen report-app stays a regular web page (separate phase if needed)
- Display mode: standalone (no browser chrome)
- Start URL: `/` with role-based redirect (existing LoginResponse handles routing)
- Tooling: vite-plugin-pwa for manifest and service worker generation from vite.config.ts
- App shell caching -- cache JS/CSS/HTML so the app loads instantly without network
- No offline-first data caching (no IndexedDB, no sync queue)
- API requests: network-first strategy -- try network, fall back to cache only for static assets
- Offline UX: persistent top banner ("You are offline") using existing ConnectionBanner component pattern
- App updates: "New version available -- Reload" banner when service worker detects update; user chooses when to reload
- Push notifications included in this phase (not deferred)
- Backend: VAPID key pair, push subscription storage, Laravel notification channel
- Events that trigger push: (1) New assignment pushed to responder, (2) P1 incident created (all dispatchers notified), (3) Ack timeout warning for unacknowledged assignments
- New dispatch messages: NOT included in push (too noisy)
- Notification preferences: fixed per role, no user settings page
- Responders receive: assignment + ack timeout
- Dispatchers receive: P1 alerts
- Permission prompt: custom in-app prompt after first successful login explaining value; if dismissed, don't re-ask until settings
- App name: short "IRMS", full "IRMS - Incident Response Management System"
- Theme color: dark navy from design system (--t-bg / #0B1120)
- App icon: existing CDRRMO shield SVG from AppLogo, exported as 192x192 and 512x512 PNGs + maskable variant
- Splash screen: dark only

### Claude's Discretion
- Exact vite-plugin-pwa configuration options (workbox strategies, glob patterns)
- Service worker registration timing
- Push notification payload structure
- Icon generation tooling (sharp, pwa-asset-generator, etc.)
- Maskable icon safe zone handling

### Deferred Ideas (OUT OF SCOPE)
- Citizen report-app PWA -- separate phase if needed
- Offline-first responder with IndexedDB sync queue -- future phase
- User-configurable notification preferences -- future enhancement
- Background sync for queued status updates -- future phase
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| MOBILE-01 | PWA Service Worker with offline caching for responder app | vite-plugin-pwa injectManifest strategy with Workbox precaching for app shell; scope covers all IRMS roles not just responder |
| MOBILE-02 | Web Push notifications (VAPID) for background assignment alerts | laravel-notification-channels/webpush package with VAPID keys, push_subscriptions table, custom Notification classes, service worker push event handler |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| vite-plugin-pwa | ^1.2.0 | Manifest generation, service worker build/inject, registration | Official Vite PWA plugin, zero-config defaults, Workbox integration, Vite 7 support since v1.0.1 |
| laravel-notification-channels/webpush | ^10.5.0 | Server-side Web Push via VAPID, subscription storage, Laravel Notification channel | Standard Laravel notification channel, handles VAPID auth, expired endpoint cleanup, polymorphic subscription storage |
| workbox-precaching | (bundled via vite-plugin-pwa) | Precache manifest injection, cache management | Google's standard service worker toolkit, auto-injected by vite-plugin-pwa |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| sharp | ^0.33 | PNG icon generation from SVG source | One-time dev script to export 192x192, 512x512, and maskable PNG icons from CDRRMO shield SVG |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| vite-plugin-pwa | Manual service worker + manifest | Would need to hand-roll Workbox config, manifest file, registration script -- vite-plugin-pwa automates all three |
| laravel-notification-channels/webpush | minishlink/web-push directly | Lower-level PHP library; webpush channel wraps it with Laravel Notification integration, subscription model, trait |
| sharp | @vite-pwa/assets-generator | assets-generator is more automated but adds complexity; sharp is simpler for a known SVG-to-PNG task |
| injectManifest | generateSW | generateSW cannot add custom push/notificationclick event listeners; injectManifest required for Web Push |

**Installation:**
```bash
# Frontend
npm install -D vite-plugin-pwa

# Backend
composer require laravel-notification-channels/webpush

# Icon generation (dev only, can be removed after icons are created)
npm install -D sharp
```

## Architecture Patterns

### Recommended Project Structure
```
resources/js/
  sw.ts                          # Custom service worker (injectManifest source)
  composables/
    usePushSubscription.ts       # Push subscription management composable
  components/
    ReloadPrompt.vue             # "New version available" banner
    PushPermissionPrompt.vue     # First-login push permission dialog

app/
  Notifications/
    AssignmentPushedNotification.php   # Push for new assignment
    P1IncidentNotification.php         # Push for P1 incident
    AckTimeoutNotification.php         # Push for ack timeout warning

config/
  webpush.php                    # WebPush package config (published)

database/migrations/
  xxxx_create_push_subscriptions_table.php  # Published from package

public/
  pwa-192x192.png                # Generated PWA icon
  pwa-512x512.png                # Generated PWA icon
  maskable-icon-512x512.png      # Maskable variant for Android
```

### Pattern 1: injectManifest Service Worker with Push Events
**What:** Custom TypeScript service worker that combines Workbox precaching with Web Push event handlers
**When to use:** When the service worker needs custom logic beyond caching (push notifications, notification clicks)
**Example:**
```typescript
// resources/js/sw.ts
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';

declare let self: ServiceWorkerGlobalScope;

cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST);

// Handle push notifications
self.addEventListener('push', (event) => {
    const data = event.data?.json() ?? {};
    event.waitUntil(
        self.registration.showNotification(data.title ?? 'IRMS', {
            body: data.body,
            icon: '/pwa-192x192.png',
            badge: '/pwa-192x192.png',
            tag: data.tag,
            data: { url: data.url ?? '/' },
        }),
    );
});

// Handle notification click -- open/focus the app
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then((clients) => {
            const existing = clients.find((c) => c.url.includes(url));
            if (existing) {
                return existing.focus();
            }
            return self.clients.openWindow(url);
        }),
    );
});

// Handle skip waiting for prompt-for-update
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
```

### Pattern 2: Laravel Notification with WebPush Channel
**What:** Laravel Notification class that sends push via WebPushChannel alongside existing broadcast
**When to use:** When a broadcast event should also trigger a push notification
**Example:**
```php
// app/Notifications/AssignmentPushedNotification.php
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Illuminate\Notifications\Notification;

class AssignmentPushedNotification extends Notification
{
    public function __construct(
        public Incident $incident,
        public string $unitId,
    ) {}

    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('New Assignment')
            ->body("{$this->incident->incident_no} - {$this->incident->incidentType?->name}")
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-192x192.png')
            ->tag('assignment-' . $this->incident->id)
            ->data([
                'url' => '/assignment',
                'incident_id' => $this->incident->id,
            ])
            ->options(['TTL' => 300, 'urgency' => 'high']);
    }
}
```

### Pattern 3: Prompt-for-Update Vue Component
**What:** Vue component using `virtual:pwa-register/vue` to show update banner
**When to use:** Always mounted in app layout, shows when new service worker detected
**Example:**
```vue
<!-- resources/js/components/ReloadPrompt.vue -->
<script setup lang="ts">
import { useRegisterSW } from 'virtual:pwa-register/vue';

const { needRefresh, offlineReady, updateServiceWorker } = useRegisterSW();

function close() {
    needRefresh.value = false;
    offlineReady.value = false;
}
</script>

<template>
    <Transition ...>
        <div v-if="needRefresh" class="fixed bottom-4 right-4 ...">
            <span>New version available</span>
            <button @click="updateServiceWorker()">Reload</button>
            <button @click="close">Dismiss</button>
        </div>
    </Transition>
</template>
```

### Pattern 4: vite-plugin-pwa Configuration for Laravel
**What:** Specific VitePWA config that works with Laravel's `public/build/` output structure
**When to use:** Required configuration pattern for any Laravel + vite-plugin-pwa project
**Key settings:**
```typescript
// vite.config.ts
VitePWA({
    strategies: 'injectManifest',
    srcDir: 'resources/js',
    filename: 'sw.ts',
    buildBase: '/build/',
    scope: '/',
    base: '/',
    registerType: 'prompt',
    injectRegister: false, // Manual registration in app.ts
    manifest: {
        name: 'IRMS - Incident Response Management System',
        short_name: 'IRMS',
        theme_color: '#0B1120',
        background_color: '#0B1120',
        display: 'standalone',
        scope: '/',
        start_url: '/',
        id: '/',
        icons: [
            { src: '/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
            { src: '/pwa-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
            { src: '/maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
    },
    workbox: {
        // Only used for globPatterns in injectManifest mode
    },
    injectManifest: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
    },
})
```

### Anti-Patterns to Avoid
- **Using `generateSW` with push notifications:** generateSW does not support custom event listeners. Push and notificationclick handlers require `injectManifest`.
- **Registering service worker from `public/build/sw.js` without scope header:** The browser restricts SW scope to its file location. Without `Service-Worker-Allowed: /` header, the SW cannot control pages outside `/build/`.
- **Caching API responses in the service worker:** The decision is app shell only. Do not add runtime caching strategies for `/api/*` or Inertia page loads -- let them fail naturally and show the offline banner.
- **Auto-requesting push permission on page load:** This triggers browser's built-in permission prompt which users dismiss. Use the custom in-app dialog after first login instead.
- **Sending push notifications while the app is in the foreground:** Push is for background alerting. The in-app WebSocket events already handle foreground notifications. Check `clients.matchAll()` in the push handler and suppress if any window is focused, or let the notification show but mark it non-intrusive.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Service worker generation | Manual Workbox config + build script | vite-plugin-pwa | Handles precache manifest injection, revision hashing, registration script, and dev mode |
| Web push encryption/delivery | Custom VAPID signing + HTTP/2 push | laravel-notification-channels/webpush | VAPID key management, payload encryption (RFC 8291), endpoint rotation, expired subscription cleanup |
| Push subscription storage | Custom migration + model | webpush package's `HasPushSubscriptions` trait + published migration | Polymorphic `push_subscriptions` table with endpoint, public_key, auth_token, content_encoding |
| Manifest file | Static JSON in public/ | vite-plugin-pwa manifest config | Auto-generated with content hash, auto-linked in HTML via plugin |
| Service worker update detection | Manual `navigator.serviceWorker.controller` polling | `virtual:pwa-register/vue` composable | Provides reactive `needRefresh` and `offlineReady` refs with `updateServiceWorker()` action |

**Key insight:** The Web Push protocol involves ECDH key exchange, AES-GCM encryption, and VAPID JWT signing. The laravel-notification-channels/webpush package (built on minishlink/web-push-php) handles all cryptographic operations. Never implement push encryption manually.

## Common Pitfalls

### Pitfall 1: Service Worker Scope Restriction with Laravel Build Directory
**What goes wrong:** Service worker built to `public/build/sw.js` cannot control pages at `/` because browsers restrict scope to the SW file's directory.
**Why it happens:** Laravel Vite plugin builds to `public/build/` by default, but the PWA needs root scope.
**How to avoid:** Add `Service-Worker-Allowed: /` HTTP header when serving `sw.js`. In Laravel Herd (nginx-based), add to site-specific nginx config at `~/Library/Application Support/Herd/config/valet/Nginx/irms.test`. In production nginx, add `location /build/sw.js { add_header Service-Worker-Allowed /; }`.
**Warning signs:** Console error "The path of the provided scope ('/') is not under the max scope allowed ('/build/')".

### Pitfall 2: Manifest Link Not Auto-Injected in Blade Template
**What goes wrong:** vite-plugin-pwa auto-injects `<link rel="manifest">` into index.html, but Laravel uses Blade templates, not a static HTML entrypoint.
**How to avoid:** Set `injectRegister: false` in VitePWA config and manually add manifest link + service worker registration. The manifest file is generated to `public/build/manifest.webmanifest` -- add `<link rel="manifest" href="/build/manifest.webmanifest">` to `app.blade.php`. Register the service worker manually in `app.ts` or use the Vue composable.
**Warning signs:** No manifest detected by browser DevTools, "Add to Home Screen" not available.

### Pitfall 3: Safari VAPID_SUBJECT Requirement
**What goes wrong:** Push notifications work on Chrome/Firefox but fail on Safari/iOS with `BadJwtToken` error.
**Why it happens:** Apple requires `VAPID_SUBJECT` (a `mailto:` or HTTPS URL) in the JWT claims. Other browsers tolerate its absence.
**How to avoid:** Always set `VAPID_SUBJECT=mailto:cdrrmo@butuan.gov.ph` in `.env` alongside VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY.
**Warning signs:** Push works on Chrome, fails silently on Safari.

### Pitfall 4: Push Sent While App Is in Foreground Creates Duplicate Notification
**What goes wrong:** User sees both the in-app WebSocket notification (toast/audio) AND a system push notification for the same event.
**Why it happens:** Push notifications are delivered regardless of whether the app window is focused.
**How to avoid:** In the service worker `push` event handler, check `self.clients.matchAll({ type: 'window', includeUncontrolled: true })`. If any client is visible/focused, either suppress the system notification or use a lower priority. Alternatively, accept the duplication since push is mainly for backgrounded apps and foreground users will simply see both.
**Warning signs:** Users report seeing duplicate alerts.

### Pitfall 5: Service Worker Caches Inertia HTML Responses
**What goes wrong:** The service worker caches Inertia page HTML, causing stale content on navigation.
**Why it happens:** Default Workbox globPatterns include `**/*.html` or `navigateFallback` is set, which intercepts Inertia's server-rendered responses.
**How to avoid:** Only precache static assets (JS, CSS, fonts, images). Do NOT set `navigateFallback` -- let navigation requests go to network. Inertia handles its own page resolution via JSON responses, not static HTML files. The only HTML is `app.blade.php` which should be network-first.
**Warning signs:** App shows old data after deployment, navigation returns cached pages.

### Pitfall 6: Ack Timeout Push Notification Requires Server-Side Timer
**What goes wrong:** Relying on client-side `useAckTimer` composable to trigger ack timeout push -- but push needs to fire when the app is closed.
**Why it happens:** The existing `useAckTimer.ts` runs in the browser. If the responder's app is closed/backgrounded, no client-side code is executing.
**How to avoid:** Implement a server-side scheduled job (Laravel scheduler or delayed queue job) that checks for unacknowledged assignments past the 90-second threshold and sends the push notification. The job dispatches `AckTimeoutNotification` to the assigned user.
**Warning signs:** Ack timeout push only works when the app is open -- defeating the purpose.

## Code Examples

### Client-Side Push Subscription (usePushSubscription composable)
```typescript
// resources/js/composables/usePushSubscription.ts
import { ref } from 'vue';

const VAPID_PUBLIC_KEY = import.meta.env.VITE_VAPID_PUBLIC_KEY;

export function usePushSubscription() {
    const isSubscribed = ref(false);
    const isSupported = ref('serviceWorker' in navigator && 'PushManager' in window);

    async function subscribe(): Promise<boolean> {
        if (!isSupported.value) return false;

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
        });

        const json = subscription.toJSON();
        await fetch('/push-subscriptions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]'
                )?.content ?? '',
            },
            body: JSON.stringify({
                endpoint: json.endpoint,
                public_key: json.keys?.p256dh,
                auth_token: json.keys?.auth,
                content_encoding: 'aesgcm',
            }),
        });

        isSubscribed.value = true;
        return true;
    }

    async function unsubscribe(): Promise<void> {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
            const endpoint = subscription.endpoint;
            await subscription.unsubscribe();
            await fetch('/push-subscriptions', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint }),
            });
        }
        isSubscribed.value = false;
    }

    return { isSubscribed, isSupported, subscribe, unsubscribe };
}

function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return Uint8Array.from(rawData, (char) => char.charCodeAt(0));
}
```

### Server-Side: Hooking Push into Existing Events (Event Listener)
```php
// app/Listeners/SendAssignmentPushNotification.php
use App\Events\AssignmentPushed;
use App\Models\User;
use App\Notifications\AssignmentPushedNotification;

class SendAssignmentPushNotification
{
    public function handle(AssignmentPushed $event): void
    {
        $user = User::find($event->userId);
        if ($user) {
            $user->notify(new AssignmentPushedNotification(
                $event->incident,
                $event->unitId,
            ));
        }
    }
}
```

### Blade Template: Manifest Link + CSRF Meta
```html
<!-- resources/views/app.blade.php additions -->
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="manifest" href="/build/manifest.webmanifest">
<meta name="theme-color" content="#0B1120">
```

### Server-Side: Ack Timeout Check (Scheduled Job)
```php
// app/Jobs/CheckAckTimeouts.php -- dispatched every minute via scheduler
// Queries incident_unit pivot for unacknowledged assignments older than 90 seconds
// Sends AckTimeoutNotification via WebPush to assigned user
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual service worker + manifest.json | vite-plugin-pwa auto-generation | 2022+ | Eliminates boilerplate, handles versioning |
| GCM/FCM for web push | VAPID standard (RFC 8292) | 2018+ | No Google dependency, works cross-browser including Safari 16.4+ |
| Service worker via `navigator.serviceWorker.register()` | Virtual module `virtual:pwa-register/vue` | vite-plugin-pwa | Provides reactive Vue refs for SW state |
| Safari excluded from Web Push | Safari 16.4+ supports Web Push with VAPID | March 2023 | iOS/macOS Safari now works, requires VAPID_SUBJECT |
| `generateSW` for most PWAs | `injectManifest` when custom SW logic needed | Stable pattern | Push notifications require custom event listeners |

**Deprecated/outdated:**
- `sw-precache` / `sw-toolbox`: Replaced by Workbox (Google, 2018)
- GCM sender IDs for web push: Replaced by VAPID (standardized)
- `manifest.json` file name: `manifest.webmanifest` is the W3C standard (both work, plugin uses `.webmanifest`)

## Open Questions

1. **Service-Worker-Allowed header in Laravel Herd**
   - What we know: Herd uses nginx internally. Custom nginx config can be placed at `~/Library/Application Support/Herd/config/valet/Nginx/irms.test`
   - What's unclear: Whether Herd regenerates this config on updates. May need testing.
   - Recommendation: Create the nginx config addition. If Herd overwrites it, add the header via Laravel middleware on the SW response route instead.

2. **Ack timeout push -- server-side timer implementation**
   - What we know: A server-side mechanism is needed to detect unacknowledged assignments after 90 seconds
   - What's unclear: Whether to use a delayed queue job dispatched on assignment, or a scheduled command that scans every minute
   - Recommendation: Dispatch a delayed queue job (90-second delay) on each assignment. The job checks if `acknowledged_at` is still null when it runs. Simpler and more precise than a cron sweep.

3. **vite-plugin-pwa manifest output path**
   - What we know: With `buildBase: '/build/'`, the manifest goes to `public/build/manifest.webmanifest`
   - What's unclear: Whether the `<link rel="manifest">` must be manually added to Blade or if the plugin can inject it
   - Recommendation: Manual Blade insertion is safest. Set `injectRegister: false` and handle registration in app.ts.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --compact --filter=PwaTest` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| MOBILE-01 | Service worker registration + manifest served | smoke (manual) | Manual: browser DevTools Application tab | N/A -- frontend-only |
| MOBILE-02a | Push subscription CRUD endpoints | feature | `php artisan test --compact tests/Feature/PushSubscriptionTest.php -x` | Wave 0 |
| MOBILE-02b | AssignmentPushed triggers push notification | feature | `php artisan test --compact tests/Feature/PushNotificationTest.php -x` | Wave 0 |
| MOBILE-02c | P1 IncidentCreated triggers push to dispatchers | feature | `php artisan test --compact tests/Feature/PushNotificationTest.php -x` | Wave 0 |
| MOBILE-02d | Ack timeout job sends push notification | feature | `php artisan test --compact tests/Feature/AckTimeoutPushTest.php -x` | Wave 0 |
| MOBILE-02e | VAPID keys configured | unit | `php artisan test --compact tests/Unit/WebPushConfigTest.php -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact tests/Feature/PushSubscriptionTest.php tests/Feature/PushNotificationTest.php -x`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/PushSubscriptionTest.php` -- covers subscription CRUD
- [ ] `tests/Feature/PushNotificationTest.php` -- covers push notification dispatch on events
- [ ] `tests/Feature/AckTimeoutPushTest.php` -- covers ack timeout delayed job
- [ ] `tests/Unit/WebPushConfigTest.php` -- covers VAPID config presence

## Sources

### Primary (HIGH confidence)
- [vite-pwa/vite-plugin-pwa](https://github.com/vite-pwa/vite-plugin-pwa) - v1.2.0 release, Vite 7 support, injectManifest strategy, Vue integration
- [vite-pwa-org.netlify.app](https://vite-pwa-org.netlify.app/guide/) - Official docs: getting started, prompt-for-update, inject-manifest, Vue framework guide, Laravel integration
- [laravel-notification-channels/webpush](https://github.com/laravel-notification-channels/webpush) - v10.5.0, installation, HasPushSubscriptions trait, WebPushMessage API, VAPID setup
- [MDN Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API) - Push event, notificationclick event, PushManager.subscribe()
- [MDN ServiceWorkerGlobalScope: push event](https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerGlobalScope/push_event) - Event handler pattern
- [MDN ServiceWorkerGlobalScope: notificationclick event](https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerGlobalScope/notificationclick_event) - Notification click handling

### Secondary (MEDIUM confidence)
- [sfreytag/laravel-vite-pwa](https://github.com/sfreytag/laravel-vite-pwa) - Reference implementation for Laravel + vite-plugin-pwa integration (Laravel 10, but config patterns apply)
- [vite-pwa/vite-plugin-pwa#431](https://github.com/vite-pwa/vite-plugin-pwa/issues/431) - Laravel integration discussion, outDir/buildBase/scope/base configuration
- [Herd nginx configuration](https://herd.laravel.com/docs/macos/sites/nginx-configuration) - Site-specific nginx config location

### Tertiary (LOW confidence)
- Sharp SVG-to-PNG conversion -- verified sharp supports SVG input, but exact CLI flags for this project's SVG need testing

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - vite-plugin-pwa and webpush package are well-documented, versions verified
- Architecture: HIGH - injectManifest + webpush channel is the established pattern; Laravel integration verified via reference repo and GitHub issues
- Pitfalls: HIGH - scope restriction, Blade manifest injection, and Safari VAPID_SUBJECT are well-documented issues with known solutions
- Push notification flow: MEDIUM - the ack timeout server-side timer is a custom design decision; delayed queue job approach is sound but untested in this codebase

**Research date:** 2026-03-15
**Valid until:** 2026-04-15 (stable libraries, 30-day validity)
