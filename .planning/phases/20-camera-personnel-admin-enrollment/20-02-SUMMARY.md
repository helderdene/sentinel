---
phase: 20-camera-personnel-admin-enrollment
plan: 02
subsystem: fras-services
tags: [fras, services, intervention-image, mqtt, camera-enrollment]
requirements: [PERSONNEL-02, PERSONNEL-04, PERSONNEL-07]
dependency_graph:
  requires:
    - plan-20-01 (Intervention Image v4 install + config/fras.php + CameraStatusChanged + EnrollmentProgressed + Personnel.photo_access_token migration + photo_url accessor + enrollments relations)
  provides:
    - FrasPhotoProcessor::process(UploadedFile, Personnel) -> {photo_path, photo_hash}
    - FrasPhotoProcessor::delete(?string) idempotent
    - PhotoTooLargeException
    - CameraEnrollmentService::enrollPersonnel(Personnel)
    - CameraEnrollmentService::enrollAllToCamera(Camera)
    - CameraEnrollmentService::upsertBatch(Camera, array)
    - CameraEnrollmentService::deleteFromAllCameras(Personnel)
    - CameraEnrollmentService::translateErrorCode(int)
    - Temporary app/Jobs/EnrollPersonnelBatch.php stub (replaced by plan 20-03)
  affects:
    - plan-20-03 (AckHandler + full EnrollPersonnelBatch job replaces stub)
    - plan-20-04 (CameraController calls enrollAllToCamera on status-online transitions)
    - plan-20-05 (PersonnelController calls FrasPhotoProcessor + enrollPersonnel)
    - plan-20-06 (ExpireSweep command calls deleteFromAllCameras)
tech-stack:
  added:
    - Intervention Image v4 (resolved via Image::read() + JpegEncoder)
    - php-mqtt/laravel-client publisher connection
  patterns:
    - Service-class port (verbatim FRAS + 4 IRMS deltas per 20-PATTERNS.md)
    - ShouldDispatchAfterCommit broadcasts (inherits from EnrollmentProgressed, plan 20-01)
    - Cache-correlation via Redis keys (enrollment-ack:{cam}:{msgId})
key-files:
  created:
    - app/Exceptions/PhotoTooLargeException.php
    - app/Services/FrasPhotoProcessor.php
    - app/Services/CameraEnrollmentService.php
    - app/Jobs/EnrollPersonnelBatch.php (stub — replaced in plan 20-03)
    - tests/fixtures/personnel-photo-sample.jpg (4.6MB, random-noise)
    - tests/Feature/Fras/FrasPhotoProcessorTest.php
    - tests/Feature/Fras/CameraEnrollmentServiceTest.php
  modified: []
decisions:
  - Chose Image::read() over Image::decodePath() per plan task 1 note + more idiomatic v4 API
  - Used random-noise fixture (mt_srand(42), 2000x1500@q=98 = 4.6MB) so degradation loop provably executes on any platform (sequential gradients compressed below 1.5MB in testing)
  - Plan 20-02's stub EnrollPersonnelBatch job authored inline (not in frontmatter files_modified) — needed so dispatch sites compile; noted as deviation + destined for plan 20-03 replacement
  - FRAS payload `personType` field kept but `array_filter(!== null)` used so IRMS personnel lacking that field produce clean payloads
metrics:
  duration: ~35 minutes
  completed_date: 2026-04-21
  tasks_completed: 2
  files_created: 7
  lines_added: ~600 (services 340, tests ~240, fixture binary, stub ~30)
---

# Phase 20 Plan 02: FRAS Service Layer Port — Summary

Ported the two load-bearing FRAS services (`PhotoProcessor` → `FrasPhotoProcessor` and `CameraEnrollmentService` → `CameraEnrollmentService`) into IRMS with the IRMS deltas specified by 20-CONTEXT D-11/D-14/D-15/D-18/D-19. Companion exception `PhotoTooLargeException`, a 4.6MB random-noise JPEG fixture, and 9 Pest feature tests round out the plan.

## Requirements Addressed

- **PERSONNEL-02** — photo processor with resize/compress/hash pipeline
- **PERSONNEL-04** — camera enrollment state machine (upsert/chunk/publish/cache)
- **PERSONNEL-07** — MQTT delete-on-decommission path (`deleteFromAllCameras`)

## Implementation

### FrasPhotoProcessor (app/Services/FrasPhotoProcessor.php, 71 lines)

- **Entry point:** `Image::read($file->getRealPath())`. Chose `read()` over the FRAS source's `decodePath()` because `read()` is the canonical v4 API covered throughout the Intervention v4 docs; both are equivalent for local file paths. The plan allows either (20-PATTERNS.md lines 398-428 + task note).
- **Pipeline:** `orient()` → `scaleDown(width: 1080, height: 1080)` → encode JPEG@85 → degradation loop `quality -= 10` while `> 1_048_576 bytes && quality > 40` → `md5` hash → `Storage::disk('fras_photos')->put("personnel/{uuid}.jpg", ...)`.
- **Exception:** `PhotoTooLargeException` (extends `RuntimeException`) thrown if loop bottoms out with bytes still over the cap.
- **Delete:** null-safe + idempotent (no `exists()` precheck — the Laravel filesystem `delete()` is safe on missing paths).

### CameraEnrollmentService (app/Services/CameraEnrollmentService.php, 254 lines)

Five public methods + two payload builders:

| Method | Contract |
|---|---|
| `enrollPersonnel(Personnel)` | upsert enrollment → dispatch `EnrollmentProgressed` → dispatch `EnrollPersonnelBatch` on `fras` queue, per online non-decommissioned camera |
| `enrollAllToCamera(Camera)` | guards non-online/decommissioned early; chunk by `batch_size`, same upsert/dispatch pattern per row + one job per chunk |
| `upsertBatch(Camera, array)` | chunk by `batch_size`; per chunk: random `messageId`, Redis cache-put `enrollment-ack:{cam}:{msgId}` with TTL = `ack_timeout_minutes * 60`, transition Pending→Syncing + broadcast, MQTT publish to `{topic_prefix}/{device_id}` on the `publisher` connection |
| `deleteFromAllCameras(Personnel)` | fire-and-forget DeletePersons payload per online non-decommissioned camera; no ACK tracking (D-14) |
| `translateErrorCode(int)` | 9-entry map (461/463..468/474/478) + default fallback that exposes only the numeric code (T-20-02-06 mitigation) |

### IRMS Deltas Applied vs FRAS Source

Per 20-PATTERNS.md lines 347-356 (the 4 mandated tweaks):

1. **UUID FKs** — `$personnel->id` passed as string UUID throughout (no cast). ✅
2. **`config('hds.*')` → `config('fras.*')`** — replaced at 5 sites (`batch_size`, `ack_timeout_minutes`, `mqtt.topic_prefix` x2, photo config remains in `FrasPhotoProcessor`). Grep confirms 0 remaining `hds.` matches in the service. ✅
3. **Broadcast `EnrollmentProgressed`** — dispatched at 3 call sites: after `updateOrCreate(..., Pending)` in both `enrollPersonnel` and `enrollAllToCamera`, and after `update(..., Syncing)` in `upsertBatch`. ✅
4. **`is_online` → `status === CameraStatus::Online`** — replaced at 3 call sites: `enrollPersonnel`, `enrollAllToCamera` (guard), `deleteFromAllCameras`. Grep confirms 0 remaining `is_online` matches. ✅

### Temporary EnrollPersonnelBatch Stub

File: `app/Jobs/EnrollPersonnelBatch.php`. Implements `ShouldQueue` with `Queueable` trait and promoted constructor `(Camera $camera, array $personnelIds)` — just enough surface for `EnrollPersonnelBatch::dispatch(...)->onQueue('fras')` to compile and for `Queue::fake()` tests to verify dispatch count. The `handle()` body is empty. Plan 20-03 Task 1 replaces this stub with the real job implementation (which calls `CameraEnrollmentService::upsertBatch` + retry handling). The docblock marks it `@internal` and calls out the replacement.

## Tests

### FrasPhotoProcessorTest (4 cases, all currently skipped — Wave 1 dependency)

1. **Resize/compress/store** — 2000×1500 JPEG → `personnel/{uuid}.jpg` ≤1080×1080 and ≤1MB on `fras_photos` disk
2. **Hash determinism** — same bytes yield identical MD5 hashes (length 32)
3. **Delete idempotency** — null, missing-path, and existing-path all handled without throwing
4. **PhotoTooLargeException** — `fras.photo.max_size_bytes = 1000` with the 4.6MB fixture forces degradation-loop exhaustion

### CameraEnrollmentServiceTest (5 cases, all currently skipped — Wave 1 dependency)

1. **enrollPersonnel camera filtering** — only online + non-decommissioned receive jobs (`Queue::assertPushed(..., 2)`), one broadcast per row
2. **upsertBatch state machine** — Pending → Syncing transition + `EnrollmentProgressed` dispatched with the correct enrollment closure
3. **upsertBatch photo_url injection** — captures the MQTT payload JSON and asserts the personnel's `photo_access_token` appears inside (proves `Personnel::photo_url` accessor was invoked to build `picURI`)
4. **translateErrorCode** — all 9 known codes + default produce non-empty strings
5. **deleteFromAllCameras** — `MQTT::shouldReceive('connection->publish')->times(3)` for 3 online + 0 for the offline

### Fixture Generation

`tests/fixtures/personnel-photo-sample.jpg` — 4,664,071 bytes (4.6MB), 2000×1500 @ quality=98 random-noise JPEG (`mt_srand(42)` for reproducibility). Size is ~3x above the plan's 1.5MB threshold, which guarantees the degradation loop executes at least 4-5 iterations even on environments where libjpeg compresses more aggressively. The random-noise pattern defeats any entropy-coding path that might otherwise compress a gradient below the cap at q=85.

## Deferred — Wave 1 Dependency

All 9 Pest tests skip cleanly in this parallel-executor worktree because the classes they depend on ship in plan 20-01 which is running concurrently:

- `Intervention\Image\Laravel\Facades\Image` (installed by plan 20-01 Task 1)
- `App\Events\EnrollmentProgressed` (created by plan 20-01 Task 3)
- `personnel.photo_access_token` column (migration from plan 20-01 Task 2)
- `Personnel::photo_url` accessor (plan 20-01 Task 2)

The test bodies are complete; the `beforeEach` guards check `class_exists` on the missing classes and call `markTestSkipped(...)`. After the orchestrator merges plan 20-01, the facade / event / column / accessor all resolve and the 9 tests run against real behaviour with no source changes required.

Skip counts: `FrasPhotoProcessorTest = 4 skipped`, `CameraEnrollmentServiceTest = 5 skipped`.

## Deviations from Plan

**1. [Rule 2 — Missing critical functionality] Authored EnrollPersonnelBatch stub outside frontmatter files_modified**

- **Found during:** Task 2 setup
- **Issue:** Task 2 action step explicitly instructs creating a stub job so the service's `EnrollPersonnelBatch::dispatch(...)` sites compile, but `app/Jobs/EnrollPersonnelBatch.php` is not in the plan's `files_modified` frontmatter.
- **Fix:** Created the stub as documented in Task 2 action step lines 498-519. Marked as `@internal` in the docblock with an explicit "replaced by plan 20-03" note.
- **Files added:** `app/Jobs/EnrollPersonnelBatch.php`
- **Commit:** 2aad005

**2. [Rule 3 — Blocking] Wave-1 test guards instead of TDD red-green**

- **Found during:** Both Task 1 and Task 2
- **Issue:** Plan calls for strict RED→GREEN TDD but the Wave 1 dependency (Intervention + EnrollmentProgressed) is not yet merged into this worktree, so tests cannot run RED→GREEN against real code.
- **Fix:** Tests author the full assertions and gate on `class_exists()` via `beforeEach` / `markTestSkipped`. After Plan 20-01 lands, the guard becomes a no-op and the 9 tests run as intended.
- **Files:** `tests/Feature/Fras/FrasPhotoProcessorTest.php`, `tests/Feature/Fras/CameraEnrollmentServiceTest.php`
- **Commits:** af305b5, 2aad005

## Verification

- `php artisan test --compact tests/Feature/Fras/FrasPhotoProcessorTest.php` → 4 skipped ✅
- `php artisan test --compact tests/Feature/Fras/CameraEnrollmentServiceTest.php` → 5 skipped ✅
- `php -l` on all 4 new PHP files → no syntax errors ✅
- `vendor/bin/pint --dirty --format agent` → clean ✅
- `rg -n "config\('hds\." app/Services/CameraEnrollmentService.php` → 0 ✅
- `rg -n "is_online" app/Services/CameraEnrollmentService.php` → 0 ✅
- `rg -n "EnrollmentProgressed::dispatch" app/Services/CameraEnrollmentService.php` → 3 ✅
- `rg -n "MQTT::connection\('publisher'\)->publish" app/Services/CameraEnrollmentService.php` → 2 ✅
- `rg -n "CameraStatus::Online" app/Services/CameraEnrollmentService.php` → 4 ✅
- `rg -n "quality -= 10" app/Services/FrasPhotoProcessor.php` → 1 ✅
- Fixture size > 1,500,000 bytes: 4,664,071 ✅

## Commits

| Task | Hash | Summary |
|---|---|---|
| 1 | af305b5 | FrasPhotoProcessor + PhotoTooLargeException + fixture + 4 tests |
| 2 | 2aad005 | CameraEnrollmentService + EnrollPersonnelBatch stub + 5 tests |

## Self-Check: PASSED

**Files verified present:**
- `app/Exceptions/PhotoTooLargeException.php` ✅
- `app/Services/FrasPhotoProcessor.php` ✅
- `app/Services/CameraEnrollmentService.php` ✅
- `app/Jobs/EnrollPersonnelBatch.php` ✅ (stub, replaced by plan 20-03)
- `tests/fixtures/personnel-photo-sample.jpg` ✅ (4,664,071 bytes)
- `tests/Feature/Fras/FrasPhotoProcessorTest.php` ✅
- `tests/Feature/Fras/CameraEnrollmentServiceTest.php` ✅

**Commits verified in git log:**
- af305b5 ✅
- 2aad005 ✅
