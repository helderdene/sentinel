# Phase 15: Close RSPDR Real-Time Dispatch Visibility - Pattern Map

**Mapped:** 2026-04-17
**Files analyzed:** 8 modified / 0 created
**Analogs found:** 8 / 8

## File Classification

| Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---------------|------|-----------|----------------|---------------|
| `resources/js/composables/useDispatchFeed.ts` | composable (feed / state aggregator) | event-driven (WebSocket subscribers) | **self** — `MutualAidRequested` + `MessageSent` listeners at lines 288-336 | exact (extend existing file) |
| `resources/js/composables/useAlertSystem.ts` | composable (audio helper) | imperative synth trigger | **self** — `playMessageTone()` at lines 115-140 | exact (extend existing file) |
| `resources/js/components/dispatch/IncidentDetailPanel.vue` | component (presentational section host) | one-way props | **self** — "Assignees" section at lines 327-372 | exact (extend existing file) |
| `resources/js/pages/dispatch/Console.vue` | page (prop/composable bridge) | one-way wiring | **self** — `getMessages` destructure + `selectedIncidentMessages` computed at lines 139-191 | exact (extend existing file) |
| `resources/js/types/incident.ts` | types module | type declarations | **self** — `IncidentCreatedPayload` / `TickerEvent` / `IncidentStatusChangedPayload` at lines 105-137 | exact (extend existing file) |
| `app/Http/Controllers/StateSyncController.php` | controller | request-response (JSON) | **self** — `__invoke` query + response at lines 16-42 | exact (extend existing file) |
| `tests/Feature/Responder/ChecklistTest.php` | test (feature) | Pest `Event::fake` + assertion | **self** — current `Event::assertDispatched(ChecklistUpdated::class)` at line 47 | exact (tighten existing) |
| `tests/Feature/Responder/ResourceRequestTest.php` | test (feature) | Pest `Event::fake` + assertion | **self** — current `Event::assertDispatched(ResourceRequested::class)` at line 42 | exact (tighten existing) |
| `tests/Feature/RealTime/StateSyncTest.php` | test (feature) | HTTP JSON assert | **self** — "returns incidents ordered..." test at lines 9-36 | exact (add new test) |

---

## Pattern Assignments

### `resources/js/composables/useDispatchFeed.ts` (composable, event-driven)

Two new `useEcho` subscribers and one new reactive Map. All idioms already exist in this file — clone them.

**Analog — `useEcho` subscriber shape** (lines 288-301, `MutualAidRequested`):
```typescript
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

**Analog — "find-index guard, drop if missing"** (lines 236-241, `IncidentStatusChanged`):
```typescript
const index = localIncidents.value.findIndex((inc) => inc.id === e.id);
if (index === -1) {
    return;
}
```

**Analog — reactive Map replacement pattern** (lines 322-325, `MessageSent` handler):
```typescript
const updatedMessages = new Map(messagesByIncident.value);
const existing = updatedMessages.get(m.incident_id) ?? [];
updatedMessages.set(m.incident_id, [...existing, messageItem]);
messagesByIncident.value = updatedMessages;
```

**Analog — Map ref declaration + helper export** (lines 42, 101-103, 447-455):
```typescript
const messagesByIncident = ref(new Map<string, DispatchMessageItem[]>());
// ...
function getMessages(incidentId: string): DispatchMessageItem[] {
    return messagesByIncident.value.get(incidentId) ?? [];
}
// ...
return {
    tickerEvents,
    unreadByIncident,
    totalUnreadMessages,
    messagesByIncident,
    clearUnread,
    getMessages,
    addLocalMessage,
};
```

**Analog — clear-on-exit pattern** (lines 245-254, inside `IncidentStatusChanged`):
```typescript
const exitStatuses = ['RESOLVED', 'PENDING'];
if (exitStatuses.includes(e.new_status)) {
    localIncidents.value.splice(index, 1);
    const updatedUnread = new Map(unreadByIncident.value);
    updatedUnread.delete(e.id);
    unreadByIncident.value = updatedUnread;
    const updatedMessages = new Map(messagesByIncident.value);
    updatedMessages.delete(e.id);
    messagesByIncident.value = updatedMessages;
}
```
*Copy shape: add `updatedResources.delete(e.id)` block for the new `resourceRequestsByIncident` Map.*

**Analog — state-sync clear** (lines 439-440, inside `onStateSync`):
```typescript
unreadByIncident.value = new Map();
messagesByIncident.value = new Map();
```
*Copy shape: add `resourceRequestsByIncident.value = new Map();` after line 440.*

---

### `resources/js/composables/useAlertSystem.ts` (composable, imperative synth)

**Analog — closest structural twin for new `playResourceRequestTone()`** (lines 115-140, `playMessageTone`):
```typescript
function playMessageTone(): void {
    const ctx = ensureAudioContext();

    if (!ctx || ctx.state !== 'running') {
        return;
    }

    const notes = [523, 659];
    const duration = 0.1;

    for (let i = 0; i < notes.length; i++) {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.value = notes[i];
        gain.gain.value = 0.12;
        osc.start(ctx.currentTime + i * 0.12);
        gain.gain.exponentialRampToValueAtTime(
            0.01,
            ctx.currentTime + i * 0.12 + duration,
        );
        osc.stop(ctx.currentTime + i * 0.12 + duration);
    }
}
```

**Analog — return-object export** (lines 142-147):
```typescript
return {
    playPriorityTone,
    playAckExpiredTone,
    triggerP1Flash,
    playMessageTone,
};
```
*Copy shape: insert `playResourceRequestTone,` into the return object. Differentiate tone per RESEARCH §Pattern 5 (triangle wave, C5→E5→G5 arpeggio, gain 0.22, 3 notes × 0.15s).*

---

### `resources/js/components/dispatch/IncidentDetailPanel.vue` (component, one-way props)

Two new sections inserted between existing ones. Match section chrome exactly.

**Analog — section shell with uppercase label + optional count chip** (lines 327-342, "Assignees"):
```vue
<div class="border-b border-t-border px-3 py-2.5">
    <span
        class="mb-2 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"
    >
        ASSIGNEES
        <span
            v-if="
                incident.assigned_units &&
                incident.assigned_units.length > 0
            "
            class="ml-1 text-t-accent"
        >
            ({{ incident.assigned_units.length }})
        </span>
    </span>
    ...
</div>
```

**Analog — list item card** (lines 350-367, per-assignee row):
```vue
<div
    v-for="au in incident.assigned_units"
    :key="au.unit_id"
    class="flex items-center justify-between rounded border border-t-border bg-t-surface px-2.5 py-1.5"
>
    <button ...>{{ au.callsign }}</button>
    <AckTimerRing ... />
</div>
```
*Copy shape: replace with resource-request card (resource_label + timestamp header, requester line, optional notes line). Use `rounded border border-t-border bg-t-surface px-2.5 py-1.5` exactly.*

**Analog — conditional section (only shown when data present)** (lines 308-317, "Notes"):
```vue
<div v-if="incident.notes" class="border-b border-t-border px-3 py-2.5">
    <span class="mb-1 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase">
        NOTES
    </span>
    <p class="text-xs text-t-text-dim">
        {{ incident.notes }}
    </p>
</div>
```
*Copy shape for Scene Progress `v-if="showChecklistProgress"` gate. Insert after Status Pipeline (line 325), before Assignees (line 327). Resource Requests section goes after Assignees (line 372), before Available Units (line 374).*

**Analog — `defineProps` extension** (lines 23-30):
```typescript
const props = defineProps<{
    incident: DispatchIncident;
    agencies: DispatchAgency[];
    messages: DispatchMessageItem[];
    messagesExpanded: boolean;
    currentUserId: number;
    unreadCount: number;
}>();
```
*Copy shape: add `resourceRequests: ResourceRequest[];` to the props type.*

---

### `resources/js/pages/dispatch/Console.vue` (page, one-way wiring)

**Analog — destructure + computed selector + prop pass-through** (lines 139-191 and lines 389-402):
```typescript
const {
    tickerEvents,
    unreadByIncident,
    totalUnreadMessages,
    clearUnread,
    getMessages,
    addLocalMessage,
} = useDispatchFeed(...);

const selectedIncidentMessages = computed<DispatchMessageItem[]>(() => {
    if (!selectedIncidentId.value) {
        return [];
    }
    return getMessages(selectedIncidentId.value);
});
```

```vue
<IncidentDetailPanel
    ...
    :messages="selectedIncidentMessages"
    ...
/>
```
*Copy shape: add `getResourceRequests` to the destructure, add `selectedIncidentResourceRequests` computed, pass as `:resource-requests="selectedIncidentResourceRequests"` on the panel. Merge rule (per RESEARCH §Open Question 3): union `selectedIncident.resource_requests ?? []` (state-sync-hydrated) with `getResourceRequests(selectedIncidentId.value)` (session), dedupe by `timestamp`, sort newest-first.*

---

### `resources/js/types/incident.ts` (types module, declarations)

**Analog — payload interface shape** (lines 105-120, `IncidentCreatedPayload`):
```typescript
export interface IncidentCreatedPayload {
    id: string;
    incident_no: string;
    incident_type_id: number;
    priority: IncidentPriority;
    status: IncidentStatus;
    incident_type: string | null;
    location_text: string;
    barangay: string | null;
    channel: IncidentChannel;
    coordinates: { lat: number; lng: number } | null;
    caller_name: string | null;
    caller_contact: string | null;
    notes: string | null;
    created_at: string;
}
```

**Analog — small broadcast payload** (lines 131-137, `IncidentStatusChangedPayload`):
```typescript
export interface IncidentStatusChangedPayload {
    id: string;
    incident_no: string;
    old_status: IncidentStatus;
    new_status: IncidentStatus;
    priority: IncidentPriority;
}
```
*Copy shape for `ChecklistUpdatedPayload { incident_id: string; incident_no: string; checklist_pct: number; }` and `ResourceRequestedPayload { incident_id; incident_no; resource_type; resource_label; notes: string | null; requested_by; timestamp; }`.*

**Analog — `Incident` field extension** (line 66, existing `checklist_pct` field is already present — only add `resource_requests?: ResourceRequest[]` per RESEARCH §Open Question 4).

**Analog — existing `TickerEvent`** (lines 122-129) — D-03/D-06 do NOT require extension; `incident_type` and `location_text` carry the resource_label and requester info per the handler excerpt in RESEARCH lines 659-666.

---

### `app/Http/Controllers/StateSyncController.php` (controller, request-response)

**Analog — current query + response** (lines 16-42):
```php
public function __invoke(): JsonResponse
{
    $incidents = Incident::query()
        ->with('incidentType', 'barangay')
        ->where('status', IncidentStatus::Pending)
        ->orderByRaw("CASE priority WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 WHEN 'P3' THEN 3 WHEN 'P4' THEN 4 END")
        ->orderBy('created_at', 'asc')
        ->get();

    $channelCounts = Incident::query()
        ->where('status', IncidentStatus::Pending)
        ->selectRaw('channel, count(*) as count')
        ->groupBy('channel')
        ->pluck('count', 'channel');

    $units = Unit::query()
        ->active()
        ->where('status', '!=', UnitStatus::Offline)
        ->select('id', 'callsign', 'type', 'status', 'coordinates')
        ->get();

    return response()->json([
        'incidents' => $incidents,
        'channelCounts' => $channelCounts,
        'units' => $units,
    ]);
}
```
*Copy shape: (a) widen the `where('status', …)` clause to include dispatch statuses (see RESEARCH §Open Question 1 — planner must flag for user confirmation); (b) eager-load `timeline` with `where('event_type', 'resource_requested')` filter; (c) map each incident to attach a `resource_requests` array derived from `$inc->timeline->map(...)` — see RESEARCH lines 754-786 for the exact shape.*

**Analog — timeline event_data contract** (from `ResponderController::requestResource`, lines 365-373):
```php
$incident->timeline()->create([
    'event_type' => 'resource_requested',
    'event_data' => [
        'type' => $resourceType->value,
        'label' => $resourceType->label(),
        'notes' => $request->validated('notes'),
    ],
    ...
]);
```
*Use these exact keys (`type`, `label`, `notes`) when mapping `timeline.event_data` → `resource_requests[]` in state-sync.*

---

### `tests/Feature/Responder/ChecklistTest.php` (test, Pest)

**Analog — current weak assertion** (line 47):
```php
Event::assertDispatched(ChecklistUpdated::class);
```

**Analog — beforeEach with Event::fake** (lines 11-16):
```php
beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        ChecklistUpdated::class,
    ]);
});
```
*Copy shape for tightened assertion (see RESEARCH lines 694-714):*
```php
Event::assertDispatched(ChecklistUpdated::class, function (ChecklistUpdated $event) use ($incident) {
    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-dispatch.incidents');

    $payload = $event->broadcastWith();
    expect($payload)->toHaveKeys(['incident_id', 'incident_no', 'checklist_pct']);
    expect($payload['incident_id'])->toBe($incident->id);
    expect($payload['checklist_pct'])->toBe(50);

    return true;
});
```
*Add `use Illuminate\Broadcasting\PrivateChannel;` at the top. Per A1 in RESEARCH Assumptions Log: verify `$channels[0]->name` prefix with tinker on Wave 0.*

---

### `tests/Feature/Responder/ResourceRequestTest.php` (test, Pest)

**Analog — current weak assertion** (line 42):
```php
Event::assertDispatched(ResourceRequested::class);
```
*Tighten using the same closure shape as above; payload keys per RESEARCH lines 728-750: `incident_id, incident_no, resource_type, resource_label, notes, requested_by, timestamp`. Sentinel values to assert: `resource_type === 'ADDITIONAL_AMBULANCE'`, `resource_label === 'Additional Ambulance'`, `notes === 'Multiple casualties, need additional transport.'`.*

---

### `tests/Feature/RealTime/StateSyncTest.php` (test, Pest — new test case)

**Analog — existing test shape** (lines 9-36, "returns incidents ordered by priority then FIFO"):
```php
it('returns incidents ordered by priority then FIFO', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $p3 = Incident::factory()->for($dispatcher, 'createdBy')->create([
        'priority' => 'P3',
        'status' => IncidentStatus::Pending,
        ...
    ]);
    ...
    $response = $this->actingAs($dispatcher)
        ->getJson(route('state-sync'))
        ->assertSuccessful();

    $ids = collect($response->json('incidents'))->pluck('id')->values()->all();
    expect($ids[0])->toBe($p1_older->id);
});
```
*Copy shape: create `ON_SCENE` incident with one `timeline()->create(['event_type' => 'resource_requested', 'event_data' => [...], 'actor_id' => $responder->id, 'actor_type' => User::class])`, call `route('state-sync')` as a dispatcher, assert `incidents[0].resource_requests[0]` contains `resource_type`, `resource_label`, `notes`, `requested_by`, `timestamp`.*

**Note:** This test depends on RESEARCH §Open Question 1 (state-sync status-filter widening). If the filter stays PENDING-only, this test cannot exist in a meaningful form — PENDING incidents have no assignees, therefore no resource requests. Planner must resolve OQ1 before scheduling this task.

---

## Shared Patterns

### Event-driven WebSocket subscriber
**Source:** `resources/js/composables/useDispatchFeed.ts:117-336` (four existing `useEcho` calls on `dispatch.incidents`)
**Apply to:** Both new subscribers (`ChecklistUpdated`, `ResourceRequested`) in Phase 15
- Always top-level (not inside `watch`/`watchEffect`)
- Always pass typed generic: `useEcho<FooPayload>('channel', 'EventName', handler)`
- Use `findIndex` + `-1` guard to silently drop events for incidents not in `localIncidents`

### Reactive Map replacement (Vue reactivity requirement)
**Source:** `resources/js/composables/useDispatchFeed.ts:96-99, 108-113, 248-254, 322-325`
**Apply to:** New `resourceRequestsByIncident` Map in Phase 15
```typescript
const updated = new Map(originalMapRef.value);
updated.set(key, newValue);
originalMapRef.value = updated;   // ← reassignment, not in-place mutation
```

### Tailwind design-token section chrome
**Source:** `resources/js/components/dispatch/IncidentDetailPanel.vue:319-325, 327-372, 308-317`
**Apply to:** Both new sections (Scene Progress, Resource Requests)
- Wrapper: `class="border-b border-t-border px-3 py-2.5"`
- Label: `class="mb-2 block font-mono text-[9px] font-bold tracking-[1.5px] text-t-text-faint uppercase"`
- Count badge (optional): `<span class="ml-1 text-t-accent">({{ n }})</span>`
- List item card: `class="rounded border border-t-border bg-t-surface px-2.5 py-1.5"`
- NEVER use raw `bg-zinc-*`, `bg-slate-*`, or fixed neutrals (Phase 10 token-only discipline enforced)

### Audio helper shape
**Source:** `resources/js/composables/useAlertSystem.ts:115-140` (`playMessageTone`)
**Apply to:** New `playResourceRequestTone`
- Always call `ensureAudioContext()` + guard `ctx.state !== 'running'` (user-gesture unlock)
- Use `osc.connect(gain)` → `gain.connect(ctx.destination)` chain
- Use `gain.gain.exponentialRampToValueAtTime(0.01, …)` for decay
- Schedule all oscillators up front (do not `await` between notes)

### Pest event assertion closure
**Source:** This phase's tightened pattern (RESEARCH lines 694-714 + 728-750)
**Apply to:** Both `ChecklistTest` and `ResourceRequestTest` tighten tasks
- Import `Illuminate\Broadcasting\PrivateChannel`
- Closure receives typed event, returns `true` if all assertions pass
- Assert channel class + name, then payload keys + selected values
- Retain the existing weak `assertDispatched` path for the second test in each file (only tighten one per file per D-14)

### Laravel broadcast event contract
**Source:** `app/Events/ChecklistUpdated.php`, `app/Events/ResourceRequested.php`
**Apply to:** No new events — both events are sealed (D-10, D-11)
- Events implement `ShouldBroadcast, ShouldDispatchAfterCommit`
- `broadcastOn(): array` returns `[new PrivateChannel('dispatch.incidents')]`
- `broadcastWith(): array` is the ONLY payload surface frontend sees (not public properties)

---

## No Analog Found

None. Every new code fragment has a nearby twin in the existing codebase. This is a pure extension phase.

---

## Metadata

**Analog search scope:** `resources/js/composables/`, `resources/js/components/dispatch/`, `resources/js/pages/dispatch/`, `resources/js/types/`, `app/Http/Controllers/`, `app/Events/`, `tests/Feature/Responder/`, `tests/Feature/RealTime/`
**Files scanned:** 11 (useDispatchFeed.ts, useAlertSystem.ts, IncidentDetailPanel.vue, Console.vue, incident.ts, dispatch.ts, StateSyncController.php, ResponderController.php, ChecklistUpdated.php, ResourceRequested.php, ChecklistTest.php, ResourceRequestTest.php, StateSyncTest.php)
**Pattern extraction date:** 2026-04-17
**Reference phase (precedent):** Phase 12 (`.planning/phases/12-bi-directional-dispatch-responder-communication/`) — same "add subscriber + UI section + tighten tests" shape; `messagesByIncident` Map in that phase is the direct precedent for `resourceRequestsByIncident` here.
