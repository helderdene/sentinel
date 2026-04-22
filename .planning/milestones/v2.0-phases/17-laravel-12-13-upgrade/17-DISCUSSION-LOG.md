# Phase 17: Laravel 12 → 13 Upgrade - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-21
**Phase:** 17-laravel-12-13-upgrade
**Areas discussed:** Package-compat fallback, PR/commit structure, Broadcast payload lock-in, Rollback + regression gates

---

## Gray Area Selection

User multi-select: all 4 proposed gray areas were selected.

| Gray Area | Selected |
|-----------|----------|
| Package-compat fallback | ✓ |
| PR/commit structure | ✓ |
| Broadcast payload lock-in | ✓ |
| Rollback + regression gates | ✓ |

---

## Package Compatibility Fallback

| Option | Description | Selected |
|--------|-------------|----------|
| Pin + proceed if compat declared | Verify L13 declarations at plan time; block phase if priority packages (Horizon, Magellan) don't declare `^13.0`. No forking, no mid-milestone upstream PRs. (Recommended) | ✓ |
| Pin older, force L13 on framework | Upgrade framework to ^13, constrain Horizon/Magellan to L12-compat minors with override. | |
| Contribute compat PR upstream | If package lacks L13 compat, open upstream PR, time-box 48h, fall back to block otherwise. | |

**User's choice:** Pin + proceed if compat declared
**Notes:** Lowest-risk path; matches SUMMARY.md §Phase 17 research alignment and STATE.md "research flag" posture.

---

## PR / Commit Structure

| Option | Description | Selected |
|--------|-------------|----------|
| Framework first, packages second | Three separate commits: framework + middleware + cache + tinker, then packages, then snapshots. Clean bisect. (Recommended) | ✓ |
| Single atomic composer update | One-shot `composer update --with-all-dependencies`. | |
| Pre-upgrade snapshot commit first | Add broadcast snapshot tests on L12 first, then perform upgrade in atomic steps. | |

**User's choice:** Framework first, packages second
**Notes:** Resolved to three-commit sequencing in CONTEXT.md D-04 — snapshot capture happens as Commit 1 (pre-upgrade on L12), framework as Commit 2, aligned package bumps as Commit 3. Combines the "framework first" ordering with the "snapshot first of all" safety net.

---

## Broadcast Payload Lock-In

| Option | Description | Selected |
|--------|-------------|----------|
| Snapshot tests only | Pest tests capture `broadcastWith()` JSON as golden fixtures; pre/post upgrade must match byte-for-byte. Minimal diff. (Recommended) | ✓ |
| Refactor to Resource classes | Convert broadcastWith() to typed Resource classes per event. | |
| Snapshot + Resource refactor | Both — highest safety, largest diff, violates feature-free upgrade. | |

**User's choice:** Snapshot tests only
**Notes:** Resource refactor deferred to Deferred Ideas — respects Phase 17 feature-free posture. 6 events confirmed: IncidentCreated, IncidentTriaged (via IncidentStatusChanged→TRIAGED), UnitAssigned (via AssignmentPushed), UnitStatusChanged, ChecklistUpdated, ResourceRequested.

---

## Rollback + Regression Gate

| Option | Description | Selected |
|--------|-------------|----------|
| Git revert + Horizon drain | Revert commit range + composer install + Horizon drain/restart. Regression gate = v1.0 Pest suite + 6 broadcast snapshots. (Recommended) | ✓ |
| Add sidebar/Fortify regression tests | Same as above + new Pest tests for sidebar-per-role (5 roles × 9 flags) and Fortify feature list. | |
| Staging verification gate + v1.0 UAT replay | Staging deploy first with manual UAT replay, promote to prod after human sign-off. | |

**User's choice:** Git revert + Horizon drain
**Notes:** Sidebar/Fortify regression tests deferred per CONTEXT.md D-11 — would expand scope during feature-free upgrade. Rollback runbook lives at `docs/operations/laravel-13-upgrade.md` (new directory).

---

## Finalization Check

| Option | Selected |
|--------|----------|
| Ready for context | ✓ |
| One more thing: Fortify scope | |
| One more thing: CI bundle-check | |

**User's choice:** Ready for context
**Notes:** Fortify feature lockdown handled inline via CONTEXT.md D-* without a separate discussion round; CI bundle-check explicitly out of scope for Phase 17 (begins Phase 20).

---

## Claude's Discretion

- Exact Pest fixture location/naming convention (sibling to test, under `__snapshots__/`, or inline — planner picks).
- Composer lockfile regeneration strategy per commit.
- Structure of `docs/operations/laravel-13-upgrade.md` (runbook vs checklist).
- Optional `composer audit` step post-upgrade.

## Deferred Ideas

- Refactor broadcast events to Resource classes (post-v2.0).
- Sidebar-per-role + Fortify-features regression Pest tests.
- CI bundle-check for `mapbox-gl` (begins Phase 20).
- Staging-deployment UAT-replay gate.
- Inertia v3 migration (separate milestone).
- Passkey / WebAuthn auth surface (future phase with dedicated UAT).
