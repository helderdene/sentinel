---
phase: 01-foundation
plan: 01
subsystem: database
tags: [postgresql, postgis, eloquent, rbac, migrations, spatial, magellan, enums, seeders]

requires:
  - phase: none
    provides: "First phase - no prior dependencies"
provides:
  - "PostgreSQL with PostGIS spatial database"
  - "8 migrations creating 7 tables plus user extensions"
  - "7 Eloquent models with Magellan spatial casts"
  - "5 PHP backed enums (UserRole, IncidentStatus, IncidentPriority, UnitStatus, UnitType)"
  - "RBAC middleware (EnsureUserHasRole) and 9 Laravel Gates"
  - "7 model factories with role states on UserFactory"
  - "4 seeders: admin user, 49 incident types, 10 units, 86 barangays with boundary polygons"
  - "8 foundation test files covering spatial, RBAC, and model behavior"
affects: [intake, dispatch, responder, analytics, admin-panel, real-time]

tech-stack:
  added: [clickbar/laravel-magellan]
  patterns: [geography-column-with-magellan-cast, backed-enum-role-on-users, custom-middleware-alias, laravel-gates-for-permissions, uuid-primary-key-with-HasUuids, auto-generated-incident-number]

key-files:
  created:
    - app/Enums/UserRole.php
    - app/Enums/IncidentStatus.php
    - app/Enums/IncidentPriority.php
    - app/Enums/UnitStatus.php
    - app/Enums/UnitType.php
    - app/Models/Incident.php
    - app/Models/Unit.php
    - app/Models/Barangay.php
    - app/Models/IncidentType.php
    - app/Models/IncidentTimeline.php
    - app/Models/IncidentMessage.php
    - app/Http/Middleware/EnsureUserHasRole.php
    - database/migrations/2026_03_12_000001_enable_postgis_extension.php
    - database/migrations/2026_03_12_000002_add_role_fields_to_users_table.php
    - database/migrations/2026_03_12_000003_create_units_table.php
    - database/migrations/2026_03_12_000004_create_barangays_table.php
    - database/migrations/2026_03_12_000005_create_incident_types_table.php
    - database/migrations/2026_03_12_000006_create_incidents_table.php
    - database/migrations/2026_03_12_000007_create_incident_timeline_table.php
    - database/migrations/2026_03_12_000008_create_incident_messages_table.php
    - database/factories/IncidentFactory.php
    - database/factories/UnitFactory.php
    - database/factories/BarangayFactory.php
    - database/factories/IncidentTypeFactory.php
    - database/factories/IncidentTimelineFactory.php
    - database/factories/IncidentMessageFactory.php
    - database/seeders/AdminUserSeeder.php
    - database/seeders/IncidentTypeSeeder.php
    - database/seeders/UnitSeeder.php
    - database/seeders/BarangaySeeder.php
    - tests/Feature/Foundation/PostgisSetupTest.php
    - tests/Feature/Foundation/RoleAccessTest.php
    - tests/Feature/Foundation/UserUnitTest.php
    - tests/Feature/Foundation/IncidentModelTest.php
    - tests/Feature/Foundation/UnitModelTest.php
    - tests/Feature/Foundation/IncidentTimelineTest.php
    - tests/Feature/Foundation/IncidentMessageTest.php
    - tests/Feature/Foundation/BarangaySpatialTest.php
    - .env.testing
  modified:
    - app/Models/User.php
    - app/Providers/AppServiceProvider.php
    - app/Providers/FortifyServiceProvider.php
    - bootstrap/app.php
    - config/fortify.php
    - phpunit.xml
    - composer.json
    - composer.lock
    - database/factories/UserFactory.php
    - database/seeders/DatabaseSeeder.php

key-decisions:
  - "Switched all tests to PostgreSQL (no SQLite split) for consistent behavior"
  - "Used clickbar/laravel-magellan for PostGIS model casts and spatial query builder"
  - "Custom role enum + EnsureUserHasRole middleware + Laravel Gates instead of Spatie"
  - "Incident uses HasUuids trait with boot method for auto-generating INC-YYYY-NNNNN numbers"
  - "Unit uses string primary key (AMB-01 style) instead of auto-increment"
  - "BarangaySeeder extracts first polygon from MultiPolygon GeoJSON for simple boundaries"

patterns-established:
  - "Geography columns: $table->geography('col', subtype: 'point', srid: 4326) + Magellan Point/Polygon cast"
  - "Role checking: EnsureUserHasRole middleware with 'role:admin,dispatcher' syntax"
  - "Enum casts in casts() method (not $casts property)"
  - "Factory role states: User::factory()->admin(), ->dispatcher(), ->responder(), ->supervisor()"
  - "JSONB columns: cast as 'array' in model casts() method"
  - "Polymorphic actor/sender: morphTo() with actor_type + actor_id columns"

requirements-completed: [FNDTN-01, FNDTN-02, FNDTN-03, FNDTN-04, FNDTN-05, FNDTN-06, FNDTN-07, FNDTN-08]

duration: 11min
completed: 2026-03-12
---

# Phase 1 Plan 1: Database Foundation Summary

**PostgreSQL/PostGIS database with 7 Eloquent models, Magellan spatial casts, RBAC middleware with 9 gates, 86 seeded barangay boundaries, and 8 passing foundation test files**

## Performance

- **Duration:** 11 min
- **Started:** 2026-03-12T15:22:40Z
- **Completed:** 2026-03-12T15:33:58Z
- **Tasks:** 3
- **Files modified:** 49

## Accomplishments
- PostgreSQL with PostGIS 3.6 as sole database for dev and test; 8 migrations create all tables with geography columns and GiST spatial indexes
- 7 Eloquent models with Magellan Point/Polygon casts, proper relationships, and enum casts; Incident model auto-generates INC-YYYY-NNNNN numbers
- RBAC system with EnsureUserHasRole middleware, 9 Laravel Gates matching spec Section 9 permissions matrix, and 4-role UserRole enum
- 86 Butuan City barangay boundary polygons seeded from docs/brgy.json; point-in-polygon ST_Contains query verified working
- 49 incident types across 8 categories, 10 response units, and admin user seeded
- Full test suite: 84 passed, 2 skipped (registration correctly disabled), 210 assertions

## Task Commits

Each task was committed atomically:

1. **Task 1: PostgreSQL/PostGIS setup, Fortify config, enums, and all 8 migrations** - `f03b65c` (feat)
2. **Task 2: Eloquent models, RBAC middleware, gates, and model tests** - `2924881` (feat, TDD)
3. **Task 3: Factories, seeders, barangay GeoJSON, and spatial test** - `8f7440a` (feat)

## Files Created/Modified
- `app/Enums/*.php` - 5 PHP backed enums (UserRole, IncidentStatus, IncidentPriority, UnitStatus, UnitType)
- `app/Models/*.php` - 7 Eloquent models with spatial casts and relationships
- `app/Http/Middleware/EnsureUserHasRole.php` - Route-level role checking middleware
- `app/Providers/AppServiceProvider.php` - 9 Laravel Gates for permissions matrix
- `bootstrap/app.php` - Registered 'role' middleware alias
- `database/migrations/2026_03_12_00000*.php` - 8 migrations (PostGIS, users, units, barangays, incident_types, incidents, timeline, messages)
- `database/factories/*.php` - 7 factories with role states
- `database/seeders/*.php` - 4 seeders (admin, types, units, barangays)
- `.env.testing` - PostgreSQL test database configuration
- `phpunit.xml` - Removed SQLite overrides for PostgreSQL testing
- `config/fortify.php` - Disabled registration feature
- `tests/Feature/Foundation/*.php` - 8 test files covering all foundation requirements

## Decisions Made
- Switched all tests to PostgreSQL instead of keeping a SQLite/PostgreSQL split -- avoids behavior differences and simplifies CI
- Used clickbar/laravel-magellan v2 for PostGIS model casts instead of raw DB::select for spatial queries
- Custom role enum + middleware + Gates instead of Spatie laravel-permission -- 4 fixed roles with 1 per user doesn't justify 5 extra tables
- Incident uses HasUuids with boot-method auto-generation of INC-YYYY-NNNNN (DB-level max+1 sequence)
- Unit primary key is string (AMB-01 style) for human readability in dispatch operations
- BarangaySeeder takes first polygon from MultiPolygon GeoJSON features for boundary storage

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed JSONB test assertions for PostgreSQL key ordering**
- **Found during:** Task 2 (TDD GREEN phase)
- **Issue:** PostgreSQL JSONB sorts keys alphabetically; `toBe()` uses strict identity comparison which fails on reordered keys
- **Fix:** Changed `toBe($array)` to `toEqual($array)` for JSONB-cast fields in IncidentModelTest and IncidentTimelineTest
- **Files modified:** tests/Feature/Foundation/IncidentModelTest.php, tests/Feature/Foundation/IncidentTimelineTest.php
- **Committed in:** 2924881

**2. [Rule 1 - Bug] Fixed CarbonImmutable assertion in IncidentMessageTest**
- **Found during:** Task 2 (TDD GREEN phase)
- **Issue:** AppServiceProvider configures `Date::use(CarbonImmutable::class)`, so datetime casts return CarbonImmutable, not Carbon
- **Fix:** Changed assertion from `Carbon::class` to `CarbonImmutable::class`
- **Files modified:** tests/Feature/Foundation/IncidentMessageTest.php
- **Committed in:** 2924881

**3. [Rule 1 - Bug] Fixed point-in-polygon test coordinate**
- **Found during:** Task 3 (BarangaySpatialTest)
- **Issue:** Test coordinate (125.5614, 8.9618) fell in BuhanginPoblacion, not AgaoPoblacion based on actual boundary data
- **Fix:** Used centroid of AgaoPoblacion polygon (125.5599, 8.9607) for accurate point-in-polygon assertion
- **Files modified:** tests/Feature/Foundation/BarangaySpatialTest.php
- **Committed in:** 8f7440a

---

**Total deviations:** 3 auto-fixed (3 bugs)
**Impact on plan:** All auto-fixes were test assertion corrections. No scope creep.

## Issues Encountered
None beyond the auto-fixed test assertions.

## User Setup Required
None - PostgreSQL and PostGIS were pre-configured per environment notes.

## Next Phase Readiness
- Database foundation complete: all models, migrations, seeders, and RBAC working
- Ready for Phase 1 Plan 2 (Admin Panel): models and gates exist for user/role CRUD, incident type management, and barangay metadata editing
- Ready for Phase 1 Plan 3 (Role-based Navigation): UserRole enum and gates available for sidebar filtering and Inertia shared props

## Self-Check: PASSED

All 21 key files verified present. All 3 task commits verified in git log.

---
*Phase: 01-foundation*
*Completed: 2026-03-12*
