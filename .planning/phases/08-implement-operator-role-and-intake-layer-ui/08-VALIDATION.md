---
phase: 8
slug: implement-operator-role-and-intake-layer-ui
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-13
---

# Phase 8 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=Intake` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=Intake`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 08-01-01 | 01 | 1 | OP-01 | unit | `php artisan test --compact tests/Unit/Enums/UserRoleTest.php` | ✅ | ✅ green |
| 08-01-02 | 01 | 1 | OP-02 | unit | `php artisan test --compact tests/Unit/Enums/IncidentStatusTest.php` | ✅ | ✅ green |
| 08-01-03 | 01 | 1 | OP-03 | feature | `php artisan test --compact tests/Feature/Intake/IntakeGatesTest.php` | ✅ | ✅ green |
| 08-01-04 | 01 | 1 | OP-04 | feature | `php artisan test --compact tests/Feature/Auth/OperatorRedirectTest.php` | ✅ | ✅ green |
| 08-01-05 | 01 | 1 | OP-08 | feature | `php artisan test --compact tests/Feature/Intake/TriageIncidentTest.php` | ✅ | ✅ green |
| 08-02-01 | 02 | 1 | OP-05 | feature | `php artisan test --compact tests/Feature/Intake/IntakeStationTest.php` | ✅ | ✅ green |
| 08-02-02 | 02 | 1 | OP-06 | feature | `php artisan test --compact tests/Feature/Intake/IntakeStationTest.php` | ✅ | ✅ green |
| 08-03-01 | 03 | 2 | OP-07 | feature | `php artisan test --compact tests/Feature/Intake/TriageIncidentTest.php` | ✅ | ✅ green |
| 08-03-02 | 03 | 2 | OP-08 | feature | `php artisan test --compact tests/Feature/Intake/TriageIncidentTest.php` | ✅ | ✅ green |
| 08-04-01 | 04 | 3 | OP-11 | feature | `php artisan test --compact tests/Feature/Intake/IntakeGatesTest.php` | ✅ | ✅ green |
| 08-04-02 | 04 | 3 | OP-15 | feature | `php artisan test --compact tests/Feature/Broadcasting/ChannelAuthTest.php` | ✅ | ✅ green |

*Status: pending / green / red / flaky*

---

## Wave 0 Requirements

- [x] `tests/Unit/Enums/UserRoleTest.php` — Plan 08-01 Task 1 creates this (OP-01: operator enum)
- [x] `tests/Unit/Enums/IncidentStatusTest.php` — Plan 08-01 Task 1 creates this (OP-02: TRIAGED status)
- [x] `tests/Feature/Intake/IntakeGatesTest.php` — Plan 08-01 Task 1 creates this (OP-03, OP-11: gate authorization)
- [x] `tests/Feature/Auth/OperatorRedirectTest.php` — Plan 08-01 Task 2 creates this (OP-04: operator redirect)
- [x] `tests/Feature/Intake/IntakeStationTest.php` — Plan 08-01 Task 2 creates this (OP-05, OP-06: page render/forbidden)
- [x] `tests/Feature/Intake/TriageIncidentTest.php` — Plan 08-01 Task 2 creates this (OP-07, OP-08: triage + manual entry)
- [x] `tests/Feature/Broadcasting/ChannelAuthTest.php` — stubs for OP-15 (channel auth)
- [x] `database/factories/UserFactory.php` needs `operator()` state method

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Dark mode visual correctness | Design System | Visual rendering cannot be automated with Pest | Toggle dark mode, verify token colors match design system spec |
| Live ticker scroll animation | Topbar Ticker | CSS animation timing is visual | Watch ticker for smooth scroll behavior |
| Three-column layout responsiveness | Layout Architecture | Fixed-width panels need visual check | Resize browser, verify no overflow or collapse |
| Feed card click -> triage form population | Intake Workflow | End-to-end UI interaction | Click feed card, verify all fields pre-filled |

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
