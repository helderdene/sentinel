---
phase: 20
slug: camera-personnel-admin-enrollment
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-04-21
---

# Phase 20 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Source: RESEARCH.md §Validation Architecture. The planner fills per-task rows during PLAN.md authoring.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (Laravel 12) |
| **Config file** | `phpunit.xml` (project root) |
| **Quick run command** | `php artisan test --compact --filter=<TaskName>` |
| **Full suite command** | `php artisan test --compact` |
| **Feature-group command** | `php artisan test --compact --group=fras` |
| **Estimated runtime** | ~20s quick filter · ~90s full suite (SQLite in-memory) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=<TaskName>` (the test file created in the same wave).
- **After every plan wave:** Run `php artisan test --compact --group=fras`.
- **Before `/gsd-verify-work`:** Full suite must be green (`php artisan test --compact`).
- **Max feedback latency:** 20 seconds per task; 90 seconds per phase full suite.

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 20-01-T1 | 20-01 | 0 | PERSONNEL-02, CAMERA-02 | — | Intervention + fras_photos + config/fras.* land in install state | Feature | `php artisan test --compact tests/Feature/Fras/Wave0InfrastructureTest.php` | ⬜ wave0 | ⬜ pending |
| 20-01-T2 | 20-01 | 0 | PERSONNEL-03 | T-20-01-03 | photo_access_token column + Personnel/Camera relations + photo_url accessor | Feature | `php artisan test --compact tests/Feature/Fras/Wave0InfrastructureTest.php` | ⬜ wave0 | ⬜ pending |
| 20-01-T3 | 20-01 | 0 | CAMERA-04, PERSONNEL-05 | T-20-01-01, T-20-01-02, T-20-01-04, T-20-01-05 | ShouldBroadcast + ShouldDispatchAfterCommit on both events; fras.cameras + fras.enrollments channel gates | Feature | `php artisan test --compact tests/Feature/Fras/Wave0InfrastructureTest.php` | ⬜ wave0 | ⬜ pending |
| 20-01-T4 | 20-01 | 0 | (doc amendments D-40..D-43) | — | REQUIREMENTS.md + ROADMAP.md Phase 20 text reflects mapbox-gl retained + last_seen_at | inspection | `rg -n 'MapLibre\|last_heartbeat_at' .planning/REQUIREMENTS.md .planning/ROADMAP.md` returns zero | ⬜ wave0 | ⬜ pending |
| 20-01-T5 | 20-01 | 0 | all above | — | Full infrastructure gate | Feature | `php artisan test --compact tests/Feature/Fras/Wave0InfrastructureTest.php` | ⬜ wave0 | ⬜ pending |
| 20-02-T1 | 20-02 | 1 | PERSONNEL-02 | T-20-02-01, T-20-02-02 | FrasPhotoProcessor converges ≤1MB/1080p, deterministic hash, PhotoTooLargeException on unshrinkable | Feature | `php artisan test --compact tests/Feature/Fras/FrasPhotoProcessorTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-02-T2 | 20-02 | 1 | PERSONNEL-04, PERSONNEL-07 | T-20-02-03, T-20-02-04 | CameraEnrollmentService dispatches per online camera, writes cache, broadcasts, MQTT publishes | Feature | `php artisan test --compact tests/Feature/Fras/CameraEnrollmentServiceTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-03-T1 | 20-03 | 2 | PERSONNEL-04 | T-20-03-05 | EnrollPersonnelBatch with WithoutOverlapping + $tries=3 + failed() handler | Feature | `php artisan test --compact tests/Feature/Fras/EnrollPersonnelBatchTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-03-T2 | 20-03 | 2 | PERSONNEL-04 | T-20-03-06 | PersonnelObserver gated on wasChanged(['photo_hash','category']); registered in boot() | Feature | `php artisan test --compact tests/Feature/Fras/PersonnelObserverTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-03-T3 | 20-03 | 2 | PERSONNEL-07 | T-20-03-01, T-20-03-07 | AckHandler correlates Cache::pull, updates rows, broadcasts, idempotent on duplicate delivery | Feature | `php artisan test --compact tests/Feature/Fras/AckHandlerTest.php` | ⚠️ Phase 19 stub extend | ⬜ pending |
| 20-04-T1 | 20-04 | 2 | CAMERA-01 | T-20-04-02 | Form Requests + Route::resource + role:admin gate | Feature (route:list) | `php artisan route:list --path=admin/cameras --except-vendor` (8 routes) | ❌ Wave 0 | ⬜ pending |
| 20-04-T2 | 20-04 | 2 | CAMERA-01, CAMERA-02, CAMERA-06 | T-20-04-01, T-20-04-07 | Controller CRUD + auto-sequence + barangay lookup + deletion guard + role gate | Feature | `php artisan test --compact tests/Feature/Admin/AdminCameraControllerTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-05-T1 | 20-05 | 2 | PERSONNEL-03 | T-20-05-02 | Public /fras/photo/{token} serves only while enrollment pending/syncing; 404 on revocation + token rotation | Feature | `php artisan test --compact tests/Feature/Fras/FrasPhotoAccessControllerTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-05-T2 | 20-05 | 2 | PERSONNEL-01, PERSONNEL-02, PERSONNEL-05 | T-20-05-01, T-20-05-04, T-20-05-05 | AdminPersonnelController full lifecycle + custom_id + photo rotation + retry/resync endpoints | Feature | `php artisan test --compact tests/Feature/Admin/AdminPersonnelControllerTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-05-T2-photo | 20-05 | 2 | PERSONNEL-03 | T-20-05-03, T-20-05-06 | AdminPersonnelPhotoController signed-URL TTL + tamper + role-matrix (D-22: operator/supervisor/admin allow, responder/dispatcher deny) | Feature | `php artisan test --compact tests/Feature/Admin/AdminPersonnelPhotoControllerTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-05-T3 | 20-05 | 2 | PERSONNEL-05, CAMERA-04 | T-20-05-07 | Broadcast channel auth role matrix for fras.cameras + fras.enrollments | Feature | `php artisan test --compact tests/Feature/Fras/BroadcastAuthorizationTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-06-T1 | 20-06 | 3 | CAMERA-05 | T-20-06-01, T-20-06-04 | irms:camera-watchdog transitions online/degraded/offline; broadcast only on transition | Feature | `php artisan test --compact tests/Feature/Fras/CameraWatchdogTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-06-T2 | 20-06 | 3 | PERSONNEL-06 | T-20-06-03, T-20-06-07 | irms:personnel-expire-sweep unenrolls expired, soft-decommissions, marks enrollments done, logs | Feature | `php artisan test --compact tests/Feature/Fras/PersonnelExpireSweepTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-07-T1 | 20-07 | 4 | CAMERA-01, CAMERA-02 | T-20-07-01, T-20-07-05 | Cameras.vue + CameraForm.vue + CameraLocationPicker + CameraStatusBadge build clean | Build | `npm run lint && npm run types:check && npm run build` | ❌ Wave 0 | ⬜ pending |
| 20-07-T2 | 20-07 | 4 | PERSONNEL-01, PERSONNEL-05 | T-20-07-02, T-20-07-04 | Personnel.vue + PersonnelForm.vue + EnrollmentProgressPanel + useEnrollmentProgress build clean | Build | `npm run lint && npm run types:check && npm run build` | ❌ Wave 0 | ⬜ pending |
| 20-07-T3 | 20-07 | 4 | CAMERA-01, PERSONNEL-01, PERSONNEL-05 | — | Human-verify end-to-end admin UI + live EnrollmentProgressPanel | Manual UAT | Checkpoint (composer run dev + 2-tab live test) | ⬜ manual | ⬜ pending |
| 20-08-T1 | 20-08 | 5 | CAMERA-03, CAMERA-04 | T-20-08-01, T-20-08-06, T-20-08-07 | DispatchConsoleController cameras prop + useDispatchMap cameras layer + Echo subscription | Feature + Build | `php artisan test --compact tests/Feature/Dispatch/DispatchConsoleCamerasPropTest.php && npm run build` | ❌ Wave 0 | ⬜ pending |
| 20-08-T2 | 20-08 | 5 | all | — | Cross-surface integration: create camera → personnel → ACK → revocation | Feature | `php artisan test --compact tests/Feature/Fras/Phase20IntegrationTest.php` | ❌ Wave 0 | ⬜ pending |
| 20-08-T3 | 20-08 | 5 | CAMERA-03, CAMERA-04 | — | Human-verify dispatch console cameras layer + live transitions | Manual UAT | Checkpoint (tinker flip + dispatch console observe) | ⬜ manual | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*
*Populated by the planner during PLAN.md authoring from the RESEARCH.md §Per-Requirement Coverage Map.*

---

## Wave 0 Requirements

Installs / scaffolds that MUST land before feature waves so the per-task test commands above have files to invoke:

- [ ] `composer require intervention/image-laravel:^1.3` — Intervention Image v4 install (RESEARCH finding #1 — package is NOT currently in `composer.json`)
- [ ] `php artisan vendor:publish --provider="Intervention\Image\Laravel\ServiceProvider"` — publish `config/image.php`
- [ ] `database/migrations/2026_04_2x_add_photo_access_token_to_personnel_table.php` — `photo_access_token` uuid column, nullable, unique (D-20)
- [ ] `config/fras.php` — extend with `cameras.*`, `enrollment.*`, `photo.*` sections (D-39)
- [ ] `config/filesystems.php` — add `fras_photos` private local disk (D-19)
- [ ] `app/Enums/CameraStatus.php`, `PersonnelCategory.php`, `CameraEnrollmentStatus.php` — verify Phase 18 exports exist (read-only check, no creation)
- [ ] `tests/Feature/Admin/AdminCameraControllerTest.php` — empty skeleton with `#[Group('fras')]` attribute
- [ ] `tests/Feature/Admin/AdminPersonnelControllerTest.php` — empty skeleton + group
- [ ] `tests/Feature/Fras/CameraEnrollmentServiceTest.php` — empty skeleton + group
- [ ] `tests/Feature/Fras/FrasPhotoProcessorTest.php` — empty skeleton + group
- [ ] `tests/Feature/Fras/AckHandlerTest.php` — extend Phase 19 stub with Phase 20 body-fill test shape
- [ ] `tests/Feature/Fras/CameraWatchdogTest.php` — empty skeleton + group
- [ ] `tests/Feature/Fras/PersonnelExpireSweepTest.php` — empty skeleton + group
- [ ] `tests/Feature/Fras/FrasPhotoAccessControllerTest.php` — empty skeleton + group (token revocation coverage)
- [ ] `tests/Feature/Fras/EnrollmentProgressedBroadcastTest.php` — empty skeleton + group

---

## Nyquist Sampling Points (phase invariants)

Each invariant below MUST have at least one automated test in the Per-Task Verification Map above, directly invokable from the quick command. These are the load-bearing correctness gates for Phase 20:

1. **ACK correlation idempotency** — same `messageId` delivered twice produces exactly one `camera_enrollments` row transition and one `EnrollmentProgressed` broadcast. Cache key `enrollment-ack:{camera_id}:{messageId}` is consumed via `Cache::pull()` (atomic read+delete in a single Redis op) on first ACK — preventing duplicate-delivery race conditions. [AckHandlerTest]
2. **Enrollment job overlap prevention** — two concurrent `EnrollPersonnelBatch(camera, [...])` dispatches for the same camera → second release-back, only one `handle()` invocation observed. `WithoutOverlapping('enrollment-camera-{id}')->releaseAfter(30)->expireAfter(300)`. [CameraEnrollmentServiceTest + Queue::fake()]
3. **Photo URL revocation after ACK** — `GET /fras/photo/{token}` returns 200 while `camera_enrollments.status IN (pending, syncing)` for at least one row; returns 404 once all rows are `done` OR `photo_access_token` is rotated. [FrasPhotoAccessControllerTest]
4. **Camera deletion block on in-flight enrollments** — `DELETE /admin/cameras/{id}` returns 422 with `errors.camera` populated when any `camera_enrollments` row for that camera is in `pending` or `syncing`. [AdminCameraControllerTest]
5. **Watchdog transition atomicity** — `irms:camera-watchdog` fires `CameraStatusChanged` exactly once per transition (online→degraded, degraded→offline), zero dispatches when status is unchanged. [CameraWatchdogTest + Event::fake()]
6. **Expiry sweep accuracy** — `irms:personnel-expire-sweep` unenrolls ONLY personnel where `expires_at < now() AND decommissioned_at IS NULL`; does NOT touch already-decommissioned rows or rows with null `expires_at`. [PersonnelExpireSweepTest]
7. **Photo quality degradation loop termination** — `FrasPhotoProcessor::process()` converges on ≤1MB within the `quality ∈ [40, 85]` band; raises `PhotoTooLargeException` when a photo exceeds 1MB even at quality=40. [FrasPhotoProcessorTest]
8. **MD5 dedup gate** — uploading the same photo bytes twice yields the same `photo_hash`; `PersonnelObserver::saved` does NOT re-enqueue `EnrollPersonnelBatch` when `photo_hash` did not change. [FrasPhotoProcessorTest + PersonnelObserverTest]
9. **Observer gated re-enrollment** — editing a non-gated field (name/phone/address/id_card/birthday) does NOT dispatch `EnrollPersonnelBatch`; editing `photo_hash` OR `category` DOES dispatch to every active camera. [PersonnelObserverTest]
10. **Auto-sequence camera ID generation** — `AdminCameraController::store` produces `CAM-01`, `CAM-02`, …, `CAM-99` via regex MAX+1 and survives concurrent creates under DB-level unique constraint. [AdminCameraControllerTest]
11. **Signed-route TTL honored** — `/admin/personnel/{id}/photo` returns 200 within 5 minutes of `temporarySignedRoute` generation; returns 403 after. [AdminPersonnelPhotoControllerTest]
12. **Broadcast channel authorization** — `fras.cameras` accessible to operator/dispatcher/supervisor/admin; `fras.enrollments` restricted to supervisor/admin; responders denied on both. [BroadcastAuthorizationTest — may fold into existing channel test]

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| EnrollmentProgressPanel live updates visible in the browser within 500ms of Reverb broadcast | CAMERA-02 (500ms SLA), PERSONNEL-05 | WebSocket round-trip + Vue reactivity through Reverb is painful to automate faithfully; unit-test the event payload + Echo subscription shape, visual confirmation via dev browser | 1. `composer run dev` 2. Log in as admin 3. Navigate to `/admin/personnel/{id}/edit` 4. Open a second tab, rapid-fire three save edits on the same personnel 5. First tab's EnrollmentProgressPanel rows should transition pending→syncing→done without refresh, within ~500ms of save |
| Camera map markers visibly transition color on status change on the dispatch console | CAMERA-02 | Same reason — broadcast→browser render is an integration surface | 1. `composer run dev` 2. Log in as operator 3. Open `/dispatch/console` with cameras layer enabled 4. In a separate terminal, `php artisan tinker` and flip a camera's status via model → observe marker color transition |
| MapLibre CI bundle-check absence confirmed | D-02 (SC3 dropped) | Negative verification — we assert the check does NOT exist | Confirm no `maplibre-gl` grep hook or CI job exists post-phase; `mapbox-gl` imports remain | 

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references (Intervention Image install, 8 test skeletons)
- [ ] No watch-mode flags
- [ ] Feedback latency < 20s per-task, < 90s full suite
- [ ] `nyquist_compliant: true` set in frontmatter once planner populates the per-task rows

**Approval:** pending — planner completes per-task rows during PLAN.md authoring; checker verifies in step 10.
