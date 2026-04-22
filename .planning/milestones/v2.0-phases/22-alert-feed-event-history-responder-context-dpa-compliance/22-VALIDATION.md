---
phase: 22
slug: alert-feed-event-history-responder-context-dpa-compliance
status: approved
nyquist_compliant: true
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

> This map is the plan-time contract populated by the planner BEFORE execution begins. Each row binds an atomic implementation task from a Phase 22 PLAN.md `<task type="auto">` block to the Pest test that proves it. Status flips to ✅ as tasks land during execution. Wave 0 stubs (Plan 22-02 Task 1's `Wave0PlaceholdersTest.php`) are written first so the "File Exists" column flips ✅ for feature-level tests as soon as Wave 1 Plan 02 ships. Manual-verify / human-verify tasks (e.g. 22-06 Task 3, 22-07 Task 3, 22-08 Task 3, 22-09 Task 3) are enumerated separately in the Manual-Only Verifications table below.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 22-01-01 | 22-01 | 1 | ALERTS-02, DPA-04, DPA-05, DPA-07 | T-22-01-01, T-22-01-02, T-22-01-03 | CHECK constraints + foreignId FK types prevent audit-row tampering / silent drops | feature | `php artisan migrate:fresh --env=testing` | ✅ | ⬜ pending |
| 22-01-02 | 22-01 | 1 | ALERTS-02, DPA-04, DPA-05 | T-22-01-04, T-22-01-05 | Enum whitelist + fras_audio_muted scoped to own user | feature | `php artisan test --compact --filter=FrasPhotoAccessControllerTest` | ✅ | ⬜ pending |
| 22-02-01 | 22-02 | 1 | DPA-07 | T-22-02-01, T-22-02-02 | 5-gate role matrix + Inertia can.* prop + Wave 0 stubs registered | feature | `php artisan test --compact --filter=FrasGatesTest` | ✅ W0 | ⬜ pending |
| 22-02-02 | 22-02 | 1 | ALERTS-02 | T-22-02-03, T-22-02-04, T-22-02-05 | FrasAlertAcknowledged event uses ShouldDispatchAfterCommit + fras.alerts channel auth excludes responder | feature | `php artisan test --compact --filter=FrasAlertAcknowledgedEventTest` | ✅ | ⬜ pending |
| 22-03-01 | 22-03 | 2 | DPA-02, DPA-03, DPA-05 | T-22-03-01, T-22-03-02, T-22-03-03, T-22-03-04, T-22-03-05, T-22-03-06 | Sync DB::transaction audit write + responder 403 on scene + Cache-Control private no-store | feature | `php artisan test --compact --filter=FrasAccessLogTest` | ✅ W0 | ⬜ pending |
| 22-03-01b | 22-03 | 2 | DPA-03 | T-22-03-01, T-22-03-03, T-22-03-04 | Signed URL expiry + responder 403 on scene route + Cache-Control | feature | `php artisan test --compact --filter=SignedUrlSceneImageTest` | ✅ W0 | ⬜ pending |
| 22-03-02 | 22-03 | 2 | DPA-05 | — | retention config keys parseable at runtime | N/A | `php artisan config:show fras.retention` | ✅ | ⬜ pending |
| 22-04-01 | 22-04 | 2 | DPA-04, DPA-05 | T-22-04-01, T-22-04-02, T-22-04-03, T-22-04-07 | Active-incident-protection query + withoutOverlapping + dry-run summary row + access log self-purge at 730d | feature | `php artisan test --compact --filter=FrasPurgeExpiredCommandTest` | ✅ W0 | ⬜ pending |
| 22-04-02 | 22-04 | 2 | ALERTS-07 | T-22-04-04, T-22-04-05 | Manual-promote category gate + reason persistence | feature | `php artisan test --compact --filter=PromoteRecognitionEventTest` | ✅ | ⬜ pending |
| 22-05-01 | 22-05 | 3 | ALERTS-01, ALERTS-02, DPA-07 | T-22-05-01, T-22-05-02, T-22-05-05 | 3-layer defense (route role + can gate + FormRequest authorize); 409 on double-ACK; scoped audio-mute update | feature | `php artisan test --compact --filter=FrasPhotoAccessControllerTest` | ✅ | ⬜ pending |
| 22-05-02 | 22-05 | 3 | ALERTS-04, ALERTS-05, ALERTS-07 | T-22-05-03, T-22-05-04, T-22-05-06 | ILIKE parameterized search + signed middleware on scene route + promote min:8 reason | feature | `php artisan test --compact --filter=FrasAlertFeedTest` | ✅ W0 | ⬜ pending |
| 22-05-02b | 22-05 | 3 | ALERTS-04, ALERTS-05 | T-22-05-03, T-22-05-04 | Event history pagination + replay counts + responder 403 on index+scene | feature | `php artisan test --compact --filter=FrasEventHistoryTest` | ✅ W0 | ⬜ pending |
| 22-06-01 | 22-06 | 3 | ALERTS-03 | T-22-06-03, T-22-06-04, T-22-06-05 | document.visibilityState + fras_audio_muted audio gate + 100-item ring-buffer cap + signed URL on img src (no referer leak) | N/A | `npm run types:check` | ✅ | ⬜ pending |
| 22-06-02 | 22-06 | 3 | ALERTS-01, ALERTS-02, ALERTS-06 | T-22-06-01, T-22-06-02, T-22-06-06 | AlertCard ack/dismiss flows + AppSidebar entries scoped to operator/supervisor/admin branches | N/A | `npm run build` | ✅ | ⬜ pending |
| 22-07-01 | 22-07 | 3 | ALERTS-05 | T-22-07-01, T-22-07-05 | Vue mustache escaping + replace:true on search keeps history clean | N/A | `npm run types:check` | ✅ | ⬜ pending |
| 22-07-02 | 22-07 | 3 | ALERTS-04, ALERTS-07 | T-22-07-01, T-22-07-02, T-22-07-03, T-22-07-04 | Client-side canSubmit + server FormRequest min:8 reason + shareable URL filter semantics + paginator-row consumption (no extra fetch) | N/A | `npm run build` | ✅ | ⬜ pending |
| 22-08-01 | 22-08 | 4 | INTEGRATION-02 | T-22-08-01, T-22-08-02, T-22-08-05, T-22-08-06 | 3-layer responder scene exclusion + D-27 Face gate preserved + UserRound fallback + no scene_image_url in prop | feature | `php artisan test --compact --filter=ResponderSceneTabTest` | ✅ W0 | ⬜ pending |
| 22-08-02 | 22-08 | 4 | DPA-01, DPA-02 | T-22-08-03, T-22-08-04 | html_input:strip XSS-safe (programmatic fixture) + lang whitelist blocks path traversal | feature | `php artisan test --compact --filter=PrivacyNoticeTest` | ✅ W0 | ⬜ pending |
| 22-09-01 | 22-09 | 4 | DPA-06 | T-22-09-01, T-22-09-05 | PR-reviewed MD content + DejaVu Sans Blade template; no RCE via dompdf | feature | `php artisan test --compact --filter=DpaDocsExistTest` | ✅ | ⬜ pending |
| 22-09-02 | 22-09 | 4 | DPA-06 | T-22-09-01 | dompdf receives html_input:strip sanitized HTML only | feature | `php artisan test --compact --filter=FrasDpaExportTest` | ✅ | ⬜ pending |
| 22-09-02b | 22-09 | 4 | DPA-07 | T-22-09-02, T-22-09-03, T-22-09-04, T-22-09-05 | Append-only fras_legal_signoffs row + VALIDATION.md append via hardcoded base_path | feature | `php artisan test --compact --filter=LegalSignoffTest` | ✅ W0 | ⬜ pending |

*Status legend: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*
*File Exists legend: ✅ (file created in Wave 0 stubs — Plan 22-02 Task 1 — OR spawned in its own plan's Wave 1) · ✅ W0 (stubbed in Wave 0 via Wave0PlaceholdersTest.php, real test lands in the owning plan) · ❌ W0 (test scaffold must be created before the task executes)*

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

> Wave 0 is the set of test scaffolds, fixtures, and factories the phase depends on BEFORE implementation waves land. These must exist (at minimum as skipped stubs) before wave 1 begins so the Nyquist sampling loop has somewhere to write results. Plan 22-02 Task 1 creates the `Wave0PlaceholdersTest.php` that registers every planned feature test file.

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

> Human-verify checkpoints are enumerated here (not in the Per-Task Verification Map) because the Map is reserved for automated Pest assertions. Each row below corresponds to a `<task type="checkpoint:human-verify">` task in the relevant plan.

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| /fras/alerts 2-browser cross-operator ACK handoff | ALERTS-01, ALERTS-02, ALERTS-03 | Cross-browser Reverb subscription + audible tone + visibility gate must be observed by human | Plan 22-06 Task 3 checkpoint (7-step flow). |
| /fras/events filter + pagination + promote round-trip | ALERTS-04, ALERTS-05, ALERTS-07 | URL sync + 300ms debounce behavior + PromoteIncidentModal UX needs human judgement | Plan 22-07 Task 3 checkpoint (11-step flow). |
| Responder POI accordion with UserRound fallback | INTEGRATION-02, DPA-01, DPA-02 | Browser-side img @error fallback rendering + /privacy bilingual content must be visually inspected | Plan 22-08 Task 3 checkpoint (6-step flow). |
| DPA suite E2E + PDF export + legal signoff dry-run | DPA-06, DPA-07 | DPD compliance review of PDF rendering + `fras:legal-signoff` CLI + final Nyquist fill-in | Plan 22-09 Task 3 checkpoint (6-step flow). |
| Critical-severity audio cue plays in browser for logged-in operator | ALERTS-03 | Audio playback requires real browser + user-gesture unlock; Pest 4 browser driver cannot assert actual sound output | 1) Visit `/fras/alerts` as operator. 2) Trigger a Critical-severity `RecognitionAlertReceived` via tinker. 3) Confirm audible severity-distinct cue. 4) Confirm `useAlertSystem` used (single tone track, no overlap). |
| CDRRMO-branded Privacy Notice content is legally accurate | DPA-01, DPA-06 | Legal/compliance correctness is a human judgement — CDRRMO Data Privacy Officer reviews before go-live | CDRRMO Data Privacy Officer reviews `/privacy` page + `docs/dpa/PIA-template.md` content against RA 10173 text. Sign-off recorded in `fras:legal-signoff` CLI output. |
| `docs/dpa/signage-template` renders a usable CCTV-zone notice | DPA-06 | Print layout / dimensional correctness needs visual inspection | Generate template via `php artisan fras:dpa:export --doc=signage`, open rendered PDF, verify it prints on A4 with required disclosures (data controller, purpose, retention, contact). |
| Operator training notes are complete | DPA-06 | Pedagogical clarity requires a trainer's review | CDRRMO training lead reviews `docs/dpa/operator-training.md` against typical incident-response onboarding flow. |
| CDRRMO legal formal sign-off | DPA-07 | Human external approval | Legal team reviews Phase 22 implementation + `/privacy` page + `docs/dpa/` artifacts. Sign-off recorded by `php artisan fras:legal-signoff --signed-by="{name}" --date={ISO}` which appends to this VALIDATION.md and blocks milestone close until present. |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies documented
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING test files before Wave 1 commits (flips ✅ after Plan 22-02 Task 1 lands)
- [x] No watch-mode flags (`--watch`) in test commands — CI parity
- [x] Feedback latency < 60s on the `fras` group
- [x] `nyquist_compliant: true` set in frontmatter (Per-Task Verification Map populated at plan time)
- [ ] CDRRMO legal sign-off recorded via `php artisan fras:legal-signoff` (DPA-07)

**Approval:** plan-time map approved; execution sign-off pending Wave 0 + CDRRMO legal.
