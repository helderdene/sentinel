---
phase: 09-create-a-public-facing-reporting-app
plan: 01
subsystem: api
tags: [laravel, api-resources, rate-limiting, cors, citizen-reporting, tracking-token]

# Dependency graph
requires:
  - phase: 02-intake
    provides: "Incident model, IncidentType model, BarangayLookupService, IncidentCreated event"
  - phase: 08-implement-operator-role-and-intake-layer-ui
    provides: "Operator intake feed that consumes IncidentCreated events"
provides:
  - "4 citizen API endpoints under /api/v1/citizen/*"
  - "Incident tracking_token field with 8-char unambiguous generator"
  - "IncidentType show_in_public_app boolean for admin curation"
  - "CitizenReportResource with citizen-facing status mapping"
  - "Rate limiting (5 submissions/min, 60 reads/min)"
  - "CORS configuration for cross-origin citizen app"
  - "Admin toggle for show_in_public_app on incident types"
affects: [09-02-PLAN, 09-03-PLAN]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Versioned API routes under /api/v1/ namespace"
    - "API Resources for citizen-facing JSON responses (no internal data leakage)"
    - "CITIZEN_STATUS_MAP constant for internal-to-citizen status translation"
    - "Tracking token with collision-resistant 30-char unambiguous alphabet"

key-files:
  created:
    - routes/api.php
    - app/Http/Controllers/Api/V1/CitizenReportController.php
    - app/Http/Requests/Api/V1/StoreCitizenReportRequest.php
    - app/Http/Resources/V1/CitizenReportResource.php
    - app/Http/Resources/V1/CitizenIncidentTypeResource.php
    - app/Http/Resources/V1/CitizenBarangayResource.php
    - config/cors.php
    - database/migrations/2026_03_13_074547_add_citizen_reporting_columns.php
    - tests/Feature/CitizenReportTest.php
    - tests/Feature/CitizenReportInfrastructureTest.php
    - tests/Unit/CitizenStatusMappingTest.php
    - tests/Unit/CitizenReportServiceTest.php
  modified:
    - app/Models/Incident.php
    - app/Models/IncidentType.php
    - app/Providers/AppServiceProvider.php
    - bootstrap/app.php
    - app/Http/Requests/Admin/StoreIncidentTypeRequest.php
    - app/Http/Requests/Admin/UpdateIncidentTypeRequest.php
    - resources/js/pages/admin/IncidentTypeForm.vue

key-decisions:
  - "30-char unambiguous alphabet (no O/I/L/0/1) for tracking tokens to avoid citizen confusion"
  - "incidentTypes() uses orWhere code=OTHER_EMERGENCY instead of scopePublic alone to always include catch-all type"
  - "CitizenReportResource stores description in notes field for consistency with existing Incident model"
  - "Rate limiters defined in AppServiceProvider::configureRateLimiters() following existing boot pattern"

patterns-established:
  - "API versioning: /api/v1/citizen/* route group with dedicated controller namespace Api\\V1"
  - "API Resources pattern: separate resource classes per endpoint audience (citizen vs admin)"
  - "Status mapping constant: CITIZEN_STATUS_MAP on resource class for translating internal statuses"

requirements-completed: [CITIZEN-01, CITIZEN-02, CITIZEN-03, CITIZEN-04, CITIZEN-05, CITIZEN-06, CITIZEN-08, CITIZEN-09]

# Metrics
duration: 10min
completed: 2026-03-13
---

# Phase 9 Plan 1: Backend API Summary

**Citizen reporting REST API with tracking tokens, status mapping, rate limiting, and admin-configurable public incident types**

## Performance

- **Duration:** 10 min
- **Started:** 2026-03-13T07:44:41Z
- **Completed:** 2026-03-13T07:54:39Z
- **Tasks:** 3
- **Files modified:** 19

## Accomplishments
- 4 citizen API endpoints operational under /api/v1/citizen/* with rate limiting and CORS
- Citizen report creates Incident with channel=app, status=PENDING, 8-char tracking token, fires IncidentCreated event for intake feed
- incidentTypes() always includes "Other Emergency" regardless of show_in_public_app flag per CITIZEN-04
- Citizen-facing status mapping (Received/Verified/Dispatched/Resolved) prevents internal status leakage
- Admin can toggle show_in_public_app on incident type create/edit forms
- 25 tests (13 feature + 5 unit status mapping + 3 unit token + 4 infrastructure) all passing

## Task Commits

Each task was committed atomically:

1. **Task 1: Database migrations, model updates, and API infrastructure** - `d577431` (feat)
2. **Task 2: API controller, resources, form request, and full tests** - `3ac483e` (feat)
3. **Task 3: Admin toggle for show_in_public_app on incident types** - `172786d` (feat)

## Files Created/Modified
- `database/migrations/2026_03_13_074547_add_citizen_reporting_columns.php` - Adds tracking_token to incidents, show_in_public_app to incident_types
- `app/Models/Incident.php` - tracking_token in fillable, generateTrackingToken() method
- `app/Models/IncidentType.php` - show_in_public_app in fillable/casts, scopePublic()
- `routes/api.php` - 4 citizen API routes with throttle middleware
- `bootstrap/app.php` - API route registration
- `config/cors.php` - CORS configuration for citizen app
- `app/Providers/AppServiceProvider.php` - Rate limiters (citizen-reports: 5/min, citizen-reads: 60/min)
- `app/Http/Controllers/Api/V1/CitizenReportController.php` - Full CRUD: incidentTypes, barangays, store, show
- `app/Http/Requests/Api/V1/StoreCitizenReportRequest.php` - Validation for citizen report submission
- `app/Http/Resources/V1/CitizenReportResource.php` - Citizen-facing JSON with CITIZEN_STATUS_MAP
- `app/Http/Resources/V1/CitizenIncidentTypeResource.php` - Public type data (id, name, category, code, priority, description)
- `app/Http/Resources/V1/CitizenBarangayResource.php` - Barangay id+name only (no geometry)
- `app/Http/Requests/Admin/StoreIncidentTypeRequest.php` - Added show_in_public_app validation
- `app/Http/Requests/Admin/UpdateIncidentTypeRequest.php` - Added show_in_public_app validation
- `resources/js/pages/admin/IncidentTypeForm.vue` - Added "Show in Citizen App" checkbox toggle
- `tests/Feature/CitizenReportTest.php` - 13 feature tests for all API behaviors
- `tests/Feature/CitizenReportInfrastructureTest.php` - 4 infrastructure tests
- `tests/Unit/CitizenStatusMappingTest.php` - 5 unit tests for status mapping completeness
- `tests/Unit/CitizenReportServiceTest.php` - 3 unit tests for token generation

## Decisions Made
- Used 30-char alphabet (A-Z minus O/I/L, 0-9 minus 0/1) for tracking tokens to avoid citizen visual confusion
- incidentTypes() query uses orWhere('code', 'OTHER_EMERGENCY') alongside show_in_public_app filter to guarantee catch-all type is always visible
- Citizen description stored in existing notes field rather than adding new column, maintaining Incident model consistency
- Rate limiters added to AppServiceProvider::configureRateLimiters() method following the existing boot pattern (configureDefaults, configureGates)
- CORS published with wildcard origins for development; production should restrict to citizen app domain

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All 4 citizen API endpoints operational and tested for the report app SPA (Plan 09-02)
- CitizenReportResource JSON contract defined for frontend consumption
- Rate limiting active to prevent API abuse from public endpoints
- Admin show_in_public_app toggle ready for curating visible incident types

## Self-Check: PASSED

All 13 created files verified present. All 3 task commits (d577431, 3ac483e, 172786d) verified in git log. 25 tests passing. Full test suite (330 tests) passing with no regressions.

---
*Phase: 09-create-a-public-facing-reporting-app*
*Completed: 2026-03-13*
