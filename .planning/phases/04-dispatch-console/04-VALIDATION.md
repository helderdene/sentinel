---
phase: 4
slug: dispatch-console
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=Dispatch` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact tests/Feature/Dispatch/`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 04-01-01 | 01 | 1 | DSPTCH-01 | feature | `php artisan test --compact tests/Feature/Dispatch/DispatchConsolePageTest.php` | ❌ W0 | ⬜ pending |
| 04-01-02 | 01 | 1 | DSPTCH-02 | feature | `php artisan test --compact tests/Feature/Dispatch/DispatchConsolePageTest.php` | ❌ W0 | ⬜ pending |
| 04-01-03 | 01 | 1 | DSPTCH-03 | feature | `php artisan test --compact tests/Feature/Dispatch/DispatchConsolePageTest.php` | ❌ W0 | ⬜ pending |
| 04-02-01 | 02 | 1 | DSPTCH-04 | unit | `php artisan test --compact tests/Unit/BroadcastEventTest.php --filter=UnitLocationUpdated` | ✅ | ⬜ pending |
| 04-02-02 | 02 | 2 | DSPTCH-05 | feature | `php artisan test --compact tests/Feature/Dispatch/UnitAssignmentTest.php` | ❌ W0 | ⬜ pending |
| 04-02-03 | 02 | 2 | DSPTCH-06 | feature | `php artisan test --compact tests/Feature/Dispatch/ProximityRankingTest.php` | ❌ W0 | ⬜ pending |
| 04-02-04 | 02 | 2 | DSPTCH-07 | feature | `php artisan test --compact tests/Feature/Dispatch/UnitAssignmentTest.php --filter=AssignmentPushed` | ❌ W0 | ⬜ pending |
| 04-03-01 | 03 | 3 | DSPTCH-08 | manual | Visual verification — client-side ack timer | N/A | ⬜ pending |
| 04-03-02 | 03 | 3 | DSPTCH-09 | manual | Audio verification — Web Audio API alerts | N/A | ⬜ pending |
| 04-03-03 | 03 | 3 | DSPTCH-10 | feature | `php artisan test --compact tests/Feature/Dispatch/DispatchConsolePageTest.php --filter=metrics` | ❌ W0 | ⬜ pending |
| 04-04-01 | 04 | 3 | DSPTCH-11 | feature | `php artisan test --compact tests/Feature/Dispatch/MutualAidTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Dispatch/DispatchConsolePageTest.php` — stubs for DSPTCH-01, DSPTCH-02, DSPTCH-03, DSPTCH-10
- [ ] `tests/Feature/Dispatch/UnitAssignmentTest.php` — stubs for DSPTCH-05, DSPTCH-07
- [ ] `tests/Feature/Dispatch/ProximityRankingTest.php` — stubs for DSPTCH-06
- [ ] `tests/Feature/Dispatch/MutualAidTest.php` — stubs for DSPTCH-11
- [ ] `tests/Feature/Dispatch/StatusAdvancementTest.php` — stubs for status transition validation

*Existing infrastructure covers test framework — Pest 4 already configured.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Ack timer countdown (90s) | DSPTCH-08 | Client-side timer with visual countdown — no backend logic | 1. Assign unit to incident 2. Verify 90s countdown appears 3. Verify timer escalation on expiry |
| Audio alerts per priority | DSPTCH-09 | Web Audio API — browser-only feature | 1. Create P1 incident — verify distinct tone 2. Create P2/P3/P4 — verify different tones 3. P1 triggers red screen flash |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
