---
phase: 11
slug: implement-units-crud
status: draft
nyquist_compliant: false
wave_0_complete: false
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
| 11-01-01 | 01 | 0 | UNIT-01..09 | feature | `php artisan test --compact tests/Feature/Admin/AdminUnitTest.php` | No - W0 | ⬜ pending |
| 11-02-01 | 02 | 1 | UNIT-01 | feature | `php artisan test --compact --filter="admin to list units"` | ❌ W0 | ⬜ pending |
| 11-02-02 | 02 | 1 | UNIT-02 | feature | `php artisan test --compact --filter="admin to create unit"` | ❌ W0 | ⬜ pending |
| 11-02-03 | 02 | 1 | UNIT-03 | feature | `php artisan test --compact --filter="admin to update unit"` | ❌ W0 | ⬜ pending |
| 11-02-04 | 02 | 1 | UNIT-04 | feature | `php artisan test --compact --filter="decommission"` | ❌ W0 | ⬜ pending |
| 11-02-05 | 02 | 1 | UNIT-05 | feature | `php artisan test --compact --filter="recommission"` | ❌ W0 | ⬜ pending |
| 11-02-06 | 02 | 1 | UNIT-06 | feature | `php artisan test --compact --filter="crew"` | ❌ W0 | ⬜ pending |
| 11-02-07 | 02 | 1 | UNIT-07 | feature | `php artisan test --compact --filter="blocks non-admin"` | ❌ W0 | ⬜ pending |
| 11-02-08 | 02 | 1 | UNIT-08 | feature | `php artisan test --compact --filter="auto-generated"` | ❌ W0 | ⬜ pending |
| 11-02-09 | 02 | 1 | UNIT-09 | feature | `php artisan test --compact --filter="status validation"` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Admin/AdminUnitTest.php` — stubs for UNIT-01 through UNIT-09
- [ ] Migration for `decommissioned_at` column on `units` table

*Existing infrastructure (Pest 4, phpunit.xml) covers framework needs.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Status badge colors match design system | UNIT-01 | Visual CSS verification | Inspect unit list, verify badge colors match `--t-unit-*` tokens |
| Type badge colors are distinguishable | UNIT-01 | Visual CSS verification | Inspect unit list, verify each type has a distinct badge color |
| Decommissioned row muted styling | UNIT-04 | Visual CSS verification | Decommission a unit, verify faded/muted row styling |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
