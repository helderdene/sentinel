---
phase: 18-fras-schema-port-to-postgresql
plan: 05
subsystem: testing
tags: [pest, postgis, st_dwithin, unique-constraint, seeder, framework-05]

# Dependency graph
requires:
  - phase: 18-01
    provides: cameras table + Camera model + CameraFactory (spatial location column)
  - phase: 18-02
    provides: personnel table + Personnel model (fixture for enrollments)
  - phase: 18-03
    provides: camera_enrollments table (FK chain exercise)
  - phase: 18-04
    provides: recognition_events table + RecognitionEvent model + RecognitionEventFactory + UNIQUE(camera_id, record_id)
provides:
  - CameraSpatialQueryTest — ST_DWithin feature test against cameras.location (SC5)
  - RecognitionEventIdempotencyTest — UniqueConstraintViolationException feature test (SC2 + FRAMEWORK-06)
  - FrasPlaceholderSeeder — empty-body seeder satisfying SC4 wording without production risk
  - FRAMEWORK-05 verification — pgsql already configured in .env.testing
  - pest()->group('fras') convention (first use in repo)
affects: [phase-19-mqtt-listener, phase-20-camera-personnel-admin, phase-21-recognition-bridge]

# Tech tracking
tech-stack:
  added: []  # No new dependencies; uses existing Pest 4 + Magellan + Laravel 13
  patterns:
    - "Pest group tag convention: pest()->group('fras') for scoped subsystem test runs"
    - "UniqueConstraintViolationException feature-level assertion via expect(fn)->toThrow()"
    - "ST_DWithin feature-test idiom against geography column (lng-first ST_MakePoint, meters radius)"
    - "Placeholder seeder pattern: empty-body class, NOT registered in DatabaseSeeder (D-62)"

key-files:
  created:
    - tests/Feature/Fras/CameraSpatialQueryTest.php
    - tests/Feature/Fras/RecognitionEventIdempotencyTest.php
    - database/seeders/FrasPlaceholderSeeder.php
  modified: []

key-decisions:
  - "pest()->group('fras') introduced as first-ever Pest group tag in repo — enables ./vendor/bin/pest --group=fras scoped subsystem runs without phpunit.xml edits (D-58)"
  - "FrasPlaceholderSeeder body left empty AND NOT registered in DatabaseSeeder per D-62 — migrate:fresh --seed stays production-safe while satisfying SC4 'every new table has a seeder' wording"
  - "FRAMEWORK-05 verified by inspection only — .env.testing was already configured with DB_CONNECTION=pgsql in pre-phase-18 work; no reconfiguration needed"
  - "CameraSpatialQueryTest uses a standalone lat/lng pair (Butuan plaza 8.9475, 125.5406) instead of an Incident foreign reference — decouples spatial test from Incident-family schema changes and sidesteps IncidentFactory's required foreign-key chain for a pure spatial-index proof"

patterns-established:
  - "Pest group convention: pest()->group('{subsystem}'); as first top-of-file statement after use imports"
  - "Feature-level DB-constraint assertion: expect(fn () => Model::factory()->for(...)->create(...))->toThrow(UniqueConstraintViolationException::class) — first use in repo"
  - "Placeholder seeder: empty run() body with intentional-no-op comment, never wired into DatabaseSeeder::run()"

requirements-completed:
  - FRAMEWORK-04  # Incremental — recognition_events UNIQUE proven by test
  - FRAMEWORK-05  # pgsql test runner verified
  - FRAMEWORK-06  # Idempotency proven by test

# Metrics
duration: 5min
completed: 2026-04-21
---

# Phase 18 Plan 05: FRAS Test Seam + Placeholder Seeder + FRAMEWORK-05 Verification Summary

**Closes Phase 18 with 5 Pest feature tests proving ST_DWithin and UNIQUE(camera_id, record_id) on pgsql, a production-safe placeholder seeder satisfying SC4 wording without writing data, and inspection-verified FRAMEWORK-05.**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-04-21T09:37:15Z
- **Completed:** 2026-04-21T09:42:24Z
- **Tasks:** 2
- **Files modified:** 3 created, 0 modified

## Accomplishments

- **CameraSpatialQueryTest** (2 tests) — asserts ST_DWithin correctly finds a ~200m camera and excludes a ~5km camera, plus an empty-result case when no cameras fall inside the 500m radius. Executes in 0.93s + 0.04s on cold pgsql fixtures.
- **RecognitionEventIdempotencyTest** (3 tests) — asserts the UNIQUE(camera_id, record_id) constraint throws `UniqueConstraintViolationException` on duplicate insert, while correctly permitting same record_id on different cameras and different record_ids on the same camera. All 3 tests green in ~0.11s combined.
- **FrasPlaceholderSeeder** — empty-body class with intentional-no-op comment satisfying SC4 "every new table has a seeder" wording. NOT wired into DatabaseSeeder (D-62) — `php artisan migrate:fresh --seed` stays green on production without writing FRAS rows.
- **FRAMEWORK-05 verified** — `.env.testing` already contains `DB_CONNECTION=pgsql`; `./vendor/bin/pest --group=fras` runs successfully against PostgreSQL 18.3 + PostGIS.
- **Green gate confirmed:** `migrate:fresh --seed` exit 0 + `pest --group=fras` 5/5 in 1.01s + `php artisan test --compact` confined to documented Phase 17 baseline Family A/B failures (47 failures within 41–59 variance band).

## Task Commits

1. **Task 1: Create CameraSpatialQueryTest + RecognitionEventIdempotencyTest** — `7458a82` (test)
2. **Task 2: Create FrasPlaceholderSeeder + verify FRAMEWORK-05 + end-to-end green gate** — `d62f74a` (feat)

**Plan metadata:** (final commit pending — includes SUMMARY.md + STATE.md + ROADMAP.md + REQUIREMENTS.md)

## Files Created/Modified

- `tests/Feature/Fras/CameraSpatialQueryTest.php` — 2 Pest tests asserting ST_DWithin against `cameras.location` geography (SC5); uses `pest()->group('fras')`.
- `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` — 3 Pest tests: duplicate (camera_id, record_id) throws `UniqueConstraintViolationException`; cross-camera and cross-record-id permutations permitted (SC2 + FRAMEWORK-06).
- `database/seeders/FrasPlaceholderSeeder.php` — empty-body Seeder subclass with intentional-no-op comment (D-62). Not registered in DatabaseSeeder.

## Decisions Made

- **Standalone coordinates in CameraSpatialQueryTest** — used Butuan plaza literal lat/lng (8.9475, 125.5406) rather than creating an Incident fixture. Decoupling from Incident-family schema reduces test fragility: plan 18-05 should not break if Phase 21 changes Incident factory defaults. The ST_DWithin proof needs only two cameras and a query point.
- **pest()->group('fras') as first-ever Pest group tag** — no prior usage in the repo (verified 2026-04-21 grep). Adopts Pest 4 native API per D-58; runs via `./vendor/bin/pest --group=fras` with no `phpunit.xml` edit required.
- **FrasPlaceholderSeeder stays empty AND unregistered** — D-62 resolution. Satisfies SC4 wording ("every new table has a factory and a seeder") with file-presence alone; DatabaseSeeder is untouched so `migrate:fresh --seed` carries zero FRAS-related risk into production.

## Deviations from Plan

None — plan executed exactly as written. Both feature tests passed on first run; FrasPlaceholderSeeder scaffolded cleanly; FRAMEWORK-05 was already satisfied pre-phase.

## Issues Encountered

**Full-suite test baseline noise.** `php artisan test --compact` reported 47 failures out of 594 tests. Investigation confirmed all failures fall into the two documented Phase 17 baseline families (STATE.md decision log):

- **Family A:** `incident_categories_name_unique` duplicate-key violations (fake()->unique() drift in IncidentCategoryFactory)
- **Family B:** `users_pkey` duplicate-key violations (test-fixture ID-reuse pattern)

47 is within the documented 41–59 variance band. Zero failures touch `tests/Feature/Fras/*`. Zero new root-cause families. Not a Phase 18 regression.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- **Phase 18 complete at 5/6 plans** — only Plan 06 (phase verification / close-out) remains.
- **Phase 19 (MQTT Pipeline)** — can proceed; RecognitionHandler will insert recognition_events rows and will be protected from duplicate-record idempotency drift by the feature test added here. If Phase 19 accidentally relaxes the UNIQUE constraint, this test fails immediately in CI.
- **Phase 20 (Camera + Personnel Admin)** — can proceed; Camera model + factory proven via spatial test. If Phase 20 accidentally drops the spatial index or changes the geography column to geometry, the spatial test fails.
- **Phase 21 (Recognition Bridge)** — all four FRAS tables proven operational on pgsql; no schema work remaining.

## Self-Check: PASSED

Verified 2026-04-21:
- `tests/Feature/Fras/CameraSpatialQueryTest.php` exists and contains `pest()->group('fras')` + `ST_DWithin`.
- `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` exists and contains `pest()->group('fras')` + `UniqueConstraintViolationException`.
- `database/seeders/FrasPlaceholderSeeder.php` exists with `class FrasPlaceholderSeeder extends Seeder` and empty `run()` body.
- Commit `7458a82` (Task 1 — test) present in `git log`.
- Commit `d62f74a` (Task 2 — feat) present in `git log`.
- `.env.testing` has `DB_CONNECTION=pgsql` on line 7.
- `database/seeders/DatabaseSeeder.php` unchanged (`git diff` empty).
- `./vendor/bin/pest --group=fras` — 5 passed, 6 assertions, 1.01s.
- `php artisan migrate:fresh --seed` — exit 0 on pgsql.

---
*Phase: 18-fras-schema-port-to-postgresql*
*Completed: 2026-04-21*
