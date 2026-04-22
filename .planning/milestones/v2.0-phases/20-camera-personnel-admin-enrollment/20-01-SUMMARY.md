---
phase: 20-camera-personnel-admin-enrollment
plan: 01
subsystem: fras-infrastructure
tags: [wave-0, intervention-image, broadcasting, migration, config, docs]
requires:
  - Phase 18 schema (cameras, personnel, camera_enrollments, recognition_events tables)
  - Phase 17 Laravel 13 upgrade
provides:
  - Intervention/Image v4 Laravel facade (Image + JpegEncoder)
  - config/fras.php: cameras.degraded_gap_s=30, offline_gap_s=90; enrollment.batch_size=10, ack_timeout_minutes=5; photo.max_dimension=1080, jpeg_quality=85, max_size_bytes=1_048_576
  - Storage::disk('fras_photos') private disk at storage/app/private/fras_photos
  - personnel.photo_access_token uuid nullable unique column
  - App\Events\CameraStatusChanged (ShouldBroadcast + ShouldDispatchAfterCommit on fras.cameras)
  - App\Events\EnrollmentProgressed (ShouldBroadcast + ShouldDispatchAfterCommit on fras.enrollments)
  - routes/channels.php fras.cameras + fras.enrollments authorization
  - Personnel::enrollments() HasMany + Personnel::photoUrl accessor + photo_access_token fillable
  - Camera::enrollments() HasMany
affects:
  - Plan 20-02 (FrasPhotoProcessor) consumes config/fras.photo.* + fras_photos disk + photo_access_token
  - Plan 20-03 (EnrollPersonnelBatch + PersonnelObserver) consumes CameraEnrollmentService + config/fras.enrollment.*
  - Plan 20-04 (AdminCameraController) consumes Camera::enrollments()
  - Plan 20-05 (AdminPersonnelController + photo namespace) consumes photoUrl accessor + photo_access_token
  - Plan 20-07 (Personnel.vue + EnrollmentProgressPanel) consumes EnrollmentProgressed Echo payload
  - Plan 20-08 (dispatch map cameras layer) consumes CameraStatusChanged Echo payload
tech-stack:
  added:
    - intervention/image-laravel ^1.5.9 (transitively intervention/image ^3.11.7)
  patterns:
    - Additive Schema::table migration (Phase 18 schema freeze minimal break per D-20)
    - ShouldDispatchAfterCommit pairing with ShouldBroadcast to prevent rollback leaks (RESEARCH Pitfall 2)
    - Two-tier broadcast gating: fras.cameras (dispatch roles) vs fras.enrollments (supervisor/admin only) for DPA
    - Unguessable-UUID photo URL accessor latent until Plan 05 registers fras.photo.show route
key-files:
  created:
    - config/image.php
    - database/migrations/2026_04_22_000001_add_photo_access_token_to_personnel_table.php
    - app/Events/CameraStatusChanged.php
    - app/Events/EnrollmentProgressed.php
    - tests/Feature/Fras/Wave0InfrastructureTest.php
  modified:
    - composer.json
    - composer.lock
    - config/filesystems.php
    - config/fras.php
    - app/Models/Personnel.php (fillable line 36, enrollments line 75, photoUrl line 87)
    - app/Models/Camera.php (enrollments relation added after scopeActive)
    - routes/channels.php (lines 17-23, fras.cameras + fras.enrollments channels)
    - .planning/REQUIREMENTS.md (line 14 + CAMERA-02/03/05 + mapbox-gl dependency row)
decisions:
  - "20-01-D1: Mirrored fras_events disk shape byte-for-byte for fras_photos — preserves Phase 19 D-15 idiom; only knob that differs is FRAS_PHOTO_DISK env var name. Downstream S3 swap requires only env flip."
  - "20-01-D2: photo_access_token added to $fillable despite token being rotated only inside FrasPhotoProcessor (Plan 02) — Eloquent hydration requires it for model->save() cycles; mass-assignment risk mitigated at Form Request layer (Plan 05) per threat T-20-01-03."
  - "20-01-D3: photoUrl accessor intentionally latent — calls route('fras.photo.show') which is not registered until Plan 05. Accessor is never invoked in this plan; acts as a forward contract. Verified by grep that no code path calls \$personnel->photo_url in Wave 0."
  - "20-01-D4: Intervention Image major version is intervention/image ^3.11.7 (not ^4.x) — the v4-style API (Image::decodePath + encode(new JpegEncoder)) ships through the v3 release line per A4 in 20-RESEARCH Assumptions Log. No action needed."
  - "20-01-D5: fras.cameras channel reuses \$dispatchRoles (operator/dispatcher/supervisor/admin) but fras.enrollments narrows to [Supervisor, Admin] only — enrollment payloads carry personnel names + categories (DPA-sensitive); narrower gate per T-20-01-02 mitigation."
  - "20-01-D6: composer post-update boost:update script failed with 'no commands defined in boost namespace' — non-fatal in this environment; vendor/laravel/boost did install (composer show succeeded). Logged for Plan 02 awareness. No impact on Intervention install."
metrics:
  duration: ~12min
  completed: 2026-04-21
  tasks: 5
  files_changed: 12
  test_assertions: 32 (across 8 it-blocks, all passing)
---

# Phase 20 Plan 01: Wave 0 Infrastructure Summary

Wave 0 infrastructure for Phase 20 landed all 10 core artifacts plus the 4 milestone-level doc amendments (D-40..D-43). Every Plan 02-08 artifact now has a grep-able contract to consume: the Intervention Image v4 facade, the `fras_photos` private disk, the three config sections in `config/fras.php`, the `photo_access_token` UUID column, both broadcast events with ShouldDispatchAfterCommit, the two new private channels, and the Personnel/Camera model relations. Final gate test (`Wave0InfrastructureTest.php`) passes with 32 assertions across 8 it-blocks in 0.77s.

## What Landed

### Task 1: Install Intervention Image v4 + extend config (commit 7e93028)

- `composer require intervention/image-laravel:^1.3` resolved to **intervention/image-laravel 1.5.9** pulling **intervention/image 3.11.7** (the underlying v3 release ships the v4-style API per 20-RESEARCH A4).
- `php artisan vendor:publish --provider="Intervention\\Image\\Laravel\\ServiceProvider"` created `config/image.php` (1487 bytes). No pre-existing file to overwrite — publish was clean first-write, idempotent for future runs.
- `config/filesystems.php` — appended `fras_photos` disk after `fras_events`, mirroring the shape verbatim. `FRAS_PHOTO_DISK` env var reserved for S3 swap.
- `config/fras.php` — appended three sections after `mqtt`: `cameras` (degraded_gap_s=30, offline_gap_s=90), `enrollment` (batch_size=10, ack_timeout_minutes=5), `photo` (max_dimension=1080, jpeg_quality=85, max_size_bytes=1_048_576). All values from D-39 verbatim.
- Verification: `php artisan config:show fras.cameras.offline_gap_s` returns 90; `fras.enrollment.ack_timeout_minutes` returns 5; `filesystems.disks.fras_photos.root` resolves to `storage/app/private/fras_photos`.

### Task 2: photo_access_token migration + model extensions (commit 0bee95e)

- Created `database/migrations/2026_04_22_000001_add_photo_access_token_to_personnel_table.php` with additive `Schema::table` + `$table->uuid('photo_access_token')->nullable()->unique()->after('photo_hash')`.
- `php artisan migrate --no-interaction` applied migration cleanly (42.95ms). `php artisan db:table personnel` confirms column is `uuid, nullable` with index `personnel_photo_access_token_unique ... btree, unique`.
- `app/Models/Personnel.php`:
  - Line 36: `'photo_access_token'` added to `$fillable` (between `photo_hash` and `category`).
  - Line 75: `enrollments()` returns `HasMany<CameraEnrollment>` (after scopeActive).
  - Line 87: `photoUrl()` accessor via `Attribute::make(get: fn () => ...)` returning `route('fras.photo.show', ['token' => $this->photo_access_token])` or null. Latent — no call site until Plan 05.
  - New imports: `Illuminate\Database\Eloquent\Casts\Attribute` + `Illuminate\Database\Eloquent\Relations\HasMany`.
- `app/Models/Camera.php`: appended `enrollments()` relation mirror after scopeActive. Imports `HasMany`.
- Pint format pass — zero changes needed.

### Task 3: CameraStatusChanged + EnrollmentProgressed + channels (commit d936326)

- `app/Events/CameraStatusChanged.php` — ShouldBroadcast + ShouldDispatchAfterCommit on `PrivateChannel('fras.cameras')`. `broadcastWith()` returns exactly the 5 keys per D-37: camera_id, camera_id_display, status (enum->value), last_seen_at (ISO 8601 nullable), location (nested `{lat, lng}` via Magellan Point getters, nullable).
- `app/Events/EnrollmentProgressed.php` — ShouldBroadcast + ShouldDispatchAfterCommit on `PrivateChannel('fras.enrollments')`. `broadcastWith()` returns exactly the 5 keys per D-38: personnel_id, camera_id, camera_id_display (via `$this->enrollment->camera?->camera_id_display` null-safe), status (enum->value), last_error.
- `routes/channels.php`:
  - Line 17-19: `Broadcast::channel('fras.cameras', ... use ($dispatchRoles) ... in_array($user->role, $dispatchRoles))` — reuses the $dispatchRoles array already in scope from line 7 (operator/dispatcher/supervisor/admin).
  - Line 21-23: `Broadcast::channel('fras.enrollments', fn (User $user) => in_array($user->role, [UserRole::Supervisor, UserRole::Admin]))` — narrower gate per T-20-01-02 DPA mitigation.
- Verification: `ReflectionClass` confirms both events implement both contracts; `grep "PrivateChannel\('fras\." routes/channels.php` returns 0 matches (grep anchors on the Broadcast::channel call, not inside the event file); `grep "Broadcast::channel\('fras\." routes/channels.php` returns exactly 2 matches.
- Pint format pass — zero changes needed.

### Task 4: D-40..D-43 doc amendments (commit db9a5b0)

- `.planning/REQUIREMENTS.md`:
  - Line 14 (D-40): Flipped map baseline to `mapbox-gl` retained (v1.0 continuity); MapLibre path deprecated.
  - CAMERA-02 (D-42): Replaced "MapLibre GL JS picker (ported from FRAS's Mapbox picker, rewritten for MapLibre)" with "Mapbox-GL picker (port of `intake/LocationMapPicker.vue` shape)".
  - CAMERA-03 (D-41): Replaced "MapLibre map (no HTML overlays, no `mapbox-gl` import — CI bundle check enforces)" with "`mapbox-gl` map (no HTML overlays; layer extends `useDispatchMap.ts` alongside existing incidents + units layers)".
  - CAMERA-05 (D-43): Replaced `last_heartbeat_at` + simple 90s offline flip with `last_seen_at` + online/degraded/offline state machine at 30s/90s gaps with transition-only broadcasts.
  - Out-of-scope `mapbox-gl` dependency row: rewritten to reflect retention (v1.0 continuity per D-01) instead of MapLibre-rewrite-required.
- `.planning/ROADMAP.md` Phase 20 SC#2/#3/#5 — **already amended** in prior plan-checker revision (commits 4c45061, 3cd7c43). No further changes needed here; verified zero matches for `MapLibre picker|MapLibre GL JS picker|last_heartbeat_at|CI bundle-check` in ROADMAP.md. Task 5's test asserted no stale references remain.

### Task 5: Wave0InfrastructureTest + config cache rebuild (commit bdc60ae)

- `tests/Feature/Fras/Wave0InfrastructureTest.php` — 8 it-blocks, 32 assertions, `pest()->group('fras')`. Asserts:
  1. Intervention facade + JpegEncoder class_exists
  2. All 7 `config('fras.*')` keys with expected integer/literal defaults
  3. `config('filesystems.disks.fras_photos.*')` driver/visibility/root
  4. `Schema::hasColumn('personnel', 'photo_access_token')`
  5. CameraStatusChanged: ShouldBroadcast + ShouldDispatchAfterCommit + PrivateChannel name + payload keys
  6. EnrollmentProgressed: same shape as #5
  7. Personnel + Camera enrollments() returns HasMany instances
  8. Personnel $fillable contains 'photo_access_token'
- `php artisan config:clear && route:clear && event:clear` — all three cleared successfully.
- Pint auto-fixed `new_with_parentheses` + `fully_qualified_strict_types` (collapsed inline `\Intervention\...` to top-level `use` + `(new Personnel)` vs `(new Personnel())`). No assertions changed.
- Final result: **8 passed (32 assertions) in 0.77s**.

## Contract Surface for Plans 02-08

| Artifact | Consumer plan | Grep anchor |
|----------|---------------|-------------|
| `\Intervention\Image\Laravel\Facades\Image` | 20-02 FrasPhotoProcessor | `use Intervention\Image\Laravel\Facades\Image` |
| `config('fras.photo.max_dimension\|jpeg_quality\|max_size_bytes')` | 20-02 FrasPhotoProcessor | `config('fras.photo.` |
| `Storage::disk('fras_photos')` | 20-02 FrasPhotoProcessor | `disk('fras_photos')` |
| `personnel.photo_access_token` column | 20-02 + 20-05 | `photo_access_token` |
| `Personnel::photoUrl` accessor | 20-02 CameraEnrollmentService::buildEditPersonsNewPayload | `$personnel->photo_url` |
| `Personnel::enrollments()` + `Camera::enrollments()` | 20-03 PersonnelObserver + 20-04 AdminCameraController | `->enrollments()` |
| `new CameraStatusChanged($camera)` | 20-06 CameraWatchdogCommand + 20-08 dispatch map | `CameraStatusChanged::dispatch` |
| `new EnrollmentProgressed($enrollment)` | 20-02 CameraEnrollmentService + 20-03 EnrollPersonnelBatch | `EnrollmentProgressed::dispatch` |
| `fras.cameras` private channel | 20-07 useEnrollmentProgress + 20-08 useDispatchMap | `Echo.private('fras.cameras')` |
| `fras.enrollments` private channel | 20-07 useEnrollmentProgress | `Echo.private('fras.enrollments')` |
| `config('fras.cameras.degraded_gap_s\|offline_gap_s')` | 20-06 CameraWatchdogCommand | `config('fras.cameras.` |
| `config('fras.enrollment.batch_size\|ack_timeout_minutes')` | 20-03 EnrollPersonnelBatch + 20-02 CameraEnrollmentService | `config('fras.enrollment.` |

## Deviations from Plan

None — plan executed exactly as written. Task 4's ROADMAP.md edits turned out to be no-ops because the plan-checker had already applied the SC#2/#3/#5 amendments in prior commits (`4c45061 docs(20): revise 01-wave-0 per plan-checker`, `3cd7c43 docs(20): revise VALIDATION per plan-checker`). The plan's Edit 5 (ROADMAP.md SC block) instructions were satisfied before this plan started; verification grep confirms zero stale `MapLibre|last_heartbeat_at|CI bundle-check` references in ROADMAP.md Phase 20 text.

The composer post-update `boost:update` script returned exit code 1 ("no commands defined in boost namespace") during Task 1 — this is a pre-existing hook quirk unrelated to the Intervention install. The vendor/laravel/boost package itself installed cleanly (composer show succeeded; no namespace-resolution failures observed downstream). Not a deviation — documented for Plan 02 awareness.

## Key Links (grep-verifiable)

- `app/Events/CameraStatusChanged.php` → `routes/channels.php` via `PrivateChannel('fras.cameras')` (Broadcast::channel line 17)
- `app/Events/EnrollmentProgressed.php` → `routes/channels.php` via `PrivateChannel('fras.enrollments')` (Broadcast::channel line 21)
- `app/Models/Personnel.php` (photoUrl accessor line 87) → `route('fras.photo.show')` (LATENT — registered in Plan 05)

## Auto-formatting Applied

- Task 2 + Task 3: Pint on dirty files — zero formatting changes (code was already compliant).
- Task 5: Pint auto-applied `new_with_parentheses` (e.g. `(new Personnel)` instead of `(new Personnel())`) and `fully_qualified_strict_types` (hoisted `\Intervention\...` FQCNs to `use` imports) in `tests/Feature/Fras/Wave0InfrastructureTest.php`. Test passes unchanged after format.

## Self-Check: PASSED

### Created files verified present

- `config/image.php` — FOUND
- `database/migrations/2026_04_22_000001_add_photo_access_token_to_personnel_table.php` — FOUND
- `app/Events/CameraStatusChanged.php` — FOUND
- `app/Events/EnrollmentProgressed.php` — FOUND
- `tests/Feature/Fras/Wave0InfrastructureTest.php` — FOUND
- `.planning/phases/20-camera-personnel-admin-enrollment/20-01-SUMMARY.md` — FOUND (this file)

### Commits verified present in git log

- 7e93028 (Task 1) — FOUND
- 0bee95e (Task 2) — FOUND
- d936326 (Task 3) — FOUND
- db9a5b0 (Task 4) — FOUND
- bdc60ae (Task 5) — FOUND

### Verification gate

- `php artisan test --compact tests/Feature/Fras/Wave0InfrastructureTest.php` → **8 passed (32 assertions) in 0.77s** ✓
