---
phase: 22
plan: 04
subsystem: fras
tags: [dpa, retention, console-command, scheduler, service, wave-2]
requires: [22-01]
provides: [fras:purge-expired, FrasIncidentFactory::createFromRecognitionManual, fras_operator_promote trigger]
affects:
  - app/Console/Commands/FrasPurgeExpired.php
  - routes/console.php
  - app/Services/FrasIncidentFactory.php
tech_stack:
  added: []
  patterns:
    - "Per-event DB::transaction + cursor() streaming for memory-safe retention purge"
    - "Active-incident-protection query via whereNull OR whereHas terminalStatus — DPA legal wall"
    - "FrasPurgeRun summary row written on start + updated on finish (survives partial failures)"
    - "Additive service method (createFromRecognitionManual) — createFromRecognition untouched"
    - "abort(422) for operator-promote gate rejections (null personnel / allow-list)"
    - "event_data.trigger='fras_operator_promote' + audit fields (promoted_by_user_id, promoted_priority, promotion_reason) for DPA trace"
key_files:
  created:
    - app/Console/Commands/FrasPurgeExpired.php
    - tests/Feature/Fras/FrasPurgeExpiredCommandTest.php
    - tests/Feature/Fras/PromoteRecognitionEventTest.php
  modified:
    - routes/console.php
    - app/Services/FrasIncidentFactory.php
decisions:
  - "IncidentStatus enum has no Cancelled case in v1.0 — terminal-status helper collects Resolved + Cancelled (when present) defensively via enum case scan; future migration to add Cancelled requires no command-side change"
  - "Dropped --verbose from command signature — Symfony Console registers built-in -v/--verbose globally; custom --verbose raises LogicException. \$this->output->isVerbose() delivers the same behaviour through the built-in"
  - "\$skipped counter pre-counted BEFORE the delete passes (via separate COUNT query) so the summary row reflects the skipped population even under --dry-run where no deletes occur"
  - "Sub-threshold similarity (0.65) + Warning severity used in promote bypass test — proves manual override genuinely skips automatic gates rather than coincidentally passing them"
  - "Operator-promote gate rejections use abort(422) rather than returning null — HTTP layer in Plan 22-07 will propagate the response directly; Pest test asserts HttpException toThrow"
  - "Test file pins all 4 retention config keys in beforeEach — decouples this plan from Plan 22-03 config/fras.php MOD and lets this wave ship independently"
metrics:
  duration_minutes: 14
  tasks_completed: 2
  files_changed: 5
  tests_added: 15
  commits: 4
  completed_at: 2026-04-22
---

# Phase 22 Plan 04: FRAS Retention Purge + Operator Promote Factory Summary

**One-liner:** Ship `fras:purge-expired` artisan command with active-incident-protection + daily scheduler entry; add `FrasIncidentFactory::createFromRecognitionManual` for dispatcher-initiated promotion bypassing automatic gates — 15 Pest tests covering DPA retention semantics and operator-override audit trail.

## Objective

Deliver DPA-04 + DPA-05 retention surface: scene images >30d, face crops >90d, and `fras_access_log` rows >730d get purged daily at 02:00 Manila — UNLESS the event is linked to an open incident (non-terminal status). Plus the additive factory method for dispatcher-initiated promotion of sub-threshold events with a full audit trail in the timeline `event_data` blob.

## What Shipped

### Task 1 — `fras:purge-expired` command + scheduler

- **Command** `app/Console/Commands/FrasPurgeExpired.php` (signature `fras:purge-expired {--dry-run}`):
  - `handle(): int` — opens a `FrasPurgeRun` row with `started_at=now()` and `dry_run=$this->option('dry-run')`, then walks four query passes.
  - **Pass 1** — pre-count skipped-for-active-incident (events inside either retention window whose Incident has non-terminal status).
  - **Pass 2 — scene** — `RecognitionEvent::query()->whereNotNull('scene_image_path')->where('captured_at', '<', now()->subDays($sceneDays))->where($protectQuery)->cursor()->each(...)`; per row `DB::transaction(fn() => [Storage::disk('fras_events')->delete($path); $event->update(['scene_image_path' => null])])`.
  - **Pass 3 — face** — same shape on `face_image_path` + `face_crop_days` (default 90).
  - **Pass 4 — access log** — `FrasAccessLog::query()->where('accessed_at', '<', now()->subDays($logDays))->delete()` (skipped under `--dry-run`).
  - On finish: `$run->update(['finished_at' => now(), 4 counters, 'error_summary' => null])`. Returns `self::SUCCESS` or `self::FAILURE` on catch.
  - Active-incident-protection closure: `whereNull('incident_id')->orWhereHas('incident', fn ($i) => $i->whereIn('status', $terminalStatuses))` — where `$terminalStatuses` is `[IncidentStatus::Resolved]` plus `Cancelled` when/if the enum case exists (defensive scan via `IncidentStatus::cases()`).
- **Scheduler** `routes/console.php`:
  ```php
  Schedule::command('fras:purge-expired')
      ->dailyAt((string) config('fras.retention.purge_run_schedule', '02:00'))
      ->timezone('Asia/Manila')
      ->withoutOverlapping()
      ->onFailure(fn () => Log::error('FRAS retention purge failed'))
      ->description('Purge expired FRAS face/scene images per DPA retention policy');
  ```
- **Test file** `tests/Feature/Fras/FrasPurgeExpiredCommandTest.php` — 7 tests (27 assertions):
  1. purges face crops older than retention window
  2. purges scene images older than retention window
  3. keeps face crop younger than retention window
  4. **survives expired scene image when linked Incident is still Dispatched** (SC5 mandatory)
  5. --dry-run performs no deletes but writes summary row
  6. purges fras_access_log rows older than retention
  7. respects env override for face_crop_days

### Task 2 — `FrasIncidentFactory::createFromRecognitionManual`

- **Service method** `app/Services/FrasIncidentFactory.php`:
  - Additive only — `createFromSensor` (Phase 21 D-35) and `createFromRecognition` (Phase 21 D-07) unchanged.
  - Signature: `createFromRecognitionManual(RecognitionEvent $event, IncidentPriority $priority, string $reason, User $actor): Incident`.
  - **Gate** — reject null personnel with `abort(422, 'Cannot promote: no personnel match.')`; reject `PersonnelCategory::Allow` with `abort(422, 'Cannot promote: allow-list match.')`.
  - **Bypass** — severity + confidence + dedup gates are skipped (operator's override explicitly overrides the automatic chain).
  - **Write path** — DB::transaction producing Incident + IncidentTimeline. Timeline `event_data` carries the standard 7-field FRAS shape plus 3 audit fields (`trigger='fras_operator_promote'`, `promoted_by_user_id`, `promoted_priority`, `promotion_reason`).
  - Notes field formatted as `{standard formatNotes output} — Manually promoted by {$actor->name}: {$reason}`.
  - Dispatches `IncidentCreated` + `RecognitionAlertReceived` (same 2-event shape as the automatic path).
- **Test file** `tests/Feature/Fras/PromoteRecognitionEventTest.php` — 8 tests (21 assertions):
  1. promotes with operator-picked priority + reason
  2. bypasses severity gate — Warning severity + sub-threshold similarity still promotes
  3. rejects promotion when personnel_id is null (HttpException 422)
  4. rejects promotion for allow-category personnel (HttpException 422)
  5. writes `fras_operator_promote` trigger + audit fields on the timeline entry
  6. dispatches `IncidentCreated` and `RecognitionAlertReceived`
  7. appends actor attribution + reason to the incident notes field
  8. links the promoted event to the new incident

## Commits

| Hash | Message |
|------|---------|
| 2558b5e | test(22-04): add failing FrasPurgeExpiredCommandTest (RED) |
| 66c75c1 | feat(22-04): add fras:purge-expired command + schedule (GREEN) |
| 565f5c2 | test(22-04): add failing PromoteRecognitionEventTest (RED) |
| 9d6ae18 | feat(22-04): add FrasIncidentFactory::createFromRecognitionManual (GREEN) |

## Verification

| Check | Result |
|-------|--------|
| `php artisan test --compact tests/Feature/Fras/FrasPurgeExpiredCommandTest.php` | 7 passed (27 assertions) |
| `php artisan test --compact tests/Feature/Fras/PromoteRecognitionEventTest.php` | 8 passed (21 assertions) |
| `php artisan test --compact tests/Feature/Fras/FrasIncidentFactoryTest.php` | 11 passed (36 assertions) — Phase 21 regression green |
| `php artisan list \| grep 'fras:purge-expired'` | command registered, description matches |
| `php artisan schedule:list -v \| grep 'fras:purge-expired'` | entry present, Next Due 18:00 UTC = 02:00 Asia/Manila |
| `grep 'skipped_for_active_incident' app/Console/Commands/FrasPurgeExpired.php` | 2 matches (default 0 on create, count on finish) |
| `grep 'IncidentStatus::Resolved' app/Console/Commands/FrasPurgeExpired.php` | match (protection query) |
| `grep 'fras_operator_promote' app/Services/FrasIncidentFactory.php` | match (D-13 trigger marker) |
| `grep 'Manually promoted by' app/Services/FrasIncidentFactory.php` | match (notes suffix) |
| `grep 'function createFromRecognition\b' app/Services/FrasIncidentFactory.php` | match (Phase 21 method untouched) |
| `vendor/bin/pint --dirty --format agent` | pass |

**SC5 mandatory test output (verbatim from test suite):**
```
✓ it survives expired scene image when linked Incident is still Dispatched
```

## TDD Gate Compliance

- **RED gate 1:** `2558b5e` — FrasPurgeExpiredCommandTest committed failing ("command 'fras:purge-expired' does not exist") before any implementation.
- **GREEN gate 1:** `66c75c1` — command + schedule landed; all 7 tests pass on first run (after --verbose fix described in deviations).
- **RED gate 2:** `565f5c2` — PromoteRecognitionEventTest committed failing ("undefined method createFromRecognitionManual") before factory method.
- **GREEN gate 2:** `9d6ae18` — factory method landed; all 8 tests pass.
- No REFACTOR gate required — Pint passed without rewrites.

## Deviations from Plan

### Auto-fixed issues

**1. [Rule 1 - Bug] `--verbose` collides with Symfony Console built-in**
- **Found during:** Task 1 GREEN run
- **Issue:** Plan signature `fras:purge-expired {--dry-run} {--verbose}` raised `LogicException("An option named 'verbose' already exists.")` on every test run. Laravel's base `Command` class already registers `-v/--verbose` globally through Symfony Console.
- **Fix:** Dropped `--verbose` from the signature. Replaced the `$this->option('verbose')` check with `$this->output->isVerbose()` which is the canonical Laravel way to detect verbosity and responds to the built-in `-v` flag.
- **Files modified:** `app/Console/Commands/FrasPurgeExpired.php`
- **Commit:** Rolled into `66c75c1` (applied during GREEN before commit).

**2. [Rule 3 - Blocking] Composer vendor/ missing in worktree**
- **Found during:** Task 1 RED run
- **Issue:** `php artisan` aborted with "vendor/autoload.php not found" — worktree shipped without composer install (same pre-condition as Plan 22-01 deviation #1).
- **Fix:** Ran `composer install --no-interaction --prefer-dist`.
- **Files modified:** `vendor/` (not tracked), no composer.lock change.
- **Commit:** n/a (local-only install).

**3. [Rule 1 - Plan Drift] IncidentStatus::Cancelled absent in v1.0**
- **Found during:** Task 1 GREEN draft
- **Issue:** Plan's protection query references `[IncidentStatus::Resolved, IncidentStatus::Cancelled]` but the enum has no Cancelled case (only Pending/Triaged/Dispatched/Acknowledged/EnRoute/OnScene/Resolving/Resolved).
- **Fix:** `terminalIncidentStatuses()` helper builds the list defensively — always includes Resolved; scans `IncidentStatus::cases()` and appends any case whose name is 'Cancelled'. Future migration to add the case requires no command-side change, and grep `IncidentStatus::Resolved` / `Cancelled` in the file still passes the acceptance check.
- **Files modified:** `app/Console/Commands/FrasPurgeExpired.php` (inline, no separate commit).

### Deferred / out-of-scope observations

- **10 Vite-manifest fras-group failures** — Same pre-existing failures documented in Plan 22-01 Summary deviation (worktree has no built frontend assets). Confirmed unrelated to this plan: `IntakeStationFrasRailTest`, Phase 20 admin Inertia tests, etc. all fail with `ViteManifestNotFoundException`. My 15 new tests and the 11 Phase-21 regression tests all pass. No action taken.

- **`schedule:list` output doesn't echo `Asia/Manila` / `02:00`** — Laravel converts `->timezone('Asia/Manila')->dailyAt('02:00')` to UTC-anchored cron (`0 18 * * *`) for display. The plan's acceptance criterion grep on schedule:list output would fail literally, but the intent (daily at 02:00 Manila with no overlap) is fully satisfied: source file contains both literal strings; `schedule:list -v` shows "Next Due: 18:00:00 +00:00" (= Manila 02:00). Flagged but not corrected — the schedule binding is correct.

## Auth Gates

None. Command is scheduler-invoked (no HTTP layer); service method is called from test directly (the controller route + Gate check lands in Plan 22-07 per the threat model T-22-04-04 mitigation plan).

## Known Stubs

None. Both commits ship complete, tested, production-shape code.

## Threat Flags

None. No new network endpoints, auth paths, or schema changes at trust boundaries introduced outside the plan's declared `<threat_model>` scope. T-22-04-01 (T — purge deletes evidence of an open case) is mitigated by the SC5 test and the active-incident-protection query. T-22-04-02 (T — concurrent scheduler invocations) is mitigated by `->withoutOverlapping()`. T-22-04-03 (R — purge row lost on partial failure) is mitigated by creating FrasPurgeRun FIRST then updating on finish; the catch block stores error_summary so the run row is retrievable even on per-event transaction failures.

## Self-Check: PASSED

- app/Console/Commands/FrasPurgeExpired.php: FOUND
- routes/console.php (fras:purge-expired schedule): FOUND
- app/Services/FrasIncidentFactory.php (createFromRecognitionManual): FOUND
- tests/Feature/Fras/FrasPurgeExpiredCommandTest.php: FOUND
- tests/Feature/Fras/PromoteRecognitionEventTest.php: FOUND
- Commit 2558b5e: FOUND
- Commit 66c75c1: FOUND
- Commit 565f5c2: FOUND
- Commit 9d6ae18: FOUND
