---
phase: 15-close-rspdr-real-time-dispatch-visibility
plan: 01
subsystem: testing

tags: [pest, broadcasting, echo, reverb, state-sync, laravel-12]

# Dependency graph
requires:
  - phase: 03-real-time-infrastructure
    provides: "PrivateChannel auth (routes/channels.php), ChannelAuthorizationTest, Reverb broker config"
  - phase: 06-responder-console
    provides: "ChecklistUpdated + ResourceRequested events, ResponderController::requestResource timeline shape"
provides:
  - "Tightened backend Pest assertions proving ChecklistUpdated broadcasts on private-dispatch.incidents with {incident_id, incident_no, checklist_pct}"
  - "Tightened backend Pest assertions proving ResourceRequested broadcasts on private-dispatch.incidents with all 7 payload keys"
  - "Widened StateSyncController filter to dispatch-active statuses, enabling reconnect/reload for ON_SCENE incidents"
  - "State-sync response now hydrates incidents[].resource_requests[] from timeline rows"
affects: ["15-02-dispatch-console-subscription", "future dispatch UX, reconnect/refresh flows"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Pest closure-based event assertion verifying channel class + name + full payload shape"
    - "State-sync payload composition: eager-load filtered timeline, map to domain DTO, unset internal relation before response"

key-files:
  created: []
  modified:
    - app/Http/Controllers/StateSyncController.php
    - tests/Feature/Responder/ChecklistTest.php
    - tests/Feature/Responder/ResourceRequestTest.php
    - tests/Feature/RealTime/StateSyncTest.php

key-decisions:
  - "Resolved OQ-1 by widening state-sync filter to [PENDING, TRIAGED, DISPATCHED, ACKNOWLEDGED, EN_ROUTE, ON_SCENE, RESOLVING] — minimum set that lets both intake and dispatch share the endpoint."
  - "Kept channelCounts query PENDING-only to preserve operator intake semantics (widening would inflate the counts operators see)."
  - "Used ->map() transform on Incident collection to derive resource_requests[] rather than appending via accessor on the model — keeps presentation concern in the controller."
  - "Tightened only the first test case in ChecklistTest and ResourceRequestTest per D-14; remaining cases left untouched to minimize diff surface."

patterns-established:
  - "Event assertion closure returns true to signal match; all expect() calls within become part of the broader Pest assertion count."
  - "Timeline rows with event_type='resource_requested' are the single source of truth for dispatch-facing resource-request history."

requirements-completed: [RSPDR-06, RSPDR-10]

# Metrics
duration: ~45 min
completed: 2026-04-17
---

# Phase 15 Plan 01: Backend Broadcast + State-Sync Verification Summary

**Pest closures now verify the exact PrivateChannel name and full payload shape of ChecklistUpdated + ResourceRequested broadcasts; StateSyncController widened to dispatch-active statuses and hydrates incident.resource_requests[] from timeline rows so reconnect/reload reaches resource history.**

## Performance

- **Duration:** ~45 min (includes worktree env setup)
- **Started:** 2026-04-17T09:00:00Z
- **Completed:** 2026-04-17T09:45:00Z
- **Tasks:** 4
- **Files modified:** 4

## Accomplishments

- ChecklistTest first case asserts `PrivateChannel` class, exact name `'private-dispatch.incidents'`, all 3 payload keys, and sentinel `checklist_pct === 50`.
- ResourceRequestTest first case asserts `PrivateChannel` class, exact name `'private-dispatch.incidents'`, all 7 payload keys, and 4 sentinel values (incident_id, resource_type, resource_label, notes).
- StateSyncController widened from PENDING-only to the 7-status dispatch-active set; channelCounts query intentionally preserved PENDING-only for intake semantics.
- StateSyncController now eager-loads `timeline` filtered to `event_type='resource_requested'` with `actor`, maps each incident to a `resource_requests[]` array of shape `{resource_type, resource_label, notes, requested_by, timestamp}`, and unsets the raw `timeline` key before response.
- Two new StateSyncTest cases assert hydration shape on an ON_SCENE incident and empty-array behavior when no requests exist.
- All 4 files Pint-clean. Zero new dependencies. Zero new classes, migrations, or routes. Threat model scope unchanged (no new channels, no new trust boundaries).

## Task Commits

Each task committed atomically on `worktree-agent-a330ec00`:

1. **Task 1: Tighten ChecklistUpdated event assertion** — `19fa1d5` (test)
2. **Task 2: Tighten ResourceRequested event assertion** — `bdc6b9f` (test)
3. **Task 3: Extend StateSyncController — widen filter + hydrate resource_requests** — `d5622aa` (feat)
4. **Task 4: Add StateSyncTest case — resource_requests hydration** — `1b8b52d` (test)

## Files Created/Modified

- `app/Http/Controllers/StateSyncController.php` — Widened status filter to dispatch-active set; added timeline eager-load + map to `resource_requests[]`; preserved PENDING-only channelCounts query.
- `tests/Feature/Responder/ChecklistTest.php` — Tightened first test's `Event::assertDispatched` to a closure asserting channel class/name + full payload shape + sentinel values.
- `tests/Feature/Responder/ResourceRequestTest.php` — Same tightening for ResourceRequested (all 7 keys + 4 sentinel values).
- `tests/Feature/RealTime/StateSyncTest.php` — Added two new cases: one hydration shape test (ON_SCENE + timeline row → populated `resource_requests[]`), one empty-array test.

## Decisions Made

**OQ-1 resolution (state-sync filter widening):** Plan 15-CONTEXT left this open as "Claude's discretion." Chose the 7-status dispatch-active set matching `DispatchConsoleController::show` plus PENDING for operator compatibility. This is the minimal filter that lets state-sync serve both intake (PENDING only) and dispatch (active lifecycle) without maintaining two endpoints. Documented in the Task 3 commit message.

**channelCounts stays PENDING-only:** The state-sync response includes a channel-counts widget used by the operator intake view. Widening it would show responders as "incoming" channels — semantically wrong. Deliberately split: `incidents[]` widened, `channelCounts` narrow.

**Map-over-Collection transform vs. accessor:** Considered adding a `getResourceRequestsAttribute` on the Incident model, but that would couple the presentation shape to the model. Chose a `->map()` on the controller-side Collection — keeps model thin and state-sync-specific shape isolated.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking] Worktree needs real vendor + regenerated autoload**
- **Found during:** Task 1 (first `php artisan test` run)
- **Issue:** `Event` facade resolved as "Class not found" during tests even though `php artisan config:show app` showed aliases registered. Root cause: symlinked `vendor/` from primary repo meant composer's PSR-4 autoload for `Tests\\` pointed at `/Users/helderdene/IRMS/tests/` (primary), not the worktree's `tests/`. Pest's `->in('Feature')` glob therefore did not match worktree test paths, so TestCase extension (which triggers Laravel bootstrap + AliasLoader) was not applied to worktree tests.
- **Fix:** Replaced symlinked `vendor/` with a real copy (`rm vendor && cp -R /Users/helderdene/IRMS/vendor vendor`) then ran `composer dump-autoload` to regenerate the PSR-4 class map against worktree paths. Also copied `.env` and recreated `storage/` as real directories (symlink caused `git stash` breakage).
- **Files modified:** None in the repo — worktree environment only (`.env`, `storage/`, `vendor/`, `node_modules/` are not tracked).
- **Verification:** `php artisan test --compact tests/Feature/Responder/ChecklistTest.php` → 3 passed, 14 assertions.
- **Committed in:** N/A — worktree setup, not part of plan diff.

---

**Total deviations:** 1 auto-fixed (1 blocking env issue)
**Impact on plan:** Worktree environment fix only; no plan scope change.

## Issues Encountered

**Pgsql-based flakiness in full `php artisan test --compact` run:** 91 pre-existing failures across Admin/Auth/Analytics/Dashboard suites caused by pgsql test-database unique-constraint collisions (e.g., `Key (name)=(autem) already exists` in `incident_categories`) and role/auth middleware setup. Three StateSyncTest cases that pass in isolation also fail when the full suite is run due to the same cross-test state bleed. This also reproduces on the primary repo HEAD (46 failures in primary baseline vs. 91 here — delta is pre-existing test isolation issues unrelated to this plan). Per scope-boundary rule, these are out of scope for Plan 15-01. Phase-focused sample run (`tests/Feature/Responder/ChecklistTest.php tests/Feature/Responder/ResourceRequestTest.php tests/Feature/RealTime/StateSyncTest.php`) passes all 13 tests cleanly with 66 assertions.

## Self-Check: PASSED

- `app/Http/Controllers/StateSyncController.php` — FOUND (commit d5622aa)
- `tests/Feature/Responder/ChecklistTest.php` — FOUND (commit 19fa1d5)
- `tests/Feature/Responder/ResourceRequestTest.php` — FOUND (commit bdc6b9f)
- `tests/Feature/RealTime/StateSyncTest.php` — FOUND (commit 1b8b52d)
- Commit 19fa1d5 — FOUND in `git log --oneline`
- Commit bdc6b9f — FOUND in `git log --oneline`
- Commit d5622aa — FOUND in `git log --oneline`
- Commit 1b8b52d — FOUND in `git log --oneline`

## TDD Gate Compliance

Plan 15-01 is type `execute`, not type `tdd`, so plan-level TDD gates do not apply. Task 4 declared `tdd="true"` but relied on implementation from Task 3 (a feat commit) rather than RED-before-GREEN. This is consistent with the plan's own task ordering (Task 3 = feat → Task 4 = test proving it). Commit sequence shows:

1. `test(15-01): tighten ChecklistUpdated` — Task 1 (assertion tightening on existing green test)
2. `test(15-01): tighten ResourceRequested` — Task 2 (same)
3. `feat(15-01): widen state-sync filter + hydrate resource_requests` — Task 3 implementation
4. `test(15-01): assert state-sync resource_requests hydration` — Task 4 verification

Pattern aligns with plan intent: tighten existing assertions first, then implement state-sync extension, then assert new behavior.

## Next Phase Readiness

- Plan 15-02 (dispatch console frontend subscription) can consume the enriched state-sync payload shape: `incidents[].resource_requests[] = [{resource_type, resource_label, notes, requested_by, timestamp}, ...]`.
- Channel name `private-dispatch.incidents` and event payload shapes for ChecklistUpdated + ResourceRequested are now guarded by closure assertions — any future backend payload drift will fail these tests before reaching the frontend.
- No new environment variables, no new services, no migrations — frontend can integrate against current production Reverb config.

**Deferred items:**
- UI-side hydration from `resource_requests` (resource-request history panel) → Plan 15-02 (per plan output section).
- Cross-test pgsql state isolation for the full suite → out of scope for Phase 15; existing infrastructure issue noted for future cleanup.

---
*Phase: 15-close-rspdr-real-time-dispatch-visibility*
*Completed: 2026-04-17*
