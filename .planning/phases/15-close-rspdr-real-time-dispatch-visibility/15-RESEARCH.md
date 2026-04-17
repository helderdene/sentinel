# Phase 15: Close RSPDR Real-Time Dispatch Visibility - Research

**Researched:** 2026-04-17
**Domain:** Real-time WebSocket subscriber wiring (Laravel Broadcasting + @laravel/echo-vue), dispatch console UI extension, progress-bar/list rendering, Web Audio tone design, Pest event assertions
**Confidence:** HIGH

## Summary

All backend infrastructure for RSPDR-06 and RSPDR-10 is already in production. `ChecklistUpdated` and `ResourceRequested` broadcast on `PrivateChannel('dispatch.incidents')` with complete payloads (verified: `app/Events/ChecklistUpdated.php:22-39` and `app/Events/ResourceRequested.php:29-50`). The v1.0 audit identifies a single integration gap: no frontend `useEcho` subscriber wires these events into the dispatch console. The work is an additive extension of existing patterns — no new libraries, no backend changes, no channel-auth changes.

Two existing Pest tests already assert event dispatch (`tests/Feature/Responder/ChecklistTest.php:47`, `tests/Feature/Responder/ResourceRequestTest.php:42`) but do not assert channel targeting or payload shape. Phase 15 expands these assertions rather than rewriting them. Frontend verification is manual (D-15, matching Phase 12 precedent).

**Primary recommendation:** Add two `useEcho` listeners to `useDispatchFeed.ts` immediately after the `MutualAidRequested` listener (line ~301) and before the `MessageSent` listener (line ~303). Render a "Scene Progress" section in `IncidentDetailPanel.vue` between Status Pipeline and Assignees (gate by status ∈ {ON_SCENE, RESOLVING, RESOLVED}), and a "Resource Requests" section just below Assignees. Extend `useAlertSystem.ts` with `playResourceRequestTone()` — three-note ascending arpeggio at higher gain than `playMessageTone` to signal operational urgency. Extend `StateSyncController` to include `resource_requests` derived from `incident_timeline` rows where `event_type='resource_requested'`. Expand two Pest tests with `Event::assertDispatched(…, function (Event $e) {…})` closures that inspect `broadcastOn()` and `broadcastWith()` directly. [VERIFIED: codebase inspection, all files read 2026-04-17]

<user_constraints>

## User Constraints (from CONTEXT.md)

### Locked Decisions

**Checklist Progress UI**
- **D-01:** Render `checklist_pct` as a horizontal progress bar with % label inside a "Scene Progress" section of `IncidentDetailPanel.vue`
- **D-02:** Only show the progress bar when incident status is `ON_SCENE`, `RESOLVING`, or later — it is not meaningful before arrival
- **D-03:** Checklist updates do not add ticker entries (progress bars animate continuously; ticker is reserved for attention-required events)
- **D-04:** Checklist updates play no audio cue — visual-only progress

**Resource Request UI**
- **D-05:** Resource requests surface in BOTH a live ticker entry (pattern parity with `MutualAidRequested`) AND a "Resource Requests" list section in `IncidentDetailPanel.vue`
- **D-06:** Ticker entry uses `resource_label` (human-readable, e.g., "Medevac") and includes requester callsign/name plus notes
- **D-07:** Detail panel list shows all resource requests for the active incident — newest first — with timestamp, resource label, requester, and notes
- **D-08:** State-sync endpoint returns `incident.resource_requests[]` so reconnect/reload preserves the list
- **D-09:** A new distinct `resource-request` tone is added to `useAlertSystem.ts` and plays on every `ResourceRequested` event (always — dispatcher attention required)

**Broadcast Payloads**
- **D-10:** `ChecklistUpdated` payload stays minimal: `incident_id, incident_no, checklist_pct` — sufficient for progress bar, no backend change needed
- **D-11:** `ResourceRequested` payload already includes all required fields (`resource_type, resource_label, notes, requested_by, timestamp`) — no backend change needed

**State Mutation**
- **D-12:** `useDispatchFeed.ts` subscriber for `ChecklistUpdated` finds the matching incident in `localIncidents` and sets `checklist_pct` reactively; silently drops if incident not found in current feed (matches existing pattern for events for non-tracked incidents)
- **D-13:** `useDispatchFeed.ts` subscriber for `ResourceRequested` pushes to ticker AND appends to a new `resourceRequestsByIncident` Map (keyed by incident_id) exposed from the composable for the detail panel to render

**Testing**
- **D-14:** Backend coverage only — expand existing `tests/Feature/Responder/ChecklistTest.php` and `tests/Feature/Responder/ResourceRequestTest.php` with `Event::fake()` + `Event::assertDispatched()` asserting channel is `dispatch.incidents` and payload contains expected keys
- **D-15:** Frontend verification is manual (follows Phase 4 / Phase 12 precedent) — no new Vitest harness introduced
- **D-16:** Phase verification will manually confirm: responder updates checklist → dispatcher's progress bar moves; responder requests resource → dispatcher's ticker + detail panel list update + audio cue fires

### Claude's Discretion
- Exact CSS/Tailwind tokens for the progress bar — follow existing IncidentDetailPanel design system tokens
- Exact tone shape for `resource-request` (frequency, duration, pattern) — design to be distinguishable from message tone, priority tone, and mutual-aid tone
- Internal composable data structures for `resourceRequestsByIncident` (Map<string, ResourceRequest[]> or similar)
- Ordering/formatting of the detail-panel resource-requests list beyond "newest first"
- Whether to expose a `clearResourceRequests(incidentId)` helper when the incident is resolved (useDispatchFeed already clears unread/messages on RESOLVED — likely yes for consistency)

### Deferred Ideas (OUT OF SCOPE)
- Checklist item-level display (which checkboxes are ticked) — would require expanding `ChecklistUpdated` payload with `checklist_data`; defer to v2 if dispatchers request it after v1.0 ships
- Toast with operator ack for resource requests — considered but rejected as heavyweight for routine requests; could revisit if medevac-specific urgency policy needed
- Vitest frontend composable test harness — could become a future infrastructure phase; out of scope here to avoid dependency creep
- Playwright E2E for dispatcher↔responder real-time flows — not established in project; add if/when CI grows a browser-test stage
- UnitForm.vue TS2322 and dompdf memory exhaustion — pre-existing tech debt deferred to v2 per audit

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| RSPDR-06 | Contextual arrival checklists per incident type with animated checkboxes and progress bar; **completion % broadcast to dispatch** | `ChecklistUpdated` event at `app/Events/ChecklistUpdated.php` already dispatches on `dispatch.incidents` with `checklist_pct` in payload. Research identifies the exact insertion point in `useDispatchFeed.ts:301` for the new subscriber and the exact section placement in `IncidentDetailPanel.vue:326` (after Status Pipeline, before Assignees) for the progress bar. |
| RSPDR-10 | Resource request from field: 6 types ... **request creates timeline entry and dispatch notification** | `ResourceRequested` event at `app/Events/ResourceRequested.php` already dispatches on `dispatch.incidents` with full payload. Timeline entry creation already works (`ResponderController.php:365-374`). Research identifies the `MutualAidRequested` pattern (line 288-301) as the parity template for the ticker entry, and the insertion point for the new detail-panel list section. State-sync extension approach documented. |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

**Backend:**
- Laravel 12 — middleware in `bootstrap/app.php`, no `Kernel.php`
- PHP 8.2+ — constructor property promotion, explicit return types, PHPDoc over inline comments, curly braces always
- Pint with `--dirty --format agent` required after PHP edits
- Form Request classes for validation (already exist: `UpdateChecklistRequest`, `RequestResourceRequest`)
- `Model::query()` over `DB::` — but state-sync already uses query builder directly
- Eloquent eager loading to prevent N+1 — relevant when state-sync extends to include timeline data
- Pest 4 for tests; run with `php artisan test --compact`; prefer narrow file/filter scopes

**Frontend:**
- Vue 3 + Inertia v2 + TypeScript strict mode
- `prefer-type-imports` ESLint rule — use `import type` for types
- Tailwind v4 with design-system CSS variables (`--t-*`); no hardcoded neutral/zinc classes (see Phase 10)
- Path alias: `@/*` → `resources/js/*`
- Auto-generated dirs are ESLint-ignored: `resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/`
- Wayfinder: `@/actions/App/Http/Controllers/…` for controller actions; do NOT hardcode URL strings (Phase 16 tech debt explicitly flags this)
- `npm run types:check` and `npm run build` are the frontend gates
- Prettier: 4-space indent, single quotes, semicolons, Tailwind class sorting
- Vue components must have a single root element

**Testing discipline (from CLAUDE.md):**
- Every change must be programmatically tested — write new tests or update existing ones, then run
- Prefer narrow filters: `php artisan test --compact --filter=testName` or file path
- Do not create verification scripts when tests cover the functionality

**Conventions that affect this phase:**
- The existing `useDispatchFeed.ts` pattern (4 `useEcho` listeners on `dispatch.incidents`) is the authority — new listeners MUST match it
- Reactive Map replacement pattern (`new Map(old)`) for Vue reactivity on Maps — established at `useDispatchFeed.ts:96-99, 108-113, 248-254` — MUST be followed for `resourceRequestsByIncident`
- Fire-and-forget `fetch()` with XSRF token — established pattern but NOT needed this phase (no new dispatch endpoints)
- The `TickerEvent` shape is already simple and used by `DispatchTopbar.vue:34-37` — new optional field must not break existing usage

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Checklist % state mutation on WebSocket event | Browser / Client (`useDispatchFeed`) | — | Event arrives on long-lived dispatch channel; reactive `localIncidents` Ref drives UI |
| Checklist progress bar rendering | Browser / Client (`IncidentDetailPanel.vue`) | — | Pure presentational section keyed off `incident.checklist_pct` |
| Resource-request list state | Browser / Client (`useDispatchFeed`) | — | Session-local accumulation via `resourceRequestsByIncident` Map; parity with `messagesByIncident` |
| Resource-request list rendering | Browser / Client (`IncidentDetailPanel.vue`) | — | Lookup helper `getResourceRequests(id)` injected by composable |
| Resource-request ticker entry | Browser / Client (`useDispatchFeed.addTickerEvent`) | — | Reuses existing ring buffer; surfaces in `DispatchTopbar.vue` ticker area |
| `resource-request` audio tone | Browser / Client (`useAlertSystem`) | — | Web Audio API oscillator chain; matches existing `playPriorityTone`/`playMessageTone` shape |
| Broadcasting both events | API / Backend (already complete) | — | Events already defined; no change needed |
| State-sync history (reconnect recovery) | API / Backend (`StateSyncController`) | Database (timeline table) | Resource-request history lives in `incident_timeline` rows with `event_type='resource_requested'`; state-sync must hydrate into frontend shape |
| Event assertion tests | API / Backend (Pest) | — | `Event::fake()` + `assertDispatched` with channel/payload assertion closures |

**Key insight:** This is a frontend-heavy phase. The only backend change is the state-sync extension (one controller + test coverage). All other work is TypeScript/Vue and Pest test extension.

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `@laravel/echo-vue` | 2.x | `useEcho` composable for private-channel subscription | Already the established pattern — 4 existing subscribers in `useDispatchFeed.ts` [VERIFIED: `package.json` + `useDispatchFeed.ts:2`] |
| `laravel-echo` | 2.x | Underlying Echo instance | Already configured in app entry [CITED: CLAUDE.md foundational context] |
| Laravel Broadcasting | v12 | Event dispatch with `ShouldBroadcast` + `ShouldDispatchAfterCommit` | Both events already implement both interfaces [VERIFIED: `app/Events/ChecklistUpdated.php:13`, `ResourceRequested.php:15`] |
| Laravel Reverb | v1 | WebSocket broker — `dispatch.incidents` channel already auth'd | [VERIFIED: `routes/channels.php:9-11`] |
| Web Audio API | native | Oscillator-based tone generation in `useAlertSystem.ts` | Existing helpers `playPriorityTone`, `playAckExpiredTone`, `playMessageTone` [VERIFIED: `resources/js/composables/useAlertSystem.ts`] |
| Pest | 4.x | Backend tests | Existing tests already use `Event::fake()` pattern [VERIFIED: `tests/Feature/Responder/ChecklistTest.php:12-16`] |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Vue 3 Composition API | 3.x | `ref`, `computed`, `Map` reactivity via replacement | For `resourceRequestsByIncident` Map mutation (new `Map(old)` pattern) |
| Tailwind v4 | 4.x | Design-token-driven styling (`--t-border`, `--t-text`, `--t-accent`, `--t-p1..p4`) | For Scene Progress bar and Resource Requests list — must follow Phase 10 token-only discipline |
| TypeScript strict | — | Type safety for payloads and composable returns | For new `ChecklistUpdatedPayload`, `ResourceRequestedPayload`, `ResourceRequest` interfaces |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Adding a new audio tone in `useAlertSystem` | Reusing `playPriorityTone('P2')` | Rejected per D-09 — user explicitly wants a DISTINCT tone because ignoring medevac requests is consequential. Reusing priority tone would conflict with P2 incident creation alerts. |
| `resourceRequestsByIncident` in composable state | Storing requests in `localIncidents[].resource_requests` | Composable-state is consistent with `messagesByIncident` (Phase 12 precedent) and keeps `DispatchIncident` type focused on server-backed data. However, per D-08 we ALSO need `incident.resource_requests` on the Incident type to survive state-sync — so the answer is BOTH: hydrate from state-sync into the type field, then accumulate new requests into the composable Map. |
| Eager-loading `timeline` in state-sync | Adding a dedicated `resource_requests` relationship on `Incident` | Rejected — `incident_timeline` already holds the data (`event_type='resource_requested'`), and no SQL schema change is welcome for a gap closure. Filter timeline rows at query time. |
| New Pest test files | Extending existing ones | Per D-14: extend existing `ChecklistTest.php` and `ResourceRequestTest.php`. They already have `Event::fake()` and `Event::assertDispatched` — just deepen the assertions. |

**Installation:** No new packages.

**Version verification:** Not applicable — zero new dependencies.

## Architecture Patterns

### System Architecture Diagram

```
┌────────────────────────────────────────────────────────────────────────────┐
│  FIELD RESPONDER                                                           │
│  ─────────────                                                             │
│  ResponderController::updateChecklist() [line 241-256]                     │
│      ├─> persists Incident.checklist_pct                                   │
│      └─> ChecklistUpdated::dispatch($incident->fresh()) [line 253]         │
│                                                                            │
│  ResponderController::requestResource() [line 359-384]                     │
│      ├─> creates incident_timeline row (event_type='resource_requested')   │
│      └─> ResourceRequested::dispatch(...) [line 376-381]                   │
└────────────────────────────────────────────────────────────────────────────┘
                                    │
                    broadcastOn: PrivateChannel('dispatch.incidents')
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────────┐
│  REVERB WEBSOCKET SERVER                                                   │
│  Channel auth: routes/channels.php:9-11 — dispatch roles only              │
└────────────────────────────────────────────────────────────────────────────┘
                                    │
                    WebSocket frame delivered to all subscribed dispatchers
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────────┐
│  DISPATCH CONSOLE (browser)                                                │
│  ───────────────────────                                                   │
│                                                                            │
│  useDispatchFeed.ts (NEW subscribers)                                      │
│      ├─> useEcho<ChecklistUpdatedPayload>('dispatch.incidents',            │
│      │       'ChecklistUpdated', handler)                                  │
│      │   handler: mutate localIncidents[id].checklist_pct (D-12)           │
│      │                                                                     │
│      └─> useEcho<ResourceRequestedPayload>('dispatch.incidents',           │
│              'ResourceRequested', handler)                                 │
│          handler: addTickerEvent + push to resourceRequestsByIncident Map  │
│                    + alertSystem.playResourceRequestTone() (D-09)          │
│                                                                            │
│  reactive localIncidents ──────► IncidentDetailPanel.vue                   │
│      ├─> Scene Progress section (shows when status ∈ {ON_SCENE, …})        │
│      │   horizontal bar bound to incident.checklist_pct                    │
│      └─> Resource Requests section                                         │
│          list fed by getResourceRequests(incident.id) +                    │
│          incident.resource_requests[] (from state-sync hydration)          │
│                                                                            │
│  resourceRequestsByIncident Map (session-local, new on each page load)     │
│      ├─> keyed by incident_id                                              │
│      └─> value: ResourceRequest[] (newest first)                           │
│                                                                            │
│  tickerEvents ring buffer (cap 20) ──► DispatchTopbar.vue LIVE ticker      │
└────────────────────────────────────────────────────────────────────────────┘
                                    │
                  RECONNECT → state-sync GET /state-sync
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────────┐
│  StateSyncController__invoke (extended for D-08)                           │
│      current: returns PENDING incidents only (line 18-23) — NOTE MISMATCH  │
│      BUT useDispatchFeed.onStateSync already re-populates from the         │
│      response (line 372-445) — extend the payload to include               │
│      resource_requests per incident:                                       │
│          Incident::…->with(['timeline' => fn($q) => $q->where(             │
│              'event_type', 'resource_requested')])->get()                  │
│          → map each timeline entry to { type, label, notes, requested_by,  │
│              timestamp } shape matching ResourceRequestedPayload           │
└────────────────────────────────────────────────────────────────────────────┘
```

**Note on state-sync asymmetry:** `StateSyncController` currently returns only `IncidentStatus::Pending` incidents (line 20), but `useDispatchFeed.onStateSync` re-populates `localIncidents` from the response. For D-08 to work, state-sync must either (a) include dispatch-status incidents (`TRIAGED, DISPATCHED, ACKNOWLEDGED, EN_ROUTE, ON_SCENE, RESOLVING`) OR (b) the reconnect-reload flow must rely on Inertia `router.reload({ only: ['incidents'] })` rather than state-sync. See Open Question 1.

### Component Responsibilities

| Component/File | Current Role | Phase 15 Addition |
|----------------|--------------|-------------------|
| `resources/js/composables/useDispatchFeed.ts` | 4 `useEcho` subscribers on `dispatch.incidents` + 2 on `dispatch.units` + state-sync handler | Add 2 new `useEcho` subscribers (ChecklistUpdated, ResourceRequested); add `resourceRequestsByIncident` Map + `getResourceRequests` helper; clear Map on RESOLVED/PENDING status change and in `onStateSync` |
| `resources/js/composables/useAlertSystem.ts` | Exposes `playPriorityTone`, `playAckExpiredTone`, `triggerP1Flash`, `playMessageTone` | Add `playResourceRequestTone()` to exports |
| `resources/js/components/dispatch/IncidentDetailPanel.vue` | Header → SLA → Info → Notes → Status Pipeline → Assignees → Available Units → Messages → Timeline → Mutual Aid button | Insert "Scene Progress" section after Status Pipeline (line 319-325) and before Assignees (line 327); insert "Resource Requests" section after Assignees (line 327-372) and before Available Units (line 374). Accept new props `resourceRequests: ResourceRequest[]`. |
| `resources/js/pages/dispatch/Console.vue` | Destructures `tickerEvents, unreadByIncident, totalUnreadMessages, clearUnread, getMessages, addLocalMessage` from `useDispatchFeed` | Add `getResourceRequests` to destructure; pass `resourceRequests` prop to `IncidentDetailPanel` via computed selector |
| `resources/js/types/incident.ts` | Defines `Incident`, `IncidentTimelineEntry`, `TickerEvent`, `IncidentCreatedPayload`, `IncidentStatusChangedPayload`, `StateSyncResponse` | Add `ChecklistUpdatedPayload`, `ResourceRequestedPayload`, `ResourceRequest` interfaces; extend `TickerEvent` with optional `resource_label?: string` and `requested_by?: string`; extend `Incident` with optional `resource_requests?: ResourceRequest[]` |
| `resources/js/types/dispatch.ts` | Extends `DispatchIncident` from `Incident` | `resource_requests` inherits from the base type update; no change here |
| `app/Http/Controllers/StateSyncController.php` | Returns `incidents` (pending-only currently), `channelCounts`, `units` | Eager-load `timeline` with `where event_type='resource_requested'` (or map from a dedicated scope); transform into `resource_requests` array on each incident (see Open Question 1 re: status filter) |
| `tests/Feature/Responder/ChecklistTest.php` | 3 tests; fakes ChecklistUpdated and asserts dispatch | Tighten one test with closure asserting `broadcastOn()` contains `dispatch.incidents` PrivateChannel and `broadcastWith()` returns `incident_id, incident_no, checklist_pct` |
| `tests/Feature/Responder/ResourceRequestTest.php` | 2 tests; fakes ResourceRequested and asserts dispatch | Tighten one test with closure asserting channel + payload keys: `incident_id, incident_no, resource_type, resource_label, notes, requested_by, timestamp` |
| `tests/Feature/RealTime/StateSyncTest.php` | 6 tests covering priority ordering, channel counts, unit filtering, auth | Add test for `resource_requests` hydration per incident |

### Recommended Project Structure

```
app/Events/
├── ChecklistUpdated.php      # unchanged
└── ResourceRequested.php     # unchanged

app/Http/Controllers/
├── ResponderController.php   # unchanged
└── StateSyncController.php   # extend __invoke to include resource_requests

resources/js/
├── composables/
│   ├── useDispatchFeed.ts    # +2 useEcho subscribers, +resourceRequestsByIncident Map
│   └── useAlertSystem.ts     # +playResourceRequestTone
├── components/dispatch/
│   └── IncidentDetailPanel.vue  # +Scene Progress section, +Resource Requests section
├── pages/dispatch/
│   └── Console.vue           # +getResourceRequests wiring
└── types/
    └── incident.ts           # +ChecklistUpdatedPayload, +ResourceRequestedPayload,
                              #  +ResourceRequest, +Incident.resource_requests?,
                              #  +TickerEvent optional fields

tests/Feature/
├── Responder/
│   ├── ChecklistTest.php     # deepen event assertion
│   └── ResourceRequestTest.php  # deepen event assertion
└── RealTime/
    └── StateSyncTest.php     # add resource_requests hydration test
```

### Pattern 1: The Four Existing `useEcho` Subscribers — Signature Shape the New Ones Must Match

`useDispatchFeed.ts` has four `useEcho` listeners on `dispatch.incidents`. They share an exact shape that the new two MUST follow. [VERIFIED: `resources/js/composables/useDispatchFeed.ts:117-336`]

```typescript
// Source: resources/js/composables/useDispatchFeed.ts:117-199 (IncidentCreated — most complex)
useEcho<IncidentCreatedPayload>(
    'dispatch.incidents',              // channel (string, no dot-prefix)
    'IncidentCreated',                 // event name (string, no namespace)
    (e) => {
        if (localIncidents.value.some((inc) => inc.id === e.id)) {
            return;                    // GUARD: event for already-tracked incident
        }
        // ... mutate state, refreshMapIncidents(), alertSystem calls, addTickerEvent
    },
);
```

**Simpler example — the one to clone structurally for ChecklistUpdated:**

```typescript
// Source: resources/js/composables/useDispatchFeed.ts:288-301 (MutualAidRequested — closest parity for ResourceRequested)
useEcho<MutualAidPayload>(
    'dispatch.incidents',
    'MutualAidRequested',
    (e) => {
        addTickerEvent({
            incident_no: e.incident_no,
            priority: 'P1',
            channel: 'radio',
            incident_type: `Mutual Aid: ${e.agency.name}`,
            location_text: e.notes ?? '',
            created_at: e.timestamp,
        });
    },
);
```

**Guard pattern for "incident not in localIncidents"** (matches D-12 — silently drop):

```typescript
// Source: resources/js/composables/useDispatchFeed.ts:205-241 (IncidentStatusChanged handler)
const index = localIncidents.value.findIndex((inc) => inc.id === e.id);
if (index === -1) {
    // incident not currently tracked — handle or drop
    return;
}
localIncidents.value[index].<mutation>;
```

**ChecklistUpdated handler (D-12):**

```typescript
useEcho<ChecklistUpdatedPayload>(
    'dispatch.incidents',
    'ChecklistUpdated',
    (e) => {
        const index = localIncidents.value.findIndex(
            (inc) => inc.id === e.incident_id,
        );

        if (index === -1) {
            return;                    // silently drop per D-12
        }

        localIncidents.value[index].checklist_pct = e.checklist_pct;
        // NO ticker, NO audio per D-03/D-04
    },
);
```

**ResourceRequested handler (D-05, D-09, D-13):**

```typescript
useEcho<ResourceRequestedPayload>(
    'dispatch.incidents',
    'ResourceRequested',
    (e) => {
        // Always play audio — D-09
        alertSystem.playResourceRequestTone();

        // Always append to ticker — D-05
        addTickerEvent({
            incident_no: e.incident_no,
            priority: 'P1',            // force visibility regardless of incident priority
            channel: 'radio',
            incident_type: `Resource: ${e.resource_label}`,
            location_text: [e.requested_by, e.notes].filter(Boolean).join(' — '),
            created_at: e.timestamp,
        });

        // Always append to resourceRequestsByIncident Map — D-13
        // Follow Phase 12 "new Map(old)" reactive replacement pattern
        const updated = new Map(resourceRequestsByIncident.value);
        const existing = updated.get(e.incident_id) ?? [];
        updated.set(e.incident_id, [
            {
                resource_type: e.resource_type,
                resource_label: e.resource_label,
                notes: e.notes,
                requested_by: e.requested_by,
                timestamp: e.timestamp,
            },
            ...existing,               // newest first per D-07
        ]);
        resourceRequestsByIncident.value = updated;
    },
);
```

### Pattern 2: Reactive Map Replacement for Vue Reactivity

[VERIFIED: established at lines 96-99, 108-113, 248-254, 322-325 in `useDispatchFeed.ts` — same pattern `messagesByIncident` uses]

```typescript
// DO NOT mutate in place:
// resourceRequestsByIncident.value.set(id, [...])  ❌ — Vue won't re-render deep consumers

// DO replace the whole Map reference:
const updated = new Map(resourceRequestsByIncident.value);
updated.set(id, newArray);
resourceRequestsByIncident.value = updated;        // ✓ triggers reactivity
```

### Pattern 3: Progress Bar — No Existing Primitive; Build Minimal Inline

[VERIFIED: grep for `progress` and `<progress>` in `resources/js/components/**/*.vue` — no reusable progress bar component exists. Existing SLA bar (`SlaProgressBar.vue`) is a time-based countdown, not a percentage.]

Recommended approach — inline in `IncidentDetailPanel.vue`, using design-system tokens:

```vue
<!-- Scene Progress section, inserted after Status Pipeline (line 325), before Assignees (line 327) -->
<div
    v-if="showChecklistProgress"
    class="border-b border-t-border px-3 py-2.5"
>
    <div class="mb-1.5 flex items-center justify-between">
        <span
            class="font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
        >
            SCENE PROGRESS
        </span>
        <span class="font-mono text-[11px] font-bold text-t-text">
            {{ incident.checklist_pct ?? 0 }}%
        </span>
    </div>
    <div
        class="h-1.5 w-full overflow-hidden rounded-full bg-t-surface-alt"
    >
        <div
            class="h-full rounded-full bg-t-accent transition-[width] duration-300 ease-out"
            :style="{ width: `${incident.checklist_pct ?? 0}%` }"
        />
    </div>
</div>
```

Guard computed for D-02:

```typescript
const showChecklistProgress = computed(() => {
    const onSceneOrLater = ['ON_SCENE', 'RESOLVING', 'RESOLVED'] as const;
    return onSceneOrLater.includes(
        props.incident.status as (typeof onSceneOrLater)[number],
    );
});
```

[VERIFIED: `IncidentStatus` enum at `resources/js/types/incident.ts:5-13` — values are exactly `'PENDING' | 'TRIAGED' | 'DISPATCHED' | 'ACKNOWLEDGED' | 'EN_ROUTE' | 'ON_SCENE' | 'RESOLVING' | 'RESOLVED'`. Gate values `ON_SCENE`, `RESOLVING`, `RESOLVED` match D-02 intent.]

### Pattern 4: Resource Requests List — Follow Assignees/Timeline Section Style

[VERIFIED: `IncidentDetailPanel.vue:327-372` — existing Assignees section style]

```vue
<!-- Resource Requests section, inserted after Assignees (line 372), before Available Units (line 374) -->
<div
    v-if="resourceRequests.length > 0"
    class="border-b border-t-border px-3 py-2.5"
>
    <span
        class="mb-2 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
    >
        RESOURCE REQUESTS
        <span class="ml-1 text-t-accent">({{ resourceRequests.length }})</span>
    </span>
    <div class="space-y-1.5">
        <div
            v-for="(req, i) in resourceRequests"
            :key="`${req.timestamp}-${i}`"
            class="rounded border border-t-border bg-t-surface px-2.5 py-1.5"
        >
            <div class="flex items-center justify-between">
                <span class="font-mono text-[11px] font-bold text-t-text">
                    {{ req.resource_label }}
                </span>
                <span class="font-mono text-[9px] text-t-text-faint">
                    {{ formatTime(req.timestamp) }}
                </span>
            </div>
            <div class="text-[10px] text-t-text-dim">
                {{ req.requested_by }}
            </div>
            <div
                v-if="req.notes"
                class="mt-1 text-[10px] text-t-text-faint"
            >
                {{ req.notes }}
            </div>
        </div>
    </div>
</div>
```

**Merged list source** — combine `incident.resource_requests` (from state-sync / initial Inertia load) with `resourceRequestsByIncident.get(incident.id)` (session accumulation). Deduplicate on `timestamp` to avoid double-counting a request that arrived just before a reconnect. Newest-first sort by timestamp (D-07).

### Pattern 5: Audio Tone Helper — `playResourceRequestTone()`

[VERIFIED: `resources/js/composables/useAlertSystem.ts` — existing tone helpers]

Existing tones for reference:
- `playPriorityTone('P1')` — 6-note A5/E5 alternation at gain 0.3 (alarm-like, line 9-12)
- `playPriorityTone('P2')` — 2-note 700Hz at gain 0.3 (assertive)
- `playAckExpiredTone()` — 2-note 600Hz at gain 0.3 (warning, line 81-102)
- `playMessageTone()` — 2-note C5→E5 ascending at gain 0.12, sine wave (subtle, line 115-140)

**Design for `playResourceRequestTone`** — must be distinguishable from all four:
- Three-note ascending arpeggio: 523Hz (C5) → 659Hz (E5) → 784Hz (G5) — major triad, urgency without alarm
- Duration 0.15s per note, offset 0.15s (slightly longer + slower than messageTone)
- Gain 0.22 — louder than messageTone (0.12), quieter than priorityTone (0.3), signals "operator attention required but not emergency"
- Oscillator type: `'triangle'` — distinct timbre from sine (message) and default square (priority)

```typescript
function playResourceRequestTone(): void {
    const ctx = ensureAudioContext();

    if (!ctx || ctx.state !== 'running') {
        return;
    }

    const notes = [523, 659, 784];     // C5, E5, G5 — major triad
    const duration = 0.15;
    const offset = 0.15;

    for (let i = 0; i < notes.length; i++) {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'triangle';
        osc.frequency.value = notes[i];
        gain.gain.value = 0.22;
        osc.start(ctx.currentTime + i * offset);
        gain.gain.exponentialRampToValueAtTime(
            0.01,
            ctx.currentTime + i * offset + duration,
        );
        osc.stop(ctx.currentTime + i * offset + duration);
    }
}
```

Add to return object at line 142-147.

### Anti-Patterns to Avoid

- **Subscribing to `ChecklistUpdated` / `ResourceRequested` from inside `IncidentDetailPanel.vue`** — the panel mounts/unmounts on incident selection; subscribing there creates churn and misses events for non-selected incidents. Subscribe at the `useDispatchFeed` level so the state is always live for the whole queue. [CITED: Phase 12 RESEARCH.md Anti-Patterns — exact same rationale]
- **Modifying `ChecklistUpdated` payload to include `checklist_data`** — explicitly deferred per D-10 and Deferred Ideas. The progress bar only needs `checklist_pct`.
- **Adding a new Pest test file for event assertions** — D-14 is clear: extend existing tests. The two existing tests already `Event::fake(…)` and `assertDispatched(…)`; they just need assertion closures that inspect channel and payload.
- **Hardcoding fetch URLs in the composable/component** — not applicable here (no new endpoints called from frontend), but relevant if state-sync extension reveals the need for a new GET endpoint. Use Wayfinder action imports instead (CLAUDE.md convention; Phase 16 explicitly cleans up existing hardcoded URLs).
- **Making `resource_requests` a first-class relationship on `Incident`** — a new migration for something already captured by `incident_timeline` is scope creep. Query timeline with the `event_type='resource_requested'` filter; transform in state-sync.
- **Auto-opening / toasting on `ResourceRequested`** — explicitly deferred. Dispatcher attention is earned via ticker + audio + detail-panel list; no modal.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| WebSocket subscription lifecycle | Manual `Echo.private().listen()` | `useEcho` composable | Auto-subscribes on mount, auto-cleans on unmount, reactive deps, already the project pattern (4 existing usages) |
| Audio context management | New AudioContext creation | Existing `ensureAudioContext()` in `useAlertSystem.ts:30-46` | Handles user-gesture unlock and singleton already |
| Event payload reshaping | Inline payload extraction | Typed `ChecklistUpdatedPayload` / `ResourceRequestedPayload` interfaces | Keeps `useEcho` generic param honest; Phase 12 pattern |
| Progress bar component | Installing a Vue progress-bar library | Inline Tailwind + div pattern | Single visual element; adding a dep violates CLAUDE.md "Do not change dependencies without approval" |
| Ticker entry schema | New ticker event type | Extended `TickerEvent` with optional fields | Avoids a second ticker pipeline |
| Channel auth for new events | New channel constant | `dispatch.incidents` already authorized | No new auth surface |
| Session state for requests | Inertia prop sharing | `resourceRequestsByIncident` Map in `useDispatchFeed` | Matches `messagesByIncident` pattern; survives WebSocket events without server round-trips |
| State-sync custom shape | New `/api/resource-requests` endpoint | Extend `StateSyncController` payload | One source of truth for reconnect recovery |

**Key insight:** Every building block exists. The trap here is over-engineering (new components, new endpoints, new migrations) when the discipline is "extend by analogy" to the `messagesByIncident` / `MutualAidRequested` precedents.

## Runtime State Inventory

Phase 15 is purely additive (new subscribers, new UI sections, new tone, extended state-sync). No renames, migrations, or refactors. This inventory is included for completeness but is minimal.

| Category | Items Found | Action Required |
|----------|-------------|-----------------|
| Stored data | None — verified by reading Incident model, timeline model, state-sync. `resource_requests` surface is a derived view of `incident_timeline` rows with `event_type='resource_requested'`. | None |
| Live service config | None | None |
| OS-registered state | None | None |
| Secrets/env vars | None | None |
| Build artifacts | Vite build (`npm run build`) needs to run after frontend changes | Standard build after code changes — `composer run dev` handles this in dev; `npm run build` on deploy |

**Nothing found in category:** All five categories are clean. This is a code-only additive change.

## Common Pitfalls

### Pitfall 1: `checklist_pct` Serialization Mismatch on Initial Page Load

**What goes wrong:** Progress bar renders at 0% on page load even though the database has `checklist_pct = 80`.
**Why it happens:** `DispatchConsoleController::show()` at line 47-62 does `$incident->toArray()` but `checklist_pct` is already on the fillable list and cast to integer (`app/Models/Incident.php:52, 80`). Current pattern is fine — but the serialized data passes through the `IncidentCreatedPayload` → `DispatchIncident` synthesis in `useDispatchFeed.ts:127-168` which explicitly sets `checklist_pct: null` in its reconstruction. For non-created (already-loaded) incidents on page load, however, the value comes from `props.incidents` → `localIncidents` via a spread, which DOES preserve the field.
**How to avoid:** Verify `checklist_pct` is non-null in the initial `props.incidents` payload during manual verification (D-16). If null appears when it shouldn't, check the `DispatchConsoleController::show()` eager-load chain.
**Warning signs:** On-scene incidents show 0% until the next WebSocket event.

### Pitfall 2: State-Sync Status Filter Mismatch

**What goes wrong:** On reconnect, `resource_requests` don't populate because the incident isn't in the state-sync response.
**Why it happens:** `StateSyncController::__invoke` (line 18-23) filters to `IncidentStatus::Pending` only — but resource requests are only ever made on `ON_SCENE` or later incidents. Pending incidents have no assigned units and therefore no resource requests.
**How to avoid:** Extend the state-sync query to include dispatch-status incidents (the dispatch console is what actually consumes state-sync). This is arguably a bug in the current code (state-sync should match the incidents the dispatch console expects, which is `DispatchConsoleController::show()` `$dispatchStatuses` list — `TRIAGED, DISPATCHED, EN_ROUTE, ON_SCENE`). See Open Question 1.
**Warning signs:** Reload the dispatch page → resource requests list disappears. (Manual verification D-16 will catch this.)

### Pitfall 3: Ticker Entry Overflow Suppresses Resource Request

**What goes wrong:** A busy shift generates many `IncidentCreated` / `IncidentStatusChanged` ticker events, and a resource request drops off the buffer before the dispatcher notices.
**Why it happens:** `MAX_TICKER_EVENTS = 20` in `useDispatchFeed.ts:30`. Newest-first push + pop(last) means the oldest scrolls out.
**How to avoid:** Accept for v1 — audio tone (D-09) and detail-panel list (D-07) are the durable surfaces; ticker is the glanceable surface. Consider a "sticky" ticker category in v2 if dispatchers report missing requests.
**Warning signs:** Dispatcher reports "I didn't see it in the ticker" but the detail-panel shows the request correctly.

### Pitfall 4: `requested_by` Field Is a Name, Not a Unit Callsign

**What goes wrong:** Ticker entry shows "Juan Dela Cruz" but dispatcher expects "AMB-01 — Juan Dela Cruz".
**Why it happens:** `ResourceRequested` event payload uses `'requested_by' => $this->requester->name` (line 47 of the event) — it's the User's name, not their unit's callsign. [VERIFIED: `app/Events/ResourceRequested.php:47`]
**How to avoid:** D-06 says "requester callsign/name". This phase should NOT modify the event payload (D-11 locks it to already-complete), so callsign enrichment must either (a) happen on the backend side in a follow-up phase, or (b) be accepted as name-only for v1. The frontend should display whatever `requested_by` gives it. Flag to the planner: D-06 and D-11 are in slight tension. Recommend frontend displays `requested_by` as-is and surfaces unit callsign only when the data becomes available.
**Warning signs:** Ambiguity in the ticker when a name is shared between responders (unlikely but possible).

### Pitfall 5: Own-Message Filter Pattern Does Not Apply Here

**What goes wrong:** Developer copy-pastes the `m.sender_id === currentUserId` guard from the `MessageSent` handler (line 307-309) to the `ResourceRequested` handler.
**Why it happens:** Pattern-match copy from the closest sibling.
**How to avoid:** `ResourceRequested` originates from responders — a dispatcher never dispatches it. No self-echo to filter. The handler should always execute.
**Warning signs:** Resource requests fail to render in dev testing where the dispatcher happens to share an auth session with something.

### Pitfall 6: Clearing `resourceRequestsByIncident` on Status Change

**What goes wrong:** When an incident resolves, its resource-request history lingers in the Map, slowly growing session memory.
**Why it happens:** Existing `IncidentStatusChanged` exit logic at `useDispatchFeed.ts:245-254` clears `unreadByIncident` and `messagesByIncident`, but a newly added Map won't be cleared unless explicitly coded.
**How to avoid:** Mirror the existing pattern — when `exitStatuses.includes(e.new_status)`, also delete from `resourceRequestsByIncident`. Also clear in `onStateSync` (line 439-440).
**Warning signs:** Memory profile grows linearly with session duration. Catchable only in long soak tests.

## Code Examples

Verified patterns from the existing codebase. File references are load-bearing.

### Insertion Point — New Subscribers

```typescript
// Source: resources/js/composables/useDispatchFeed.ts:288-336 (MutualAidRequested and MessageSent)
// INSERT between MutualAidRequested (line 301) and MessageSent (line 303)

useEcho<MutualAidPayload>(
    'dispatch.incidents',
    'MutualAidRequested',
    (e) => { /* ... line 292-300 ... */ },
);

// ===================== NEW SUBSCRIBERS — PHASE 15 =====================
useEcho<ChecklistUpdatedPayload>(
    'dispatch.incidents',
    'ChecklistUpdated',
    (e) => {
        const index = localIncidents.value.findIndex(
            (inc) => inc.id === e.incident_id,
        );
        if (index === -1) return;
        localIncidents.value[index].checklist_pct = e.checklist_pct;
    },
);

useEcho<ResourceRequestedPayload>(
    'dispatch.incidents',
    'ResourceRequested',
    (e) => {
        alertSystem.playResourceRequestTone();

        addTickerEvent({
            incident_no: e.incident_no,
            priority: 'P1',
            channel: 'radio',
            incident_type: `Resource: ${e.resource_label}`,
            location_text: [e.requested_by, e.notes]
                .filter((s): s is string => Boolean(s))
                .join(' — '),
            created_at: e.timestamp,
        });

        const updated = new Map(resourceRequestsByIncident.value);
        const existing = updated.get(e.incident_id) ?? [];
        updated.set(e.incident_id, [
            {
                resource_type: e.resource_type,
                resource_label: e.resource_label,
                notes: e.notes,
                requested_by: e.requested_by,
                timestamp: e.timestamp,
            },
            ...existing,
        ]);
        resourceRequestsByIncident.value = updated;
    },
);
// ======================================================================

useEcho<DispatchMessagePayload>(
    'dispatch.incidents',
    'MessageSent',
    (m) => { /* ... line 306-335 ... */ },
);
```

### Pest Event Assertion — Minimal Diff

```php
// Source: tests/Feature/Responder/ChecklistTest.php:47 — current assertion (trivial)
Event::assertDispatched(ChecklistUpdated::class);

// Phase 15 tightened assertion:
Event::assertDispatched(ChecklistUpdated::class, function (ChecklistUpdated $event) use ($incident) {
    // Channel assertion
    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-dispatch.incidents');

    // Payload assertion
    $payload = $event->broadcastWith();
    expect($payload)->toHaveKeys(['incident_id', 'incident_no', 'checklist_pct']);
    expect($payload['incident_id'])->toBe($incident->id);
    expect($payload['checklist_pct'])->toBe(50);

    return true;
});
```

**Import needed at top of the test:**

```php
use Illuminate\Broadcasting\PrivateChannel;
```

**Note on channel name:** Laravel prefixes private channels with `'private-'` at runtime. The `PrivateChannel::name` property reflects this. [VERIFIED: source of `Illuminate\Broadcasting\PrivateChannel::__construct` — name set as `'private-'.$name`.] Search-docs with `['private channel', 'channel name']` for confirmation if needed.

### Pest Event Assertion for ResourceRequested

```php
// tests/Feature/Responder/ResourceRequestTest.php — add to first test
Event::assertDispatched(ResourceRequested::class, function (ResourceRequested $event) use ($incident) {
    $channels = $event->broadcastOn();
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-dispatch.incidents');

    $payload = $event->broadcastWith();
    expect($payload)->toHaveKeys([
        'incident_id',
        'incident_no',
        'resource_type',
        'resource_label',
        'notes',
        'requested_by',
        'timestamp',
    ]);
    expect($payload['incident_id'])->toBe($incident->id);
    expect($payload['resource_type'])->toBe('ADDITIONAL_AMBULANCE');
    expect($payload['resource_label'])->toBe('Additional Ambulance');
    expect($payload['notes'])->toBe('Multiple casualties, need additional transport.');

    return true;
});
```

### State-Sync Extension

```php
// Source: app/Http/Controllers/StateSyncController.php (current — lines 17-42)
$incidents = Incident::query()
    ->with('incidentType', 'barangay')
    ->where('status', IncidentStatus::Pending)  // SEE OPEN QUESTION 1
    ->orderByRaw(...)
    ->get();

// Phase 15 extension — eager-load resource-request timeline entries:
$incidents = Incident::query()
    ->with(['incidentType', 'barangay', 'timeline' => function ($q) {
        $q->where('event_type', 'resource_requested')
          ->orderByDesc('created_at');
    }, 'timeline.actor'])
    ->where('status', IncidentStatus::Pending)   // or expand to dispatch statuses
    ->orderByRaw(...)
    ->get()
    ->map(function (Incident $inc) {
        $data = $inc->toArray();
        $data['resource_requests'] = $inc->timeline
            ->map(fn ($t) => [
                'resource_type' => $t->event_data['type'] ?? null,
                'resource_label' => $t->event_data['label'] ?? null,
                'notes' => $t->event_data['notes'] ?? null,
                'requested_by' => $t->actor?->name ?? 'Unknown',
                'timestamp' => $t->created_at->toISOString(),
            ])
            ->values()
            ->all();
        unset($data['timeline']);
        return $data;
    });
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Dispatcher sees checklist % only on page reload | Real-time via `ChecklistUpdated` subscriber | This phase (Phase 15) | Dispatcher has continuous scene-progress awareness without polling |
| Resource requests arrive silently to timeline only | Real-time ticker + detail-panel list + audio cue | This phase | Dispatcher notices field requests without missing them |
| State-sync does not expose resource-request history | State-sync includes `resource_requests[]` per incident | This phase | Reconnect/reload doesn't lose history |

**Deprecated/outdated after this phase:**
- "Partial" status for RSPDR-06 and RSPDR-10 in `.planning/v1.0-MILESTONE-AUDIT.md` — these close to "fully satisfied"
- The two orphaned broadcast events (`ChecklistUpdated`, `ResourceRequested`) in the audit's Broadcast Event → Subscriber Matrix

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Laravel's `PrivateChannel::name` property returns `'private-dispatch.incidents'` (with `private-` prefix) when inspected inside the event's `broadcastOn()` return array | Pest Event Assertion example | If the prefix is NOT applied at construction time, the assertion `expect($channels[0]->name)->toBe('private-dispatch.incidents')` would fail. Fallback: assert against `'dispatch.incidents'` without prefix, or cast to string via `(string) $channels[0]`. Verify with a quick `php artisan tinker` during Wave 0. |
| A2 | `event_data` JSON in timeline rows for `event_type='resource_requested'` contains the keys `type`, `label`, `notes` (and nothing else required) | State-Sync Extension example | Verified partially — `ResponderController::requestResource` line 366-373 writes exactly `['type' => ..., 'label' => ..., 'notes' => ...]`. The assumption holds UNLESS seeded/old data has a different shape. Low risk for a gap-closure phase where no legacy data is in scope. |
| A3 | Extending state-sync to include dispatch-status incidents (not just PENDING) is the intended fix for the mismatch between state-sync and the dispatch console's visible set | Pitfall 2, Open Question 1 | If state-sync was intentionally narrow, this expansion would silently enlarge Reverb reconnect payloads. Needs user confirmation before implementation. **PLANNER: flag for discussion.** |
| A4 | The `requested_by` field's current value (User name) is acceptable for v1 display, even though D-06 mentions "callsign/name" | Pitfall 4 | If D-06 is strictly enforced, the event payload needs to change to include unit callsign — which contradicts D-11 ("no backend change needed"). **PLANNER: flag this tension; recommend v1 ships with name-only.** |
| A5 | Adding a new `useEcho` listener at the top level of `useDispatchFeed.ts` (outside any watch/watchEffect) is the correct pattern — it subscribes once on composable setup and does not need re-binding because `dispatch.incidents` is a constant channel name | Pattern 1 | Verified — the existing 4 listeners on `dispatch.incidents` use this shape. No risk. |

**If this table is empty:** This research contains 5 assumed claims — 3 are low-risk verification items, 2 are user-decision items flagged for the planner and discuss-phase loop.

## Open Questions

1. **Should state-sync expand beyond PENDING incidents, and does any reload flow rely on state-sync for dispatch-status incidents today?**
   - What we know: `StateSyncController::__invoke` returns only `IncidentStatus::Pending` (line 20). `DispatchConsoleController::show` returns `TRIAGED, DISPATCHED, EN_ROUTE, ON_SCENE` (line 40-45). The dispatch console's `useDispatchFeed.onStateSync` handler at line 372 fully replaces `localIncidents` from the state-sync response. This means a reconnect that triggers state-sync will DROP all dispatch-status incidents currently on screen — which seems unintended but may be historical.
   - What's unclear: Whether the reconnect flow is actually triggered often enough in production to matter, or whether Inertia's page-level reload covers the usual reload case and state-sync is only for long-idle browser resumption. Worth checking the WebSocket reconnect test `tests/Feature/RealTime/StateSyncTest.php` for intent.
   - Recommendation: Extend the state-sync query to include the dispatch-status list alongside PENDING. Add a `StateSyncTest` assertion for an `ON_SCENE` incident appearing with its `resource_requests[]` populated. This also fixes Pitfall 2. Confirm with user during plan-checker loop whether widening the filter is acceptable.

2. **Does `requested_by` need enrichment with unit callsign for v1, or is user name sufficient?**
   - What we know: `ResourceRequested` payload carries `requested_by = $this->requester->name` (User name). D-06 reads "requester callsign/name"; D-11 reads "no backend change needed".
   - What's unclear: Whether "callsign/name" in D-06 means "callsign AND name" or "callsign OR name".
   - Recommendation: Ship v1 with `requested_by` as the User name only. Add a follow-up item if field reports indicate ambiguity. If the user clarifies D-06 to require callsign, revisit — but that contradicts D-11.

3. **Should the `resourceRequestsByIncident` Map be cleared on `onStateSync`, or merged with server-provided `resource_requests`?**
   - What we know: The existing `onStateSync` handler (line 439-440) clears `unreadByIncident` and `messagesByIncident` (they are session-local). But `resource_requests` now have a durable server-side source (timeline).
   - What's unclear: Whether to clear-then-let-state-sync-rehydrate (simpler) or merge (avoids flicker).
   - Recommendation: Clear-then-rehydrate. On state-sync, the incident type field `resource_requests` from the server becomes authoritative; the composable Map can be cleared. The detail-panel rendering logic should union `incident.resource_requests` and `resourceRequestsByIncident.get(id)` — so fresh in-session events added after state-sync still render. This matches the messages model.

4. **Does the existing `DispatchIncident` type need `resource_requests?: ResourceRequest[]` added, or only the base `Incident` type?**
   - What we know: `DispatchIncident extends Incident` (line 21 of `resources/js/types/dispatch.ts`). Adding to `Incident` propagates automatically.
   - What's unclear: Nothing — add to `Incident`, `DispatchIncident` inherits.
   - Recommendation: Add to `Incident` in `resources/js/types/incident.ts`.

## Environment Availability

This phase has no external dependencies.

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Laravel Reverb | Broadcasting | ✓ | v1 (already running) | — |
| @laravel/echo-vue | Subscribers | ✓ | v2 (already installed) | — |
| Web Audio API | Tones | ✓ | Browser-native | Graceful no-op via `ctx.state !== 'running'` guard (existing pattern) |
| Pest | Tests | ✓ | v4 (already installed) | — |

**Missing dependencies with no fallback:** None.

**Missing dependencies with fallback:** None.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=<pattern>` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| RSPDR-06 | `ChecklistUpdated` event broadcasts on `dispatch.incidents` with `incident_id`, `incident_no`, `checklist_pct` | integration (Pest Feature) | `php artisan test --compact tests/Feature/Responder/ChecklistTest.php -x` | ✅ (extend assertion) |
| RSPDR-06 | Dispatch console subscriber mutates `localIncidents[id].checklist_pct` | **manual** (D-15) | Human verification: responder completes checklist item → dispatch Scene Progress bar updates | ❌ (manual per D-16) |
| RSPDR-06 | Progress bar only shown when status ∈ {ON_SCENE, RESOLVING, RESOLVED} | **manual** (D-15) | Human verification: select TRIAGED incident → no progress bar; advance to ON_SCENE → bar appears | ❌ (manual per D-16) |
| RSPDR-10 | `ResourceRequested` event broadcasts on `dispatch.incidents` with full payload keys | integration (Pest Feature) | `php artisan test --compact tests/Feature/Responder/ResourceRequestTest.php -x` | ✅ (extend assertion) |
| RSPDR-10 | Dispatch console subscriber pushes to ticker + `resourceRequestsByIncident` Map + plays audio | **manual** (D-15) | Human verification: responder submits resource request → ticker entry appears + detail-panel list updates + audio cue fires | ❌ (manual per D-16) |
| RSPDR-10 | State-sync includes `resource_requests[]` per incident | integration (Pest Feature) | `php artisan test --compact tests/Feature/RealTime/StateSyncTest.php -x` | ✅ (extend with new test case) |

### Unit tests

None — this phase has no pure-unit-testable logic. Both backend events are already covered by existing tests; the frontend logic is integration-adjacent (WebSocket subscribers) which is manual-verified per D-15.

### Integration tests (Pest Feature — primary validation)

1. **`tests/Feature/Responder/ChecklistTest.php`** — TIGHTEN the existing `Event::assertDispatched(ChecklistUpdated::class)` at line 47 with a closure asserting:
   - `broadcastOn()` returns exactly one `PrivateChannel` named `dispatch.incidents`
   - `broadcastWith()` returns array with keys `incident_id`, `incident_no`, `checklist_pct`
   - `checklist_pct` value matches the computed percentage from the request
2. **`tests/Feature/Responder/ResourceRequestTest.php`** — TIGHTEN the existing `Event::assertDispatched(ResourceRequested::class)` at line 42 with a closure asserting:
   - `broadcastOn()` returns exactly one `PrivateChannel` named `dispatch.incidents`
   - `broadcastWith()` returns array with keys `incident_id`, `incident_no`, `resource_type`, `resource_label`, `notes`, `requested_by`, `timestamp`
   - `resource_type` value matches the requested type enum value
   - `resource_label` value matches the enum's `.label()` output
3. **`tests/Feature/RealTime/StateSyncTest.php`** — ADD a new test case:
   - Create an `ON_SCENE` incident with one `resource_requested` timeline entry
   - Act as dispatcher, GET `/state-sync`
   - Assert `incidents[0].resource_requests[0]` has `resource_type`, `resource_label`, `notes`, `requested_by`, `timestamp`
   - (Depends on Open Question 1 resolution — if state-sync is NOT extended to include dispatch-status incidents, this test needs a PENDING incident, which would block it since PENDING incidents have no assignees and therefore no resource requests. State-sync MUST be extended for D-08 to be meaningful.)

### Manual verification (D-15, D-16)

Per D-15 and Phase 4 / Phase 12 precedent, no Vitest / Playwright is introduced. Phase verification runs through a manual checklist:

1. **Scene Progress visibility gate (D-02)**
   - Select a TRIAGED incident → confirm no Scene Progress section visible
   - Advance to ON_SCENE → confirm bar appears at current `checklist_pct`
   - Advance to RESOLVING → bar still visible
2. **Scene Progress live update (RSPDR-06, D-01, D-04)**
   - Open responder app, complete a checklist item
   - Confirm dispatch Scene Progress bar fills/moves smoothly within ~1 second
   - Confirm NO audio cue plays
   - Confirm NO ticker entry appears for the checklist event (D-03)
3. **Resource Request flow (RSPDR-10, D-05, D-06, D-07, D-09)**
   - Open responder app, submit a resource request (e.g., "Medevac" with notes)
   - Confirm dispatch hears `playResourceRequestTone` audio cue
   - Confirm ticker entry appears: `Resource: Medevac — Juan Dela Cruz — Multiple casualties`
   - Confirm Resource Requests section in detail panel shows the new entry at the top of the list
   - Confirm timestamp is accurate
4. **State-sync reload (RSPDR-10, D-08)**
   - Make a resource request
   - Hard-reload the dispatch console (Cmd+R)
   - Confirm the Resource Requests section still shows the historical request
5. **Audio distinctiveness (D-09)**
   - Trigger in quick succession: new P2 incident, new message, resource request, mutual aid
   - Confirm each tone is subjectively distinguishable (user judgment)

### Coverage dimensions

| ROADMAP Success Criterion | Validation Type | Assertion |
|---------------------------|----------------|-----------|
| `useDispatchFeed.ts` subscribes to `ChecklistUpdated` and mutates `localIncidents[id].checklist_pct` | Manual verification (D-15, D-16, step 2) | Scene Progress bar moves in real time |
| `useDispatchFeed.ts` subscribes to `ResourceRequested` and surfaces in ticker + notifies operator | Manual verification (D-15, D-16, step 3) | Ticker shows request, audio fires, detail-panel list updates |
| Incident detail panel renders updated checklist % and resource request count reactively | Manual verification (D-15, D-16, steps 2 + 3) | No page reload required; values update within ~1s |
| Pest asserts events broadcast on correct channel + expected payload | Integration test (extended) | `Event::assertDispatched` closure returns true; `vendor/bin/pint --dirty` clean |

### Sampling Rate

- **Per task commit:** `php artisan test --compact tests/Feature/Responder/ChecklistTest.php tests/Feature/Responder/ResourceRequestTest.php tests/Feature/RealTime/StateSyncTest.php -x`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green + manual D-16 checklist passed before `/gsd-verify-work`

### Wave 0 Gaps

- None — all three test files exist and contain the patterns to extend:
  - `tests/Feature/Responder/ChecklistTest.php` ✓
  - `tests/Feature/Responder/ResourceRequestTest.php` ✓
  - `tests/Feature/RealTime/StateSyncTest.php` ✓

**No framework install needed; no new fixtures needed.**

## Security Domain

Gap closure phase with no new attack surface. Existing ASVS controls apply transitively.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes (transitive) | Laravel Fortify / session auth — already enforced; subscribers require authenticated dispatch-role session |
| V3 Session Management | yes (transitive) | Laravel session + Reverb auth per connection — already enforced at `routes/channels.php:9-11` |
| V4 Access Control | yes (transitive) | `Broadcast::channel('dispatch.incidents', …)` restricts to `[Operator, Dispatcher, Supervisor, Admin]` roles — already enforced |
| V5 Input Validation | yes (transitive) | `UpdateChecklistRequest` and `RequestResourceRequest` FormRequests already validate event-triggering inputs |
| V6 Cryptography | no | No new key material, no new payload encryption |

### Known Threat Patterns for Laravel + Echo + Reverb

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Cross-channel subscription leak (responder sees other responders' dispatch.incidents events) | Information disclosure | Channel auth check — `routes/channels.php` restricts `dispatch.incidents` to dispatch roles only. Responders do NOT have access. VERIFIED in Phase 3 `tests/Feature/RealTime/ChannelAuthorizationTest.php`. |
| Payload tampering via spoofed socket | Tampering | Reverb signs subscription auth responses; `ShouldDispatchAfterCommit` ensures payload reflects persisted state |
| Ticker/resource-request flooding | Denial of service | `MAX_TICKER_EVENTS = 20` buffer prevents unbounded growth; audio plays once per event (no retries) |
| Reflected inputs in `notes` field | XSS | Vue templating auto-escapes `{{ }}` bindings; no `v-html` recommended in new list rendering |
| State-sync returns too much data | Information disclosure | State-sync already gated to dispatch roles; filtering `event_type='resource_requested'` bounds the timeline subset |

No new security controls required.

## Sources

### Primary (HIGH confidence) — direct codebase inspection on 2026-04-17

- [VERIFIED] `app/Events/ChecklistUpdated.php` — channel `dispatch.incidents`, payload `{incident_id, incident_no, checklist_pct}`, interfaces `ShouldBroadcast, ShouldDispatchAfterCommit`
- [VERIFIED] `app/Events/ResourceRequested.php` — channel `dispatch.incidents`, payload `{incident_id, incident_no, resource_type, resource_label, notes, requested_by, timestamp}`
- [VERIFIED] `app/Http/Controllers/ResponderController.php:241-256` — updateChecklist endpoint dispatches event
- [VERIFIED] `app/Http/Controllers/ResponderController.php:359-384` — requestResource endpoint creates timeline + dispatches event
- [VERIFIED] `app/Http/Controllers/StateSyncController.php:14-43` — current state-sync shape
- [VERIFIED] `app/Models/Incident.php` — `checklist_pct` on fillable, integer cast
- [VERIFIED] `app/Models/IncidentTimeline.php` — `event_data` array cast, fillable includes `event_type`
- [VERIFIED] `app/Enums/ResourceType.php` — 6 enum cases with `label()` method
- [VERIFIED] `resources/js/composables/useDispatchFeed.ts` — 4-subscriber pattern, Map-reactivity pattern, state-sync handler
- [VERIFIED] `resources/js/composables/useAlertSystem.ts` — tone helper shapes, AudioContext singleton, gesture unlock
- [VERIFIED] `resources/js/components/dispatch/IncidentDetailPanel.vue` — section structure and design-token usage
- [VERIFIED] `resources/js/pages/dispatch/Console.vue` — composable destructure pattern, prop threading
- [VERIFIED] `resources/js/types/incident.ts` — `IncidentStatus` enum, `Incident`, `TickerEvent`, `StateSyncResponse` shapes
- [VERIFIED] `resources/js/types/dispatch.ts` — `DispatchIncident extends Incident`
- [VERIFIED] `resources/js/layouts/DispatchLayout.vue` — `provide('tickerEvents', …)` wiring to DispatchTopbar
- [VERIFIED] `resources/js/components/dispatch/DispatchTopbar.vue` — ticker inject + latestEvent rendering
- [VERIFIED] `routes/channels.php` — `dispatch.incidents` auth for dispatch roles
- [VERIFIED] `routes/web.php:137, 141` — responder checklist/request-resource route names
- [VERIFIED] `tests/Feature/Responder/ChecklistTest.php` — existing Event::fake + assertDispatched pattern
- [VERIFIED] `tests/Feature/Responder/ResourceRequestTest.php` — existing pattern
- [VERIFIED] `tests/Feature/RealTime/StateSyncTest.php` — existing state-sync test structure
- [VERIFIED] `.planning/phases/12-bi-directional-dispatch-responder-communication/12-RESEARCH.md` — dual-channel broadcasting, reactive Map pattern, anti-patterns, code examples for Phase 12 (reference implementation)
- [VERIFIED] `.planning/phases/12-bi-directional-dispatch-responder-communication/12-02-PLAN.md` — reference for "add subscriber + UI section + audio tone" execution structure
- [VERIFIED] `.planning/phases/12-bi-directional-dispatch-responder-communication/12-02-SUMMARY.md` — lessons learned
- [VERIFIED] `.planning/v1.0-MILESTONE-AUDIT.md` — identifies exact gap (events dispatch but no subscriber)

### Secondary (MEDIUM confidence)

- [CITED] `.claude/skills/echo-vue-development/SKILL.md` — `useEcho` composable API, deps parameter, auto-cleanup on unmount
- [CITED] `.claude/skills/echo-development/SKILL.md` — PrivateChannel construction, ShouldDispatchAfterCommit semantics
- [CITED] `.planning/STATE.md` — accumulated decisions: [12-01] PrivateChannel choice, [12-02] reactive Map replacement, [05-03] fire-and-forget fetch, [04-04] useDispatchFeed as event hub

### Tertiary (LOW confidence)

- [ASSUMED] A1, A2, A3, A4, A5 in Assumptions Log — see that section for risk and mitigation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already installed and in use; zero new dependencies
- Architecture: HIGH — pattern is a direct extension of the 4 existing `useEcho` subscribers; Phase 12 reference is exact structural match
- Pitfalls: HIGH — derived from direct code reading of current broadcast events, state-sync controller, composable state flow, and existing Pest test patterns
- Validation: HIGH — Pest 4 pattern is well-established; extending existing files not creating new ones

**Research date:** 2026-04-17
**Valid until:** 2026-05-17 (stable — no backend broadcast events changing, no dependency updates expected)
