---
status: complete
phase: 20-camera-personnel-admin-enrollment
source: [20-VERIFICATION.md]
started: 2026-04-21T16:42:28Z
updated: 2026-04-22T09:45:00Z
completed: 2026-04-22T09:45:00Z
---

## Current Test

[all tests complete]

## Tests

### 1. Camera Create + Mapbox-GL Picker Visual QA (CAMERA-02)

expected: Interactive Mapbox map renders; pin drop updates lat/lng; address auto-fills; camera created successfully; `/admin/cameras` table shows `CAM-01` entry with name and status.

steps:
1. Log in as admin at `irms.test`
2. Navigate to `/admin/cameras/create`
3. Click a location on the Mapbox map — verify a pin drops
4. Verify lat/lng fields update to the click coordinates
5. Verify address field populates via forward-geocode (may be empty if no Mapbox token configured)
6. Fill name + device_id, submit
7. Verify camera appears in `/admin/cameras` with auto-assigned `CAM-NN` id

why_human: Mapbox-GL rendering + forward-geocoding are browser/network interactions that cannot be verified programmatically.

result: pass
notes: Initial attempt blocked by G-01 (missing sidebar nav link); after fix in commit 5cd2759 user confirmed pass on 2026-04-22.

### 2. Dispatch Console Cameras Layer UAT (CAMERA-03 + CAMERA-04)

expected: 3 sub-layers (halo/body/label) render per camera; toggle sets layout visibility to none/visible; Popup opens on click with escaped HTML content; edit link is navigable to `/admin/cameras/{id}/edit`.

steps:
1. Log in as dispatcher at `irms.test`
2. Navigate to `/dispatch/console`
3. Verify camera markers appear as colored symbols on the map (blue=online, amber=degraded, gray=offline)
4. Click the cameras-layer toggle button (top-left map area)
5. Verify all camera markers hide
6. Click the toggle again — verify markers reappear
7. Click one camera marker
8. Verify Popup opens with camera name, status badge, last-seen timestamp, and "Edit camera" link
9. Click the edit link — verify navigation to `/admin/cameras/{id}/edit`

why_human: WebGL map rendering correctness, layer z-ordering, Popup HTML content, and color-coded status transitions require a live browser with WebGL hardware acceleration.

result: pass
notes: Initial attempt surfaced G-02 (popup text invisible in dark mode); after fix in commit c2e5928 user confirmed pass on 2026-04-22.

## Summary

total: 2
passed: 2
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

### G-01: Admin sidebar missing Cameras + Personnel links

status: resolved
found_during: Test 1 attempt (2026-04-22)
severity: blocker (user could not reach /admin/cameras to test)

issue:
  The Admin sidebar submenu ships with Users, Barangays, Incident Categories,
  Incident Types, Checklist Templates, Units, and City — but no Cameras or
  Personnel entries. Admins had no UI path to reach the pages built in Plan 20-07.

root_cause:
  Plan 20-07 shipped the 4 Inertia pages + components + composable but did not
  add the nav entries to `resources/js/components/AppSidebar.vue`. The plan's
  scope focused on the per-page surface, not sidebar wiring.

fix: commit 5cd2759 — added `Cameras` (Camera icon) and `Personnel` (IdCard
icon) under Admin > children, positioned after Units so operational/fleet
items group together above City. `npm run types:check` passes.

retest: user needs to refresh the admin session (Vite dev server will hot-
reload; `npm run build` needed for production bundle) and retry Test 1.

### G-02: Camera Popup text invisible in dark mode

status: resolved
found_during: Test 2 (2026-04-22)
severity: warning (popup is the primary way dispatchers see camera detail)

issue:
  On `/dispatch/console` in dark mode, clicking a camera marker opens a
  mapbox-gl Popup whose background is hardcoded white. The name line
  (font-medium inherits foreground) and Edit-camera link rendered in
  dark-mode light colors that disappeared against the white popup.
  Only the `• online` middle row stayed legible (text-muted-foreground
  happens to resolve to a darker gray that's barely visible).

root_cause:
  `useDispatchMap.ts` camera popup used theme-aware Tailwind utilities
  (`font-medium` inherits `text-foreground`; `text-muted-foreground`;
  underlined link inherits primary color) that flip lightness in dark
  mode. Mapbox Popup has no dark-mode variant — its container CSS is
  hardcoded white.

fix: commit c2e5928 — switched popup text to explicit theme-independent
colors: `text-slate-900` (name), `text-slate-600` (status row),
`text-blue-600` (Edit camera link). All three stay readable against the
popup's white background regardless of app theme.

retest: refresh `/dispatch/console` and click a camera marker; name +
status + edit link should all be visible against the popup's white
background in both light and dark modes.

### G-03: Admin cameras table does not live-update on CameraStatusChanged

status: resolved
found_during: post-UAT inspection (2026-04-22)
severity: functional gap (admins saw stale status until manual refresh)

issue:
  `/admin/cameras` displayed camera CAM-02 "CCTV sa Balay" as **Offline**
  even though MQTT heartbeats were being received and the DB showed
  `status: online`, `last_seen_at: 8s ago`. Verified the backend state
  via `php artisan tinker` and confirmed `CameraWatchdogCommand` was
  running via `schedule:list`. The dispatch console DID show the correct
  status live — only the admin table was stale.

root_cause:
  Plan 20-07 shipped `Cameras.vue` consuming `props.cameras` directly
  with no `useEcho('fras.cameras', 'CameraStatusChanged', ...)`
  subscription. Plan 20-08 wired the same broadcast into the dispatch
  console (`Console.vue`) via `updateCameraStatus`, but the admin index
  was not wired. Broadcasts fired but had no listener on the admin
  surface → admin table only reflected status at page-load time.

fix: commit 10bd211 — mirror `props.cameras` into reactive `ref(rows)`
with a `watch(props.cameras)` re-sync on Inertia reload, subscribe via
`useEcho<CameraStatusChangedPayload>('fras.cameras', 'CameraStatusChanged', ...)`
and mutate the matching row's `status` on arrival. `CameraStatusBadge`
already reacts to `row.status` via Vue reactivity. Types-check clean.

retest: refresh `/admin/cameras`, observe a camera with heartbeats
coming in (or wait for the watchdog to run). The status badge should
flip online/degraded/offline in place without a page reload.
