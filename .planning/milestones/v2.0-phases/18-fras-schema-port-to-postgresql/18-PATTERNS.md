# Phase 18: FRAS Schema Port to PostgreSQL - Pattern Map

**Mapped:** 2026-04-21
**Files analyzed:** 19 new files (4 migrations, 4 models, 4 enums, 4 factories, 1 seeder, 2 tests)
**Analogs found:** 19 / 19 (all files have direct in-repo precedents)

## File Classification

| New File | Role | Data Flow | Closest Analog | Match Quality |
|----------|------|-----------|----------------|---------------|
| `database/migrations/2026_04_21_000001_create_cameras_table.php` | migration (schema DDL) | write-once structural | `database/migrations/2026_03_12_000006_create_incidents_table.php` | exact (UUID PK + geography + spatialIndex) |
| `database/migrations/2026_04_21_000002_create_personnel_table.php` | migration (schema DDL) | write-once structural | `database/migrations/2026_03_12_000006_create_incidents_table.php` + decommission from `units` | exact (UUID PK + nullable columns) |
| `database/migrations/2026_04_21_000003_create_camera_enrollments_table.php` | migration (pivot DDL) | write-once structural | `database/migrations/2026_03_13_200001_create_incident_unit_table.php` | exact (pivot + FK + composite unique) |
| `database/migrations/2026_04_21_000004_create_recognition_events_table.php` | migration (schema DDL) | write-once structural | `database/migrations/2026_03_12_000006_create_incidents_table.php` | role-match (adds jsonb + GIN + composite UNIQUE — new territory) |
| `app/Models/Camera.php` | Eloquent model | CRUD + spatial | `app/Models/Incident.php` | exact (HasUuids + Magellan Point cast) |
| `app/Models/Personnel.php` | Eloquent model | CRUD | `app/Models/Unit.php` (decommission scope) + `app/Models/Incident.php` (HasUuids) | role-match |
| `app/Models/CameraEnrollment.php` | Eloquent pivot model | CRUD | `app/Models/IncidentUnit.php` (pivot precedent) + `app/Models/Incident.php` (HasUuids) | role-match (IncidentUnit has no UUID PK; combine patterns) |
| `app/Models/RecognitionEvent.php` | Eloquent model | event-append (insert-heavy) | `app/Models/Incident.php` | exact (HasUuids + jsonb cast + datetime cast) |
| `app/Enums/CameraStatus.php` | PHP backed enum | read-model constant | `app/Enums/IncidentStatus.php` | exact (simple string-backed) |
| `app/Enums/PersonnelCategory.php` | PHP backed enum | read-model constant | `app/Enums/IncidentOutcome.php` | exact (string-backed + label()) |
| `app/Enums/CameraEnrollmentStatus.php` | PHP backed enum | read-model constant | `app/Enums/IncidentStatus.php` | exact |
| `app/Enums/RecognitionSeverity.php` | PHP backed enum | read-model constant | `app/Enums/IncidentStatus.php` | exact |
| `database/factories/CameraFactory.php` | model factory | test-fixture | `database/factories/UnitFactory.php` + `database/factories/IncidentFactory.php` | exact (Magellan + prefix-less) |
| `database/factories/PersonnelFactory.php` | model factory | test-fixture | `database/factories/IncidentFactory.php` | role-match (no geography, no FK) |
| `database/factories/CameraEnrollmentFactory.php` | model factory | test-fixture | `database/factories/IncidentFactory.php` (child FK via factory()) | role-match |
| `database/factories/RecognitionEventFactory.php` | model factory (with states) | test-fixture | `database/factories/IncidentFactory.php` (jsonb fixture pattern) | role-match (adds state methods — new) |
| `database/seeders/FrasPlaceholderSeeder.php` | seeder | write-once fixture | `database/seeders/UnitSeeder.php` (structure) — kept empty body | structural-only |
| `tests/Feature/Fras/CameraSpatialQueryTest.php` | Pest feature test | spatial query assertion | `tests/Feature/Foundation/BarangaySpatialTest.php` | exact (raw DB::select ST_ query) |
| `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` | Pest feature test | DB constraint assertion | `tests/Feature/Foundation/UnitModelTest.php` (factory create pattern) + `toThrow()` idiom | role-match (first UniqueConstraintViolationException test in repo) |

## Pattern Assignments

### `database/migrations/2026_04_21_000001_create_cameras_table.php`

**Role:** migration · **Data flow:** write-once structural
**Analog:** `database/migrations/2026_03_12_000006_create_incidents_table.php`

**Migration skeleton pattern** (analog lines 1-14, 50-58):
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            // ... columns ...
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
```

**UUID PK + geography + spatialIndex pattern** (analog lines 15, 22, 45):
```php
$table->uuid('id')->primary();                                 // line 15
$table->geography('coordinates', subtype: 'point', srid: 4326)->nullable();  // line 22
$table->spatialIndex('coordinates');                           // line 45 (inside closure)
```

**Adaptations for Phase 18 (cameras):**
1. Rename `coordinates` → `location` (D-08).
2. Add raw `DB::statement` CHECK block AFTER `Schema::create` closure (see Shared Pattern: CHECK constraints below) — pattern not present in analog, introduced by Phase 18.
3. `camera_id_display` VARCHAR(10) UNIQUE NULLABLE (D-05) — use `$table->string('camera_id_display', 10)->unique()->nullable()`.
4. `device_id` VARCHAR(64) UNIQUE (D-04) — `$table->string('device_id', 64)->unique()`.
5. `decommissioned_at` nullable timestamptz — mirrors `add_decommissioned_at_to_units_table.php` pattern but lands in initial CREATE.
6. Add `use Illuminate\Support\Facades\DB;` import (needed for CHECK constraint).

---

### `database/migrations/2026_04_21_000002_create_personnel_table.php`

**Role:** migration · **Data flow:** write-once structural
**Analog:** `database/migrations/2026_03_12_000006_create_incidents_table.php` (lines 14-15, 43, 48) for UUID PK + timestamps + indexes.

Same skeleton as above. Relevant adaptation excerpt:

**Column set (D-13 through D-20):**
```php
$table->uuid('id')->primary();
$table->string('custom_id', 48)->unique()->nullable();
$table->string('name', 100);
$table->smallInteger('gender')->nullable();   // Phase 18 widens FRAS tinyint
$table->date('birthday')->nullable();
$table->string('id_card', 32)->nullable();
$table->string('phone', 32)->nullable();
$table->string('address', 255)->nullable();
$table->string('photo_path', 255)->nullable();
$table->string('photo_hash', 32)->nullable();
$table->string('category', 20)->default('allow');
$table->timestampTz('expires_at')->nullable();
$table->text('consent_basis')->nullable();
$table->timestampTz('decommissioned_at')->nullable();
$table->timestamps();
```

**Adaptations:**
1. Append `DB::statement` CHECK for `category IN ('allow','block','missing','lost_child')` (D-16).
2. No spatial column (unlike cameras).
3. No FK dependency on other FRAS tables — this migration runs 2nd.

---

### `database/migrations/2026_04_21_000003_create_camera_enrollments_table.php`

**Role:** migration (pivot) · **Data flow:** write-once structural
**Analog:** `database/migrations/2026_03_13_200001_create_incident_unit_table.php`

**Pivot + FK pattern** (analog lines 14-33):
```php
Schema::create('incident_unit', function (Blueprint $table) {
    $table->uuid('incident_id');
    $table->string('unit_id', 20);
    $table->timestamp('assigned_at');
    // ...
    $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();

    $table->primary(['incident_id', 'unit_id']);

    $table->foreign('incident_id')
        ->references('id')
        ->on('incidents')
        ->cascadeOnDelete();

    $table->foreign('unit_id')
        ->references('id')
        ->on('units')
        ->cascadeOnDelete();
});
```

**Adaptations for Phase 18 (camera_enrollments):**
1. Replace the composite-PK pattern with its own UUID `id` (D-21) and a separate `UNIQUE (camera_id, personnel_id)` (D-28). Use `$table->unique(['camera_id', 'personnel_id']);` inside closure.
2. Use the Laravel 13 `foreignUuid(...)->constrained()->cascadeOnDelete()` sugar instead of the long-form analog (RESEARCH.md Pattern 3 recommends). Analog predates this sugar on UUID FKs.
3. Add `status` VARCHAR(20) default `'pending'` + DB::statement CHECK (D-24).
4. Add composite indexes `(camera_id, status)` and `(personnel_id, status)` per D-29: `$table->index(['camera_id', 'status']);` / `$table->index(['personnel_id', 'status']);`.
5. Add `$table->timestamps();` — analog uses only `assigned_at` instead.

---

### `database/migrations/2026_04_21_000004_create_recognition_events_table.php`

**Role:** migration · **Data flow:** write-once structural
**Analog:** `database/migrations/2026_03_12_000006_create_incidents_table.php` (lines 15, 22, 38, 42, 45-49)

**jsonb + indexes pattern** (analog lines 15, 38, 46-49):
```php
$table->uuid('id')->primary();              // line 15
$table->jsonb('vitals')->nullable();        // line 38
$table->foreignId('created_by')->nullable()->constrained('users');  // line 42

$table->spatialIndex('coordinates');        // line 45
$table->index('priority');                  // line 46
$table->index('status');                    // line 47
$table->index('created_at');                // line 48
```

**Adaptations for Phase 18 (recognition_events) — most extensive new territory:**
1. UUID FK cascades use mixed semantics (D-32 RESTRICT, D-33/34/50 SET NULL). Use:
   ```php
   $table->foreignUuid('camera_id')->constrained()->restrictOnDelete();
   $table->foreignUuid('personnel_id')->nullable()->constrained()->nullOnDelete();
   $table->foreignUuid('incident_id')->nullable()->constrained()->nullOnDelete();
   $table->foreignUuid('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
   ```
2. Microsecond-precision timestamps (D-45, D-46):
   ```php
   $table->timestampTz('captured_at', precision: 6);
   $table->timestampTz('received_at', precision: 6);
   ```
3. Two jsonb columns: `raw_payload` NOT NULL + `target_bbox` nullable (D-44, D-48):
   ```php
   $table->jsonb('raw_payload');
   $table->jsonb('target_bbox')->nullable();
   ```
4. Composite UNIQUE for idempotency (D-54): `$table->unique(['camera_id', 'record_id']);` — names to `recognition_events_camera_id_record_id_unique` automatically.
5. GIN index on `raw_payload` — NOT supported by Blueprint in the `jsonb_path_ops` form. Use raw DDL AFTER `Schema::create`:
   ```php
   DB::statement('CREATE INDEX recognition_events_raw_payload_gin_idx ON recognition_events USING GIN (raw_payload jsonb_path_ops)');
   ```
6. CHECK for `severity IN ('info','warning','critical')` (D-49).
7. All D-55 indexes: `$table->index(['camera_id', 'captured_at']);`, `$table->index(['person_type', 'verify_status']);`, `$table->index('severity');`, `$table->index(['is_real_time', 'severity']);`, `$table->index('incident_id');`.
8. `down()` — `Schema::dropIfExists` drops the GIN + CHECK automatically; no manual `DROP INDEX`/`DROP CONSTRAINT` needed.

---

### `app/Models/Camera.php`

**Role:** Eloquent model · **Data flow:** CRUD + spatial
**Analog:** `app/Models/Incident.php`

**HasUuids + HasFactory pattern** (analog lines 1-21):
```php
<?php

namespace App\Models;

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// ...

class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory, HasUuids;
```

**Casts with Magellan + enum** (analog lines 66-85):
```php
protected function casts(): array
{
    return [
        'coordinates' => Point::class,
        'priority' => IncidentPriority::class,
        'status' => IncidentStatus::class,
        'channel' => IncidentChannel::class,
        'vitals' => 'array',
        'assessment_tags' => 'array',
        'dispatched_at' => 'datetime',
        // ...
    ];
}
```

**Coordinates serialization for frontend** — NOT required for Phase 18 (no JSON response route consumes cameras). Skip the `toArray()` override from `Incident.php:147-159` unless follow-up phase needs it. CONTEXT.md D-66 says "no accessor helpers."

**Adaptations for Phase 18 (Camera):**
1. Substitute `IncidentFactory` → `CameraFactory` in the HasFactory docblock.
2. `coordinates` → `location` field name.
3. Enum list: only `status => CameraStatus::class` (D-64).
4. Datetime casts: `last_seen_at`, `decommissioned_at`.
5. Add `scopeActive(Builder $query): Builder` mirroring `Unit::scopeActive` (see Shared Pattern).

---

### `app/Models/Personnel.php`

**Role:** Eloquent model · **Data flow:** CRUD
**Analog:** `app/Models/Unit.php` (for `scopeActive`) + `app/Models/Incident.php` (for HasUuids/casts)

**scopeActive pattern** (`app/Models/Unit.php:64-67`):
```php
public function scopeActive(Builder $query): Builder
{
    return $query->whereNull('decommissioned_at');
}
```

Note: `Unit` does NOT use `HasUuids` (it has a string PK, see `Unit.php:20-22`). Phase 18 `Personnel` uses `HasUuids` like `Incident`; take the decommission scope from `Unit` but the UUID trait from `Incident`.

**Adaptations:**
1. `use HasFactory, HasUuids;` (from Incident).
2. `scopeActive` method body copied from Unit verbatim.
3. Casts per D-64: `category => PersonnelCategory::class`, `expires_at/decommissioned_at => 'datetime'`, `birthday => 'date'`.
4. No geography column — no Point cast, no `toArray()` override.

---

### `app/Models/CameraEnrollment.php`

**Role:** Eloquent model (pivot-as-table) · **Data flow:** CRUD
**Analog:** `app/Models/IncidentUnit.php` (pivot shape) blended with `app/Models/Incident.php` (HasUuids)

**IncidentUnit pivot pattern** (`app/Models/IncidentUnit.php:1-34`):
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class IncidentUnit extends Pivot
{
    public $incrementing = false;

    protected $table = 'incident_unit';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            // ...
        ];
    }
}
```

**Adaptations for Phase 18 (CameraEnrollment):**
1. Phase 18 table has a UUID PK and its own timestamps — extend plain `Model` (not `Pivot`), same as a standalone model. Do NOT copy the `extends Pivot` choice — it's wrong for this row-has-id pivot.
2. `use HasFactory, HasUuids;` (from Incident).
3. Casts per D-64: `status => CameraEnrollmentStatus::class`, `enrolled_at => 'datetime'`.
4. No `$table` override needed — `camera_enrollments` is the default for `CameraEnrollment`.
5. `public $timestamps` stays TRUE (default) — `timestamps()` is present on the migration (D-30).

---

### `app/Models/RecognitionEvent.php`

**Role:** Eloquent model · **Data flow:** event-append (insert-heavy, read-heavy)
**Analog:** `app/Models/Incident.php`

Same import + HasUuids pattern as Camera (see above).

**Adaptations per D-64:**
1. Casts:
   ```php
   return [
       'raw_payload' => 'array',
       'target_bbox' => 'array',
       'captured_at' => 'datetime',
       'received_at' => 'datetime',
       'acknowledged_at' => 'datetime',
       'dismissed_at' => 'datetime',
       'severity' => RecognitionSeverity::class,
       'is_real_time' => 'boolean',
       'similarity' => 'float',
       'verify_status' => 'integer',
       'person_type' => 'integer',
       'is_no_mask' => 'integer',
       'gender' => 'integer',
   ];
   ```
2. Relations: `camera()`, `personnel()`, `incident()`, `acknowledgedBy()` as basic `belongsTo` — **minimal per D-66**; no scopes beyond defaults.
3. No `booted()` hook (Incident's `generateIncidentNumber` at lines 90-97 has no equivalent; firmware supplies `record_id`).

---

### `app/Enums/CameraStatus.php` (and the 3 other enums)

**Role:** backed enum · **Data flow:** read-model constant
**Analog:** `app/Enums/IncidentStatus.php` (simple) and `app/Enums/IncidentOutcome.php` (with `label()` helper)

**Simple backed-string pattern** (`app/Enums/IncidentStatus.php:1-15`):
```php
<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Pending = 'PENDING';
    case Triaged = 'TRIAGED';
    // ...
}
```

**Enum with label() + helper predicate** (`app/Enums/IncidentOutcome.php:16-36`):
```php
public function label(): string
{
    return match ($this) {
        self::TreatedOnScene => 'Treated on Scene',
        // ...
    };
}

public function isMedical(): bool
{
    return in_array($this, [self::TreatedOnScene, self::TransportedToHospital], true);
}
```

**Adaptations for Phase 18:**
1. **`CameraStatus`** — string-backed, values `online | offline | degraded` (D-09). Cases `Online = 'online'`, `Offline = 'offline'`, `Degraded = 'degraded'`. CONTEXT.md uses **lower-case** values (unlike Incident* enums which use SCREAMING_CASE) — planner follows CONTEXT.md since DB CHECK uses lower-case literals. Add `label()` helper matching IncidentOutcome precedent.
2. **`PersonnelCategory`** — values `allow | block | missing | lost_child` (D-16). Cases `Allow`, `Block`, `Missing`, `LostChild`. Add `label()`.
3. **`CameraEnrollmentStatus`** — values `pending | syncing | done | failed` (D-24). Add `label()`.
4. **`RecognitionSeverity`** — values `info | warning | critical` (D-49). Add `label()` and optionally `isCritical()` predicate following `IncidentOutcome::isMedical()` shape.

---

### `database/factories/CameraFactory.php`

**Role:** factory · **Data flow:** test-fixture
**Analog:** `database/factories/UnitFactory.php` (prefix ID pattern — NOT used in Phase 18) + `database/factories/IncidentFactory.php` (Magellan pattern — used)

**Magellan factory pattern** (`UnitFactory.php:43-46`, `IncidentFactory.php:33-36`):
```php
'coordinates' => Point::makeGeodetic(
    8.9475 + fake()->randomFloat(4, -0.05, 0.05),   // lat (Butuan City)
    125.5406 + fake()->randomFloat(4, -0.05, 0.05), // lng
),
```

**Factory class skeleton** (`IncidentFactory.php:1-43`):
```php
namespace Database\Factories;

use App\Models\Incident;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            // ...
        ];
    }
}
```

**Adaptations for Phase 18 (CameraFactory):**
1. `$model = Camera::class`.
2. Columns per D-61:
   ```php
   'device_id' => fake()->uuid(),
   'camera_id_display' => null,  // Phase 20 sequences
   'name' => fake()->company().' Camera',
   'location_label' => fake()->streetAddress(),
   'location' => Point::makeGeodetic(
       8.9475 + fake()->randomFloat(4, -0.05, 0.05),
       125.5406 + fake()->randomFloat(4, -0.05, 0.05),
   ),
   'status' => CameraStatus::Offline,
   ```
3. Do NOT copy `UnitFactory`'s `id` / `callsign` prefix logic (lines 25-37) — Phase 18 deliberately skips auto-sequencing per D-05 rationale.

---

### `database/factories/PersonnelFactory.php`

**Role:** factory · **Data flow:** test-fixture
**Analog:** `database/factories/IncidentFactory.php`

Same skeleton. **Adaptations per D-61:**
```php
'custom_id' => null,
'name' => fake()->name(),
'gender' => fake()->randomElement([0, 1, null]),
'birthday' => fake()->date(),
'id_card' => fake()->numerify('IDC-########'),
'phone' => fake()->phoneNumber(),
'address' => fake()->address(),
'photo_path' => null,
'photo_hash' => null,
'category' => PersonnelCategory::Allow,
```

No Magellan column; simpler than CameraFactory.

---

### `database/factories/CameraEnrollmentFactory.php`

**Role:** factory · **Data flow:** test-fixture
**Analog:** `database/factories/IncidentFactory.php` (`IncidentType::factory()` FK pattern on line 28)

**FK-through-factory pattern** (`IncidentFactory.php:28`):
```php
'incident_type_id' => IncidentType::factory(),
```

**Adaptations:**
```php
'camera_id' => Camera::factory(),
'personnel_id' => Personnel::factory(),
'status' => CameraEnrollmentStatus::Pending,
'enrolled_at' => null,
'photo_hash' => null,
'last_error' => null,
```

---

### `database/factories/RecognitionEventFactory.php`

**Role:** factory with state methods · **Data flow:** test-fixture
**Analog:** `database/factories/IncidentFactory.php` (jsonb + FK pattern at lines 28, 41)

**jsonb array pattern** (`IncidentFactory.php:41`):
```php
'vitals' => ['bp' => '120/80', 'hr' => 72, 'spo2' => 98, 'gcs' => 15],
```

**Adaptations — new state-method pattern (no direct analog — introduced by Phase 18 per D-61):**
```php
public function definition(): array
{
    $capturedAt = now()->subSeconds(1);

    return [
        'camera_id' => Camera::factory(),
        'personnel_id' => null,
        'incident_id' => null,
        'record_id' => fake()->unique()->numberBetween(1, 2_000_000_000),
        'custom_id' => null,
        'camera_person_id' => null,
        'verify_status' => 1,
        'person_type' => 0,
        'similarity' => fake()->randomFloat(2, 60, 99),
        'is_real_time' => true,
        'name_from_camera' => null,
        'is_no_mask' => 0,
        'target_bbox' => [120, 80, 340, 360],
        'captured_at' => $capturedAt,
        'received_at' => now(),
        'raw_payload' => [
            'recordId' => fake()->numberBetween(1, 2_000_000_000),
            'cameraDeviceId' => fake()->uuid(),
            'personName' => fake()->name(),   // correct spelling
            'persionName' => fake()->name(),  // firmware typo — preserve both (D-61)
            'similarity' => 85.2,
            'verifyStatus' => 1,
            'personType' => 0,
            'isRealTime' => true,
        ],
        'severity' => RecognitionSeverity::Info,
    ];
}

public function critical(): static
{
    return $this->state(fn () => ['severity' => RecognitionSeverity::Critical]);
}

public function warning(): static { ... }
public function info(): static { ... }

public function withPersonnel(Personnel $p): static
{
    return $this->state(fn () => ['personnel_id' => $p->id]);
}

public function blockMatch(): static
{
    return $this->state(fn () => ['person_type' => 1, 'severity' => RecognitionSeverity::Critical]);
}
```

---

### `database/seeders/FrasPlaceholderSeeder.php`

**Role:** seeder · **Data flow:** write-once fixture
**Analog:** `database/seeders/UnitSeeder.php` (structure only — FrasPlaceholderSeeder body stays empty per D-62)

**Seeder skeleton** (`UnitSeeder.php:1-14`):
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        // body...
    }
}
```

**Adaptations:**
1. Class name `FrasPlaceholderSeeder`.
2. Empty `run()` body (D-62) or single-line comment `// Intentionally empty — FRAS tables seed on demand from factories.`.
3. **Do NOT register in `DatabaseSeeder::run()` `$this->call([...])` array** (D-62). `database/seeders/DatabaseSeeder.php` stays untouched.

---

### `tests/Feature/Fras/CameraSpatialQueryTest.php`

**Role:** Pest feature test · **Data flow:** spatial-query assertion
**Analog:** `tests/Feature/Foundation/BarangaySpatialTest.php`

**Spatial DB::select pattern** (analog lines 17-22):
```php
$result = DB::select('
    SELECT name FROM barangays
    WHERE ST_Contains(boundary::geometry, ST_SetSRID(ST_MakePoint(125.5599, 8.9607), 4326)::geometry)
    LIMIT 1
');

expect($result)->not->toBeEmpty();
expect($result[0]->name)->toBe('AgaoPoblacion');
```

**Alternative spatial idiom using `ST_DWithin`** (`app/Services/ProximityRankingService.php:36-40`):
```php
AND ST_DWithin(
    coordinates,
    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
    ?
)
```

**Adaptations for Phase 18 (SC5 per D-59):**
1. Add `pest()->group('fras');` at top of file (new convention introduced by Phase 18; repo has no prior `group()` usage — verified via grep).
2. `uses(RefreshDatabase::class)` already applied globally via `tests/Pest.php:18-20` — no explicit call needed.
3. Test body:
   ```php
   use App\Models\Camera;
   use App\Models\Incident;
   use Illuminate\Support\Facades\DB;

   pest()->group('fras');

   it('finds cameras within 500m of an incident via ST_DWithin', function () {
       $camera = Camera::factory()->create([
           // lock coordinate near a fixed incident point
       ]);
       $incident = Incident::factory()->create([/* near camera */]);

       $rows = DB::select('
           SELECT c.id FROM cameras c
           WHERE ST_DWithin(c.location, (SELECT coordinates FROM incidents WHERE id = ?), 500)
       ', [$incident->id]);

       expect($rows)->not->toBeEmpty();
       expect($rows[0]->id)->toBe($camera->id);
   });
   ```

---

### `tests/Feature/Fras/RecognitionEventIdempotencyTest.php`

**Role:** Pest feature test · **Data flow:** DB-constraint assertion
**Analog:** `tests/Feature/Foundation/UnitModelTest.php` (factory create pattern) + Pest `toThrow` expectation (new for repo)

**Factory create assertion pattern** (`tests/Feature/Foundation/UnitModelTest.php:9-13`):
```php
it('creates unit with string primary key', function () {
    $unit = Unit::factory()->create(['id' => 'AMB-TEST-01']);

    expect($unit->id)->toBe('AMB-TEST-01');
});
```

**Adaptations for Phase 18 (SC2 + FRAMEWORK-06 per D-59, D-60):**
1. Add `pest()->group('fras');` at top.
2. Use Pest's `->throws(...)` or `expect(fn () => ...)->toThrow(...)` pattern:
   ```php
   use App\Models\Camera;
   use App\Models\RecognitionEvent;
   use Illuminate\Database\UniqueConstraintViolationException;

   pest()->group('fras');

   it('rejects duplicate (camera_id, record_id) via DB UNIQUE', function () {
       $camera = Camera::factory()->create();

       RecognitionEvent::factory()
           ->for($camera)
           ->create(['record_id' => 123456]);

       expect(fn () => RecognitionEvent::factory()
           ->for($camera)
           ->create(['record_id' => 123456])
       )->toThrow(UniqueConstraintViolationException::class);
   });
   ```

## Shared Patterns

### Pattern A: HasUuids + HasFactory + UUIDv7

**Source:** `app/Models/Incident.php:1-21` (+ Laravel 13 `HasUuids` trait at `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUuids.php` which emits UUIDv7)
**Apply to:** All 4 FRAS models (Camera, Personnel, CameraEnrollment, RecognitionEvent) per D-01.

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Camera extends Model
{
    /** @use HasFactory<CameraFactory> */
    use HasFactory, HasUuids;
}
```

No `$keyType`/`$incrementing` overrides — trait handles them.

### Pattern B: DB-level CHECK constraint via raw DB::statement

**Source:** NOT present in v1.0 codebase. Phase 18 introduces. Pattern per RESEARCH.md §Pattern 4.
**Apply to:** `cameras.status` (D-09), `personnel.category` (D-16), `camera_enrollments.status` (D-24), `recognition_events.severity` (D-49).

**Idiom** (to author):
```php
use Illuminate\Support\Facades\DB;

Schema::create('cameras', function (Blueprint $table) {
    // ... columns (status with default) ...
});

DB::statement(
    "ALTER TABLE cameras ADD CONSTRAINT cameras_status_check "
    . "CHECK (status IN ('online','offline','degraded'))"
);
```

**Naming convention:** `{table}_{column}_check` (explicit, deterministic — prevents Postgres auto-name collisions).
**`down()` note:** `Schema::dropIfExists('cameras')` drops the CHECK transitively; no explicit `DROP CONSTRAINT` needed.

### Pattern C: `scopeActive` + `decommissioned_at` soft delete

**Source:** `app/Models/Unit.php:64-67`
**Apply to:** `Camera` (D-11), `Personnel` (D-19).

```php
use Illuminate\Database\Eloquent\Builder;

/**
 * @param  Builder<Camera>  $query
 * @return Builder<Camera>
 */
public function scopeActive(Builder $query): Builder
{
    return $query->whereNull('decommissioned_at');
}
```

**Rationale** (from `ProximityRankingService.php:34`): the `WHERE decommissioned_at IS NULL` guard is already a repo-wide convention for active-row queries.

### Pattern D: Butuan City factory coordinates

**Source:** `database/factories/UnitFactory.php:43-46` and `database/factories/IncidentFactory.php:33-36`
**Apply to:** `CameraFactory` only.

```php
use Clickbar\Magellan\Data\Geometries\Point;

'location' => Point::makeGeodetic(
    8.9475 + fake()->randomFloat(4, -0.05, 0.05),   // latitude first
    125.5406 + fake()->randomFloat(4, -0.05, 0.05), // longitude second
),
```

**API note from RESEARCH.md Pattern 2:** `Point::makeGeodetic` is lat-first; `Point::make` is x/lng-first. Always use `makeGeodetic` in factories to match repo precedent.

### Pattern E: Pest feature test with `RefreshDatabase`

**Source:** `tests/Pest.php:18-20` (global `RefreshDatabase` trait for `Feature/*`)
**Apply to:** Both Phase 18 feature tests — they inherit the trait without declaring it.

**Repo-wide convention:** Feature tests do NOT need `uses(RefreshDatabase::class);` at top of file. Pest auto-binds.

### Pattern F: `pest()->group()` — NEW convention in Phase 18

**Source:** Pest 4 native API; no prior use in repo (verified via grep — 0 matches).
**Apply to:** Both Phase 18 feature tests per D-60.

```php
pest()->group('fras');
```

Runs via `./vendor/bin/pest --group=fras`. No `phpunit.xml` edit needed.

### Pattern G: Seeder class that is NOT registered in DatabaseSeeder

**Source:** `database/seeders/UnitSeeder.php` (exists but is called — keep structure; Phase 18 breaks the "registered" part).
**Apply to:** `FrasPlaceholderSeeder` per D-62.

**Idiom:** Write the seeder class; leave its `run()` body empty; **do not modify** `database/seeders/DatabaseSeeder.php:14-23` (the `$this->call([...])` array). SC4 — "every new table has a factory and a seeder" — is satisfied by the file's presence; safety is preserved by leaving the body empty.

## No Analog Found

| File | Role | Reason | Recommended Source |
|------|------|--------|-------------------|
| DB CHECK constraint inside migration | migration idiom | v1.0 has zero CHECK constraints today | Author per RESEARCH.md §Pattern 4 (see Shared Pattern B above) |
| GIN index `USING GIN (col jsonb_path_ops)` | migration idiom | v1.0 has jsonb columns (`incidents.vitals`) but no GIN indexes | Author per RESEARCH.md §Pattern 5 Option A: raw `DB::statement('CREATE INDEX ... USING GIN (raw_payload jsonb_path_ops)')` |
| `pest()->group('fras')` annotation | test idiom | 0 grep matches in repo | Pest 4 docs — single top-of-file call |
| `Illuminate\Database\UniqueConstraintViolationException` assertion | test idiom | 0 grep matches in repo | Laravel framework exception; use Pest `toThrow(UniqueConstraintViolationException::class)` |
| RecognitionEventFactory state methods (`->critical()`, `->blockMatch()`, `->withPersonnel($p)`) | factory idiom | v1.0 factories define `definition()` only; no state-method precedent | Laravel factory docs §States — author per D-61 |
| Microsecond-precision `timestampTz('x', precision: 6)` | migration idiom | v1.0 uses plain `timestamps()` / `timestamp()` with no precision argument | Laravel Schema Builder docs — verified Laravel 13 supports it |

## Metadata

**Analog search scope:** `database/migrations/`, `database/factories/`, `database/seeders/`, `app/Models/`, `app/Enums/`, `tests/Feature/`, `tests/Pest.php`, `app/Services/`
**Files scanned:** 25 (10 migrations, 9 models, 8 enums, 9 factories, 9 seeders, 6 foundation tests, 1 Pest config, 1 proximity service)
**Pattern extraction date:** 2026-04-21

## PATTERN MAPPING COMPLETE
