---
phase: 15-close-rspdr-real-time-dispatch-visibility
fixed_at: 2026-04-17
review_path: .planning/phases/15-close-rspdr-real-time-dispatch-visibility/15-REVIEW.md
iteration: 1
findings_in_scope: 3
fixed: 3
skipped: 0
status: all_fixed
---

# Phase 15: Code Review Fix Report

**Fixed at:** 2026-04-17
**Source review:** 15-REVIEW.md
**Iteration:** 1

**Summary:**
- Findings in scope: 3 (warnings only; 5 info items deliberately skipped per instructions)
- Fixed: 3
- Skipped: 0

## Fixed Issues

### WR-01: State sync drops live resource-requests for incidents present in fresh payload

**Files modified:** `resources/js/types/incident.ts`, `resources/js/composables/useDispatchFeed.ts`
**Commit:** `6fdd546`
**Applied fix:**
- Added optional `resource_requests?: ResourceRequest[]` to `IncidentForQueue` so the state-sync response type matches the backend JSON shape.
- In `onStateSync`, `freshIncidents` now copies `resource_requests: inc.resource_requests ?? []` through to the local `DispatchIncident` shape, preserving request history across reconnects.

### WR-02: Resource-request dedup key is not unique across requests

**Files modified:** `resources/js/pages/dispatch/Console.vue`
**Commit:** `e32b02a`
**Applied fix:**
- Replaced `req.timestamp` single-key dedup with composite tuple key `${req.timestamp}|${req.resource_type}|${req.requested_by}` inside `selectedIncidentResourceRequests`. Added an inline comment documenting the choice.
- Kept the fix purely frontend to avoid perturbing the D-11 locked `ResourceRequested` event payload; did not propagate `id` through the backend event.

### WR-03: Ticker priority mislabels ResourceRequested and MutualAid as P1

**Files modified:** `resources/js/composables/useDispatchFeed.ts`
**Commit:** `c1f6c7e`
**Applied fix:**
- Both `MutualAidRequested` and `ResourceRequested` handlers now look up the incident via `localIncidents.value.find((i) => i.id === e.incident_id)` and use `inc?.priority ?? 'P3'` for the ticker event, instead of the hardcoded `'P1'`.

## Skipped Issues

None.

## Verification

Commands run after all fixes applied:

| Command | Result |
| --- | --- |
| `npm run types:check` | Pass (only pre-existing, out-of-scope `UnitForm.vue` TS2322 remains — explicitly noted as v2 tech debt in CONTEXT.md and instructions). |
| `npm run build` | Clean exit; Vite built in 21.73s; PWA service worker generated. |
| `php artisan test --compact tests/Feature/RealTime/StateSyncTest.php tests/Feature/Responder/ChecklistTest.php tests/Feature/Responder/ResourceRequestTest.php` | 13 passed / 66 assertions / 1.61s. |

## Staging Strategy

The working tree contained substantial uncommitted phase-15 implementation work on several files (including `useDispatchFeed.ts` and `Console.vue`). To keep each fix commit atomic and focused on just the review finding, each fix was staged via `git apply --cached` from a minimal patch file containing only the review-fix hunks. Pre-existing phase-15 implementation work in the same files was left in the working tree untouched for separate follow-up commits.

## Deferred / Out-of-Scope

Per instructions, the following were not addressed:

- IN-01 through IN-05 (info-level findings)
- Backend broadcast payload changes (D-10 / D-11 LOCKED)
- Pre-existing `UnitForm.vue` TS2322 and unrelated working-tree modifications

---

_Fixed: 2026-04-17_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
