---
phase: 15-close-rspdr-real-time-dispatch-visibility
audited: 2026-04-17
asvs_level: 1
threats_total: 4
threats_closed: 4
threats_open: 0
status: SECURED
---

# Phase 15 Security Audit — SECURED

**Auditor:** gsd-secure-phase  
**Phase:** 15 — Close RSPDR Real-Time Dispatch Visibility  
**ASVS Level:** 1  
**Threats Closed:** 4/4  
**Threats Open:** 0/4

---

## Threat Verification

| Threat ID | Category | Disposition | Status | Evidence |
|-----------|----------|-------------|--------|----------|
| T-15-01 | STRIDE Information Disclosure | accept (existing control) | CLOSED | `routes/channels.php` line 9: `Broadcast::channel('dispatch.incidents', ...)` restricts to `[Operator, Dispatcher, Supervisor, Admin]` roles via `in_array($user->role, $dispatchRoles)`. `ChannelAuthorizationTest.php` line 38 explicitly asserts responders receive `assertForbidden()`. Neither file was modified in Phase 15. |
| T-15-02 | STRIDE Tampering / XSS | mitigate | CLOSED | Zero `v-html` occurrences in `IncidentDetailPanel.vue`, `Console.vue`, and `useDispatchFeed.ts` (confirmed by grep returning 0). User-submitted fields `req.resource_label` (line 443), `req.requested_by` (line 450), and `req.notes` (line 456) in `IncidentDetailPanel.vue` are all rendered via `{{ }}` interpolation. UAT Test 6 (`15-UAT.md`) confirmed live literal rendering of `<script>alert(1)</script>` with no alert dialog. |
| T-15-03 | STRIDE DoS (ticker flooding / unbounded query) | accept (existing control) + mitigate | CLOSED | `useDispatchFeed.ts` line 33: `const MAX_TICKER_EVENTS = 20;` caps the ring buffer; line 65 enforces the cap on every `addTickerEvent` call. `StateSyncController.php` lines 29-31: timeline eager-load is bounded by `->where('event_type', 'resource_requested')` and `->whereIn('status', $dispatchVisibleStatuses)` (line 33) constrains the incident set to 7 active statuses. No unbounded loops introduced. |
| T-15-04 | STRIDE DoS (client-side stale memory) | mitigate | CLOSED | `useDispatchFeed.ts` provides cleanup in two confirmed locations: (a) Status-exit cleanup at lines 266-270 — `exitStatuses = ['RESOLVED', 'PENDING']` (line 253) triggers `new Map(resourceRequestsByIncident.value)` + `.delete(e.id)` + reassignment, using the reactive replacement pattern at all three Map mutation sites. (b) State-sync rehydration at line 511: `resourceRequestsByIncident.value = new Map()` unconditionally resets on every `onStateSync` call. Both locations present. |

---

## D-10 / D-11 Payload Integrity (Locked Decisions)

| File | Modified in Phase 15 | Verdict |
|------|---------------------|---------|
| `app/Events/ChecklistUpdated.php` | No | CLEAN — payload `{incident_id, incident_no, checklist_pct}` unchanged. D-10 honored. |
| `app/Events/ResourceRequested.php` | No | CLEAN — payload `{incident_id, incident_no, resource_type, resource_label, notes, requested_by, timestamp}` unchanged. D-11 honored. |

Verification: `git log` confirmed zero commits to either event file since phase start (15-VERIFICATION.md Gap-Closure Sanity Checks row "D-10/D-11 respected"). Live file content confirmed via read — both files match the READ-ONLY contracts declared in 15-01-PLAN.md `<interfaces>`.

---

## Unregistered Threat Flags

No `## Threat Flags` section was present in `15-01-SUMMARY.md` or `15-02-SUMMARY.md`. No unregistered flags to record.

---

## Accepted Risks Log

| Threat ID | Accepted Risk | Rationale |
|-----------|---------------|-----------|
| T-15-01 | Non-dispatch roles cannot subscribe to `dispatch.incidents` | Pre-existing control from Phase 3. Verified unchanged. ChannelAuthorizationTest regression coverage active. |
| T-15-03 (partial) | `MAX_TICKER_EVENTS = 20` ring buffer accepts event loss under extreme flood | Intentional UX design — oldest ticker entries are evicted, not queued. Resource-request history is preserved independently in `resourceRequestsByIncident` Map, so no data loss for the operator. |

---

## Review Warning Cross-Reference

The code review (`15-REVIEW.md`) raised three warnings (WR-01, WR-02, WR-03) that intersected with threat scope. All three were fixed before UAT (confirmed in `15-REVIEW-FIX.md`, commits `6fdd546`, `e32b02a`, `c1f6c7e`):

- **WR-01** (state-sync drops resource_requests on reconnect): Fixed — `onStateSync` now copies `resource_requests: inc.resource_requests ?? []` through the `DispatchIncident` shape (useDispatchFeed.ts line 485). This directly strengthens the T-15-04 mitigation by ensuring server-authoritative data survives every reconnect.
- **WR-02** (timestamp-only dedup collision): Fixed — dedup key widened to composite `${timestamp}|${resource_type}|${requested_by}` in `Console.vue`. No security impact; correctness fix.
- **WR-03** (ticker hardcoded P1 priority): Fixed — both `MutualAidRequested` and `ResourceRequested` handlers now use `inc?.priority ?? 'P3'` lookup. No security impact; correctness fix.

Five info-level findings (IN-01 through IN-05) were deliberately left unaddressed per instructions scope. None carry ASVS Level 1 security impact.

---

_Audited: 2026-04-17_  
_Auditor: Claude (gsd-secure-phase)_  
_ASVS Level: 1_  
_block_on: high_
