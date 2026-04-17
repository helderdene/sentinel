---
phase: 9
slug: create-a-public-facing-reporting-app
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-13
---

# Phase 9 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP) |
| **Config file** | phpunit.xml |
| **Quick run command** | `php artisan test --compact --filter=CitizenReport` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=CitizenReport`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 09-01-01 | 01 | 1 | ADV-04 | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php` | ✅ | ✅ green |
| 09-01-02 | 01 | 1 | ADV-04.1 | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php` | ✅ | ✅ green |
| 09-01-03 | 01 | 1 | ADV-04.2 | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php` | ✅ | ✅ green |
| 09-01-04 | 01 | 1 | ADV-04.3 | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php` | ✅ | ✅ green |
| 09-01-05 | 01 | 1 | ADV-04.4 | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php` | ✅ | ✅ green |
| 09-01-06 | 01 | 1 | ADV-04.5 | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php` | ✅ | ✅ green |
| 09-02-01 | 02 | 1 | ADV-04.6 | unit | `php artisan test --compact tests/Unit/CitizenReportServiceTest.php` | ✅ | ✅ green |
| 09-02-02 | 02 | 1 | ADV-04.7 | unit | `php artisan test --compact tests/Unit/CitizenStatusMappingTest.php` | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Feature/CitizenReportTest.php` — stubs for citizen API endpoint tests (submit, track, types, rate limit, event dispatch)
- [x] `tests/Unit/CitizenReportServiceTest.php` — stubs for token generation, coordinate handling
- [x] `tests/Unit/CitizenStatusMappingTest.php` — stubs for status enum to citizen label mapping

*Existing Pest infrastructure covers framework setup.*

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Geolocation permission prompt | ADV-04.6 | Browser API requires real device | Open report-app on mobile, grant/deny location, verify form fallback |
| Dark mode rendering | Design | CSS prefers-color-scheme | Toggle OS dark mode, verify app theme changes |
| Mobile responsiveness | Design | Visual verification | Open on 390x844 viewport, verify layout matches prototype |
| localStorage persistence | Tracking | Browser storage API | Submit report, close tab, reopen, verify "My Reports" shows report |

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
