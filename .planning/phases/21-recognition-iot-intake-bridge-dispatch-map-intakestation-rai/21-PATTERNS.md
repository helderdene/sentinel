# Phase 21: Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail — Pattern Map

**Mapped:** 2026-04-22
**Files analyzed:** 27 (12 NEW, 9 MOD, 6 test NEW + MOD)
**Analogs found:** 25 / 27 (2 files have no direct analog — flagged below)

---

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|-------------------|------|-----------|----------------|---------------|
| `app/Services/FrasIncidentFactory.php` (NEW) | service | gate-then-write | `app/Http/Controllers/IoTWebhookController.php` + `app/Services/BarangayLookupService.php` | exact (factor-out) |
| `app/Events/RecognitionAlertReceived.php` (NEW) | broadcast event | event-driven | `app/Events/IncidentCreated.php` + `app/Events/CameraStatusChanged.php` | exact |
| `app/Http/Controllers/IoTWebhookController.php` (MOD) | controller | request-response | self (refactor to thin) | n/a — self-refactor |
| `app/Mqtt/Handlers/RecognitionHandler.php` (MOD) | handler | message-driven | self (append factory call) | n/a — self-extend |
| `app/Http/Controllers/IntakeStationController.php` (MOD) | controller | request-response (Inertia) | self (extend `show()` + `overridePriority()`) | n/a — self-extend |
| `routes/channels.php` (MOD) | config | auth-gate | `fras.cameras` block (lines 17–19) | exact |
| `config/fras.php` (MOD) | config | static | existing `cameras`/`enrollment` blocks | exact |
| `database/seeders/PersonOfInterestIncidentTypeSeeder.php` (NEW) | seeder | one-shot write | `database/seeders/IncidentTypeSeeder.php::seedTypes()` | role-match |
| `resources/js/composables/useFrasAlerts.ts` (NEW) | composable | Echo subscribe | `resources/js/composables/useEnrollmentProgress.ts` | exact |
| `resources/js/composables/useFrasRail.ts` (NEW) | composable | Echo subscribe + ring buffer | `resources/js/composables/useIntakeFeed.ts` | exact |
| `resources/js/composables/useDispatchMap.ts` (MOD) | composable | map state | self (extend camera layer paint + add `pulseCamera`) | n/a — self-extend |
| `resources/js/components/intake/ChannelFeed.vue` (MOD) | component | prop-driven list | self (add 6th row to `channelRows`) | n/a — self-extend |
| `resources/js/components/intake/ChBadge.vue` (MOD) | component | config map | self (extend `ChannelKey` + `channels` record) | n/a — self-extend |
| `resources/js/components/intake/icons/IntakeIconFras.vue` (NEW) | icon | static SVG | `resources/js/components/intake/icons/IntakeIconIot.vue` | exact |
| `resources/js/components/intake/FrasEventDetailModal.vue` (NEW) | component | modal (read-only) | existing Reka `Dialog` stack + v1.0 modal idiom | role-match |
| `resources/js/components/intake/FrasRailCard.vue` (NEW) | component | list item | `resources/js/components/intake/FeedCard.vue` (same dir) | role-match |
| `resources/js/components/fras/FrasSeverityBadge.vue` (NEW) | component | badge | `resources/js/components/intake/ChBadge.vue` (color-mix 15/40 idiom) | role-match |
| `resources/js/components/incidents/EscalateToP1Button.vue` (NEW) | component | form submit | `resources/js/components/intake/QueueRow.vue::handleOverride` | exact |
| `resources/js/pages/incidents/Show.vue` (MOD) | page | header extend | self (add conditional button to header action cluster) | n/a — self-extend |
| `tests/Feature/Fras/FrasIncidentFactoryTest.php` (NEW) | test | unit-ish feature | `tests/Feature/Intake/IoTWebhookTest.php` | role-match |
| `tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php` (NEW) | test | event + channel auth | `tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php` + `tests/Feature/Fras/BroadcastAuthorizationTest.php` | exact |
| `tests/Feature/Fras/EscalateToP1Test.php` (NEW) | test | route + audit | `tests/Feature/Intake/TriageIncidentTest.php` + `tests/Feature/Intake/IntakeGatesTest.php` | role-match |
| `tests/Feature/Fras/IntakeStationFrasRailTest.php` (NEW) | test | Inertia prop | `tests/Feature/Intake/IntakeStationTest.php` | exact |
| `tests/Feature/Mqtt/RecognitionHandlerTest.php` (MOD) | test | handler behavior | self (extend with factory-call expectation) | n/a — self-extend |
| `tests/Feature/Intake/IoTWebhookTest.php` (EXISTING, unchanged) | test | must pass unchanged | self | n/a — contract gate |

---

## Pattern Assignments

### `app/Services/FrasIncidentFactory.php` (NEW service)

**Analogs:**
1. `app/Http/Controllers/IoTWebhookController.php` — body lines 56–92 factored verbatim into `createFromSensor()`
2. `app/Services/BarangayLookupService.php` — service shape reference (constructor-injected, single-purpose, PHP 8 strict types)
3. `app/Mqtt/Handlers/AckHandler.php` lines 75–85 — Redis `Cache` dedup idiom (use `Cache::add` instead of `Cache::pull`)

**Imports pattern** (mirrors `IoTWebhookController.php` lines 3–15):
```php
use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Events\IncidentCreated;
use App\Events\RecognitionAlertReceived;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\Cache;
```

**Core write pattern** — factor from `IoTWebhookController.php` lines 56–92 (copy verbatim into `createFromSensor()`):
```php
$data = [
    'incident_type_id' => $incidentType->id,
    'priority' => IncidentPriority::from($mapping['priority']),
    'status' => IncidentStatus::Pending,
    'channel' => IncidentChannel::IoT,
    'location_text' => $validated['location_text'] ?? null,
    'notes' => "IoT Alert: {$sensorType} sensor {$validated['sensor_id']} ...",
    'raw_message' => json_encode($request->all()),
];
// ... Point + BarangayLookup ...
$incident = Incident::query()->create($data);
IncidentTimeline::query()->create([
    'incident_id' => $incident->id,
    'event_type' => 'incident_created',
    'event_data' => ['source' => 'iot_sensor', 'sensor_type' => $sensorType, ...],
]);
$incident->load('incidentType', 'barangay');
IncidentCreated::dispatch($incident);
```

**Dedup pattern** — clone `AckHandler.php` Cache idiom but use `Cache::add` (atomic add-if-not-exists):
```php
// D-08: Cache::add returns false if key already present — atomic, no race.
$key = "fras:incident-dedup:{$event->camera_id}:{$event->personnel_id}";
$ttl = (int) config('fras.recognition.dedup_window_seconds', 60);
if (! Cache::add($key, true, $ttl)) {
    return null;  // Dedup gate: within window
}
```

**Constructor injection shape** — mirror `IoTWebhookController.php` line 19–21:
```php
public function __construct(
    private BarangayLookupService $barangayLookup,
) {}
```

**Service class shape reference** — `app/Services/BarangayLookupService.php` (whole file, 30 lines): single public method, typed params, typed return, no state.

---

### `app/Events/RecognitionAlertReceived.php` (NEW event)

**Analog:** `app/Events/CameraStatusChanged.php` (46 lines, exact shape match — single constructor model + `PrivateChannel` + `broadcastWith`); `app/Events/IncidentCreated.php` (eager-loaded denorm pattern for `broadcastWith`)

**Full boilerplate from `CameraStatusChanged.php` lines 1–15** (copy verbatim, change class + channel name):
```php
<?php

namespace App\Events;

use App\Models\RecognitionEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RecognitionAlertReceived implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
```

**Channel pattern** — `CameraStatusChanged.php` lines 22–25 → use `fras.alerts`:
```php
public function broadcastOn(): array
{
    return [new PrivateChannel('fras.alerts')];
}
```

**Eager-loaded denorm payload pattern** — `IncidentCreated.php` lines 32–55: load nested relations in constructor or before dispatch, read via `?->` null-safe chain, normalize coordinates as `[lng, lat]` array. Apply for `camera.location`, `personnel.name`, `personnel.category`.

---

### `app/Http/Controllers/IoTWebhookController.php` (MOD — thin refactor)

**Self-analog:** current `__invoke` body (lines 26–98). Keep lines 26–54 (validate + mappings + incidentType lookup + 422 responses), delete lines 56–92 (factored to `FrasIncidentFactory::createFromSensor`), replace with factory call, keep 94–97 (JSON response).

**Post-refactor shape** (D-09):
```php
public function __construct(
    private FrasIncidentFactory $factory,
) {}

public function __invoke(Request $request): JsonResponse
{
    $validated = $request->validate([...]);  // keep lines 28–36

    $mappings = config('services.iot.sensor_mappings');
    // keep 422 on unknown sensor_type (lines 41–45)
    // keep 422 on missing incident_type (lines 50–54)

    $incident = $this->factory->createFromSensor($validated, $mapping, $incidentType);

    return response()->json([
        'incident_no' => $incident->incident_no,
        'incident_id' => $incident->id,
    ], 201);
}
```

**Constructor swap:** `BarangayLookupService` → `FrasIncidentFactory` (which owns the lookup now).

---

### `app/Mqtt/Handlers/RecognitionHandler.php` (MOD — append factory call)

**Self-analog:** current `handle()` method (lines 33–120). Insert factory call at line 119 END (after `persistImage` calls for face + scene). Pattern: fetch the fresh event, pass to factory.

**Append pattern (D-06, D-10):**
```php
// After line 119 (final persistImage call):
app(FrasIncidentFactory::class)->createFromRecognition($event);
```

**Inline handler contract** — Phase 19 D-01 established inline (no queue); factory returns null on gate failures so handler never needs to branch.

**Do NOT dispatch `IncidentCreated` from the handler** — factory owns it (D-10).

---

### `app/Http/Controllers/IntakeStationController.php` (MOD)

**Self-analog `show()` method lines 53–64** (prop-shape reference) — clone for `recentFrasEvents`:

Current `recentActivity` prop pattern:
```php
$recentActivity = IncidentTimeline::query()
    ->with('incident:id,incident_no,priority')
    ->whereDate('created_at', today())
    ->whereIn('event_type', [...])
    ->orderByDesc('created_at')
    ->limit(50)
    ->get()
    ->map(fn (IncidentTimeline $entry) => [...]);
```

**New `recentFrasEvents` follows the same idiom** (D-18) — selective `with()` columns, `->map()` into Inertia-safe array.

**`overridePriority()` method lines 203–232** — extend validation only (D-22):
```php
// Current lines 207–209:
$validated = $request->validate([
    'priority' => ['required', 'in:P1,P2,P3,P4'],
]);

// Post-Phase-21 (D-22):
$validated = $request->validate([
    'priority' => ['required', 'in:P1,P2,P3,P4'],
    'trigger' => ['sometimes', 'in:manual_override,fras_escalate_button'],
]);
```

Audit write at line 217–227 extends with `'trigger' => $validated['trigger'] ?? 'manual_override'` in `event_data`. Default preserves v1.0 audit shape.

---

### `routes/channels.php` (MOD)

**Analog:** lines 17–19 (`fras.cameras` block):
```php
Broadcast::channel('fras.cameras', function (User $user) use ($dispatchRoles): bool {
    return in_array($user->role, $dispatchRoles);
});
```

**Phase 21 `fras.alerts` follows same shape** (D-11):
```php
Broadcast::channel('fras.alerts', function (User $user) use ($dispatchRoles): bool {
    return in_array($user->role, $dispatchRoles);
});
```

`$dispatchRoles` (line 7) = `[Operator, Dispatcher, Supervisor, Admin]` — matches D-11 role set exactly.

---

### `config/fras.php` (MOD)

**Analog:** existing `cameras`, `enrollment`, `photo` sections (lines 25–39) — flat array with `env()` default pattern:
```php
'cameras' => [
    'degraded_gap_s' => (int) env('FRAS_CAMERA_DEGRADED_GAP_S', 30),
    'offline_gap_s' => (int) env('FRAS_CAMERA_OFFLINE_GAP_S', 90),
],
```

**Phase 21 `recognition` section (D-05):** copy idiom verbatim — `(float) env()`, `(int) env()`, nested `priority_map` array.

---

### `database/seeders/PersonOfInterestIncidentTypeSeeder.php` (NEW)

**Analog:** `database/seeders/IncidentTypeSeeder.php::seedTypes()` lines 52–72 — `IncidentType::updateOrCreate(['code' => ...], [...])` idempotent seed pattern.

**Extract pattern (D-02):**
```php
IncidentType::updateOrCreate(
    ['code' => 'person_of_interest'],
    [
        'incident_category_id' => $categoryMap[$type['category']] ?? null,
        'category' => 'Crime / Security',
        'name' => 'Person of Interest',
        'default_priority' => 'P2',
        'is_active' => true,
        'show_in_public_app' => false,
        'sort_order' => $sortOrder++,  // last
    ]
);
```

Register in `DatabaseSeeder` after `IncidentTypeSeeder` (category must exist first).

---

### `resources/js/composables/useFrasAlerts.ts` (NEW)

**Analog:** `resources/js/composables/useEnrollmentProgress.ts` (whole file, 62 lines) — same library (`@laravel/echo-vue`), same `useEcho<Payload>(channel, event, cb)` idiom.

**Copy imports + useEcho shape (lines 1–3, 32–35):**
```ts
import { useEcho } from '@laravel/echo-vue';
import type { Ref } from 'vue';
import { ref } from 'vue';

// ...
useEcho<RecognitionAlertPayload>(
    'fras.alerts',
    'RecognitionAlertReceived',
    (e) => { /* call pulseCamera(e.camera_id, e.severity) */ },
);
```

**Payload type** mirrors `EnrollmentProgressedPayload` shape (lines 16–22) — flat object, nullable fields typed `| null`.

**Per-camera timeout map** (D-15) — use `Map<string, number>` closure state, `clearTimeout` + `setTimeout` on each event. No analog for this specific pattern; invent from Mapbox docs + reduced-motion contract (UI-SPEC line 423).

---

### `resources/js/composables/useFrasRail.ts` (NEW)

**Analog:** `resources/js/composables/useIntakeFeed.ts` (178 lines) — ring-buffer + Echo subscribe + channelCounts computed (lines 41–59), `MAX_FEED_SIZE = 100` (line 14), prepend-and-pop idiom (lines 132–136).

**Ring-buffer prepend pattern (lines 128–136):**
```ts
target.value.unshift(newIncident);
if (target.value.length > MAX_FEED_SIZE) {
    target.value.pop();
}
```

**Phase 21 adapts:** `MAX_FRAS_FEED_SIZE = 50` (D-18 limit matches `recentActivity`). Subscribe to `fras.alerts` via `useEcho` (same as `useEnrollmentProgress.ts`). Return `{ frasEvents, channelCounts: { FRAS: count } }`.

---

### `resources/js/composables/useDispatchMap.ts` (MOD — export `pulseCamera`)

**Self-analog:**
1. Camera layers: `camera-halo` (lines 394–404) + `camera-body` (lines 407–422) — replace literal `'icon-size': 0.55` and `'circle-radius': 18` with `case` expressions per UI-SPEC section "Map pulse".
2. `setCameraData()` (lines 761–789) — already sets `id: c.id` on each feature (line 768), which is the exact requirement for `map.setFeatureState({source, id}, {...})`.
3. `CAMERA_STATUS_COLORS` (lines 47–57) — `match` expression idiom; Phase 21 nests this inside the severity `case` expression fallback branch.

**Export function shape** mirrors existing exports (line 1015 `setCameraData` export). Add `pulseCamera` to the returned public API object.

**Paint case expression reference (from UI-SPEC §Map pulse):**
```ts
'icon-size': [
    'case',
    ['boolean', ['feature-state', 'pulsing'], false],
    0.88,
    0.55
],
```

---

### `resources/js/components/intake/ChannelFeed.vue` (MOD)

**Self-analog:** `channelRows` array (lines 38–69). Append 6th entry after `WALKIN`:
```ts
{
    key: 'FRAS',
    label: 'FRAS',
    icon: IntakeIconFras,
    color: 'var(--t-ch-fras)',
},
```

**Render loop** (lines 120–157) uses `channelRows` + `channelCounts[row.key]` — new FRAS row auto-renders with no template changes. `barWidth()` helper (line 81) uses `totalChannelCount` sum — no change needed.

---

### `resources/js/components/intake/ChBadge.vue` (MOD)

**Self-analog:** `ChannelKey` type (line 2) + `channels` record (lines 40–54) — both extended with `'FRAS'`.

```ts
// line 2:
export type ChannelKey = 'SMS' | 'APP' | 'VOICE' | 'IOT' | 'WALKIN' | 'FRAS';

// in channels record (after WALKIN at line 53):
FRAS: {
    color: 'var(--t-ch-fras)',
    icon: IntakeIconFras,
    label: 'FRAS',
},
```

`channelDisplayMap` (lines 7–13) does NOT get a new entry — FRAS rail is driven by RecognitionEvent severity, not IncidentChannel. The map is for Incident → ChannelKey resolution only.

---

### `resources/js/components/intake/icons/IntakeIconFras.vue` (NEW)

**Analog:** `resources/js/components/intake/icons/IntakeIconIot.vue` (32 lines, whole file).

**Copy verbatim skeleton (lines 1–12 + 14–23):**
```vue
<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: number;
        color?: string;
    }>(),
    { size: 16, color: 'currentColor' },
);
</script>

<template>
    <svg :width="size" :height="size" :viewBox="`0 0 16 16`" fill="none"
         :stroke="color" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
        <!-- face/recognition motif paths (planner draws) -->
    </svg>
</template>
```

16×16 viewBox, stroke-width 1.3 — matches all 15 existing `IntakeIcon*.vue` files. Glyph motif: face with targeting reticle / face outline + scan lines / face + corner brackets. Planner picks.

---

### `resources/js/components/intake/FrasEventDetailModal.vue` (NEW)

**Analogs:**
1. Reka Dialog primitives: `resources/js/components/ui/dialog/` (Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose) — already in the project
2. Existing modal idiom: search `components/intake/` for other Dialog usage; v1.0 override-priority modal + Phase 20 decommission dialog both use `DialogContent class="max-w-2xl p-6"` per UI-SPEC

**Reference shell (UI-SPEC §FrasEventDetailModal):**
```vue
<Dialog v-model:open="open">
    <DialogContent class="max-w-2xl p-6 space-y-6">
        <DialogHeader>
            <DialogTitle>Recognition Event Details</DialogTitle>
            <DialogDescription>Read-only view of a FRAS recognition event...</DialogDescription>
        </DialogHeader>
        <!-- header strip / Why No Incident / Event Details / Face / Scene -->
        <DialogFooter>
            <DialogClose as-child><Button variant="outline">Close</Button></DialogClose>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

**Read-only by contract (D-19).** No `useForm`, no submit handler. Event payload consumed from Inertia prop (no backend fetch).

---

### `resources/js/components/intake/FrasRailCard.vue` (NEW)

**Analog:** `resources/js/components/intake/FeedCard.vue` (sibling in same dir — existing rail card idiom). UI-SPEC §FrasRailCard fully specifies the layout. Card height `h-16` per UI-SPEC spacing contract line 112.

**Structure** (UI-SPEC lines 461–470):
```
<article role="button" tabindex="0"
         class="flex items-stretch gap-3 rounded-[var(--radius)] border border-border bg-card
                p-3 shadow-sm cursor-pointer hover:bg-accent transition-colors h-16">
    <div class="w-0.5 bg-t-ch-fras rounded-full self-stretch"/>  <!-- accent stripe -->
    <div class="size-10 rounded-[var(--radius)] overflow-hidden bg-muted flex-shrink-0">
        <!-- face thumbnail or placeholder -->
    </div>
    <div class="flex-1 min-w-0 flex flex-col justify-between gap-1">
        <!-- top: personnel name + severity badge -->
        <!-- bottom: category chip + camera label + CREATED INCIDENT pill + timestamp -->
    </div>
</article>
```

Click handler emits `open-modal` or `router.visit(incidents.show(incident_id).url)` — see UI-SPEC §click-behavior.

---

### `resources/js/components/fras/FrasSeverityBadge.vue` (NEW)

**Analog:** `resources/js/components/intake/ChBadge.vue` lines 60–79 — `color-mix 15% bg / 40% border` pill idiom.

**Copy pattern:**
```vue
<span class="inline-flex items-center gap-1 rounded-full font-mono font-bold whitespace-nowrap px-2 py-[2px] text-[10px] uppercase"
      :style="{
          backgroundColor: `color-mix(in srgb, ${color} 15%, transparent)`,
          borderWidth: '1px', borderStyle: 'solid',
          borderColor: `color-mix(in srgb, ${color} 40%, transparent)`,
          color: color,
      }">
    ● {{ label }}
</span>
```

Severity → token resolution (UI-SPEC §Color table): `critical → --t-p1`, `warning → --t-unit-onscene`, `info → --t-unit-offline`. Leading `●` dot carries meaning for color-blind users (Warning amber fails AA at 15% tint).

---

### `resources/js/components/incidents/EscalateToP1Button.vue` (NEW)

**Analog:** `resources/js/components/intake/QueueRow.vue` lines 5–8 + 57–70 — existing Wayfinder-action + `router.post` idiom for `overridePriority`.

**Copy Wayfinder import pattern (line 5–8):**
```ts
import {
    overridePriority,
} from '@/actions/App/Http/Controllers/IntakeStationController';
```

**Copy submit handler pattern (lines 57–70) + add `trigger` field (D-22):**
```ts
function handleEscalate(): void {
    router.post(
        overridePriority(props.incident.id).url,
        { priority: 'P1', trigger: 'fras_escalate_button' },
        {
            preserveScroll: true,
            onSuccess: () => { /* toast */ },
            onError: (errors) => { /* toast */ },
        },
    );
}
```

**Button variant:** `<Button variant="destructive">` with `lucide:TrendingUp` prefix icon + tooltip (UI-SPEC lines 524–528). Conditional render per D-21:
```ts
const showEscalateButton = computed(() =>
    incident.value.timeline?.[0]?.event_data?.source === 'fras_recognition'
    && incident.value.priority !== 'P1'
);
```

---

### `resources/js/pages/incidents/Show.vue` (MOD)

**Self-analog:** header action cluster at lines 83–115 — existing `Badge` + `Button` stack inside `<div class="flex items-center gap-3">`. Add `<EscalateToP1Button v-if="showEscalateButton" ... />` between line 89 (priority badge) and line 95 (`ml-auto` spacer) — placement per D-21 / UI-SPEC "top-right, LEFT of status/priority badges."

**Note on analog accuracy:** UI-SPEC says "to the LEFT of the existing status/priority badge stack so it reads first." Actual current markup order (lines 87–113) is: priority badge → status badge → `ml-auto` spacer → Report button. Escalate button slots after `<h1>` + before both badges (or stays in `ml-auto` cluster — planner confirms during implementation).

---

### `tests/Feature/Fras/FrasIncidentFactoryTest.php` (NEW)

**Analog:** `tests/Feature/Intake/IoTWebhookTest.php` (192 lines, Pest feature test shape). Use `Event::fake([IncidentCreated::class, RecognitionAlertReceived::class])`, `RefreshDatabase`, factory helpers.

**Test-shape pattern from IoTWebhookTest.php lines 10–12:**
```php
beforeEach(function () {
    Event::fake([IncidentCreated::class]);
});
```

**Gate-order test coverage** (D-07 five gates) — one `it(...)` per gate boundary:
- `it('returns null for Info severity')`
- `it('returns null below confidence threshold')`
- `it('returns null for allow-category personnel')`
- `it('returns null for unknown/null personnel')`
- `it('returns null when dedup key already set')`
- `it('creates P2 Incident for Critical × block-list')`
- `it('creates P1 Incident for Critical × lost_child')`
- `it('sets recognition_events.incident_id on success')`
- `it('dispatches IncidentCreated and RecognitionAlertReceived on success')`
- `it('broadcasts RecognitionAlertReceived for Warning severity with incident_id null')`

**Payload assertion pattern** from IoTWebhookTest.php lines 170–176 — direct DB read + `expect()` on column values.

---

### `tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php` (NEW)

**Analogs:**
1. `tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php` (67 lines) — payload snapshot pattern, direct `new Event(...)->broadcastWith()` call
2. `tests/Feature/Fras/BroadcastAuthorizationTest.php` (58 lines) — channel auth matrix pattern via `/broadcasting/auth` POST

**Payload test pattern (IncidentCreatedSnapshotTest.php lines 20–66):**
```php
it('RecognitionAlertReceived payload matches golden fixture', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21T00:00:00Z'));
    // ... factory setup ...
    $payload = (new RecognitionAlertReceived($event))->broadcastWith();
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    // ... fixture compare ...
});
```

**Channel auth matrix pattern (BroadcastAuthorizationTest.php lines 17–27 + 29–40):**
```php
function authAttempt(UserRole $role, string $channelName): TestResponse {
    $user = User::factory()->create(['role' => $role]);
    return test()->actingAs($user)->post('/broadcasting/auth', [
        'channel_name' => $channelName,
        'socket_id' => '1234.5678',
    ]);
}

describe('fras.alerts channel', function () {
    foreach ([Operator, Dispatcher, Supervisor, Admin] as $allowedRole) {
        it("authorizes {$allowedRole->value}...", function () use ($allowedRole) {
            $response = authAttempt($allowedRole, 'private-fras.alerts');
            expect($response->getStatusCode())->toBeIn([200, 201]);
        });
    }
    it('denies responder', function () {
        expect(authAttempt(Responder, 'private-fras.alerts')->getStatusCode())->toBe(403);
    });
});
```

Note: `BroadcastAuthorizationTest.php` line 7 declares `pest()->group('fras')` — mirror for consistency.

---

### `tests/Feature/Fras/EscalateToP1Test.php` (NEW)

**Analogs:**
1. `tests/Feature/Intake/TriageIncidentTest.php` (187 lines) — route POST test shape, `actingAs`, `assertDatabaseHas` for audit row
2. `tests/Feature/Intake/IntakeGatesTest.php` lines 41–51 — `override-priority` gate matrix pattern

**Route-POST pattern (TriageIncidentTest.php lines 17–34):**
```php
it('escalates FRAS-originated incident to P1 with fras_escalate_button trigger', function () {
    $supervisor = User::factory()->supervisor()->create();
    $incident = Incident::factory()->create(['priority' => IncidentPriority::P2]);
    // set up FRAS-originated timeline entry on $incident

    $this->actingAs($supervisor)
        ->post(route('intake.override-priority', $incident), [
            'priority' => 'P1',
            'trigger' => 'fras_escalate_button',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('incident_timeline', [
        'incident_id' => $incident->id,
        'event_type' => 'priority_override',
    ]);
    // also assert event_data.trigger === 'fras_escalate_button'
});
```

**Gate-reuse pattern (IntakeGatesTest.php lines 41–51)** — reuse existing `override-priority` Gate; no new test, verify via matrix that operator/dispatcher/responder get 403.

---

### `tests/Feature/Fras/IntakeStationFrasRailTest.php` (NEW)

**Analog:** `tests/Feature/Intake/IntakeStationTest.php` (72 lines, whole file). Shape verbatim — role matrix + prop-presence assertions.

**Copy pattern (lines 58–72):**
```php
it('receives recentFrasEvents prop', function () {
    $operator = User::factory()->operator()->create();
    // seed a RecognitionEvent

    $this->actingAs($operator)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intake/IntakeStation')
            ->has('recentFrasEvents')
            ->has('recentFrasEvents.0.event_id')
            ->where('recentFrasEvents.0.severity', 'critical')
        );
});
```

---

### `tests/Feature/Mqtt/RecognitionHandlerTest.php` (MOD)

**Self-analog:** existing test structure (first 80 lines reviewed). Extend with factory-call expectations — e.g., after handler runs on a Critical block-list payload, assert `Incident::count()` increased by 1 and `RecognitionAlertReceived` was dispatched.

**Insertion point:** after existing idempotency + unknown-camera tests — add new `describe('factory integration', function () { ... })` block.

---

## Shared Patterns

### Broadcast event shape
**Source:** `app/Events/CameraStatusChanged.php` (entire 46-line file)
**Apply to:** `app/Events/RecognitionAlertReceived.php`
- `final class ... implements ShouldBroadcast, ShouldDispatchAfterCommit`
- `use Dispatchable, InteractsWithSockets, SerializesModels`
- Constructor: single public property (Model instance)
- `broadcastOn()` returns `[new PrivateChannel('fras.alerts')]`
- `broadcastWith()` returns flat denorm array with nullable fields via `?->`

### Echo-subscribe composable shape
**Source:** `resources/js/composables/useEnrollmentProgress.ts` (entire 62-line file)
**Apply to:** `useFrasAlerts.ts`, `useFrasRail.ts`
- `import { useEcho } from '@laravel/echo-vue'`
- Typed payload interface
- `useEcho<Payload>(channelName, eventClassBasename, callback)`
- Mutate reactive `ref<Map>` or `ref<[]>` inside callback
- Return reactive state (not callback registration)

### Redis cache idiom
**Source:** `app/Mqtt/Handlers/AckHandler.php` lines 75–85
**Apply to:** `FrasIncidentFactory::createFromRecognition` dedup gate
- `use Illuminate\Support\Facades\Cache;`
- `Cache::add($key, true, $ttl)` — atomic add-if-not-exists (returns false if present)
- Key shape: `{namespace}:{id1}:{id2}` (e.g. `fras:incident-dedup:{camera_id}:{personnel_id}`)
- TTL from `config('fras.recognition.dedup_window_seconds', 60)`

### Private channel auth
**Source:** `routes/channels.php` lines 9–19 (`dispatch.incidents`, `dispatch.units`, `fras.cameras`)
**Apply to:** `fras.alerts` (new entry after line 19)
- `Broadcast::channel('name', function (User $user) use ($dispatchRoles): bool { ... })`
- `$dispatchRoles` variable (line 7) covers Operator/Dispatcher/Supervisor/Admin
- Responder explicitly excluded (matches D-11)

### Thin controller → service class
**Source:** pre-refactor `IoTWebhookController.php` demonstrates the before-state; `AckHandler.php` constructor-injecting `CameraEnrollmentService` (line 34) demonstrates the after-state
**Apply to:** all 3 controller entry points that touch `FrasIncidentFactory` (`IoTWebhookController`, `RecognitionHandler`, `IntakeStationController` — latter only via `overridePriority`)
- Constructor injection of service
- Validation + 422 responses stay in controller
- Write path delegated to service

### Wayfinder-action + router.post
**Source:** `resources/js/components/intake/QueueRow.vue` lines 5–8 + 57–70
**Apply to:** `EscalateToP1Button.vue`
- Import action from `@/actions/App/Http/Controllers/{Controller}`
- Call `action(id).url` to get URL
- `router.post(url, data, { preserveScroll: true, onSuccess, onError })`

### Inertia prop-seed + Echo-hydrate
**Source:** `IntakeStationController::show()` lines 53–64 (`recentActivity` prop) + `useIntakeFeed.ts` lines 20–22 (ref-seed from initial prop)
**Apply to:** IntakeStation FRAS rail (`recentFrasEvents` prop + `useFrasRail` composable)
- Server: `->with('rel:id,col')` selective columns, `->limit(50)`, `->map(fn => [...])`
- Client: `ref([...initial])` then `useEcho` callback prepends

### Pest Event::fake shape
**Source:** `tests/Feature/Intake/IoTWebhookTest.php` lines 10–12
**Apply to:** all new Phase 21 feature tests
- `beforeEach(function () { Event::fake([IncidentCreated::class, ...]); });`

### Idempotent seed
**Source:** `database/seeders/IncidentTypeSeeder.php` lines 58–71
**Apply to:** `PersonOfInterestIncidentTypeSeeder.php`
- `IncidentType::updateOrCreate(['code' => X], [attrs])`

---

## No Analog Found

| File | Role | Data Flow | Reason + Planner Guidance |
|------|------|-----------|---------------------------|
| `resources/js/composables/useFrasAlerts.ts` per-camera pulse timeout map | composable | map state | No existing composable has a per-entity `Map<string, timeoutHandle>` with clearTimeout+setTimeout reset. Invent from scratch per D-15 + UI-SPEC reduced-motion contract (line 423). |
| Mapbox `feature-state` paint `case` expression for pulse | frontend config | GPU state | `useDispatchMap.ts` has `match` expressions but no `feature-state` usage yet. Reference UI-SPEC §Map pulse code block for the exact expression shape. |

---

## Metadata

**Analog search scope:**
- `app/Events/` (12 files)
- `app/Http/Controllers/` (selective)
- `app/Mqtt/Handlers/` (all 4 files)
- `app/Services/` (BarangayLookupService)
- `resources/js/composables/` (all 26 files listed; deep-read: useEnrollmentProgress, useIntakeFeed, useDispatchMap)
- `resources/js/components/intake/` (all 18 files listed; deep-read: ChannelFeed, ChBadge, QueueRow, IntakeIconIot)
- `resources/js/pages/incidents/Show.vue`
- `routes/channels.php`, `routes/web.php` (selective)
- `config/fras.php`
- `database/seeders/` (IncidentTypeSeeder, FrasPlaceholderSeeder)
- `tests/Feature/Fras/` + `tests/Feature/Intake/` + `tests/Feature/Broadcasting/` + `tests/Feature/Mqtt/`

**Files scanned:** ~60 files across backend + frontend + tests

**Pattern extraction date:** 2026-04-22

---

*Phase: 21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai*
*Pattern map written: 2026-04-22*
