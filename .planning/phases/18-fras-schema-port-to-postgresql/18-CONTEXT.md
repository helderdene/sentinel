# Phase 18: FRAS Schema Port to PostgreSQL - Context

**Gathered:** 2026-04-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Feature-free schema port. Four FRAS tables (`cameras`, `personnel`, `camera_enrollments`, `recognition_events`) land in IRMS's PostgreSQL database as **empty** tables with IRMS-native types (UUID PKs, JSONB + GIN, TIMESTAMPTZ, Magellan `geography(POINT, 4326)`), forward-compatible columns for Phase 19/20/21/22, factories and (empty) seeders per v1.0 pattern, and the two mandated feature tests (PostGIS `ST_DWithin` spatial test + `(camera_id, record_id)` idempotency test).

**Out of scope:** No controllers, no jobs, no routes, no admin UI, no Eloquent relations beyond FK declarations, no MQTT ingestion, no broadcasting, no photo storage disks, no auto-sequencing logic for `camera_id_display`. Those belong to Phases 19 (MQTT), 20 (Admin CRUD), 21 (IoT bridge), 22 (Alerts/DPA).

The deliverable is: `php artisan migrate:fresh --seed` completes green, Pest group `fras` passes on PostgreSQL, and Phases 19 & 20 can `Camera::create(...)` / `Personnel::create(...)` / `RecognitionEvent::create(...)` without a follow-up migration.

</domain>

<decisions>
## Implementation Decisions

### Primary keys and model pattern

- **D-01:** All 4 FRAS models use `Illuminate\Database\Eloquent\Concerns\HasUuids` trait — matches `Incident` model precedent verbatim. UUIDs auto-generated on create; factories work transparently; Magellan casts unaffected.
- **D-02:** Migration column: `$table->uuid('id')->primary()` (matches `incidents` migration line 15).

### Schema forward-compatibility (Phase 20/21/22 columns land in Phase 18)

The phase goal is "no schema churn" for downstream phases. Phase 18 includes all columns that Phases 19-22 write to.

**`cameras` table:**
- **D-03:** `id` uuid PK (HasUuids)
- **D-04:** `device_id` varchar(64) NOT NULL UNIQUE — MQTT client identifier; **immutable once set** (Phase 20 form enforces app-level; no update route). Index exists via UNIQUE.
- **D-05:** `camera_id_display` varchar(10) UNIQUE NULLABLE — reserves the `CAM-01`/`CAM-02` human-readable column Phase 20 will populate. Nullable because Phase 18 factories can't sequence yet (Phase 20 `AdminCameraController::store()` adds the regex-based auto-sequencing logic, mirroring `UnitFactory::autoId()`).
- **D-06:** `name` varchar(100) NOT NULL — matches Unit.callsign required-ness.
- **D-07:** `location_label` varchar(150) NOT NULL — human-readable address. Both name and location_label required to prevent unlabeled cameras polluting the dispatch map.
- **D-08:** `location` geography(POINT, 4326) NULLABLE — via `$table->geography('location', subtype: 'point', srid: 4326)->nullable()` matching `incidents.coordinates`. Admin picks on MapLibre picker in Phase 20.
- **D-09:** `status` varchar(20) NOT NULL DEFAULT 'offline' — values `online | offline | degraded`. DB-level `CHECK (status IN ('online','offline','degraded'))`. Replaces FRAS's `is_online` bool. Drives Phase 20 `CameraStatusChanged` broadcast and map marker color.
- **D-10:** `last_seen_at` TIMESTAMPTZ NULLABLE — last heartbeat timestamp (Phase 19 watchdog updates; default precision 0).
- **D-11:** `decommissioned_at` TIMESTAMPTZ NULLABLE — Unit soft-decommission pattern. Admin decommissions instead of hard-delete. `scopeActive()` uses `whereNull('decommissioned_at')`.
- **D-12:** `created_at` / `updated_at` default precision 0.

**`personnel` table:**
- **D-13:** `id` uuid PK (HasUuids)
- **D-14:** `custom_id` varchar(48) UNIQUE NULLABLE — FRAS firmware-side personnel identifier (FRAS uses `custom_id` when sync'd to camera). Nullable because Phase 20 generates on admin create.
- **D-15:** Full FRAS field set: `name` varchar(100) NOT NULL, `gender` smallint nullable (0=male/1=female, keep FRAS tinyint encoding but widen to smallint for Postgres friendliness), `birthday` date nullable, `id_card` varchar(32) nullable, `phone` varchar(32) nullable, `address` varchar(255) nullable, `photo_path` varchar(255) nullable, `photo_hash` varchar(32) nullable (MD5).
- **D-16:** **Replace** FRAS's `person_type` (tinyint 0/1) with `category` varchar(20) NOT NULL DEFAULT 'allow' — PHP enum `PersonnelCategory { Allow, Block, Missing, LostChild }` backed by strings. DB-level `CHECK (category IN ('allow','block','missing','lost_child'))`. Matches Phase 20 SC4.
- **D-17:** `expires_at` TIMESTAMPTZ NULLABLE — Phase 20 SC7 auto-unenroll when past. Phase 18 reserves the column only.
- **D-18:** `consent_basis` TEXT NULLABLE — DPA compliance free-text (Phase 22). Reserves the column.
- **D-19:** `decommissioned_at` TIMESTAMPTZ NULLABLE — soft-delete parity with cameras. Phase 22 DPA retention purge may still hard-delete for right-to-erasure; decommission column is for admin flow.
- **D-20:** `created_at` / `updated_at` default precision 0.

**`camera_enrollments` pivot table:**
- **D-21:** `id` uuid PK (HasUuids)
- **D-22:** `camera_id` uuid NOT NULL FK → cameras, `ON DELETE CASCADE` (enrollment rows are sync state, safe to cascade when a camera is decommissioned and eventually purged).
- **D-23:** `personnel_id` uuid NOT NULL FK → personnel, `ON DELETE CASCADE`.
- **D-24:** `status` varchar(20) NOT NULL DEFAULT 'pending' — PHP enum `CameraEnrollmentStatus { Pending, Syncing, Done, Failed }` backed by strings. DB-level `CHECK (status IN ('pending','syncing','done','failed'))`.
- **D-25:** `enrolled_at` TIMESTAMPTZ NULLABLE — set when `status → done`.
- **D-26:** `photo_hash` varchar(32) NULLABLE — copy of personnel.photo_hash at enrollment time (detects photo drift).
- **D-27:** `last_error` TEXT NULLABLE — free-text error from camera (no varchar cap; camera errors can be long).
- **D-28:** UNIQUE (`camera_id`, `personnel_id`) — one enrollment per camera/personnel pair.
- **D-29:** Indexes: (`camera_id`, `status`), (`personnel_id`, `status`) — match FRAS `add_status_to_camera_enrollments` migration.
- **D-30:** `created_at` / `updated_at` default precision 0.

**`recognition_events` table:**
- **D-31:** `id` uuid PK (HasUuids)
- **D-32:** `camera_id` uuid NOT NULL FK → cameras, `ON DELETE RESTRICT` — preserve recognition history; cameras can't hard-delete when events exist (forces admin to purge events first via DPA Phase 22 retention).
- **D-33:** `personnel_id` uuid NULLABLE FK → personnel, `ON DELETE SET NULL` — a deleted/purged person still has recognition history. Matches FRAS v1.0 choice.
- **D-34:** `incident_id` uuid NULLABLE FK → incidents, `ON DELETE SET NULL` — Phase 21 `FrasIncidentFactory` sets this when a Critical recognition bridges to an IoT-channel Incident. Indexed.
- **D-35:** `record_id` bigint NOT NULL — firmware-assigned record number from RecPush payload. Paired with camera_id for idempotency.
- **D-36:** `custom_id` varchar(100) NULLABLE + INDEX — firmware's own ID string (for reverse lookup).
- **D-37:** `camera_person_id` varchar(100) NULLABLE — firmware's camera-side person ID (FRAS quirk, preserve for debugging).
- **D-38:** `verify_status` smallint NOT NULL — camera-reported 0-3 value (FRAS preserves).
- **D-39:** `person_type` smallint NOT NULL — camera-reported 0/1 at recognition time (snapshot; personnel.category might drift later).
- **D-40:** `similarity` real NOT NULL — 0.0–100.0 face-match score (Postgres `real` is single-precision float).
- **D-41:** `is_real_time` boolean NOT NULL.
- **D-42:** `name_from_camera` varchar(100) NULLABLE, `facesluice_id` varchar(100) NULLABLE, `id_card` varchar(32) NULLABLE, `phone` varchar(32) NULLABLE — camera-reported fields snapshot.
- **D-43:** `is_no_mask` smallint NOT NULL — 0/1/2 mask detection state from firmware.
- **D-44:** `target_bbox` jsonb NULLABLE — `[x1,y1,x2,y2]` face box. **No GIN index** (low-cardinality, not app-queryable).
- **D-45:** `captured_at` TIMESTAMPTZ(6) NOT NULL — **microsecond precision**. Camera-reported timestamp. Timezone = Asia/Manila (CDRRMO config); stored as TIMESTAMPTZ so shifts are explicit.
- **D-46:** `received_at` TIMESTAMPTZ(6) NOT NULL — **microsecond precision**. Server-side Carbon::now() at MQTT ingest time (Phase 19 RecognitionHandler sets this). **NEW column (not in FRAS source)** — distinguishes camera-clock from server-clock. Research-recommended; camera_clock_skew is derivable (received_at - captured_at) so NOT persisted.
- **D-47:** `face_image_path` varchar(255) NULLABLE, `scene_image_path` varchar(255) NULLABLE — local disk paths (Phase 19 writes to private disk).
- **D-48:** `raw_payload` jsonb NOT NULL — full MQTT RecPush JSON. **GIN index** via `$table->index('raw_payload', null, 'gin')` (Laravel builder) or raw `CREATE INDEX ... USING GIN (raw_payload)` in migration.
- **D-49:** `severity` varchar(10) NOT NULL DEFAULT 'info' — values `info | warning | critical`. DB-level `CHECK`. Matches FRAS's `add_acknowledgment_columns` migration. Phase 21 writes on ingest.
- **D-50:** `acknowledged_by` uuid NULLABLE FK → users, `ON DELETE SET NULL` — Phase 22 alert feed ack.
- **D-51:** `acknowledged_at` TIMESTAMPTZ(0) NULLABLE.
- **D-52:** `dismissed_at` TIMESTAMPTZ(0) NULLABLE.
- **D-53:** `created_at` / `updated_at` default precision 0.
- **D-54:** **UNIQUE (`camera_id`, `record_id`)** — FRAMEWORK-06 idempotency constraint. A duplicate RecPush redelivery fails at the DB layer.
- **D-55:** Indexes: (`camera_id`, `captured_at`) for time-range queries per camera, (`person_type`, `verify_status`) for severity filtering, (`severity`) for alert feed, (`is_real_time`, `severity`) for live-feed filtering, single-column index on `incident_id` for incident-join lookups.

### PostGIS + geography type

- **D-56:** Use Magellan's `$table->geography('location', subtype: 'point', srid: 4326)` (as in `incidents.coordinates`). Confirmed present in v1.0 codebase — no new extension enable needed beyond existing `2026_03_12_000001_enable_postgis_extension.php`.
- **D-57:** `cameras.location` gets a GIST spatial index via `$table->spatialIndex('location')` (matches `incidents` migration line 45) for Phase 20 `ST_DWithin` nearby-camera queries and SC5's dedicated feature test.

### Test suite verification (FRAMEWORK-05)

- **D-58:** **FRAMEWORK-05 is already satisfied** by existing `.env.testing: DB_CONNECTION=pgsql`. Pest suite runs on PostgreSQL today. Phase 18 does NOT need to reconfigure phpunit.xml; it only needs to **assert** this is true and add the `fras` Pest group for the two new feature tests.
- **D-59:** Two mandated feature tests live in `tests/Feature/Fras/`:
  - `CameraSpatialQueryTest.php` — exercises `ST_DWithin(cameras.location, incidents.coordinates, 500)` against a seeded camera row. Covers SC5.
  - `RecognitionEventIdempotencyTest.php` — attempts duplicate insert with the same `(camera_id, record_id)` and asserts `Illuminate\Database\UniqueConstraintViolationException`. Covers SC2 + FRAMEWORK-06.
- **D-60:** Each test uses `pest()->group('fras')` (and `uses(RefreshDatabase::class)` via the global Pest config). CI can run `./vendor/bin/pest --group=fras` to verify FRAS-specific coverage separately.

### Factories and seeders

- **D-61:** Factories for all 4 tables following `UnitFactory`/`IncidentFactory` conventions:
  - `CameraFactory` — device_id via `fake()->uuid()` (not production MQTT device but unique); name via fake()->company() + ' Camera'; location_label via fake()->streetAddress(); location via Magellan `Point` around Butuan City center (8.9475° N, 125.5406° E) ± 0.05° jitter; status default 'offline'. `camera_id_display` stays null (Phase 20 sequencing).
  - `PersonnelFactory` — name via fake()->name(); category default 'allow'; other fields nullable defaults.
  - `CameraEnrollmentFactory` — links camera + personnel; status default 'pending'.
  - `RecognitionEventFactory` — **emits a real RecPush-shape `raw_payload`** including both firmware spellings (`personName` AND `persionName`) in an example state, realistic `target_bbox` `[120, 80, 340, 360]`, `captured_at = now()->subSeconds(1)`, `received_at = now()`, `record_id` via `fake()->unique()->numberBetween(1, 2_000_000_000)`. State methods: `->critical()`, `->warning()`, `->info()`, `->withPersonnel(Personnel $p)`, `->blockMatch()`.
- **D-62:** Seeders: factory classes committed; **DatabaseSeeder is NOT modified to seed FRAS tables in Phase 18**. Matches v1.0 pattern (`UnitFactory` exists; units are seeded only through `UnitSeeder` which runs on demand). `php artisan migrate:fresh --seed` completes green because DatabaseSeeder doesn't touch FRAS tables. SC4 is satisfied: "every new table has a factory and a seeder" — seeder = factory-using `FrasPlaceholderSeeder` class that's present but empty-by-default (safe for prod).

### Models

- **D-63:** Four Eloquent models in `app/Models/`: `Camera`, `Personnel`, `CameraEnrollment`, `RecognitionEvent`. Each uses `HasUuids` + `HasFactory`.
- **D-64:** Casts per model:
  - `Camera`: location via Magellan `Point::class`, last_seen_at / decommissioned_at via `'datetime'`, status via `PersonnelCategory::class` — no wait, `CameraStatus::class` (new enum).
  - `Personnel`: category via `PersonnelCategory::class` enum cast, expires_at / decommissioned_at via `'datetime'`, birthday via `'date'`.
  - `CameraEnrollment`: status via `CameraEnrollmentStatus::class` enum cast, enrolled_at via `'datetime'`.
  - `RecognitionEvent`: raw_payload via `'array'`, target_bbox via `'array'`, captured_at / received_at via `'datetime'`, severity via `RecognitionSeverity::class` enum cast, is_real_time via `'boolean'`.
- **D-65:** PHP enums created:
  - `app/Enums/CameraStatus.php` — `Online`, `Offline`, `Degraded`
  - `app/Enums/PersonnelCategory.php` — `Allow`, `Block`, `Missing`, `LostChild`
  - `app/Enums/CameraEnrollmentStatus.php` — `Pending`, `Syncing`, `Done`, `Failed`
  - `app/Enums/RecognitionSeverity.php` — `Info`, `Warning`, `Critical`
- **D-66:** Relations declared but kept minimal — no accessor helpers, no scopes beyond `scopeActive()` (whereNull decommissioned_at) for cameras + personnel. Phase 20 extends.

### Migration file strategy

- **D-67:** **Four new migrations**, one per table, timestamp-dated 2026-04-2x (NOT ported from FRAS migration files verbatim — research STACK.md line 179 rationale: "Keeps `composer run ci:check` clean and lets PostGIS decisions be made per-column").
- **D-68:** Migration order: cameras → personnel → camera_enrollments → recognition_events (FK dependency order).
- **D-69:** `down()` methods use `Schema::dropIfExists()` in reverse order.
- **D-70:** Raw `DB::statement()` used for DB-level CHECK constraints (Laravel builder doesn't expose them directly). One statement per CHECK after `Schema::create`.

### Claude's Discretion

- Exact migration filenames (timestamp prefix + verb): planner picks `2026_04_21_...` consistent with 2026_03_12 v1.0 numbering.
- Enum class location (`app/Enums/` vs `app/Models/Enums/`): follow `app/Enums/IncidentOutcome.php` precedent.
- Whether `camera_id_display` column is `char(10)` vs `varchar(10)`: planner picks based on existing `Unit.id` pattern (check migration).
- GIN index DDL: Laravel Blueprint `$table->index('raw_payload', null, 'gin')` vs raw `DB::statement('CREATE INDEX ... USING GIN(raw_payload jsonb_path_ops)')` — planner picks. `jsonb_path_ops` is smaller + faster for `->>` / `@>` queries if raw DDL used.
- Personnel.custom_id: whether to keep `varchar(48)` (FRAS) or tighten to `varchar(36)` (UUID-width).
- Factory Butuan City coordinate jitter: planner picks exact bounding box from existing barangay polygon seed data.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 18 goal, requirements, success criteria
- `.planning/ROADMAP.md` §Phase 18 — goal, depends-on (Phase 17), 5 success criteria, requirements list
- `.planning/REQUIREMENTS.md` FRAMEWORK-04, FRAMEWORK-05, FRAMEWORK-06 — acceptance criteria

### Downstream schema expectations (what Phase 19/20/21/22 will write)
- `.planning/ROADMAP.md` §Phase 19 — MQTT pipeline writes recognition_events (raw_payload, base64 face crop path, received_at)
- `.planning/ROADMAP.md` §Phase 20 — Camera admin (camera_id_display auto-gen, status broadcast, decommissioned_at), Personnel admin (category, expires_at, consent_basis), enrollment (status enum)
- `.planning/ROADMAP.md` §Phase 21 — recognition_events.incident_id set by FrasIncidentFactory
- `.planning/ROADMAP.md` §Phase 22 — recognition_events.severity + ack columns used by alert feed

### Research — PostgreSQL port decisions
- `.planning/research/STACK.md` §PostgreSQL Port of FRAS MySQL Schema — MySQL → Postgres type mapping table (JSONB, TIMESTAMPTZ, Magellan, CHECK instead of ENUM, no collation, camera-clock skew handling)
- `.planning/research/STACK.md` §Integration Points with Existing IRMS v1.0 Stack — camera pin rendering (Phase 20 rel), broadcast events (Phase 19+20 rel)
- `.planning/research/PITFALLS.md` §Pitfall 1 (feature-free upgrade enforcement) — bias toward minimal surface
- `.planning/research/SUMMARY.md` §Phase 18 — cross-researcher alignment on schema-only scope

### v1.0 conventions and reference code
- `database/migrations/2026_03_12_000001_enable_postgis_extension.php` — PostGIS already enabled, no re-enable in Phase 18
- `database/migrations/2026_03_12_000003_create_units_table.php` — Unit decommission pattern, string PK display ID (Unit uses string PK; cameras use UUID PK + display column)
- `database/migrations/2026_03_12_000006_create_incidents_table.php` — UUID PK pattern, Magellan `geography(POINT, 4326)` column, spatialIndex, jsonb column, HasUuids precedent
- `database/migrations/2026_03_13_200001_create_incident_unit_table.php` — pivot table pattern (camera_enrollments analog)
- `database/factories/UnitFactory.php` — auto-ID sequencing reference (Phase 20 mirrors for camera_id_display; Phase 18 factory does NOT sequence)
- `database/factories/IncidentFactory.php` — factory-with-coordinates pattern for Magellan geography
- `app/Enums/IncidentOutcome.php` — PHP enum backing pattern (string-backed, used in casts)
- `app/Models/Incident.php` — HasUuids + Magellan cast + factory registration pattern
- `.env.testing` — confirms `DB_CONNECTION=pgsql` (FRAMEWORK-05 already satisfied)
- `phpunit.xml` — test env config (no DB_CONNECTION override, so `.env.testing` wins)

### FRAS source schema (reference only — not ported verbatim)
- `/Users/helderdene/fras/database/migrations/2026_04_10_000001_create_cameras_table.php` — FRAS cameras shape
- `/Users/helderdene/fras/database/migrations/2026_04_10_000002_create_personnel_table.php` — FRAS personnel shape
- `/Users/helderdene/fras/database/migrations/2026_04_10_000003_create_recognition_events_table.php` — FRAS recognition_events shape
- `/Users/helderdene/fras/database/migrations/2026_04_10_000004_create_camera_enrollments_table.php` — FRAS camera_enrollments shape (pending status default)
- `/Users/helderdene/fras/database/migrations/2026_04_10_123047_add_status_to_camera_enrollments_table.php` — FRAS added status + per-status indexes
- `/Users/helderdene/fras/database/migrations/2026_04_11_000001_add_acknowledgment_columns_to_recognition_events_table.php` — FRAS severity + acknowledged_by/at + dismissed_at (ported into Phase 18)
- `/Users/helderdene/fras/database/migrations/2026_04_11_071900_add_captured_at_index_to_recognition_events_table.php` — FRAS's captured_at index (ported)

### Carried milestone-level decisions
- `.planning/STATE.md` §Accumulated Context §Decisions "v2.0 roadmap-level decisions (2026-04-21)" — UUID PKs on all 4 FRAS tables, MQTT under Supervisor (not Horizon; Phase 19), mapbox-gl rejected (Phase 20), Inertia v2 retained, severity → priority mapping (Phase 21)

### Carried from Phase 17
- `.planning/phases/17-laravel-12-13-upgrade/17-CONTEXT.md` — Laravel 13 baseline, Magellan + PostGIS confirmed working post-upgrade, Horizon 6 compat verified
- `composer.json` — Laravel 13.5.0 + PHP ^8.3 baseline

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **HasUuids + UUID PK pattern** — `Incident` model uses this verbatim. Copy the trait import, `$keyType` / `$incrementing` are handled by the trait. Factory transparently emits UUIDs.
- **Magellan geography column** — `incidents.coordinates` is `geography(POINT, 4326)` with spatialIndex. Mirror for `cameras.location`. Model cast is `Point::class` (Magellan).
- **Unit decommission pattern** — `Unit.decommissioned_at` + `scopeActive(whereNull)` + form rule that rejects decommissioned references. Mirror for `cameras` + `personnel`.
- **PHP enum backed by string column** — `IncidentOutcome`, `ResourceType`, `AlertSeverity` precedent. Use `enum PersonnelCategory: string { case Allow = 'allow'; ... }`. Cast as `'category' => PersonnelCategory::class` in `$casts`.
- **jsonb + `'array'` cast** — `incidents.vitals` is `jsonb` cast as `'array'`. Reuse for recognition_events.raw_payload and target_bbox.
- **Pest group scoping** — v1.0 doesn't yet use `group()` annotations broadly, but Pest 4 supports it. Phase 18 introduces `group('fras')` convention.
- **.env.testing DB_CONNECTION=pgsql** — verified. No phpunit.xml change needed. FRAMEWORK-05 is a verification gate, not a reconfiguration task.

### Established Patterns
- **UUID PK + `$table->uuid('id')->primary()`** — incidents migration line 15. Never `bigIncrements` for domain tables touching cross-context relations.
- **spatialIndex after Schema::create** — `$table->spatialIndex('coordinates')` inside the closure. PostGIS generates the GIST index.
- **FK with uuid type** — `$table->foreignUuid('incident_id')->constrained()` is the Laravel 13 sugar; alternatively `$table->uuid('incident_id')->nullable()` + explicit `->foreign(...)->references('id')->on('incidents')`. Check v1.0 `incident_unit` migration for the exact idiom.
- **CHECK constraints** — not used in v1.0 schema yet. Phase 18 introduces the first DB-level CHECKs (enum columns). Raw `DB::statement("ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)")` after `Schema::create`. Drop in `down()` via `ALTER TABLE ... DROP CONSTRAINT`.
- **Factory registration** — `HasFactory` trait on model + factory in `database/factories/`. Factory extends `Factory<Model>` with `protected $model = Model::class;` (or inferred from name).
- **Seeder discipline** — `DatabaseSeeder::run()` calls specific seeders; factory-only classes don't belong there. v1.0 has `UnitFactory` without a `UnitSeeder` wired into DatabaseSeeder — FRAS follows same pattern.

### Integration Points
- `database/migrations/2026_04_21_000001_create_cameras_table.php` (NEW)
- `database/migrations/2026_04_21_000002_create_personnel_table.php` (NEW)
- `database/migrations/2026_04_21_000003_create_camera_enrollments_table.php` (NEW)
- `database/migrations/2026_04_21_000004_create_recognition_events_table.php` (NEW)
- `app/Models/Camera.php` (NEW), `app/Models/Personnel.php` (NEW), `app/Models/CameraEnrollment.php` (NEW), `app/Models/RecognitionEvent.php` (NEW)
- `app/Enums/CameraStatus.php` (NEW), `app/Enums/PersonnelCategory.php` (NEW), `app/Enums/CameraEnrollmentStatus.php` (NEW), `app/Enums/RecognitionSeverity.php` (NEW)
- `database/factories/CameraFactory.php` (NEW), `database/factories/PersonnelFactory.php` (NEW), `database/factories/CameraEnrollmentFactory.php` (NEW), `database/factories/RecognitionEventFactory.php` (NEW)
- `database/seeders/FrasPlaceholderSeeder.php` (NEW, empty-by-default) — optional; not wired into DatabaseSeeder
- `tests/Feature/Fras/CameraSpatialQueryTest.php` (NEW)
- `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` (NEW)

### Known touchpoints that DO NOT change in Phase 18
- `config/database.php` — no change (Postgres already default)
- `config/horizon.php` — no change (Phase 19/20 add cameras queue)
- `phpunit.xml` — no change (pgsql already the test DB via .env.testing)
- `bootstrap/app.php` — no change (no new routes, middleware, or providers)
- `config/broadcasting.php` — no change (Phase 19 adds channels)
- `routes/*.php` — no changes

</code_context>

<specifics>
## Specific Ideas

- **"Phase 18 freezes the schema so Phases 19-22 don't migrate."** The single phrase that captures the intent. Every forward-compat column lands here; downstream phases only write data.
- **"camera_clock_skew is derivable — don't persist it."** Research recommended a skew column; we keep only captured_at + received_at and compute skew on demand. Removes a schema-drift risk and matches minimalist instinct.
- **"DB-level CHECK for enum columns is belt-and-suspenders, worth the 4 lines."** Phase 20 jobs, Phase 19 MQTT handlers, and seeders can't accidentally write garbage category/status values. Failure is immediate at insert time, not at read time when a PHP enum cast blows up.
- **"Cameras use UUID PK + separate CAM-XX display column."** Departure from Unit (which uses CAM-style string as the PK itself) — justified because cameras are cross-referenced from recognition_events, camera_enrollments, and potentially MQTT payloads; UUID PK keeps those FKs stable even if display ID sequencing logic changes.
- **"No MQTT code, no admin UI, no photo disks in Phase 18."** Hard boundary. If a question arises about where photo files live, MQTT topic routing, or heartbeat command dispatch — those are Phase 19+ and get noted as "Deferred Ideas" if they surface.
- **"RecognitionEventFactory emits real RecPush shape including `persionName` typo."** Phase 19 handler tests can grab a factory instance, run it through `RecognitionHandler::handle()`, and exercise firmware-quirk parsing without a live broker.

</specifics>

<deferred>
## Deferred Ideas

- **camera_id_display auto-sequencing logic** — the regex `SUBSTRING/CAST` query that extracts the max CAM-XX suffix. Phase 20 `AdminCameraController::store()` implements, mirroring `UnitFactory::autoId()`. Phase 18 reserves the column only.
- **MapLibre camera picker (Mapbox-free)** — Phase 20 SC2 rewrites FRAS's Mapbox picker in MapLibre. Phase 18 only reserves `cameras.location` as geography.
- **Heartbeat watchdog + `CameraStatusChanged` broadcast** — Phase 20 SC3 + `routes/console.php` schedule. Phase 18 has the `status` column and `last_seen_at` but no watchdog.
- **EnrollPersonnelBatch jobs on the `cameras` queue** — Phase 20 SC5. Phase 18 has status column + pivot, no job scaffolding.
- **FRAS photo disks (public + private)** — Phase 19 adds `FRAS_PHOTO_DISK` and `FRAS_EVENT_DISK` to `config/filesystems.php`. Phase 18 only reserves `photo_path` / `face_image_path` / `scene_image_path` as varchar paths.
- **DPA audit log (`fras_access_log`), signed URLs, retention purge** — Phase 22 (milestone gate). Phase 18 does not add the `fras_access_log` table.
- **Email/SMS notifications on Critical recognition** — Phase 22. Phase 18 schema-only.
- **GIN index on `raw_payload->>'cameraDeviceId'`** — research notes this as an optimization for reverse lookup. Deferred; the primary GIN index on `raw_payload` covers the requirement. Add if Phase 20/21 shows the query pattern is hot.
- **`citext` extension for case-insensitive personnel name search** — research notes `ILIKE` is fine at 200-personnel scale. Deferred unless Phase 20 UX shows friction.
- **Photo thumbnail generation** — FRAS has `photo_hash` (MD5) but no thumbnail column. Phase 20 Intervention Image pipeline decides. Phase 18 reserves `photo_path` + `photo_hash` only.
- **Retention-purge column on recognition_events** (DPA right-to-erasure marker) — Phase 22 decides if a soft `purged_at` column is needed or if hard delete + audit log suffices.

</deferred>

---

*Phase: 18-fras-schema-port-to-postgresql*
*Context gathered: 2026-04-21*
