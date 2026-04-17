---
phase: 1
slug: foundation
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-12
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHPUnit 12 backend) |
| **Config file** | `phpunit.xml` + `tests/Pest.php` + `.env.testing` |
| **Quick run command** | `php artisan test --compact --filter=testName` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=<relevant_test_class>`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 01-01-01 | 01 | 1 | FNDTN-01 | feature | `php artisan test --compact --filter=PostgisSetup` | ✅ | ✅ green |
| 01-02-01 | 02 | 1 | FNDTN-03 | feature | `php artisan test --compact --filter=RoleAccess` | ✅ | ✅ green |
| 01-02-02 | 02 | 1 | FNDTN-04 | feature | `php artisan test --compact --filter=UserUnit` | ✅ | ✅ green |
| 01-03-01 | 03 | 2 | FNDTN-05 | feature | `php artisan test --compact --filter=IncidentModel` | ✅ | ✅ green |
| 01-03-02 | 03 | 2 | FNDTN-06 | feature | `php artisan test --compact --filter=UnitModel` | ✅ | ✅ green |
| 01-03-03 | 03 | 2 | FNDTN-07 | feature | `php artisan test --compact --filter=IncidentTimeline` | ✅ | ✅ green |
| 01-03-04 | 03 | 2 | FNDTN-08 | feature | `php artisan test --compact --filter=IncidentMessage` | ✅ | ✅ green |
| 01-04-01 | 04 | 2 | FNDTN-02 | feature | `php artisan test --compact --filter=BarangaySpatial` | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `.env.testing` — PostgreSQL test database configuration
- [x] `tests/Feature/Foundation/PostgisSetupTest.php` — FNDTN-01
- [x] `tests/Feature/Foundation/RoleAccessTest.php` — FNDTN-03
- [x] `tests/Feature/Foundation/UserUnitTest.php` — FNDTN-04
- [x] `tests/Feature/Foundation/IncidentModelTest.php` — FNDTN-05
- [x] `tests/Feature/Foundation/UnitModelTest.php` — FNDTN-06
- [x] `tests/Feature/Foundation/IncidentTimelineTest.php` — FNDTN-07
- [x] `tests/Feature/Foundation/IncidentMessageTest.php` — FNDTN-08
- [x] `tests/Feature/Foundation/BarangaySpatialTest.php` — FNDTN-02
- [x] Factory files: all present
- [x] UserFactory with role states: all present

*Existing infrastructure covers all phase requirements — 45 tests, 82 assertions, all green.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Role-based navigation rendering | FNDTN-03 | Vue sidebar rendering per role requires browser | Login as each role, verify sidebar shows correct items |
| Admin panel UI flows | FNDTN-03 | CRUD forms require visual verification | Create user, assign role, verify list updates |
| Barangay metadata editing | FNDTN-02 | Admin form interaction | Edit risk level/population in admin panel |

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

All 8 test files pre-exist from phase execution. 45 tests, 82 assertions — all green. No new tests needed.
