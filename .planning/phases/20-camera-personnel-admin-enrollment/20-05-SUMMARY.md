---
phase: 20-camera-personnel-admin-enrollment
plan: 05
subsystem: fras-admin
tags: [wave-2, admin, personnel, photo-pipeline, signed-url, broadcast-auth, dpa]
requirements:
  - PERSONNEL-01
  - PERSONNEL-02
  - PERSONNEL-03
  - PERSONNEL-05
dependency_graph:
  requires:
    - plan-20-01 (Personnel.photo_access_token column + photo_url accessor + channels)
    - plan-20-02 (FrasPhotoProcessor::process + CameraEnrollmentService)
    - plan-20-03 (EnrollPersonnelBatch job + PersonnelObserver registered)
    - plan-20-04 (routes/admin.php cameras block — APPENDED to, never rewritten)
  provides:
    - App\Http\Controllers\Admin\AdminPersonnelController (7 methods)
    - App\Http\Controllers\Admin\AdminPersonnelPhotoController (signed-URL photo stream)
    - App\Http\Controllers\Admin\EnrollmentController (retry + resyncAll)
    - App\Http\Controllers\Fras\FrasPhotoAccessController (public token-gated photo)
    - App\Http\Requests\Admin\StorePersonnelRequest + UpdatePersonnelRequest
    - routes/fras.php (new file — D-22 role gate)
    - 11 admin.personnel.* routes registered (7 resource + recommission + retry + resync + photo)
    - 1 public route /fras/photo/{token} named fras.photo.show
    - resources/js/actions/App/Http/Controllers/Admin/AdminPersonnelController.ts (Wayfinder — 8 exports)
    - resources/js/actions/App/Http/Controllers/Admin/AdminPersonnelPhotoController.ts (Wayfinder — 1 export)
  affects:
    - plan-20-06 (expire-sweep command will call CameraEnrollmentService::deleteFromAllCameras on decommission — already wired here)
    - plan-20-07 (Plan 07 Personnel.vue + PersonnelForm.vue replace the stubs shipped here; will consume photo_signed_url prop + enrollment_rows + admin.personnel.* Wayfinder actions)
    - tests/Feature/Fras/CameraEnrollmentServiceTest.php::"upsertBatch includes photo_url in MQTT payload" — previously skipped, now auto-unskipped by Route::has('fras.photo.show') guard
tech-stack:
  added: []
  patterns:
    - Two-namespace photo URL scheme (D-20..D-23): public /fras/photo/{token} for cameras (UUID token = access boundary) + signed /admin/personnel/{id}/photo for operators (HMAC + 5-min TTL + role:operator,supervisor,admin)
    - Token rotation on every photo replace (Str::uuid() in both store and update paths) — invalidates prior URLs so cache-lagged cameras cannot pull stale bytes after lifecycle change
    - custom_id = str_replace('-', '', (string) $personnel->id) — deterministic 32-char camera identifier derived from the UUID primary key (FRAS firmware contract)
    - Soft-decommission on destroy (D-33) — $personnel->decommissioned_at = now() + $service->deleteFromAllCameras($personnel) (explicit MQTT publish, not via observer deleted hook, since the row persists)
    - routes/fras.php as a second withRouting group sharing prefix('admin')/name('admin.') but with role:operator,supervisor,admin — broadens the role gate without moving the URL/name surface
    - Controller delegates re-enrollment to PersonnelObserver's wasChanged(['photo_hash', 'category']) gate; store()/update() just mutate the model
    - EnrollmentController.retry marks the row Pending + dispatches EnrollPersonnelBatch directly (bypasses the observer because only status changed)
    - EnrollmentController.resyncAll delegates to CameraEnrollmentService::enrollPersonnel (per-camera upsert + batch dispatch)
    - Public photo endpoint revokes automatically once every enrollment has settled (whereIn('status', ['pending','syncing'])->exists() gate)
    - Every /fras/photo/{token} hit logs to Log::channel('mqtt') with personnel_id + ip + ua for DPA audit preamble (Phase 22 formalizes the access_log table)
key-files:
  created:
    - app/Http/Controllers/Admin/AdminPersonnelController.php
    - app/Http/Controllers/Admin/AdminPersonnelPhotoController.php
    - app/Http/Controllers/Admin/EnrollmentController.php
    - app/Http/Controllers/Fras/FrasPhotoAccessController.php
    - app/Http/Requests/Admin/StorePersonnelRequest.php
    - app/Http/Requests/Admin/UpdatePersonnelRequest.php
    - routes/fras.php
    - resources/js/pages/admin/Personnel.vue (placeholder — Plan 07 replaces)
    - resources/js/pages/admin/PersonnelForm.vue (placeholder — Plan 07 replaces)
    - tests/Feature/Admin/AdminPersonnelControllerTest.php
    - tests/Feature/Admin/AdminPersonnelPhotoControllerTest.php
    - tests/Feature/Fras/FrasPhotoAccessControllerTest.php
    - tests/Feature/Fras/BroadcastAuthorizationTest.php
  modified:
    - bootstrap/app.php (added second withRouting->then group for routes/fras.php with role:operator,supervisor,admin)
    - routes/admin.php (appended Route::resource personnel + recommission + enrollments.retry + enrollments.resync BELOW Plan 04's cameras block)
    - routes/web.php (added public /fras/photo/{token} route named fras.photo.show)
decisions:
  - "20-05-D1: routes/fras.php housed admin.personnel.photo instead of routes/admin.php so D-22's broader gate (role:operator,supervisor,admin) does not contaminate the rest of routes/admin.php (which stays role:admin only). The URL + name prefix is preserved (admin/ and admin.) via a parallel withRouting->then group, keeping operator-facing URLs stable while broadening the role gate."
  - "20-05-D2: Token rotation happens inside the controller's photo-upload branch (Str::uuid()->toString()), NOT via FormRequest input. StorePersonnelRequest + UpdatePersonnelRequest rules() whitelists do NOT include photo_access_token — mass-assignment of the token is therefore blocked at the validation boundary (T-20-05-01)."
  - "20-05-D3: update() deletes the old photo only when the new path differs from the old path. FrasPhotoProcessor writes to personnel/{id}.jpg deterministically (same Personnel UUID → same path), so on photo replace the old file is already overwritten in place by process(); calling delete() on the identical path would wipe the new photo. Added an inline guard (if old !== new → delete). This is a subtle deviation from the plan's literal Step 4 (unconditional delete of old path) — documented in Deviations."
  - "20-05-D4: destroy() calls $service->deleteFromAllCameras($personnel) explicitly instead of relying on the PersonnelObserver's deleted hook. The row is soft-decommissioned (update of decommissioned_at), never hard-deleted, so the observer's deleted hook NEVER fires on this path. Without the explicit call, cameras would retain the face DB entry until the next enrollment sync. Matches plan behavior spec."
  - "20-05-D5: photo_access_token DB column is typed uuid (not string) — Phase 20-01 migration uses \$table->uuid('photo_access_token'). A test seed with 'old-token-xxx' literal failed with PostgreSQL 22P02. Fixed by using Str::uuid()->toString() for the old-token seed. Recorded as test-fixture fix during GREEN phase, not a plan spec change."
  - "20-05-D6: Task 3 broadcast-auth role matrix dropped the Citizen case entirely — UserRole enum has 5 cases (Admin/Dispatcher/Operator/Responder/Supervisor), no Citizen. Matrix reduces to 4 allowed + 1 denied on fras.cameras (responder only denied; supervisor/admin/operator/dispatcher all allowed), and 2 allowed + 3 denied on fras.enrollments. Total: 10 assertions."
  - "20-05-D7: update() test uses a real UUID ('oldToken = Str::uuid()->toString()' in-body) instead of the plan's 'old-token-xxx' literal — the photo_access_token column is typed uuid in PostgreSQL. Same test-fixture correction as D5 applied to AdminPersonnelControllerTest."
metrics:
  duration: 7min
  completed_date: 2026-04-21
  tasks_completed: 3
  files_created: 13
  files_modified: 3
  tests_added: 29 (48 + 10 + 6 = 64 assertions)
  fras_group_before: 63 passed + 1 skipped (after Plan 04)
  fras_group_after: 93 passed + 0 skipped (+29 new + 1 unskipped)
  commits: 3
---

# Phase 20 Plan 05: Personnel Admin + Two-Namespace Photo URL Scheme — Summary

Closed the personnel admin surface and both photo URL namespaces. AdminPersonnelController ships 7 methods (CRUD + recommission + photo upload pipeline via FrasPhotoProcessor, with custom_id derivation on create and photo_access_token rotation on every photo replace). Two photo controllers: public token-gated `/fras/photo/{token}` (no auth — the UUID token IS the boundary, with enrollment-state revocation gate), and operator-facing `/admin/personnel/{id}/photo` served via 5-minute signed URL through the new `routes/fras.php` group at `role:operator,supervisor,admin` per D-22. Per-camera retry + resync-all enrollment endpoints round out the action surface. 29 new Pest tests across 4 files (64 assertions, all green in 6s).

Previously-skipped cross-plan test `CameraEnrollmentServiceTest::"upsertBatch includes photo_url in MQTT payload"` auto-unskipped once `fras.photo.show` was registered — now runs and passes.

## Requirements Addressed

- **PERSONNEL-01** — Admin CRUD for personnel records at `/admin/personnel/*` with photo upload lifecycle (create/edit/replace/decommission/recommission)
- **PERSONNEL-02** — Full lifecycle test coverage (create → photo upload → edit → photo replace → destroy → recommission) confirms end-to-end admin flow
- **PERSONNEL-03** — Two photo URL namespaces live: public `/fras/photo/{token}` for cameras (token-gated + enrollment-state revoked) + signed `/admin/personnel/{id}/photo` for operators (5-min TTL + role:operator,supervisor,admin gate)
- **PERSONNEL-05** — Per-camera retry (`POST /admin/personnel/{id}/enrollments/{camera}/retry`) + resync-all (`POST /admin/personnel/{id}/enrollments/resync`) endpoints dispatch EnrollPersonnelBatch correctly

## Task 1: Public photo route + FrasPhotoAccessController + 5 revocation tests (commit e3bdd6f)

- `FrasPhotoAccessController::show(token, request)` — looks up Personnel by token, gates on `enrollments()->whereIn('status', ['pending','syncing'])->exists()`. 404 on unknown token OR all-settled enrollments (revocation) OR rotated token. Streams from `Storage::disk('fras_photos')` on success.
- DPA preamble: every hit writes `Log::channel('mqtt')->info('fras.photo.access', [personnel_id, ip, ua])` — Phase 22 replaces with the formal access_log table, this is the interim record.
- `routes/web.php`: new `Route::get('/fras/photo/{token}', ...)` above the auth middleware group, named `fras.photo.show` (consumed by `Personnel::photo_url` accessor from Plan 01).
- 5 tests: pending enrollment → 200, syncing → 200, all-done → 404 (revocation), unknown token → 404, rotated token → old 404 + new 200.

## Task 2: 4 controllers + 2 form requests + routes + bootstrap wiring + 14 tests (commit f336eb2)

### Controllers

- **AdminPersonnelController (7 methods):**
  - `index` — personnel.withCount(total/done/failed enrollments).orderBy('name') → Inertia `admin/Personnel` page
  - `create` — empty form page with categories prop
  - `store(StorePersonnelRequest)` — creates personnel with placeholder `photo_access_token` (Str::uuid()), derives `custom_id = str_replace('-', '', $personnel->id)` (32-char), then if photo present, runs `FrasPhotoProcessor::process` → sets `photo_path` + `photo_hash` → PersonnelObserver fires on the second update (photo_hash changed) and enrolls across every online camera
  - `edit($personnel)` — loads `enrollments.camera:id,camera_id_display,name`, emits `photo_signed_url` via `URL::temporarySignedRoute('admin.personnel.photo', now()->addMinutes(5), ...)` plus `enrollment_rows` array for the UI
  - `update(UpdatePersonnelRequest, $personnel)` — on photo replace: processes new photo, rotates `photo_access_token` (Str::uuid()), deletes old photo only if path differs from new (guards against same-path overwrite)
  - `destroy($personnel)` — D-33 soft-decommission: sets `decommissioned_at = now()` then calls `$service->deleteFromAllCameras($personnel)` explicitly (observer's deleted hook never fires on soft-decommission paths)
  - `recommission($personnel)` — clears `decommissioned_at`
- **AdminPersonnelPhotoController::show** — route-level middleware chain does all auth (signed + role:operator,supervisor,admin + auth/verified/web). Controller just 404s on missing `photo_path`, otherwise streams `personnel/{id}.jpg` from `fras_photos` disk.
- **EnrollmentController::retry($personnel, $camera)** — finds the specific enrollment row via `firstOrFail`, sets status=Pending + clears last_error, dispatches `EnrollPersonnelBatch::dispatch($camera, [$personnel->id])->onQueue('fras')`.
- **EnrollmentController::resyncAll($personnel)** — delegates to `CameraEnrollmentService::enrollPersonnel($personnel)` (per-camera pending upsert + EnrollPersonnelBatch per camera).

### Form Requests

- `StorePersonnelRequest`: photo required|file|mimes:jpeg,jpg|max:1024 + enum validation for category + name/consent_basis required. Whitelist explicitly omits `photo_access_token` (T-20-05-01 mitigation).
- `UpdatePersonnelRequest`: photo `nullable` (otherwise identical to Store). Same whitelist.

### Route + bootstrap wiring

- `routes/fras.php` (NEW) — 1 route: `GET personnel/{personnel}/photo` with `signed` middleware, name `personnel.photo`. URL/name prefix inherited from bootstrap group → resolves to `admin/personnel/{id}/photo` / `admin.personnel.photo`.
- `bootstrap/app.php` — second withRouting->then group registered with `['web','auth','verified','role:operator,supervisor,admin']`, `prefix('admin')`, `name('admin.')`, `group(base_path('routes/fras.php'))`. Sits below the existing role:admin admin.php group.
- `routes/admin.php` — imports extended (AdminPersonnelController, EnrollmentController). 4 lines APPENDED below Plan 04's cameras block:
  - `Route::resource('personnel', AdminPersonnelController::class)`
  - `Route::post('personnel/{personnel}/recommission', ...)`
  - `Route::post('personnel/{personnel}/enrollments/{camera}/retry', ...)`
  - `Route::post('personnel/{personnel}/enrollments/resync', ...)`
- Photo route deliberately NOT in admin.php (per D-22 — would inherit role:admin which is too narrow).

### Tests (14 it-blocks, 48 assertions)

**AdminPersonnelControllerTest (9 tests):**

| # | Test | Assertion |
|---|------|-----------|
| 1 | index lists all 3 personnel | component=admin/Personnel + personnel has 3 |
| 2 | denies operator/dispatcher/supervisor/responder | 4 separate 403s |
| 3 | store sets custom_id (32-char, UUID-no-dashes) + photo_access_token + hash | dashless custom_id + 32-length + token non-null + hash non-null + photo exists on disk |
| 4 | update rotates photo_access_token on photo replace | old != new after refresh |
| 5 | rejects 2MB photo | session error on photo |
| 6 | destroy soft-decommissions + publishes DeletePersons MQTT | decommissioned_at set + row still exists + MQTT::publish called |
| 7 | recommission clears decommissioned_at | null after action |
| 8 | retry sets enrollment pending + dispatches EnrollPersonnelBatch | status=Pending + Queue::assertPushed matching camera |
| 9 | resync dispatches per active camera (3 online) | Queue::assertPushed(3) |

**AdminPersonnelPhotoControllerTest (5 tests):**

| # | Test | Assertion |
|---|------|-----------|
| 1 | operator serves valid signed URL within 5-min TTL | 200 + Content-Type: image/jpeg |
| 2 | expired signed URL returns 403 | Carbon::setTestNow(+6min) → 403 |
| 3 | tampered signature returns 403 | regex-replaced signature → 403 |
| 4 | operator/supervisor/admin allowed | 3 roles × 200 |
| 5 | responder/dispatcher denied | 2 roles × 403 |

## Task 3: Broadcast authorization role matrix (commit 27a5b91)

- `tests/Feature/Fras/BroadcastAuthorizationTest.php` — 10 it-blocks (10 assertions):
  - **fras.cameras** allows operator/dispatcher/supervisor/admin (4); denies responder (1)
  - **fras.enrollments** allows supervisor/admin (2); denies operator/dispatcher/responder (3)
- `authAttempt(UserRole, channelName)` helper factory-creates a user, actingAs, posts `/broadcasting/auth` with channel_name + socket_id, returns raw TestResponse for flexible status-code assertions.

## Output Section Answers (plan required)

1. **UserRole enum cases found:** Admin, Dispatcher, Operator, Responder, Supervisor — exactly 5 cases, NO Citizen role. Broadcast auth matrix adjusted accordingly: Task 3 dropped the Citizen row entirely from both channel tests.

2. **admin.personnel.photo route gated per D-22:** Confirmed. `php artisan route:list --name=admin.personnel.photo -v` prints middleware chain `[web, auth, verified, role:operator,supervisor,admin, signed]`. Route lives in `routes/fras.php`, registered via a parallel withRouting->then group in `bootstrap/app.php`. Zero references to `personnel.photo` in `routes/admin.php` (rg confirmed — the photo route is intentionally absent from the admin.php role:admin scope).

3. **Observed photo-fixture file sizes in CI:** UploadedFile::fake()->image('face.jpg', 800, 600) produces ~1KB synthetic JPEG per Laravel's fake factory (not a real face — an arbitrary PNG-like header stream re-encoded by Intervention). The 2MB oversize test uses `->size(2048)` which sets a 2048KB (2MB) file via the ->size() hint, triggering FormRequest's `max:1024` rejection. No test exceeded the 1MB processor cap on the happy path — Intervention's scaleDown-then-encode pipeline produced outputs <100KB for the 800×600 fakes.

4. **Wayfinder-generated admin/personnel action exports:**
   - `resources/js/actions/App/Http/Controllers/Admin/AdminPersonnelController.ts` — 8 exports (default + 7 named: index, create, store, edit, update, destroy, recommission)
   - `resources/js/actions/App/Http/Controllers/Admin/AdminPersonnelPhotoController.ts` — 1 export (default)
   - Note: EnrollmentController generates separately; its retry + resyncAll actions are under the EnrollmentController.ts action file.

5. **Pre-existing tests in tests/Feature/Broadcasting/ conflict with new BroadcastAuthorizationTest:** None. Broadcasting/ contains only 6 snapshot tests (ChecklistUpdated/IncidentCreated/IncidentTriaged/ResourceRequested/UnitAssigned/UnitStatusChanged) — none of them exercise channel auth. BroadcastAuthorizationTest is newly-created under tests/Feature/Fras/ (not Broadcasting/) — colocates with other fras-group tests per Plan 18-06's precedent (EnumCheckParityTest placement).

6. **Wave sequencing approach:** Plan 05 stayed at `wave: 2` with `depends_on: [01, 02, 03, 04]`. Executor confirmed Plan 04's cameras block was already landed (`rg "Route::resource\('cameras'" routes/admin.php` returned 1 hit) BEFORE editing routes/admin.php. Personnel routes APPENDED below the cameras block with 2 new imports added alphabetically; zero existing cameras routes were touched. No wave renumbers cascaded through 20-06/20-07/20-08/VALIDATION/ROADMAP.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 — Bug] Plan's test fixture used non-UUID literal `'old-token-xxx'` for photo_access_token**

- **Found during:** Task 2 first test run — `AdminPersonnelControllerTest::"rotates photo_access_token on photo replace"` failed with PostgreSQL 22P02 `invalid input syntax for type uuid: "old-token-xxx"`.
- **Root cause:** The photo_access_token column is typed `uuid` (not varchar) per Plan 01's migration `2026_04_22_000001_add_photo_access_token_to_personnel_table.php` line 12. The plan's literal test fixture was written assuming a varchar column.
- **Fix:** Replaced `'old-token-xxx'` with `Str::uuid()->toString()` captured into a local `$oldToken` variable before Personnel::factory()->create — same semantic (different-from-new token) but UUID-valid.
- **Files:** `tests/Feature/Admin/AdminPersonnelControllerTest.php`
- **Commit:** f336eb2

**2. [Rule 1 — Bug] Unconditional `$this->photoProcessor->delete($oldPath)` would wipe the new photo when upload path is deterministic**

- **Found during:** GREEN phase — recognized before coding from FrasPhotoProcessor::process writing to `personnel/{personnel->id}.jpg` (deterministic by UUID). Old and new paths are identical for any replace, so the new upload overwrites the old file in place; calling delete() on the same path would wipe the freshly-written bytes.
- **Fix:** Added an inline guard in AdminPersonnelController::update:
  ```php
  if ($oldPath !== null && $oldPath !== $result['photo_path']) {
      $this->photoProcessor->delete($oldPath);
  }
  ```
  Preserves the intent (clean up old file on path change) without regressing the common same-path case.
- **Why not raised as Rule 4:** The plan's Step 4 literal Python-style "old_path → delete, process new" was a descriptive summary. The actual invariant (don't delete the file you just wrote) is implicit and corrected inline. Tests cover both the create path (no old photo) and the update-with-replace path (same-path rewrite) with passing assertions.
- **Files:** `app/Http/Controllers/Admin/AdminPersonnelController.php`
- **Commit:** f336eb2

### Non-deviations worth noting

**A. Plan's UserRole::Citizen reference in Task 3 test** — The plan's Task 3 Step 1 includes `UserRole::Citizen` in both the fras.cameras denied list and the fras.enrollments denied list. UserRole enum has NO Citizen case (5 cases only). Plan explicitly said "adjust the list — only include cases that exist; skip others." Dropped Citizen rows from both channel matrices. 10 assertions total instead of the plan's implied 12.

**B. CameraEnrollmentServiceTest skipped → running** — Plan 02 shipped `upsertBatch includes photo_url in MQTT payload` with a `Route::has('fras.photo.show')` guard that markTestSkipped until Plan 05 registered the route. No code change needed in that test; the guard auto-unskipped once Task 1 landed. Verified passing via `--filter` run.

## Verification

- `php artisan test --compact tests/Feature/Fras/FrasPhotoAccessControllerTest.php` → **5 passed (6 assertions) in 0.93s**
- `php artisan test --compact tests/Feature/Admin/AdminPersonnelControllerTest.php tests/Feature/Admin/AdminPersonnelPhotoControllerTest.php` → **14 passed (48 assertions) in 1.59s**
- `php artisan test --compact tests/Feature/Fras/BroadcastAuthorizationTest.php` → **10 passed (10 assertions) in 1.01s**
- `php artisan test --compact --group=fras` → **93 passed (285 assertions) in 5.55s** (was 63+1 before plan; +29 new + 1 previously-skipped unskipped)
- `php artisan route:list --path=admin/personnel --except-vendor` → **11 routes** (index/store/create/show/update/destroy/edit/enrollment.resync/enrollment.retry/photo/recommission)
- `php artisan route:list --name=admin.personnel.photo -v` → **middleware: web, auth, verified, role:operator,supervisor,admin, signed** ✓
- `php artisan route:list --path=fras/photo --except-vendor` → 1 route (fras.photo.show)
- `vendor/bin/pint --dirty --format agent` → clean after each task

### Grep-verifiable acceptance criteria

| Criterion | Expected | Actual |
|---|---|---|
| `str_replace('-', '', (string) $personnel->id)` in AdminPersonnelController | 1 | 1 (in store) |
| `Str::uuid()->toString()` in AdminPersonnelController | ≥ 2 | 2 (store + update rotation) |
| `URL::temporarySignedRoute('admin.personnel.photo'` in AdminPersonnelController | 1 | 1 (in edit; multi-line) |
| `Route::resource('personnel'` in routes/admin.php | 1 | 1 |
| `'personnel.enrollment.retry'` in routes/admin.php | 1 | 1 |
| `'personnel.photo'` in routes/fras.php | 1 | 1 |
| `'personnel.photo'` in routes/admin.php (must NOT be here — D-22) | 0 | 0 |
| `role:operator,supervisor,admin` in bootstrap/app.php | 1 | 1 |
| `base_path('routes/fras.php')` in bootstrap/app.php | 1 | 1 |
| `whereIn('status', ['pending', 'syncing'])` in FrasPhotoAccessController | 1 | 1 |
| `Log::channel('mqtt')` in FrasPhotoAccessController | 1 | 1 |
| `'fras.photo.show'` in routes/web.php | 1 | 1 |
| `admin/personnel` routes | ≥ 10 | 11 |
| Wayfinder AdminPersonnelController.ts exists | yes | yes (8 exports) |
| Wayfinder AdminPersonnelPhotoController.ts exists | yes | yes (1 export) |

## Commits

| Task | Hash | Summary |
|---|---|---|
| 1 | e3bdd6f | feat(20-05): add public token-gated fras photo access route |
| 2 | f336eb2 | feat(20-05): admin personnel crud + signed-url photo controller + enrollment retry/resync |
| 3 | 27a5b91 | test(20-05): broadcast authorization role matrix for fras channels |

## Self-Check: PASSED

**Files verified present:**
- `app/Http/Controllers/Admin/AdminPersonnelController.php` — FOUND
- `app/Http/Controllers/Admin/AdminPersonnelPhotoController.php` — FOUND
- `app/Http/Controllers/Admin/EnrollmentController.php` — FOUND
- `app/Http/Controllers/Fras/FrasPhotoAccessController.php` — FOUND
- `app/Http/Requests/Admin/StorePersonnelRequest.php` — FOUND
- `app/Http/Requests/Admin/UpdatePersonnelRequest.php` — FOUND
- `routes/fras.php` — FOUND
- `resources/js/pages/admin/Personnel.vue` — FOUND (placeholder)
- `resources/js/pages/admin/PersonnelForm.vue` — FOUND (placeholder)
- `resources/js/actions/App/Http/Controllers/Admin/AdminPersonnelController.ts` — FOUND (Wayfinder)
- `resources/js/actions/App/Http/Controllers/Admin/AdminPersonnelPhotoController.ts` — FOUND (Wayfinder)
- `tests/Feature/Admin/AdminPersonnelControllerTest.php` — FOUND
- `tests/Feature/Admin/AdminPersonnelPhotoControllerTest.php` — FOUND
- `tests/Feature/Fras/FrasPhotoAccessControllerTest.php` — FOUND
- `tests/Feature/Fras/BroadcastAuthorizationTest.php` — FOUND

**Commits verified in git log:**
- e3bdd6f (Task 1) — FOUND
- f336eb2 (Task 2) — FOUND
- 27a5b91 (Task 3) — FOUND

**Verification gates (all green):**
- `php artisan test --compact tests/Feature/Fras/FrasPhotoAccessControllerTest.php` → 5/5 pass
- `php artisan test --compact tests/Feature/Admin/AdminPersonnelControllerTest.php tests/Feature/Admin/AdminPersonnelPhotoControllerTest.php` → 14/14 pass
- `php artisan test --compact tests/Feature/Fras/BroadcastAuthorizationTest.php` → 10/10 pass
- `php artisan test --compact --group=fras` → 93/93 pass (was 63+1 skipped; +29 new + 1 unskipped)
- `php artisan test --compact --filter="upsertBatch includes photo_url"` → 1/1 pass (previously-skipped cross-plan test now runs)
