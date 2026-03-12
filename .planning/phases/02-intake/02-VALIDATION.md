---
phase: 2
slug: intake
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | `tests/Pest.php` + `phpunit.xml` |
| **Quick run command** | `php artisan test --compact tests/Feature/Intake/ tests/Unit/ -x` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact tests/Feature/Intake/ tests/Unit/ -x`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | INTK-01 | Feature | `php artisan test --compact tests/Feature/Intake/CreateIncidentTest.php -x` | ❌ W0 | ⬜ pending |
| 02-01-02 | 01 | 1 | INTK-02 | Feature | `php artisan test --compact tests/Feature/Foundation/IncidentModelTest.php --filter=auto-generates -x` | ✅ | ⬜ pending |
| 02-01-03 | 01 | 1 | INTK-03 | Unit | `php artisan test --compact tests/Unit/PrioritySuggestionServiceTest.php -x` | ❌ W0 | ⬜ pending |
| 02-01-04 | 01 | 1 | INTK-04 | Unit+Feature | `php artisan test --compact tests/Unit/GeocodingServiceTest.php -x` | ❌ W0 | ⬜ pending |
| 02-01-05 | 01 | 1 | INTK-05 | Feature | `php artisan test --compact tests/Feature/Intake/BarangayAssignmentTest.php -x` | ❌ W0 | ⬜ pending |
| 02-02-01 | 02 | 2 | INTK-06 | Feature | `php artisan test --compact tests/Feature/Intake/DispatchQueueTest.php -x` | ❌ W0 | ⬜ pending |
| 02-03-01 | 03 | 2 | INTK-07 | Feature | `php artisan test --compact tests/Feature/Intake/IoTWebhookTest.php -x` | ❌ W0 | ⬜ pending |
| 02-03-02 | 03 | 2 | INTK-08 | Feature | `php artisan test --compact tests/Feature/Intake/SmsWebhookTest.php -x` | ❌ W0 | ⬜ pending |
| 02-03-03 | 03 | 2 | INTK-09 | Feature | `php artisan test --compact tests/Feature/Intake/ChannelMonitorTest.php -x` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Intake/CreateIncidentTest.php` — stubs for INTK-01, INTK-02
- [ ] `tests/Unit/PrioritySuggestionServiceTest.php` — stubs for INTK-03
- [ ] `tests/Unit/GeocodingServiceTest.php` — stubs for INTK-04
- [ ] `tests/Feature/Intake/BarangayAssignmentTest.php` — stubs for INTK-05
- [ ] `tests/Feature/Intake/DispatchQueueTest.php` — stubs for INTK-06
- [ ] `tests/Feature/Intake/IoTWebhookTest.php` — stubs for INTK-07
- [ ] `tests/Feature/Intake/SmsWebhookTest.php` — stubs for INTK-08
- [ ] `tests/Feature/Intake/ChannelMonitorTest.php` — stubs for INTK-09

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Combobox grouped search UX | INTK-01 | Interactive UI behavior | Open form, type incident type keyword, verify grouped dropdown filters correctly |
| Priority color rendering | INTK-03 | Visual styling | Verify P1-P4 buttons display correct colors (red, orange, amber, green) |
| Queue live refresh | INTK-06 | Inertia polling timing | Open queue, create incident in another tab, verify it appears within 10s |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
