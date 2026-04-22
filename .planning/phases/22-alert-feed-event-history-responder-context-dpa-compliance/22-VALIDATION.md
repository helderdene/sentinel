---
phase: 22
slug: alert-feed-event-history-responder-context-dpa-compliance
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-22
---

# Phase 22 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 4 (PHP 8.4) + PHPUnit 12 |
| **Config file** | `phpunit.xml` (configured, RefreshDatabase + SQLite in-memory for feature tests) |
| **Quick run command** | `php artisan test --compact --group=fras` |
| **Full suite command** | `php artisan test --compact` |
| **Estimated runtime** | ~45 seconds (fras group), ~120 seconds (full suite) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --compact --filter={TestClass}`
- **After every plan wave:** Run `php artisan test --compact --group=fras`
- **Before `/gsd-verify-work`:** Full suite must be green (`php artisan test --compact`)
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

> Populated by the planner during PLAN.md creation. Each task row links an atomic implementation unit to the Pest test that proves it. Status flips to ✅ as tasks complete during execution.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 22-XX-XX | XX | X | REQ-{XX} | T-22-XX / — | {secure behavior or "N/A"} | unit / feature / browser | `php artisan test --compact --filter={Test}` | ⬜ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

**Coverage targets (derived from RESEARCH.md §Validation Architecture — 36-row requirement→test map):**

| Success Criterion | Requirement IDs | Pest File(s) |
|-------------------|-----------------|--------------|
| SC1 — Live alert feed + ack/dismiss + audio | ALERTS-01, ALERTS-02, ALERTS-03 | `tests/Feature/Fras/AlertFeedBroadcastTest.php`, `tests/Feature/Fras/AlertAckDismissTest.php`, `tests/Browser/FrasAlertsFeedTest.php` |
| SC2 — Event history filter + search + replay + promote | ALERTS-04, ALERTS-05, ALERTS-06, ALERTS-07 | `tests/Feature/Fras/EventHistoryFilterTest.php`, `tests/Feature/Fras/EventPromoteToIncidentTest.php`, `tests/Browser/FrasEventHistoryTest.php` |
| SC3 — Responder Person-of-Interest context | INTEGRATION-02 | `tests/Feature/Fras/ResponderSceneTabTest.php` |
| SC4 — Signed URLs + audit log | DPA-03, DPA-04 | `tests/Feature/Fras/SignedUrlSceneImageTest.php`, `tests/Feature/Fras/FrasAccessLogTest.php` |
| SC5 — Retention purge with active-incident protection | DPA-05 | `tests/Feature/Fras/RetentionPurgeTest.php` |
| SC6 — Privacy Notice + `docs/dpa/` templates | DPA-01, DPA-02, DPA-06 | `tests/Feature/Fras/PrivacyNoticeTest.php` + `docs/dpa/` artifact presence check |
| SC7 — 5 new gates + legal sign-off | DPA-07 | `tests/Feature/Fras/FrasGatesTest.php`, `tests/Feature/Fras/LegalSignoffTest.php` |

---

## Wave 0 Requirements

> Wave 0 is the set of test scaffolds, fixtures, and factories the phase depends on BEFORE implementation waves land. These must exist (at minimum as skipped stubs) before wave 1 begins so the Nyquist sampling loop has somewhere to write results.

- [ ] `tests/Feature/Fras/AlertFeedBroadcastTest.php` — stubs for ALERTS-01..03 (broadcast assertions via `Event::fake`)
- [ ] `tests/Feature/Fras/AlertAckDismissTest.php` — stubs for ALERTS-02, ALERTS-03
- [ ] `tests/Feature/Fras/EventHistoryFilterTest.php` — stubs for ALERTS-04..06
- [ ] `tests/Feature/Fras/EventPromoteToIncidentTest.php` — stub for ALERTS-07
- [ ] `tests/Feature/Fras/ResponderSceneTabTest.php` — stub for INTEGRATION-02
- [ ] `tests/Feature/Fras/SignedUrlSceneImageTest.php` — stub for DPA-03
- [ ] `tests/Feature/Fras/FrasAccessLogTest.php` — stub for DPA-04
- [ ] `tests/Feature/Fras/RetentionPurgeTest.php` — stub for DPA-05 (includes active-incident-protection assertion)
- [ ] `tests/Feature/Fras/PrivacyNoticeTest.php` — stub for DPA-01, DPA-02, DPA-06
- [ ] `tests/Feature/Fras/FrasGatesTest.php` — stub for DPA-07 (all 5 new gates)
- [ ] `tests/Feature/Fras/LegalSignoffTest.php` — stub asserting phase VALIDATION records CDRRMO legal sign-off
- [ ] `tests/Browser/FrasAlertsFeedTest.php` — Pest 4 browser stub (ack flow, audio trigger)
- [ ] `tests/Browser/FrasEventHistoryTest.php` — Pest 4 browser stub (filter + pagination)
- [ ] `database/factories/FrasAccessLogFactory.php` — new factory for audit table
- [ ] `database/factories/RecognitionEventFactory.php` — extend with `dismissed()`, `acknowledged()`, `with_incident()` states (if not already present from Phase 18)

*Verification: `php artisan test --compact --group=fras` runs (even if most cases are `->skip('Wave 0 stub')`) before Wave 1 commits.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Critical-severity audio cue plays in browser for logged-in operator | ALERTS-03 | Audio playback requires real browser + user-gesture unlock; Pest 4 browser driver cannot assert actual sound output | 1) Visit `/fras/alerts` as operator. 2) Trigger a Critical-severity `RecognitionAlertReceived` via tinker. 3) Confirm audible severity-distinct cue. 4) Confirm `useAlertSystem` used (single tone track, no overlap). |
| CDRRMO-branded Privacy Notice content is legally accurate | DPA-01, DPA-06 | Legal/compliance correctness is a human judgement — CDRRMO Data Privacy Officer reviews before go-live | CDRRMO Data Privacy Officer reviews `/privacy` page + `docs/dpa/PIA-template.md` content against RA 10173 text. Sign-off recorded in `fras:legal-signoff` CLI output. |
| `docs/dpa/signage-template` renders a usable CCTV-zone notice | DPA-06 | Print layout / dimensional correctness needs visual inspection | Generate template via `php artisan fras:generate-signage`, open rendered PDF/HTML, verify it prints on A4 with required disclosures (data controller, purpose, retention, contact). |
| Operator training notes are complete | DPA-06 | Pedagogical clarity requires a trainer's review | CDRRMO training lead reviews `docs/dpa/operator-training.md` against typical incident-response onboarding flow. |
| CDRRMO legal formal sign-off | DPA-07 | Human external approval | Legal team reviews Phase 22 implementation + `/privacy` page + `docs/dpa/` artifacts. Sign-off recorded by `php artisan fras:legal-signoff --signed-by="{name}" --date={ISO}` which appends to this VALIDATION.md and blocks milestone close until present. |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies documented
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING test files before Wave 1 commits
- [ ] No watch-mode flags (`--watch`) in test commands — CI parity
- [ ] Feedback latency < 60s on the `fras` group
- [ ] `nyquist_compliant: true` set in frontmatter after planner fills the Per-Task Verification Map
- [ ] CDRRMO legal sign-off recorded via `php artisan fras:legal-signoff` (DPA-07)

**Approval:** pending
