---
phase: 11
slug: implement-units-crud
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-14
---

# Phase 11 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact tests/Feature/Admin/AdminUnitTest.php` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact tests/Feature/Admin/AdminUnitTest.php`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 11-01-01 | 01 | 0 | UNIT-01..09 | feature | `php artisan test --compact tests/Feature/Admin/AdminUnitTest.php` | ✅ | ✅ green |
| 11-02-01 | 02 | 1 | UNIT-01 | feature | `php artisan test --compact --filter="admin to list units"` | ✅ | ✅ green |
| 11-02-02 | 02 | 1 | UNIT-02 | feature | `php artisan test --compact --filter="admin to create unit"` | ✅ | ✅ green |
| 11-02-03 | 02 | 1 | UNIT-03 | feature | `php artisan test --compact --filter="admin to update unit"` | ✅ | ✅ green |
| 11-02-04 | 02 | 1 | UNIT-04 | feature | `php artisan test --compact --filter="decommission"` | ✅ | ✅ green |
| 11-02-05 | 02 | 1 | UNIT-05 | feature | `php artisan test --compact --filter="recommission"` | ✅ | ✅ green |
| 11-02-06 | 02 | 1 | UNIT-06 | feature | `php artisan test --compact --filter="crew"` | ✅ | ✅ green |
| 11-02-07 | 02 | 1 | UNIT-07 | feature | `php artisan test --compact --filter="blocks non-admin"` | ✅ | ✅ green |
| 11-02-08 | 02 | 1 | UNIT-08 | feature | `php artisan test --compact --filter="auto-generated"` | ✅ | ✅ green |
| 11-02-09 | 02 | 1 | UNIT-09 | feature | `php artisan test --compact --filter="status validation"` | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Feature/Admin/AdminUnitTest.php` — stubs for UNIT-01 through UNIT-09
- [x] Migration for `decommissioned_at` column on `units` table

*Existing infrastructure (Pest 4, phpunit.xml) covers framework needs.*

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Status badge colors match design system | UNIT-01 | Visual CSS verification | Inspect unit list, verify badge colors match `--t-unit-*` tokens |
| Type badge colors are distinguishable | UNIT-01 | Visual CSS verification | Inspect unit list, verify each type has a distinct badge color |
| Decommissioned row muted styling | UNIT-04 | Visual CSS verification | Decommission a unit, verify faded/muted row styling |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 15s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-03-14

## Validation Audit 2026-03-14

| Metric | Count |
|--------|-------|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |

All test files pre-exist from phase execution. No new tests needed.
