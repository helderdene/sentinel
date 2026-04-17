---
phase: 13
slug: pwa-setup
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-15
audited: 2026-04-17
---

# Phase 13 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php artisan test --compact --filter=Push` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=Push`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 13-01-01 | 01 | 1 | MOBILE-01 | smoke (manual) | Manual: browser DevTools Application tab | N/A | manual-only |
| 13-03-01 | 03 | 3 | MOBILE-02a | feature | `php artisan test --compact tests/Feature/PushSubscriptionTest.php` | exists | green |
| 13-03-02 | 03 | 3 | MOBILE-02b | feature | `php artisan test --compact tests/Feature/PushNotificationTest.php` | exists | green |
| 13-03-03 | 03 | 3 | MOBILE-02c | feature | `php artisan test --compact tests/Feature/PushNotificationTest.php` | exists | green |
| 13-03-04 | 03 | 3 | MOBILE-02d | feature | `php artisan test --compact tests/Feature/AckTimeoutPushTest.php` | exists | green |
| 13-03-05 | 03 | 3 | MOBILE-02e | unit | `php artisan test --compact tests/Unit/WebPushConfigTest.php` | exists | green |

*Status: pending / green / red / flaky / manual-only*

---

## Wave 0 Requirements

- [x] `tests/Feature/PushSubscriptionTest.php` — 5 tests covering MOBILE-02a (subscription CRUD)
- [x] `tests/Feature/PushNotificationTest.php` — 5 tests covering MOBILE-02b, MOBILE-02c (push on events)
- [x] `tests/Feature/AckTimeoutPushTest.php` — 3 tests covering MOBILE-02d (ack timeout delayed job)
- [x] `tests/Unit/WebPushConfigTest.php` — 3 tests covering MOBILE-02e (VAPID config presence)

All 16 tests pass (27 assertions, 1.18s). Created in Plan 03 Task 2 (wave 3, commit 71f59f3).

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Service worker registers and manifest served | MOBILE-01 | Browser-only API, no PHP test surface | Open app in Chrome -> DevTools -> Application tab -> verify SW active and manifest loaded |
| PWA installable | MOBILE-01 | Browser install prompt is UI-only | Chrome -> address bar install icon appears -> click -> app opens standalone |
| Offline banner shows | MOBILE-01 | Requires network toggle in browser | DevTools -> Network -> Offline -> verify ConnectionBanner appears in layout |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 15s (actual: 1.18s)
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved (2026-04-17)

---

## Validation Audit 2026-04-17

| Metric | Count |
|--------|-------|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |

**Audit outcome:** All MOBILE-02 sub-requirements covered by 16 automated Pest tests (5 PushSubscription + 5 PushNotification + 3 AckTimeoutPush + 3 WebPushConfig). MOBILE-01 remains manual-only by design — browser-level PWA install, service worker update banner, and offline-ready toast require real browser environment and are not automatable without headless Chrome harness. Phase 13 is Nyquist-compliant.
