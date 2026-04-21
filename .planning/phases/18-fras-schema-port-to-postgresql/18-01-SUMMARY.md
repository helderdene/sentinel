---
phase: 18-fras-schema-port-to-postgresql
plan: 01
subsystem: database
tags: [postgresql, postgis, magellan, uuid, geography, check-constraint, eloquent, factory, enum]

# Dependency graph
requires:
  - phase: 17-laravel-12-13-upgrade
    provides: Laravel 13 baseline with Magellan + PostGIS verified
provides:
  - cameras table in Postgres (UUID PK, geography(POINT, 4326) location, TIMESTAMPTZ(0) lifecycle columns, DB-level CHECK on status)
  - CameraStatus PHP enum (Online/Offline/Degraded — lowercase values matching DB CHECK)
  - Camera Eloquent model (HasUuids + HasFactory + Magellan Point cast + scopeActive)
  - CameraFactory (Butuan-City-jittered Point, status=Offline default)
affects: [18-02, 18-03, 18-04, 19-mqtt, 20-camera-admin, 21-recognition-bridge, 22-alerts-dpa]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "DB-level CHECK constraint via raw DB::statement after Schema::create (Shared Pattern B — first CHECK constraint in v1.0 schema)"
    - "Forward-compat nullable columns (camera_id_display, last_seen_at, decommissioned_at) reserve Phase 20/19 writes without follow-up migration"
    - "Lowercase string-backed enum convention for FRAS domain (departure from Incident SCREAMING_CASE) — matches DB CHECK literals"

key-files:
  created:
    - app/Enums/CameraStatus.php
    - database/migrations/2026_04_21_000001_create_cameras_table.php
    - app/Models/Camera.php
    - database/factories/CameraFactory.php
  modified: []

key-decisions:
  - "Migration filename chosen as 2026_04_21_000001_create_cameras_table.php — continues 2026_03_12_000001… numbering style and reserves 000002/000003/000004 slots for personnel, camera_enrollments, recognition_events in Plans 18-02..04"
  - "CHECK constraint name cameras_status_check is explicit (not Postgres-auto-named) — deterministic for future DROP CONSTRAINT in down() paths (Shared Pattern B)"
  - "Camera model does NOT override toArray() for {lat,lng} serialization (unlike Incident/Unit) — per D-66 minimalist scope; Phase 20 can add when the admin UI needs JSON responses"
  - "camera_id_display left null in factory (D-05) — Phase 20 AdminCameraController::store() will sequence CAM-XX via SUBSTRING/CAST regex pattern mirroring UnitFactory::autoId(); factory cannot sequence safely in parallel test runs"

patterns-established:
  - "Shared Pattern B (DB CHECK via raw DDL): first use in v1.0 schema — will be repeated in Plans 18-02 (personnel.category), 18-03 (camera_enrollments.status), 18-04 (recognition_events.severity)"
  - "FRAS lowercase enum values: CameraStatus uses 'online'/'offline'/'degraded' rather than Incident-family SCREAMING_CASE — establishes convention for PersonnelCategory, CameraEnrollmentStatus, RecognitionSeverity"

requirements-completed: [FRAMEWORK-04]

# Metrics
duration: 3min
completed: 2026-04-21
---

# Phase 18 Plan 01: FRAS cameras table port Summary

**cameras table landed in Postgres with UUID PK, geography(POINT,4326) location + GIST spatialIndex, TIMESTAMPTZ(0) lifecycle columns, and first-ever DB-level CHECK constraint in v1.0 schema — plus CameraStatus enum, Camera Eloquent model with Magellan Point cast, and CameraFactory emitting Butuan-City-jittered rows.**

## Performance

- **Duration:** 3min
- **Started:** 2026-04-21T09:13:56Z
- **Completed:** 2026-04-21T09:16:54Z
- **Tasks:** 2
- **Files created:** 4

## Accomplishments

- `cameras` table exists in Postgres with all 11 columns from D-03..D-12 (10 domain + created_at/updated_at): UUID PK, device_id UNIQUE (64), camera_id_display UNIQUE NULLABLE (10), name (100), location_label (150), geography(POINT,4326) location NULLABLE, status (20, default 'offline'), TIMESTAMPTZ(0) last_seen_at / decommissioned_at, plain timestamps()
- GIST spatial index `cameras_location_spatialindex` on `cameras.location` — satisfies SC5 prerequisite for Plan 18-06's `ST_DWithin` test
- DB-level `cameras_status_check` CHECK constraint present in `pg_constraint` — rejects non-enum status writes at the DB layer (mitigates T-18-01 tampering threat from threat_model)
- `CameraStatus` enum with `label()` helper in `app/Enums/` matching `IncidentOutcome` precedent, lowercase-backed to match DB CHECK literal values
- `Camera` model hydrates Magellan `Point` (`getLatitude()/getLongitude()` verified round-trip) and `CameraStatus` enum on refetch; `scopeActive()` excludes decommissioned rows (verified: 2 total / 1 active after decommissioning one)
- `Camera::factory()->create()` emits a valid UUIDv7-keyed row with Point location and `status=Offline` (verified via `php artisan tinker`)

## Task Commits

Each task was committed atomically:

1. **Task 1: CameraStatus enum + cameras migration** — `888f861` (feat)
2. **Task 2: Camera model + CameraFactory** — `f7bd4b1` (feat)

## Files Created

- `app/Enums/CameraStatus.php` (22 lines) — string-backed enum with `Online`/`Offline`/`Degraded` cases and `label()` helper
- `database/migrations/2026_04_21_000001_create_cameras_table.php` (43 lines) — Schema::create + spatialIndex + DB::statement CHECK; down() uses dropIfExists (drops CHECK transitively)
- `app/Models/Camera.php` (59 lines) — HasUuids + HasFactory, Point/CameraStatus/datetime casts, scopeActive(Builder) with PHPDoc generics
- `database/factories/CameraFactory.php` (36 lines) — `$model = Camera::class`, Point::makeGeodetic jitter around Butuan City center, status=Offline default

## Decisions Made

None beyond the three discretion decisions logged in frontmatter (migration filename, CHECK constraint naming, no toArray() override, factory leaves camera_id_display null). Plan 18-01 executed exactly as written.

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## Verification Summary

- `php artisan migrate:fresh` exits 0 — all 26 migrations run green, cameras table created in 4.15ms
- `php artisan db:table cameras` confirms all 11 columns with correct types (uuid, varchar(64/10/100/150/20), geography(Point,4326), timestamp(0) with time zone for last_seen_at/decommissioned_at, plain timestamp for created_at/updated_at)
- `DB::select` on `pg_constraint` returns `cameras_status_check` (CHECK constraint verified programmatically via tinker since `psql` CLI not in shell PATH)
- `Camera::factory()->create()` returns UUIDv7 (verified: `019daf52-74ec-7314-b4d3-4e035f03e228`), location round-trips as `Clickbar\Magellan\Data\Geometries\Point` with readable lat/lng
- `Camera::active()->count()` returns 1 of 2 after one row is decommissioned — scopeActive verified
- `vendor/bin/pint --dirty --format agent` returns `{"result":"pass"}` on both task iterations (formatting clean)
- `php artisan test --compact` — 538 passed, 51 failed, 2 skipped. All 51 failures confined to baseline Family A (`incident_categories_name_unique`: 10) + Family B (`users_pkey`: 5 — plus 36 downstream cascades); zero failures contain `cameras`, `Camera`, or `CameraStatus`. Failure count 51 falls within documented Phase 17 baseline distribution (41-59). No regressions introduced by Plan 18-01 per STATE.md line 312-315 family-classification gate.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Plan 18-02 (personnel table) unblocked: can now reuse Shared Pattern B (CHECK constraint DDL idiom) established here; also confirms the lowercase-enum + DB-CHECK + PHP-enum-cast pipeline round-trips correctly through Eloquent
- Plans 18-03 (camera_enrollments) and 18-04 (recognition_events) unblocked on the FK target: `cameras.id` uuid PK is now live; their `foreignUuid('camera_id')->constrained()` calls will resolve
- Plan 18-06 (convention tests) unblocked on the cameras side of the `ST_DWithin` spatial test — GIST index + geography column both present
- FRAMEWORK-04 partially satisfied (cameras slice); personnel + camera_enrollments + recognition_events complete the requirement in Plans 18-02..04

## Self-Check: PASSED

- `app/Enums/CameraStatus.php` — FOUND
- `database/migrations/2026_04_21_000001_create_cameras_table.php` — FOUND
- `app/Models/Camera.php` — FOUND
- `database/factories/CameraFactory.php` — FOUND
- Commit `888f861` — FOUND (Task 1)
- Commit `f7bd4b1` — FOUND (Task 2)

---
*Phase: 18-fras-schema-port-to-postgresql*
*Completed: 2026-04-21*
