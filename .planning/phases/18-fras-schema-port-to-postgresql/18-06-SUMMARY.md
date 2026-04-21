---
phase: 18-fras-schema-port-to-postgresql
plan: 06
subsystem: testing

tags: [pest, postgres, information_schema, pg_constraint, enum-parity, schema-regression, fras]

requires:
  - phase: 18-fras-schema-port-to-postgresql
    provides: 4 FRAS tables (cameras, personnel, camera_enrollments, recognition_events), 4 PHP backed enums with DB CHECK constraints, mandatory feature tests (Plan 05)
provides:
  - tests/Feature/Fras/SchemaTest.php — 7-case information_schema regression covering UUID/geography/jsonb/timestamptz(6), GIST + GIN indexes, FRAMEWORK-06 UNIQUE, composite indexes
  - tests/Feature/Fras/EnumCheckParityTest.php — 4-case PHP enum ↔ DB CHECK parity guard catching drift in either direction
  - 16-test fras-group suite (~1.5s) bundling mandatory + optional regression coverage
affects: [phase-19-mqtt, phase-20-camera-admin, phase-21-recognition-bridge, phase-22-alerts-dpa]

tech-stack:
  added: []
  patterns:
    - "information_schema + pg_indexes + pg_constraint introspection as CI-time schema drift guard"
    - "pg_get_constraintdef() + regex (/'([^']+)'/) for extracting CHECK IN values — shape-agnostic (IN or ANY(ARRAY))"
    - "ILIKE instead of LIKE for Postgres/PostGIS index-definition matches (USING gist vs USING GIST version drift)"
    - "PHP backed-enum ↔ DB CHECK parity asserted via sorted value equality (collect()->sort()->values()->all())"

key-files:
  created:
    - tests/Feature/Fras/SchemaTest.php
    - tests/Feature/Fras/EnumCheckParityTest.php
  modified: []

key-decisions:
  - "extractCheckValues helper uses regex /'([^']+)'/ on pg_get_constraintdef output — shape-agnostic across both Postgres CHECK output dialects (IN (...) vs (col)::text = ANY (ARRAY[...::text]))"
  - "ILIKE for all pg_indexes.indexdef string matches — Postgres/PostGIS versions emit 'USING gist' vs 'USING GIST' inconsistently; case-insensitive matches survive upgrades"
  - "EnumCheckParityTest placed under tests/Feature/Fras/ (not tests/Unit/Conventions/) — test does live DB introspection, so Feature-shaped; inherits RefreshDatabase + pgsql binding from tests/Pest.php automatically"
  - "SchemaTest asserts FRAMEWORK-06 UNIQUE(camera_id, record_id) structurally (shape), not behaviorally — Plan 05's RecognitionEventIdempotencyTest already proves behavior; Plan 06 guards structure"
  - "Sorted-equality comparison (collect()->sort()->values()->all()) rather than set-equality — order-insensitive, detects missing OR extra values on either side"

patterns-established:
  - "Pattern: Postgres system-catalog regression tests tagged pest()->group('<subsystem>') colocate with subsystem's mandatory feature tests"
  - "Pattern: Parity tests between PHP enums and DB CHECK constraints are Feature tests (not Unit) when they introspect live schema"

requirements-completed: [FRAMEWORK-04]

duration: 3min
completed: 2026-04-21
---

# Phase 18 Plan 06: FRAS Schema Regression Test Suite Summary

**Two Pest regression tests (11 new cases total) guarding FRAS schema shape and PHP-enum/DB-CHECK parity against drift across Phase 19–22 migrations.**

## Performance

- **Duration:** 3min
- **Started:** 2026-04-21T09:45:20Z
- **Completed:** 2026-04-21T09:48:33Z
- **Tasks:** 2
- **Files created:** 2
- **Files modified:** 0
- **fras-group runtime:** 1.43s (16 tests, 42 assertions) — well under the 10s latency gate from 18-VALIDATION.md

## Accomplishments

- **SchemaTest.php** — 7 introspection cases catching any Phase 19–22 migration that drops a column, renames a type, or breaks an index. Asserts: `cameras.id` is uuid; `cameras.location` is geography with a GIST spatial index; personnel has all 14 FRAS columns (id/custom_id/name/gender/birthday/id_card/phone/address/photo_path/photo_hash/category/expires_at/consent_basis/decommissioned_at); `recognition_events.raw_payload` + `target_bbox` are jsonb; captured_at + received_at have microsecond precision (datetime_precision=6); GIN index on raw_payload uses jsonb_path_ops opclass; FRAMEWORK-06 UNIQUE(camera_id, record_id) exists; camera_enrollments has UNIQUE(camera_id, personnel_id) + composite indexes on (camera_id, status) and (personnel_id, status).
- **EnumCheckParityTest.php** — 4 parity cases, one per enum (CameraStatus, PersonnelCategory, CameraEnrollmentStatus, RecognitionSeverity). Each reads its target CHECK constraint via pg_get_constraintdef(), regex-extracts quoted values, and asserts sorted equality with `::cases()` backed values. Any drift in either direction (PHP-side case addition without CHECK update, or CHECK update without PHP case) now fails CI instead of silently shipping to production.
- **Combined fras-group expanded from 5 mandatory tests (Plan 05) to 16 total** — single `./vendor/bin/pest --group=fras` exercises both behavioral and structural contracts in ~1.5s.

## Task Commits

1. **Task 1: Create SchemaTest.php** — `ff46bbd` (test) — 7 information_schema/pg_indexes/pg_constraint introspection cases, all green
2. **Task 2: Create EnumCheckParityTest.php** — `79f2e44` (test) — 4 pg_constraint regex-extract parity cases, all green

_Plan metadata commit to follow in final docs commit._

## Files Created/Modified

- `tests/Feature/Fras/SchemaTest.php` (created, 104 lines) — information_schema + pg_indexes + pg_constraint regression suite tagged `pest()->group('fras')`
- `tests/Feature/Fras/EnumCheckParityTest.php` (created, 71 lines) — 4 enum-vs-CHECK parity cases with extractCheckValues() helper using pg_get_constraintdef() + regex

## Decisions Made

- **Regex shape agnosticism:** `extractCheckValues()` uses `/'([^']+)'/` to pull quoted string literals from whatever CHECK shape Postgres emits. The DDL for all 4 FRAS CHECK constraints lands as `CHECK ((column)::text = ANY (ARRAY['val1'::text, 'val2'::text]))` on Postgres 18.3, but the same helper works unchanged against the older `CHECK (column IN ('val1', 'val2'))` form if a future migration rewrites it. No preg-tokenization of the surrounding ANY/IN structure needed.
- **Sorted comparison over set comparison:** `collect()->sort()->values()->all()` on both sides catches missing AND extra values. Set equality via `array_diff` would need two-direction comparison. Sorted-array equality is one expression, symmetric, and fails loudly on either-side drift.
- **ILIKE not LIKE for index definitions:** PostGIS emits `USING gist` in some versions, `USING GIST` in others. `ILIKE '%gist%location%'` and `ILIKE '%gin%raw_payload%'` survive that variance without a regex. Acceptance criteria grep'd for both ILIKE patterns specifically to lock this in.
- **Feature-shaped placement for EnumCheckParityTest:** Plan considered Unit/Conventions placement but test does live DB introspection (pg_constraint reads). Feature/Fras/ placement wins: inherits RefreshDatabase + pgsql binding from `tests/Pest.php` automatically, colocates with the other fras-group tests, and matches Plan 05's test location for consistency.

## Deviations from Plan

None — plan executed exactly as written. All 11 acceptance criteria met on first run (7 SchemaTest cases + 4 EnumCheckParityTest cases), Pint clean on first pass, no Rule 1–4 triggers.

## Issues Encountered

None. One observation: the full test suite shows 56 baseline failures per the documented 17-02 distribution (41-59 failures from Family A `incident_categories_name_unique` and Family B `users_pkey` — pre-existing since Phase 17). The 16-test fras group is 100% green and unrelated to baseline noise.

## User Setup Required

None — schema-only plan, no external service configuration.

## Next Phase Readiness

- **Phase 18 complete.** All 6 plans shipped: schema (01-04), seeder + mandatory tests (05), optional regression tests (06).
- **FRAMEWORK-04, FRAMEWORK-05, FRAMEWORK-06 all satisfied** — schema shape, pgsql test runner, DB-layer idempotency.
- **Regression safety net in place for Phase 19–22:** any migration that silently drops a FRAS column, changes a column type, removes an index, or mutates a CHECK constraint without corresponding PHP enum update will fail CI at plan-execute time.
- **fras-group command (`./vendor/bin/pest --group=fras`) established as the fast-feedback gate** for any downstream FRAS-adjacent work.

---
*Phase: 18-fras-schema-port-to-postgresql*
*Completed: 2026-04-21*

## Self-Check: PASSED

- FOUND: tests/Feature/Fras/SchemaTest.php
- FOUND: tests/Feature/Fras/EnumCheckParityTest.php
- FOUND: ff46bbd (Task 1 commit)
- FOUND: 79f2e44 (Task 2 commit)
- fras-group: 16 tests pass (5 mandatory from Plan 05 + 7 SchemaTest + 4 EnumCheckParityTest), 42 assertions, 1.43s runtime
