---
phase: 12
slug: bi-directional-dispatch-responder-communication
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-14
---

# Phase 12 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --compact tests/Feature/Communication/ -x` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact tests/Feature/Communication/ -x`
- **After every plan wave:** Run `php artisan test --compact`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 12-01-01 | 01 | 1 | COMM-01 | unit | `php artisan test --compact tests/Feature/Communication/MessageSentEventTest.php -x` | ✅ | ✅ green |
| 12-01-02 | 01 | 1 | COMM-02 | feature | `php artisan test --compact tests/Feature/Communication/IncidentMessageChannelTest.php -x` | ✅ | ✅ green |
| 12-01-03 | 01 | 1 | COMM-03 | feature | `php artisan test --compact tests/Feature/Communication/DispatchSendMessageTest.php -x` | ✅ | ✅ green |
| 12-01-04 | 01 | 1 | COMM-04 | feature | `php artisan test --compact tests/Feature/Communication/ResponderSendMessageTest.php -x` | ✅ | ✅ green |
| 12-01-05 | 01 | 1 | COMM-05 | feature | `php artisan test --compact tests/Feature/Communication/IncidentMessageChannelTest.php -x` | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Feature/Communication/MessageSentEventTest.php` — stubs for COMM-01
- [x] `tests/Feature/Communication/IncidentMessageChannelTest.php` — stubs for COMM-02, COMM-05
- [x] `tests/Feature/Communication/DispatchSendMessageTest.php` — stubs for COMM-03
- [x] `tests/Feature/Communication/ResponderSendMessageTest.php` — stubs for COMM-04

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Dispatch Messages section UI (collapsible, scroll, auto-expand) | CONTEXT decisions | Visual layout cannot be automated with Pest | Open dispatch console, select incident with messages, verify collapsible behavior |
| Responder ChatTab shows messages from other units | CONTEXT decisions | Requires 2+ browser sessions | Open 2 responder sessions assigned to same incident, send message from one, verify it appears in other |
| Audio notification cue on incoming message | CONTEXT decisions | Audio output cannot be automated | Send message from responder, verify subtle audio plays on dispatch when incident not selected |
| Unread badge on queue row and topbar count | CONTEXT decisions | Visual rendering + real-time state | Send message from responder, verify badge appears on queue row and MSGS count increments in topbar |

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
