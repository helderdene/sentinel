# Phase 15: Close RSPDR Real-Time Dispatch Visibility - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-17
**Phase:** 15-close-rspdr-real-time-dispatch-visibility
**Areas discussed:** Checklist progress UI, Resource request surface, Audio cue policy, Test harness scope

---

## Checklist Progress UI

| Option | Description | Selected |
|--------|-------------|----------|
| Progress bar + % | Horizontal bar with % label in 'Scene Progress' section, visible ≥ ON_SCENE. Clearest signal. | ✓ |
| Compact badge only | Small '75%' badge near status pipeline. Less obvious. | |
| Full section with items | Bar + list of checked items. Richer but needs payload expansion. | |

| Option | Description | Selected |
|--------|-------------|----------|
| No ticker entry | Silent update, progress bar animates. | ✓ |
| Only on 100% completion | Ticker fires once on full completion. | |
| Every update | Ticker entry per tick — noisy. | |

**User's choice:** Progress bar + % in Scene Progress section, no ticker entries.
**Notes:** Ticker reserved for attention-required events; continuous progress should be visual only.

---

## Resource Request Surface

| Option | Description | Selected |
|--------|-------------|----------|
| Ticker + detail panel section | Live ticker + 'Resource Requests' list in IncidentDetailPanel. | ✓ |
| Ticker only | Just ticker, matches MutualAidRequested. | |
| Toast with operator ack | Modal toast requiring click. Overkill for routine requests. | |

| Option | Description | Selected |
|--------|-------------|----------|
| Include in state-sync | Reload/reconnect preserves list. | ✓ |
| In-session only | Only visible if dispatcher was listening when event fired. | |

**User's choice:** Ticker + detail panel section; state-sync exposes history.
**Notes:** Belt-and-suspenders visibility for resource requests; already timeline-logged, just needs API exposure.

---

## Audio Cue Policy

| Option | Description | Selected |
|--------|-------------|----------|
| Silent | Visual-only progress. | ✓ |
| Soft chime on 100% | Cue on completion. | |

| Option | Description | Selected |
|--------|-------------|----------|
| New distinct tone | Add 'resource-request' to useAlertSystem, distinguishable from other tones. | ✓ |
| Reuse message tone | Quiet, may be missed. | |
| Reuse priority tone | Risk of P1 flash. | |
| Silent | Visual only. | |

**User's choice:** Silent on checklist updates; new distinct tone on resource requests.
**Notes:** Ignoring a medevac is consequential — warrants its own distinct cue.

---

## Test Harness Scope

| Option | Description | Selected |
|--------|-------------|----------|
| Backend-only Pest | Event::fake() + assertDispatched on existing tests. Matches project precedent. | ✓ |
| Add Vitest composable tests | Mock useEcho, assert state. New permanent dependency. | |
| Playwright E2E | Browser sessions. Highest fidelity, needs Reverb/Horizon in CI. | |

| Option | Description | Selected |
|--------|-------------|----------|
| Keep minimal payload | Just incident_id, incident_no, checklist_pct. | ✓ |
| Include checklist_data | Enables full item-level display (rejected UI option). | |

**User's choice:** Backend-only Pest; keep minimal ChecklistUpdated payload.
**Notes:** Follows Phase 4 / Phase 12 precedent of manual frontend verification. Avoids introducing a new test harness dependency just for two subscribers.

---

## Claude's Discretion

- Progress bar styling/tokens — follow existing IncidentDetailPanel design system tokens
- Tone shape for resource-request (frequency, duration) — design to be distinguishable
- Internal composable data structures for `resourceRequestsByIncident`
- Detail-panel resource-requests list ordering/formatting beyond "newest first"
- Whether to add a `clearResourceRequests(incidentId)` on RESOLVED

## Deferred Ideas

- Checklist item-level display (would expand payload; defer to v2)
- Toast with operator ack for urgent resource types (medevac-specific policy)
- Vitest frontend composable test harness (future infrastructure phase)
- Playwright E2E for real-time flows (needs CI browser stage)
- Pre-existing tech debt (UnitForm.vue TS2322, dompdf memory exhaustion) — deferred to v2 per audit
