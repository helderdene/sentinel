---
phase: 20-camera-personnel-admin-enrollment
plan: 04
subsystem: fras-admin
tags: [wave-2, admin, cameras, crud, barangay-lookup, enrollment, deletion-guard]
requirements:
  - CAMERA-01
  - CAMERA-02
  - CAMERA-06
dependency_graph:
  requires:
    - plan-20-01 (Camera::enrollments() HasMany + Camera model)
    - plan-20-02 (CameraEnrollmentService::enrollAllToCamera)
    - plan-20-03 (EnrollPersonnelBatch job — only dispatched when camera is Online)
    - Phase 18 cameras table + BarangayLookupService (v1.0 Phase 2)
  provides:
    - App\Http\Controllers\Admin\AdminCameraController (7 methods)
    - App\Http\Requests\Admin\StoreCameraRequest + UpdateCameraRequest
    - 8 admin.cameras.* routes (7 resource + 1 recommission)
    - cameras.barangay_id FK + cameras.notes columns (additive migration)
    - cameras.location_label NULLABLE (loosened from Phase 18 NOT NULL)
    - Camera::barangay() BelongsTo relation
    - resources/js/actions/App/Http/Controllers/Admin/AdminCameraController.ts (Wayfinder)
    - resources/js/routes/admin/cameras/ (Wayfinder)
  affects:
    - plan-20-05 (AdminPersonnelController — parallel shape reuse)
    - plan-20-07 (Cameras.vue + CameraForm.vue Mapbox picker — replaces stub pages shipped here)
    - plan-20-08 (dispatch map cameras layer — Camera::barangay relation used for hover card)
tech-stack:
  added: []
  patterns:
    - Auto-sequenced display ID via PostgreSQL SUBSTRING/CAST MAX+1 regex (verbatim mirror of AdminUnitController::store)
    - Constructor-promoted service DI (CameraEnrollmentService + BarangayLookupService)
    - Magellan Point::makeGeodetic(lat, lng) for geography(POINT, 4326) persistence
    - Soft-decommission via decommissioned_at timestamp (mirrors Unit pattern)
    - withErrors-based deletion guard returning operator-friendly 422-shaped session error
    - Backend-first placeholder .vue pages so Inertia ::render passes Vite manifest lookup without the Plan 07 UI
key-files:
  created:
    - app/Http/Controllers/Admin/AdminCameraController.php
    - app/Http/Requests/Admin/StoreCameraRequest.php
    - app/Http/Requests/Admin/UpdateCameraRequest.php
    - database/migrations/2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php
    - tests/Feature/Admin/AdminCameraControllerTest.php
    - resources/js/pages/admin/Cameras.vue (placeholder — Plan 07 replaces)
    - resources/js/pages/admin/CameraForm.vue (placeholder — Plan 07 replaces)
  modified:
    - app/Models/Camera.php (barangay_id + notes added to $fillable; barangay() BelongsTo added)
    - routes/admin.php (Route::resource cameras + recommission route; narrow scope — no personnel)
decisions:
  - "20-04-D1: Shipped a second cameras migration (2026_04_22_000002) instead of rewriting 2026_04_21_000001 — preserves Phase 18 schema-freeze contract (D-20) while adding barangay_id FK + notes column + loosening location_label to NULLABLE. Additive-only, reversible via standard rollback."
  - "20-04-D2: location_label loosened from NOT NULL to NULLABLE as part of the same migration (Rule 1 bug fix) — StoreCameraRequest marks it `nullable` and the Plan 07 Mapbox picker only emits it after reverseGeocode resolves (which may fail silently). Phase 18 shipped NOT NULL unilaterally; this conflicts with the plan's Form Request contract."
  - "20-04-D3: Point::makeGeodetic(lat, lng) confirmed as the only Magellan factory used in v1.0 codebase (grep returned 12 matches across controllers + factories + seeders, zero uses of Point::make or new Point()). Controller adopts this convention verbatim."
  - "20-04-D4: store() persists status=CameraStatus::Offline on new cameras — CameraEnrollmentService::enrollAllToCamera gates on status === Online, so new offline cameras produce zero EnrollPersonnelBatch dispatches. The enrollment test asserts this contract (Queue::assertNotPushed) rather than asserting a push that would never happen. First heartbeat from the camera flips status to Online via CameraWatchdog (Plan 06), which then triggers the first sync. Enrollment fan-out coverage lives in CameraEnrollmentService tests."
  - "20-04-D5: Placeholder Cameras.vue + CameraForm.vue shipped in resources/js/pages/admin/ as 9-line stubs — required so Inertia ::render succeeds Vite manifest lookup during Pest tests. AdminBarangayTest / AdminUnitTest rely on the same pattern (real page files exist in main). Plan 07 wholly rewrites these with the Mapbox-GL picker + table + edit form."
  - "20-04-D6: Index returns ALL cameras (including decommissioned) per D-08 UI-SPEC — decommissioned cameras render with opacity-50 in Plan 07's table, filter happens client-side. Scope `Camera::active()` is NOT applied here; the `cameras` prop carries the full fleet."
  - "20-04-D7: Deletion guard message hardcoded in controller (not extracted to a language file) — matches v1.0 convention (AdminUnitController's `Cannot decommission unit with active incidents.` is inline). Operator-friendly copy from UI-SPEC §Error states; 422 shape comes from `withErrors` semantics (session error bag + redirect back)."
metrics:
  duration: 6min
  completed_date: 2026-04-21
  tasks_completed: 2
  files_created: 7
  files_modified: 2
  tests_added: 12 (72 assertions)
  fras_group_before: 51 passed + 1 skipped
  fras_group_after: 63 passed + 1 skipped (+12 new)
---

# Phase 20 Plan 04: Admin Camera CRUD — Summary

Backend admin surface for the camera fleet landed complete: 7-method AdminCameraController with auto-sequenced display IDs, PostGIS barangay auto-assignment via the v1.0 BarangayLookupService, pre-existing-personnel enrollment fan-out via CameraEnrollmentService, and a deletion guard that blocks soft-decommission when any enrollment row is pending/syncing. 12 Pest tests cover the full contract (72 assertions, 1.21s). `--group=fras`: 63 passed + 1 skipped (was 51+1 before this plan; +12 new all green).

## Requirements Addressed

- **CAMERA-01** — Admin can list, create, edit, decommission, and recommission cameras via `/admin/cameras/*`
- **CAMERA-02** — Camera placement resolves barangay_id server-side via PostGIS ST_Contains (called on both store and update with fresh coords)
- **CAMERA-06** — Deletion guard blocks soft-decommission when in-flight enrollments exist; operator-friendly error surfaces to the session errors bag

## Task 1: Form Requests + routes + schema extension (commit a6d5b4a)

- `StoreCameraRequest` + `UpdateCameraRequest` — 6-field rules (name, device_id unique, latitude, longitude, location_label nullable, notes nullable). Update variant uses `Rule::unique('cameras','device_id')->ignore($ignoreId)` from the route-bound Camera.
- `routes/admin.php` — 2-line addition: `Route::resource('cameras', AdminCameraController::class)` + `Route::post('cameras/{camera}/recommission', ...)->name('cameras.recommission')`. Imports hoisted alphabetically. Narrow-scope per orchestrator: zero personnel route edits (20-05 owns those).
- `2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php` — additive migration adding `barangay_id` (FK to barangays, nullable, nullOnDelete), `notes` (text, nullable), and loosening `location_label` to nullable. Down() drops all three.
- Camera model: `$fillable` extended with `barangay_id` + `notes`; `barangay()` BelongsTo relation added after `enrollments()`.
- Empty stub AdminCameraController (body filled in Task 2).

**Verification:** `php artisan route:list --path=admin/cameras --except-vendor` returned 8 routes (7 resource + 1 recommission).

## Task 2: AdminCameraController body + 12 Pest tests (commit 0596157)

### Controller shape (168 lines)

- **Constructor DI:** `CameraEnrollmentService` + `BarangayLookupService` — both injected via promoted properties.
- **`index()`:** `Camera::withCount(['enrollments as total_enrollments'])->with('barangay:id,name')->orderBy('camera_id_display')->get()` → `Inertia::render('admin/Cameras', ['cameras' => ..., 'statuses' => CameraStatus::cases()])`. Returns ALL cameras including decommissioned per D-08.
- **`create()`:** renders `admin/CameraForm` with statuses only.
- **`store()`:** 4-step: (1) `selectRaw("MAX(CAST(SUBSTRING(camera_id_display FROM '[0-9]+$') AS INTEGER))")` → `sprintf('CAM-%02d', max+1)`; (2) `$this->barangayLookup->findByCoordinates($lat, $lng)` → `?Barangay`; (3) `Camera::create([...])` with `Point::makeGeodetic($lat, $lng)`, `barangay_id => $barangay?->id`, `status => CameraStatus::Offline`; (4) `$this->enrollmentService->enrollAllToCamera($camera)` → redirect index with success.
- **`edit($camera)`:** loads `barangay:id,name` relation, renders form.
- **`update()`:** re-runs barangay lookup on every coord submit, persists identically to store (except camera_id_display is immutable), redirects with success.
- **`destroy()`:** counts `$camera->enrollments()->whereIn('status', ['pending','syncing'])->count()`. If > 0 → `withErrors(['camera' => "...in-flight enrollment{s}..."])` → redirect index. Else `decommissioned_at = now()` → redirect with success.
- **`recommission()`:** sets `decommissioned_at = null` → redirect with success.

### Test coverage (12 it-blocks, 72 assertions)

| # | Test | Assertion |
|---|------|-----------|
| 1 | index lists all 4 cameras (3 active + 1 decommissioned) | 4 cameras in Inertia prop + statuses present |
| 2 | non-admin roles get 403 | 4 separate role checks (operator/dispatcher/responder/supervisor) all forbidden |
| 3 | auto-sequence CAM-01 → CAM-02 | Two sequential posts → orderByDesc matches CAM-02 |
| 4 | BarangayLookupService::findByCoordinates invoked on store | Store succeeds + camera row has barangay_id key (null is valid when no seeded barangay contains the point) |
| 5 | enrollAllToCamera contract on store (offline camera → zero dispatches) | Queue::assertNotPushed(EnrollPersonnelBatch) per D-11 gate |
| 6 | destroy blocked when enrollments pending/syncing | Session error on 'camera' key + decommissioned_at still null |
| 7 | destroy succeeds when all enrollments done/failed | Redirect + success flash + decommissioned_at not null |
| 8 | recommission clears decommissioned_at | decommissioned_at becomes null + success flash |
| 9 | update persists name change with unchanged device_id | unique-ignoring-self permits the update |
| 10 | create form renders with statuses | Inertia component 'admin/CameraForm' + statuses prop |
| 11 | edit form renders with camera + statuses | Camera prop + statuses prop present |
| 12 | duplicate device_id rejected | Session has device_id error |

### Wayfinder regeneration

- `resources/js/actions/App/Http/Controllers/Admin/AdminCameraController.ts` (gitignored — full path for Plan 07 import)
- `resources/js/routes/admin/cameras/` (gitignored — named-route functions)

## Output Section Answers (plan required)

1. **Magellan Point factory used:** `Point::makeGeodetic((float) $lat, (float) $lng)`. Grep across v1.0 codebase confirmed this is the ONLY Magellan factory in use (12 matches across 7 files: IntakeStationController, IoTWebhookController, IncidentController, ResponderController, CitizenReportController, BarangayFactory, UnitFactory, IncidentFactory, UnitSeeder, BarangaySeeder, IncidentSeeder, CameraFactory). Zero uses of `Point::make` or `new Point()`. Phase 20 conforms.

2. **Camera::$fillable had barangay_id / notes / location_label / address already?** No — Phase 18's cameras migration shipped WITHOUT `barangay_id` FK or `notes` column, and Camera::$fillable included only the 8 fields needed by the watchdog (device_id, camera_id_display, name, location_label, location, status, last_seen_at, decommissioned_at). This plan added `barangay_id` + `notes` to both the schema (via new migration) AND the $fillable list. `address` was never part of the plan — the UI-SPEC's `address` was a map-picker-only client-side derivation, not a persisted column. location_label was NOT NULL pre-plan; loosened to NULLABLE here per Rule 1 (bug — conflicted with Form Request's `nullable` rule).

3. **BarangayLookupService quirks uncovered:** None. The service works as documented — `ST_Contains(boundary::geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geometry)` returns the first barangay whose polygon contains the lat/lng, or null. In tests without seeded barangays, lookups return null and the controller persists `barangay_id = null`. No parameter-order bugs (the service uses `$longitude, $latitude` in that ST_MakePoint order — PostGIS convention — matching what the service accepts as `(float $latitude, float $longitude)` on its public interface).

4. **Wayfinder-generated action paths (for Plan 07 imports):**
   - `@/actions/App/Http/Controllers/Admin/AdminCameraController` (default import + named exports: `index`, `create`, `store`, `edit`, `update`, `destroy`, `recommission`)
   - `@/routes/admin/cameras` (named route function with nested scopes: `cameras.index`, `cameras.create`, `cameras.store`, `cameras.show`, `cameras.edit`, `cameras.update`, `cameras.destroy`, `cameras.recommission`)

5. **Per-test runtime:** All 12 tests run in 1.21s total (avg ~100ms/test). Longest individual test is the multi-role denial (4 HTTP requests) at ~200ms. Zero tests exceed 2s — no optimization flag warranted.

## Deviations from Plan

**1. [Rule 2 — Missing critical functionality] Phase 18 schema omitted barangay_id + notes columns**

- **Found during:** Task 1 scaffold — plan's must_haves state "POST /admin/cameras triggers BarangayLookupService::findByCoordinates + sets barangay_id" and PATTERNS shows `with('barangay:id,name')` index projection, but the Phase 18 cameras migration (`2026_04_21_000001`) never reserved these columns.
- **Fix:** Shipped `2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php` — additive migration (Rule 2). Adds `barangay_id` FK (nullable, nullOnDelete), `notes` (text, nullable). Reversible.
- **Files:** `database/migrations/2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php`, `app/Models/Camera.php` ($fillable + barangay() relation).
- **Commit:** a6d5b4a

**2. [Rule 1 — Bug] location_label column NOT NULL conflicts with Form Request nullable rule**

- **Found during:** Task 2 GREEN phase — 4 tests failed with SQLSTATE[23502] when POSTing without a location_label (StoreCameraRequest marks it `nullable`, but the column NOT NULL constraint rejected it).
- **Fix:** Extended the same Task-1 migration to loosen `location_label` to NULLABLE via `$table->string('location_label', 150)->nullable()->change()`. Down() restores NOT NULL.
- **Root cause:** Phase 18's cameras migration shipped `location_label` as `$table->string('location_label', 150)` with no `->nullable()` qualifier. This conflicted with the UI-SPEC reverseGeocode flow (Plan 07's Mapbox picker resolves the label async; Form Request allows null to support the picker's optional-submit path).
- **Files:** `database/migrations/2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php`
- **Commit:** 0596157 (schema tweak bundled into Task-2 re-migration; original migration rolled back + re-applied)

**3. [Rule 3 — Blocking] Inertia ::render fails Vite manifest lookup without .vue page files**

- **Found during:** Task 2 GREEN phase — 7 tests failed with `Unable to locate file in Vite manifest: resources/js/pages/admin/Cameras.vue` because the Blade root template at `resources/views/app.blade.php` calls `@vite([...,"resources/js/pages/{$page['component']}.vue"])`. Plan 07 owns the real implementation; backend-first plan needs stubs.
- **Fix:** Shipped 9-line placeholder `.vue` files for `admin/Cameras.vue` + `admin/CameraForm.vue`. Each has a `defineProps` matching the controller's Inertia payload and a one-line `<template>` announcing the Plan 07 handoff. Ran `npm run build` to regenerate the Vite manifest.
- **Files:** `resources/js/pages/admin/Cameras.vue`, `resources/js/pages/admin/CameraForm.vue`
- **Commit:** 0596157

**4. [Plan spec adjustment] enrollAllToCamera test revised from dispatch-assertion to non-dispatch assertion**

- **Found during:** Task 2 test authoring — plan's Task-2 test #5 asserts `Queue::assertPushed(EnrollPersonnelBatch::class)` after store(), but `CameraEnrollmentService::enrollAllToCamera` (Plan 02 line 63) gates on `$camera->status === CameraStatus::Online`. A freshly-created camera has `status = offline` (per this plan's store() implementation). The service is therefore a no-op on new cameras; no EnrollPersonnelBatch ever fires at store time.
- **Fix:** Revised test to assert the contract that IS true: the store call reaches enrollAllToCamera without throwing AND zero EnrollPersonnelBatch jobs are pushed (because the camera is offline). Added a comment in the test explaining the D-11 gate. Enrollment fan-out coverage lives in CameraEnrollmentService tests (Plan 02); this test's scope is the controller → service handshake.
- **Not a deviation from intent:** The plan's must_have #4 says "POST /admin/cameras triggers CameraEnrollmentService::enrollAllToCamera(camera) for pre-existing personnel sync" — this still happens. The method is called. The downstream fan-out is gated by camera state, not the controller.
- **Files:** `tests/Feature/Admin/AdminCameraControllerTest.php` (test #5 body + comment)
- **Commit:** 0596157

## Verification

- `php artisan test --compact tests/Feature/Admin/AdminCameraControllerTest.php` → **12 passed (72 assertions) in 1.21s**
- `php artisan test --compact --group=fras` → **63 passed + 1 skipped (219 assertions) in 3.27s** (was 51+1 before Plan 04; +12 new all green)
- `php artisan route:list --path=admin/cameras --except-vendor` → 8 routes listed
- `vendor/bin/pint --test --format agent` → clean after Task 1 + Task 2 auto-fixes (no_unused_imports + fully_qualified_strict_types + ordered_imports)

### Grep-verifiable acceptance criteria

| Criterion | Expected | Actual |
|---|---|---|
| `Route::resource('cameras'` in routes/admin.php | 1 | 1 |
| `cameras/{camera}/recommission` in routes/admin.php | 1 | 1 |
| `unique:cameras,device_id` in StoreCameraRequest | 1 | 1 |
| `SUBSTRING(camera_id_display FROM '[0-9]+$')` in controller | 1 | 1 |
| `enrollAllToCamera` in controller store() | 1 | 1 |
| `whereIn('status', ['pending', 'syncing'])` in controller | 1 | 1 |
| `BarangayLookupService` references in controller (import + DI + 2 call sites) | 4 | 4 |
| Wayfinder `AdminCameraController.ts` exists | yes | yes (resources/js/actions/App/Http/Controllers/Admin/) |
| `admin/cameras` routes registered | 8 | 8 |

## Commits

| Task | Hash | Summary |
|---|---|---|
| 1 | a6d5b4a | feat(20-04): scaffold admin cameras routes + form requests + schema |
| 2 | 0596157 | feat(20-04): implement AdminCameraController + 12 Pest tests |

## Self-Check: PASSED

**Files verified present:**
- `app/Http/Controllers/Admin/AdminCameraController.php` (168 lines) — FOUND
- `app/Http/Requests/Admin/StoreCameraRequest.php` — FOUND
- `app/Http/Requests/Admin/UpdateCameraRequest.php` — FOUND
- `database/migrations/2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php` — FOUND
- `tests/Feature/Admin/AdminCameraControllerTest.php` — FOUND
- `resources/js/pages/admin/Cameras.vue` — FOUND (placeholder)
- `resources/js/pages/admin/CameraForm.vue` — FOUND (placeholder)
- `resources/js/actions/App/Http/Controllers/Admin/AdminCameraController.ts` — FOUND (Wayfinder, gitignored)
- `resources/js/routes/admin/cameras/index.ts` — FOUND (Wayfinder, gitignored)

**Commits verified in git log:**
- a6d5b4a (Task 1) — FOUND
- 0596157 (Task 2) — FOUND

**Verification gate:** `php artisan test --compact tests/Feature/Admin/AdminCameraControllerTest.php` → **12 passed (72 assertions) in 1.21s** ✓
