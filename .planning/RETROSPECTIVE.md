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

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.0 | 16 | 51 | Established: CONTEXT.md locked-decisions pattern, PATTERNS.md mapper, Pest convention guards, `audited:` frontmatter key, sequential-mode for dirty trees |

### Cumulative Quality

| Milestone | Automated Tests | Pest Tests Passing | Convention Guards |
|-----------|-----------------|---------------------|-------------------|
| v1.0 | 100+ Pest + 16 PWA + 56 TDD | ~170 (48 pre-existing DB-state failures out of scope) | 1 (Wayfinder URL literals) |

### Top Lessons (Verified Across Milestones)

*Populate after v2.0 — trends require ≥2 milestones.*
