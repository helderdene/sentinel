---
phase: 22
plan: 03
subsystem: fras
tags: [dpa, signed-url, audit-log, retention, controller, wave-2, tdd]
requires:
  - 22-01 (Wave 1 — provides FrasAccessLog model + FrasAccessSubject/FrasAccessAction enums + fras_access_log table)
  - 22-02 (Wave 1 — provides FRAS gates / Inertia share, no direct binding here but co-wave)
provides:
  - FrasEventFaceController wrapped with sync fras_access_log write (D-16)
  - FrasEventSceneController (new) at /fras/events/{event}/scene (fras.events.scene.show)
  - config/fras.php retention section with 4 env-backed keys (scene_image_days, face_crop_days, purge_run_schedule, access_log_retention_days)
  - .env.example FRAS retention knobs documented
  - 10 Pest feature tests (5 per suite) green; FrasPhotoAccessController untouched (RESEARCH reconciliation #3)
affects:
  - app/Http/Controllers/FrasEventFaceController.php (MOD)
  - routes/fras.php (new route registered)
tech-stack:
  added: []
  patterns:
    - DB::transaction sync-wrap around audit write — DB failure aborts stream, never silently skips audit (D-16)
    - Cache-Control tightened to `private, no-store, max-age=0` to eliminate proxy/CDN caching past signed-URL TTL (T-22-03-04)
    - Clone-by-shape controller pair (Face + Scene) — two enum values (RecognitionEventFace / RecognitionEventScene) diverge inside otherwise-identical handlers
    - Symfony normalises Cache-Control directive order alphabetically — tests compare as a set via array_map/explode rather than raw string equality
key-files:
  created:
    - app/Http/Controllers/FrasEventSceneController.php
    - tests/Feature/Fras/FrasAccessLogTest.php
    - tests/Feature/Fras/SignedUrlSceneImageTest.php
  modified:
    - app/Http/Controllers/FrasEventFaceController.php
    - routes/fras.php
    - config/fras.php
    - .env.example
decisions:
  - "22-03-D1: Tests reference current route name `fras.event.face` (Phase 21) rather than the planner-speculated `fras.events.face.show` — `php artisan route:list --name=fras` confirmed the singular-noun name is what's currently registered; Plan 22-05 can later rename-align if needed. Preserves test green at commit time (plan §action explicitly instructed this)"
  - "22-03-D2: Cache-Control assertion compares directives as a set (not literal string) — Symfony's HeaderUtils sorts Cache-Control alphabetically at serialization, producing `max-age=0, no-store, private` on the wire. Literal equality would fail under any version bump that touches HeaderUtils ordering; set-wise comparison is version-robust without weakening the contract"
  - "22-03-D3: Transaction-rollback test uses `DB::shouldReceive('transaction')->andThrow(RuntimeException)` with pest `->throws()` assertion rather than asserting HTTP 500. `withoutExceptionHandling()` re-raises the exception past Laravel's handler, proving the stream NEVER returned. HTTP 500 assertion would require the handler layer to absorb the exception, which obscures the sync-wrap guarantee"
  - "22-03-D4: Scene controller top-level namespace `App\\Http\\Controllers` (not `App\\Http\\Controllers\\Fras\\`) — matches the existing FrasEventFaceController placement for consistency; Plan 22-05 FrasAlertFeedController will follow the same convention"
metrics:
  duration_min: 8
  tasks: 2
  commits: 3
  files_created: 3
  files_modified: 4
  tests_added: 10
  tests_passed: 10
  completed_date: "2026-04-22"
---

# Phase 22 Plan 03: DPA Signed-URL + Audit-Log Backbone — Summary

**One-liner:** Wave 2 Plan 1 of 2 lands the DPA-grade sync `fras_access_log` wrap on FrasEventFaceController, ships a twin FrasEventSceneController for scene images (operator/supervisor/admin only — defense-in-depth layer 1 of 3), tightens Cache-Control to eliminate proxy caching past signed-URL TTL, and seeds the `retention` config section that drives Plan 22-04's purge command.

## What Shipped

**Controllers (2 files — 1 MOD, 1 NEW):**

- `FrasEventFaceController.php` — TODO(Phase 22) line REPLACED with `DB::transaction(function () { FrasAccessLog::create([...]); })` writing `actor_user_id`, `ip_address`, `user_agent` (substr 255), `subject_type=RecognitionEventFace`, `subject_id=$event->id`, `action=View`, `accessed_at=now()`. Cache-Control header TIGHTENED from `private, max-age=60` to `private, no-store, max-age=0`. All abort_unless guards preserved.
- `FrasEventSceneController.php` — NEW top-level controller at `App\Http\Controllers` namespace cloned verbatim from the wrapped Face controller, diverging only on `$event->scene_image_path` + `FrasAccessSubject::RecognitionEventScene`. Same `[Operator,Supervisor,Admin]` role gate excludes responders (layer 1 of 3 per D-26). Same `fras_events` disk.

**Routes (1 file — MOD):**

- `routes/fras.php` — new `Route::get('fras/events/{event}/scene', ...)->middleware('signed')->name('fras.events.scene.show')` appended alongside existing face route; both live under `role:operator,supervisor,admin` middleware chain from bootstrap/app.php.

**Config (2 files — MOD):**

- `config/fras.php` — 6th section `retention` appended with 4 env-backed keys: `scene_image_days` (30), `face_crop_days` (90), `purge_run_schedule` ('02:00'), `access_log_retention_days` (730).
- `.env.example` — FRAS retention block documented with defaults so CDRRMO ops knows the knobs without reading config/fras.php.

**Tests (2 files — NEW, 10 tests total):**

- `FrasAccessLogTest.php` — 5 tests: sync row write + actor/subject/ip assertions; DB::transaction rollback aborts stream; expired signed URL → 403; responder role → 403; two consecutive fetches append 2 distinct rows (append-only proof).
- `SignedUrlSceneImageTest.php` — 5 tests: operator valid URL → 200 + 1 log row subject_type=RecognitionEventScene; responder → 403 (layer 1); dispatcher → 403; expired signature → 403; missing `scene_image_path` → 404.

## Commits

| Hash | Message |
|------|---------|
| 9aa2ca2 | test(22-03): add failing audit-log + scene signed-URL tests |
| 606c422 | feat(22-03): wrap Face controller + add Scene controller with sync audit log |
| ffc776e | feat(22-03): add retention section to config/fras.php + .env.example |

## Verification Results

| Check | Result |
|-------|--------|
| `php artisan test --compact --filter=FrasAccessLogTest` | 5 passed (21 assertions) + 1 Wave 0 placeholder skipped |
| `php artisan test --compact --filter=SignedUrlSceneImageTest` | 5 passed (15 assertions) + 1 Wave 0 placeholder skipped |
| `php artisan test --compact --filter=FrasPhotoAccessControllerTest` | 5 passed (6 assertions) — Phase 20 regression green |
| `php artisan test --compact --group=fras` (re-run, deflake) | 198 passed, 12 skipped, 0 failed, 681 assertions |
| `php artisan config:show fras.retention` | 4 keys present (scene_image_days, face_crop_days, purge_run_schedule, access_log_retention_days) |
| `php artisan config:show fras` | all 6 sections (mqtt, cameras, enrollment, photo, recognition, retention) present |
| `vendor/bin/pint --dirty --format agent` | `{"result":"pass"}` (no formatting drift) |

**Grep contracts:**

- `grep -c "FrasAccessLog::create" app/Http/Controllers/FrasEventFaceController.php` → 1
- `grep -c "TODO(Phase 22)" app/Http/Controllers/FrasEventFaceController.php` → 0 (removed)
- `grep -c "private, no-store, max-age=0" app/Http/Controllers/FrasEventFaceController.php` → 1
- `grep -c "RecognitionEventScene" app/Http/Controllers/FrasEventSceneController.php` → 1
- `grep -c "scene_image_path" app/Http/Controllers/FrasEventSceneController.php` → 3
- `grep -c "'retention' =>" config/fras.php` → 1
- FRAS_RETENTION_SCENE_IMAGE_DAYS / FRAS_RETENTION_FACE_CROP_DAYS / FRAS_PURGE_RUN_SCHEDULE / FRAS_ACCESS_LOG_RETENTION_DAYS in `.env.example` → each 1
- `git diff HEAD~3 HEAD -- app/Http/Controllers/FrasPhotoAccessController.php` → empty (RESEARCH reconciliation #3 honored)

## TDD Gate Compliance

- RED gate: commit `9aa2ca2` registers both failing suites before any controller changes. Face suite failed on Cache-Control header mismatch + rollback exception absent + append-only row count = 0; Scene suite failed with RouteNotFoundException for `fras.events.scene.show` (route not yet defined). Gate observable in git log.
- GREEN gate: commit `606c422` (+ small test fix for Symfony Cache-Control ordering) makes both suites pass 5/5 (10 tests, 36 assertions) without modifying the RED contracts.
- REFACTOR gate: none needed — the GREEN implementation was already minimal (no drift from PATTERNS.md §Wave 2 §FrasEventFaceController MOD excerpt).

## Implementation Notes

### Cache-Control Directive Ordering

The plan specified `Cache-Control: private, no-store, max-age=0` as a literal assertion target. Symfony's `Response::$headers->get()` returns what `HeaderUtils::toString()` produces, which sorts Cache-Control directives alphabetically — so the wire form is `max-age=0, no-store, private`. The initial test used `$response->assertHeader('Cache-Control', 'private, no-store, max-age=0')` and failed on the ordering. Rewrote to compare as a directive set via `array_map('trim', explode(',', ...))` then `expect(...)->toContain('private', 'no-store', 'max-age=0')` — version-robust without weakening the contract (T-22-03-04 proxy-cache mitigation still fully asserted).

The grep-based acceptance criterion (`grep 'private, no-store, max-age=0' app/Http/Controllers/FrasEventFaceController.php`) matches the source code (where the directive order IS as-written), which is what was verified at commit time.

### DB::transaction Rollback Test Shape

The rollback test uses `DB::shouldReceive('transaction')->once()->andThrow(new RuntimeException('db down'))` paired with Pest's `->throws(RuntimeException::class, 'db down')` test modifier and `withoutExceptionHandling()`. This proves:

1. The sync-wrap IS a `DB::transaction` call (Mockery shouldReceive requires it).
2. The stream handler never returns when the transaction throws — the exception propagates past the controller without a `$disk->response(...)` call ever being constructed.
3. No `FrasAccessLog` row persists (because the transaction would have rolled back at the Mockery layer even if we'd let it proceed).

Alternative was `assertStatus(500)` after letting Laravel's handler absorb the exception, but that obscures the sync-wrap guarantee — a buggy catch block upstream could swallow the rollback and silently serve the image.

### Route Name Preservation

Per plan §action, ran `php artisan route:list --name=fras` BEFORE writing tests. Output showed `fras.event.face` (Phase 21 singular-noun name), not the planner-speculated `fras.events.face.show`. Tests use the current registered name; no route rename in this plan. Plan 22-05 can later align naming if UI-SPEC prefers the plural form.

### Worktree Environment Setup

This plan executed in a parallel worktree. Initial clone had no `vendor/`, `node_modules/`, or `public/build/`. Copied `vendor/` from the main repo (full copy so `composer autoload` $baseDir resolves inside the worktree), symlinked `node_modules/` and `public/build/` (both generated content, safe to symlink). None of these are tracked — they're executor-local concerns. See 22-02-SUMMARY.md for the full rationale (Wave 1 precedent).

## Deviations from Plan

### Plan deviations

**1. [Plan correction - route name] Tests use `fras.event.face` (current) instead of `fras.events.face.show` (planned)**

- **Found during:** Task 1 RED test authoring
- **Issue:** Plan §action line 124 suggested using `fras.events.face.show` OR current name (said "prefer the current name"). Plan §action §action-block line 136 explicitly reconfirmed: "If the name is `fras.event.face`, tests use that." I ran `php artisan route:list --name=fras` and confirmed `fras.event.face` is registered, so tests use that.
- **Action:** None — the plan explicitly endorsed this path. Documented here for traceability.
- **Files:** tests/Feature/Fras/FrasAccessLogTest.php
- **Commit:** 9aa2ca2

**2. [Rule 1 - bug] Cache-Control test assertion normalisation (see §Cache-Control Directive Ordering above)**

- **Found during:** Task 1 GREEN first run
- **Issue:** Literal-string header assertion failed because Symfony sorts directives alphabetically at response serialization.
- **Fix:** Rewrote assertion to directive-set comparison. Contract (T-22-03-04) preserved.
- **Files:** tests/Feature/Fras/FrasAccessLogTest.php, tests/Feature/Fras/SignedUrlSceneImageTest.php
- **Commit:** Rolled into 606c422 (pre-commit fix).

### Out-of-scope observations

**AckHandlerTest PostgreSQL RefreshDatabase deadlock (flaky, pre-existing)**

- Full fras-group run #1: 13 spurious QueryException failures in EnrollPersonnelBatchTest, EnumCheckParityTest, EscalateToP1Test. Full-group run #2: 0 failures, 198 passed.
- Documented in 22-01-SUMMARY.md (Wave 1) as a pre-existing PostgreSQL RefreshDatabase deadlock predating Phase 22. Re-running the affected tests in isolation passes cleanly. Not introduced by this plan.

## Auth Gates

None encountered — all execution is pure controller + config + test code with no external-service interaction.

## Known Stubs

None — every shipped code path is fully wired. Every test assertion corresponds to an actual production behavior guarantee (DB::transaction sync wrap, role gate, signed-URL expiry, append-only log, proxy-cache mitigation).

## Threat Flags

None — the plan's `<threat_model>` fully covered all surface introduced (T-22-03-01 through T-22-03-06). No new network endpoints beyond the planned scene route, no new auth paths, no new file access patterns outside `fras_events` disk, no schema changes. FrasPhotoAccessController was explicitly left untouched (T-22-03-06 mitigation grep criterion holds).

## Self-Check: PASSED

**Files created (verified present):**

- `app/Http/Controllers/FrasEventSceneController.php` — FOUND
- `tests/Feature/Fras/FrasAccessLogTest.php` — FOUND
- `tests/Feature/Fras/SignedUrlSceneImageTest.php` — FOUND

**Files modified (verified diff non-empty):**

- `app/Http/Controllers/FrasEventFaceController.php` — TODO removed, FrasAccessLog::create + DB::transaction added, Cache-Control tightened
- `routes/fras.php` — fras.events.scene.show route registered
- `config/fras.php` — retention section appended (4 keys)
- `.env.example` — 4 FRAS_* retention env vars documented

**Commits (verified in `git log`):**

- `9aa2ca2` test(22-03): add failing audit-log + scene signed-URL tests — FOUND
- `606c422` feat(22-03): wrap Face controller + add Scene controller with sync audit log — FOUND
- `ffc776e` feat(22-03): add retention section to config/fras.php + .env.example — FOUND
