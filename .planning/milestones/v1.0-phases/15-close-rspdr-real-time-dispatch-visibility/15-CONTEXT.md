# Phase 15: Close RSPDR Real-Time Dispatch Visibility - Context

**Gathered:** 2026-04-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire the existing `ChecklistUpdated` and `ResourceRequested` backend broadcast events into the dispatch console so scene checklist progress and field resource requests appear in real time without a page reload. This phase is a gap closure for RSPDR-06 (partial) and RSPDR-10 (partial) from the v1.0 milestone audit.

**In scope:**
- `useEcho` subscribers in `useDispatchFeed.ts` for both events
- Checklist progress UI (progress bar + %) in `IncidentDetailPanel.vue`
- Resource requests UI section in `IncidentDetailPanel.vue` + live ticker entry
- New `resource-request` audio cue added to `useAlertSystem`
- State-sync endpoint exposes `incident.resource_requests[]` so reload/reconnect preserves visibility
- Pest backend coverage expanded with `Event::fake()` + `assertDispatched` on correct channel/payload

**Out of scope:**
- Expanding `ChecklistUpdated` payload beyond `checklist_pct`
- Checklist item-level display (individual ticks)
- Toast/modal ack flow for resource requests
- Frontend composable test harness (Vitest)
- Playwright E2E

</domain>

<decisions>
## Implementation Decisions

### Checklist Progress UI
- **D-01:** Render `checklist_pct` as a horizontal progress bar with % label inside a "Scene Progress" section of `IncidentDetailPanel.vue`
- **D-02:** Only show the progress bar when incident status is `ON_SCENE`, `RESOLVING`, or later — it is not meaningful before arrival
- **D-03:** Checklist updates do not add ticker entries (progress bars animate continuously; ticker is reserved for attention-required events)
- **D-04:** Checklist updates play no audio cue — visual-only progress

### Resource Request UI
- **D-05:** Resource requests surface in BOTH a live ticker entry (pattern parity with `MutualAidRequested`) AND a "Resource Requests" list section in `IncidentDetailPanel.vue`
- **D-06:** Ticker entry uses `resource_label` (human-readable, e.g., "Medevac") and includes requester callsign/name plus notes
- **D-07:** Detail panel list shows all resource requests for the active incident — newest first — with timestamp, resource label, requester, and notes
- **D-08:** State-sync endpoint returns `incident.resource_requests[]` so reconnect/reload preserves the list (requests already have timeline entries; surfaces them explicitly in API response)
- **D-09:** A new distinct `resource-request` tone is added to `useAlertSystem.ts` and plays on every `ResourceRequested` event (always — dispatcher attention required)

### Broadcast Payloads
- **D-10:** `ChecklistUpdated` payload stays minimal: `incident_id, incident_no, checklist_pct` — sufficient for progress bar, no backend change needed
- **D-11:** `ResourceRequested` payload already includes all required fields (`resource_type, resource_label, notes, requested_by, timestamp`) — no backend change needed

### State Mutation
- **D-12:** `useDispatchFeed.ts` subscriber for `ChecklistUpdated` finds the matching incident in `localIncidents` and sets `checklist_pct` reactively; silently drops if incident not found in current feed (matches existing pattern for events for non-tracked incidents)
- **D-13:** `useDispatchFeed.ts` subscriber for `ResourceRequested` pushes to ticker AND appends to a new `resourceRequestsByIncident` Map (keyed by incident_id) exposed from the composable for the detail panel to render

### Testing
- **D-14:** Backend coverage only — expand existing `tests/Feature/Responder/ChecklistTest.php` and `tests/Feature/Responder/ResourceRequestTest.php` with `Event::fake()` + `Event::assertDispatched()` asserting channel is `dispatch.incidents` and payload contains expected keys
- **D-15:** Frontend verification is manual (follows Phase 4 / Phase 12 precedent) — no new Vitest harness introduced
- **D-16:** Phase verification will manually confirm: responder updates checklist → dispatcher's progress bar moves; responder requests resource → dispatcher's ticker + detail panel list update + audio cue fires

### Claude's Discretion
- Exact CSS/Tailwind tokens for the progress bar — follow existing IncidentDetailPanel design system tokens
- Exact tone shape for `resource-request` (frequency, duration, pattern) — design to be distinguishable from message tone, priority tone, and mutual-aid tone
- Internal composable data structures for `resourceRequestsByIncident` (Map<string, ResourceRequest[]> or similar)
- Ordering/formatting of the detail-panel resource-requests list beyond "newest first"
- Whether to expose a `clearResourceRequests(incidentId)` helper when the incident is resolved (useDispatchFeed already clears unread/messages on RESOLVED — likely yes for consistency)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### v1.0 Audit (drives this phase)
- `.planning/v1.0-MILESTONE-AUDIT.md` — RSPDR-06 and RSPDR-10 identified as partial; missing subscribers are the exact gap being closed

### Requirements
- `.planning/REQUIREMENTS.md` — RSPDR-06 ("completion % broadcast to dispatch") and RSPDR-10 ("request creates timeline entry and dispatch notification") are the target requirements

### Existing broadcast pattern (must follow)
- `resources/js/composables/useDispatchFeed.ts` — 4 existing `useEcho` subscribers on `dispatch.incidents` (IncidentCreated, IncidentStatusChanged, MutualAidRequested, MessageSent). New subscribers must match this pattern and co-locate in the same composable.
- `app/Events/ChecklistUpdated.php` — event definition and broadcast payload contract (DO NOT modify)
- `app/Events/ResourceRequested.php` — event definition and broadcast payload contract (DO NOT modify)

### Dispatch UI
- `resources/js/components/dispatch/IncidentDetailPanel.vue` — target component for both checklist progress bar and resource-request list insertion
- `resources/js/composables/useAlertSystem.ts` — target for new `playResourceRequestTone()` helper

### Backend controllers (reference only, DO NOT modify for this phase)
- `app/Http/Controllers/ResponderController.php` — dispatches both events; already covered by existing tests

### Existing test files to extend
- `tests/Feature/Responder/ChecklistTest.php` — add `Event::fake()` + assertDispatched for ChecklistUpdated
- `tests/Feature/Responder/ResourceRequestTest.php` — add `Event::fake()` + assertDispatched for ResourceRequested

### State sync
- `app/Http/Controllers/StateSyncController.php` (or equivalent) — extend response to include `resource_requests` relationship per active incident
- Laravel Echo / Reverb channel auth already defined in `routes/channels.php` for `dispatch.incidents`

### Conventions
- `CLAUDE.md` — Laravel 12 / Vue 3 / Inertia / TypeScript strict mode conventions
- `.planning/phases/12-bi-directional-dispatch-responder-communication/` — reference implementation for a similar "add subscriber + UI section" phase (same pattern)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `useEcho` from `@laravel/echo-vue` — pattern already used 4 times in `useDispatchFeed.ts`
- `addTickerEvent()` helper already inside `useDispatchFeed.ts` — reuse for resource-request ticker
- `alertSystem.playMessageTone()` / `playPriorityTone()` / `triggerP1Flash()` — existing helpers in `useAlertSystem.ts`; add sibling `playResourceRequestTone()`
- `checklist_pct: number | null` field already on `DispatchIncident` type (`resources/js/types/incident.ts:66`) — no type change needed, just populate
- `IncidentDetailPanel.vue` already renders per-incident sections — inject new "Scene Progress" and "Resource Requests" sections following existing section conventions

### Established Patterns
- Broadcast events: `ShouldBroadcast` + `ShouldDispatchAfterCommit` + PrivateChannel `dispatch.incidents`
- Test pattern: `Event::fake()` before the HTTP action; `Event::assertDispatched(Class::class, fn($e) => ...)` afterwards
- Composable return shape: expose state refs + action helpers (see existing `getMessages`, `clearUnread`)
- State-sync shape: `incidents[]` with nested relationships — extend to include `resource_requests`

### Integration Points
- Channel auth: `dispatch.incidents` already authorized for dispatch roles in `routes/channels.php` — no change
- Event registration: events auto-discovered by Laravel — no broadcast registration change
- Type definitions: `DispatchIncident` in `resources/js/types/incident.ts` — may need new `ResourceRequest` type + optional `resource_requests?: ResourceRequest[]` field
- Ticker payload: `TickerEvent` type in `resources/js/types/incident.ts` — may need optional `resource_label` field OR overload existing fields

</code_context>

<specifics>
## Specific Ideas

- Progress bar only appears for `ON_SCENE` / `RESOLVING` / later statuses — user confirmed this gate
- Resource-request tone is a NEW tone (not reuse) — user confirmed distinct tone needed because ignoring a medevac request is consequential
- Both ticker AND detail-panel section for resource requests — user wants belt-and-suspenders visibility
- State-sync must include resource_requests so a reconnecting dispatcher sees the history — they were already timeline-logged per audit

</specifics>

<deferred>
## Deferred Ideas

- Checklist item-level display (which checkboxes are ticked) — would require expanding `ChecklistUpdated` payload with `checklist_data`; defer to v2 if dispatchers request it after v1.0 ships
- Toast with operator ack for resource requests — considered but rejected as heavyweight for routine requests; could revisit if medevac-specific urgency policy needed
- Vitest frontend composable test harness — could become a future infrastructure phase; out of scope here to avoid dependency creep
- Playwright E2E for dispatcher↔responder real-time flows — not established in project; add if/when CI grows a browser-test stage
- UnitForm.vue TS2322 and dompdf memory exhaustion — pre-existing tech debt deferred to v2 per audit

</deferred>

---

*Phase: 15-close-rspdr-real-time-dispatch-visibility*
*Context gathered: 2026-04-17*
