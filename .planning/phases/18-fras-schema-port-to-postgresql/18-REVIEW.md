---
phase: 18-fras-schema-port-to-postgresql
reviewed: 2026-04-21T00:00:00Z
depth: standard
files_reviewed: 19
files_reviewed_list:
  - app/Enums/CameraEnrollmentStatus.php
  - app/Enums/CameraStatus.php
  - app/Enums/PersonnelCategory.php
  - app/Enums/RecognitionSeverity.php
  - app/Models/Camera.php
  - app/Models/CameraEnrollment.php
  - app/Models/Personnel.php
  - app/Models/RecognitionEvent.php
  - database/factories/CameraEnrollmentFactory.php
  - database/factories/CameraFactory.php
  - database/factories/PersonnelFactory.php
  - database/factories/RecognitionEventFactory.php
  - database/migrations/2026_04_21_000001_create_cameras_table.php
  - database/migrations/2026_04_21_000002_create_personnel_table.php
  - database/migrations/2026_04_21_000003_create_camera_enrollments_table.php
  - database/migrations/2026_04_21_000004_create_recognition_events_table.php
  - database/seeders/FrasPlaceholderSeeder.php
  - tests/Feature/Fras/CameraSpatialQueryTest.php
  - tests/Feature/Fras/EnumCheckParityTest.php
  - tests/Feature/Fras/RecognitionEventIdempotencyTest.php
  - tests/Feature/Fras/SchemaTest.php
findings:
  critical: 0
  warning: 2
  info: 5
  total: 7
status: issues_found
---

# Phase 18: Code Review Report

**Reviewed:** 2026-04-21
**Depth:** standard
**Files Reviewed:** 19 (plus 2 test files listed together = 21 changed files; 19 distinct source artifacts plus 2 extra test files)
**Status:** issues_found (no Critical findings; 2 Warnings, 5 Info — all low-severity)

## Summary

The FRAS schema port is a high-quality implementation that cleanly mirrors existing IRMS v1.0 idioms (HasUuids, Magellan Point cast, timestampTz precision, CHECK-constraint enum parity). All four migrations align with their backing models, factories produce valid rows, and the test suite covers structural schema, enum parity, spatial queries, and FRAMEWORK-06 idempotency. Project conventions from CLAUDE.md (curly braces, constructor type hints via fillable, explicit return types, PHPDoc over inline comments) are respected throughout.

No Critical issues. No security issues (all DB queries use parameterized bindings; no hardcoded secrets; no eval/exec). The "firmware typo" `persionName` in `RecognitionEventFactory` is intentional per decision D-61 and cross-referenced in phase documentation — not a bug.

Warnings are minor defensive-programming opportunities. Info items are observations worth noting for Phase 19+ maintainers but do not require changes.

## Warnings

### WR-01: `RecognitionEvent::casts()` assigns `record_id => 'integer'` while column is `bigInteger`

**File:** `app/Models/RecognitionEvent.php:70`
**Issue:** The `record_id` column is declared `bigInteger` in the migration (supporting values up to 2^63−1). The model cast is `'integer'`, which on 64-bit PHP (the project's PHP 8.4 target) maps to `int` (64-bit). This works on Laravel Herd and production 64-bit hardware, but the semantic intent is clearer with an explicit wider cast. If the app is ever run on 32-bit PHP (unlikely but not enforced anywhere), values above 2,147,483,647 would overflow silently. The `RecognitionEventFactory` caps generated values at `2_000_000_000` (line 26), just under the 32-bit boundary — likely because of this same concern — so the ceiling is informally respected but not documented.
**Fix:** Either (a) leave as-is with a clarifying PHPDoc on the cast list noting "64-bit PHP assumed; record_id can reach 2^63−1 on production," or (b) drop the `integer` cast entirely — Laravel will return the raw DB bigint as a PHP int without a cast, so the cast adds nothing. Recommendation: remove the cast.

```php
// Remove this line from casts():
'record_id' => 'integer',
```

### WR-02: `EnumCheckParityTest::extractCheckValues` uses fragile `LIKE` pattern for column matching

**File:** `tests/Feature/Fras/EnumCheckParityTest.php:22-28`
**Issue:** The helper locates a CHECK constraint via `pg_get_constraintdef(oid) LIKE '%{$column}%'`. If a future migration adds a second CHECK constraint on the same table whose definition happens to contain the target column name as a substring (e.g., a `CHECK (length(category_name) > 0)` on a column whose name contains "category"), `selectOne` will return an arbitrary row and the assertion may silently pass against the wrong constraint.
**Fix:** Filter by constraint name instead. The Phase 18 migrations explicitly name each constraint (`cameras_status_check`, `personnel_category_check`, etc.), so the test can assert by name for reliability.

```php
$constraint = DB::selectOne("
    SELECT pg_get_constraintdef(oid) AS def
    FROM pg_constraint
    WHERE conrelid = ?::regclass
      AND conname = ?
", [$table, "{$table}_{$column}_check"]);
```

This also makes the test fail loudly if a future migration accidentally drops or renames the named constraint.

## Info

### IN-01: `RecognitionEventFactory` `record_id` uses `fake()->unique()` which can exhaust

**File:** `database/factories/RecognitionEventFactory.php:26`
**Issue:** `fake()->unique()->numberBetween(1, 2_000_000_000)` maintains per-test-process state. With a 2-billion range and tests creating handfuls of events, exhaustion is practically impossible, but if a future test loop creates 10k+ events without resetting unique state, the faker eventually throws `OverflowException` after ~1000 retries. Idempotency is guaranteed by the DB UNIQUE constraint, so `->unique()` at factory level is belt-and-suspenders.
**Fix:** Consider dropping `->unique()` since FRAMEWORK-06 idempotency is enforced by the `(camera_id, record_id)` DB constraint. Duplicate records within a single test would collide with the factory's default single camera only if the test creates two events without calling `->for($camera)` — and the idempotency test explicitly wants duplicates to fail. Suggested: leave as-is (current behavior is correct and defensive); just be aware if a downstream phase writes a large-volume seeder.

### IN-02: `RecognitionEvent::casts()` casts `is_no_mask` to `integer` instead of `boolean`

**File:** `app/Models/RecognitionEvent.php:69`
**Issue:** `is_no_mask` is semantically a boolean flag (0/1) but is cast to `integer`. The column is `smallInteger`. Casting to boolean would be more idiomatic for callers who want `if ($event->is_no_mask)`. The integer cast is likely defensive in case the firmware emits values outside {0, 1}.
**Fix:** No change required — current cast matches the cautious approach used for `verify_status` and `person_type`. If Phase 19's `RecognitionHandler` confirms the firmware only ever sends 0/1, consider tightening to `'boolean'` in Phase 20.

### IN-03: BelongsTo methods lack PHPDoc generic annotations

**File:** `app/Models/CameraEnrollment.php:47-58`, `app/Models/RecognitionEvent.php:77-104`
**Issue:** Relation methods return bare `BelongsTo` without generic type parameters (e.g., `BelongsTo<Camera, self>`). Static analysis tools (PHPStan, Larastan) benefit from the generics.
**Fix:** No change required — this matches existing project convention (`app/Models/Incident.php:164-183` uses bare `BelongsTo`). Keeping consistency with the wider codebase is correct for Phase 18.

### IN-04: `Personnel::$fillable` includes `decommissioned_at` but no accompanying accessor/mutator

**File:** `app/Models/Personnel.php:37`
**Issue:** `decommissioned_at` is mass-assignable. A mistaken `Personnel::create(['name' => 'X', 'decommissioned_at' => now()])` would mark a new record as already decommissioned. Low risk since callers go through controllers/form requests in Phase 20.
**Fix:** No change required in Phase 18 — the minimal-model approach (D-66) is correct and Phase 20's admin flow will validate inputs. Flag for Phase 20: ensure `StorePersonnelRequest` does not permit `decommissioned_at` as an input, and add a dedicated `decommission()` method on the model.

### IN-05: `Camera::$fillable` includes `decommissioned_at` and `last_seen_at` — same mass-assignment concern

**File:** `app/Models/Camera.php:30-31`
**Issue:** Same as IN-04. `last_seen_at` will be bumped by Phase 19 heartbeat handler (legitimate mass assignment); `decommissioned_at` should be controlled by a dedicated admin action in Phase 20.
**Fix:** No change required in Phase 18. Note for Phase 20: gate `decommissioned_at` behind a dedicated model method with authorization.

---

## Positive Observations

- Enum + CHECK constraint parity is enforced by test — catches drift during Phase 21+ changes.
- `RecognitionEventFactory` preserves the firmware typo `persionName` per D-61 — this is intentional and well-documented in `18-04-SUMMARY.md:55` and PHPDoc-adjacent comments.
- Migration down() methods correctly rely on `dropIfExists()` to cascade-drop named CHECK constraints and indexes (PostgreSQL behavior is idiomatic here).
- Spatial test correctly uses `[$lng, $lat]` for `ST_MakePoint` (PostGIS convention) while factories use `Point::makeGeodetic($lat, $lng)` (Magellan convention) — parameter order is documented inline.
- Mixed FK types (`foreignUuid` for camera/personnel/incident + `foreignId` for `acknowledged_by → users`) is an explicit, correct choice per D-50 and consistent with the existing v1.0 `users` table using bigint PK.
- `FrasPlaceholderSeeder` is intentionally empty with a clear PHPDoc explaining why — satisfies SC4 without risking production rows.
- All models declare `HasUuids` + `HasFactory` with correct `@use` generic annotations for Laravel 12.
- Migration naming follows the 4-digit zero-padded ordering convention within a single date.
- `decimal(5,2)` for `similarity` with `'decimal:2'` model cast (D-40 correction) — avoids float rounding drift that would break Phase 19 dedup.
- GIN index on `raw_payload` uses `jsonb_path_ops` opclass (D-48) — smaller and faster for the `@>` containment queries Phase 19 will issue.

---

_Reviewed: 2026-04-21_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
