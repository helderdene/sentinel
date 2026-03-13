---
phase: 11-implement-units-crud
plan: 01
subsystem: admin
tags: [crud, eloquent, form-request, inertia, scopes, migration]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: Unit model, admin routes, role middleware
provides:
  - AdminUnitController with full CRUD, decommission, recommission
  - StoreUnitRequest and UpdateUnitRequest form validation
  - Auto-generated unit IDs (TYPE_PREFIX-NN format)
  - Bidirectional crew sync via User.unit_id
  - Unit.scopeActive() for filtering decommissioned units
  - decommissioned_at migration and model cast
affects: [11-02-frontend, dispatch, state-sync]

# Tech tracking
tech-stack:
  added: []
  patterns: [auto-generated string primary keys from type prefix + max sequence, decommission lifecycle separate from operational status]

key-files:
  created:
    - app/Http/Controllers/Admin/AdminUnitController.php
    - app/Http/Requests/Admin/StoreUnitRequest.php
    - app/Http/Requests/Admin/UpdateUnitRequest.php
    - database/migrations/2026_03_13_210232_add_decommissioned_at_to_units_table.php
    - tests/Feature/Admin/AdminUnitTest.php
    - resources/js/pages/admin/Units.vue
    - resources/js/pages/admin/UnitForm.vue
  modified:
    - app/Models/Unit.php
    - routes/admin.php
    - routes/web.php
    - resources/js/components/AppSidebar.vue
    - app/Http/Controllers/DispatchConsoleController.php
    - app/Http/Controllers/StateSyncController.php
    - app/Http/Controllers/Admin/AdminUserController.php

key-decisions:
  - "Auto-generated unit IDs use PostgreSQL regex SUBSTRING/CAST to extract max sequence from existing units of same type"
  - "Decommission lifecycle (decommissioned_at timestamp) kept separate from operational status (AVAILABLE/OFFLINE/DISPATCHED) for clean domain separation"
  - "Stub Vue pages created for Inertia component assertions; full frontend built in Plan 11-02"

patterns-established:
  - "scopeActive() pattern: whereNull('decommissioned_at') for excluding soft-disabled records without soft deletes"
  - "Bidirectional crew sync: two-step User update (remove old, assign new) instead of pivot table"

requirements-completed: [UNIT-01, UNIT-02, UNIT-03, UNIT-04, UNIT-05, UNIT-06, UNIT-07, UNIT-08, UNIT-09]

# Metrics
duration: 5min
completed: 2026-03-14
---

# Phase 11 Plan 01: Units CRUD Backend Summary

**AdminUnitController with auto-generated IDs (AMB-01, FIRE-02), decommission/recommission lifecycle, bidirectional crew sync, and scopeActive() filtering**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-13T21:02:13Z
- **Completed:** 2026-03-13T21:07:15Z
- **Tasks:** 2
- **Files modified:** 14

## Accomplishments
- Full CRUD controller for units with auto-generated IDs from type prefix + max sequence
- Decommission/recommission lifecycle with active incident guard and crew unassignment
- Bidirectional crew sync via User.unit_id on create and update
- All operational queries (dispatch, state-sync, user form) now filter decommissioned units
- 10 passing tests covering all 9 UNIT requirements plus active incident guard

## Task Commits

Each task was committed atomically:

1. **Task 1: Migration, model scope, and controller with form requests (TDD)**
   - `3a22d9a` (test: add failing tests for Units CRUD)
   - `6b31486` (feat: implement Units CRUD backend with auto-generated IDs)
2. **Task 2: Update dispatch queries to filter decommissioned units** - `c4a05b8` (fix)

## Files Created/Modified
- `app/Http/Controllers/Admin/AdminUnitController.php` - Resource controller with index, create, store, edit, update, destroy, recommission
- `app/Http/Requests/Admin/StoreUnitRequest.php` - Validation for unit creation (type, agency, crew_capacity, status restricted to AVAILABLE/OFFLINE)
- `app/Http/Requests/Admin/UpdateUnitRequest.php` - Validation for unit updates (same as store minus type)
- `database/migrations/2026_03_13_210232_add_decommissioned_at_to_units_table.php` - Adds nullable decommissioned_at timestamp
- `app/Models/Unit.php` - Added decommissioned_at to fillable/casts, scopeActive(), scopeCommissioned()
- `routes/admin.php` - Added Route::resource('units') + recommission route
- `routes/web.php` - Replaced ComingSoon placeholder with redirect to /admin/units
- `resources/js/components/AppSidebar.vue` - Updated Units href from /units to /admin/units
- `resources/js/pages/admin/Units.vue` - Stub page for Inertia component assertion
- `resources/js/pages/admin/UnitForm.vue` - Stub page for Inertia component assertion
- `tests/Feature/Admin/AdminUnitTest.php` - 10 tests covering UNIT-01 through UNIT-09
- `app/Http/Controllers/DispatchConsoleController.php` - Added active() scope to unit queries
- `app/Http/Controllers/StateSyncController.php` - Added active() scope to unit query
- `app/Http/Controllers/Admin/AdminUserController.php` - Changed Unit::all() to Unit::query()->active()->get()

## Decisions Made
- Auto-generated unit IDs use PostgreSQL regex SUBSTRING/CAST to extract max sequence from existing units of same type
- Decommission lifecycle (decommissioned_at timestamp) kept separate from operational status for clean domain separation
- Stub Vue pages created for Inertia component assertions; full frontend built in Plan 11-02
- Default callsign auto-generated from type name + number (e.g., "Ambulance 3") with optional override

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Backend routes and controller fully operational for Plan 11-02 frontend implementation
- Vue stub pages ready to be replaced with full unit management UI
- All operational queries properly filtering decommissioned units

---
*Phase: 11-implement-units-crud*
*Completed: 2026-03-14*
