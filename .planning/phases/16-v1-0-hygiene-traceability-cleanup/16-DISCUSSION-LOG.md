# Phase 16: v1.0 Hygiene & Traceability Cleanup - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-17
**Phase:** 16-v1-0-hygiene-traceability-cleanup
**Areas discussed:** Traceability backfill depth, Phase 10 browser verification scope, Plan bundling strategy, Test posture for Wayfinder swaps

---

## Gray Area Selection

| Option | Description | Selected |
|--------|-------------|----------|
| Traceability backfill depth | Add only rows vs also full requirement definitions | ✓ |
| Phase 10 browser verification scope | Screenshots vs matrix vs checklist only | ✓ |
| Plan bundling strategy | Omnibus vs split by concern vs split per criterion | ✓ |
| Test posture for Wayfinder swaps | Existing tests only vs grep guard vs Vitest tests | ✓ |

**User's choice:** All four areas selected.
**Notes:** Phase is tightly scoped — these are the only real HOW decisions left after the audit prescribed the WHAT.

---

## Traceability Backfill Depth

| Option | Description | Selected |
|--------|-------------|----------|
| Full backfill | Requirement text + traceability rows; count bumps 102 → 123 | ✓ |
| Traceability rows only | Just table rows; registry text still missing | |
| Full sections, no count bump | Full sections but footnote to keep canonical 102 | |

**User's choice:** Full backfill (recommended).
**Notes:** Audit explicitly flagged OP/REBRAND as orphaned from REQUIREMENTS.md. Full backfill makes the registry match reality and stops future audits from re-flagging.

### OP-01..15 / REBRAND-01..06 Source-of-Truth

| Option | Description | Selected |
|--------|-------------|----------|
| ROADMAP.md Phase 8 / 14 | Pull definitions from roadmap | |
| Derive from Phase 8 / 14 artifacts | Reconstruct from PLAN.md + SUMMARY.md + VERIFICATION.md | ✓ (effective) |

**User's choice:** ROADMAP.md selected, but investigation showed ROADMAP only lists IDs (no full text).
**Notes:** CONTEXT.md D-02 redirects the planner to `08-VERIFICATION.md` (lines 133+) and `14-VERIFICATION.md` (lines 99+) as canonical text sources since ROADMAP lacks per-ID statements. `08-RESEARCH.md` line 74+ serves as cross-reference.

---

## Phase 10 Browser Verification Scope

| Option | Description | Selected |
|--------|-------------|----------|
| Screenshots × 2 | Chrome light + dark per check (6 total) | ✓ |
| Screenshot matrix | Chrome + Safari × light + dark (12 total) | |
| Written checklist only | Sign-off ticks, no screenshots | |

**User's choice:** Screenshots × 2 (recommended).
**Notes:** Pragmatic rigor matching Phase 4/12 precedent. Chrome WebKit color-mix quirks not relevant to core CDRRMO ops.

### Verification Actor

| Option | Description | Selected |
|--------|-------------|----------|
| Human user | User performs checks manually | ✓ |
| Claude via Playwright MCP | Automated headless screenshots | |

**User's choice:** Human user (recommended).
**Notes:** Status was `human_needed` by design — color-mix rendering fidelity genuinely needs eyes.

---

## Plan Bundling Strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Split: code / docs / human | 16-01 Wayfinder / 16-02 Traceability / 16-03 Approval + Verification | ✓ |
| Single omnibus plan | All 5 cleanups in 16-01 | |
| Split per success criterion (5 plans) | Maximum granularity | |

**User's choice:** Split by concern type (recommended).
**Notes:** Clean commit boundaries, each plan has one concern. Plans are independent and can execute in any order or parallel waves.

---

## Test Posture for Wayfinder Swaps

| Option | Description | Selected |
|--------|-------------|----------|
| Existing tests + grep guard | Rely on existing Pest + new convention test | ✓ |
| Existing tests only | Trust existing tests, social enforcement | |
| Vitest component tests | New Vitest infrastructure | |

**User's choice:** Existing tests + grep guard (recommended).
**Notes:** Convention is locked in via single failing Pest test. Zero new tooling. No Vitest introduction.

### Guard Location

| Option | Description | Selected |
|--------|-------------|----------|
| Pest test | `tests/Unit/Conventions/WayfinderConventionTest.php` | ✓ |
| npm lint script | Add script to package.json | |

**User's choice:** Pest test (recommended).
**Notes:** Runs inside existing `php artisan test` / `composer ci:check` path. Frontend-convention-tested-in-PHP is philosophically a little off but zero new tooling wins.

---

## Claude's Discretion

- Exact regex / string-literal patterns for the Pest convention test (must avoid over-matching)
- Frontmatter approval-line wording for Phase 14 (match Phase 13 pattern)
- Screenshot filename scheme and directory layout for Phase 10 verification assets
- Grouping of traceability rows (combined list vs preserve prefix groupings)
- Placement of the coverage-count update in the footer block

## Deferred Ideas

- Multi-browser Chrome + Safari verification matrix
- Playwright MCP automation for Phase 10 checks
- Vitest component tests for QueueRow / usePushSubscription
- npm lint script for Wayfinder convention guard
- `/gsd-validate-phase 14` full regeneration (frontmatter flip is sufficient)
- v2 milestone: dompdf memory exhaustion, UnitForm.vue TS2322
