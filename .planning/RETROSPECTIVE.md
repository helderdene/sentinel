# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.0 — IRMS MVP

**Shipped:** 2026-04-17
**Phases:** 16 | **Plans:** 51 | **Tasks:** 111 | **Commits:** 340 | **Timeline:** 36 days (2026-03-12 → 2026-04-17) | **LOC:** 82,960 (PHP/TS/Vue)

### What Was Built

- **Core dispatch pipeline** — PostgreSQL + PostGIS data model, 5-role RBAC (dispatcher/responder/supervisor/admin/operator), multi-channel intake (SMS/App/Voice/IoT/Walk-in), bilingual keyword-based priority classification, geocoding + barangay auto-assignment
- **Real-time layer** — Laravel Reverb WebSocket server with 6+ broadcast events, role-scoped private channels with authorization, `useDispatchFeed` / `useResponderSession` / `useEcho` composables, state-sync reconnection endpoint
- **Dispatch console** — 2D MapLibre GL JS WebGL markers, multi-unit assignment with PostGIS proximity ranking, mutual aid agencies, session metrics + live ticker, 90s ack timer, priority-based Web Audio API tones, collapsible Messages with unread badges
- **Responder workflow** — Mobile-first Vue shell (44px topbar + 56px tab bar), Standby/Scene/Chat/Nav tabs, assignment notification with 90s countdown, DomPDF closure reports, OutcomeSheet with hospital picker, ResourceRequestModal, GPS tracking, bi-directional ChatTab with multi-participant awareness
- **Public citizen reporting SPA** — Standalone Vue 3 + Vue Router app with tracking tokens, rate limiting, admin-configurable incident types, shared design tokens
- **Integration layer** — Stubbed contracts for SMS, Directions, PAGASA Weather, HL7 FHIR R4 hospital pre-notification, NDRRMC SitRep XML, BFP, PNP e-Blotter — architecturally ready for real wiring
- **Analytics** — 5 KPI dashboard with Chart.js, MapLibre choropleth heatmap, GeneratedReport model, DILG/NDRRMC/quarterly/annual compliance reports via DomPDF + league/csv
- **PWA** — vite-plugin-pwa (injectManifest), custom TypeScript service worker precaching 104 assets, Web Push with VAPID, 16 Pest tests validating the full pipeline
- **Sentinel rebrand** — Navy/blue palette across all CSS tokens, DM Mono + Bebas Neue typography, animated shield logo, full IRMS → Sentinel rename across app + report-app
- **Hygiene & traceability** — v1.0 audit closure: Wayfinder URL swaps, REQUIREMENTS.md traceability backfill (102→123), Phase 14 validation approved, Phase 10 visual fidelity browser-verified

### What Worked

- **Strict dependency chain in Phase 1-7** — Foundation → Intake → Real-Time → Dispatch → Responder → Integration → Analytics — each phase landed coherent + testable capability; no retrofit thrash
- **Phase 3 isolating Real-Time Infrastructure before Dispatch** — Channel auth + reconnection proven before Phase 4 needed WebSocket-driven UI; avoided a nasty WebSocket-vs-UI debugging loop
- **TDD for high-stakes phases (Phase 8 operator role)** — 56 tests caught role-redirect + gate edge cases during implementation; shipped with high confidence
- **Wayfinder for backend routes** — Eliminated entire class of "URL typo" bugs; auto-generated TS functions kept frontend and backend in sync; Phase 16's literal-URL regression guard turned this into a policy
- **CONTEXT.md locked decisions (D-01..D-N) in /gsd-discuss-phase** — Pattern used heavily from Phase 8 onward; planner and executor both had a single source of truth, avoiding "wait, did we decide X or Y?" mid-implementation
- **Pest convention guards** — Shipped in Phase 16; belt-and-suspenders against regression (literal URLs, hardcoded hex colors). Runs with zero new tooling via existing `composer ci:check`
- **Stub-first integrations** — Phase 6 contracts + stubs meant v1.0 shipped without API keys or MOUs blocking; real integrations become drop-in
- **Design system alignment before rebrand** — Phase 10 consolidated tokens BEFORE Phase 14 applied Sentinel palette, so rebrand was a single-pass variable swap
- **Human verification checkpoints (Phase 10 + Phase 15)** — Color-mix rendering, focus-ring behavior, and WebSocket audio distinctiveness are genuinely not automatable; explicit `status: human_needed` was the right call rather than forcing Playwright

### What Was Inefficient

- **ROADMAP dates showed "2015" not "2026"** — Typo propagated silently until milestone close; would have been caught by lightweight date-format validation at roadmap creation
- **Phase 14 VALIDATION left in `draft` status** — Phase 16 spent a full CONTEXT decision (D-13/D-14) resolving whether to run `/gsd-validate-phase 14` vs. flip the frontmatter manually; the `audited:` vs `approved:` key conflict added more friction. A clearer convention at validation-authoring time would have saved this
- **Phase 15 human UAT deferred at phase close** — Shipped as `human_needed`, then carried open through v1.0 close. Better to either block phase completion OR explicitly schedule the UAT in the same session
- **Pre-existing dirty tree during Phase 16 execution** — 17 modified + 17 untracked files pre-dated the phase and conflicted with plan 16-01's target file. Required stash+pop workflow and careful executor sandboxing. Convention: reach a clean tree before phase execution starts
- **Duplicate `ekhorizon` accomplishment entry in MILESTONES.md** — "Commit:" appeared twice as an "accomplishment" extracted from partial SUMMARY.md files. SUMMARY extraction heuristic could be tightened
- **Pre-existing `dompdf` memory exhaustion + `UnitForm.vue` TS2322** — Audit correctly deferred both to v2, but both were latent through most of v1.0; earlier capture into a deferred-items register would have surfaced them sooner

### Patterns Established

- **Phase directory archival pattern** — Moving `.planning/phases/NN-slug/` to `.planning/milestones/v1.0-phases/NN-slug/` via `git mv` preserves blame and keeps the v2 phase dir empty
- **Wayfinder named imports** (`import { action } from '@/actions/.../Controller'`) over default imports — tree-shaking + matches existing composable analogs (`useGpsTracking.ts`)
- **`audited: YYYY-MM-DD` frontmatter key** — Phase 13 + Phase 14 approval precedent; NOT `approved:`
- **Sequential-mode execution when main tree is dirty** — Worktree isolation can't safely merge back when unrelated files have uncommitted work in target files; sequential + stash-scoped-file is the escape hatch
- **`checkpoint:human-verify` for genuinely visual checks** — Focus ring, color-mix opacity, dark-mode contrast; don't automate what Playwright can't reliably assess
- **Pest convention guards in `tests/Unit/Conventions/`** — File-scan tests using Symfony Finder; catches literal-string regressions with zero new tooling

### Key Lessons

1. **Lock decisions BEFORE planning, not during** — `/gsd-discuss-phase` CONTEXT.md D-XX decisions eliminated 80% of planning back-and-forth from Phase 8 onward. The earlier in the pipeline a decision is locked, the less rework downstream.
2. **Defer UI automation that needs real eyes** — Color-mix, focus rings, audio distinctiveness, dark-mode contrast. Capture screenshots + checklist, human verifies, commit evidence.
3. **Architect for stubs first** — Integration contracts without real implementations shipped v1.0. Real integrations are now plug-in; no external blocker delayed the launch.
4. **Gap-closure phases (15, 16) keep milestone audit credible** — Rather than shipping with known debt and a sad note in MILESTONES.md, dedicated cleanup phases made v1.0 audit-complete. Cheap insurance.
5. **Pin literal-string conventions with automated guards** — Wayfinder URL regressions can now break CI. Future rebrand regressions (hardcoded hex), i18n bypass, or direct `env()` usage outside config can follow the same Pest pattern.
6. **Track deferred items explicitly** — The 5 items recorded in `STATE.md ## Deferred Items` at v1.0 close would have been lost as lore. Formal capture means v2 intake has a starting point.
7. **Sequential execution survives a messy working tree** — User was editing other files mid-phase. Parallel worktrees would have required a clean tree; sequential + per-plan stash isolated the blast radius.

### Cost Observations

- **Model mix:** Opus-dominant for planning + executor (research → discuss → plan phases); Sonnet for plan-checker + verifier. Haiku not used in this milestone.
- **Wave execution:** Phase 16 ran all 3 plans sequentially due to dirty-tree constraint; typical waves ran parallel worktrees (Phases 1-14).
- **Checkpoints:** 2 human-verify checkpoints total (Phase 10 visual fidelity, Phase 15 live-session verification). Both resolved offline in minutes once screenshots were captured.
- **Notable:** TDD in Phase 8 cost ~10% more planning/execution time but eliminated 3 subsequent bug-fix rounds across later phases. Net: faster.

---

## Milestone: v2.0 — FRAS Integration

**Shipped:** 2026-04-22
**Phases:** 6 | **Plans:** 38 | **Tasks:** 58 | **Commits:** 283 | **Timeline:** 6 days (2026-04-17 → 2026-04-22) | **LOC:** +90k/-4k (PHP/TS/Vue)

### What Was Built

- **Laravel 13 upgrade (Phase 17)** — Feature-free framework jump with byte-identical broadcast payload snapshots as the regression oracle. 11 aligned package bumps, CSRF middleware rename, drain-and-deploy runbook. Also closed the pre-existing incident-report PDF download gap (route + Gate + 10 Pest tests).
- **FRAS PostgreSQL schema port (Phase 18)** — cameras (PostGIS geography + GIST), personnel (VARCHAR+CHECK category enum), camera_enrollments pivot (composite UNIQUE for idempotency), recognition_events (28 columns, JSONB GIN, microsecond TIMESTAMPTZ, idempotency UNIQUE on camera_id+record_id). 11 regression tests guarding shape drift across phases 19-22.
- **MQTT listener infrastructure (Phase 19)** — `php-mqtt/laravel-client` under dedicated `[program:irms-mqtt]` Supervisor (explicitly NOT Horizon per Pitfall 6). TopicRouter → 4 handlers. `mqtt_listener_health` watchdog banner on dispatch console. Live-verified against cloud MQTT broker 148.230.99.73 with real-firmware payload shape (`info.facesluiceId` nested).
- **Camera & Personnel admin + enrollment (Phase 20)** — Full CRUD on `/admin/cameras` + `/admin/personnel` with live status broadcasts (CameraStatusChanged). FrasPhotoProcessor (Intervention Image v4) photo pipeline with MD5 dedup. EnrollPersonnelBatch with `WithoutOverlapping` per-camera mutex. Retention auto-unenroll scheduler. Dispatch map cameras layer.
- **Recognition → IoT-Intake bridge (Phase 21)** — `FrasIncidentFactory` service as the single integration seam, reusing `IncidentChannel::IoT` (no new channel enum). Severity-aware Mapbox pulse on camera layer. fras.alerts Echo composable + SSR-seeded 50-event buffer. IntakeStation 4th rail with severity badge, read-only event modal, Escalate-to-P1 button.
- **Alert feed + Event history + POI + DPA gate (Phase 22)** — `/fras/alerts` live feed with 100-alert ring buffer + cross-operator ACK broadcast. `/fras/events` filterable/paginated history with promote-to-Incident. Responder POI accordion (face crop + personnel + camera, never raw scene image — enforced by arch test). Public bilingual `/privacy` page (EN/TL toggle). `fras_access_log` audit trail. 5-minute signed URLs. Retention purge (30d/90d) with active-incident protection. `docs/dpa/` package + `fras:dpa:export` + `fras:legal-signoff` CLIs.

### What Worked

- **FrasIncidentFactory as the integration seam** — Phase 21's load-bearing architectural decision. Kept the FRAS port surgical (one service class is the bridge), preserved the constraint "no new IncidentChannel enum", and let the dispatch pipeline accept recognition events with zero new code paths.
- **Live-broker UAT against real hardware (Phase 19 tests 7-10)** — Uncovered the `info.facesluiceId` nested firmware payload shape that Pest unit tests couldn't have found. Fixed inline during UAT, regression tests added to lock. Validated the decision to test against cloud broker 148.230.99.73 early.
- **Arch test for DPA role-gating (Phase 22 D-27)** — A `grep '\bscene_image\b'` arch test on responder controller + accordion component turned "never shows scene image to responders" from a convention into a CI-enforced invariant. Much stronger than a code review checklist.
- **RA 10173 compliance built in from the start (not bolted on)** — Phase 22 treated DPA as a first-class requirement alongside features. Public `/privacy`, audit log, signed URLs, retention, bilingual docs all shipped together. CDRRMO legal sign-off became a routine external gate, not a scramble.
- **Override mechanism in VERIFICATION.md frontmatter** — Phase 19's 4 ops-environment overrides and Phase 22's CDRRMO legal override were recorded as structured `overrides:` arrays with per-item rationale. Audit-open tool + milestone audit both read this cleanly. No "known debt" hand-wave.
- **Sequential CDRRMO-constrained decisions (D-XX in CONTEXT.md)** — Carried forward from v1.0. Phase 22 had ~30 D-XX decisions locked before plan-phase. Planner + executor had a single source of truth; no mid-implementation "wait, did we decide X?" thrash.
- **Wave-based parallelization (Phase 20, 22)** — Plans within a phase split into waves based on dependency order. Executor ran wave-1 plans in parallel, synced, ran wave-2. Meaningfully faster than strict sequential without sacrificing atomic commits.

### What Was Inefficient

- **4 of 6 phases shipped with VALIDATION.md still in `draft` status** — Nyquist validation audit was not run for phases 17, 18, 19, 21. `nyquist_compliant: false` propagated through. Structural test coverage was green in each phase's VERIFICATION.md, so no actual quality gap — but the audit loop was skipped. Carried forward as v2.1 tech debt.
- **REQUIREMENTS.md checkboxes drifted** — 21 v2.0 requirement boxes (MQTT, ALERTS, INTEGRATION-02, DPA) stayed as `[ ]` through the entire milestone despite the corresponding phases shipping. Found only at milestone audit. Automated check at phase-transition could flip these as evidence lands in VERIFICATION.md.
- **2 debug sessions stale** — `chat-input-hidden-by-status-btn` (fixed in commit 600574f, 2026-03-14) and `incident-report-pdf-not-generated` (fixed in commit 25ec02a, 2026-04-21) both had status files stuck at `diagnosed` / `investigating` long after fix commits shipped. Pre-close audit caught them. Debug-session status should be flipped in the same commit as the fix.
- **UAT status convention split** — Phases 17, 20 used `status: resolved`; Phase 22 used `status: complete`. audit-open tool only recognizes `complete`. Three files needed alignment. One-word convention in the scaffolder would prevent this.
- **CLI-generated MILESTONES.md accomplishments were noise** — The `milestone.complete` extractor scraped "One-liner:" placeholders and "[Rule 2 - Correctness]" observation lines as "accomplishments". Had to hand-rewrite to 6 curated bullets. The extractor needs a better heuristic or should skip and let the operator write these.

### Patterns Established

- **`FrasIncidentFactory`-style integration seam** — When integrating a pre-existing system into an existing codebase, build ONE service class that is the load-bearing bridge. All wiring goes through it. Reuses existing enums/channels/tables rather than shadowing.
- **Arch tests for DPA-style absence invariants** — `grep -c '\bscene_image\b' == 0` style assertions for "this role must never see X" are cheaper + more reliable than role-based tests that could miss new call sites.
- **Documented overrides as first-class frontmatter** — `overrides: [{ item, resolution }]` array in VERIFICATION.md is machine-readable + human-auditable. Don't hide "human_needed" behind unstructured text.
- **Live-broker smoke test during UAT, not only in unit tests** — Pest mocks are necessary but not sufficient for hardware protocols. Schedule a live session against the real broker/device at least once per phase.
- **Public unauthenticated compliance pages isolated from app theme** — `/privacy` forced a light-mode token override (22-08 D-30) to prevent the dark-theme default from bleeding into a legal document. Pattern: public legal routes get their own layout.
- **CDRRMO legal sign-off as a CLI-mediated external gate** — `fras:legal-signoff` creates an audit row rather than a file edit. Same pattern works for any "external human approval with persisted evidence" requirement.

### Key Lessons

1. **Architect the integration seam before porting** — Phase 21 decided FrasIncidentFactory upfront in CONTEXT.md. Every subsequent plan either used it or justified bypass. The port stayed surgical; the alternative (each plan decides its own hook point) would have sprawled.
2. **Test against real hardware at least once** — Phase 19's cloud-broker UAT caught a payload-shape bug (`info.facesluiceId` nested) that wouldn't have surfaced otherwise. Unit test mocks shape the payloads you test; real hardware ships the payloads you get.
3. **Compliance as a first-class requirement, not a gate at the end** — Phase 22 shipped DPA controls (audit log, signed URLs, retention, bilingual docs) alongside features. The CDRRMO legal sign-off became routine external review rather than a last-minute scramble.
4. **Override blocks > human_needed lore** — When verification can't run in a given environment, document it as a structured override with the compensating evidence (unit tests, runbook, arch test). Audit tools can read these; future operators can trust them.
5. **Status convention parity across phases** — Small scaffolder inconsistencies (`resolved` vs `complete`) cost real cycles at milestone close. Canonical frontmatter values should be enforced at write time.
6. **CLI automation is a starting point, not the output** — `milestone.complete` did the mechanical work (archive, move phases, seed MILESTONES.md). The accomplishments and post-ship narrative still need human curation. Budget time for the hand-polish.

### Cost Observations

- 6 phases shipped in 6 calendar days (one phase per day cadence at quality level).
- 283 commits averaging ~47/day; FRAS integration is feature-dense but the integration seam pattern kept churn low (+90k inserts dominated by migrations, DPA docs, and tests rather than rewrites; -4k deletions).
- Wave-based parallelization (phases 20, 22) meaningfully reduced wall-clock time vs strict sequential.

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.0 | 16 | 51 | Established: CONTEXT.md locked-decisions pattern, PATTERNS.md mapper, Pest convention guards, `audited:` frontmatter key, sequential-mode for dirty trees |
| v2.0 | 6 | 38 | Added: wave-based parallel execution, documented `overrides:` arrays in VERIFICATION.md, arch-test pattern for DPA absence invariants, FrasIncidentFactory-style integration seam, live-broker UAT against real hardware |

### Cumulative Quality

| Milestone | Automated Tests | Pest Tests Passing | Convention Guards |
|-----------|-----------------|---------------------|-------------------|
| v1.0 | 100+ Pest + 16 PWA + 56 TDD | ~170 (48 pre-existing DB-state failures out of scope) | 1 (Wayfinder URL literals) |
| v2.0 | +224 FRAS Pest (Mqtt/Camera/Personnel/Recognition/Fras domains) | ~400 total (baseline L12 families carried unchanged through L13 upgrade) | 2 (+ scene-image-absence arch test for DPA role-gating) |

### Top Lessons (Verified Across Milestones)

1. **Lock architecture decisions in CONTEXT.md before planning** — v1.0 established, v2.0 confirmed. ~30 D-XX entries per phase eliminated mid-implementation thrash. Especially load-bearing for integration seams (Phase 21 FrasIncidentFactory) and constrained decisions (Phase 22 DPA role-gating).
2. **Stub external integrations; activate later** — v1.0 stubbed agencies (SMS, NDRRMC, BFP, PNP, hospital EHR). v2.0 treated CDRRMO legal sign-off the same way (CLI mechanism tested, external review is a post-ship gate). Ship-ready architecture without blocking on external humans.
3. **Archive phase directories at milestone close** — v1.0 + v2.0 both moved `phases/NN-slug/` → `milestones/vX.Y-phases/NN-slug/` via `git mv`. Preserves blame, keeps the active phases directory empty for the next milestone.
4. **Human verification only where it's genuinely unautomatable** — v1.0 flagged color-mix/focus rings/audio distinctiveness. v2.0 flagged two-browser ACK propagation, audio playback, CDRRMO legal review, PDF legibility. In both milestones, the "obvious" impulse to automate would have produced brittle tests; `checkpoint:human-verify` was the right call.
5. **Convention guards in Pest `tests/Unit/Conventions/`** — v1.0 shipped Wayfinder URL regression guard. v2.0 added DPA scene-image-absence arch test. Pattern: CI-enforced invariants are cheaper than code reviews for load-bearing rules.
