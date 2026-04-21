---
phase: 20-camera-personnel-admin-enrollment
plan: 03
subsystem: fras-runtime
tags: [fras, jobs, observers, mqtt, ack, enrollment, idempotency]
requirements: [PERSONNEL-04, PERSONNEL-07]
dependency_graph:
  requires:
    - plan-20-01 (EnrollmentProgressed event, CameraEnrollment/Personnel/Camera models)
    - plan-20-02 (CameraEnrollmentService: upsertBatch + enrollPersonnel + deleteFromAllCameras + translateErrorCode)
    - Phase 19 (AckHandler shell + MqttHandler contract + fras Horizon supervisor + mqtt log channel)
  provides:
    - App\Jobs\EnrollPersonnelBatch (WithoutOverlapping mutex + $tries = 3 + failed() handler)
    - App\Observers\PersonnelObserver (wasChanged-gated photo_hash/category trigger + delete cascade)
    - App\Providers\AppServiceProvider::configureObservers registration
    - App\Mqtt\Handlers\AckHandler::handle() full D-16 body (Cache::pull correlation + row updates + broadcast)
  affects:
    - plan-20-04 (AdminCameraController — relies on EnrollPersonnelBatch + PersonnelObserver for online-transitions)
    - plan-20-05 (AdminPersonnelController — relies on PersonnelObserver for save/delete triggers)
    - plan-20-07 (EnrollmentProgressPanel Vue component — consumes per-row EnrollmentProgressed broadcasts)
tech-stack:
  added:
    - Illuminate\Queue\Middleware\WithoutOverlapping (per-camera mutex)
  patterns:
    - Job failed() recovery — transitions rows + broadcasts instead of swallowing
    - Observer wasChanged-gated dispatch (D-13) — avoids metadata-edit noise
    - Cache::pull idempotency (T-20-03-07) — atomic read+delete survives MQTT QoS re-send
    - configureObservers() helper method mirroring existing configureEventListeners/configureGates pattern
key-files:
  created:
    - app/Observers/PersonnelObserver.php
    - tests/Feature/Fras/EnrollPersonnelBatchTest.php
    - tests/Feature/Fras/PersonnelObserverTest.php
    - tests/Feature/Fras/AckHandlerTest.php
  modified:
    - app/Jobs/EnrollPersonnelBatch.php (stub fully replaced)
    - app/Mqtt/Handlers/AckHandler.php (scaffold body replaced)
    - app/Providers/AppServiceProvider.php (observer registration + configureObservers)
decisions:
  - "queue = 'fras' set via constructor assignment instead of typed property — Queueable trait pre-declares public \$queue (untyped) and a typed re-declaration causes a trait-composition fatal at class load (verified via php -r class_exists check)"
  - "configureObservers() method added following the existing boot() helper-method pattern (configureDefaults / configureGates / configureRateLimiters / configureEventListeners) — keeps boot() a single-responsibility dispatcher"
  - "AckHandler test spies Log::channel('mqtt') explicitly via Mockery::spy(LoggerInterface::class) + Log::shouldReceive('channel')->andReturn(spy) instead of the plan's Log::spy()-based assertion — Log::spy() returns null from channel() which crashes the warn paths"
  - "Task 1 delegation test replaced Mockery on CameraEnrollmentService with an MQTT::shouldReceive side-effect observation — the service is declared final (D-11 FRAS port norm) which blocks both Mockery and anonymous subclass doubling"
metrics:
  duration: ~40 minutes
  completed_date: 2026-04-21
  tasks_completed: 3
  files_created: 4
  files_modified: 3
  tests_added: 13 (3 + 5 + 5 across the three test files)
  assertions_added: 27
---

# Phase 20 Plan 03: Enrollment Round-Trip — Summary

Completed the enrollment pipeline round-trip by replacing the plan-02 stub job with the full FRAS port, adding the `PersonnelObserver` that bridges admin edits to camera resync, and filling the Phase-19 `AckHandler` scaffold with the D-16 correlation body. Three Pest test files (13 cases, 27 assertions) cover mutex shape, observer gating, and ACK idempotency. Full `--group=fras` suite: 51 passed + 1 skipped.

## Requirements Addressed

- **PERSONNEL-04** — camera enrollment state machine end-to-end (service → job → MQTT → ACK → row transition → broadcast)
- **PERSONNEL-07** — delete-on-decommission cascade via `PersonnelObserver::deleted → CameraEnrollmentService::deleteFromAllCameras`

## Task 1: EnrollPersonnelBatch (commit 15c3041)

Replaced the 33-line plan-02 stub with an 89-line FRAS-port job:

| Contract | Surface |
|---|---|
| `public int $tries = 3` | typed property, matches FRAS |
| `queue = 'fras'` | set in constructor (see Decisions — typed redeclaration conflicts with Queueable trait) |
| `middleware()` | `[WithoutOverlapping('enrollment-camera-'.$camera->id)->releaseAfter(30)->expireAfter(300)]` |
| `handle(CameraEnrollmentService)` | single-line delegation to `upsertBatch` |
| `failed(Throwable $e)` | iterates `personnelIds`, marks each row `status=Failed` with `last_error=$e->getMessage()`, dispatches `EnrollmentProgressed` per row |

### Tests (`tests/Feature/Fras/EnrollPersonnelBatchTest.php` — 3 cases, 11 assertions)

1. **middleware + metadata** — validates `$tries`, `$queue`, middleware class + reflection-read lock key format
2. **handle() delegates** — observes `upsertBatch` side effects (MQTT publish with correct topic + `EditPersonsNew` payload substring + Pending → Syncing transition). Rationale: service is `final`, so Mockery + anonymous subclass both blocked.
3. **failed() marks rows + broadcasts** — 2 personnel → 2 rows flipped to Failed with correct `last_error` + 2 broadcasts

Stub deletion confirmed: `rg -n "Stub — real implementation ships in plan 20-03"` returns 0 matches.

## Task 2: PersonnelObserver + AppServiceProvider (commit f9a7412)

Created `app/Observers/PersonnelObserver.php` (36 lines) with two hooks:

- **`saved(Personnel)`** — if `wasChanged(['photo_hash', 'category'])`, delegates to `service->enrollPersonnel($personnel)` (D-13 gate prevents name/phone/address edits from triggering camera resync)
- **`deleted(Personnel)`** — fires `service->deleteFromAllCameras($personnel)` for immediate face-database purge without waiting for the expire-sweep command (plan 20-06)

### Registration pattern

Extended `AppServiceProvider::boot()` with `$this->configureObservers()` following the existing `configureDefaults/configureGates/configureRateLimiters/configureEventListeners` delegation style. The new method:

```php
protected function configureObservers(): void
{
    Personnel::observe(PersonnelObserver::class);
}
```

Imports `App\Models\Personnel` + `App\Observers\PersonnelObserver` added at the top. Boot-method ordering: observers registered **after** listeners, consistent with Laravel idiom (events fire first, then observers layer behavior on them).

### Tests (`tests/Feature/Fras/PersonnelObserverTest.php` — 5 cases, 6 assertions)

1. **name change suppressed** — editing `name` alone produces 0 `EnrollPersonnelBatch` dispatches
2. **photo_hash change fires** — 2 online cameras → 2 dispatches
3. **category change fires** — 2 online cameras → 2 dispatches
4. **delete cascades to MQTT** — `$p->delete()` publishes DeletePersons to each online camera (2 publishes)
5. **hydration does NOT dispatch** — `Personnel::find()` never fires `saved` hook (Laravel reload isn't a save)

Each test uses `Queue::fake()` reset after factory creation because Laravel's `Attribute::get`-gated `photo_url` accessor does NOT mark photo_hash dirty on hydration — but a belt-and-suspenders reset guards against false positives.

## Task 3: AckHandler::handle() full body (commit eb14106)

Replaced the 28-line Phase-19 log-only scaffold with a 168-line handler implementing D-16 step-by-step:

1. **JSON decode** → `warning('Invalid ACK payload', ...)` on non-array
2. **messageId extract** → `warning('ACK missing messageId', ...)` on missing/empty
3. **device_id extract** from `mqtt/face/{device_id}/Ack` → `warning('ACK topic missing device_id', ...)` on malformed topic
4. **Camera lookup** by `device_id` → `warning('ACK for unknown camera', ...)` on miss
5. **Cache::pull** (`enrollment-ack:{$camera->id}:{$messageId}`) → `warning('ACK for unknown or expired messageId', ...)` on miss
6. **processSuccesses** over `info.AddSucInfo`: status → Done, enrolled_at → now(), photo_hash from cache's `photo_hashes[$customId]`, broadcast per row
7. **processFailures** over `info.AddErrInfo`: status → Failed, last_error from `$this->service->translateErrorCode((int) $entry['errorCode'])`, broadcast per row

### Idempotency (T-20-03-07)

`Cache::pull` is an atomic read+delete at the cache-layer level. Duplicate ACK delivery (MQTT QoS 0 re-send scenarios) sees the cache miss on the 2nd call and warn-logs; no duplicate row transitions. The idempotency test proves this with 2x `handle()` calls on the same payload producing exactly 1 `EnrollmentProgressed` dispatch (not 2).

### Constructor injection

The Phase 19 shell had a parameterless constructor; I added `public function __construct(private CameraEnrollmentService $service) {}`. Laravel's service container auto-resolves this — no container binding edit needed because `app(AckHandler::class)` calls resolve `CameraEnrollmentService` directly.

### Tests (`tests/Feature/Fras/AckHandlerTest.php` — 5 cases, 10 assertions)

1. **success correlation** — row Syncing → Done with photo_hash from cache + cache key consumed + broadcast
2. **failure correlation** — row Syncing → Failed with `last_error` containing "face" (errorCode 467 → "no face detected")
3. **duplicate idempotency** — 2x handle() = 1 broadcast (Cache::pull atomicity)
4. **expired cache warn** — cache miss → `warning('ACK for unknown or expired messageId', ...)` on mqtt channel
5. **unknown camera warn** — device_id miss → `warning('ACK for unknown camera', ...)` on mqtt channel

Tests 4 and 5 use `Mockery::spy(LoggerInterface::class)` bound via `Log::shouldReceive('channel')->andReturn($spy)` instead of the plan's `Log::spy()` — `Log::spy()` causes `channel()` to return null, which crashes the warn paths before they can be observed.

## Deviations from Plan

**1. [Rule 3 — Blocking] Queueable trait $queue property conflict**

- **Found during:** Task 1 RED-to-GREEN transition (silent exit 2 from pest)
- **Issue:** Plan specifies `public string $queue = 'fras';` on the job. The `Illuminate\Foundation\Queue\Queueable` trait already declares `public $queue;` (untyped) — a typed re-declaration in the composing class causes a trait-conflict fatal at class load (`Fatal error: ... define the same property ($queue). However, the definition differs and is considered incompatible.`)
- **Fix:** Removed the typed property declaration; set `$this->queue = 'fras'` in the constructor body after property promotion. `$job->queue === 'fras'` test assertion unchanged and green.
- **File:** `app/Jobs/EnrollPersonnelBatch.php` (L44-52)
- **Commit:** 15c3041

**2. [Rule 3 — Blocking] CameraEnrollmentService is final — Mockery unusable**

- **Found during:** Task 1 GREEN phase, second test case
- **Issue:** Plan's task-1 test uses `Mockery::mock(CameraEnrollmentService::class)` to verify `handle()` delegates. The service is declared `final` (FRAS port norm — the class has no intended extension points), which Mockery rejects with: "The class \App\Services\CameraEnrollmentService is marked final and its methods cannot be replaced."
- **Fix:** Replaced the mock-based assertion with an observable-side-effect assertion — `handle()` is verified by confirming exactly 1 MQTT publish with the correct topic + payload prefix + the Pending → Syncing row transition. This validates delegation through real service behavior without breaking final.
- **File:** `tests/Feature/Fras/EnrollPersonnelBatchTest.php` (test 2)
- **Commit:** 15c3041

**3. [Rule 1 — Bug] Log::spy() channel() returns null**

- **Found during:** Task 3 GREEN phase
- **Issue:** Plan's task-3 warn-log tests use `Log::spy()` + `Log::shouldHaveReceived('channel')->with('mqtt')`. `Log::spy()` creates a Mockery spy for the facade root, but calling `Log::channel('mqtt')` on a spy returns null by default — which causes the AckHandler's `Log::channel('mqtt')->warning(...)` call to fatal with "Call to a member function warning() on null" BEFORE the test can observe the facade call.
- **Fix:** Bound an explicit `Mockery::spy(LoggerInterface::class)` return value via `Log::shouldReceive('channel')->with('mqtt')->andReturn($channelSpy)`, then assert on `$channelSpy->shouldHaveReceived('warning')`. This lets the handler complete normally and reach the assertion.
- **File:** `tests/Feature/Fras/AckHandlerTest.php` (tests 4 + 5)
- **Commit:** eb14106

## Verification

- `./vendor/bin/pest tests/Feature/Fras/EnrollPersonnelBatchTest.php` → **3 passed, 11 assertions** in 1.20s
- `./vendor/bin/pest tests/Feature/Fras/PersonnelObserverTest.php` → **5 passed, 6 assertions** in 1.07s
- `./vendor/bin/pest tests/Feature/Fras/AckHandlerTest.php` → **5 passed, 10 assertions** in 1.29s
- `./vendor/bin/pest --group=fras` → **51 passed, 1 skipped** (147+ assertions total) in 3.37s
- `vendor/bin/pint --dirty --format agent` → clean after auto-fixes (FQCN hoisting only, no semantic changes)

### Grep-verifiable acceptance criteria

| Criterion | Expected | Actual |
|---|---|---|
| `$tries = 3` in job | 1 | 1 |
| `releaseAfter(30)->expireAfter(300)` | 1 | 1 |
| `public function failed(Throwable` | 1 | 1 |
| `enrollment-camera-'.\$this->camera->id` | 1 | 1 |
| `wasChanged(['photo_hash', 'category'])` in observer | 1 | 1 |
| `Personnel::observe(PersonnelObserver::class)` in AppServiceProvider | 1 | 1 |
| `Cache::pull` in AckHandler | 1 | 1 |
| `Log::channel('mqtt')->warning` in AckHandler | ≥3 | 5 |
| `EnrollmentProgressed::dispatch` in AckHandler | ≥2 | 2 |
| `translateErrorCode` in AckHandler | 1 | 1 (failure path only) |
| `Cache::get.*enrollment-ack` in `app/` | 0 | 0 (atomicity preserved) |

## Output Section Questions Answered

1. **Temporary stub from Plan 02 replaced (not extended)?** ✅ Yes. Full rewrite of `app/Jobs/EnrollPersonnelBatch.php` — the stub's constructor signature is preserved but the class body, docblock, trait usage, middleware method, handle body, and failed handler are all new.

2. **boot() registration pattern used?** Helper-method pattern: `$this->configureObservers()` added to `boot()` call list; the `configureObservers()` method added alongside existing `configureDefaults/configureGates/configureRateLimiters/configureEventListeners` per-method helpers. This mirrors the dominant v1.0 convention in the file.

3. **AckHandler constructor signature change?** ✅ Yes — Phase 19 shell had a parameterless constructor. Added `public function __construct(private CameraEnrollmentService $service) {}` using PHP 8 promoted property. Laravel's service container auto-resolves; no container binding edit needed.

4. **Cache TTL behavior under array store?** `Cache::pull` returned null on the 2nd call as expected — Laravel's `ArrayStore` (the default cache driver in `.env.testing`) implements `pull()` as the canonical `get + forget + return` idiom, so atomicity is behavior-correct even without Redis's single-round-trip LUA. The idempotency test validates this under the array driver; production on Redis gets true LUA atomicity for free.

5. **`config('hds.*')` leakage discovered?** None found in the AckHandler port path. The Phase 19 shell was a clean scaffold with no config references; the new body uses `config('fras.*')` only via `CameraEnrollmentService::translateErrorCode` (which itself has no config lookups — just a match expression). Grep `rg -n "config\('hds\." app/Mqtt/Handlers/` returns 0 matches.

## Commits

| Task | Hash | Summary |
|---|---|---|
| 1 | 15c3041 | feat(20-03): replace EnrollPersonnelBatch stub with FRAS port |
| 2 | f9a7412 | feat(20-03): add PersonnelObserver gated on photo_hash/category changes |
| 3 | eb14106 | feat(20-03): fill AckHandler::handle() with FRAS port + idempotent Cache::pull |

## Self-Check: PASSED

**Files verified present:**
- `app/Jobs/EnrollPersonnelBatch.php` (replaced) ✅
- `app/Observers/PersonnelObserver.php` ✅
- `app/Providers/AppServiceProvider.php` (modified) ✅
- `app/Mqtt/Handlers/AckHandler.php` (replaced) ✅
- `tests/Feature/Fras/EnrollPersonnelBatchTest.php` ✅
- `tests/Feature/Fras/PersonnelObserverTest.php` ✅
- `tests/Feature/Fras/AckHandlerTest.php` ✅

**Commits verified in git log:**
- 15c3041 (Task 1) ✅
- f9a7412 (Task 2) ✅
- eb14106 (Task 3) ✅
