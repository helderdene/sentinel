---
phase: 18-fras-schema-port-to-postgresql
fixed_at: 2026-04-21T00:00:00Z
review_path: .planning/phases/18-fras-schema-port-to-postgresql/18-REVIEW.md
iteration: 1
findings_in_scope: 2
fixed: 2
skipped: 0
status: all_fixed
---

# Phase 18: Code Review Fix Report

**Fixed at:** 2026-04-21
**Source review:** .planning/phases/18-fras-schema-port-to-postgresql/18-REVIEW.md
**Iteration:** 1

**Summary:**
- Findings in scope: 2 (0 Critical, 2 Warning — Info findings excluded per fix_scope=critical_warning)
- Fixed: 2
- Skipped: 0

Post-fix verification:
- `vendor/bin/pint --dirty --format agent` — pass
- `php artisan test --compact tests/Feature/Fras` — 16 passed (42 assertions), 1.21s

## Fixed Issues

### WR-01: `RecognitionEvent::casts()` assigns `record_id => 'integer'` while column is `bigInteger`

**Files modified:** `app/Models/RecognitionEvent.php`
**Commit:** 9c3e5ca
**Applied fix:** Removed the `'record_id' => 'integer'` entry from `RecognitionEvent::casts()`. Laravel returns bigint columns as PHP `int` on 64-bit runtimes without any cast, so the line was cosmetic and misleading about 32-bit overflow behavior. The recommendation option (b) in the review (drop the cast entirely) was applied rather than adding a clarifying PHPDoc. Verified the remaining cast list (`verify_status`, `person_type`, `is_no_mask`, datetimes, enum, decimal, array, boolean) is intact.

### WR-02: `EnumCheckParityTest::extractCheckValues` uses fragile `LIKE` pattern for column matching

**Files modified:** `tests/Feature/Fras/EnumCheckParityTest.php`
**Commit:** 707c93e
**Applied fix:** Replaced the `pg_get_constraintdef(oid) LIKE '%{column}%'` clause with a direct `conname = ?` filter using the convention `{table}_{column}_check`. All four Phase 18 CHECK constraints follow this naming (`cameras_status_check`, `personnel_category_check`, `camera_enrollments_status_check`, `recognition_events_severity_check` — verified against the migration files at `database/migrations/2026_04_21_00000{1..4}_*.php`). Kept the `contype = 'c'` filter (passed as a bound parameter for consistency with other bindings) so the helper still restricts to CHECK constraints. All four enum-parity tests pass against the new query.

---

_Fixed: 2026-04-21_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
