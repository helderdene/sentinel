---
phase: 1
slug: foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
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
| 01-01-01 | 01 | 1 | FNDTN-01 | feature | `php artisan test --compact --filter=PostgisSetup` | ❌ W0 | ⬜ pending |
| 01-02-01 | 02 | 1 | FNDTN-03 | feature | `php artisan test --compact --filter=RoleAccess` | ❌ W0 | ⬜ pending |
| 01-02-02 | 02 | 1 | FNDTN-04 | feature | `php artisan test --compact --filter=UserUnit` | ❌ W0 | ⬜ pending |
| 01-03-01 | 03 | 2 | FNDTN-05 | feature | `php artisan test --compact --filter=IncidentModel` | ❌ W0 | ⬜ pending |
| 01-03-02 | 03 | 2 | FNDTN-06 | feature | `php artisan test --compact --filter=UnitModel` | ❌ W0 | ⬜ pending |
| 01-03-03 | 03 | 2 | FNDTN-07 | feature | `php artisan test --compact --filter=IncidentTimeline` | ❌ W0 | ⬜ pending |
| 01-03-04 | 03 | 2 | FNDTN-08 | feature | `php artisan test --compact --filter=IncidentMessage` | ❌ W0 | ⬜ pending |
| 01-04-01 | 04 | 2 | FNDTN-02 | feature | `php artisan test --compact --filter=BarangaySpatial` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `.env.testing` — PostgreSQL test database configuration (DB_CONNECTION=pgsql, DB_DATABASE=irms_testing)
- [ ] `tests/Feature/Foundation/PostgisSetupTest.php` — stubs for FNDTN-01
- [ ] `tests/Feature/Foundation/RoleAccessTest.php` — stubs for FNDTN-03
- [ ] `tests/Feature/Foundation/UserUnitTest.php` — stubs for FNDTN-04
- [ ] `tests/Feature/Foundation/IncidentModelTest.php` — stubs for FNDTN-05
- [ ] `tests/Feature/Foundation/UnitModelTest.php` — stubs for FNDTN-06
- [ ] `tests/Feature/Foundation/IncidentTimelineTest.php` — stubs for FNDTN-07
- [ ] `tests/Feature/Foundation/IncidentMessageTest.php` — stubs for FNDTN-08
- [ ] `tests/Feature/Foundation/BarangaySpatialTest.php` — stubs for FNDTN-02
- [ ] Factory files: `IncidentFactory`, `UnitFactory`, `BarangayFactory`, `IncidentTypeFactory`, `IncidentTimelineFactory`, `IncidentMessageFactory`
- [ ] Update `UserFactory` with role states: `->admin()`, `->dispatcher()`, `->responder()`, `->supervisor()`

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Role-based navigation rendering | FNDTN-03 | Vue sidebar rendering per role requires browser | Login as each role, verify sidebar shows correct items |
| Admin panel UI flows | FNDTN-03 | CRUD forms require visual verification | Create user, assign role, verify list updates |
| Barangay metadata editing | FNDTN-02 | Admin form interaction | Edit risk level/population in admin panel |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
