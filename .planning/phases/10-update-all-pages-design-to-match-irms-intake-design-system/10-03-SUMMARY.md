---
phase: 10-update-all-pages-design-to-match-irms-intake-design-system
plan: 03
subsystem: ui
tags: [tailwind, design-system, color-mix, space-mono, data-tables, vue]

# Dependency graph
requires:
  - phase: 10-01
    provides: CSS token remapping and shadow scale variables
provides:
  - Design system data table pattern on admin pages (Users, Barangays, IncidentTypes)
  - Design system card pattern on admin form pages (UserForm, BarangayForm, IncidentTypeForm)
  - Design system data table pattern on incidents list and queue pages
  - Design system tokens on incident Create and Show pages
  - Priority/status badges using color-mix() with t-p* and status tokens
  - Role badges using color-mix() with t-role-* tokens
affects: [10-04]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "color-mix() badge pattern for priority, status, and role colors"
    - "Space Mono 9px uppercase tracking-[2px] for table column headers"
    - "shadow-[var(--shadow-1)] + rounded-[7px] + border-border for table wrappers"
    - "font-mono text-[10px] text-t-text-faint for machine-readable data cells (IDs, timestamps)"

key-files:
  created: []
  modified:
    - resources/js/pages/incidents/Index.vue
    - resources/js/pages/incidents/Create.vue
    - resources/js/pages/incidents/Queue.vue
    - resources/js/pages/incidents/Show.vue

key-decisions:
  - "Status badges mapped to design system tokens: PENDING->t-p3, TRIAGED->t-accent, DISPATCHED->t-unit-dispatched, etc."
  - "Fieldset legends in Create.vue use Space Mono uppercase pattern matching table headers"

patterns-established:
  - "color-mix() badge pattern: bg-[color-mix(in_srgb,var(--token)_12%,transparent)] text-{token}"
  - "Empty state pattern: rounded-[7px] border-dashed border-border bg-card shadow-[var(--shadow-3)]"

requirements-completed: [DS-08, DS-09]

# Metrics
duration: 8min
completed: 2026-03-14
---

# Phase 10 Plan 03: Admin Tables & Incidents Pages Summary

**Design system data table pattern and color-mix() badges applied to all admin and incidents pages, replacing hardcoded neutral/color classes with CSS variable tokens**

## Performance

- **Duration:** 8 min
- **Started:** 2026-03-13T17:40:21Z
- **Completed:** 2026-03-13T17:48:25Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- All incidents pages (Index, Create, Queue, Show) restyled with design system data table pattern
- Priority badges use color-mix() with t-p1 through t-p4 tokens across all pages
- Status badges use color-mix() with contextual design system tokens (t-accent, t-unit-*, t-online)
- Zero remaining neutral-*/zinc-* hardcoded color classes in modified files
- Create.vue form wrapped in design system card pattern with fieldset legends in Space Mono uppercase

## Task Commits

Each task was committed atomically:

1. **Task 1: Restyle admin pages with design system data table pattern** - `8d2f441` (feat, from 10-02 execution -- admin changes were already applied in 10-02 commit)
2. **Task 2: Restyle incidents pages with design system tokens** - `4c3a1b9` (feat)

**Plan metadata:** pending (docs: complete plan)

## Files Created/Modified
- `resources/js/pages/incidents/Index.vue` - Design system data table with color-mix() priority/status badges
- `resources/js/pages/incidents/Create.vue` - Card pattern wrapper, Space Mono fieldset legends, removed neutral-*
- `resources/js/pages/incidents/Queue.vue` - Design system data table, priority border with t-p* tokens, empty state
- `resources/js/pages/incidents/Show.vue` - Priority/status badges with color-mix(), text-foreground replacing neutral-*

## Decisions Made
- Status badges mapped to semantically meaningful design system tokens: PENDING uses t-p3 (warning/yellow), TRIAGED uses t-accent (blue), DISPATCHED uses t-unit-dispatched, ON_SCENE uses t-unit-onscene, RESOLVED uses t-online (green)
- Fieldset legends in Create.vue styled with Space Mono uppercase pattern to match table column headers for visual consistency
- Queue.vue priority border indicators use t-p* tokens (border-l-t-p1 through border-l-t-p4)

## Deviations from Plan

### Task 1 Already Applied

Task 1 (admin pages restyle) was already completed by the 10-02 plan execution (commit 8d2f441). The admin pages in HEAD already had all design system changes specified in this plan: Space Mono column headers, Level 1 shadow, 7px border-radius, color-mix() role badges, and card pattern on form pages. No new commit was needed for Task 1.

### Auto-fixed Issues

None - Task 2 executed as planned.

---

**Total deviations:** 1 (Task 1 scope overlap with 10-02)
**Impact on plan:** No negative impact. Admin pages were already correctly restyled. Only incidents pages required new work.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All admin and incidents pages now use design system tokens
- Ready for 10-04 (dispatch console and responder station token alignment)

---
*Phase: 10-update-all-pages-design-to-match-irms-intake-design-system*
*Completed: 2026-03-14*

## Self-Check: PASSED
