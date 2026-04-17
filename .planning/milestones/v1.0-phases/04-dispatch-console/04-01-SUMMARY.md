---
phase: 04-dispatch-console
plan: 01
subsystem: api
tags: [postgis, proximity, dispatch, inertia, broadcast, pivot]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: User model with roles, Unit model with PostGIS coordinates, Incident model, IncidentTimeline
  - phase: 02-intake
    provides: IncidentType seeder with categories, BarangayLookupService PostGIS pattern
  - phase: 03-real-time
    provides: AssignmentPushed, IncidentStatusChanged, UnitStatusChanged broadcast events
provides:
  - DispatchConsoleController with 6 actions (show, assign, unassign, advance-status, mutual-aid, nearby-units)
  - ProximityRankingService for PostGIS proximity ranking
  - Agency model with incident type associations
  - incident_unit pivot for multi-unit assignment
  - MutualAidRequested broadcast event
  - Stub dispatch/Console.vue page
affects: [04-02, 04-03, 04-04, 05-responder]

# Tech tracking
tech-stack:
  added: []
  patterns: [multi-unit pivot with active-only filtering, forward-only status transitions, PostGIS ST_DWithin proximity ranking]

key-files:
  created:
    - app/Http/Controllers/DispatchConsoleController.php
    - app/Services/ProximityRankingService.php
    - app/Contracts/ProximityServiceInterface.php
    - app/Models/Agency.php
    - app/Models/IncidentUnit.php
    - app/Events/MutualAidRequested.php
    - app/Http/Requests/AssignUnitRequest.php
    - app/Http/Requests/UnassignUnitRequest.php
    - app/Http/Requests/AdvanceStatusRequest.php
    - app/Http/Requests/MutualAidRequest.php
    - database/migrations/2026_03_13_200001_create_incident_unit_table.php
    - database/migrations/2026_03_13_200002_create_agencies_table.php
    - database/migrations/2026_03_13_200003_create_agency_incident_type_table.php
    - database/seeders/AgencySeeder.php
    - resources/js/pages/dispatch/Console.vue
    - tests/Feature/Dispatch/DispatchConsolePageTest.php
    - tests/Feature/Dispatch/UnitAssignmentTest.php
    - tests/Feature/Dispatch/ProximityRankingTest.php
    - tests/Feature/Dispatch/MutualAidTest.php
    - tests/Feature/Dispatch/StatusAdvancementTest.php
  modified:
    - app/Models/Incident.php
    - app/Models/Unit.php
    - app/Providers/AppServiceProvider.php
    - routes/web.php
    - database/seeders/DatabaseSeeder.php
    - tests/Feature/Navigation/RoleNavigationTest.php

key-decisions:
  - "Forward-only status transitions enforced via allowedTransitions map in controller"
  - "incident_unit pivot with unassigned_at null filter for active-only queries"
  - "Route renamed from dispatch.index to dispatch.console for semantic clarity"
  - "ETA calculated assuming 30km/h urban speed for Butuan City context"

patterns-established:
  - "Multi-unit pivot pattern: BelongsToMany with wherePivotNull for active filtering"
  - "Dispatch status transitions: explicit allowedTransitions map prevents backward movement"
  - "ProximityRankingService: raw PostGIS ST_DWithin + ST_Distance following BarangayLookupService pattern"

requirements-completed: [DSPTCH-05, DSPTCH-06, DSPTCH-07, DSPTCH-10, DSPTCH-11]

# Metrics
duration: 7min
completed: 2026-03-13
---

# Phase 04 Plan 01: Dispatch Backend Summary

**Multi-unit assignment with PostGIS proximity ranking, forward-only status transitions, mutual aid agencies, and 6 dispatch controller actions**

## Performance

- **Duration:** 7 min
- **Started:** 2026-03-13T00:04:48Z
- **Completed:** 2026-03-13T00:11:41Z
- **Tasks:** 2
- **Files modified:** 26

## Accomplishments
- DispatchConsoleController with 6 fully tested actions: show (Inertia page with metrics), assignUnit, unassignUnit, advanceStatus, requestMutualAid, nearbyUnits
- ProximityRankingService using raw PostGIS ST_DWithin for distance-based unit ranking with ETA calculation
- Multi-unit assignment data model via incident_unit pivot table with active filtering (wherePivotNull)
- Agency model with 5 seeded agencies (BFP, PNP, DSWD, DOH, LGU_CABADBARAN) and incident type associations
- Forward-only status transition enforcement preventing backward state changes
- averageHandleTime session metric computed from resolved incidents
- 21 dispatch tests covering all endpoints, status transitions, and edge cases

## Task Commits

Each task was committed atomically:

1. **Task 1: Data model** - `9d5b5dc` (feat)
2. **Task 2 RED: Failing tests** - `a8f3e84` (test)
3. **Task 2 GREEN: Implementation** - `1c91f98` (feat)

## Files Created/Modified
- `app/Http/Controllers/DispatchConsoleController.php` - 6-action controller for dispatch console
- `app/Services/ProximityRankingService.php` - PostGIS proximity ranking
- `app/Contracts/ProximityServiceInterface.php` - Service contract
- `app/Models/Agency.php` - Mutual aid agency model
- `app/Models/IncidentUnit.php` - Pivot model with datetime casts
- `app/Events/MutualAidRequested.php` - Broadcast event for mutual aid
- `app/Http/Requests/AssignUnitRequest.php` - Assignment validation
- `app/Http/Requests/UnassignUnitRequest.php` - Unassignment validation
- `app/Http/Requests/AdvanceStatusRequest.php` - Status transition validation
- `app/Http/Requests/MutualAidRequest.php` - Mutual aid request validation
- `database/migrations/2026_03_13_200001_create_incident_unit_table.php` - Multi-unit pivot
- `database/migrations/2026_03_13_200002_create_agencies_table.php` - Agencies table
- `database/migrations/2026_03_13_200003_create_agency_incident_type_table.php` - Agency-type pivot
- `database/seeders/AgencySeeder.php` - Seeds 5 agencies with type mappings
- `resources/js/pages/dispatch/Console.vue` - Stub dispatch page
- `app/Models/Incident.php` - Added assignedUnits() BelongsToMany
- `app/Models/Unit.php` - Added activeIncidents() BelongsToMany
- `app/Providers/AppServiceProvider.php` - Bound ProximityServiceInterface
- `routes/web.php` - Replaced placeholder with 6 dispatch routes
- `database/seeders/DatabaseSeeder.php` - Added AgencySeeder
- `tests/Feature/Navigation/RoleNavigationTest.php` - Updated route name

## Decisions Made
- Forward-only status transitions enforced via explicit allowedTransitions map -- prevents any backward state changes from dispatch context
- incident_unit pivot with unassigned_at null filter -- BelongsToMany relationship auto-filters to show only active assignments
- Route renamed from dispatch.index to dispatch.console -- semantic clarity for the real implementation
- ETA calculated at 30km/h urban speed -- appropriate for Butuan City traffic context

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Updated navigation tests for route rename**
- **Found during:** Task 2 (route registration)
- **Issue:** Existing RoleNavigationTest.php references dispatch.index route name which was renamed to dispatch.console
- **Fix:** Updated all dispatch.index references to dispatch.console; updated placeholder component assertion to match actual dispatch/Console component
- **Files modified:** tests/Feature/Navigation/RoleNavigationTest.php
- **Verification:** Full test suite passes (305 tests, 0 failures)
- **Committed in:** 1c91f98 (Task 2 GREEN commit)

---

**Total deviations:** 1 auto-fixed (Rule 3 - blocking)
**Impact on plan:** Route rename required updating existing test references. No scope creep.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All 6 dispatch backend endpoints functional and tested
- ProximityRankingService ready for map integration (Plan 02)
- Agency model ready for mutual aid UI (Plan 03/04)
- Stub Console.vue ready for frontend implementation (Plan 02-04)

## Self-Check: PASSED

All 20 created files verified present. All 3 task commits (9d5b5dc, a8f3e84, 1c91f98) verified in git log. 21 dispatch tests pass. Full suite 305 tests pass.

---
*Phase: 04-dispatch-console*
*Completed: 2026-03-13*
