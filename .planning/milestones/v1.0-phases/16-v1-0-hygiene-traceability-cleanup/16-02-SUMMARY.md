---
phase: 16
plan: 02
subsystem: planning-documentation
tags: [requirements-backfill, traceability, tech-debt, v1-audit, documentation]
dependency_graph:
  requires:
    - ".planning/phases/08-implement-operator-role-and-intake-layer-ui/08-VERIFICATION.md (source of OP-01..15 descriptions)"
    - ".planning/phases/14-update-design-system-to-sentinel-branding-and-rename-app/14-VERIFICATION.md (source of REBRAND-01..06 descriptions)"
    - ".planning/phases/16-v1-0-hygiene-traceability-cleanup/16-CONTEXT.md (D-01..D-05 decisions)"
    - ".planning/phases/16-v1-0-hygiene-traceability-cleanup/16-PATTERNS.md §4 (REQUIREMENTS.md self-analog)"
  provides:
    - "Complete OP-01..15 requirement registry (15 new statements in REQUIREMENTS.md)"
    - "Complete REBRAND-01..06 requirement registry (6 new statements in REQUIREMENTS.md)"
    - "21 new traceability rows linking OP and REBRAND IDs to Phase 8 and Phase 14"
    - "Reconciled coverage count: 102 -> 123 (102 + 15 OP + 6 REBRAND)"
  affects:
    - ".planning/REQUIREMENTS.md (single-file edit; all 4 sub-operations)"
    - ".planning/v1.0-MILESTONE-AUDIT.md Tech Debt items (lines 208, 223) now closeable"
tech_stack:
  added: []
  patterns:
    - "H3 section format: `### Title` + `- [x] **ID**: statement` with code backticks for technical terms"
    - "Traceability row format: `| {ID} | Phase {N} | {Complete|Pending} |`"
    - "Coverage footer format: 3 bullet lines (total, mapped, unmapped)"
key_files:
  created: []
  modified:
    - ".planning/REQUIREMENTS.md"
decisions:
  - "OP-10 marked Complete (not Partial) per 16-PATTERNS.md §4 OP-10 status exception - gap resolved in commit 66b8a52 per 08-VERIFICATION.md:7-9 frontmatter"
  - "Traceability rows appended after MOBILE-02 grouped by prefix (15 OP-* then 6 REBRAND-*) per D-05"
  - "Last updated line rewritten to credit Phase 16 backfill while preserving Phase 15 gap-closure context (for RSPDR-06 and RSPDR-10)"
  - "Descriptions enriched with technical-term precision (class names, file paths, channel names, route names) in code backticks, matching style of existing sections like `### Bi-directional Communication`"
metrics:
  duration_min: 2
  tasks: 2
  files_touched: 1
  completed_date: "2026-04-17"
---

# Phase 16 Plan 02: REQUIREMENTS.md Traceability Backfill (OP-01..15 + REBRAND-01..06) Summary

REQUIREMENTS.md brought into full 1:1 correspondence with the phase-scoped requirement IDs (OP-01..15 from Phase 8, REBRAND-01..06 from Phase 14) via a single-file docs edit: 21 new statements, 21 new traceability rows, coverage count reconciled from 102 to 123.

## Objective Recap

Close the v1.0 Milestone Audit's two orphaned-requirement items (audit lines 208 and 223) by backfilling `.planning/REQUIREMENTS.md` so that Phase 8's operator-role IDs and Phase 14's Sentinel rebrand IDs are first-class v1 requirements with full statements AND corresponding Traceability rows. Phase-chronological reading order preserved.

## What Changed

### Sub-operation 1: Inserted `### Operator Role` section (Task 1 commit `5773abb`)

New H3 section added between `### Intake` and `### Dispatch` containing 15 `- [x] **OP-NN**:` lines with full requirement statements. Descriptions derived from `.planning/phases/08-implement-operator-role-and-intake-layer-ui/08-VERIFICATION.md` §Requirements Coverage (lines 131-147) per D-02, enriched with technical-term precision (route names like `intake.station`, channel names like `dispatch.incidents`, class names like `IntakeStationController::triage`) in code backticks to match the style of `### Bi-directional Communication` and `### PWA & Push Notifications`.

OP-10 marked `[x]` (Complete) - not `[ ]` (Partial) - per the 16-PATTERNS.md §4 OP-10 status exception. The PARTIAL qualifier at 08-VERIFICATION.md:142 was resolved by commit 66b8a52, recorded in the 08-VERIFICATION.md frontmatter `gaps[].status: resolved` field (lines 7-9).

### Sub-operation 2: Inserted `### Sentinel Rebrand` section (Task 2 commit `b29d2cf`)

New H3 section added between `### PWA & Push Notifications` and `## v2 Requirements` containing 6 `- [x] **REBRAND-NN**:` lines with full requirement statements. Descriptions derived from `.planning/phases/14-update-design-system-to-sentinel-branding-and-rename-app/14-VERIFICATION.md` §Requirements Coverage (lines 99-104) per D-02, enriched with specific file paths (`AppLogo.vue`, `public/favicon.svg`), hex values (`#042C53`, `#E24B4A`, etc.), and env var names (`APP_NAME`).

REBRAND-04 explicitly calls out the documented non-user-facing INFO-severity residuals (FHIR URNs, BFP API identifiers, CDRRMO agency name, `irms.test` Herd hostname, PHPDoc comments) so the registry reflects the spec-level acceptance criteria rather than an unqualified "all IRMS strings removed" claim.

### Sub-operation 3: Appended 21 traceability rows after MOBILE-02 (Task 2 commit `b29d2cf`)

21 new rows added to the Traceability table immediately after `| MOBILE-02 | Phase 13 | Complete |`. Rows grouped by prefix per D-05 (all 15 OP-* rows first, then all 6 REBRAND-* rows) to preserve phase ordering inside the table. Every row uses `Complete` status (including OP-10 per the status exception noted above). No existing rows renumbered, reordered, or modified.

### Sub-operation 4: Updated coverage footer + "Last updated" line (Task 2 commit `b29d2cf`)

- `v1 requirements:` bumped from `102 total` to `123 total (102 + 15 OP + 6 REBRAND backfilled in Phase 16)`
- `Mapped to phases:` bumped from `102` to `123`
- `Unmapped: 0` unchanged
- `Last updated:` line rewritten from the Phase 15 gap-closure message to the Phase 16 backfill message, while preserving the Phase 15 RSPDR-06/RSPDR-10 context inside a parenthetical so the audit trail for both phases is readable in a single line

## Math Reconciliation

```
Prior total:              102 (per 16-PATTERNS.md §4)
+ OP-01..15:              +15 (Phase 8)
+ REBRAND-01..06:          +6 (Phase 14)
= New total:              123
```

Verified via `grep -cE '^\| [A-Z]+-[0-9]{2}[a-z]? \| Phase' .planning/REQUIREMENTS.md` returning `123`.

## Verification Results (8/8 checks passed)

| # | Check | Expected | Actual | Status |
|---|-------|----------|--------|--------|
| 1 | Total `### ...` section headers (v1 sections including new two) | 13 | 13 | PASS |
| 2 | Count of `- [x] **OP-NN**:` lines | 15 | 15 | PASS |
| 3 | Count of `- [x] **REBRAND-NN**:` lines | 6 | 6 | PASS |
| 4 | Count of new traceability rows matching `^\| (OP-NN\|REBRAND-NN) \| Phase (8\|14) \| Complete \|$` | 21 | 21 | PASS |
| 5 | Coverage footer contains `v1 requirements: 123 total` and `Mapped to phases: 123` and does NOT contain `v1 requirements: 102 total` | all three | all three | PASS |
| 6 | "Last updated" line contains `after Phase 16 traceability backfill` | present | present | PASS |
| 7 | Total ID-rows in traceability table (sanity check) | 123 | 123 | PASS |
| 8 | Pre-existing rows preserved (spot checks: FNDTN-01, MOBILE-02) | both present | both present | PASS |

## Audit Item Closure

- v1.0-MILESTONE-AUDIT.md line 208: "OP-01..15 missing from REQUIREMENTS.md traceability table" - CLOSED (15 new statements + 15 new traceability rows)
- v1.0-MILESTONE-AUDIT.md line 223: "REBRAND-01..06 missing from REQUIREMENTS.md traceability table" - CLOSED (6 new statements + 6 new traceability rows)

## Deviations from Plan

None - plan executed exactly as written. The plan's 4 sub-edits (Operator Role section insert, Sentinel Rebrand section insert, traceability rows + coverage update, Last updated line) were executed as 3 physical Edit operations (the plan's Edit 2 combined the 21-row append and the coverage footer update into a single regex-anchored swap because they are contiguous in the file, which is the minimum-risk form; this is not a deviation from the plan's semantics). No auto-fixes triggered, no architectural decisions surfaced, no auth gates, no out-of-scope work discovered.

## Files Modified

| File | Lines before | Lines after | Delta |
|------|--------------|-------------|-------|
| `.planning/REQUIREMENTS.md` | 285 | 333 | +48 (+15 OP items, +6 REBRAND items, +21 traceability rows, +2 section headers + spacing, +3 footer/lastupdated deltas, -3 old footer lines) |

## Commits

| Task | Commit | Message |
|------|--------|---------|
| Task 1 | `5773abb` | docs(16-02): add Operator Role requirements section to REQUIREMENTS.md |
| Task 2 | `b29d2cf` | docs(16-02): add Sentinel Rebrand section, 21 traceability rows, bump coverage 102->123 |

## Self-Check: PASSED

- `.planning/REQUIREMENTS.md` exists with expected content (verified via all 8 verification checks above)
- Both commits exist in git log (`git log --oneline | grep -E '5773abb|b29d2cf'`)
- No pre-existing dirty-tree files were touched (staging limited to `.planning/REQUIREMENTS.md` via individual `git add` calls)

---

*Plan 16-02 complete. REQUIREMENTS.md registry and coverage table now in 1:1 correspondence with phase-scoped requirement IDs referenced in ROADMAP.md Phase 8 and Phase 14 entries. Audit Tech Debt items 208 and 223 closed.*
