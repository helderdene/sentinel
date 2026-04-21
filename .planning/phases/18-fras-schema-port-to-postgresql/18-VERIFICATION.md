---
phase: 18-fras-schema-port-to-postgresql
verified: 2026-04-21T10:15:00Z
status: passed
score: 16/16 must-haves verified
overrides_applied: 0
re_verification: null
gaps: []
deferred: []
human_verification: []
---

# Phase 18: FRAS Schema Port to PostgreSQL — Verification Report

**Phase Goal:** The four FRAS tables exist empty in IRMS's PostgreSQL database with types that match IRMS conventions (UUID PKs, JSONB, TIMESTAMPTZ, Magellan geography) and with the idempotency constraint recognition ingestion will rely on, so Phase 19 and Phase 20 can begin persisting data without schema churn.
**Verified:** 2026-04-21T10:15:00Z
**Status:** PASSED
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | cameras table exists with UUID PK, geography(POINT,4326) location, TIMESTAMPTZ(0) timestamps, and DB CHECK on status | VERIFIED | `db:table cameras` — 11 columns: uuid PK, geography(Point,4326) location, timestamptz(0) last_seen_at/decommissioned_at, `cameras_status_check` in pg_constraint |
| 2 | CameraStatus enum cases Online/Offline/Degraded map to lowercase strings matching DB CHECK clause | VERIFIED | `app/Enums/CameraStatus.php` — `Online='online'`, `Offline='offline'`, `Degraded='degraded'`; label() helper present |
| 3 | Camera model uses HasUuids + HasFactory + Magellan Point cast, scopeActive() excludes decommissioned rows | VERIFIED | `app/Models/Camera.php` — `use HasFactory, HasUuids`, `'location' => Point::class`, `'status' => CameraStatus::class`, `scopeActive(Builder $query)` returns `whereNull('decommissioned_at')` |
| 4 | CameraFactory emits Butuan-City-jittered Point via Point::makeGeodetic, status=Offline default, camera_id_display null | VERIFIED | `database/factories/CameraFactory.php` — `Point::makeGeodetic(8.9475 + jitter, 125.5406 + jitter)`, `'status' => CameraStatus::Offline`, `'camera_id_display' => null` |
| 5 | personnel table exists with UUID PK, full FRAS field set, category CHECK constraint, and forward-compat columns (expires_at, consent_basis, decommissioned_at) | VERIFIED | `db:table personnel` — 16 columns confirmed: uuid PK, all 14 FRAS domain fields, timestamptz(0) expires_at/decommissioned_at, text consent_basis; `personnel_category_check` in pg_constraint |
| 6 | PersonnelCategory enum cases Allow/Block/Missing/LostChild map to lowercase backed strings | VERIFIED | `app/Enums/PersonnelCategory.php` — `Allow='allow'`, `Block='block'`, `Missing='missing'`, `LostChild='lost_child'`; label() returns "Allow"/"BOLO (Block)"/"Missing Person"/"Lost Child" |
| 7 | Personnel model uses HasUuids + HasFactory + PersonnelCategory cast + scopeActive; explicit `$table = 'personnel'` | VERIFIED | `app/Models/Personnel.php` — `use HasFactory, HasUuids`, `protected $table = 'personnel'`, `'category' => PersonnelCategory::class`, `scopeActive()` with whereNull |
| 8 | PersonnelFactory emits valid rows with category=Allow default | VERIFIED | `database/factories/PersonnelFactory.php` — `protected $model = Personnel::class`, `'category' => PersonnelCategory::Allow`, faker fields, null custom_id |
| 9 | camera_enrollments pivot exists with UUID PK, FK cascades to cameras+personnel, UNIQUE(camera_id, personnel_id), status CHECK, and composite indexes | VERIFIED | `db:table camera_enrollments` — 9 columns; FKs cascade; `camera_enrollments_camera_id_personnel_id_unique` + `camera_enrollments_camera_id_status_index` + `camera_enrollments_personnel_id_status_index` in pg_indexes; `camera_enrollments_status_check` in pg_constraint |
| 10 | CameraEnrollmentStatus enum Pending/Syncing/Done/Failed; CameraEnrollment extends Model (not Pivot) with HasUuids + HasFactory + BelongsTo relations | VERIFIED | `app/Enums/CameraEnrollmentStatus.php` — 4 lowercase cases; `app/Models/CameraEnrollment.php` — `class CameraEnrollment extends Model`, `use HasFactory, HasUuids`, `'status' => CameraEnrollmentStatus::class`, `camera()` and `personnel()` BelongsTo present |
| 11 | recognition_events exists with UUID PK, camera_id RESTRICT, personnel_id/incident_id/acknowledged_by SET NULL, UNIQUE(camera_id, record_id), GIN on raw_payload (jsonb_path_ops), microsecond captured_at/received_at, decimal(5,2) similarity | VERIFIED | `db:table recognition_events` — 28 columns; `recognition_events_camera_id_record_id_unique` (btree, compound, unique); `recognition_events_raw_payload_gin_idx` (gin); captured_at/received_at as timestamp(6) with time zone; similarity as numeric(5,2); acknowledged_by as bigint FK; all 4 cascade behaviors confirmed |
| 12 | All 5 secondary indexes present on recognition_events: (camera_id, captured_at), (person_type, verify_status), (severity), (is_real_time, severity), (incident_id) | VERIFIED | `db:table recognition_events` index section confirms all 5 btree indexes present |
| 13 | RecognitionSeverity enum Info/Warning/Critical with isCritical() helper; RecognitionEvent model with jsonb array casts, severity enum cast, similarity cast as 'decimal:2', 4 BelongsTo relations | VERIFIED | `app/Enums/RecognitionSeverity.php` — `isCritical()` present; `app/Models/RecognitionEvent.php` — `'raw_payload' => 'array'`, `'severity' => RecognitionSeverity::class`, `'similarity' => 'decimal:2'`, camera()/personnel()/incident()/acknowledgedBy() relations present |
| 14 | RecognitionEventFactory emits RecPush-shape raw_payload with BOTH firmware spellings (personName AND persionName) and has 5 state methods: critical/warning/info/withPersonnel/blockMatch | VERIFIED | `database/factories/RecognitionEventFactory.php` — raw_payload contains `'personName'` and `'persionName'`; all 5 state methods (`critical()`, `warning()`, `info()`, `withPersonnel(Personnel)`, `blockMatch()`) present |
| 15 | CameraSpatialQueryTest and RecognitionEventIdempotencyTest both tagged pest()->group('fras') run green against PostgreSQL; ST_DWithin tested; UniqueConstraintViolationException asserted | VERIFIED | Both test files present with correct group tag; `php artisan test --compact --group=fras` — 16 passed, 42 assertions, 1.11s |
| 16 | FrasPlaceholderSeeder exists with empty run() body; NOT wired into DatabaseSeeder; FRAMEWORK-05: .env.testing has DB_CONNECTION=pgsql | VERIFIED | `database/seeders/FrasPlaceholderSeeder.php` — empty run() with no-op comment; `git diff DatabaseSeeder.php` empty; `.env.testing` confirmed `DB_CONNECTION=pgsql` |

**Score:** 16/16 truths verified

---

## Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `database/migrations/2026_04_21_000001_create_cameras_table.php` | cameras DDL with spatialIndex + CHECK | VERIFIED | 43 lines; uuid PK, geography(POINT,4326), timestampTz(0) columns, spatialIndex inside closure, DB::statement CHECK after Schema::create |
| `app/Enums/CameraStatus.php` | 3-case string-backed enum | VERIFIED | 22 lines; Online/Offline/Degraded lowercase; label() helper |
| `app/Models/Camera.php` | HasUuids + Point cast + scopeActive | VERIFIED | 59 lines; use HasFactory, HasUuids; casts Point/CameraStatus/datetime; scopeActive(Builder) |
| `database/factories/CameraFactory.php` | Factory with Magellan Point | VERIFIED | 36 lines; Point::makeGeodetic jitter around Butuan City; status=Offline |
| `database/migrations/2026_04_21_000002_create_personnel_table.php` | personnel DDL with CHECK | VERIFIED | 47 lines; 14 domain columns + timestamps; DB::statement personnel_category_check |
| `app/Enums/PersonnelCategory.php` | 4-case string-backed enum | VERIFIED | 24 lines; Allow/Block/Missing/LostChild lowercase; label() helper |
| `app/Models/Personnel.php` | HasUuids + explicit $table + scopeActive | VERIFIED | 66 lines; protected $table = 'personnel'; PersonnelCategory cast; scopeActive |
| `database/factories/PersonnelFactory.php` | Factory with PersonnelCategory::Allow | VERIFIED | 36 lines; faker name/date/phone/address; category=Allow; null custom_id |
| `database/migrations/2026_04_21_000003_create_camera_enrollments_table.php` | Pivot DDL with CASCADE FKs + UNIQUE + indexes + CHECK | VERIFIED | 43 lines; foreignUuid cascade; unique+index calls; DB::statement CHECK |
| `app/Enums/CameraEnrollmentStatus.php` | 4-case string-backed enum | VERIFIED | 24 lines; Pending/Syncing/Done/Failed lowercase; label() helper |
| `app/Models/CameraEnrollment.php` | extends Model (not Pivot) + HasUuids + BelongsTo | VERIFIED | 59 lines; extends Model; use HasFactory, HasUuids; camera()/personnel() BelongsTo |
| `database/factories/CameraEnrollmentFactory.php` | Factory with Camera::factory() + Personnel::factory() | VERIFIED | Sub-factory FK wiring confirmed; CameraEnrollmentStatus::Pending default |
| `database/migrations/2026_04_21_000004_create_recognition_events_table.php` | 28-column table with UNIQUE + GIN + CHECK + 5 secondary indexes | VERIFIED | 80 lines; decimal(5,2) similarity; foreignId acknowledged_by; timestampTz(6) microsecond; unique+GIN+5 secondary indexes; two DB::statements |
| `app/Enums/RecognitionSeverity.php` | 3-case string-backed enum with isCritical() | VERIFIED | 30 lines; Info/Warning/Critical lowercase; label() + isCritical() helpers |
| `app/Models/RecognitionEvent.php` | HasUuids + 13 casts + 4 BelongsTo | VERIFIED | 105 lines; extends Model; HasFactory,HasUuids; 13-entry casts array; camera/personnel/incident/acknowledgedBy relations |
| `database/factories/RecognitionEventFactory.php` | RecPush-shape payload + 5 state methods | VERIFIED | 108 lines; both personName+persionName in raw_payload; critical/warning/info/withPersonnel/blockMatch state methods |
| `database/seeders/FrasPlaceholderSeeder.php` | Empty-body Seeder; NOT in DatabaseSeeder | VERIFIED | 20 lines; run() is no-op comment only; DatabaseSeeder unchanged |
| `tests/Feature/Fras/CameraSpatialQueryTest.php` | 2 tests with pest()->group('fras') + ST_DWithin | VERIFIED | 55 lines; pest()->group('fras'); ST_DWithin near/far camera assertions |
| `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` | 3 tests with UniqueConstraintViolationException | VERIFIED | 41 lines; pest()->group('fras'); toThrow(UniqueConstraintViolationException::class) |
| `tests/Feature/Fras/SchemaTest.php` | 7 information_schema regression tests | VERIFIED | 104 lines; pest()->group('fras'); ILIKE gist/gin index checks; FRAMEWORK-06 UNIQUE check |
| `tests/Feature/Fras/EnumCheckParityTest.php` | 4 PHP enum ↔ DB CHECK parity tests | VERIFIED | 71 lines; pest()->group('fras'); extractCheckValues() pg_constraint helper; all 4 enums covered |

---

## Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `Camera.php` | `CameraStatus.php` | cast `'status' => CameraStatus::class` | WIRED | Cast array confirmed in model |
| `CameraFactory.php` | `Camera.php` | `protected $model = Camera::class` | WIRED | Factory $model property confirmed |
| `cameras migration` | DB CHECK constraint | `ALTER TABLE cameras ADD CONSTRAINT cameras_status_check` | WIRED | `cameras_status_check` confirmed in pg_constraint via tinker |
| `cameras migration` | GIST spatial index | `$table->spatialIndex('location')` | WIRED | `cameras_location_spatialindex (gist)` confirmed in pg_indexes |
| `Personnel.php` | `PersonnelCategory.php` | cast `'category' => PersonnelCategory::class` | WIRED | Cast array confirmed in model |
| `personnel migration` | DB CHECK constraint | `ALTER TABLE personnel ADD CONSTRAINT personnel_category_check` | WIRED | `personnel_category_check` confirmed in pg_constraint via EnumCheckParityTest |
| `camera_enrollments migration` | cameras + personnel FK | `foreignUuid('camera_id')->constrained()->cascadeOnDelete()` + explicit `constrained('personnel')` | WIRED | Both FK constraints with cascade confirmed in db:table output |
| `camera_enrollments migration` | DB CHECK + UNIQUE + indexes | DB::statement + `$table->unique/index` calls | WIRED | All 3 structural constraints confirmed in pg_indexes; status CHECK confirmed in EnumCheckParityTest |
| `recognition_events migration` | UNIQUE(camera_id, record_id) — FRAMEWORK-06 | `$table->unique(['camera_id', 'record_id'])` | WIRED | `recognition_events_camera_id_record_id_unique` confirmed in db:table + SchemaTest |
| `recognition_events migration` | GIN index on raw_payload | `DB::statement('CREATE INDEX ... USING GIN (raw_payload jsonb_path_ops)')` | WIRED | `recognition_events_raw_payload_gin_idx (gin)` with jsonb_path_ops confirmed in SchemaTest |
| `recognition_events migration` | camera_id RESTRICT FK | `foreignUuid('camera_id')->constrained()->restrictOnDelete()` | WIRED | ON DELETE RESTRICT confirmed in db:table |
| `recognition_events migration` | acknowledged_by bigint FK → users | `foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete()` | WIRED | acknowledged_by as bigint (not uuid) confirmed in db:table; SET NULL on delete confirmed |
| `CameraSpatialQueryTest.php` | cameras.location spatial column | `DB::select` with `ST_DWithin` | WIRED | Test passes green; ST_DWithin returns correct near/far results |
| `RecognitionEventIdempotencyTest.php` | recognition_events UNIQUE(camera_id, record_id) | `expect->toThrow(UniqueConstraintViolationException::class)` | WIRED | Test passes green; duplicate insert rejected at DB layer |

---

## Data-Flow Trace (Level 4)

Not applicable — Phase 18 is a schema-only phase with no controllers, no routes, and no data rendering components. All artifacts are migrations, models, factories, seeders, and schema-regression tests. No dynamic rendering paths exist to trace.

---

## Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| All 16 FRAS-group tests pass on pgsql | `php artisan test --compact --group=fras` | 16 passed, 42 assertions, 1.11s | PASS |
| cameras_status_check constraint exists in pg_constraint | tinker + DB::select on pg_constraint | `cameras_status_check` returned | PASS |
| recognition_events_severity_check + UNIQUE exist | tinker + DB::select on pg_constraint | Both constraints returned | PASS |
| FRAMEWORK-05: DB_CONNECTION=pgsql in .env.testing | `grep '^DB_CONNECTION=pgsql' .env.testing` | Match confirmed | PASS |
| DatabaseSeeder not modified | `git diff database/seeders/DatabaseSeeder.php` | 0 lines diff | PASS |
| cameras table has geography(Point,4326) location with GIST index | `php artisan db:table cameras` | `cameras_location_spatialindex (gist)` confirmed | PASS |
| recognition_events.similarity is numeric(5,2) | `php artisan db:table recognition_events` | `numeric(5,2)` confirmed | PASS |
| recognition_events.acknowledged_by is bigint FK | `php artisan db:table recognition_events` | `bigint` + FK to users confirmed | PASS |
| captured_at/received_at are timestamp(6) with time zone | `php artisan db:table recognition_events` | `timestamp(6) with time zone` confirmed | PASS |

---

## Requirements Coverage

| Requirement | Source Plan(s) | Description | Status | Evidence |
|-------------|----------------|-------------|--------|----------|
| FRAMEWORK-04 | 18-01, 18-02, 18-03, 18-04, 18-05, 18-06 | IRMS Postgres schema gains empty cameras, personnel, camera_enrollments, recognition_events tables with UUID PKs, JSONB+GIN, TIMESTAMPTZ, Magellan geography(POINT,4326) | SATISFIED | All 4 tables verified in live DB with correct types; 16 FRAS tests green; SchemaTest introspects information_schema confirming each type |
| FRAMEWORK-05 | 18-05 | Pest test suite runs on PostgreSQL for FRAS groups so JSONB + geography behavior is exercised | SATISFIED | `.env.testing` has `DB_CONNECTION=pgsql`; `pest --group=fras` runs 16 tests against pgsql in 1.11s; no SQLite in-memory fallback |
| FRAMEWORK-06 | 18-04, 18-05 | recognition_events has (camera_id, record_id) unique constraint for idempotency against MQTT redelivery | SATISFIED | `recognition_events_camera_id_record_id_unique` (btree, compound, unique) confirmed in db:table; RecognitionEventIdempotencyTest proves UniqueConstraintViolationException thrown on duplicate insert |

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| None | — | — | — | — |

No TODO/FIXME/placeholder comments found in any Phase 18 production files. No stub return null or empty implementations in models, enums, or factories. FrasPlaceholderSeeder's empty run() is intentional by design (D-62), not a stub — the file-presence satisfies SC4 and the no-op comment documents the intent explicitly.

---

## Human Verification Required

None. All Phase 18 deliverables are schema and test artifacts verifiable programmatically. There are no UI components, no real-time behaviors, and no external service integrations in this phase's scope.

---

## Gaps Summary

No gaps found. All 16 must-haves verified. All 3 requirements (FRAMEWORK-04, FRAMEWORK-05, FRAMEWORK-06) satisfied with evidence from both code inspection and live database introspection. The 16-test FRAS group passes green in 1.11s on pgsql.

---

_Verified: 2026-04-21T10:15:00Z_
_Verifier: Claude (gsd-verifier)_
