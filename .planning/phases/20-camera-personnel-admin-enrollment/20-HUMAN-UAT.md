---
status: testing
phase: 20-camera-personnel-admin-enrollment
source: [20-VERIFICATION.md]
started: 2026-04-21T16:42:28Z
updated: 2026-04-22T09:20:00Z
---

## Current Test

number: 1
name: Camera Create + Mapbox-GL Picker Visual QA
expected: |
  On `/admin/cameras/create`, the Mapbox map renders interactively. Clicking anywhere on the map drops a pin; lat/lng fields update to those coordinates. Address field auto-fills via forward-geocode (may be empty if no Mapbox token). After filling name + device_id and submitting, the camera appears in `/admin/cameras` with an auto-assigned `CAM-NN` id (CAM-01 if first).
awaiting: user response

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

result: [pending]

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

result: [pending]

## Summary

total: 2
passed: 0
issues: 0
pending: 2
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
