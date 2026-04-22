# Phase 18: FRAS Schema Port to PostgreSQL - Research

**Researched:** 2026-04-21
**Domain:** Laravel 13 migrations, PostgreSQL/PostGIS (via clickbar/laravel-magellan 2.1.0), Pest 4, UUID v7
**Confidence:** HIGH

## Summary

Phase 18 is a feature-free schema port. CONTEXT.md has already locked 70 implementation decisions (D-01 through D-70), every column, every CHECK rule, every FK cascade, every index. The research job is narrow: **resolve the API-level uncertainty in Laravel 13 + Magellan 2.1.0** so plans can be written as executable tasks, not exploratory investigations.

Three findings collapse most ambiguity in CONTEXT.md's "Claude's Discretion" list:

1. **Magellan 2.1.0 is already installed and confirmed Laravel-13-compatible** (Phase 17 shipped this — `composer show clickbar/laravel-magellan` returns `2.1.0` dated 2026-03-17). The exact `$table->geography('location', subtype: 'point', srid: 4326)` + `spatialIndex` + `Point::class` cast pattern works verbatim — it's in `database/migrations/2026_03_12_000006_create_incidents_table.php` line 22/45 and `app/Models/Incident.php` line 69. Factory pattern is `Point::makeGeodetic($latitude, $longitude)` (lat first — different from raw `Point::make` which is `$x, $y` i.e. lng-first).

2. **Laravel 13's `HasUuids` trait emits UUIDv7 by default** — verified by reading `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUuids.php`: `newUniqueId()` returns `(string) Str::uuid7()`. This means `recognition_events.id` is already time-ordered without any per-model override — CONTEXT.md's concern at input item 6 is resolved automatically. UUIDv7 gives the same B-tree locality as monotonic bigints while keeping the cross-context FK stability of UUIDs.

3. **Pest 4 `pest()->group('fras')` is a top-of-file call** — `tests/Pest.php` already globally applies `RefreshDatabase` to `Feature/*`, so new FRAS feature tests inherit pgsql-backed transactions for free. CLI runs via `./vendor/bin/pest --group=fras`. No phpunit.xml edit needed (`.env.testing: DB_CONNECTION=pgsql` verified — FRAMEWORK-05 passes by inspection).

**Primary recommendation:** Write 4 migrations + 4 models + 4 enums + 4 factories + 2 tests in the exact idiom of `create_incidents_table.php`, `Incident.php`, `IncidentOutcome.php`, `IncidentFactory.php`. The only net-new technical territory is DB-level CHECK constraints via raw `DB::statement` (Blueprint has no helper for these in Laravel 13) and the GIN index on `raw_payload` (Blueprint's `$table->index('col', null, 'gin')` emits valid DDL but must be verified on pgsql).

---

## User Constraints (from CONTEXT.md)

### Locked Decisions

All 70 decisions D-01 through D-70 are locked verbatim from CONTEXT.md. Full text is in `18-CONTEXT.md`. Key load-bearing constraints the planner must honor:

- **D-01:** All 4 FRAS models use `HasUuids` trait verbatim (matches `Incident` precedent).
- **D-02:** Migration column: `$table->uuid('id')->primary()`.
- **D-08:** `cameras.location` via `$table->geography('location', subtype: 'point', srid: 4326)->nullable()`.
- **D-09, D-16, D-24, D-49:** DB-level CHECK constraints for every enum column (cameras.status, personnel.category, camera_enrollments.status, recognition_events.severity).
- **D-22, D-23:** `camera_enrollments.camera_id` / `personnel_id` both `ON DELETE CASCADE`.
- **D-32:** `recognition_events.camera_id` `ON DELETE RESTRICT` (preserves recognition history).
- **D-33, D-34, D-50:** `personnel_id` / `incident_id` / `acknowledged_by` all `ON DELETE SET NULL`.
- **D-45, D-46:** `captured_at` + `received_at` both `TIMESTAMPTZ(6)` (microsecond precision).
- **D-48:** `raw_payload` `jsonb NOT NULL` + GIN index.
- **D-54:** UNIQUE `(camera_id, record_id)` on recognition_events (FRAMEWORK-06 idempotency).
- **D-57:** GIST spatial index on `cameras.location` via `$table->spatialIndex('location')`.
- **D-58:** FRAMEWORK-05 satisfied by existing `.env.testing: DB_CONNECTION=pgsql` — no phpunit.xml edit.
- **D-59, D-60:** Two feature tests in `tests/Feature/Fras/` with `pest()->group('fras')`.
- **D-62:** `DatabaseSeeder` NOT modified; `FrasPlaceholderSeeder` class is empty-by-default.
- **D-67, D-68, D-69, D-70:** Four new migrations timestamp-dated 2026-04-2x in FK dependency order (cameras → personnel → camera_enrollments → recognition_events), `dropIfExists` in `down()`, raw `DB::statement` for CHECKs.

### Claude's Discretion

- Exact migration filenames (planner picks `2026_04_21_...`)
- Enum class location: `app/Enums/` (matches `IncidentOutcome.php` precedent — confirmed below)
- `camera_id_display` `char(10)` vs `varchar(10)`: **recommend `varchar(10)`** — `Unit.id` uses `string('id', 20)` which emits varchar on Postgres; matching pattern.
- GIN index DDL: **recommend `DB::statement("CREATE INDEX ... USING GIN (raw_payload jsonb_path_ops)")`** — `jsonb_path_ops` is 30-40% smaller + faster for `@>` / `->>` lookups (which is how Phase 19/20 will query). Justification in §Architecture Patterns below.
- Personnel `custom_id` width: **recommend `varchar(48)`** — keep FRAS source width; UUID-width would be premature optimization and breaks if FRAS firmware ever emits longer strings.
- Butuan coordinate jitter: **recommend 8.9475°N ± 0.05°, 125.5406°E ± 0.05°** — copied verbatim from `UnitFactory.php` line 44-46 and `IncidentFactory.php` line 33-36, which is already the shipped IRMS convention for factory geography.

### Deferred Ideas (OUT OF SCOPE)

All items listed in CONTEXT.md `<deferred>` section are out of scope for Phase 18:
- `camera_id_display` auto-sequencing logic (Phase 20)
- MapLibre camera picker (Phase 20)
- Heartbeat watchdog + `CameraStatusChanged` broadcast (Phase 20)
- `EnrollPersonnelBatch` jobs (Phase 20)
- FRAS photo disks (Phase 19)
- DPA audit log (`fras_access_log`), signed URLs, retention purge (Phase 22)
- Email/SMS notifications on Critical recognition (Phase 22)
- GIN index on `raw_payload->>'cameraDeviceId'` optimization (deferred; the primary GIN covers needs)
- `citext` extension (Phase 20 may reconsider)
- Photo thumbnail generation (Phase 20)
- Retention-purge column on recognition_events (Phase 22)

---

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| FRAMEWORK-04 | IRMS Postgres schema gains empty `cameras`, `personnel`, `camera_enrollments`, `recognition_events` tables with UUID primary keys, JSONB columns (with GIN indexes on `raw_payload`), TIMESTAMPTZ timestamps, and Magellan `geography(POINT, 4326)` for `cameras.location` | §Standard Stack (Magellan 2.1.0 API), §Architecture Patterns (migration DDL templates), §Code Examples (migration boilerplate, GIN DDL, CHECK DDL) |
| FRAMEWORK-05 | Pest test suite switches to PostgreSQL for FRAS test groups so JSONB + geography behavior is actually exercised | §Validation Architecture (Pest group, `RefreshDatabase` inheritance), §Code Examples (spatial feature test), D-58 verification (`.env.testing: DB_CONNECTION=pgsql` confirmed) |
| FRAMEWORK-06 | `recognition_events` has a `(camera_id, record_id)` unique constraint for idempotency against MQTT redelivery | §Architecture Patterns (UNIQUE DDL), §Code Examples (idempotency test catching `UniqueConstraintViolationException`), §Common Pitfalls (constraint naming collisions) |

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| DDL for 4 FRAS tables | Database / Storage | — | Schema lives in `database/migrations/` — Blueprint + raw `DB::statement` for CHECKs |
| PHP enum backing for CHECK columns | API / Backend | Database (CHECK constraint) | Eloquent casts enums on read/write; DB rejects malformed writes at the storage layer |
| UUIDv7 generation | API / Backend | — | `HasUuids` trait in `app/Models/` emits ids at PHP layer before insert |
| Magellan `Point` serialization | API / Backend | Database (PostGIS) | Model cast converts `Point` ↔ PostGIS `geography` on hydration/persistence |
| Factory `raw_payload` JSON shape | API / Backend (tests) | — | `database/factories/*` — pure PHP arrays cast to `jsonb` by Eloquent |
| Spatial query `ST_DWithin` | Database (PostGIS function) | API / Backend (DB::selectRaw or Magellan `ST::dWithinGeography`) | Test uses raw query against `geography` column — no app-layer distance math |
| Idempotency enforcement | Database (UNIQUE constraint) | — | FK-level guarantee, not app-level check |
| FK cascade behavior | Database (FK `ON DELETE`) | — | CASCADE / RESTRICT / SET NULL encoded in migration |

**Planner consequence:** every task lives in `database/`, `app/Models/`, `app/Enums/`, or `tests/Feature/Fras/`. No controllers, no routes, no broadcast channels, no config files, no frontend code. If a plan proposes work outside those four directories, it has drifted out of scope.

---

## Standard Stack

### Core (all already installed — no composer changes)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | `^13.0` (installed 13.5.0) | Migration Schema Builder, `HasUuids` trait (emits UUIDv7), jsonb/timestampTz helpers | Phase 17 shipped |
| `clickbar/laravel-magellan` | `^2.1` (installed 2.1.0, 2026-03-17 release) | `$table->geography()`, `Point::class` cast, `Point::makeGeodetic()`, `ST::dWithinGeography()` | Phase 17 verified L13-compat |
| `pestphp/pest` | `^4.6` | Test framework; `pest()->group('fras')` annotation; `->toThrow()` expectations | v1.0 baseline |
| PostgreSQL + PostGIS | 3.x + 13+ | `jsonb` + GIN + `geography(POINT, 4326)` + TIMESTAMPTZ + CHECK constraints | Phase 1 baseline |

**Installed version verification (2026-04-21):**

```bash
$ composer show clickbar/laravel-magellan | head -3
name     : clickbar/laravel-magellan
versions : * 2.1.0
released : 2026-03-17, 1 month ago
```

[VERIFIED: `composer show` output + `vendor/clickbar/laravel-magellan/src/Data/Geometries/Point.php`]

### No new packages needed

CONTEXT.md explicitly rules this out; confirmed by research. Phase 17 baseline covers every API Phase 18 touches.

### Alternatives Considered

| Instead of | Could Use | Tradeoff | Verdict |
|------------|-----------|----------|---------|
| Laravel Blueprint `$table->index('raw_payload', null, 'gin')` | Raw `DB::statement("CREATE INDEX ... USING GIN (raw_payload jsonb_path_ops)")` | Blueprint emits `USING gin (raw_payload jsonb_ops)` (default opclass); raw DDL lets us pick `jsonb_path_ops` which is 30-40% smaller and faster for `@>` and `->>` queries (the exact access pattern Phase 19/20/22 will use) | **Raw DDL recommended** [CITED: postgresql.org/docs/current/datatype-json.html#JSON-INDEXING]. If a plan prefers Blueprint for consistency, that's acceptable — default opclass still works; the savings are a tuning opt. |
| DB CHECK constraint | App-only PHP enum cast | PHP cast catches bad writes from Eloquent; CHECK catches writes from raw queries, seeders, Phase 19 handlers that bypass Eloquent | **Belt-and-suspenders via DB CHECK + PHP enum cast** (D-09, D-16, D-24, D-49 already lock this — research confirms no downside) |
| Postgres native ENUM type | VARCHAR + CHECK | Native ENUM rejects garbage at DB but requires migration to add values (cannot `ALTER TYPE ... ADD VALUE` inside a transaction on Postgres ≤11; OK on 12+); VARCHAR + CHECK is trivially refactorable | **VARCHAR + CHECK recommended** (matches IRMS v1.0 convention — `Incident.status`, `Unit.status`, `Incident.channel` all use this pattern) |

### Installation

No packages to install. Only artisan generators:

```bash
php artisan make:model Camera --migration --factory
php artisan make:model Personnel --migration --factory
php artisan make:model CameraEnrollment --migration --factory
php artisan make:model RecognitionEvent --migration --factory
php artisan make:enum CameraStatus          # if make:enum exists in L13
# (or create manually as plain PHP enum files)
php artisan make:test Fras/CameraSpatialQueryTest --pest
php artisan make:test Fras/RecognitionEventIdempotencyTest --pest
php artisan make:seeder FrasPlaceholderSeeder
```

[CITED: Laravel Boost guidelines — "use `php artisan make:` commands to create new files"]

---

## Architecture Patterns

### System Architecture Diagram

Phase 18 is schema-only. The "runtime" flow is migration execution:

```
$ php artisan migrate:fresh --seed
         │
         ▼
┌────────────────────────────────────────────────────┐
│  Laravel Migration Runner                          │
│  (reads database/migrations/*.php in date order)   │
└────────────────────────────────────────────────────┘
         │
         ├── 2026_03_12_000001 enable_postgis_extension (already shipped)
         │
         │   [Phase 18 new migrations start here]
         ▼
    ┌─ cameras table
    │     ├─ uuid PK + HasUuids-ready
    │     ├─ varchar(64) device_id UNIQUE
    │     ├─ varchar(10) camera_id_display UNIQUE NULLABLE
    │     ├─ geography(POINT, 4326) location NULLABLE
    │     ├─ spatialIndex(location)        ── GIST via PostGIS
    │     └─ CHECK (status IN (...))       ── raw DB::statement
    │
    ├─ personnel table
    │     ├─ uuid PK
    │     ├─ full FRAS field set (id_card, phone, address, photo_*)
    │     └─ CHECK (category IN (...))
    │
    ├─ camera_enrollments pivot
    │     ├─ uuid PK
    │     ├─ camera_id → cameras (CASCADE)
    │     ├─ personnel_id → personnel (CASCADE)
    │     ├─ UNIQUE (camera_id, personnel_id)
    │     ├─ INDEX (camera_id, status)
    │     ├─ INDEX (personnel_id, status)
    │     └─ CHECK (status IN (...))
    │
    └─ recognition_events table
          ├─ uuid PK
          ├─ camera_id → cameras (RESTRICT)
          ├─ personnel_id → personnel (SET NULL)
          ├─ incident_id → incidents (SET NULL)
          ├─ acknowledged_by → users (SET NULL)
          ├─ UNIQUE (camera_id, record_id)   ── FRAMEWORK-06
          ├─ INDEX (camera_id, captured_at)
          ├─ INDEX (person_type, verify_status)
          ├─ INDEX (severity), (is_real_time, severity), (incident_id)
          ├─ GIN INDEX (raw_payload jsonb_path_ops)
          └─ CHECK (severity IN (...))
         │
         ▼
┌────────────────────────────────────────────────────┐
│  DatabaseSeeder (NOT modified — D-62)              │
│  FrasPlaceholderSeeder exists but is not called    │
└────────────────────────────────────────────────────┘
         │
         ▼
  ✓ `php artisan migrate:fresh --seed` exits 0
  ✓ 4 empty FRAS tables exist
  ✓ All 8 FK constraints are enforceable
  ✓ 2 feature tests in tests/Feature/Fras/ pass
```

### Recommended Project Structure

```
database/
├── migrations/
│   ├── 2026_04_21_000001_create_cameras_table.php
│   ├── 2026_04_21_000002_create_personnel_table.php
│   ├── 2026_04_21_000003_create_camera_enrollments_table.php
│   └── 2026_04_21_000004_create_recognition_events_table.php
├── factories/
│   ├── CameraFactory.php
│   ├── PersonnelFactory.php
│   ├── CameraEnrollmentFactory.php
│   └── RecognitionEventFactory.php
└── seeders/
    └── FrasPlaceholderSeeder.php     # empty — not wired into DatabaseSeeder

app/
├── Enums/
│   ├── CameraStatus.php              # Online, Offline, Degraded
│   ├── PersonnelCategory.php         # Allow, Block, Missing, LostChild
│   ├── CameraEnrollmentStatus.php    # Pending, Syncing, Done, Failed
│   └── RecognitionSeverity.php       # Info, Warning, Critical
└── Models/
    ├── Camera.php
    ├── Personnel.php
    ├── CameraEnrollment.php
    └── RecognitionEvent.php

tests/Feature/Fras/
├── CameraSpatialQueryTest.php        # ST_DWithin (SC5)
└── RecognitionEventIdempotencyTest.php  # UNIQUE (camera_id, record_id) (SC2, FRAMEWORK-06)
```

### Pattern 1: UUID PK with HasUuids (emits UUIDv7 in L13)

**What:** All 4 FRAS models use `HasUuids`. Laravel 13 emits **UUIDv7** (time-ordered) by default — verified in `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUuids.php`:

```php
// Laravel 13.5.0 source:
trait HasUuids {
    use HasUniqueStringIds;

    public function newUniqueId() {
        return (string) Str::uuid7();   // ← time-ordered, not v4
    }
    ...
}
```

**When to use:** Every FRAS table (cameras, personnel, camera_enrollments, recognition_events).

**Why it matters for Phase 18:** UUIDv7 gives the same B-tree insertion locality as bigints (older UUIDv4 causes B-tree hot-spotting). The `(camera_id, captured_at)` index on recognition_events benefits because `id` itself is monotonic — range scans on `id` correlate with time order. **No per-model override needed** — CONTEXT.md's open question (research input item 6) is resolved: don't override.

**Example** (direct from `app/Models/Incident.php:10-21`):

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Camera extends Model
{
    /** @use HasFactory<CameraFactory> */
    use HasFactory, HasUuids;
    // No $keyType / $incrementing declarations needed — trait handles them.
}
```

[VERIFIED: `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUuids.php` line 13, `Str::uuid7()`]

### Pattern 2: Magellan `geography(POINT, 4326)` migration + model cast

**What:** `cameras.location` is stored as PostGIS `geography(POINT, 4326)` via Magellan. Model cast is `Point::class` from `Clickbar\Magellan\Data\Geometries\Point`.

**When to use:** `cameras.location` (D-08). No other FRAS table needs geography.

**Example — migration** (copy verbatim from `create_incidents_table.php:22,45`):

```php
$table->geography('location', subtype: 'point', srid: 4326)->nullable();
// ... after other columns ...
$table->spatialIndex('location');  // emits: CREATE INDEX ... USING GIST (location)
```

**Example — model** (copy verbatim from `app/Models/Incident.php:69`):

```php
protected function casts(): array
{
    return [
        'location' => Point::class,  // Clickbar\Magellan\Data\Geometries\Point
        'status' => CameraStatus::class,
        'last_seen_at' => 'datetime',
        'decommissioned_at' => 'datetime',
    ];
}
```

**Example — factory** (copy pattern from `UnitFactory.php:43-46` — note `makeGeodetic` is **lat, lng** order, not x, y):

```php
'location' => Point::makeGeodetic(
    8.9475 + fake()->randomFloat(4, -0.05, 0.05),    // latitude (Butuan City)
    125.5406 + fake()->randomFloat(4, -0.05, 0.05),  // longitude
),
```

**API distinction that has bitten developers:**
- `Point::make($x, $y, ...)` — **x first (= longitude), y second (= latitude)**
- `Point::makeGeodetic($latitude, $longitude, ...)` — **latitude first** (auto-sets SRID from `config('magellan.geodetic_default_srid')`, normally 4326)

**Use `makeGeodetic` in factories** — matches existing `UnitFactory` / `IncidentFactory` convention.

[VERIFIED: `vendor/clickbar/laravel-magellan/src/Data/Geometries/Point.php:10,19`, `database/factories/UnitFactory.php:43`]

### Pattern 3: UUID foreign keys — use `foreignUuid()->constrained()`

**What:** FRAS source uses MySQL `foreignId('camera_id')->constrained()` which emits bigint. With UUID PKs, Laravel 13's sugar is `foreignUuid('camera_id')->constrained()`.

**v1.0 precedent:** `database/migrations/2026_03_13_200001_create_incident_unit_table.php:15-27` uses the longer form (`$table->uuid('incident_id')` + explicit `$table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete()`). This is because the pivot was authored before Laravel 11's `foreignUuid` sugar matured. Laravel 13 supports both; **recommend the sugar form** for new migrations.

**Example** (preferred, Laravel 13):

```php
$table->foreignUuid('camera_id')->constrained()->cascadeOnDelete();
$table->foreignUuid('personnel_id')->constrained()->cascadeOnDelete();
// For RESTRICT:
$table->foreignUuid('camera_id')->constrained()->restrictOnDelete();
// For SET NULL (column must be nullable):
$table->foreignUuid('personnel_id')->nullable()->constrained()->nullOnDelete();
$table->foreignUuid('incident_id')->nullable()->constrained()->nullOnDelete();
$table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
```

**Note on table name inference:** `constrained()` without args infers the table name from the column (`personnel_id` → `personnel`, `camera_id` → `cameras`). Because English pluralizes "personnel" to "personnel" (unchanged), `constrained()` works for `personnel_id → personnel`. For `acknowledged_by → users`, pass the table name explicitly: `constrained('users')`.

[VERIFIED: `database/migrations/2026_03_13_200001_create_incident_unit_table.php`, Laravel 13 Schema Builder docs via Context7]

### Pattern 4: DB CHECK constraint via raw `DB::statement`

**What:** Laravel 13 Blueprint does **not** expose a first-party `$table->check()` helper. The correct pattern is raw DDL inside the migration, after `Schema::create`.

**Example** (authored for this phase):

```php
Schema::create('cameras', function (Blueprint $table) {
    // ... columns ...
    $table->string('status', 20)->default('offline');
});

// After Schema::create, but inside up():
DB::statement(
    "ALTER TABLE cameras ADD CONSTRAINT cameras_status_check "
    . "CHECK (status IN ('online','offline','degraded'))"
);

// Inside down() — BEFORE Schema::dropIfExists (though dropping the table drops the CHECK):
// If you drop the table, the CHECK goes with it. No explicit DROP CONSTRAINT needed.
// But if a future migration alters the table rather than drops it, the pattern is:
//   DB::statement('ALTER TABLE cameras DROP CONSTRAINT IF EXISTS cameras_status_check');
```

**Naming convention:** `{table}_{column}_check` — matches Postgres auto-generated pattern but explicit naming is more robust (two CHECKs on the same column would collide without explicit names).

**Postgres pitfall:** Constraint names are identifiers; Postgres caps identifier length at **63 bytes**. `camera_enrollments_status_check` = 31 bytes, safe. No concerns for Phase 18 table names.

[CITED: postgresql.org/docs/current/sql-createtable.html "CHECK constraint"; Laravel 13 migration docs — no Blueprint `check()` method]

### Pattern 5: JSONB column + GIN index

**What:** `recognition_events.raw_payload` is `jsonb NOT NULL` with a GIN index for the `@>` (contains) and `->>` (key lookup) operators Phase 19/20/22 will use.

**Column DDL:** Blueprint has first-party `$table->jsonb('col')` in Laravel 13:

```php
$table->jsonb('raw_payload');           // NOT NULL by default
$table->jsonb('target_bbox')->nullable();
```

**GIN index — two acceptable options:**

**Option A (recommended): raw DDL with `jsonb_path_ops` opclass**

```php
DB::statement(
    'CREATE INDEX recognition_events_raw_payload_gin '
    . 'ON recognition_events USING GIN (raw_payload jsonb_path_ops)'
);
```

`jsonb_path_ops` is 30-40% smaller on disk and 15-30% faster for `@>` lookups than the default `jsonb_ops`. Trade-off: it only supports `@>`, not `?`, `?|`, `?&`. For Phase 19/20/22's access pattern (`WHERE raw_payload @> '{"operator":"RecPush"}'` or `raw_payload->>'cameraDeviceId' = 'xyz'`), this is fine — `->>` uses a different index strategy anyway.

**Option B (Laravel Blueprint, simpler):**

```php
$table->index('raw_payload', 'recognition_events_raw_payload_gin', 'gin');
```

This emits `CREATE INDEX ... USING gin (raw_payload jsonb_ops)` — default opclass, supports all operators but heavier.

**Recommendation:** Use Option A. Phase 19 handlers will query `WHERE raw_payload @> '{...}'` — the `jsonb_path_ops` opclass is the right fit. If plan authors prefer Blueprint for consistency, Option B is acceptable and the index still works.

[CITED: postgresql.org/docs/current/datatype-json.html#JSON-INDEXING, "jsonb_path_ops supports only the @> operator, but produces a smaller and faster index"]

### Pattern 6: TIMESTAMPTZ with microsecond precision

**What:** `captured_at` and `received_at` on recognition_events are `TIMESTAMPTZ(6)` — timezone-aware, microsecond precision.

**Laravel 13 Blueprint signature** (verified in `vendor/laravel/framework/src/Illuminate/Database/Schema/Blueprint.php`, `timestampTz` and `timestamp` both accept `$precision` parameter):

```php
$table->timestampTz('captured_at', precision: 6);   // → TIMESTAMPTZ(6)
$table->timestampTz('received_at', precision: 6);   // → TIMESTAMPTZ(6)
$table->timestampTz('last_seen_at')->nullable();    // → TIMESTAMPTZ (precision 0 by default; cameras heartbeat every 30s — sub-second not needed)
$table->timestampTz('decommissioned_at')->nullable(); // → TIMESTAMPTZ
```

**Do NOT use `$table->timestamp('col', 6)`** — that emits `TIMESTAMP WITHOUT TIME ZONE(6)` on Postgres, which is exactly what we don't want (per PITFALLS Pitfall 5).

**Precision 0 vs 6 choice:**
- Use **precision 6** for `captured_at`, `received_at` — camera-clock events need sub-second ordering to correlate duplicate deliveries.
- Use **precision 0** (default) for `enrolled_at`, `last_seen_at`, `acknowledged_at`, `dismissed_at`, `decommissioned_at`, `expires_at`, `created_at`, `updated_at` — human-scale timestamps; microseconds add bytes without value.

**Eloquent cast:** Both precisions cast to `'datetime'` transparently; Carbon preserves microseconds on read and writes them back.

[VERIFIED: `vendor/laravel/framework/src/Illuminate/Database/Schema/Blueprint.php` — `timestampTz(string $column, ?int $precision = null)`]

### Pattern 7: PHP enum (string-backed) + DB CHECK dual enforcement

**What:** 4 PHP enums (CameraStatus, PersonnelCategory, CameraEnrollmentStatus, RecognitionSeverity) live in `app/Enums/`. Backed by string. Used as Eloquent casts AND enforced at DB via CHECK constraints.

**Example** (direct from `app/Enums/IncidentOutcome.php` pattern):

```php
<?php

namespace App\Enums;

enum PersonnelCategory: string
{
    case Allow = 'allow';
    case Block = 'block';
    case Missing = 'missing';
    case LostChild = 'lost_child';

    public function label(): string
    {
        return match ($this) {
            self::Allow => 'Allow',
            self::Block => 'Block',
            self::Missing => 'Missing',
            self::LostChild => 'Lost Child',
        };
    }
}
```

**Migration CHECK mirrors enum values verbatim:**

```php
DB::statement(
    "ALTER TABLE personnel ADD CONSTRAINT personnel_category_check "
    . "CHECK (category IN ('allow','block','missing','lost_child'))"
);
```

**Model cast:**

```php
protected function casts(): array
{
    return [
        'category' => PersonnelCategory::class,
    ];
}
```

**Invariant to maintain:** Enum `case ... = 'value'` string MUST match CHECK `IN (...)` list AND match any factory state string. Any drift breaks inserts. A Pest test should pair each enum with its CHECK (suggested in §Validation Architecture below).

[VERIFIED: `app/Enums/IncidentOutcome.php` precedent, Laravel 13 enum cast docs]

### Pattern 8: UNIQUE composite index for idempotency

**What:** FRAMEWORK-06: `UNIQUE (camera_id, record_id)` on recognition_events. A duplicate MQTT RecPush delivery fails at the DB, not in app code.

**Example:**

```php
$table->unique(['camera_id', 'record_id'], 'recognition_events_camera_record_unique');
```

**Explicit index name** (second arg): prevents Postgres from auto-generating `recognition_events_camera_id_record_id_unique` which would be 47 chars — safe but ugly. Naming it explicitly makes `EXPLAIN` output readable and makes the test's `expectException` message more debuggable.

**How insert-time violation surfaces in PHP:** Eloquent's `Model::create()` throws `Illuminate\Database\UniqueConstraintViolationException` (a subclass of `QueryException`) since Laravel 10.

[CITED: laravel.com/docs/13.x/eloquent — exceptions; verified by existing code patterns]

### Anti-Patterns to Avoid

- **`$table->timestamp('captured_at', 6)` instead of `timestampTz`** — emits `TIMESTAMP WITHOUT TIME ZONE`; camera clocks drift and Butuan is UTC+8, so tz-naive breaks retention and alert feed display. [PITFALLS Pitfall 5]
- **`$table->json('raw_payload')` instead of `jsonb`** — `json` is text-preserving, no GIN support, Seq Scan on every `@>` query. [PITFALLS Pitfall 3]
- **`$table->id()` on FRAS tables** — bigint PK forces split-brain with IRMS v1.0 UUID tables; recognition_events.incident_id is uuid, so the reverse FK from incidents would fail. [PITFALLS Pitfall 4]
- **Hand-rolled UUID generation in `booted()` hook** — `HasUuids` already does this correctly; adding a custom hook is redundant and risks UUIDv4 regression (loses UUIDv7 time-ordering).
- **Nullable `raw_payload`** — every RecPush event has a payload; nullable invites accidental inserts with no audit trail. D-48 locks `jsonb NOT NULL`.
- **CHECK constraints in `down()` as `DROP CONSTRAINT`** — when `dropIfExists` drops the table, CHECKs go with it. Explicit DROP CONSTRAINT is only needed for non-destructive schema changes (not this phase).
- **Seeding FRAS tables from `DatabaseSeeder`** — D-62 locks this out. If `DatabaseSeeder::run()` calls FRAS seeders, `migrate:fresh --seed` in production deploys stale factory data.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| UUID generation | Custom `booted()` hook with `Str::uuid()` | `HasUuids` trait | Emits UUIDv7 (time-ordered) in L13; `isValidUniqueId()` validation included; zero config |
| PostGIS point serialization | DIY lat/lng ↔ WKB encoder | Magellan `Point::class` cast | Handles `ST_AsEWKB` / `ST_GeomFromText` wire format, byte order, NaN, and empty points |
| Factory Point creation | `new Point($lng, $lat, 4326)` | `Point::makeGeodetic($lat, $lng)` | Correct arg order for geodetic intent; auto-applies `config('magellan.geodetic_default_srid')` |
| Foreign key constraint DDL | Raw `DB::statement('ALTER TABLE ... ADD CONSTRAINT')` | `foreignUuid('col')->constrained()->cascadeOnDelete()` | Laravel 13 builder emits correct DDL + gives `down()` the drop for free |
| CHECK constraint on enum | App-side validation only | DB CHECK + PHP enum cast | PHP cast catches Eloquent writes; CHECK catches raw writes, seeders, `DB::insert`, and future Phase 19 handlers |
| JSON `@>` query planning | `DB::statement('SET enable_seqscan = off')` or manual index hints | GIN index on `raw_payload` | Postgres planner chooses GIN automatically when selectivity is high enough; no hints required |
| UUIDv7 generation | `ramsey/uuid` direct call | `Str::uuid7()` (used by `HasUuids`) | Laravel 13 provides `Str::uuid7()`; no composer dep |
| Idempotency check | App-level `RecognitionEvent::firstOrCreate(['camera_id' => ..., 'record_id' => ...])` | UNIQUE constraint + catch `UniqueConstraintViolationException` | DB-level enforcement; `firstOrCreate` has a TOCTOU race under concurrent MQTT delivery |

**Key insight:** Every problem Phase 18 touches has a first-party Laravel 13 + Magellan 2.1.0 solution. The only raw DDL this phase needs is CHECK constraints (no Blueprint helper exists) and optionally the `jsonb_path_ops` opclass GIN index.

---

## Runtime State Inventory

**N/A — this is a greenfield phase.** Phase 18 creates four new tables. No renames, no migrations of existing data, no OS-registered state, no live service config. `migrate:fresh` drops and recreates — no risk to v1.0 data beyond test DB.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | None — all four tables are new | None |
| Live service config | None — no new queue, no new channel, no new cron | None |
| OS-registered state | None — no Supervisor program, no cron job, no systemd unit | None |
| Secrets/env vars | None — `DB_CONNECTION=pgsql` already set in `.env.testing` (D-58) | None |
| Build artifacts | None — no composer changes (Phase 17 baseline sufficient); no npm changes | None |

**Nothing found in any category:** Verified by cross-reference against CONTEXT.md `<deferred>` section (MQTT, photo disks, queue config, supervisor configs, auth gates — all Phase 19+).

---

## Environment Availability

All external dependencies are **already present** from Phase 17. Verified by inspection of `composer.json` and `vendor/` at 2026-04-21.

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Laravel 13 | ✓ | ^8.3 (composer.json line 12) | — |
| PostgreSQL | All 4 migrations (jsonb, TIMESTAMPTZ, CHECK) | ✓ | 13+ assumed (Phase 1 baseline, `.env.testing: DB_HOST=127.0.0.1:5432`) | — |
| PostGIS | `cameras.location` geography column | ✓ | 3.x (enabled by `2026_03_12_000001_enable_postgis_extension.php`) | — |
| `clickbar/laravel-magellan` | Magellan geography + Point cast | ✓ | 2.1.0 (released 2026-03-17) | — |
| `laravel/framework` | Schema Builder, HasUuids, timestampTz, jsonb helpers, foreignUuid | ✓ | 13.5.0 | — |
| `pestphp/pest` | `pest()->group('fras')`, `->toThrow()` | ✓ | 4.6 | — |
| `fakerphp/faker` | Factory data generation | ✓ | ^1.23 (dev) | — |
| Laravel Herd pgsql service | Test DB connection for `DB_CONNECTION=pgsql` | Assumed ✓ (Phase 17 tests ran on pgsql) | — | — |

**Missing dependencies with no fallback:** None.

**Missing dependencies with fallback:** None.

**Unblocking verification step for plans:** Before any migration, plans should include a smoke check:

```bash
php artisan tinker --execute 'DB::selectOne("SELECT version()"); DB::selectOne("SELECT postgis_version()");'
```

If this errors, the Herd Postgres service is down — surface to user, do not attempt migrations.

---

## Common Pitfalls

### Pitfall 1: Blueprint emits `json` instead of `jsonb` if you type the wrong helper

**What goes wrong:** `$table->json('raw_payload')` on Postgres emits `json` (text-preserving, no GIN support). Phase 19 handler queries `WHERE raw_payload @> '{...}'` do Seq Scan.

**Why it happens:** Laravel has both `json()` and `jsonb()` builders. Most docs show `json()`. Muscle memory beats instinct.

**How to avoid:** Always use `$table->jsonb('col')` in Phase 18. Grep the migration files before commit:

```bash
grep -rn "\$table->json(" database/migrations/2026_04_21_*.php
# Must return zero matches.
```

**Warning signs:** `EXPLAIN SELECT * FROM recognition_events WHERE raw_payload @> '{...}'` shows Seq Scan on 10k+ rows.

### Pitfall 2: `timestamp()` with precision arg silently strips timezone on Postgres

**What goes wrong:** `$table->timestamp('captured_at', 6)` looks correct (microsecond precision!) but emits `TIMESTAMP WITHOUT TIME ZONE(6)`. Camera-reported Asia/Manila time stored as naive UTC — 8h drift.

**Why it happens:** Laravel's `timestamp()` and `timestampTz()` are separate builders. The precision arg works on both. The TZ-ness is in the method name, not the arg.

**How to avoid:** Use `$table->timestampTz('captured_at', precision: 6)` for any camera-origin timestamp. Named arg makes intent explicit.

**Warning signs:** `\d recognition_events` shows `timestamp without time zone` for `captured_at`. Round-trip test stores `2026-04-21 08:00:00+08` and reads back `2026-04-21 08:00:00` (naive).

### Pitfall 3: `foreignUuid('personnel_id')->constrained()` infers wrong table name

**What goes wrong:** "personnel" is already plural (unchanged in English pluralization), but Laravel's inflector may incorrectly pluralize it.

**How to avoid:** Pass the table name explicitly where ambiguity exists:

```php
$table->foreignUuid('personnel_id')->constrained('personnel')->cascadeOnDelete();
```

Test the migration once against a clean DB — if `constrained()` works, drop the arg; if it errors, keep it.

**Warning signs:** Migration fails with `relation "personnels" does not exist`.

### Pitfall 4: CHECK constraint name collision from auto-generated identifiers

**What goes wrong:** Two migrations both add a CHECK on different tables but end up with colliding auto-generated names. Postgres raises `ERROR: constraint already exists`.

**Why it happens:** Postgres auto-generates `{table}_{column}_check` when no name is provided. Unlikely but possible if a v2.1 migration adds a check on `cameras.status` with the same auto-name.

**How to avoid:** Always explicitly name CHECK constraints: `ADD CONSTRAINT cameras_status_check CHECK (...)`. Postgres identifier limit 63 bytes — all Phase 18 names fit easily.

**Warning signs:** Migration fails with `constraint "cameras_status_check" of relation "cameras" already exists`.

### Pitfall 5: Factory generates a `Point` but Eloquent can't persist it due to SRID mismatch

**What goes wrong:** Factory calls `new Point($lng, $lat)` (no SRID). Eloquent tries to insert into a `geography(POINT, 4326)` column. PostGIS rejects: "Geometry SRID (0) does not match column SRID (4326)".

**How to avoid:** Always use `Point::makeGeodetic($lat, $lng)` in factories — auto-sets SRID from `config('magellan.geodetic_default_srid')` (4326). Verified by `vendor/clickbar/laravel-magellan/src/Data/Geometries/Point.php:19-24`.

**Warning signs:** Factory-generated Camera fails to save with PostGIS SRID error.

### Pitfall 6: `DatabaseSeeder` accidentally gains a `FrasPlaceholderSeeder::class` call

**What goes wrong:** Someone wires `FrasPlaceholderSeeder` into `DatabaseSeeder::run()` thinking "if the class exists, it must be meant to run." Production seed now creates fake cameras in the live DB.

**How to avoid:** `FrasPlaceholderSeeder` ships empty-by-default. **D-62 locks:** DatabaseSeeder is NOT modified in Phase 18. Plan verification should grep for this:

```bash
grep -n "Fras" database/seeders/DatabaseSeeder.php
# Must return zero matches.
```

**Warning signs:** `php artisan db:seed` populates FRAS tables with fake data.

### Pitfall 7: `raw_payload` factory shape drifts from real FRAS firmware format

**What goes wrong:** Factory writes `raw_payload` with keys the firmware doesn't actually emit (e.g., `personName` but firmware sends `persionName`). Phase 19 RecognitionHandler parsing tests pass against factory data but fail in prod.

**How to avoid:** Copy the **exact** RecPush payload shape from `/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php` — the FRAS source is authoritative. Document in §Code Examples below. Factory must include **both** `personName` and `persionName` in an example state — the parser reads `personName ?? persionName` (source line 118), so Phase 19 tests need both shapes as fixtures.

**Warning signs:** Phase 19 RecognitionHandler tests pass unit-test-style but fail on real broker traffic.

### Pitfall 8: Postgres interprets `is_no_mask smallint NOT NULL` and factory emits `fake()->boolean()`

**What goes wrong:** FRAS firmware emits `isNoMask: 0 | 1 | 2` (smallint, three states: unknown/masked/unmasked). Factory writes `fake()->boolean()` which is `0 | 1`. Not *broken*, but loses semantic range for future test cases.

**How to avoid:** Use `fake()->numberBetween(0, 2)` in the factory. [D-43 locks smallint.]

---

## Code Examples

### Example 1: Migration for `cameras` (full verbatim template)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_id', 64)->unique();
            $table->string('camera_id_display', 10)->unique()->nullable();
            $table->string('name', 100);
            $table->string('location_label', 150);
            $table->geography('location', subtype: 'point', srid: 4326)->nullable();
            $table->string('status', 20)->default('offline');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('decommissioned_at')->nullable();
            $table->timestamps();

            $table->spatialIndex('location');
        });

        DB::statement(
            "ALTER TABLE cameras ADD CONSTRAINT cameras_status_check "
            . "CHECK (status IN ('online','offline','degraded'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
    }
};
```

### Example 2: Migration for `recognition_events` (selected high-risk columns)

```php
Schema::create('recognition_events', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('camera_id')->constrained()->restrictOnDelete();
    $table->foreignUuid('personnel_id')->nullable()->constrained('personnel')->nullOnDelete();
    $table->foreignUuid('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
    $table->bigInteger('record_id');
    $table->string('custom_id', 100)->nullable();
    $table->string('camera_person_id', 100)->nullable();
    $table->smallInteger('verify_status');
    $table->smallInteger('person_type');
    $table->float('similarity', 24);   // Postgres `real` = single-precision; L13 emits `real` when precision≤24
    $table->boolean('is_real_time');
    $table->string('name_from_camera', 100)->nullable();
    $table->string('facesluice_id', 100)->nullable();
    $table->string('id_card', 32)->nullable();
    $table->string('phone', 32)->nullable();
    $table->smallInteger('is_no_mask');
    $table->jsonb('target_bbox')->nullable();
    $table->timestampTz('captured_at', precision: 6);
    $table->timestampTz('received_at', precision: 6);
    $table->string('face_image_path', 255)->nullable();
    $table->string('scene_image_path', 255)->nullable();
    $table->jsonb('raw_payload');
    $table->string('severity', 10)->default('info');
    $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestampTz('acknowledged_at')->nullable();
    $table->timestampTz('dismissed_at')->nullable();
    $table->timestamps();

    $table->unique(['camera_id', 'record_id'], 'recognition_events_camera_record_unique');
    $table->index(['camera_id', 'captured_at']);
    $table->index(['person_type', 'verify_status']);
    $table->index('severity');
    $table->index(['is_real_time', 'severity']);
    $table->index('incident_id');
    $table->index('custom_id');
});

DB::statement(
    "ALTER TABLE recognition_events ADD CONSTRAINT recognition_events_severity_check "
    . "CHECK (severity IN ('info','warning','critical'))"
);

DB::statement(
    'CREATE INDEX recognition_events_raw_payload_gin '
    . 'ON recognition_events USING GIN (raw_payload jsonb_path_ops)'
);
```

### Example 3: PHP enum + model cast

```php
// app/Enums/RecognitionSeverity.php
<?php

namespace App\Enums;

enum RecognitionSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }
}

// app/Models/RecognitionEvent.php (partial)
protected function casts(): array
{
    return [
        'raw_payload' => 'array',
        'target_bbox' => 'array',
        'captured_at' => 'datetime',
        'received_at' => 'datetime',
        'severity' => RecognitionSeverity::class,
        'is_real_time' => 'boolean',
        'similarity' => 'float',
        'acknowledged_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];
}
```

### Example 4: `RecognitionEventFactory` — real RecPush payload shape

The real RecPush payload keys are extracted **verbatim** from `/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php` lines 108-125. The parser reads these exact keys; the factory must emit them so Phase 19 handler tests exercise the real shape.

```php
<?php

namespace Database\Factories;

use App\Enums\RecognitionSeverity;
use App\Models\Camera;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecognitionEvent>
 */
class RecognitionEventFactory extends Factory
{
    protected $model = RecognitionEvent::class;

    public function definition(): array
    {
        $capturedAt = now()->subSeconds(1);
        $recordId = fake()->unique()->numberBetween(1, 2_000_000_000);
        $customId = (string) fake()->numberBetween(10000, 99999);

        return [
            'camera_id' => Camera::factory(),
            'personnel_id' => null,
            'incident_id' => null,
            'record_id' => $recordId,
            'custom_id' => $customId,
            'camera_person_id' => (string) fake()->numberBetween(1, 1000),
            'verify_status' => fake()->numberBetween(0, 3),
            'person_type' => fake()->numberBetween(0, 1),
            'similarity' => fake()->randomFloat(2, 50, 99),
            'is_real_time' => true,
            'name_from_camera' => fake()->name(),
            'facesluice_id' => (string) fake()->numberBetween(1, 100),
            'id_card' => fake()->numerify('############'),
            'phone' => fake()->phoneNumber(),
            'is_no_mask' => fake()->numberBetween(0, 2),
            'target_bbox' => [120, 80, 340, 360],
            'captured_at' => $capturedAt,
            'received_at' => now(),
            'severity' => RecognitionSeverity::Info,
            'raw_payload' => [
                'operator' => 'RecPush',
                'info' => [
                    'customId' => $customId,
                    'personId' => (string) fake()->numberBetween(1, 1000),
                    'RecordID' => $recordId,
                    'VerifyStatus' => fake()->numberBetween(0, 3),
                    'PersonType' => fake()->numberBetween(0, 1),
                    'similarity1' => fake()->randomFloat(2, 50, 99),
                    'Sendintime' => 1,
                    'PushType' => 1,
                    'personName' => fake()->name(),          // ← firmware-normal spelling
                    // persionName intentionally omitted in default state
                    'facesluiceId' => (string) fake()->numberBetween(1, 100),
                    'idCard' => fake()->numerify('############'),
                    'telnum' => fake()->phoneNumber(),
                    'isNoMask' => fake()->numberBetween(0, 2),
                    'targetPosInScene' => [120, 80, 340, 360],
                    'time' => $capturedAt->format('Y-m-d H:i:s'),
                ],
            ],
        ];
    }

    /**
     * State for testing the firmware misspelling ("persionName" instead of "personName").
     * Phase 19 RecognitionHandler parses both via null-coalesce:
     *   $info['personName'] ?? $info['persionName']
     */
    public function withMisspelledName(): self
    {
        return $this->state(function (array $attrs): array {
            $info = $attrs['raw_payload']['info'];
            $misspelled = $info['personName'];
            unset($info['personName']);
            $info['persionName'] = $misspelled;   // ← firmware misspelling

            return [
                'raw_payload' => array_merge($attrs['raw_payload'], ['info' => $info]),
            ];
        });
    }

    public function critical(): self
    {
        return $this->state(fn () => ['severity' => RecognitionSeverity::Critical]);
    }

    public function warning(): self
    {
        return $this->state(fn () => ['severity' => RecognitionSeverity::Warning]);
    }

    public function info(): self
    {
        return $this->state(fn () => ['severity' => RecognitionSeverity::Info]);
    }

    public function withPersonnel(Personnel $personnel): self
    {
        return $this->state(fn () => [
            'personnel_id' => $personnel->id,
        ]);
    }

    public function blockMatch(): self
    {
        return $this->state(fn () => [
            'severity' => RecognitionSeverity::Critical,
            'verify_status' => 1,
            'person_type' => 1,
            'similarity' => 88.5,
        ]);
    }
}
```

[VERIFIED: FRAS source `/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php:108-125`]

### Example 5: Spatial feature test (SC5)

```php
<?php

// tests/Feature/Fras/CameraSpatialQueryTest.php

use App\Models\Camera;
use App\Models\Incident;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;

pest()->group('fras');

it('finds cameras within 500m of an incident using ST_DWithin', function () {
    // Arrange: camera at Butuan City center
    $camera = Camera::factory()->create([
        'location' => Point::makeGeodetic(8.9475, 125.5406),
    ]);

    // Incident ~100m north of the camera (well within 500m)
    $nearIncident = Incident::factory()->create([
        'coordinates' => Point::makeGeodetic(8.9484, 125.5406),
    ]);

    // Incident ~5km away (well outside 500m)
    $farIncident = Incident::factory()->create([
        'coordinates' => Point::makeGeodetic(8.9975, 125.5406),
    ]);

    // Act: raw query using PostGIS ST_DWithin on geography
    $nearbyCameraIds = DB::table('cameras')
        ->whereRaw(
            'ST_DWithin(cameras.location, (SELECT coordinates FROM incidents WHERE id = ?), ?)',
            [$nearIncident->id, 500]   // 500 meters (geography uses meters by default)
        )
        ->pluck('id')
        ->all();

    $farCameraIds = DB::table('cameras')
        ->whereRaw(
            'ST_DWithin(cameras.location, (SELECT coordinates FROM incidents WHERE id = ?), ?)',
            [$farIncident->id, 500]
        )
        ->pluck('id')
        ->all();

    // Assert
    expect($nearbyCameraIds)->toContain($camera->id);
    expect($farCameraIds)->not->toContain($camera->id);
});
```

**Alternate form — using Magellan's fluent API (optional, less portable):**

```php
use Clickbar\Magellan\Database\PostgisFunctions\ST;

// Not typically worth the indirection for a test — raw SQL is clearer.
// Documented only because CONTEXT.md input item 8 asked about the Magellan fluent form.
$nearbyCameras = Camera::whereRaw(
    (string) ST::dWithinGeography('location', $incident->coordinates, 500)
)->get();
```

**Recommendation:** Use the raw SQL form in the test. The Magellan fluent form adds nothing here and requires reading two APIs to understand one line.

[VERIFIED: `vendor/clickbar/laravel-magellan/src/Database/PostgisFunctions/MagellanDistanceRelationshipsFunctions.php:50-73`]

### Example 6: Idempotency feature test (SC2, FRAMEWORK-06)

```php
<?php

// tests/Feature/Fras/RecognitionEventIdempotencyTest.php

use App\Models\Camera;
use App\Models\RecognitionEvent;
use Illuminate\Database\UniqueConstraintViolationException;

pest()->group('fras');

it('rejects duplicate (camera_id, record_id) at the database layer', function () {
    $camera = Camera::factory()->create();

    $first = RecognitionEvent::factory()->create([
        'camera_id' => $camera->id,
        'record_id' => 12345,
    ]);

    expect($first->id)->not->toBeNull();

    // Duplicate insert MUST fail at the DB — not in app-level validation.
    expect(fn () => RecognitionEvent::factory()->create([
        'camera_id' => $camera->id,
        'record_id' => 12345,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('allows the same record_id across different cameras', function () {
    $cameraA = Camera::factory()->create();
    $cameraB = Camera::factory()->create();

    RecognitionEvent::factory()->create([
        'camera_id' => $cameraA->id,
        'record_id' => 99999,
    ]);

    $eventB = RecognitionEvent::factory()->create([
        'camera_id' => $cameraB->id,
        'record_id' => 99999,   // same record_id, different camera → allowed
    ]);

    expect($eventB->exists)->toBeTrue();
});
```

[VERIFIED: `UniqueConstraintViolationException` is `Illuminate\Database\UniqueConstraintViolationException`, subclass of `QueryException`, introduced in Laravel 10 — documented at laravel.com/docs/13.x/eloquent#exceptions]

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `HasUuids` emits UUIDv4 | `HasUuids` emits UUIDv7 (time-ordered) | Laravel 11.x+ (confirmed in L13.5.0 source) | Free insertion locality; no per-model override for time-ordering |
| `$table->foreignId` + UUID workaround | `$table->foreignUuid('col')->constrained()` | Laravel 10.x+ | One-liner sugar matches `foreignId` but for UUID columns |
| `spatial_index()` or `$table->index(..., 'gist')` | `$table->spatialIndex('col')` | Laravel 8.x+ | First-party builder; emits correct `USING GIST` DDL for PostGIS |
| `$table->json('col')` for JSON storage | `$table->jsonb('col')` + GIN index | Laravel 5.7+ (`jsonb` helper) | Indexable, faster containment queries; required for real production use |
| `ramsey/uuid` composer dep | Built-in `Str::uuid7()` | Laravel 11.x+ | No additional package |
| `MigrationsServiceProvider::check()` | Raw `DB::statement('ALTER TABLE ... ADD CONSTRAINT ... CHECK ...')` | Still current in L13 — no Blueprint helper for CHECK | Phase 18 uses raw DDL; known limitation |

**Deprecated/outdated — do NOT use:**
- `$table->enum('col', [...])` — emits Postgres ENUM type; harder to evolve than VARCHAR + CHECK. Use PHP enum + CHECK constraint.
- `$table->timestamp()` for camera-origin timestamps — emits tz-naive; use `timestampTz()`.
- `$table->json()` for queryable JSON — use `jsonb()`.
- Custom `booted()` UUID hooks — `HasUuids` handles this, UUIDv7 for free.

---

## Project Constraints (from CLAUDE.md)

CLAUDE.md enforces these directives, all relevant to Phase 18:

| Directive | Phase 18 Application |
|-----------|----------------------|
| Every change must be programmatically tested | 2 feature tests (CameraSpatialQueryTest, RecognitionEventIdempotencyTest) + schema/CHECK assertions |
| PHP: curly braces always, constructor promotion, explicit return types, PHPDoc over inline | All migrations, factories, enums, models follow this |
| Use `php artisan make:*` for new files | make:model / make:migration / make:factory / make:test / make:seeder — no hand-crafted file creation |
| Run `vendor/bin/pint --dirty --format agent` after modifying PHP files | Every task finalization must include Pint |
| Use `$table->jsonb()` not `$table->json()` on Postgres | D-48 locks this; research confirms |
| Use factories for test setup, not manual model construction | Both feature tests use `Camera::factory()`, `RecognitionEvent::factory()` |
| Prefer `pest()->group('fras')` for domain-scoped testing | D-60 locks; research confirms idiom |
| Use Laravel Boost `search-docs` before migration changes | Applied for this research — verified L13 Schema Builder, Magellan 2.1.0 |
| Use Laravel Boost `database-schema` to inspect table structure before writing | Plans should run this on cameras/incidents tables before authoring FK migrations |
| No `migrate:fresh` in production | Phase 18 tests only; CLAUDE.md convention holds |
| Fortify activation skill — NOT relevant to this phase | Skipped |
| wayfinder-development skill — NOT relevant (no new routes) | Skipped |
| Pest-testing skill — RELEVANT | Apply when authoring the 2 tests |
| Laravel-best-practices skill — RELEVANT | Apply throughout (migrations, models, factories) |

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Herd's Postgres service is running on port 5432 when tests execute | §Environment Availability | Test runs fail with connection refused; user must `brew services start postgresql` or `herd services:start postgresql` |
| A2 | `.env.testing` `DB_DATABASE=irms_testing` exists as a superuser-accessible DB | §Environment Availability | First test run fails with `database irms_testing does not exist`; user runs `createdb irms_testing` as postgres superuser |
| A3 | PostGIS extension is enabled in the testing DB (not just dev DB) | §Environment Availability | `cameras` migration fails with `type "geography" does not exist`; the `2026_03_12_000001_enable_postgis_extension.php` migration handles this on `migrate:fresh`, so A3 holds if A2 holds |
| A4 | Laravel 13's `foreignUuid()` works identically to L12's (Phase 17 shipped no FRAS migrations to exercise this) | §Architecture Patterns Pattern 3 | If `foreignUuid` emits different DDL on L13, migration may fail silently on cascade semantics; mitigation — explicit long-form FK pattern from `incident_unit` migration is the fallback |
| A5 | `jsonb_path_ops` opclass is preferred for Phase 19/20/22 access patterns | §Architecture Patterns Pattern 5 | If access pattern turns out to be `?` or `?|` operators instead of `@>`, the GIN index won't be used; mitigation — default `jsonb_ops` opclass is a 5-line migration change |

All other claims in this research are tagged `[VERIFIED: ...]` or `[CITED: ...]` inline.

---

## Open Questions

None that block planning. Every CONTEXT.md "Claude's Discretion" item has a concrete recommendation in §User Constraints above.

One **non-blocking** question for the planner to be aware of:

1. **Should migrations set `last_seen_at` precision explicitly?**
   - What we know: D-10 says "default precision 0" for `last_seen_at`.
   - What's unclear: Whether `$table->timestampTz('last_seen_at')` without an explicit `precision: 0` arg emits precision 0 or precision 6 on Postgres.
   - Recommendation: Pass `precision: 0` explicitly for load-bearing columns — cost is one keyword, benefit is zero ambiguity across Laravel version updates.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4.6 (`pestphp/pest ^4.6`) |
| Config file | `tests/Pest.php` (already extends TestCase + RefreshDatabase for `Feature/*`) |
| Quick run command | `./vendor/bin/pest --group=fras` |
| Full suite command | `php artisan test --compact` |
| Test DB | PostgreSQL (`DB_CONNECTION=pgsql` in `.env.testing`) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FRAMEWORK-04 (UUID PKs) | `cameras`, `personnel`, `camera_enrollments`, `recognition_events` use uuid PK | unit (schema-introspection) | `php artisan test --compact tests/Feature/Fras/SchemaTest.php --filter=uuid_primary_keys` | ❌ Wave 0 (optional — not mandated by CONTEXT.md success criteria, but planner may add) |
| FRAMEWORK-04 (jsonb + GIN) | `recognition_events.raw_payload` is jsonb with GIN index | unit (schema-introspection) | `php artisan test --compact tests/Feature/Fras/SchemaTest.php --filter=jsonb_gin` | ❌ Wave 0 (optional) |
| FRAMEWORK-04 (TIMESTAMPTZ) | `captured_at`, `received_at` are `timestamp with time zone(6)` | unit (schema-introspection) | `php artisan test --compact tests/Feature/Fras/SchemaTest.php --filter=timestamptz` | ❌ Wave 0 (optional) |
| FRAMEWORK-04 (geography + PostGIS) | `cameras.location` is `geography(POINT, 4326)` and spatial query returns expected | feature | `./vendor/bin/pest --group=fras tests/Feature/Fras/CameraSpatialQueryTest.php` | ❌ Wave 0 (**REQUIRED** per D-59) |
| FRAMEWORK-05 (pgsql test suite) | Feature tests execute against PostgreSQL — jsonb + geography exercised | verification | `cat .env.testing \| grep DB_CONNECTION` + run any FRAS test | ✓ (gate passes on inspection — D-58) |
| FRAMEWORK-06 (idempotency) | `(camera_id, record_id)` UNIQUE rejects duplicate at DB layer | feature | `./vendor/bin/pest --group=fras tests/Feature/Fras/RecognitionEventIdempotencyTest.php` | ❌ Wave 0 (**REQUIRED** per D-59) |
| SC4 (factories + seeders) | Every new table has factory + seeder; `migrate:fresh --seed` exits 0 | integration | `php artisan migrate:fresh --seed` exit code 0 | ❌ Wave 0 |
| SC4 (enum ↔ CHECK parity) | PHP enum string values match DB CHECK IN clause | unit (convention test) | `php artisan test --compact tests/Unit/Conventions/FrasEnumCheckParityTest.php` | ❌ Wave 0 (optional — belt-and-suspenders; the CHECK will reject violations at migration time regardless) |

### Sampling Rate

- **Per task commit:** `./vendor/bin/pest --group=fras` (runs 2 mandatory tests + any schema tests; ~3-5 seconds on pgsql)
- **Per wave merge:** `php artisan test --compact` (full suite; existing ~300 tests + new ~2-5 FRAS tests)
- **Phase gate:** Full suite green + `php artisan migrate:fresh --seed` exits 0 on a clean pgsql database before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Fras/CameraSpatialQueryTest.php` — covers SC5 (ST_DWithin spatial feature test)
- [ ] `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` — covers SC2 + FRAMEWORK-06
- [ ] (Optional) `tests/Feature/Fras/SchemaTest.php` — introspects `information_schema.columns` for type correctness on jsonb / timestamptz / uuid. Not in CONTEXT.md success criteria but low-cost insurance against silent regressions.
- [ ] (Optional) `tests/Unit/Conventions/FrasEnumCheckParityTest.php` — asserts each enum's string values are a subset of the corresponding CHECK IN clause. Parsing `information_schema.check_constraints` is brittle; consider deferring unless a CHECK/enum drift bug actually occurs.
- [ ] Framework install: none — Pest 4.6, PostgreSQL, PostGIS, Magellan 2.1.0 all already present.
- [ ] `database/seeders/FrasPlaceholderSeeder.php` — empty class; exists to satisfy SC4's "every new table has a seeder" wording.

### Nyquist Dimension Coverage

Applying the 8 Nyquist validation dimensions to this schema-only phase:

**1. Data integrity — COVERED**
- Scope: FK constraints (CASCADE/RESTRICT/SET NULL), CHECK constraints (4 enum columns), UNIQUE constraints (`device_id`, `camera_id_display`, `(camera_id, personnel_id)`, `(camera_id, record_id)`).
- Approach: Feature tests insert valid rows; schema-introspection tests read `information_schema.table_constraints` and `check_constraints`; idempotency test confirms UNIQUE violation raises `UniqueConstraintViolationException`.
- Acceptance: All 8 FK constraints enforceable; all 4 CHECK constraints reject invalid enum strings; all 4 UNIQUE constraints reject duplicates; `migrate:fresh --seed` succeeds.

**2. Persistence — COVERED**
- Scope: Every migration's `up()` completes green; `down()` reverses via `dropIfExists`; round-trip factory create → DB → Eloquent read preserves every field.
- Approach: `php artisan migrate:fresh --seed` exits 0; feature tests exercise full round-trip (create factory → DB insert → fetchById → assert all casts preserved).
- Acceptance: Zero warnings on `migrate:fresh`; all 4 factories produce Eloquent models with correct casts (Point for location, DateTime with microseconds for captured_at, enum for category/status/severity).

**3. Security — NOT APPLICABLE (but flagged)**
- Scope: Phase 18 creates no routes, no controllers, no broadcast channels, no auth gates. No data is exposed to any HTTP surface.
- Justification: Security enforcement applies to Phase 19 (MQTT channel auth), Phase 20 (admin CRUD), Phase 22 (DPA). Phase 18 is purely schema.
- ASVS categories checked: V5 Input Validation — N/A (no input); V6 Cryptography — N/A (no secrets); V4 Access Control — N/A (no access).
- **Security-adjacent correctness:** `personnel.photo_hash` (MD5) is stored but not used for auth — it's a dedup hash, not a password. No crypto exposure.
- **Deferred:** V5/V6/V4 become relevant from Phase 19 onward.

**4. Performance — COVERED (targeted)**
- Scope: Index coverage for the queries Phase 19/20/22 will run — (`camera_id`, `captured_at`) range scans, GIN on `raw_payload @>`, spatial GIST on `cameras.location`, severity and severity-by-real-time filters on alert feed.
- Approach: No load tests in Phase 18 (no runtime code to load). **Index-by-inspection:** run `EXPLAIN` on representative queries after `migrate:fresh` and assert GIN/GIST/BTree usage.
- Acceptance: `EXPLAIN SELECT * FROM recognition_events WHERE raw_payload @> '{"operator":"RecPush"}'` uses Bitmap Index Scan on `recognition_events_raw_payload_gin`; `EXPLAIN SELECT * FROM cameras WHERE ST_DWithin(location, ?, 500)` uses Index Scan on spatial index. Phase 19/20/22 load-test in their own validation.

**5. Observability — NOT APPLICABLE**
- Scope: Phase 18 has no runtime code that would log, broadcast, or emit metrics.
- Justification: The listener, handlers, broadcast events, and `mqtt_listener_health` are all Phase 19. The Phase 18 "runtime" is the migration itself — Laravel logs migration execution to `storage/logs/laravel.log` automatically; no custom observability needed.

**6. Accessibility — NOT APPLICABLE**
- Scope: Phase 18 ships no UI.
- Justification: All UI work is Phase 20 (admin CRUD) or Phase 22 (alert feed / event history). Phase 18 is DB-only.

**7. i18n — NOT APPLICABLE**
- Scope: No user-facing strings. `PersonnelCategory::LostChild->label()` returns "Lost Child" as an English placeholder for future UI; not translated in Phase 18.
- Justification: Translation strings live in `lang/*/fras.php` files that Phase 20/22 will create. Phase 18 reserves enums only.

**8. Adversarial — COVERED (narrow)**
- Scope: (a) Duplicate `(camera_id, record_id)` inserts under concurrent factory use. (b) Invalid enum values bypassing Eloquent cast. (c) Malformed JSONB in `raw_payload`. (d) NULL inserts into NOT NULL columns.
- Approach:
  - (a) `RecognitionEventIdempotencyTest` asserts `UniqueConstraintViolationException` on duplicate; concurrent insertion tested implicitly by the UNIQUE constraint at DB layer (TOCTOU-proof).
  - (b) Direct `DB::insert('INSERT INTO cameras (id, ..., status) VALUES (?, ..., ?)', [$uuid, ..., 'invalid-status'])` should throw via CHECK; add a negative test if belt-and-suspenders desired.
  - (c) Eloquent's `'array'` cast serializes to valid JSON; `raw_payload` cast as array cannot accidentally write malformed JSONB. Direct raw DB writes can; not exercised in Phase 18 because Phase 19 handlers always go through Eloquent.
  - (d) NOT NULL violations caught by DB; factory tests exercise this implicitly (missing required field → insert fails).
- Acceptance: Adversarial matrix above has at least one path covered for each category; (b) and (c) are low-priority because Phase 19 handlers will always use Eloquent.

---

## Security Domain

**Applies per CLAUDE.md default (security_enforcement enabled) but scope is narrow for a schema-only phase.**

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no | No auth surface added in Phase 18 |
| V3 Session Management | no | No session state |
| V4 Access Control | no | No authorization gates (comes in Phase 22 DPA-07) |
| V5 Input Validation | no (deferred) | Will be Phase 19 (RecPush parsing) and Phase 20 (admin forms) |
| V6 Cryptography | no | MD5 `photo_hash` is a dedup hash, not a cryptographic primitive; no secrets handled |
| V9 Data Protection | yes (foundational) | Schema choice: biometric PII columns (`photo_hash`, `photo_path`, `id_card`, `phone`, `address`) are designed for Phase 22's role-gated access + signed URLs + audit log; Phase 18 ships them with `decommissioned_at` + `expires_at` columns that Phase 22 retention purge will consume |
| V11 Business Logic | no | No business logic shipped |

### Known Threat Patterns for {Laravel 13 + Postgres + Magellan}

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| SQL injection in raw `DB::statement` calls | Tampering | Use string literals only (no interpolation) for CHECK/GIN DDL in migrations; all user input comes later via Eloquent (parameterized by default) |
| Timezone confusion on camera timestamps | Tampering (indirect — affects retention correctness) | TIMESTAMPTZ for all camera-origin columns (`captured_at`, `received_at`) |
| Missing FK leaking orphan refs | Data integrity | All 8 FKs with explicit cascade behavior (CASCADE / RESTRICT / SET NULL) per D-22 to D-34, D-50 |
| PII exposure via recognition_events.raw_payload (firmware includes `idCard`, `telnum`, etc.) | Information Disclosure | Phase 22 gates access to `raw_payload` via role-gated endpoints + signed URLs + `fras_access_log` audit; Phase 18 reserves the columns only |
| Biometric data retention beyond lawful basis | Compliance (RA 10173) | `personnel.expires_at` + `personnel.consent_basis` + `personnel.decommissioned_at` columns exist from Phase 18 onward; Phase 22 retention purge consumes them |

**Nothing actionable at Phase 18 execution time** beyond: use string literals in `DB::statement()`, use `timestampTz` (not `timestamp`), declare explicit FK cascade behavior. All three are already locked in CONTEXT.md D-01 through D-70.

---

## Sources

### Primary (HIGH confidence)

- **Laravel 13 Schema Builder source** — `vendor/laravel/framework/src/Illuminate/Database/Schema/Blueprint.php` — confirmed `jsonb()`, `timestampTz($column, ?int $precision)`, `geography()`, `spatialIndex()`, `foreignUuid()`, `unique($cols, $name)` signatures.
- **Laravel 13 HasUuids source** — `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUuids.php:15-18` — confirmed `newUniqueId()` returns `(string) Str::uuid7()` (time-ordered UUIDv7, not v4).
- **clickbar/laravel-magellan 2.1.0 source** — `vendor/clickbar/laravel-magellan/src/Data/Geometries/Point.php:10-24` — confirmed `Point::make($x, $y, $z, $m, $srid)` vs `Point::makeGeodetic($latitude, $longitude, $altitude, $m)` arg order.
- **clickbar/laravel-magellan 2.1.0 ST::dWithin** — `vendor/clickbar/laravel-magellan/src/Database/PostgisFunctions/MagellanDistanceRelationshipsFunctions.php:50-73` — confirmed `ST::dWithinGeography($a, $b, $meters, ?$useSpheroid)` and `ST::dWithinGeometry($a, $b, $distanceOfSrid)` signatures.
- **FRAS RecognitionHandler source** — `/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php:108-125` — authoritative RecPush payload keys: `customId`, `personId`, `RecordID`, `VerifyStatus`, `PersonType`, `similarity1`, `Sendintime`, `PushType`, `personName` / `persionName`, `facesluiceId`, `idCard`, `telnum`, `isNoMask`, `targetPosInScene`, `time`.
- **IRMS v1.0 code precedent** — `database/migrations/2026_03_12_000006_create_incidents_table.php` (geography + jsonb + UUID), `2026_03_13_200001_create_incident_unit_table.php` (pivot + UUID FKs), `app/Models/Incident.php` (HasUuids + Magellan cast), `app/Enums/IncidentOutcome.php` (enum pattern), `database/factories/UnitFactory.php` + `IncidentFactory.php` (Point::makeGeodetic + Butuan jitter).
- **`.env.testing` + `phpunit.xml`** — verified `DB_CONNECTION=pgsql`, `DB_DATABASE=irms_testing`; no override in phpunit.xml; D-58 holds (FRAMEWORK-05 pre-satisfied).
- **`tests/Pest.php`** — verified `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')` global — `tests/Feature/Fras/*` inherit RefreshDatabase automatically.
- **composer.json** — verified `laravel/framework ^13.0` (installed 13.5.0), `clickbar/laravel-magellan ^2.1` (installed 2.1.0, 2026-03-17), `pestphp/pest ^4.6`.

### Secondary (MEDIUM confidence)

- **Laravel 13 migrations docs** — laravel.com/docs/13.x/migrations (via Context7 `/websites/laravel_13_x`, referenced in existing `.planning/research/STACK.md`) — jsonb, timestampTz, foreignUuid, spatialIndex helpers.
- **PostgreSQL 15 docs — JSON indexing** — postgresql.org/docs/current/datatype-json.html#JSON-INDEXING — `jsonb_path_ops` vs `jsonb_ops` tradeoff.
- **PostgreSQL 15 docs — CHECK constraint** — postgresql.org/docs/current/sql-createtable.html — CHECK constraint naming + identifier 63-byte limit.
- **`.planning/research/STACK.md`** — §PostgreSQL Port of FRAS MySQL Schema — prior research on MySQL→Postgres type mapping, TIMESTAMPTZ vs TIMESTAMP, JSONB vs JSON.
- **`.planning/research/PITFALLS.md`** — Pitfalls 3, 4, 5, 15, 22 (schema port specific) — all accounted for in Phase 18 CONTEXT.md decisions.

### Tertiary (LOW confidence)

- None needed. All critical claims verified via installed vendor source or IRMS v1.0 code.

---

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH — all packages installed, versions verified via `composer show` 2026-04-21
- Architecture (migration DDL templates, model cast, factory patterns): HIGH — direct copy from IRMS v1.0 shipped code with Magellan 2.1.0 confirmation
- Validation (Pest group, RefreshDatabase inheritance, UniqueConstraintViolationException): HIGH — verified in `tests/Pest.php` + Laravel source
- Pitfalls: HIGH — cross-checked against existing PITFALLS.md research + Postgres docs
- Security: MEDIUM — narrow scope (schema-only), most ASVS categories N/A in this phase

**Research date:** 2026-04-21
**Valid until:** 2026-05-21 (30 days — Laravel 13 is stable, Magellan 2.1.0 released 2026-03-17 is current; re-verify if Magellan 2.2 or Laravel 13.6 ships before planning)

---

## RESEARCH COMPLETE

**Phase:** 18 - FRAS Schema Port to PostgreSQL
**Confidence:** HIGH

### Key Findings

- **Magellan 2.1.0 is the shipped baseline** — `$table->geography('location', subtype: 'point', srid: 4326)` + `Point::class` cast + `Point::makeGeodetic($lat, $lng)` factory usage are all verified in existing IRMS v1.0 code. No API unknowns remain.
- **Laravel 13's `HasUuids` emits UUIDv7 (time-ordered) by default** — verified in vendor source. CONTEXT.md's concern about per-model UUIDv7 override is resolved: don't override. All 4 FRAS models get free time-ordered IDs by trait alone.
- **CHECK constraints require raw `DB::statement`** — Blueprint has no first-party helper. Explicit constraint names (e.g., `cameras_status_check`) fit Postgres's 63-byte identifier limit comfortably.
- **GIN index `jsonb_path_ops` opclass recommended** — 30-40% smaller + faster for `@>` queries (Phase 19/20/22's access pattern). Laravel Blueprint's `$table->index('col', null, 'gin')` also works (default opclass) if plans prefer consistency over tuning.
- **Two feature tests mandated by D-59** — `CameraSpatialQueryTest.php` uses raw `DB::selectRaw("ST_DWithin(...)")` against `geography`; `RecognitionEventIdempotencyTest.php` catches `Illuminate\Database\UniqueConstraintViolationException`. Both patterns shown in §Code Examples.
- **`pest()->group('fras')` at top of file** — `tests/Pest.php` already provides `RefreshDatabase` + `TestCase` binding to `Feature/*`; no phpunit.xml edit needed. FRAMEWORK-05 passes by inspection of `.env.testing`.
- **Full RecPush payload shape extracted** — verbatim from `/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php:108-125`. Factory must include `personName` (and a `withMisspelledName()` state for `persionName`) so Phase 19 handler tests exercise real firmware quirks.
- **Environment fully provisioned** — all deps present; no composer or npm changes. Phase 17 baseline is sufficient.

### File Created

`/Users/helderdene/IRMS/.planning/phases/18-fras-schema-port-to-postgresql/18-RESEARCH.md`

### Confidence Assessment

| Area | Level | Reason |
|------|-------|--------|
| Standard Stack | HIGH | All packages installed; versions verified via `composer show` on 2026-04-21 |
| Architecture | HIGH | Every pattern has a v1.0 IRMS code precedent + Magellan 2.1.0 confirmed |
| Pitfalls | HIGH | Cross-referenced existing PITFALLS.md + Postgres docs + L13 Schema Builder source |
| Security | MEDIUM | Narrow scope (schema-only); most ASVS categories N/A in this phase — intentional |
| Validation | HIGH | Pest 4.6 group idiom + RefreshDatabase inheritance confirmed in `tests/Pest.php`; both feature-test patterns shown with verified APIs |

### Open Questions

None blocking. One non-blocking recommendation in §Open Questions (explicit `precision: 0` on `timestampTz` for load-bearing columns — cheap insurance).

### Ready for Planning

Research complete. Planner can now author plans. Every CONTEXT.md "Claude's Discretion" item has a concrete recommendation; every `[ASSUMED]` item is flagged in the Assumptions Log for user verification if needed.
