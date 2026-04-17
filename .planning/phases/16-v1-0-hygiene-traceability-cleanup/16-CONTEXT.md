# Phase 16: v1.0 Hygiene & Traceability Cleanup - Context

**Gathered:** 2026-04-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Close the v1.0 milestone audit's hygiene debt. Five prescriptive cleanups, no new capability:

1. Replace hardcoded intake URLs in `QueueRow.vue` with Wayfinder `IntakeStationController` actions
2. Replace hardcoded push-subscription URLs in `usePushSubscription.ts` with Wayfinder `PushSubscriptionController` actions
3. Backfill `REQUIREMENTS.md` with OP-01..15 (Phase 8) and REBRAND-01..06 (Phase 14) — full requirement text + traceability rows
4. Approve `14-VALIDATION.md` (flip `status: draft` → `status: approved`)
5. Human browser-verification of Phase 10 visual fidelity items, update `10-VERIFICATION.md` from `human_needed` → `passed`

**In scope:**
- Frontend file edits (2 files) — no endpoint or controller changes
- REQUIREMENTS.md text additions (~21 new requirement definitions + 21 traceability rows)
- VALIDATION.md frontmatter flip for Phase 14
- VERIFICATION.md update + embedded screenshots for Phase 10
- Pest regression guard preventing reintroduction of banned literal URLs

**Out of scope:**
- Any behavior change in Intake or PWA push flows (route names already stable)
- New requirements beyond OP/REBRAND backfill
- Rescoping Phase 10 requirements (DS-01..12 already PASSED — only the 3 human_needed visual checks remain)
- Fixing pre-existing `dompdf` memory exhaustion or `UnitForm.vue` TS2322 (explicitly deferred to v2 by audit)
- Adding Vitest infrastructure

</domain>

<decisions>
## Implementation Decisions

### Traceability Backfill (Success Criterion 3)
- **D-01:** Full backfill — write complete `## Operator Role` and `## Sentinel Rebrand` sections in `REQUIREMENTS.md` mirroring existing section structure (heading, `- [x] **ID**: statement` lines), AND add corresponding Traceability rows
- **D-02:** Requirement **text** sourced from:
  - OP-01..15 → short descriptions in `.planning/phases/08-implement-operator-role-and-intake-layer-ui/08-VERIFICATION.md` (lines 133+ have the canonical description per ID); cross-check against `08-RESEARCH.md` line 74+
  - REBRAND-01..06 → descriptions in `.planning/phases/14-update-design-system-to-sentinel-branding-and-rename-app/14-VERIFICATION.md` (lines 99+)
  - NOT from ROADMAP.md (ROADMAP only lists IDs; phase success criteria are abbreviated and don't align 1:1 with individual requirement IDs)
- **D-03:** v1 total moves from 102 → 123 (102 + 15 OP + 6 REBRAND). Update the `**Coverage:**` footer block accordingly
- **D-04:** Insert the new `### Operator Role` section between `### Intake` and `### Dispatch` (preserves phase ordering); insert `### Sentinel Rebrand` between `### PWA & Push Notifications` and `## v2 Requirements`
- **D-05:** OP/REBRAND traceability rows go at the END of the existing table (after MOBILE-02), grouped by prefix, each as `| {ID} | Phase {8|14} | Complete |`

### Wayfinder URL Swaps (Success Criteria 1 & 2)
- **D-06:** `QueueRow.vue` uses `import { overridePriority, recall } from '@/actions/App/Http/Controllers/IntakeStationController'` and calls them inside `router.post()` via their `.url()` or direct invocation pattern (match how other intake actions are invoked in existing pages — scout for precedent before writing)
- **D-07:** `usePushSubscription.ts` uses `import PushSubscriptionController from '@/actions/App/Http/Controllers/PushSubscriptionController'` and calls `PushSubscriptionController.store()` and `PushSubscriptionController.destroy()` to build request URLs; preserves current `fetch()` call sites (manual fetch is required here, not Inertia router, because of the raw JSON body + XSRF handling pattern — see D-08)
- **D-08:** The existing `fetch()` + manual `X-XSRF-TOKEN` + `getXsrfToken()` helper stays — Wayfinder only supplies the URL; no migration to Inertia form helpers (route behavior is background push subscription, not a UI form)
- **D-09:** Route names (`intake.override-priority`, `intake.recall`, `push-subscriptions.store`, `push-subscriptions.destroy`) stay identical — this is purely a frontend literal-string removal

### Test Posture (Covers D-06..D-09)
- **D-10:** No new component tests. Rely on existing Pest feature tests that assert the underlying endpoints accept the POST/DELETE and produce the expected response
- **D-11:** Add a single Pest convention test at `tests/Unit/Conventions/WayfinderConventionTest.php` that scans `resources/js/**/*.{ts,vue}` (excluding `resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/`) for the banned literal URL patterns:
  - `/intake/\\{incident\\}/override-priority` hardcoded (regex or string)
  - `/intake/.*?/recall` hardcoded
  - `'/push-subscriptions'` (string literal anywhere except the Wayfinder-generated files)
  - Test fails with a descriptive message listing any offending file:line matches
- **D-12:** Guard lives in Pest (not an npm lint script) — runs via existing `php artisan test` / `composer ci:check` with zero new tooling

### Phase 14 Approval (Success Criterion 4)
- **D-13:** Single-file edit: change `14-VALIDATION.md` frontmatter `status: draft` → `status: approved`; append a line `approved: 2026-04-17` to frontmatter to match the Phase 13 precedent pattern
- **D-14:** Do NOT re-run `/gsd-validate-phase 14` — that command regenerates the whole file. We only need the status flip since `nyquist_compliant: true` and `wave_0_complete: true` are already set. If planner disagrees after reading Phase 13's approval pattern, they may reroute to the command. Planner's call.

### Phase 10 Browser Verification (Success Criterion 5)
- **D-15:** Verification artefact: two screenshots (Chrome light + Chrome dark) per check, for a total of 6 images. Screenshots may be embedded inline in `10-VERIFICATION.md` via relative paths under a new `.planning/phases/10-*/10-verification-screenshots/` subdirectory OR linked as attachments — planner decides based on file size
- **D-16:** The three checks to verify (from existing `10-VERIFICATION.md` `human_needed` block):
  1. **Focus ring** — Tab through login form and settings page inputs; confirm focus ring is `border-color: #2563eb + box-shadow: 0 0 0 3px rgba(37,99,235,0.1)`, NOT the default Tailwind `outline-ring/50` pattern
  2. **color-mix() opacity** — `PrioritySelector.vue` inactive-button borders/hovers at 40%/8% opacity render as tinted variants of t-p1..t-p4 (not hardcoded red/orange/amber/green)
  3. **Dark mode contrast** — `ReportRow.vue` TYPE_BADGES + STATUS_BADGES using color-mix() with t-* tokens remain legible in dark mode
- **D-17:** Verified by: **human user** (not automated via Playwright). Status is `human_needed` specifically because color-mix rendering requires real eyes. Planner should produce a checklist in `10-VERIFICATION.md` that the user can tick + paste screenshots into
- **D-18:** On completion, flip `10-VERIFICATION.md` frontmatter `status: human_needed` → `status: passed` and keep the existing truth-row detail

### Plan Structure
- **D-19:** Three plans:
  - `16-01-PLAN.md` — Wayfinder swaps: edit `QueueRow.vue`, edit `usePushSubscription.ts`, create Pest convention test, run targeted Pest runs
  - `16-02-PLAN.md` — REQUIREMENTS.md traceability backfill: add `### Operator Role` + `### Sentinel Rebrand` sections, add 21 traceability rows, update coverage count
  - `16-03-PLAN.md` — Validation & verification updates: flip `14-VALIDATION.md` status, add browser-verification checklist to `10-VERIFICATION.md` (user runs checks offline then commits with screenshots)
- **D-20:** Plans are independent — no cross-plan dependencies. Can execute in any order or in parallel waves.

### Claude's Discretion
- Exact regex / string-literal patterns in the Pest convention test (must cover URL variants but not over-match)
- Exact frontmatter approval-line wording for Phase 14 (match Phase 13 if Phase 13 has precedent, otherwise use `approved: 2026-04-17`)
- Screenshot filename scheme and directory layout for Phase 10 verification assets
- Whether to fold the traceability rows into one combined list or preserve prefix groupings in the table
- Exact placement of the coverage-count update (footer text block)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### v1.0 Audit (drives this phase)
- `.planning/v1.0-MILESTONE-AUDIT.md` — Tech Debt section (lines 203-224) enumerates every item Phase 16 closes; success criteria in ROADMAP.md Phase 16 entry are a compressed view of this

### Roadmap & Requirements
- `.planning/ROADMAP.md` §Phase 16 (lines 301-315) — success criteria 1-5 are the authoritative contract
- `.planning/REQUIREMENTS.md` — target of traceability backfill; preserves existing section style; Traceability table at line 170+

### OP-01..15 Source Texts (for D-02)
- `.planning/phases/08-implement-operator-role-and-intake-layer-ui/08-VERIFICATION.md` §Traceability (lines 133+) — per-ID short descriptions (canonical)
- `.planning/phases/08-implement-operator-role-and-intake-layer-ui/08-RESEARCH.md` lines 74+, 614+ — per-ID work descriptions (cross-reference)
- `.planning/phases/08-implement-operator-role-and-intake-layer-ui/08-VERIFICATION.md:127` — explicit note that OP-IDs are phase-internal and absent from REQUIREMENTS.md (explains why backfill is needed)

### REBRAND-01..06 Source Texts (for D-02)
- `.planning/phases/14-update-design-system-to-sentinel-branding-and-rename-app/14-VERIFICATION.md` §Traceability (lines 99+) — per-ID short descriptions (canonical)
- `.planning/phases/14-update-design-system-to-sentinel-branding-and-rename-app/14-VERIFICATION.md:93-106,157` — explicit note that REBRAND-IDs are absent from REQUIREMENTS.md and documents the gap

### Wayfinder Swap Targets
- `resources/js/components/intake/QueueRow.vue:53-79` — `handleOverride` + `handleRecall` with hardcoded URLs (lines 57 + 70)
- `resources/js/composables/usePushSubscription.ts:64,102` — hardcoded `/push-subscriptions` URLs in `subscribe()` and `unsubscribe()`
- `resources/js/actions/App/Http/Controllers/IntakeStationController.ts` — exports `overridePriority()` and `recall()` (Wayfinder-generated, already available)
- `resources/js/actions/App/Http/Controllers/PushSubscriptionController.ts` — exports `store()` and `destroy()` (Wayfinder-generated, already available)
- `routes/web.php:93-94` — `intake.override-priority` and `intake.recall` route names

### Precedent for Wayfinder Patterns
- `.claude/skills/wayfinder-development/SKILL.md` — conventions for importing and calling actions
- `CLAUDE.md` §Frontend Structure — lists `resources/js/actions/` as auto-generated and ESLint-ignored
- Scout existing pages under `resources/js/pages/intake/` for calling conventions (`overridePriority(args).url` vs `overridePriority.post(args)` vs direct invocation)

### Phase 14 Approval Precedent
- `.planning/phases/13-pwa-setup/13-VALIDATION.md` — Phase 13 approved on 2026-04-17 (per audit line 95). Match its frontmatter status pattern
- `.planning/phases/14-update-design-system-to-sentinel-branding-and-rename-app/14-VALIDATION.md` — target file; currently `status: draft` with `nyquist_compliant: true`, `wave_0_complete: true`

### Phase 10 Browser Verification
- `.planning/phases/10-update-all-pages-design-to-match-irms-intake-design-system/10-VERIFICATION.md` — current `status: human_needed`; lines 11,21,25,142-143 describe the 3 visual checks
- `resources/css/app.css:304` — DS-03 focus ring rule (`[data-slot]:focus-visible { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }`)
- `resources/js/components/incidents/PrioritySelector.vue` — color-mix() inactive buttons
- `resources/js/components/analytics/ReportRow.vue` — TYPE_BADGES + STATUS_BADGES color-mix()

### Test Convention Guard
- `tests/Unit/` — existing unit test directory; will gain `tests/Unit/Conventions/WayfinderConventionTest.php`
- `CLAUDE.md` §Testing — Pest 4 conventions; `php artisan test --compact` run pattern

### Prior CONTEXT.md
- `.planning/phases/15-close-rspdr-real-time-dispatch-visibility/15-CONTEXT.md` — immediately prior phase; style reference for this doc

### Project Conventions
- `CLAUDE.md` — Laravel 12 / Vue 3 / Inertia / TypeScript strict / Pint / Prettier
- `boost.json` — project-level Boost config

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/js/actions/App/Http/Controllers/IntakeStationController.ts` — `overridePriority()` + `recall()` already generated and exported
- `resources/js/actions/App/Http/Controllers/PushSubscriptionController.ts` — `store()` + `destroy()` already generated and exported
- `getXsrfToken()` helper in `usePushSubscription.ts:5-11` — keeps working unchanged
- Existing Pest test scaffolding under `tests/Unit/` — place convention guard alongside
- `.planning/phases/13-pwa-setup/13-VALIDATION.md` — frontmatter approval pattern to mirror in Phase 14 update

### Established Patterns
- Wayfinder action imports go through `@/actions/App/Http/Controllers/{Name}Controller` (see `CLAUDE.md` §Frontend Structure)
- `router.post(url, payload, options)` is the Inertia.js v2 idiom — Wayfinder actions give URLs that slot into this pattern
- Manual `fetch()` with XSRF-TOKEN is used for non-form, non-Inertia endpoints (PushSubscription) — not migrating that pattern
- Requirement sections in `REQUIREMENTS.md` follow `### {Section Name}` → `- [x] **{ID}**: {one-line statement}` format
- Traceability table rows are `| {ID} | Phase {N} | {Complete\|Pending} |`
- Phase VALIDATION.md frontmatter uses YAML `status: {draft\|approved}`; Phase 13 has `status: approved` and likely an `approved: YYYY-MM-DD` line — planner should read Phase 13's file to confirm

### Integration Points
- `routes/web.php:93-94` already names `intake.override-priority` and `intake.recall` (no route changes needed)
- VAPID env var and channel auth are already in place — usePushSubscription body unchanged except URL strings
- `composer ci:check` and `php artisan test --compact` pick up new Pest tests automatically

</code_context>

<specifics>
## Specific Ideas

- Human user performs Phase 10 browser verification (D-17) — status is `human_needed` by design because color-mix and focus-ring rendering require real eyes; Playwright automation adds cost without value
- Keep `fetch()` in `usePushSubscription.ts` (D-08) — the XSRF + raw JSON body flow is deliberate and unrelated to the URL-string cleanup
- Pest convention test is the "belt-and-suspenders" against regression (D-11) — a single failing test file tells future contributors immediately when they reintroduce a literal URL
- Three plans (D-19) keep commits focused: one PR-friendly commit per concern (code change / docs change / human verification)
- Requirement count goes to 123 (D-03) — audit already flagged OP/REBRAND as "orphaned from REQUIREMENTS.md", so backfilling makes the registry match reality

</specifics>

<deferred>
## Deferred Ideas

- Multi-browser verification matrix (Chrome + Safari × light/dark) — over-rigor for a post-v1 hygiene pass; Chrome light+dark is sufficient per D-15
- Playwright MCP automation for Phase 10 checks — color-mix rendering still needs human review, so automation is cost without benefit
- Vitest component tests for QueueRow / usePushSubscription — would introduce new frontend test infra (no Vitest in project today); defer to a future test-infrastructure phase if the team wants it
- npm lint script for Wayfinder convention guard — cleaner separation of concerns but adds a new tool; Pest guard achieves the same check with zero new tooling
- Re-running `/gsd-validate-phase 14` — would regenerate the validation artifact unnecessarily; a frontmatter flip is sufficient per D-13/D-14
- Full v2 milestone intake (pre-existing `dompdf` memory exhaustion, `UnitForm.vue` TS2322) — audit explicitly recommends deferring these to v2 milestone

</deferred>

---

*Phase: 16-v1-0-hygiene-traceability-cleanup*
*Context gathered: 2026-04-17*
