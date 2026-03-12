---
phase: 01-foundation
plan: 03
subsystem: ui
tags: [inertia, vue3, typescript, rbac, navigation, sidebar, middleware, placeholder-routes]

requires:
  - phase: 01-foundation
    provides: "UserRole enum, EnsureUserHasRole middleware, 9 Laravel Gates from Plan 01"
provides:
  - "HandleInertiaRequests shares user role string and 9 permission booleans"
  - "TypeScript User type with role: UserRole and can: UserPermissions"
  - "Role-based AppSidebar with per-role navigation items"
  - "ComingSoon.vue placeholder page for unbuilt features"
  - "8 placeholder routes with role middleware for all planned features"
  - "Role-aware Dashboard with per-role content sections"
  - "Unified /messages route for all communication roles"
  - "16 navigation tests covering permission flags and route access"
affects: [intake, dispatch, responder, analytics, real-time]

tech-stack:
  added: []
  patterns: [inertia-shared-props-with-can-permissions, role-based-computed-nav-items, route-inertia-with-role-middleware, placeholder-page-pattern]

key-files:
  created:
    - resources/js/pages/placeholder/ComingSoon.vue
    - tests/Feature/Navigation/RoleNavigationTest.php
  modified:
    - app/Http/Middleware/HandleInertiaRequests.php
    - resources/js/types/auth.ts
    - resources/js/components/AppSidebar.vue
    - resources/js/pages/Dashboard.vue
    - routes/web.php
    - resources/js/pages/Welcome.vue
    - resources/js/pages/auth/Login.vue
    - resources/js/pages/auth/Register.vue

key-decisions:
  - "Unified /messages route for all 4 roles instead of separate per-role routes"
  - "Removed [key: string]: unknown index signature from User type for explicit typing"
  - "Used computed nav items per role instead of filtering a single list"

patterns-established:
  - "Inertia shared props: auth.user.role (string) + auth.user.can.{permission} (boolean) for frontend RBAC"
  - "Role-based navigation: computed Record<UserRole, NavItem[]> in AppSidebar"
  - "Placeholder pages: Route::inertia with role middleware and ComingSoon component"
  - "Dashboard: role-aware content sections using v-if on userRole computed"

requirements-completed: [FNDTN-03, FNDTN-04]

duration: 16min
completed: 2026-03-12
---

# Phase 1 Plan 3: Role-Based Navigation Summary

**Role-based sidebar navigation with per-role Inertia shared props (9 permission flags), 8 placeholder routes, ComingSoon component, and role-aware Dashboard for all 4 IRMS roles**

## Performance

- **Duration:** 16 min
- **Started:** 2026-03-12T15:38:44Z
- **Completed:** 2026-03-12T15:55:16Z
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments
- HandleInertiaRequests shares user role string and 9 permission booleans (`can.manage_users`, `can.create_incidents`, etc.) for all authenticated users
- TypeScript `User` type updated with `role: UserRole` and `can: UserPermissions` for type-safe frontend RBAC
- AppSidebar renders different navigation items per role: Admin (8 items), Dispatcher (5), Responder (3), Supervisor (6)
- ComingSoon.vue placeholder page with construction icon, title, description, and back-to-dashboard link
- 8 placeholder routes registered with role middleware: dispatch, incidents/queue, incidents, messages, assignment, my-incidents, units, analytics
- Messages route unified at `/messages` accessible to all 4 roles (no separate responder route)
- Dashboard shows role-specific content: admin quick links, dispatcher stat placeholders, responder "no active assignment" card, supervisor KPI placeholders
- 16 navigation tests passing: 4 role permission checks + 11 route access + 1 guest redirect

## Task Commits

Each task was committed atomically:

1. **Task 1: TDD RED - Failing tests for role-based Inertia shared props** - `d115e1f` (test)
2. **Task 1: TDD GREEN - HandleInertiaRequests + TypeScript types** - `98f424c` (feat)
3. **Task 2: Role-based sidebar, placeholder pages, routes, route access tests** - `e16917b` (feat)

## Files Created/Modified
- `app/Http/Middleware/HandleInertiaRequests.php` - Shares role string and 9 permission booleans via Inertia props
- `resources/js/types/auth.ts` - UserRole, UserPermissions, and updated User type
- `resources/js/components/AppSidebar.vue` - Role-based computed navigation items, removed boilerplate footer nav
- `resources/js/pages/Dashboard.vue` - Role-aware dashboard with per-role content sections
- `resources/js/pages/placeholder/ComingSoon.vue` - Reusable placeholder page for unbuilt features
- `routes/web.php` - 8 placeholder routes with role middleware groups
- `tests/Feature/Navigation/RoleNavigationTest.php` - 16 tests covering permissions and route access
- `resources/js/pages/Welcome.vue` - Fixed dead register route import (Rule 3)
- `resources/js/pages/auth/Login.vue` - Fixed dead register route import (Rule 3)
- `resources/js/pages/auth/Register.vue` - Fixed dead register route import (Rule 3)

## Decisions Made
- Unified `/messages` route for all 4 roles instead of separate per-role routes -- messages are bi-directional between dispatch and responders, so all roles need the same endpoint
- Removed `[key: string]: unknown` index signature from TypeScript User type -- explicit fields are safer and enable IDE autocompletion
- Used computed `Record<UserRole, NavItem[]>` mapping instead of filtering a flat list -- clearer and easier to maintain per-role nav

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Fixed pre-existing build failure from dead register route imports**
- **Found during:** Task 2 verification (npm run build)
- **Issue:** Registration is disabled in Fortify (Plan 01-01), so Wayfinder doesn't generate `@/routes/register`. Three files still imported from it: Welcome.vue, Login.vue, Register.vue. Build failed with "ENOENT: no such file or directory" and "register is not exported".
- **Fix:** Removed `register` import from all three files, replaced `register()` calls with raw `/register` URL strings
- **Files modified:** resources/js/pages/Welcome.vue, resources/js/pages/auth/Login.vue, resources/js/pages/auth/Register.vue
- **Verification:** `npm run build` succeeds, `npm run types:check` clean
- **Committed in:** e16917b (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Pre-existing build issue from Plan 01-01 disabling registration. Fix was minimal (3 import removals). No scope creep.

## Issues Encountered
- Task 2 commit inadvertently included Plan 01-02 (Admin Panel) files that were in the working tree from parallel execution. This is not harmful -- the admin panel code is correct and needed. The full test suite (129 passed, 2 skipped) confirms everything works together.

## User Setup Required
None - all changes are code-only with no external service configuration required.

## Next Phase Readiness
- Role-based navigation complete: each role sees correct sidebar items
- RBAC end-to-end verified: backend gates -> Inertia shared props -> frontend sidebar -> route access control
- Placeholder routes ready to be replaced with real pages as each phase is built
- Ready for Phase 2 (Intake): incident triage form will replace the incidents placeholder route
- Ready for Phase 3 (Real-Time): WebSocket infrastructure will add live updates to dispatch placeholder

## Self-Check: PASSED

All key files verified present. All 3 task commits verified in git log. Full test suite passes (129 passed, 2 skipped). Frontend builds clean. TypeScript checks clean.

---
*Phase: 01-foundation*
*Completed: 2026-03-12*
