---
phase: 18-fras-schema-port-to-postgresql
plan: 03
subsystem: database

tags: [postgres, migration, pivot, uuid, check-constraint, enum, fras, camera-enrollments]

# Dependency graph
requires:
  - phase: 18-01
    provides: cameras table with UUID PK (FK target)
  - phase: 18-02
    provides: personnel table with UUID PK (FK target)
provides:
  - camera_enrollments pivot table with UUID PK, UUID FKs to cameras and personnel (ON DELETE CASCADE)
  - Composite UNIQUE (camera_id, personnel_id) — FRAMEWORK-04 idempotency
  - Composite indexes (camera_id, status) + (personnel_id, status) for Phase 20 progress queries
  - camera_enrollments_status_check DB CHECK constraining status to pending/syncing/done/failed
  - CameraEnrollmentStatus enum (string-backed, lowercase cases + label() helper)
  - CameraEnrollment model extending Model (not Pivot) with HasUuids + HasFactory + enum cast
  - CameraEnrollmentFactory wiring Camera::factory() + Personnel::factory() sub-factories
affects: [18-04, 19, 20, 21, 22]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Shared Pattern B (raw DB::statement CHECK) applied a third time — now idiomatic for FRAS enum columns"
    - "Pivot table with own UUID PK uses extends Model (not Pivot) because rows carry id + timestamps"
    - "foreignUuid('col')->constrained('table')->cascadeOnDelete sugar for UUID FK with explicit table hint when Laravel pluralizer is ambiguous (personnel)"

key-files:
  created:
    - database/migrations/2026_04_21_000003_create_camera_enrollments_table.php
    - app/Enums/CameraEnrollmentStatus.php
    - app/Models/CameraEnrollment.php
    - database/factories/CameraEnrollmentFactory.php
  modified: []

key-decisions:
  - "foreignUuid('personnel_id')->constrained('personnel') uses explicit table argument — 18-PATTERNS warned that Laravel pluralizer may not map personnel_id → personnel (personnel is already plural), so the explicit table name guards against silent breakage"
  - "CameraEnrollment extends Model (not Pivot) because the row has its own UUID PK + timestamps; Pivot would force composite PK semantics that don't apply here"
  - "Status enum + DB CHECK values duplicated intentionally (pending/syncing/done/failed in both PHP enum and raw DB::statement literal) — belt-and-suspenders for garbage-write defence"
  - "Factory uses Camera::factory() and Personnel::factory() sub-factories (not hardcoded IDs) — CameraEnrollment::factory()->create() now builds the full 3-row chain in one call, matching IncidentFactory precedent"

patterns-established:
  - "DB CHECK constraint stable at scale — third application (cameras.status, personnel.category, camera_enrollments.status); recipe is well-known and ready for recognition_events.severity in plan 18-04"
  - "Pivot-with-UUID-PK model shape — extends Model, $timestamps stays true, no $table override needed because table name matches class pluralization"

requirements-completed: [FRAMEWORK-04]

# Metrics
duration: 3min
completed: 2026-04-21
---

# Phase 18 Plan 03: FRAS Schema Port — camera_enrollments Pivot Summary

**camera_enrollments pivot table with UUID PK, cascade FKs to cameras + personnel, composite UNIQUE for FRAMEWORK-04 idempotency, CHECK-constrained status enum, and factory chain that scaffolds all three rows in one call.**

## Performance

- **Duration:** 3 min
- **Started:** 2026-04-21T09:26:43Z
- **Completed:** 2026-04-21T09:29:12Z
- **Tasks:** 2
- **Files modified:** 4 files created

## Accomplishments
- camera_enrollments pivot DDL green on PostgreSQL 18.3 — 9 columns, 3 indexes, 2 FK constraints with cascade, 1 DB CHECK (verified in pg_constraint)
- CameraEnrollmentStatus enum lands with lowercase backed cases matching DB CHECK literals; label() method follows IncidentOutcome precedent
- CameraEnrollment model wires HasUuids + HasFactory + enum cast and declares minimal belongsTo relations to Camera and Personnel
- CameraEnrollmentFactory builds Camera + Personnel + CameraEnrollment via sub-factories — one `->create()` call is enough for integration tests
- Structural UNIQUE enforcement verified via live tinker: duplicate `(camera_id, personnel_id)` insert raises `UniqueConstraintViolationException`
- Full test suite holds at baseline (50 failed / 539 passed / 2 skipped) — within the 47-51 pre-existing Family A (incident_categories_name_unique) + Family B (users_pkey) variance; zero new regressions attributable to this plan

## Task Commits

1. **Task 1: Create CameraEnrollmentStatus enum and camera_enrollments migration** — `1f778a1` (feat)
2. **Task 2: Create CameraEnrollment model and factory** — `9f3af71` (feat)

## Files Created/Modified
- `database/migrations/2026_04_21_000003_create_camera_enrollments_table.php` — Pivot DDL: UUID PK, two foreignUuid cascade FKs, status varchar(20) default pending, timestampTz(0) enrolled_at, photo_hash varchar(32), last_error text, timestamps, UNIQUE (camera_id, personnel_id), (camera_id, status) + (personnel_id, status) indexes, raw DB::statement CHECK
- `app/Enums/CameraEnrollmentStatus.php` — String-backed enum (Pending/Syncing/Done/Failed) with `label()` helper
- `app/Models/CameraEnrollment.php` — Extends Model (not Pivot); HasUuids + HasFactory; enum cast + datetime cast; belongsTo camera() and personnel()
- `database/factories/CameraEnrollmentFactory.php` — Sub-factory FK wiring via `Camera::factory()` and `Personnel::factory()`

## Decisions Made
- **Explicit `constrained('personnel')` table argument** on the personnel FK — the plan flagged that `foreignUuid('personnel_id')->constrained()` may or may not infer `personnel` correctly depending on Laravel pluralizer behaviour. Passing the table name explicitly removes the ambiguity and matches the Personnel model's own `$table = 'personnel'` override.
- **`extends Model` (not `extends Pivot`)** on CameraEnrollment — the row has its own UUID PK plus created_at/updated_at; Pivot would default `public $incrementing = false` with composite-PK semantics that don't fit.
- **Lowercase enum backed values** — matches CameraStatus and PersonnelCategory precedent established in 18-01 and 18-02; the DB CHECK literal set is lower-case so the enum cast round-trips cleanly.
- **`enrolled_at` uses precision 0** (not microseconds) — this column records a human-observable enrollment event, not a recognition timestamp; microsecond precision is reserved for `recognition_events.captured_at/received_at` in plan 18-04.

## Deviations from Plan

None — plan executed exactly as written. All acceptance criteria met on the first pass:
- `php artisan migrate:fresh` exits 0 with cameras → personnel → camera_enrollments in order
- `db:table camera_enrollments` shows all 9 columns
- `CameraEnrollment::factory()->create()` builds the full chain and status casts to `'pending'`
- Duplicate `(camera_id, personnel_id)` tinker insert throws UniqueConstraintViolationException (SC confirmed ahead of Plan 05)
- `camera_enrollments_status_check` present in `pg_constraint`
- Pint clean on both commits

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness
- Wave 2 half complete (plan 18-03). Plan 18-04 (recognition_events) can now FK-reference cameras, personnel, and bridge through plan 18-05 tests.
- Shared Pattern B (raw DB CHECK) confirmed stable on a third application; plan 18-04 can reuse verbatim for `recognition_events.severity`.
- Factory sub-factory pattern proven end-to-end; plan 18-04's RecognitionEventFactory can rely on the same `Camera::factory()` wiring without revisiting the Laravel factory resolution order.

## Self-Check: PASSED

Claimed files:
- `app/Enums/CameraEnrollmentStatus.php` — FOUND
- `database/migrations/2026_04_21_000003_create_camera_enrollments_table.php` — FOUND
- `app/Models/CameraEnrollment.php` — FOUND
- `database/factories/CameraEnrollmentFactory.php` — FOUND

Claimed commits:
- `1f778a1` (Task 1) — FOUND
- `9f3af71` (Task 2) — FOUND

---
*Phase: 18-fras-schema-port-to-postgresql*
*Completed: 2026-04-21*
