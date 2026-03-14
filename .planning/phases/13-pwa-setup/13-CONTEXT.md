# Phase 13: PWA setup - Context

**Gathered:** 2026-03-15
**Status:** Ready for planning

<domain>
## Phase Boundary

Make the main IRMS web application installable as a Progressive Web App with service worker, web app manifest, offline app shell caching, and Web Push notifications for critical events. The citizen report-app (separate Vite build) is out of scope for PWA in this phase.

</domain>

<decisions>
## Implementation Decisions

### Install scope
- Main IRMS app only (dispatch, intake, responder, admin) — single PWA
- Citizen report-app stays a regular web page (separate phase if needed)
- Display mode: standalone (no browser chrome)
- Start URL: `/` with role-based redirect (existing LoginResponse handles routing responder → /assignment, dispatcher → /dispatch, operator → /intake)
- Tooling: vite-plugin-pwa for manifest and service worker generation from vite.config.ts

### Offline strategy
- App shell caching — cache JS/CSS/HTML so the app loads instantly without network
- No offline-first data caching (no IndexedDB, no sync queue)
- API requests: network-first strategy — try network, fall back to cache only for static assets
- Offline UX: persistent top banner ("You are offline") using existing ConnectionBanner component pattern
- App updates: "New version available — Reload" banner when service worker detects update; user chooses when to reload

### Push notifications
- Included in this phase (not deferred)
- Backend: VAPID key pair, push subscription storage, Laravel notification channel
- Events that trigger push:
  1. New assignment pushed to responder
  2. P1 incident created (all dispatchers notified)
  3. Ack timeout warning for unacknowledged assignments
- New dispatch messages: NOT included in push (too noisy)
- Notification preferences: fixed per role, no user settings page
  - Responders receive: assignment + ack timeout
  - Dispatchers receive: P1 alerts
- Permission prompt: custom in-app prompt after first successful login explaining value; if dismissed, don't re-ask until settings

### App identity
- App name: short "IRMS", full "IRMS - Incident Response Management System"
- Theme color: dark navy from design system (--t-bg / #0B1120)
- App icon: existing CDRRMO shield SVG from AppLogo, exported as 192x192 and 512x512 PNGs + maskable variant for Android
- Splash screen: dark only (matches app's dark-mode emergency operations aesthetic)

### Claude's Discretion
- Exact vite-plugin-pwa configuration options (workbox strategies, glob patterns)
- Service worker registration timing
- Push notification payload structure
- Icon generation tooling (sharp, pwa-asset-generator, etc.)
- Maskable icon safe zone handling

</decisions>

<specifics>
## Specific Ideas

- The app already has a ConnectionBanner component and useConnectionStatus composable — reuse for offline detection
- Existing apple-touch-icon link already in app.blade.php — extend with full manifest link
- AssignmentPushed event already broadcasts to responders — hook push notification into same event
- P1 incident detection already exists in dispatch metrics — reuse for push trigger

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `ConnectionBanner.vue` + `useConnectionStatus` composable: online/offline detection already built
- `AppLogo.vue`: CDRRMO shield SVG that can be exported for PWA icons
- `AssignmentPushed` event: already broadcasts to individual responders — extend with push notification
- `IncidentCreated` event: already fires on new incidents — filter for P1 and push to dispatchers
- `useAckTimer` composable: tracks ack timeouts — can trigger push on expiry

### Established Patterns
- Vite 7 build with `laravel-vite-plugin` — vite-plugin-pwa integrates alongside it
- Laravel Notifications system available for push channel
- Reverb WebSocket already handles real-time — push notifications are for when app is closed/backgrounded
- Design tokens in CSS custom properties (--t-bg, --t-brand) for consistent theming

### Integration Points
- `vite.config.ts`: add VitePWA plugin configuration
- `resources/views/app.blade.php`: manifest link tag (auto-injected by plugin)
- `app/Events/AssignmentPushed.php`: add WebPush notification alongside broadcast
- `app/Providers/AppServiceProvider.php`: VAPID key configuration
- `database/migrations`: push_subscriptions table for storing user push endpoints
- `resources/js/app.ts`: service worker registration

</code_context>

<deferred>
## Deferred Ideas

- Citizen report-app PWA — separate phase if needed
- Offline-first responder with IndexedDB sync queue — future phase for areas with poor connectivity
- User-configurable notification preferences — future enhancement if role-based proves too rigid
- Background sync for queued status updates — future phase tied to offline-first

</deferred>

---

*Phase: 13-pwa-setup*
*Context gathered: 2026-03-15*
