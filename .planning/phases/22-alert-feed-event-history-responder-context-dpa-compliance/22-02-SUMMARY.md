---
phase: 22
plan: 02
subsystem: fras
tags: [gates, inertia, broadcasting, wave-0, tdd]
requires:
  - 22-01 (Wave 1 Plan 1 — parallel; provides fras_audio_muted column + User $fillable/$casts wiring)
provides:
  - 5 new FRAS gates (view-fras-alerts, manage-cameras, manage-personnel, trigger-enrollment-retry, view-recognition-image)
  - Inertia share exposes 5 new auth.user.can.* keys + fras_audio_muted on auth.user
  - FrasAlertAcknowledged broadcast event class (scalar-only, ShouldDispatchAfterCommit, fras.alerts channel)
  - Wave 0 test stubs registering 12 planned Phase 22 feature/browser tests
affects:
  - app/Providers/AppServiceProvider.php (configureGates extended from 15 → 20 gates)
  - app/Http/Middleware/HandleInertiaRequests.php ($user->only list + can[] extended)
tech-stack:
  added: []
  patterns:
    - multi-role gate with `in_array($user->role, [...], true)` (strict comparison flag per existing convention)
    - scalar-only broadcast event (no Eloquent model in constructor) — safe under ShouldDispatchAfterCommit
    - Pest dataset per-gate (not shared role_matrix) because allowed-role sets differ per gate
    - Wave 0 `->skip('stub — lands in Plan NN')` scaffolds so Nyquist sampling has named targets
key-files:
  created:
    - app/Events/FrasAlertAcknowledged.php
    - tests/Feature/Fras/FrasGatesTest.php
    - tests/Feature/Fras/FrasAlertAcknowledgedEventTest.php
    - tests/Feature/Fras/Wave0PlaceholdersTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - app/Http/Middleware/HandleInertiaRequests.php
decisions:
  - "22-02-D1: Per-gate datasets (not shared role_matrix) — allowed-role sets differ per gate (view-fras-alerts + view-recognition-image admit Operator; the other 3 restrict to Supervisor/Admin), so a single 5-role matrix with a uniform expected bool would misfit 3/5 gates"
  - "22-02-D2: Inertia share test invokes the middleware directly via app(HandleInertiaRequests::class)->share($request) + Request::setUserResolver — avoids full HTTP round-trip, isolates the can[] contract, and parallels the pattern used by existing IntakeStation prop tests"
  - "22-02-D3: fras_audio_muted added to \$user->only() is tolerant of parallel Wave 1 Plan 1 timing — Eloquent's only() silently omits missing attributes, so the share remains null-safe until 22-01's migration lands and is merged back"
metrics:
  duration_min: 25
  tasks: 2
  files_created: 4
  files_modified: 2
  completed_date: "2026-04-22"
---

# Phase 22 Plan 02: Gates + Inertia Share + FrasAlertAcknowledged Event + Wave 0 Stubs — Summary

Wave 1 Plan 2 of 2: 5 new Phase 22 gates live in `AppServiceProvider::configureGates()`, their snake_case mirrors shipped on `auth.user.can.*` via HandleInertiaRequests (plus `fras_audio_muted` on `auth.user`), the `FrasAlertAcknowledged` scalar-payload broadcast event is ready for Wave 3 controllers to dispatch, and `Wave0PlaceholdersTest.php` registers every Phase 22 feature/browser test VALIDATION.md promised so Nyquist has named targets.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Five Gate::define + HandleInertiaRequests extension + Wave 0 stubs | 23160e5 | AppServiceProvider.php, HandleInertiaRequests.php, FrasGatesTest.php, Wave0PlaceholdersTest.php |
| 2 | FrasAlertAcknowledged broadcast event class | 7881d32 | app/Events/FrasAlertAcknowledged.php, tests/Feature/Fras/FrasAlertAcknowledgedEventTest.php |

## Verification Results

- `php artisan test --compact --filter=FrasGatesTest` → 26 passed, 35 assertions (acceptance ≥26 ✓)
- `php artisan test --compact --filter=FrasAlertAcknowledgedEventTest` → 6 passed, 26 assertions (acceptance ≥5 ✓)
- `php artisan test --compact --filter=Wave0PlaceholdersTest` → 12 skipped (acceptance ≥12 ✓)
- `php artisan test --compact --group=fras` → 170 passed, 12 skipped, 0 failed, 551 assertions (no regression)
- `vendor/bin/pint --dirty --format agent --test` → `{"result":"pass"}` (no formatting drift)

Grep contracts all hold:
- `Gate::define('view-fras-alerts'` × 1 in AppServiceProvider
- `Gate::define('manage-cameras'` × 1
- `Gate::define('manage-personnel'` × 1
- `Gate::define('trigger-enrollment-retry'` × 1
- `Gate::define('view-recognition-image'` × 1
- `view_fras_alerts` present in HandleInertiaRequests
- `fras_audio_muted` present on `$user->only(...)` list
- `final class FrasAlertAcknowledged` + `ShouldBroadcast, ShouldDispatchAfterCommit` + `PrivateChannel('fras.alerts')` + `'FrasAlertAcknowledged'` broadcastAs all present

## Implementation Notes

### 5 Gates Appended, Not Inserted

Per 22-RESEARCH.md #7 reconciliation, the 5 new `Gate::define` calls land AFTER the `download-incident-report` closure (ending at line 189 pre-edit), not after line 167 as CONTEXT D-27 originally stated — line 167 is inside the `download-incident-report` body. All 5 follow the existing `in_array($user->role, [...], true)` strict-comparison shape used by `create-incidents`, `view-analytics`, and the Phase 8 intake gates.

### Per-Gate Datasets

`FrasGatesTest.php` defines 5 separate datasets (`view_fras_alerts_matrix`, `manage_cameras_matrix`, etc.) rather than a single shared `role_matrix`. Two gates (`view-fras-alerts`, `view-recognition-image`) admit Operator in addition to Supervisor+Admin, while the other three restrict to Supervisor+Admin only. A shared matrix with one expected bool per role would misfit 3 of 5 gates; per-gate datasets keep the truth table explicit and reviewable row-by-row. Each dataset has 5 entries × 5 gates = 25 role-matrix assertions, plus 1 `shares 5 new can keys in Inertia props` test with 5 `toHaveKey` assertions = 26 tests / 35 assertions (the extra 10 assertions come from chained expectation methods Pest auto-resolves).

### Inertia Share Test Uses Middleware Directly

Rather than fire a full HTTP request and parse the X-Inertia-Partial-Data, the test `app(HandleInertiaRequests::class)->share($request)` and asserts the returned array directly. `Request::setUserResolver(fn () => $user)` fakes the auth. This mirrors the pattern used by `IntakeStationFrasRailTest::it shares frasConfig` and keeps the can[] contract isolated from routing/session/middleware noise.

### fras_audio_muted Parallel-Worktree Tolerance

Plan 22-01 (also Wave 1) owns the `users.fras_audio_muted` migration + User model `$fillable`/`$casts` wiring. This plan (22-02) executes in parallel and adds `'fras_audio_muted'` to `$user->only(...)` in HandleInertiaRequests. Eloquent's `only()` silently omits attributes that don't exist on the model, so our share stays null-safe even when 22-01's changes haven't landed yet in this worktree. When the orchestrator merges both worktrees, the column + cast + `only()` arg line up and `auth.user.fras_audio_muted` surfaces as a boolean. No test in this plan asserts on the `fras_audio_muted` value (tests for it live in 22-01 and 22-05), so no timing coupling exists between the two worktrees' verification.

### FrasAlertAcknowledged: Scalar Over Eloquent

Per 22-CONTEXT D-01 the event fires from a Wave 3 controller after the RecognitionEvent row has been committed. A scalar-only constructor (`string $eventId` rather than `RecognitionEvent $event`) means:
- No re-hydration query when the queue worker picks up the broadcast job
- No `SerializesModels` rehydration cost even though the trait is imported
- Cross-worker environments can't race on stale model state

`ShouldDispatchAfterCommit` ensures the DB write commits before the queue push, so operators on other workstations always see the fresh state when their `useEcho` handler fires.

## Deviations from Plan

### Out-of-Scope Discoveries

**Worktree environment setup** (not a code change — local infra):
- `vendor/` was missing: copied from `/Users/helderdene/IRMS/vendor` rather than symlinked so the composer autoload `$baseDir = dirname($vendorDir)` resolves inside the worktree (a symlink would have pointed Tests\\ autoload at the main repo's `tests/` directory, causing all Feature tests to fail with "A facade root has not been set")
- `node_modules/` symlinked to main repo (autoload-agnostic)
- `public/build/` symlinked to main repo to satisfy `ViteManifestNotFoundException` in Admin/Dispatch controller tests

None of these are committed (all in `.gitignore`). They are executor-local concerns for running the Pest suite inside a parallel worktree. The plan's test commands all pass without them when the worktree is merged back into main (which has vendor/public/build intact).

### Plan Deviations

None — both tasks executed exactly as the plan specified. TDD red-green cycle observed for both tasks:
- Task 1 RED: `FrasGatesTest` 13 failed / 13 passed (existing gate-unaware behavior) → GREEN: 26 passed after appending 5 gates + extending HandleInertiaRequests
- Task 2 RED: `FrasAlertAcknowledgedEventTest` 6 failed (class not found) → GREEN: 6 passed after creating `app/Events/FrasAlertAcknowledged.php`

## Known Stubs

None — all gate bodies, event payload keys, and share prop keys are fully wired. The 12 `Wave0PlaceholdersTest` entries are intentional Nyquist scaffolds documented as Wave 0 per 22-VALIDATION §Wave 0 Requirements; each `->skip('... implementation lands in Plan NN')` cites the owning plan so the stubs are traceable to their replacements.

## Threat Flags

None — the threat register in 22-02-PLAN.md covered all security-relevant surface (T-22-02-01 strict `in_array(..., true)`, T-22-02-02 null-safe `$user ?` guard, T-22-02-03 fras.alerts channel auth excludes Responder via Phase 21, T-22-02-04 `ShouldDispatchAfterCommit`, T-22-02-05 Reverb HMAC signing) and every mitigation landed as-planned. No new network endpoints, auth paths, file access patterns, or schema changes at trust boundaries.

## Self-Check: PASSED

**Files created (verified present):**
- `app/Events/FrasAlertAcknowledged.php` — FOUND
- `tests/Feature/Fras/FrasGatesTest.php` — FOUND
- `tests/Feature/Fras/FrasAlertAcknowledgedEventTest.php` — FOUND
- `tests/Feature/Fras/Wave0PlaceholdersTest.php` — FOUND

**Files modified (verified diff non-empty):**
- `app/Providers/AppServiceProvider.php` — 5 new `Gate::define` blocks appended inside `configureGates()`
- `app/Http/Middleware/HandleInertiaRequests.php` — `fras_audio_muted` on `$user->only()`, 5 new snake_case `can[]` keys

**Commits (verified in `git log`):**
- `23160e5` feat(22-02): add 5 FRAS gates + Inertia can.* surface + Wave 0 test stubs — FOUND
- `7881d32` feat(22-02): add FrasAlertAcknowledged broadcast event + test — FOUND
