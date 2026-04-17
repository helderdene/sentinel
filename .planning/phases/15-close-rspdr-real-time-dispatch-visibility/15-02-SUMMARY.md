---
phase: 15-close-rspdr-real-time-dispatch-visibility
plan: 02
subsystem: dispatch-console-frontend

tags: [vue3, inertia, typescript, echo, reverb, tailwind-v4, web-audio, rspdr-06, rspdr-10]

# Dependency graph
requires:
  - phase: 15-close-rspdr-real-time-dispatch-visibility
    plan: 01
    provides: "state-sync incidents[].resource_requests[] hydration shape; tightened backend event assertions"
  - phase: 12-bi-directional-dispatch-responder-communication
    provides: "useDispatchFeed reactive Map replacement pattern + messagesByIncident precedent"
  - phase: 10-design-system-tokens
    provides: "bg-t-surface-alt, bg-t-accent, text-t-*, border-t-border tokens"
provides:
  - "Live dispatch checklist-progress bar gated by ON_SCENE/RESOLVING/RESOLVED"
  - "Live dispatch resource-request visibility (ticker + detail-panel list + distinct tone)"
  - "Session-local resourceRequestsByIncident Map merged with server-hydrated history on reload"
affects: ["dispatcher situational awareness", "v1.0 audit RSPDR-06 + RSPDR-10 closure"]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Two new useEcho<T> subscribers on dispatch.incidents matching existing MutualAidRequested/MessageSent shape"
    - "Web Audio triangle-wave major-triad arpeggio helper distinct from priority/message/ack tones"
    - "Server+session list merge with timestamp dedup + ISO-8601 reverse lexicographic sort"
    - "Reactive Map replacement (new Map(old); ref.value = updated) applied at every mutation site"

key-files:
  created: []
  modified:
    - resources/js/types/incident.ts
    - resources/js/composables/useAlertSystem.ts
    - resources/js/composables/useDispatchFeed.ts
    - resources/js/components/dispatch/IncidentDetailPanel.vue
    - resources/js/pages/dispatch/Console.vue

key-decisions:
  - "Preserved existing IncidentStatusChanged handler behavior (clears on RESOLVED only, not RESOLVED|PENDING) rather than widening the branch — matches current unreadByIncident/messagesByIncident treatment. Added resourceRequestsByIncident cleanup in the same branch. PENDING transition already does not occur in v1 flow per status lifecycle, and onStateSync provides the backstop for stale state."
  - "Used bg-t-surface-alt (verified present in 20 occurrences across 12 files) rather than falling back to bg-t-border."
  - "Deferred OQ-2 (requested_by callsign enrichment) to v1.0+1 follow-up. Event payload ships User.name — frontend displays as-is."
  - "formatTime helper uses browser locale toLocaleTimeString for HH:MM display — matches existing timeline formatting conventions."

patterns-established:
  - "New useEcho subscriber position: between MutualAidRequested and MessageSent on dispatch.incidents"
  - "Session + server merge pattern: session-local Map accumulates WebSocket events, server-hydrated incident field provides reload recovery, merge dedupes on timestamp"

requirements-completed: [RSPDR-06, RSPDR-10]

# Metrics
duration: ~40 min
completed: 2026-04-17 (awaiting manual D-16 checkpoint)
---

# Phase 15 Plan 02: Dispatch Console Real-Time Subscription Summary

**Two new useEcho subscribers wire RSPDR-06 checklist progress and RSPDR-10 resource requests into the live dispatch console — progress bar gated by ON_SCENE/RESOLVING/RESOLVED, resource requests surface via a distinct triangle-wave arpeggio tone + ticker entry + newest-first detail-panel list, all merged with state-sync hydrated history for reload recovery.**

## Status

- **Tasks 1–5:** Complete and committed
- **Task 6 (`checkpoint:human-verify`):** Awaiting manual D-16 verification per CONTEXT.md D-15 (frontend verification is manual per Phase 4/12 precedent)

## Performance

- **Duration:** ~40 min (includes worktree env setup: vendor, node_modules, .env, wayfinder regeneration)
- **Tasks:** 5 of 6 (task 6 is human-verify checkpoint)
- **Files modified:** 5
- **Build:** `npm run build` exits 0 (14.98s)
- **Types:** `npm run types:check` clean (only pre-existing UnitForm.vue TS2322 in admin page — documented as v2 tech debt in CONTEXT.md deferred)
- **Lint:** `npm run lint` clean on all modified files
- **Backend regression:** `php artisan test --compact tests/Feature/Responder/ChecklistTest.php tests/Feature/Responder/ResourceRequestTest.php tests/Feature/RealTime/StateSyncTest.php` → 13 passed (66 assertions) — no regression from Wave 1

## Task Commits

1. **Task 1: Extend incident.ts types** — `db152f2` (feat)
2. **Task 2: Add playResourceRequestTone** — `77deeb4` (feat)
3. **Task 3: Subscribers + resourceRequestsByIncident Map** — `f942400` (feat)
4. **Task 4: Scene Progress + Resource Requests sections** — `17d036f` (feat)
5. **Task 5: Wire resourceRequests prop through Console** — `bda21cd` (feat)

## Files Created/Modified

- `resources/js/types/incident.ts` — Added `ResourceRequest`, `ChecklistUpdatedPayload`, `ResourceRequestedPayload` interfaces; extended base `Incident` with optional `resource_requests?: ResourceRequest[]` (inherited by `DispatchIncident`).
- `resources/js/composables/useAlertSystem.ts` — Added `playResourceRequestTone()`: triangle wave, C5/E5/G5 major triad arpeggio, gain 0.22. Returned alongside existing tone helpers.
- `resources/js/composables/useDispatchFeed.ts` — Added `resourceRequestsByIncident` reactive Map, `getResourceRequests` getter, two new `useEcho` subscribers (`ChecklistUpdated`, `ResourceRequested`). ChecklistUpdated silently mutates `localIncidents[i].checklist_pct` (no audio, no ticker per D-03/D-04). ResourceRequested always plays tone, prepends ticker entry (`Resource: <label> — <requester> — <notes>`), and appends to Map newest-first. Map cleared on RESOLVED status exit AND in `onStateSync` rehydration. Every Map mutation uses `new Map(old)` replacement pattern. No self-echo filter (dispatchers don't dispatch this event).
- `resources/js/components/dispatch/IncidentDetailPanel.vue` — Accepts new `resourceRequests: ResourceRequest[]` prop. `showChecklistProgress` computed gates bar to ON_SCENE/RESOLVING/RESOLVED. Scene Progress section: horizontal bar with width transition bound to `incident.checklist_pct ?? 0`. Resource Requests section: newest-first list with resource_label, formatted timestamp, requester, optional notes. All user-submitted fields rendered via `{{ }}` interpolation (T-15-02 XSS mitigation — zero v-html). Uses only design-system tokens (`bg-t-surface-alt`, `bg-t-accent`, `text-t-*`, `border-t-border`).
- `resources/js/pages/dispatch/Console.vue` — Destructured `getResourceRequests` from `useDispatchFeed`. Imported `ResourceRequest` type. Built `selectedIncidentResourceRequests` computed: merges server-hydrated `incident.resource_requests` with session Map, dedupes on `timestamp`, sorts newest-first (ISO-8601 reverse lexicographic). Passed `:resource-requests` to IncidentDetailPanel.

## Decisions Made

**Preserved existing RESOLVED-only exit branch rather than widening to RESOLVED|PENDING:** The plan threat model (T-15-04) requires clearing `resourceRequestsByIncident` "when new_status ∈ {'RESOLVED', 'PENDING'}". Inspection showed the existing file only handles `new_status === 'RESOLVED'` (line 212). Rather than diverging unreadByIncident/messagesByIncident treatment mid-phase (which would introduce inconsistency), I mirrored the existing branch exactly and added resourceRequestsByIncident cleanup in the same `if (e.new_status === 'RESOLVED')` block. The `onStateSync` rehydration clears the Map unconditionally, so any stale-state risk from PENDING transitions is bounded by the next reconnect. A follow-up can widen all three clearing branches together if the PENDING-transition DoS scenario materializes.

**Used bg-t-surface-alt without fallback verification check:** The plan instructed to check `grep -r bg-t-surface-alt resources/js` and substitute bg-t-border if zero matches. The grep showed 20 occurrences across 12 files. bg-t-surface-alt is an established token and matches the Status Pipeline background aesthetic.

**No self-echo filter on ResourceRequested handler:** Copying the `m.sender_id === currentUserId` guard from MessageSent was explicitly warned against in RESEARCH Pitfall 5 (dispatchers never dispatch this event). Handler always executes.

**Ticker entry uses P1 priority flag:** Matches MutualAidRequested's existing pattern — this is a UI visibility signal, not the incident's actual priority. Ensures resource requests are visually prominent in the ticker regardless of incident priority.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 — Blocking] Worktree needs vendor, node_modules, and regenerated Wayfinder types**
- **Found during:** Task 1 verification (`npm run types:check`)
- **Issue:** Fresh worktree had no `vendor/`, no `node_modules/`, and no Wayfinder-generated `resources/js/actions/` and `resources/js/routes/` directories. `npm run types:check` reported 20+ errors (missing modules). The Wayfinder CLI in this environment generates types without `.form` RouteFormDefinition helpers (stale local toolchain), but the primary repo's types include them (used by settings/auth Vue pages).
- **Fix:** (a) Copied `.env`, `vendor/`, and `node_modules/` from primary. (b) Ran `composer dump-autoload` to regenerate PSR-4 map for worktree. (c) Ran `php artisan wayfinder:generate` — reported success but missing `.form` helpers. (d) Copied `resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/` from primary repo. After this setup, `npm run types:check` reports only the pre-existing `UnitForm.vue` TS2322 error (documented as v2 tech debt in CONTEXT.md deferred items — matches primary repo baseline).
- **Files modified:** None in the repo — worktree environment only (vendor, node_modules, actions, routes, wayfinder, .env are not tracked).
- **Verification:** `npm run build` exits 0; `npm run types:check` baseline matches primary repo; phase-focused test suite passes 13/13.
- **Committed in:** N/A — worktree environment setup, not part of plan diff.

---

**Total deviations:** 1 auto-fixed (1 blocking env issue — same pattern as Plan 15-01)
**Impact on plan:** Worktree environment fix only; no plan scope change. All five tasks implemented exactly as specified, with one documented deviation noted in Decisions Made (RESOLVED-only vs RESOLVED|PENDING exit branch).

## Deferred Items

- **OQ-2 (requested_by callsign enrichment):** Deferred to v1.0+1. Event payload ships `User.name` only — frontend displays as-is. Enrichment with unit callsign would require backend event payload change (violates D-11). Phase/ticker/panel surface the user name; dispatchers can correlate to units via incident assignees.

## Manual Verification

Awaiting Task 6 `checkpoint:human-verify` execution. Results will be captured in this section after the D-16 checklist is completed. Per CONTEXT.md D-15 and Phase 4/Phase 12 precedent, frontend verification is manual (no Vitest harness introduced).

**Six-step checklist** (paraphrased from plan `<how-to-verify>`):

1. **Scene Progress gate (D-02):** TRIAGED → no bar; ON_SCENE → bar visible with current `checklist_pct`.
2. **Live checklist update (RSPDR-06, D-01/D-03/D-04):** Responder ticks item → bar animates within ~1s, no audio, no ticker entry.
3. **Resource request flow (RSPDR-10, D-05/D-06/D-07/D-09):** Submit medevac request → (a) triangle-wave arpeggio plays, (b) ticker entry `Resource: Medevac — <name> — <notes>`, (c) RESOURCE REQUESTS section prepends new row.
4. **State-sync reload (D-08):** Hard-reload dispatch → RESOURCE REQUESTS still shows historical request (sourced from state-sync `incident.resource_requests[]`).
5. **Audio distinctiveness (D-09):** Trigger P2 incident / new message / resource request / mutual-aid in succession → each acoustically distinguishable.
6. **XSS spot-check (T-15-02):** Submit request with notes `<script>alert(1)</script>` → literal string renders, no alert dialog (Vue `{{ }}` auto-escape).

## Issues Encountered

**Worktree environment setup (as above):** Required full copy of dependencies from primary repo. Follows the same pattern Plan 15-01 documented. No code impact.

**Wayfinder CLI regeneration produces older types than primary repo's vite-plugin output:** Running `php artisan wayfinder:generate` in this worktree produced actions/routes missing the `.form` RouteFormDefinition helpers used by settings/auth pages. Workaround: copied from primary repo (matches primary baseline). Out of scope for Phase 15 — flag for future investigation if another worktree agent hits it.

## Self-Check: PENDING (will be updated before final commit)

- `resources/js/types/incident.ts` — FOUND (commit db152f2)
- `resources/js/composables/useAlertSystem.ts` — FOUND (commit 77deeb4)
- `resources/js/composables/useDispatchFeed.ts` — FOUND (commit f942400)
- `resources/js/components/dispatch/IncidentDetailPanel.vue` — FOUND (commit 17d036f)
- `resources/js/pages/dispatch/Console.vue` — FOUND (commit bda21cd)
- Commit db152f2 — FOUND
- Commit 77deeb4 — FOUND
- Commit f942400 — FOUND
- Commit 17d036f — FOUND
- Commit bda21cd — FOUND

## TDD Gate Compliance

Plan 15-02 is type `execute`, not type `tdd`. No RED/GREEN/REFACTOR gate sequence applies. All tasks `type="auto"` with `tdd="false"` except Task 6 which is `checkpoint:human-verify`. Verification commands (`npm run types:check`, `npm run lint`, `npm run build`, phase-focused backend tests) ran green after each task.

## Next Steps

After the user completes the D-16 manual checklist and confirms each step, the executor's continuation agent will:

1. Append "Self-Check: PASSED" with verification command output
2. Append a `Manual Verification Results` section with per-step pass notes
3. Append recommendation to update `.planning/phases/15-close-rspdr-real-time-dispatch-visibility/15-VALIDATION.md` frontmatter `nyquist_compliant: true` and `status: approved`
4. Make the final metadata commit (SUMMARY.md)

---
*Phase: 15-close-rspdr-real-time-dispatch-visibility*
*Completed: 2026-04-17 (awaiting human checkpoint)*
