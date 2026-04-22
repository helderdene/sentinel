---
phase: 21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai
plan: 03
subsystem: backend
tags: [laravel, inertia, fras, signed-urls, recognition-events, intake-station]

# Dependency graph
requires:
  - phase: 21-01
    provides: Nyquist RED tests (IntakeStationFrasRailTest, EscalateToP1Test) + config/fras.recognition.pulse_duration_seconds
  - phase: 21-02
    provides: FrasIncidentFactory + 5-gate recognition bridge
  - phase: 20
    provides: admin.personnel.photo signed-URL pattern + fras_photos/fras_events disks
provides:
  - "recentFrasEvents Inertia prop on IntakeStation (top 50 Critical+Warning RecognitionEvents, D-18 shape)"
  - "trigger field on intake.override-priority route (manual_override | fras_escalate_button, D-22)"
  - "frasConfig Inertia shared prop exposing pulseDurationSeconds (D-15)"
  - "Signed fras.event.face route + FrasEventFaceController (D-20 option a, operator/supervisor/admin only)"
affects: [21-04, 21-05, 22-fras-access-log]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Explicit per-route URL/name prefixing inside routes/fras.php (bootstrap group no longer applies admin/ blanket)"
    - "Signed 5-min URLs pre-computed server-side at Inertia prop boot for face-image crops"
    - "Role gate enforced IN-CONTROLLER (abort_unless) in addition to middleware for defense-in-depth"

key-files:
  created:
    - app/Http/Controllers/FrasEventFaceController.php
  modified:
    - app/Http/Controllers/IntakeStationController.php
    - app/Http/Middleware/HandleInertiaRequests.php
    - routes/fras.php
    - bootstrap/app.php
    - tests/Feature/Fras/IntakeStationFrasRailTest.php

key-decisions:
  - "routes/fras.php restructured to explicit per-route prefixes (legacy admin.personnel.photo kept under /admin/, new fras.event.face lives at /fras/events/...) instead of bootstrap-wide admin prefix"
  - "Role gate in FrasEventFaceController uses abort_unless(in_array($user->role, [Operator,Supervisor,Admin]))) rather than relying solely on middleware — defense-in-depth; middleware stack already denies responders/dispatchers but controller-level check defends against future route misconfiguration"
  - "face_image_url computed at prop-boot time (not lazy) because the rail needs URLs immediately for SSR; 5-min TTL bounds staleness"
  - "TODO(Phase 22) comment in FrasEventFaceController::show marks exact insertion point for fras_access_log writes (per-fetch audit row)"

patterns-established:
  - "Per-route prefix pattern in routes/fras.php: legacy routes stay admin-prefixed via inner Route::prefix('admin')->name('admin.') group; new FRAS routes declare their own URL prefix"
  - "Signed-URL pre-computation at Inertia prop boot (server generates, frontend consumes read-only)"
  - "Role gate layered in bootstrap middleware (role:operator,supervisor,admin) AND in controller (abort_unless in_array) — belt-and-braces"

requirements-completed: [RECOGNITION-04, RECOGNITION-08, INTEGRATION-03]

# Metrics
duration: 18min
completed: 2026-04-22
---

# Phase 21 Plan 03: IntakeStation FRAS Rail Backend + Signed Face-Image Route Summary

**Wave 2 backend: recentFrasEvents Inertia prop (D-18) + Escalate-to-P1 trigger audit (D-22) + frasConfig pulse shared prop (D-15) + signed fras.event.face route (D-20 option a) wired through FrasEventFaceController with operator/supervisor/admin role gate.**

## Performance

- **Duration:** 18 min
- **Started:** 2026-04-22T03:31:00Z
- **Completed:** 2026-04-22T03:49:17Z
- **Tasks:** 2
- **Files modified:** 5 (+1 created)

## Accomplishments
- `IntakeStationController::show()` returns `recentFrasEvents` — top 50 Critical+Warning RecognitionEvents ordered by `received_at DESC`, shaped per D-18 (10 keys including pre-signed 5-min `face_image_url`).
- `IntakeStationController::overridePriority()` accepts optional `trigger` field validated against `['manual_override','fras_escalate_button']`, defaults to `'manual_override'` to preserve v1.0 audit shape. Writes to `IncidentTimeline::event_data.trigger`.
- `HandleInertiaRequests::share()` exposes `frasConfig.pulseDurationSeconds` globally (reads from `config('fras.recognition.pulse_duration_seconds', 3)` — verified returns 3).
- `FrasEventFaceController::show()` streams face crops from the `fras_events` private disk with role gate (operator/supervisor/admin only) + explicit `image/jpeg` Content-Type + `X-Content-Type-Options: nosniff`.
- `routes/fras.php` restructured: existing `admin.personnel.photo` route preserved exactly (URL `/admin/personnel/{personnel}/photo`, name `admin.personnel.photo`) via explicit inner `Route::prefix('admin')->name('admin.')` group; new `fras.event.face` route at `/fras/events/{event}/face` (signed middleware only; role gate applied at bootstrap layer).
- `bootstrap/app.php` updated: the `routes/fras.php` group no longer applies a blanket `admin/` URL + `admin.` name prefix — each route in the file now declares its own prefix.
- 3 new tests added to `IntakeStationFrasRailTest`: role gate (operator 200, dispatcher/responder 403), 404 on null `face_image_path`, 403 on unsigned URLs.
- `AdminPersonnelPhotoControllerTest` (5 tests) continues to pass — no regression from route restructure.

## Task Commits

Each task was committed atomically:

1. **Task 1: Extend IntakeStationController + HandleInertiaRequests** — `b7b9057` (feat)
2. **Task 2: Ship signed-URL face-image route + FrasEventFaceController** — `7428fbe` (feat)

## Files Created/Modified

### Created
- `app/Http/Controllers/FrasEventFaceController.php` — Signed face-image stream controller. `show()` enforces role gate via `abort_unless(in_array($user->role, [Operator, Supervisor, Admin]))`, validates `face_image_path` non-null, verifies disk existence, then streams via `Storage::disk('fras_events')->response()` with `image/jpeg` + `X-Content-Type-Options: nosniff` + `Cache-Control: private, max-age=60`. Marked for Phase 22 `fras_access_log` extension via inline TODO.

### Modified
- `app/Http/Controllers/IntakeStationController.php` — Added `$recentFrasEvents` builder after `$recentActivity` (queries Critical+Warning RecognitionEvents with eager-loaded `camera:id,camera_id_display,name` + `personnel:id,name,category`; limit 50; orderByDesc received_at; maps to 10-key D-18 shape with pre-signed `face_image_url` via `URL::temporarySignedRoute('fras.event.face', now()->addMinutes(5), ['event' => $event->id])`). Added `'recentFrasEvents' => $recentFrasEvents` to Inertia::render. Extended `overridePriority()` validation with `'trigger' => ['sometimes', 'in:manual_override,fras_escalate_button']` and added `'trigger' => $validated['trigger'] ?? 'manual_override'` into `event_data`. Added imports for `RecognitionSeverity`, `RecognitionEvent`, `URL` facade.
- `app/Http/Middleware/HandleInertiaRequests.php` — Added `'frasConfig' => ['pulseDurationSeconds' => (int) config('fras.recognition.pulse_duration_seconds', 3)]` after `channelCounts` in the `share()` return array.
- `routes/fras.php` — Rewritten with per-route prefix groups: (a) inner `Route::prefix('admin')->name('admin.')` wrapping the legacy `personnel.photo` route (preserves `/admin/personnel/{id}/photo` + `admin.personnel.photo`); (b) unprefixed `fras.event.face` at `/fras/events/{event}/face` with `signed` middleware.
- `bootstrap/app.php` — Removed `->prefix('admin')->name('admin.')` from the `routes/fras.php` registration; per-route prefixing now handled inside the file.
- `tests/Feature/Fras/IntakeStationFrasRailTest.php` — Added `UserRole`, `Storage`, `URL` imports. Added `signedFaceUrl()` helper. Added 3 tests: operator-200/dispatcher-403/responder-403 role gate; 404 on null face_image_path; 403 on missing signature.

## Disk Name Verified

`FrasEventFaceController` uses `Storage::disk('fras_events')` — confirmed against `config/filesystems.php` which defines `fras_events` disk at `storage_path('app/private/fras_events')` with `private` visibility (driver defaults to `env('FRAS_EVENT_DISK', 'local')`). This is the same disk `app/Mqtt/Handlers/RecognitionHandler.php` writes face crops to during recognition ingest.

## Route Verification

```text
GET|HEAD  fras/events/{event}/face       fras.event.face            › FrasEventFaceController@show
GET|HEAD  admin/personnel/{personnel}/photo  admin.personnel.photo  › Admin\AdminPersonnelPhotoController@show
```

Both registered with `signed` middleware. The bootstrap-applied chain `['web','auth','verified','role:operator,supervisor,admin']` gates both.

## Config Verified

```text
fras.recognition.pulse_duration_seconds .................................. 3
```

## frasConfig Payload Verified

Via `IntakeStationFrasRailTest::it_renders_recentFrasEvents_as_empty_array_when_no_recognition_events_exist` assertion `$page->has('frasConfig.pulseDurationSeconds')` — passes with value `3`.

## Test Results

| Suite | Tests | Status |
|------|-------|--------|
| `IntakeStationFrasRailTest` | 6 | 6 passed (51 assertions) |
| `EscalateToP1Test` | 4 | 4 passed (10 assertions) |
| `AdminPersonnelPhotoControllerTest` | 5 | 5 passed (10 assertions) — no regression |
| `--filter=Fras` (full FRAS suite) | 111 | 111 passed (341 assertions) |

## Decisions Made

- **routes/fras.php per-route prefixing.** Original file (Phase 20) was registered under a bootstrap-wide `->prefix('admin')->name('admin.')` wrapper. Plan 03 required a new `fras.event.face` route at `/fras/events/...` (NOT `/admin/fras/events/...`). Rather than create a second file + second group registration, chose to restructure the existing file with an explicit inner `Route::prefix('admin')->name('admin.')` group around the legacy `personnel.photo` route and leave new routes at their natural URLs. This preserves `admin.personnel.photo` exactly (tests + controller signedRoute calls unchanged) while letting `fras.event.face` land at the correct URL.
- **Defense-in-depth role gate.** Even though the bootstrap middleware chain already blocks responder/dispatcher, the controller also runs `abort_unless(in_array($user->role, [Operator,Supervisor,Admin]))`. Slight redundancy; safe-by-default if routes are ever re-wired.
- **Eager load selective columns.** `with(['camera:id,camera_id_display,name', 'personnel:id,name,category'])` keeps payload minimal — only the 3 fields the rail projects. Avoids hydrating full Camera/Personnel rows for 50-row collection.
- **`face_image_url` pre-signed at prop boot, NOT lazy.** The rail SSR-renders 50 events on first paint; requiring a round-trip to mint signed URLs would add latency and break the "fast-paint" D-18 contract. 5-min TTL is ample for the page's lifetime; on refresh the controller mints fresh URLs.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] routes/fras.php bootstrap prefix conflicted with fras.event.face URL contract**
- **Found during:** Task 2 (signed route registration)
- **Issue:** The plan specified `fras.event.face` at URL `/fras/events/{event}/face` with name `fras.event.face`. But `bootstrap/app.php` wrapped the entire `routes/fras.php` file in `->prefix('admin')->name('admin.')` — any route added to the file would land at `/admin/fras/events/...` with name `admin.fras.event.face`, violating the UI-SPEC + tests.
- **Fix:** Removed the `->prefix('admin')->name('admin.')` chain from the `routes/fras.php` registration in `bootstrap/app.php`. Inside `routes/fras.php`, wrapped the existing `personnel.photo` route in an explicit `Route::prefix('admin')->name('admin.')` inner group to preserve its `/admin/personnel/{id}/photo` URL + `admin.personnel.photo` name EXACTLY (verified via `php artisan route:list --name=personnel.photo` before/after — identical output; verified via `AdminPersonnelPhotoControllerTest` 5/5 green post-change).
- **Files modified:** `bootstrap/app.php`, `routes/fras.php`
- **Verification:** Both routes register at expected URLs/names; `AdminPersonnelPhotoControllerTest` suite unchanged (5/5 passing); `IntakeStationFrasRailTest` face-route tests 3/3 passing.
- **Committed in:** `7428fbe` (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Necessary for the plan's URL contract to hold. No scope creep — touched only the files already in Task 2's file list (`routes/fras.php`) plus `bootstrap/app.php` which is unavoidable since it's the registration boundary. Legacy route fully preserved.

## Issues Encountered
None. Pint `--dirty` was clean for all touched files on both commits. Pint `--test` reports failures in 17 unrelated pre-existing files (tests/Unit/ExampleTest.php, config/image.php, multiple auth tests, tests/Feature/Admin/AdminUnitTest.php, tests/Feature/Fras/CameraEnrollmentServiceTest.php, tests/Feature/Dispatch/DispatchConsolePageTest.php) — all pre-existing style drift in files I did NOT modify. Logged to the phase deferred-items list if needed; out of scope per executor rules.

## User Setup Required

None — no external service configuration required for this plan. All changes are code + routes + controllers.

## Next Phase Readiness

- Plans 04 and 05 (frontend FRAS rail + dispatch pulse composable) can now consume:
  - `recentFrasEvents` prop at `/intake` (Plan 04 rail component)
  - `frasConfig.pulseDurationSeconds` shared prop (Plan 05 dispatch pulse composable)
  - `fras.event.face` signed-URL route via Wayfinder (`@/routes/fras` or direct URL from props)
  - `trigger: 'fras_escalate_button'` POST field on `intake.override-priority` (Plan 04 Escalate-to-P1 button)
- Plan 05 (dispatch pulse + modal) will consume `face_image_url` directly from `recentFrasEvents` payload (no client-side URL minting needed).
- Phase 22 (fras_access_log) insertion point marked with TODO in `FrasEventFaceController::show()` — one-line addition to capture actor + IP + image ref + timestamp per fetch.

## Self-Check: PASSED

Verified:
- `app/Http/Controllers/FrasEventFaceController.php` — exists
- `app/Http/Controllers/IntakeStationController.php` — modified (recentFrasEvents, trigger, temporarySignedRoute present)
- `app/Http/Middleware/HandleInertiaRequests.php` — modified (frasConfig, pulseDurationSeconds present)
- `routes/fras.php` — modified (fras.event.face + admin.personnel.photo both present)
- `bootstrap/app.php` — modified (no admin prefix on fras.php group)
- `tests/Feature/Fras/IntakeStationFrasRailTest.php` — 3 new test cases added
- Commit `b7b9057` — present in git log (Task 1)
- Commit `7428fbe` — present in git log (Task 2)
- `php artisan route:list --name=fras.event.face` — returns route
- All targeted test suites green

---
*Phase: 21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai*
*Completed: 2026-04-22*
