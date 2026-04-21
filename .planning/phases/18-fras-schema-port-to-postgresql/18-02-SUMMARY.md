---
phase: 18-fras-schema-port-to-postgresql
plan: 02
subsystem: database
tags: [postgresql, uuid, check-constraint, eloquent, factory, enum, personnel, bolo, dpa]

# Dependency graph
requires:
  - phase: 17-laravel-12-13-upgrade
    provides: Laravel 13 baseline with Pest/Pint/Tinker green
  - plan: 18-01
    provides: Shared Pattern B (raw DB::statement CHECK after Schema::create) + lowercase-enum convention
provides:
  - personnel table in Postgres (UUID PK, full FRAS field set name/gender/birthday/id_card/phone/address/photo_*, category VARCHAR(20) DEFAULT 'allow' with DB-level CHECK)
  - forward-compat reservations: expires_at (Phase 20 auto-unenroll), consent_basis (Phase 22 DPA), decommissioned_at (soft-decommission)
  - PersonnelCategory PHP enum (Allow/Block/Missing/LostChild — lowercase values matching DB CHECK, with label() helper)
  - Personnel Eloquent model (HasUuids + HasFactory + PersonnelCategory enum cast + scopeActive)
  - PersonnelFactory emitting valid rows with category=Allow default
affects: [18-03, 18-04, 18-06, 19-mqtt, 20-personnel-admin, 21-recognition-bridge, 22-alerts-dpa]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Second application of Shared Pattern B (DB CHECK via raw DDL) — first established in Plan 18-01 for cameras.status; repeated here for personnel.category without drift"
    - "Explicit protected $table = 'personnel' guard against Laravel pluralizer ambiguity (personnel is already plural — safer to be explicit than trust inflection)"
    - "Forward-compat column reservations in a single migration instead of future ALTER TABLE — Phase 20 (auto-unenroll) and Phase 22 (DPA consent_basis) land zero-migration"

key-files:
  created:
    - app/Enums/PersonnelCategory.php
    - database/migrations/2026_04_21_000002_create_personnel_table.php
    - app/Models/Personnel.php
    - database/factories/PersonnelFactory.php
  modified: []

key-decisions:
  - "Set protected \\$table = 'personnel' explicitly on the Personnel model — Laravel's pluralizer can drop to 'personnel' naturally but behavior has shifted across versions; per plan guidance erred on the explicit side so future Laravel inflection changes do not silently repoint to 'personnels' or similar"
  - "Factory emits gender as randomElement([0, 1, null]) per D-61 — covers the M/F/unknown trichotomy without presuming a gender-coding schema (Phase 20 UI will map 0/1/null to labels)"
  - "Factory leaves custom_id null — same rationale as CameraFactory.camera_id_display in Plan 18-01: Phase 20 admin controllers will assign human-readable IDs; parallel test runs cannot safely sequence without races"
  - "Personnel model omits relations (camera_enrollments HasMany, recognition_events HasMany) per D-66 — deferred to Plan 18-03/04 when target tables exist; prevents referencing undefined models"

patterns-established:
  - "Shared Pattern B (DB CHECK via raw DDL) — second use confirms idiom is stable: Schema::create closure returns → DB::statement('ALTER TABLE … ADD CONSTRAINT {table}_{column}_check CHECK ({column} IN (…))') → down() relies on Schema::dropIfExists to drop CHECK transitively. Will be repeated in Plans 18-03 (camera_enrollments.status) + 18-04 (recognition_events.severity)"
  - "FRAS lowercase-enum + DB-CHECK round-trip pipeline: Enum case values match DB CHECK literal tokens (PersonnelCategory::LostChild->value === 'lost_child' === CHECK literal), Eloquent cast rehydrates string to enum, invalid writes via raw DB rejected at DB layer — all three layers verified in tinker"

requirements-completed: [FRAMEWORK-04]

# Metrics
duration: 9min
completed: 2026-04-21
---

# Phase 18 Plan 02: FRAS personnel table port Summary

**personnel table landed in Postgres with UUID PK, full FRAS field set, VARCHAR(20) category column backed by a DB-level CHECK constraint on the four PersonnelCategory cases (allow/block/missing/lost_child), and forward-compat expires_at/consent_basis/decommissioned_at reservations — plus PersonnelCategory enum with label() helper, Personnel Eloquent model with explicit $table guard and scopeActive scope, and PersonnelFactory emitting realistic rows with category=Allow default.**

## Performance

- **Duration:** ~9min
- **Started:** 2026-04-21T09:14:32Z
- **Completed:** 2026-04-21T09:23:38Z
- **Tasks:** 2
- **Files created:** 4

## Accomplishments

- `personnel` table exists in Postgres with 16 columns (14 domain + created_at/updated_at per D-13..D-20): uuid PK, custom_id UNIQUE NULLABLE (48), name (100) NOT NULL, smallint gender NULLABLE, date birthday, varchar(32) id_card/phone, varchar(255) address/photo_path, varchar(32) photo_hash, varchar(20) category DEFAULT 'allow', timestamptz(0) expires_at/decommissioned_at NULLABLE, text consent_basis NULLABLE, plain timestamps()
- DB-level `personnel_category_check` CHECK constraint present — verified by raw-DB insert of `category = 'invalid_category'` which throws `QueryException` containing the constraint name (mitigates T-18-01 tampering at the DB layer per threat_model)
- `PersonnelCategory` enum with 4 lowercase-backed cases (`Allow`, `Block`, `Missing`, `LostChild` → `allow`, `block`, `missing`, `lost_child`) and `label()` helper returning `"Allow"`, `"BOLO (Block)"`, `"Missing Person"`, `"Lost Child"`
- `Personnel` model hydrates `PersonnelCategory` enum, `gender` as int, `birthday` as date, `expires_at`/`decommissioned_at` as Carbon datetime on refetch; `scopeActive()` excludes decommissioned rows (verified: 1 active row post-factory create)
- `Personnel::factory()->create()` emits a valid UUIDv7-keyed row with `category=PersonnelCategory::Allow` (verified via `php artisan tinker`) — hydrated instance passes `$p->category instanceof PersonnelCategory`

## Task Commits

Each task was committed atomically:

1. **Task 1: PersonnelCategory enum + personnel migration** — `f80538e` (feat)
2. **Task 2: Personnel model + PersonnelFactory** — `6598f4d` (feat)

## Files Created

- `app/Enums/PersonnelCategory.php` (23 lines) — string-backed enum with 4 lowercase cases and `label()` helper returning human-readable names
- `database/migrations/2026_04_21_000002_create_personnel_table.php` (47 lines) — Schema::create + raw DB::statement CHECK constraint (personnel_category_check); down() uses Schema::dropIfExists (drops CHECK transitively)
- `app/Models/Personnel.php` (67 lines) — HasUuids + HasFactory, explicit `$table = 'personnel'`, fillable list of 13 columns, casts for category/gender/birthday/expires_at/decommissioned_at, scopeActive(Builder) with PHPDoc generics
- `database/factories/PersonnelFactory.php` (36 lines) — `$model = Personnel::class`, faker name/date/phone/address, gender randomElement([0,1,null]), category=PersonnelCategory::Allow default, null custom_id/photo_*

## Decisions Made

None beyond the four judgment calls logged in `key-decisions` frontmatter (explicit $table name, gender trichotomy via randomElement, null custom_id deferred to Phase 20, no relations per D-66). Plan 18-02 executed exactly as written.

## Deviations from Plan

None — plan executed exactly as written. Plan acceptance text says `db:table personnel lists all 17 columns` but lists only 16 distinct names (id, custom_id, name, gender, birthday, id_card, phone, address, photo_path, photo_hash, category, expires_at, consent_basis, decommissioned_at, created_at, updated_at); the DB reports 16, matching the enumerated names. Treated as a plan text typo (16 vs 17) — implementation satisfies the enumerated list verbatim.

## Issues Encountered

None.

## Verification Summary

- `php artisan migrate:fresh` exits 0 — all 27 migrations run green, personnel table created in 2.89ms alongside cameras from Plan 18-01
- `php artisan db:table personnel` confirms all 16 columns with correct Postgres types (uuid, varchar(48/100/32/255/20), smallint, date, text, timestamp(0) with time zone for expires_at/decommissioned_at, plain timestamp for created_at/updated_at)
- DB CHECK verification: `DB::table('personnel')->insert([… 'category' => 'invalid_category'])` throws `QueryException` whose message contains `personnel_category_check` — confirmed programmatically in tinker
- Enum round-trip: `PersonnelCategory::Allow->value === 'allow'`, `PersonnelCategory::LostChild->label() === 'Lost Child'` — verified
- Factory round-trip: `Personnel::factory()->create()->category->value === 'allow'` and `->category instanceof PersonnelCategory === true` — both verified in single tinker session
- `Personnel::active()->count()` returns 1 (the factory-created row) — scopeActive verified
- `vendor/bin/pint --dirty --format agent` returns `{"result":"pass"}` after both Task 1 and Task 2 file groups (formatting clean on first pass)
- `php artisan test --compact` — 542 passed, 47 failed, 2 skipped. All 47 failures confined to baseline Family A (`incident_categories_name_unique` UniqueConstraintViolationException) + Family B (`users_pkey` cascades into Intake/Dispatch/Broadcasting/Analytics/Navigation/RealTime tests). Zero failures contain `personnel`, `Personnel`, or `PersonnelCategory`. Failure count 47 falls within documented Phase 17 baseline distribution (41-59 from 5-run spread per STATE.md line 131). No regressions introduced by Plan 18-02 per family-classification gate.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Plan 18-03 (camera_enrollments join table) unblocked: `personnel.id` uuid PK is now live → `foreignUuid('personnel_id')->constrained()` will resolve alongside existing `foreignUuid('camera_id')`
- Plan 18-04 (recognition_events) unblocked on the `matched_personnel_id` FK target (nullable — unknown faces); will use the same Shared Pattern B for its own severity CHECK
- Plan 18-06 (convention tests): Personnel model + DB CHECK now exist → convention test suite can assert lowercase-backed enum values match CHECK literals via `array_column(PersonnelCategory::cases(), 'value')` cross-referencing the `pg_constraint.consrc` string
- FRAMEWORK-04 now 2/4 satisfied (cameras + personnel); camera_enrollments + recognition_events complete the requirement in Plans 18-03..04
- Phase 20 (Personnel admin) unblocked on schema: expires_at already reserved → auto-unenroll scheduler can write without migration; consent_basis already reserved → Phase 22 DPA flows land zero-migration
- Pattern confirmation: Shared Pattern B has now executed twice cleanly (cameras.status, personnel.category). Plans 18-03 and 18-04 can reuse with confidence.

## Self-Check: PASSED

- `app/Enums/PersonnelCategory.php` — FOUND
- `database/migrations/2026_04_21_000002_create_personnel_table.php` — FOUND
- `app/Models/Personnel.php` — FOUND
- `database/factories/PersonnelFactory.php` — FOUND
- Commit `f80538e` — FOUND (Task 1)
- Commit `6598f4d` — FOUND (Task 2)

---
*Phase: 18-fras-schema-port-to-postgresql*
*Completed: 2026-04-21*
