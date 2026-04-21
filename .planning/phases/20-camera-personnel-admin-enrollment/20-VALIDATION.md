---
phase: 20
slug: camera-personnel-admin-enrollment
status: draft
nyquist_compliant: false
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
| — | — | — | — | — | Planner fills per task; Wave 0 rows pre-seeded below | — | — | — | ⬜ pending |

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

1. **ACK correlation idempotency** — same `messageId` delivered twice produces exactly one `camera_enrollments` row transition and one `EnrollmentProgressed` broadcast. Cache key `enrollment-ack:{camera_id}:{messageId}` is consumed via `Cache::forget()` on first ACK. [AckHandlerTest]
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
