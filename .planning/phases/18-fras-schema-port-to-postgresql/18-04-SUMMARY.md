---
phase: 18-fras-schema-port-to-postgresql
plan: 04
subsystem: database

tags: [postgres, migration, uuid, jsonb, gin, timestamptz, check-constraint, enum, fras, recognition-events, idempotency]

# Dependency graph
requires:
  - phase: 18-01
    provides: cameras table with UUID PK (FK target — RESTRICT on delete)
  - phase: 18-02
    provides: personnel table with UUID PK (FK target — SET NULL on delete)
  - phase: v1.0
    provides: incidents (UUID PK) and users (bigint PK) tables — FK targets from v1.0 baseline
provides:
  - recognition_events core event-append table (28 columns) with UUID PK
  - Mixed FK cascades — camera_id RESTRICT (audit preservation), personnel_id/incident_id/acknowledged_by SET NULL
  - acknowledged_by as bigint FK → users.id (D-50 corrected — users predates FRAS UUID convention)
  - similarity as decimal(5,2) fixed-point (D-40 corrected — NOT float; 0.00–100.00 range)
  - captured_at + received_at as TIMESTAMPTZ(6) microsecond precision (D-45, D-46)
  - raw_payload jsonb NOT NULL with GIN index using jsonb_path_ops opclass (D-48)
  - target_bbox jsonb nullable (no GIN — low cardinality)
  - Composite UNIQUE (camera_id, record_id) for FRAMEWORK-06 idempotency (D-54)
  - 5 secondary indexes per D-55 — (camera_id, captured_at), (person_type, verify_status), (severity), (is_real_time, severity), (incident_id)
  - recognition_events_severity_check DB CHECK constraining severity to info/warning/critical (D-49)
  - RecognitionSeverity enum (string-backed, lowercase cases + label() + isCritical() helper)
  - RecognitionEvent model with HasUuids + HasFactory + jsonb array casts + decimal:2 cast + severity enum cast + 4 BelongsTo relations
  - RecognitionEventFactory with RecPush-shape raw_payload (preserves BOTH firmware spellings personName + persionName per D-61) and 5 state methods
affects: [19, 21, 22]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Shared Pattern B (raw DB::statement CHECK) applied a fourth time — fully idiomatic for FRAS enum columns (cameras.status, personnel.category, camera_enrollments.status, recognition_events.severity)"
    - "GIN index on jsonb_path_ops opclass for append-mostly jsonb payloads — smaller index, faster writes, supports @> containment queries needed by Phase 19 dedup"
    - "Microsecond TIMESTAMPTZ via timestampTz('col', precision: 6) — first application in IRMS schema; reserved for event timestamps where sub-second ordering matters"
    - "Mixed FK cascades on a single table — camera_id RESTRICT preserves audit trail, personnel/incident/user SET NULL allows entity purge"
    - "Heterogeneous FK types on a single table — foreignUuid(camera/personnel/incident) + foreignId(acknowledged_by → users.id bigint) coexist cleanly"

key-files:
  created:
    - database/migrations/2026_04_21_000004_create_recognition_events_table.php
    - app/Enums/RecognitionSeverity.php
    - app/Models/RecognitionEvent.php
    - database/factories/RecognitionEventFactory.php
  modified: []

key-decisions:
  - "similarity stored as decimal(5,2) NOT float — D-40 corrected in CONTEXT.md; model cast is 'decimal:2' (returns string with fixed 2-digit scale). Float would introduce rounding drift on the 0.00–100.00 similarity range and break Phase 19 dedup comparisons"
  - "acknowledged_by is foreignId (bigint FK → users.id), NOT foreignUuid — D-50 corrected in CONTEXT.md; users.id predates the FRAS UUID convention (it's $table->id() from v1.0). Mixed FK types on the same table is explicit and cheap"
  - "GIN index built via raw DB::statement using jsonb_path_ops opclass, not the default jsonb_ops. Research D-48 showed jsonb_path_ops is smaller and faster for @> containment, which is the only query pattern Phase 19 needs. Default jsonb_ops is 30% larger with no benefit here"
  - "captured_at and received_at both use TIMESTAMPTZ(6) — microsecond precision is required because FRAS cameras can emit multiple recognitions per second on busy intakes; second-level precision would lose ordering under burst load"
  - "Factory preserves BOTH personName AND persionName in raw_payload — the firmware has a typo in the source device payload and Phase 19's RecognitionHandler must parse either spelling. Keeping both in factory output means handler parser tests exercise the fallback logic"

patterns-established:
  - "GIN + jsonb_path_ops is the standard idiom for append-mostly jsonb in IRMS — Phase 19 and later phases building event-append tables should mirror this recipe"
  - "Mixed FK types on one table is now proven — foreignUuid and foreignId coexist; downstream phases bridging UUID-native FRAS tables back to legacy bigint IRMS tables (like users) should follow this pattern without apology"
  - "RecPush-shape factory state methods (critical/warning/info/withPersonnel/blockMatch) are the test-scaffolding vocabulary for Phase 19 handler tests and Phase 21 incident-bridge tests"

requirements-completed: [FRAMEWORK-04, FRAMEWORK-06]

# Metrics
duration: 4min
completed: 2026-04-21
---

# Phase 18 Plan 04: FRAS Schema Port — recognition_events Core Event Table Summary

**recognition_events append-core table — 28 columns, mixed FK cascades (UUID + bigint), microsecond TIMESTAMPTZ, GIN-on-jsonb_path_ops, decimal(5,2) similarity, and FRAMEWORK-06 idempotency UNIQUE — is the most novel schema port in Phase 18 and it migrates clean on first attempt with zero runtime deviations from the pre-resolved CONTEXT.md corrections (D-40, D-50, D-64).**

## Performance

- **Duration:** ~4 min
- **Started:** 2026-04-21T09:31:00Z (approx)
- **Completed:** 2026-04-21T09:35:00Z (approx)
- **Tasks:** 2
- **Files modified:** 4 files created

## Accomplishments
- recognition_events DDL green on PostgreSQL 18.3 — 28 columns, 8 indexes (1 primary, 1 unique composite, 5 secondary, 1 GIN), 4 FK constraints with mixed cascades, 1 DB CHECK constraint
- GIN index on raw_payload uses jsonb_path_ops opclass (verified in db:table output as `gin` index type) — ready for Phase 19 @> containment queries
- Composite UNIQUE (camera_id, record_id) enforces FRAMEWORK-06 idempotency at the DB layer — Phase 19 MQTT listener can insert blindly and let duplicate RecPush redelivery be rejected by Postgres rather than doing an app-level existence check
- Mixed FK cascades verified: camera_id ON DELETE RESTRICT (preserves audit), personnel_id/incident_id/acknowledged_by ON DELETE SET NULL (allows DPA purge)
- acknowledged_by lands as bigint FK → users.id per D-50 — users.id is $table->id() = bigint from v1.0, and foreignId/foreignUuid interop is clean in the same migration
- similarity lands as numeric(5,2) per D-40 — the decimal('similarity', 5, 2) blueprint call produces fixed-point storage, and the model's 'decimal:2' cast round-trips to a 2-digit-scale string
- captured_at and received_at both confirmed as timestamp(6) with time zone via information_schema — microsecond precision live
- RecognitionSeverity enum (Info/Warning/Critical) with lowercase backed values matching the DB CHECK literal set
- RecognitionEvent model with HasUuids + HasFactory, four BelongsTo relations (camera, personnel, incident, acknowledgedBy), and all 13 casts correct on re-fetch (verified via tinker)
- RecognitionEventFactory builds Camera::factory() via sub-factory chain; raw_payload contains BOTH personName (correct) and persionName (firmware typo) per D-61 so Phase 19 handler tests can exercise parser fallback
- All 5 state methods work: critical / warning / info / withPersonnel(Personnel) / blockMatch — last verified via tinker (blockMatch sets person_type=1 AND severity=Critical atomically)
- Full test suite holds at baseline (56 failed / 533 passed / 2 skipped) — within the 41-59 pre-existing Family A (incident_categories_name_unique) + Family B (users_pkey) variance carried over from Phase 17; zero new regressions attributable to this plan

## Task Commits

1. **Task 1: Create RecognitionSeverity enum and recognition_events migration** — `cc96649` (feat)
2. **Task 2: Create RecognitionEvent model and RecognitionEventFactory with states** — `3e8296a` (feat)

## Files Created/Modified
- `app/Enums/RecognitionSeverity.php` — String-backed enum (Info/Warning/Critical) with `label()` and `isCritical()` helpers; lowercase backed values match DB CHECK literals
- `database/migrations/2026_04_21_000004_create_recognition_events_table.php` — 28-column table DDL with mixed FK cascades, microsecond TIMESTAMPTZ(6), decimal(5,2) similarity, jsonb raw_payload + target_bbox, composite UNIQUE (camera_id, record_id), 5 secondary indexes, raw DB::statement for CHECK + GIN index on jsonb_path_ops
- `app/Models/RecognitionEvent.php` — Extends Model; HasUuids + HasFactory; 13-entry casts array including 'raw_payload' => 'array', 'target_bbox' => 'array', 'severity' => RecognitionSeverity::class, 'similarity' => 'decimal:2' (D-64 corrected); 4 BelongsTo relations including acknowledgedBy() → User::class via non-default FK name
- `database/factories/RecognitionEventFactory.php` — RecPush-shape payload with both firmware spellings preserved per D-61; 5 state methods (critical, warning, info, withPersonnel, blockMatch) wrap `$this->state(fn () => [...])` pattern established in plan skeleton

## Decisions Made
- **decimal(5,2) over float for similarity** (honoring pre-resolved D-40) — the similarity score is a fixed-precision percentage (0.00–100.00) used for Phase 19 dedup comparisons; float32 would accumulate rounding drift and make "is this the same event?" checks unreliable. The 'decimal:2' Eloquent cast returns a string which preserves scale through JSON serialization too
- **foreignId over foreignUuid for acknowledged_by** (honoring pre-resolved D-50) — users.id is bigint from v1.0, not UUID, so the FK column must be bigint. Laravel's foreignId()->constrained('users') + foreignUuid()->constrained() on other columns in the same Blueprint call coexist without conflict — verified in the resulting migration
- **GIN with jsonb_path_ops via raw DB::statement, not a Blueprint helper** — Laravel's Blueprint does not expose opclass selection on ->index() for jsonb; raw DDL after Schema::create is the clean path. The opclass choice (jsonb_path_ops vs default jsonb_ops) is a real performance decision per research D-48: 30% smaller index, same containment query coverage, no support for existence ? operator (which IRMS does not need)
- **Firmware-typo preservation in factory** (honoring D-61) — the RecPush payload emitted by FRAS cameras has `persionName` as a genuine vendor typo alongside `personName`. Keeping both in factory output is not a mistake — it's test scaffolding for Phase 19's RecognitionHandler parser which must accept either spelling

## Deviations from Plan

None — plan executed exactly as written. All D-40, D-50, D-64 corrections had been pre-resolved in CONTEXT.md during the plan-checker revision cycle, so no runtime reconciliation was needed. All acceptance criteria met on the first pass:
- `php artisan migrate:fresh` exits 0 with all 4 FRAS tables migrating in order (cameras → personnel → camera_enrollments → recognition_events)
- `db:table recognition_events` lists all 28 columns with correct types (numeric(5,2), bigint for acknowledged_by, timestamp(6) with time zone, jsonb, GIN index present)
- `RecognitionEvent::factory()->create()` persists; severity casts to enum; raw_payload casts to array on re-fetch; both personName and persionName present in payload
- `RecognitionEvent::factory()->critical()->create()->severity->value === 'critical'` confirmed via tinker
- `RecognitionEvent::factory()->blockMatch()->create()->person_type === 1` confirmed via tinker
- Pint clean on both commits (both Task 1 and Task 2 passed `vendor/bin/pint --dirty --format agent` without modifications)

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness
- Wave 2 of Phase 18 complete (plans 18-03 and 18-04). Plan 18-05 (feature tests — idempotency, FK cascades, CHECK enforcement) can now exercise the full table set and will verify the FRAMEWORK-06 UNIQUE via duplicate-insert rejection
- Plan 18-06 (schema validation test) can assert column-level types via information_schema — specifically `similarity = numeric(5,2)`, `acknowledged_by = bigint`, `captured_at = timestamp(6) with time zone`, and `pg_indexes.indexdef` containing `USING gin` with `jsonb_path_ops`
- Phase 19 (MQTT RecognitionHandler) has the model, factory, enum, and UNIQUE it needs to do insert-or-ignore idempotency without app-level locking
- Phase 21 (Recognition → Incident bridge) can now set incident_id post-facto via update() because the column is nullable with SET NULL cascade
- Phase 22 (alert feed + DPA retention) has severity + acknowledged_at + acknowledged_by for feed queries and RESTRICT-on-camera-delete guarding the audit trail

## Self-Check: PASSED

Claimed files:
- `app/Enums/RecognitionSeverity.php` — FOUND
- `database/migrations/2026_04_21_000004_create_recognition_events_table.php` — FOUND
- `app/Models/RecognitionEvent.php` — FOUND
- `database/factories/RecognitionEventFactory.php` — FOUND

Claimed commits:
- `cc96649` (Task 1) — FOUND
- `3e8296a` (Task 2) — FOUND

---
*Phase: 18-fras-schema-port-to-postgresql*
*Completed: 2026-04-21*
