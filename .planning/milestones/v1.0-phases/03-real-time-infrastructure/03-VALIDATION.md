---
phase: 3
slug: real-time-infrastructure
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-13
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact tests/Feature/RealTime/ tests/Unit/BroadcastEventTest.php` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact tests/Feature/RealTime/ tests/Unit/BroadcastEventTest.php`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 01 | 1 | FNDTN-10 | feature | `php artisan test --compact tests/Feature/RealTime/RedisConfigTest.php` | ✅ | ✅ green |
| 03-01-02 | 01 | 1 | FNDTN-10 | feature | `php artisan test --compact tests/Feature/RealTime/HorizonAccessTest.php` | ✅ | ✅ green |
| 03-01-03 | 01 | 1 | FNDTN-09 | feature | `php artisan test --compact tests/Feature/RealTime/ChannelAuthorizationTest.php` | ✅ | ✅ green |
| 03-01-04 | 01 | 1 | FNDTN-09 | unit | `php artisan test --compact tests/Unit/BroadcastEventTest.php` | ✅ | ✅ green |
| 03-02-01 | 02 | 2 | FNDTN-09 | feature | `php artisan test --compact tests/Feature/RealTime/BroadcastIntegrationTest.php` | ✅ | ✅ green |
| 03-02-02 | 02 | 2 | -- | feature | `php artisan test --compact tests/Feature/RealTime/StateSyncTest.php` | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Feature/RealTime/ChannelAuthorizationTest.php` — stubs for FNDTN-09 channel auth
- [x] `tests/Feature/RealTime/RedisConfigTest.php` — stubs for FNDTN-10 Redis migration
- [x] `tests/Feature/RealTime/HorizonAccessTest.php` — stubs for FNDTN-10 Horizon access
- [x] `tests/Feature/RealTime/StateSyncTest.php` — stubs for state-sync endpoint
- [x] `tests/Feature/RealTime/BroadcastIntegrationTest.php` — stubs for event dispatch integration
- [x] `tests/Unit/BroadcastEventTest.php` — stubs for event classes, channels, payloads
- [x] Test env: `BROADCAST_CONNECTION=log`, `QUEUE_CONNECTION=sync` in phpunit.xml

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| WebSocket connection in browser | FNDTN-09 | Requires real Reverb server + browser | Start `php artisan reverb:start`, open app, verify Echo connects in console |
| Reconnecting indicator visible | FNDTN-09 | Visual/UX verification | Kill Reverb, observe banner appears; restart Reverb, observe banner clears |
| Events received within 500ms | FNDTN-09 | Timing requires live WebSocket | Create incident, measure time to client-side event in browser console |

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
