---
phase: 17-laravel-12-13-upgrade
plan: 01
subsystem: testing

tags:
  - pest
  - snapshot-testing
  - broadcasting
  - laravel-12
  - determinism

requires:
  - phase: 03-real-time-infra
    provides: 6 v1.0 broadcast events (IncidentCreated, IncidentStatusChanged, AssignmentPushed, UnitStatusChanged, ChecklistUpdated, ResourceRequested) with stable broadcastWith() payloads

provides:
  - 6 byte-identical JSON golden fixtures captured on Laravel 12.54.1 under tests/Feature/Broadcasting/__snapshots__/
  - Regression baseline for FRAMEWORK-02 (pre/post L13 upgrade payload parity check)
  - Pest snapshot-test convention for IRMS (Carbon::setTestNow + factory pinning + file-fixture idiom)

affects:
  - 17-02-framework-bump (Wave 2 — snapshot tests MUST still pass on L13)
  - 17-03-package-bumps (Wave 3 — snapshot tests MUST still pass after Horizon/Reverb/Fortify/Wayfinder/Inertia/Boost/Magellan bumps)

tech-stack:
  added: []
  patterns:
    - "Pest snapshot pattern: construct event with `new Event(...)->broadcastWith()`, json_encode with JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES, byte-compare against file fixture"
    - "Determinism: Carbon::setTestNow(fixed instant) beats freezeTime() for byte-identical fixtures across runs"
    - "Factory pinning: ALL fake()-generated fields (coordinates, priority, channel, caller_*, Unit type/agency/shift/crew_capacity) explicitly overridden via ->create([...])"

key-files:
  created:
    - tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php
    - tests/Feature/Broadcasting/IncidentTriagedSnapshotTest.php
    - tests/Feature/Broadcasting/UnitAssignedSnapshotTest.php
    - tests/Feature/Broadcasting/UnitStatusChangedSnapshotTest.php
    - tests/Feature/Broadcasting/ChecklistUpdatedSnapshotTest.php
    - tests/Feature/Broadcasting/ResourceRequestedSnapshotTest.php
    - tests/Feature/Broadcasting/__snapshots__/IncidentCreated.json
    - tests/Feature/Broadcasting/__snapshots__/IncidentTriaged.json
    - tests/Feature/Broadcasting/__snapshots__/UnitAssigned.json
    - tests/Feature/Broadcasting/__snapshots__/UnitStatusChanged.json
    - tests/Feature/Broadcasting/__snapshots__/ChecklistUpdated.json
    - tests/Feature/Broadcasting/__snapshots__/ResourceRequested.json
  modified: []

key-decisions:
  - "Used Carbon::setTestNow(fixed ISO instant) instead of plan-prescribed freezeTime() — freezeTime pins Carbon::now() at test start, which still differs run-to-run, breaking byte-compare on created_at (Pitfall 1 fix)"
  - "Pinned every UnitFactory random field explicitly (type, agency, crew_capacity, shift, coordinates) — factory randomizes 5 fields by default"
  - "AssignmentPushed constructor actually takes (incident, unitId, userId) — plan text said assignmentId but code + BroadcastEventTest.php use userId; used 42 as pinned userId"
  - "Used ResourceType::AdditionalAmbulance (the first enum case) for ResourceRequested fixture"
  - "Pinned checklist_pct=75 on Incident — column exists but IncidentFactory leaves it NULL; explicit pin prevents NULL-vs-int ambiguity in ChecklistUpdated payload"

patterns-established:
  - "Fixture convention: tests/Feature/Broadcasting/__snapshots__/{EventName}.json matches Jest-style __snapshots__/ directory"
  - "First-run write-through: file_exists check writes fixture and markTestIncomplete; second run byte-compares"
  - "Clock pin value: Carbon::parse('2026-04-21T00:00:00Z') — arbitrary but fixed; reuse across all 6 tests so the timestamp column matches across fixtures"

requirements-completed: [FRAMEWORK-02]

# Metrics
duration: 5min
completed: 2026-04-21
---

# Phase 17 Plan 01: Broadcast Event Snapshot Baseline Summary

**Six byte-identical JSON golden fixtures captured on Laravel 12.54.1 — regression oracle for the L12 → L13 upgrade (Commit 1 of D-04).**

## Performance

- **Duration:** 5 min
- **Started:** 2026-04-21T05:14:31Z
- **Completed:** 2026-04-21T05:19:47Z
- **Tasks:** 2
- **Files modified:** 12 (all new)

## Accomplishments

- Six Pest feature tests under `tests/Feature/Broadcasting/` (IncidentCreated, IncidentTriaged, UnitAssigned, UnitStatusChanged, ChecklistUpdated, ResourceRequested).
- Six golden JSON fixtures under `tests/Feature/Broadcasting/__snapshots__/` captured on L12 and committed as the regression baseline for FRAMEWORK-02.
- All six snapshot tests pass byte-identically across three consecutive runs with zero fixture drift (git status shows no modified snapshot files after successive `php artisan test` invocations).
- `composer.json` UNCHANGED — repo remains on `laravel/framework ^12.0` (Commit 1 discipline of D-04).

## Task Commits

Each task was committed atomically:

1. **Task 1: IncidentCreated + IncidentTriaged snapshot tests** — `ca937b4` (test)
2. **Task 2: UnitAssigned + UnitStatusChanged + ChecklistUpdated + ResourceRequested snapshot tests** — `9740fa9` (test)

## Files Created/Modified

- `tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php` — Snapshot assertion for `IncidentCreated` using factory-pinned Incident with frozen clock.
- `tests/Feature/Broadcasting/IncidentTriagedSnapshotTest.php` — Snapshot assertion for `IncidentStatusChanged(PENDING→TRIAGED)` (D-08 IncidentTriaged mapping).
- `tests/Feature/Broadcasting/UnitAssignedSnapshotTest.php` — Snapshot assertion for `AssignmentPushed(incident, 'AMB-01', 42)` (D-08 UnitAssigned mapping).
- `tests/Feature/Broadcasting/UnitStatusChangedSnapshotTest.php` — Snapshot assertion for `UnitStatusChanged(unit, UnitStatus::Available)` with Unit pinned to DISPATCHED.
- `tests/Feature/Broadcasting/ChecklistUpdatedSnapshotTest.php` — Snapshot assertion for `ChecklistUpdated(incident)` with pinned `checklist_pct=75`.
- `tests/Feature/Broadcasting/ResourceRequestedSnapshotTest.php` — Snapshot assertion for `ResourceRequested(incident, ResourceType::AdditionalAmbulance, 'Additional unit needed', requester)`.
- `tests/Feature/Broadcasting/__snapshots__/IncidentCreated.json` — 16-field payload (id, incident_no, priority, status, incident_type_id, incident_type, location_text, barangay, channel, coordinates, caller_name, caller_contact, notes, created_at).
- `tests/Feature/Broadcasting/__snapshots__/IncidentTriaged.json` — 10-field payload (id, incident_no, old_status, new_status, priority, timestamp nullables).
- `tests/Feature/Broadcasting/__snapshots__/UnitAssigned.json` — 11-field AssignmentPushed payload.
- `tests/Feature/Broadcasting/__snapshots__/UnitStatusChanged.json` — 4-field payload (id, callsign, old_status, new_status).
- `tests/Feature/Broadcasting/__snapshots__/ChecklistUpdated.json` — 3-field payload (incident_id, incident_no, checklist_pct).
- `tests/Feature/Broadcasting/__snapshots__/ResourceRequested.json` — 7-field payload including pinned `timestamp` from frozen clock.

## Decisions Made

- **Carbon::setTestNow instead of freezeTime():** First pass with plan-prescribed `freezeTime()` showed `created_at` drift between consecutive runs (diff of 5s observed). Root cause: `freezeTime()` pins Carbon::now() at *test start*, which differs run-to-run. Swapped to `Carbon::setTestNow(Carbon::parse('2026-04-21T00:00:00Z'))` for an absolute fixed instant. This is the textbook fix for Pitfall 1 in 17-RESEARCH.md §Common Pitfalls.
- **AssignmentPushed constructor signature:** Plan/research said `($incident, $unitId, $assignmentId)` but the actual code in `app/Events/AssignmentPushed.php` uses `$userId` (int). Used `42` as pinned userId (matches existing BroadcastEventTest.php pattern).
- **ResourceType choice:** Used `ResourceType::AdditionalAmbulance` — first declared enum case, pinned for fixture stability.
- **Clock pin value is shared across all 6 tests:** `2026-04-21T00:00:00Z` — keeps fixture timestamps visually consistent if they ever need to be inspected/diffed.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Replaced `freezeTime()` with `Carbon::setTestNow(fixed instant)` for true determinism**
- **Found during:** Task 1 (second-pass run of IncidentCreated snapshot test)
- **Issue:** Plan prescribed `freezeTime()` from `Pest\Laravel\freezeTime`. First run wrote fixture with `"created_at": "2026-04-21T05:15:32.000000Z"`. Second run — supposed to byte-compare — failed with `"created_at": "2026-04-21T05:15:37.000000Z"` (5-second drift). `freezeTime()` freezes to `Carbon::now()` at test-start, but test-start shifts between invocations.
- **Fix:** Replaced `use function Pest\Laravel\freezeTime;` + `beforeEach(fn () => freezeTime())` with `use Illuminate\Support\Carbon;` + `beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-04-21T00:00:00Z')))`. Pins to an absolute instant that does not depend on wall-clock time.
- **Files modified:** `tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php`, `tests/Feature/Broadcasting/IncidentTriagedSnapshotTest.php`. Same pattern applied pre-emptively to the 4 Task 2 tests.
- **Verification:** `php artisan test --compact tests/Feature/Broadcasting/` green on three consecutive runs; `git status tests/Feature/Broadcasting/__snapshots__/` empty between runs (no drift).
- **Committed in:** `ca937b4` (baked into the Task 1 commit before it was committed).

**2. [Rule 2 - Missing critical determinism] Pinned all UnitFactory random fields in UnitStatusChangedSnapshotTest**
- **Found during:** Task 2 preparation (pre-flight factory audit per Pitfall 1)
- **Issue:** `UnitFactory::definition()` randomizes 5 fields (type, agency, crew_capacity, coordinates lat/lng, shift). Only `id`, `callsign`, `status` were in the plan's pin list. Without pinning the others, each run could produce a different row in the units table, and even though `UnitStatusChanged.broadcastWith()` only reads id/callsign/status, the INSERT itself would randomize those fields.
- **Fix:** Pinned `type => UnitType::Ambulance`, `agency => 'CDRRMO'`, `crew_capacity => 4`, `coordinates => Point::makeGeodetic(8.9475, 125.5406)`, `shift => 'day'`. Also set `callsign => 'AMB 01'` (matching the prefix formula in UnitFactory) instead of the plan's `'Alpha-1'` — keeps visual consistency with the factory's own `{prefix} {number}` output.
- **Files modified:** `tests/Feature/Broadcasting/UnitStatusChangedSnapshotTest.php`
- **Verification:** Three consecutive green runs with byte-identical fixture (`UnitStatusChanged.json` unchanged across runs).
- **Committed in:** `9740fa9` (baked into the Task 2 commit).

**3. [Rule 2 - Missing determinism] Pinned `checklist_pct` in ChecklistUpdated fixture**
- **Found during:** Task 2 (ChecklistUpdated event requires a non-null value to produce a meaningful snapshot)
- **Issue:** `IncidentFactory::definition()` does not set `checklist_pct` (column allows null). Without pinning, fixture would capture `"checklist_pct": null` — a less useful regression signal.
- **Fix:** Pinned `'checklist_pct' => 75` in the factory creation. Also pinned status to `OnScene` to match realistic context when checklist updates fire.
- **Files modified:** `tests/Feature/Broadcasting/ChecklistUpdatedSnapshotTest.php`
- **Verification:** Byte-identical fixture across three runs.
- **Committed in:** `9740fa9`.

---

**Total deviations:** 3 auto-fixed (1 bug fix — Rule 1; 2 missing-critical — Rule 2)
**Impact on plan:** All three auto-fixes are load-bearing for the phase's entire purpose: if any of the 6 fixtures drifts across runs, the whole regression baseline is worthless because false positives will fire on L13. No scope creep — all fixes stay inside `tests/Feature/Broadcasting/` and do not touch composer.json, event classes, or any production code.

## Issues Encountered

- **Full-suite pre-existing failures:** `php artisan test --compact` (full suite) on baseline (before my changes) returns `48 failed, 2 skipped, 527 passed` due to `UniqueConstraintViolationException` in several Feature tests (likely DB isolation issue between tests under the current PostgreSQL dev DB — tests rely on `RefreshDatabase` but cascading FK ordering may leave pollution). After my changes, full suite returns `44 failed, 2 skipped, 535 passed` — my 6 new tests add 6 additional passes; no regression, and in fact 4 fewer failures because ordering shifted.
- These pre-existing failures are **out of scope** per the executor's Scope Boundary rule — they exist on `main` without any of this plan's files. Logged as deferred but not touched.
- **Acceptance criterion "full v1.0 suite remains green":** This criterion was already broken on `main` before Wave 1 started. My changes do not worsen it; they strictly improve the pass count. The phase-17 three-commit sequence (Wave 2 + Wave 3) will need to tackle these pre-existing failures either before or during the L13 upgrade work.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- **Ready for Wave 2 (17-02 framework bump):** Fixtures are committed on `main` at commit `9740fa9`. Wave 2 can now bump `laravel/framework` to `^13.0` and the snapshot tests will either (a) still pass byte-identically — confirming FRAMEWORK-02 — or (b) fail with a precise diff pointing at the exact field that changed in L13.
- **Pre-existing full-suite failures are a blocker-like risk for Wave 2:** Wave 2 verifies by running `php artisan test --compact` (full suite green). With ~44 baseline failures already red, Wave 2 will need to either fix them first or distinguish L13-introduced failures from the baseline noise. Recommend Wave 2 planner begins with a diagnostic pass on the baseline before bumping the framework.
- **Fixture stability guarantee:** All six JSON fixtures tested across 3 consecutive runs with `git status` showing zero drift. Wave 2 and Wave 3 can rely on these as the regression oracle.

## Self-Check: PASSED

Files verified to exist:
- `tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php` ✅
- `tests/Feature/Broadcasting/IncidentTriagedSnapshotTest.php` ✅
- `tests/Feature/Broadcasting/UnitAssignedSnapshotTest.php` ✅
- `tests/Feature/Broadcasting/UnitStatusChangedSnapshotTest.php` ✅
- `tests/Feature/Broadcasting/ChecklistUpdatedSnapshotTest.php` ✅
- `tests/Feature/Broadcasting/ResourceRequestedSnapshotTest.php` ✅
- `tests/Feature/Broadcasting/__snapshots__/IncidentCreated.json` ✅
- `tests/Feature/Broadcasting/__snapshots__/IncidentTriaged.json` ✅
- `tests/Feature/Broadcasting/__snapshots__/UnitAssigned.json` ✅
- `tests/Feature/Broadcasting/__snapshots__/UnitStatusChanged.json` ✅
- `tests/Feature/Broadcasting/__snapshots__/ChecklistUpdated.json` ✅
- `tests/Feature/Broadcasting/__snapshots__/ResourceRequested.json` ✅

Commits verified in git log:
- `ca937b4` — test(17-01): add IncidentCreated + IncidentTriaged snapshot tests on L12 ✅
- `9740fa9` — test(17-01): add UnitAssigned, UnitStatusChanged, ChecklistUpdated, ResourceRequested snapshots ✅

composer.json diff (HEAD~2 → HEAD): empty ✅ (Wave 2 scope preserved).

---
*Phase: 17-laravel-12-13-upgrade*
*Completed: 2026-04-21*
