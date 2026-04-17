---
phase: 15-close-rspdr-real-time-dispatch-visibility
reviewed: 2026-04-17T00:00:00Z
depth: standard
files_reviewed: 9
files_reviewed_list:
  - app/Http/Controllers/StateSyncController.php
  - resources/js/components/dispatch/IncidentDetailPanel.vue
  - resources/js/composables/useAlertSystem.ts
  - resources/js/composables/useDispatchFeed.ts
  - resources/js/pages/dispatch/Console.vue
  - resources/js/types/incident.ts
  - tests/Feature/RealTime/StateSyncTest.php
  - tests/Feature/Responder/ChecklistTest.php
  - tests/Feature/Responder/ResourceRequestTest.php
findings:
  critical: 0
  warning: 3
  info: 5
  total: 8
status: warnings_fixed
warnings_fixed_at: 2026-04-17
warnings_fixed_commits:
  - WR-01: 6fdd546
  - WR-02: e32b02a
  - WR-03: c1f6c7e
---

# Phase 15: Code Review Report

**Reviewed:** 2026-04-17
**Depth:** standard
**Files Reviewed:** 9
**Status:** issues_found

## Summary

Phase 15 wires the existing `ChecklistUpdated` and `ResourceRequested` broadcast events into the dispatch console and adds a `StateSyncController` endpoint that hydrates resource-request history on reconnect. Overall the implementation is clean and adheres to the stated focus areas:

- Reactive `Map` updates consistently use the `new Map(old)` replacement pattern (useDispatchFeed.ts lines 102–104, 119–122, 258–270, 352–364, 387–390).
- Memory cleanup on status exit removes entries from `unreadByIncident`, `messagesByIncident`, AND `resourceRequestsByIncident` (lines 258–270) and `onStateSync` reinitialises all three maps (lines 504–506).
- All user-submitted text (`req.notes`, `entry.notes`, `incident.notes`, `req.requested_by`, etc.) is rendered via `{{ }}` auto-escape in IncidentDetailPanel.vue — no `v-html` on user content.
- Design tokens (`t-accent`, `t-surface`, `t-border`, `t-text-*`) are used throughout; no hardcoded `neutral-*`/`zinc-*`/`slate-*`/`gray-*` classes.
- `import type` is used consistently for type-only imports.
- Pest tests assert `broadcastOn()` returns a `PrivateChannel` with name `private-dispatch.incidents` and that `broadcastWith()` contains the expected payload keys.
- `channelCounts` query remains intentionally PENDING-only; `incidents` query is widened to the dispatch-active set; timeline eager-load is bounded by `event_type = 'resource_requested'`.

No critical findings. Issues below are correctness/maintainability items that should be addressed before merge.

## Warnings

### WR-01: State sync drops live resource-requests for incidents present in fresh payload

**File:** `resources/js/composables/useDispatchFeed.ts:504-506`
**Issue:** On reconnect, `onStateSync` unconditionally resets `resourceRequestsByIncident.value = new Map()`. The server payload carries `resource_requests` on each `DispatchIncident`, but `freshIncidents` (lines 438–483) never copies `inc.resource_requests` through to the local incident shape — the field is silently dropped. The Console.vue `selectedIncidentResourceRequests` computed (lines 194–220) does read `selected?.resource_requests`, so it will be `undefined` after a sync until a fresh `ResourceRequested` event fires. This causes a visible regression: resource requests that were visible pre-disconnect vanish post-reconnect on any incident that has no new request during the outage.

Additionally, resetting `messagesByIncident`/`unreadByIncident` on every state sync is correct for server-authoritative data, but discards any unflushed local messages (including the current user's own optimistically-added message from `addLocalMessage` if a reconnect happens mid-send).

**Fix:**
```ts
const freshIncidents = data.incidents.map(
    (inc): DispatchIncident => ({
        // ...existing fields...
        resource_requests: inc.resource_requests ?? [],
        assigned_units: [],
    }),
);
```

Also update `StateSyncResponse.incidents` in `types/incident.ts` (currently `IncidentForQueue[]`) to a shape that carries `resource_requests`, or use a dedicated `StateSyncIncident` type. The current typing does not match the controller's JSON (which embeds `resource_requests` and drops `timeline`).

### WR-02: Resource-request dedup key is not unique across requests

**File:** `resources/js/pages/dispatch/Console.vue:207-217`
**Issue:** `selectedIncidentResourceRequests` dedupes by `req.timestamp` alone. Two requests dispatched within the same wall-clock second (easy on a busy P1 scene where multiple assets are needed at once) will produce identical ISO-8601 timestamps and the second will be silently dropped from the UI. The backing store (`timeline` table) will retain both, so the dispatcher sees "1 request" in the UI but "2 entries" in any audit log — a real correctness gap.

**Fix:** Dedup by the tuple `(timestamp, resource_type, requested_by)` or include a stable identifier in `ResourceRequestedPayload` (e.g., the `incident_timeline.id`). Preferred — propagate the timeline row id:

```ts
// types/incident.ts
export interface ResourceRequest {
    id?: number; // timeline row id
    resource_type: string;
    // ...
}

// Console.vue
const deduped = combined.filter((req) => {
    const key = req.id
        ? `id:${req.id}`
        : `${req.timestamp}|${req.resource_type}|${req.requested_by}`;

    if (seen.has(key)) {
        return false;
    }
    seen.add(key);

    return true;
});
```

### WR-03: Ticker priority mislabels ResourceRequested and MutualAid as P1

**File:** `resources/js/composables/useDispatchFeed.ts:310, 343`
**Issue:** Both `MutualAidRequested` and `ResourceRequested` handlers hardcode `priority: 'P1'` on the ticker event. If an actual P3/P4 incident raises a resource request, it will appear in the ticker styled as a P1 alert (with the P1 colour/emphasis downstream components apply). This is misleading to dispatchers who rely on the ticker's priority colouring for at-a-glance triage. `ResourceRequestedPayload` does not currently include the incident's priority, so it cannot be derived from the event alone.

**Fix:** Look up the incident's real priority from `localIncidents.value`:

```ts
const inc = localIncidents.value.find((i) => i.id === e.incident_id);

addTickerEvent({
    incident_no: e.incident_no,
    priority: inc?.priority ?? 'P3',
    // ...
});
```

For `MutualAidRequested`, either add `priority` to `MutualAidPayload` or derive it similarly via `incident_no` lookup. Add `priority` to `ResourceRequestedPayload` on the backend if lookup-by-id is not viable (e.g., the incident may not yet be in `localIncidents` if the event lands during initial hydration).

## Info

### IN-01: `IncidentCreated` event is faked but never asserted in ChecklistTest/ResourceRequestTest

**File:** `tests/Feature/Responder/ChecklistTest.php:12-17`, `tests/Feature/Responder/ResourceRequestTest.php:12-17`
**Issue:** Both suites call `Event::fake([IncidentCreated::class, ...])` but no test asserts anything about `IncidentCreated`. Faking it prevents unintended side-effects during factory creation, but the intent is non-obvious to readers. If the intent is "silence the broadcast during factory setup," a comment clarifies. If the intent is "this domain should not dispatch IncidentCreated," a `Event::assertNotDispatched(IncidentCreated::class)` assertion makes that contract explicit.

**Fix:** Either add `Event::assertNotDispatched(IncidentCreated::class);` at the end of relevant tests, or add a PHPDoc comment on `beforeEach` explaining the fake is there to suppress factory-triggered broadcasts.

### IN-02: `StateSyncController` has no test coverage for dispatch-visible statuses beyond Pending/Resolved/OnScene

**File:** `tests/Feature/RealTime/StateSyncTest.php`
**Issue:** The `$dispatchVisibleStatuses` array in the controller covers Pending, Triaged, Dispatched, Acknowledged, EnRoute, OnScene, and Resolving. Existing tests only exercise Pending (priority/FIFO, channel counts) and OnScene (resource-requests). There is no regression test asserting that Triaged/Dispatched/Acknowledged/EnRoute/Resolving incidents are included and that Resolved is excluded via `whereIn`. The "excludes resolved incidents" test (line 81) passes even if someone accidentally narrows the status filter to Pending-only.

**Fix:** Add a test that creates one incident per dispatch-visible status and asserts the count matches:
```php
it('includes all dispatch-visible statuses', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    foreach (['Pending', 'Triaged', 'Dispatched', 'Acknowledged', 'EnRoute', 'OnScene', 'Resolving'] as $s) {
        Incident::factory()->for($dispatcher, 'createdBy')->create([
            'status' => IncidentStatus::from($s),
        ]);
    }
    Incident::factory()->for($dispatcher, 'createdBy')->create(['status' => IncidentStatus::Resolved]);

    $response = $this->actingAs($dispatcher)->getJson(route('state-sync'))->assertSuccessful();
    expect($response->json('incidents'))->toHaveCount(7);
});
```

### IN-03: `StateSyncController` `resource_requests` mapping uses null-coalesce on non-null schema columns

**File:** `app/Http/Controllers/StateSyncController.php:41-44`
**Issue:** `event_data['type'] ?? null` and `event_data['label'] ?? null` tolerate missing keys, but the responder controller (per `ResourceRequestTest.php:49-57`) always dispatches with `type`, `label`, and `notes` set. If `event_data` is malformed (e.g., a manually-created timeline row), the UI will display `null` as a literal in the chip. Consider validating at write-time or filtering out entries where `resource_type` is null so the dispatcher never sees a "null" chip:

```php
->filter(fn ($t) => ! empty($t->event_data['type']))
->map(fn ($t) => [...])
```

### IN-04: `useAlertSystem` leaks `audioContext` across composable calls and never closes it

**File:** `resources/js/composables/useAlertSystem.ts:27-46`
**Issue:** `audioContext` is a module-level singleton that is created on first `ensureAudioContext()` call and never closed. This is fine for a long-lived SPA (dispatch console), but the `unlock` listeners (lines 41–42) stay bound until the once-fire. If the user interacts before the context is created, the branch `!audioUnlocked` ensures idempotence. However, the current code will silently no-op if the context enters `suspended` state after being `running` (e.g., Chrome autoplay policy on tab backgrounding) — `ctx.state !== 'running'` guards against playback but there is no attempt to resume. This is out of v1 scope for correctness but worth a note.

**Fix (optional):** If backgrounding is a known dispatcher workflow, call `ctx.resume()` defensively before each playback and handle the returned promise's rejection.

### IN-05: Checklist-progress gating includes `RESOLVED` but UI never shows this section post-resolution

**File:** `resources/js/components/dispatch/IncidentDetailPanel.vue:55`
**Issue:** `showChecklistProgress` includes `'RESOLVED'` in the gated list, but `useDispatchFeed` removes incidents with `new_status === 'RESOLVED'` from `localIncidents` (line 253–256), so the detail panel is never rendered for RESOLVED incidents in practice. The `'RESOLVED'` entry is dead code in the current flow — harmless, but misleading to a reader trying to understand state machine behaviour.

**Fix:** Remove `'RESOLVED'` from the gated array unless there is a planned workflow for viewing resolved incidents in the dispatch panel (in which case, document it).

---

_Reviewed: 2026-04-17_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
