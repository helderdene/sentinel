---
phase: 6
slug: integration-layer
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 6 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest v4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact --filter=ServiceTest` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=ServiceTest`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 06-01-01 | 01 | 1 | INTGR-01 | unit | `php artisan test --compact tests/Unit/IntegrationArchitectureTest.php` | ❌ W0 | ⬜ pending |
| 06-01-02 | 01 | 1 | INTGR-02 | unit | `php artisan test --compact tests/Unit/GeocodingServiceTest.php` | ✅ | ⬜ pending |
| 06-01-03 | 01 | 1 | INTGR-03 | unit | `php artisan test --compact tests/Unit/DirectionsServiceTest.php` | ❌ W0 | ⬜ pending |
| 06-01-04 | 01 | 1 | INTGR-04 | unit | `php artisan test --compact tests/Unit/SmsParserServiceTest.php` | ❌ W0 | ⬜ pending |
| 06-01-05 | 01 | 1 | INTGR-04 | feature | `php artisan test --compact tests/Feature/Intake/SmsWebhookTest.php` | ✅ | ⬜ pending |
| 06-02-01 | 02 | 1 | INTGR-05 | unit | `php artisan test --compact tests/Unit/WeatherServiceTest.php` | ❌ W0 | ⬜ pending |
| 06-02-02 | 02 | 1 | INTGR-06 | unit | `php artisan test --compact tests/Unit/HospitalEhrServiceTest.php` | ❌ W0 | ⬜ pending |
| 06-03-01 | 03 | 1 | INTGR-07 | unit | `php artisan test --compact tests/Unit/NdrrmcReportServiceTest.php` | ❌ W0 | ⬜ pending |
| 06-03-02 | 03 | 1 | INTGR-08 | unit | `php artisan test --compact tests/Unit/BfpSyncServiceTest.php` | ❌ W0 | ⬜ pending |
| 06-03-03 | 03 | 1 | INTGR-09 | unit | `php artisan test --compact tests/Unit/PnpBlotterServiceTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/IntegrationArchitectureTest.php` — verifies all interfaces resolve from container (INTGR-01)
- [ ] `tests/Unit/DirectionsServiceTest.php` — covers INTGR-03
- [ ] `tests/Unit/SmsParserServiceTest.php` — covers INTGR-04 interface retrofit
- [ ] `tests/Unit/WeatherServiceTest.php` — covers INTGR-05
- [ ] `tests/Unit/HospitalEhrServiceTest.php` — covers INTGR-06
- [ ] `tests/Unit/NdrrmcReportServiceTest.php` — covers INTGR-07
- [ ] `tests/Unit/BfpSyncServiceTest.php` — covers INTGR-08
- [ ] `tests/Unit/PnpBlotterServiceTest.php` — covers INTGR-09

---

## Manual-Only Verifications

*All phase behaviors have automated verification.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
