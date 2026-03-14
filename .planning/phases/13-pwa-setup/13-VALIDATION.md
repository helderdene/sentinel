---
phase: 13
slug: pwa-setup
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-15
---

# Phase 13 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php artisan test --compact --filter=Pwa` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter=Pwa`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 13-01-01 | 01 | 1 | MOBILE-01 | smoke (manual) | Manual: browser DevTools Application tab | N/A | ⬜ pending |
| 13-02-01 | 02 | 1 | MOBILE-02a | feature | `php artisan test --compact tests/Feature/PushSubscriptionTest.php` | ❌ W0 | ⬜ pending |
| 13-02-02 | 02 | 1 | MOBILE-02b | feature | `php artisan test --compact tests/Feature/PushNotificationTest.php` | ❌ W0 | ⬜ pending |
| 13-02-03 | 02 | 1 | MOBILE-02c | feature | `php artisan test --compact tests/Feature/PushNotificationTest.php` | ❌ W0 | ⬜ pending |
| 13-02-04 | 02 | 1 | MOBILE-02d | feature | `php artisan test --compact tests/Feature/AckTimeoutPushTest.php` | ❌ W0 | ⬜ pending |
| 13-02-05 | 02 | 2 | MOBILE-02e | unit | `php artisan test --compact tests/Unit/WebPushConfigTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/PushSubscriptionTest.php` — stubs for MOBILE-02a (subscription CRUD)
- [ ] `tests/Feature/PushNotificationTest.php` — stubs for MOBILE-02b, MOBILE-02c (push on events)
- [ ] `tests/Feature/AckTimeoutPushTest.php` — stubs for MOBILE-02d (ack timeout delayed job)
- [ ] `tests/Unit/WebPushConfigTest.php` — stubs for MOBILE-02e (VAPID config presence)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Service worker registers and manifest served | MOBILE-01 | Browser-only API, no PHP test surface | Open app in Chrome → DevTools → Application tab → verify SW active and manifest loaded |
| PWA installable | MOBILE-01 | Browser install prompt is UI-only | Chrome → address bar install icon appears → click → app opens standalone |
| Offline banner shows | MOBILE-01 | Requires network toggle in browser | DevTools → Network → Offline → verify banner appears |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
