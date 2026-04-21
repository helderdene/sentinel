# Phase 19: MQTT Pipeline + Listener Infrastructure - Pattern Map

**Mapped:** 2026-04-21
**Files analyzed:** 26 (18 NEW, 8 MOD)
**Analogs found:** 24 / 26 (2 entirely new conventions documented from FRAS + Laravel idiom)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|-------------------|------|-----------|----------------|---------------|
| `app/Console/Commands/FrasMqttListenCommand.php` | console-command | long-running / streaming | `/Users/helderdene/fras/app/Console/Commands/FrasMqttListenCommand.php` (FRAS verbatim) + `app/Jobs/CheckAckTimeout.php` (IRMS class shape) | FRAS-verbatim port; first `app/Console/Commands/` file in IRMS |
| `app/Console/Commands/FrasMqttListenerWatchdogCommand.php` | console-command | scheduled / pub-sub | `app/Jobs/CheckAckTimeout.php` (class shape + DB query + dispatch) + `routes/console.php` (Schedule facade) | role-match (no existing Artisan command); Laravel idiom |
| `app/Mqtt/Contracts/MqttHandler.php` | interface | request-response | `app/Contracts/ProximityServiceInterface.php` | role-match (interface convention) |
| `app/Mqtt/TopicRouter.php` | service / router | event-driven | `/Users/helderdene/fras/app/Mqtt/TopicRouter.php` (verbatim) + `app/Services/StubMapboxDirectionsService.php` (IRMS class conventions) | FRAS-verbatim port |
| `app/Mqtt/Handlers/RecognitionHandler.php` | handler | event-driven (write-through) | `/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php` + `app/Jobs/CheckAckTimeout.php` | FRAS-verbatim port |
| `app/Mqtt/Handlers/AckHandler.php` | handler | event-driven (log-only scaffold) | FRAS `AckHandler.php` | FRAS-verbatim shape |
| `app/Mqtt/Handlers/HeartbeatHandler.php` | handler | event-driven (DB update) | FRAS `HeartbeatHandler.php` | FRAS-verbatim shape |
| `app/Mqtt/Handlers/OnlineOfflineHandler.php` | handler | event-driven (enum toggle) | FRAS `OnlineOfflineHandler.php` | FRAS-verbatim shape minus `EnrollPersonnelBatch` dispatch |
| `app/Events/MqttListenerHealthChanged.php` | broadcast-event | pub-sub (Reverb) | `app/Events/IncidentCreated.php` | exact |
| `config/fras.php` | config | static | *(NEW)* `config/logging.php` (Laravel return-array convention) | role-match (IRMS config convention) |
| `config/mqtt-client.php` | config | static | `/Users/helderdene/fras/config/mqtt-client.php` | FRAS-verbatim port |
| `config/horizon.php` (MOD) | config | static | same file — `environments.production.supervisor-1` block | exact (copy block, rename) |
| `config/logging.php` (MOD) | config | static | same file — `channels.daily` block | exact (copy block shape) |
| `config/filesystems.php` (MOD) | config | static | same file — `disks.local` block | exact (private disk shape) |
| `composer.json` (MOD) | config | static | same file — `scripts.dev` concurrently line | exact (extend one-liner) |
| `routes/console.php` (MOD) | routing | scheduled | same file — existing `Schedule::job(...)` line | role-match (`Schedule::command` vs `Schedule::job`) |
| `app/Http/Controllers/DispatchConsoleController.php` (MOD) | controller | request-response (Inertia) | same file — `show()` method + `Inertia::render` array | exact (add one more array key) |
| `resources/js/pages/dispatch/Console.vue` (MOD) | page | Inertia SSR prop | same file — `defineProps` / `usePage` pattern | exact (add one prop) |
| `resources/js/components/fras/MqttListenerHealthBanner.vue` | component | presentational | `resources/js/components/ConnectionBanner.vue` | exact (same banner role + Transition usage) |
| `resources/js/composables/useDispatchFeed.ts` (MOD) | composable | pub-sub subscriber | same file — `useEcho<T>('dispatch.incidents', 'EventName', cb)` blocks | exact (add one more `useEcho` block) |
| `resources/js/types/mqtt.ts` | type-defs | static | `resources/js/types/dispatch.ts` | role-match |
| `tests/Feature/Mqtt/TopicRouterTest.php` | test | request-response | `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` | role-match (Pest + factory idiom) |
| `tests/Feature/Mqtt/RecognitionHandlerTest.php` | test | event-driven | `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` | role-match |
| `tests/Feature/Mqtt/HeartbeatHandlerTest.php` | test | event-driven | `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` | role-match |
| `tests/Feature/Mqtt/AckHandlerTest.php` | test | event-driven | same | role-match |
| `tests/Feature/Mqtt/OnlineOfflineHandlerTest.php` | test | event-driven | same | role-match |
| `tests/Feature/Mqtt/MqttListenerWatchdogTest.php` | test | pub-sub (Event::fake) | `tests/Feature/Broadcasting/IncidentCreatedSnapshotTest.php` (broadcast assertion pattern — not read, referenced by filename) + `RecognitionEventIdempotencyTest.php` | role-match |
| `docs/operations/irms-mqtt.md` | docs | static | `docs/operations/laravel-13-upgrade.md` §8 (referenced in CONTEXT) | role-match |

## Pattern Assignments

### `app/Console/Commands/FrasMqttListenCommand.php` (console-command, long-running)

**Analog:** `/Users/helderdene/fras/app/Console/Commands/FrasMqttListenCommand.php` (FRAS verbatim — port) + IRMS `app/Jobs/CheckAckTimeout.php` for constructor-promotion / `use` ordering conventions.

**FRAS signature + subscribe-loop pattern** (to port verbatim, renaming signature to `irms:mqtt-listen` and adding `--max-time=3600`):

```php
<?php

namespace App\Console\Commands;

use App\Mqtt\TopicRouter;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;

class FrasMqttListenCommand extends Command
{
    protected $signature = 'irms:mqtt-listen {--max-time=3600}';

    protected $description = 'Subscribe to camera MQTT topics and process messages';

    /** Execute the console command. */
    public function handle(TopicRouter $router): int
    {
        $mqtt = MQTT::connection();
        $prefix = config('fras.mqtt.topic_prefix');

        $topics = [
            $prefix.'/+/Rec',
            $prefix.'/+/Ack',
            $prefix.'/basic',
            $prefix.'/heartbeat',
        ];

        foreach ($topics as $topic) {
            $mqtt->subscribe($topic, fn (string $topic, string $message) => $router->dispatch($topic, $message), 0);
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $mqtt->interrupt());
        pcntl_signal(SIGINT, fn () => $mqtt->interrupt());

        $mqtt->loop(true);
        $mqtt->disconnect();

        return self::SUCCESS;
    }
}
```

**IRMS-native conventions to apply over FRAS shape (from `CheckAckTimeout.php` lines 1-46):**
- `<?php` + `namespace` + alphabetically-ordered `use` block (Pint enforces).
- Explicit `int` return type on `handle()`.
- `--max-time` read via `$this->option('max-time')` — drives `$mqtt->loop(true, true, (int) $this->option('max-time'))` OR external loop with deadline check.
- Logging via `Log::channel('mqtt')->info(...)` (D-17); replace FRAS's `$this->info(...)` with channel-scoped logs.

---

### `app/Console/Commands/FrasMqttListenerWatchdogCommand.php` (console-command, scheduled)

**Analog:** `app/Jobs/CheckAckTimeout.php` (class shape + DB-query + event dispatch) — closest existing IRMS pattern for "read state, compute transition, dispatch event."

**Class shape to mirror** (from `app/Jobs/CheckAckTimeout.php` lines 1-46):

```php
<?php

namespace App\Jobs;  // → Commands will be: namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\User;
use App\Notifications\AckTimeoutNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CheckAckTimeout implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $incidentId,
        public string $unitId,
        public int $userId,
    ) {}

    public function handle(): void
    {
        $stillUnacknowledged = DB::table('incident_unit')
            ->where('incident_id', $this->incidentId)
            // ... query state
            ->exists();

        if (! $stillUnacknowledged) {
            return;
        }

        // ... read more state, dispatch notification
        $user->notify(new AckTimeoutNotification($incident, $this->unitId));
    }
}
```

**Apply to watchdog as:**
- `protected $signature = 'irms:mqtt-listener-watchdog';`
- `handle(): int` — read `Camera::whereNull('decommissioned_at')->count()`, read `Cache::get('mqtt:listener:last_message_received_at')`, compute state (HEALTHY / SILENT / NO_ACTIVE_CAMERAS per D-11), compare to `Cache::get('mqtt:listener:last_known_state')`, on transition `MqttListenerHealthChanged::dispatch(...)` and `Cache::put('mqtt:listener:last_known_state', $state)`.
- Return `self::SUCCESS`.

---

### `app/Mqtt/Contracts/MqttHandler.php` (interface)

**Analog:** `app/Contracts/ProximityServiceInterface.php`

**Interface convention** (exact 1:1 structure):

```php
<?php

namespace App\Contracts;

interface ProximityServiceInterface
{
    /**
     * Rank nearby available units by distance from the given coordinates.
     *
     * @return array<int, object>
     */
    public function rankNearbyUnits(float $latitude, float $longitude, float $radiusMeters = 50000.0): array;
}
```

**Apply to `MqttHandler`:**
- Note: IRMS convention places interfaces in `app/Contracts/` (flat). Phase 19 deviates intentionally by using `app/Mqtt/Contracts/` (per ARCHITECTURE.md — `app/Mqtt/` is a sibling domain boundary to `app/Http/`). Document this in the plan.
- One method: `public function handle(string $topic, string $message): void;`
- PHPDoc one-liner above method per IRMS convention: `/** Handle an incoming MQTT message. */`

---

### `app/Mqtt/TopicRouter.php` (service / router)

**Analog:** `/Users/helderdene/fras/app/Mqtt/TopicRouter.php` (FRAS verbatim — port) + `app/Services/StubMapboxDirectionsService.php` (IRMS class conventions).

**FRAS source to port** (FRAS `app/Mqtt/TopicRouter.php` lines 1-35):

```php
<?php

namespace App\Mqtt;

use App\Mqtt\Contracts\MqttHandler;
use App\Mqtt\Handlers\AckHandler;
use App\Mqtt\Handlers\HeartbeatHandler;
use App\Mqtt\Handlers\OnlineOfflineHandler;
use App\Mqtt\Handlers\RecognitionHandler;
use Illuminate\Support\Facades\Log;

class TopicRouter
{
    /** @var array<string, class-string<MqttHandler>> */
    private array $routes = [
        '#mqtt/face/[^/]+/Rec$#' => RecognitionHandler::class,
        '#mqtt/face/[^/]+/Ack$#' => AckHandler::class,
        '#^mqtt/face/basic$#' => OnlineOfflineHandler::class,
        '#^mqtt/face/heartbeat$#' => HeartbeatHandler::class,
    ];

    /** Dispatch an MQTT message to the appropriate handler based on topic pattern. */
    public function dispatch(string $topic, string $message): void
    {
        foreach ($this->routes as $pattern => $handlerClass) {
            if (preg_match($pattern, $topic)) {
                app($handlerClass)->handle($topic, $message);

                return;
            }
        }

        Log::warning('Unmatched MQTT topic', ['topic' => $topic]);
    }
}
```

**IRMS-specific changes (per RESEARCH.md §Pattern 1):**
1. Constructor builds `$this->routes` using `config('fras.mqtt.topic_prefix')` so tests can override (replace hardcoded `mqtt/face` strings).
2. Inside `dispatch()` BEFORE handler invocation: `Cache::put('mqtt:listener:last_message_received_at', now()->toIso8601String(), now()->addSeconds(120));` (D-05).
3. Unmatched-topic log uses `Log::channel('mqtt')->warning(...)` not default channel (D-17).

**IRMS class-convention reinforcement from `StubMapboxDirectionsService.php` lines 1-20:**
- `use` block alphabetical, one per line.
- Constructor promotion if DI is needed (it isn't — `new TopicRouter` / service-container default resolution is fine).
- PHPDoc `/** Explanation. */` above public methods.

---

### `app/Mqtt/Handlers/RecognitionHandler.php` (handler, event-driven write-through)

**Analog:** `/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php` (FRAS verbatim port with modifications per RESEARCH.md §Pattern 2, lines 351-400).

**Key IRMS adaptations:**
1. `use App\Enums\RecognitionSeverity;` (Phase 18 enum, not FRAS's `AlertSeverity`).
2. Wrap `RecognitionEvent::create(...)` in `try/catch (UniqueConstraintViolationException $e)` — D-03. Reference the test pattern in `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` lines 9-22.
3. `Storage::disk('fras_events')` not `disk('local')` — D-15.
4. Path template: `{YYYY-MM-DD}/faces/{event_id}.jpg` and `{YYYY-MM-DD}/scenes/{event_id}.jpg`.
5. Unknown-camera early return with `Log::channel('mqtt')->warning('RecPush for unknown camera', [...])` — D-14.
6. No broadcast in Phase 19 — delete FRAS's `RecognitionAlert::dispatch()` call.
7. `incident_id` stays NULL (Phase 21 wires `FrasIncidentFactory`).

**Idempotency pattern to apply (IRMS convention from `RecognitionEventIdempotencyTest.php` lines 5, 13-21):**
```php
use Illuminate\Database\UniqueConstraintViolationException;
// ...
try {
    $event = RecognitionEvent::create([...]);
} catch (UniqueConstraintViolationException $e) {
    Log::channel('mqtt')->info('Duplicate RecPush rejected at DB layer', [
        'camera_id' => $camera->id,
        'record_id' => $recordId,
    ]);
    return;
}
```

---

### `app/Mqtt/Handlers/{Ack,Heartbeat,OnlineOffline}Handler.php` (handler, event-driven)

**Analogs:** FRAS files at `/Users/helderdene/fras/app/Mqtt/Handlers/{AckHandler,HeartbeatHandler,OnlineOfflineHandler}.php` — port verbatim.

**IRMS-specific trims:**
- `AckHandler`: Phase 19 scaffolds only. JSON-decode + `Log::channel('mqtt')->info('ACK received', [...])`. Do NOT port FRAS's enrollment-state update / correlation cache writes (Phase 20).
- `HeartbeatHandler`: keep FRAS `last_seen_at` bump via `facesluiceId` payload field. Use `Camera::where('device_id', ...)->update(['last_seen_at' => now()])`.
- `OnlineOfflineHandler`: port operator `Online`/`Offline` toggle; write `CameraStatus::Online` / `CameraStatus::Offline` via Phase 18 enum. **Do NOT port FRAS's `EnrollPersonnelBatch::dispatch()` call** — Phase 20.

**Use of Phase 18 enum** (from `app/Enums/CameraStatus.php` lines 1-22):
```php
use App\Enums\CameraStatus;
// ...
$camera->update(['status' => $operator === 'Online' ? CameraStatus::Online : CameraStatus::Offline]);
```

---

### `app/Events/MqttListenerHealthChanged.php` (broadcast-event)

**Analog:** `app/Events/IncidentCreated.php` (exact match).

**Full shape to mirror** (from `app/Events/IncidentCreated.php` lines 1-56):

```php
<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Incident $incident) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dispatch.incidents'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // ... payload array
    }
}
```

**Apply to `MqttListenerHealthChanged`:**
- Same `implements ShouldBroadcast, ShouldDispatchAfterCommit` + same trait set.
- Constructor: `public function __construct(public string $status, public ?string $lastMessageReceivedAt, public string $since, public int $activeCameraCount) {}`
- `broadcastOn()` returns `[new PrivateChannel('dispatch.incidents')]` — D-10, reuse existing channel (no `routes/channels.php` change).
- `broadcastWith()` returns the D-11 payload: `['status', 'last_message_received_at', 'since', 'active_camera_count']`.

---

### `config/fras.php` (NEW config)

**Analog:** `config/logging.php` (Laravel `return [...]` convention) + FRAS `config/hds.php` (port source).

**IRMS config convention** (from `config/logging.php` lines 1-22):
```php
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    // ...
    'channels' => [ /* ... */ ],
];
```

**Apply to `config/fras.php`:**
```php
<?php

return [
    'mqtt' => [
        'topic_prefix' => env('FRAS_MQTT_TOPIC_PREFIX', 'mqtt/face'),
        'keepalive' => (int) env('FRAS_MQTT_KEEPALIVE', 60),
        'reconnect_delay' => (int) env('FRAS_MQTT_RECONNECT_DELAY', 5),
    ],
    // retention block reserved for Phase 22; drop mapbox/enrollment/photo from FRAS hds.php
];
```

---

### `config/mqtt-client.php` (NEW config)

**Analog:** `/Users/helderdene/fras/config/mqtt-client.php` (FRAS verbatim port; publish via `php artisan vendor:publish --provider="PhpMqtt\Client\MqttClientServiceProvider" --tag="config"` then modify to mirror FRAS). MQTT-06 mandates subscriber + publisher connection blocks.

---

### `config/horizon.php` (MOD — add fras-supervisor block)

**Analog:** same file — `environments.production.supervisor-1` block (lines 216-225) and `environments.local.supervisor-1` (lines 228-238).

**Existing block to copy shape from** (lines 215-239):
```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'minProcesses' => 2,
            'maxProcesses' => 10,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],

    'local' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'tries' => 3,
            'timeout' => 30,
        ],
    ],
],
```

**Add sibling `fras-supervisor` in both `production` and `local`:**
```php
'fras-supervisor' => [
    'connection' => 'redis',
    'queue' => ['fras'],
    'balance' => 'auto',
    'minProcesses' => 1,   // production: 1; local: 1 (low-throughput enrollment work per D-02)
    'maxProcesses' => 3,   // production: 3; local: 1
    'tries' => 3,
    'timeout' => 120,       // enrollment batches may take longer than default
],
```

---

### `config/logging.php` (MOD — add mqtt channel)

**Analog:** same file — `channels.daily` block (lines 68-74).

**Existing block to mirror** (lines 68-74):
```php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => env('LOG_DAILY_DAYS', 14),
    'replace_placeholders' => true,
],
```

**Add `channels.mqtt`:**
```php
'mqtt' => [
    'driver' => 'daily',
    'path' => storage_path('logs/mqtt.log'),
    'level' => env('MQTT_LOG_LEVEL', 'info'),
    'days' => env('MQTT_LOG_DAILY_DAYS', 14),
    'replace_placeholders' => true,
],
```

---

### `config/filesystems.php` (MOD — add fras_events disk)

**Analog:** same file — `disks.local` block (lines 33-39).

**Existing block to mirror** (lines 33-39):
```php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => true,
    'throw' => false,
    'report' => false,
],
```

**Add `disks.fras_events` (private, no `url` key per D-15):**
```php
'fras_events' => [
    'driver' => env('FRAS_EVENT_DISK', 'local'),
    'root' => storage_path('app/private/fras_events'),
    'visibility' => 'private',
    'throw' => false,
    'report' => false,
],
```

---

### `composer.json` (MOD — add 6th concurrently target)

**Analog:** same file — existing `scripts.dev` line 61.

**Existing** (line 61):
```json
"npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac\" \"php artisan serve\" \"php artisan reverb:start\" \"php artisan horizon\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,reverb,horizon,logs,vite --kill-others"
```

**Modify to add 6th target** (color `#f59e0b` + name `mqtt`, per D-16):
```json
"npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#f59e0b\" \"php artisan serve\" \"php artisan reverb:start\" \"php artisan horizon\" \"php artisan pail --timeout=0\" \"npm run dev\" \"php artisan irms:mqtt-listen\" --names=server,reverb,horizon,logs,vite,mqtt --kill-others"
```

---

### `routes/console.php` (MOD — Schedule watchdog)

**Analog:** same file — existing `Schedule::job(...)` line 12.

**Existing shape** (lines 1-14):
```php
<?php

use App\Jobs\GenerateDilgMonthlyReport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDilgMonthlyReport)->monthlyOn(1, '00:00')
    ->timezone('Asia/Manila')
    ->description('Generate DILG monthly incident report');
```

**Add after existing Schedule (per D-07):**
```php
Schedule::command('irms:mqtt-listener-watchdog')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->description('Detect MQTT listener silence and broadcast health transitions');
```

---

### `app/Http/Controllers/DispatchConsoleController.php` (MOD — Inertia shared prop)

**Analog:** same file — `show()` method (lines 38-123).

**Existing Inertia::render block to extend** (lines 117-122):
```php
return Inertia::render('dispatch/Console', [
    'incidents' => $incidents,
    'units' => $units,
    'agencies' => $agencies,
    'metrics' => $metrics,
]);
```

**Modify to add D-12 shared prop:**
```php
use Illuminate\Support\Facades\Cache;
// ...

$mqttListenerHealth = [
    'status' => Cache::get('mqtt:listener:last_known_state', 'HEALTHY'),
    'last_message_received_at' => Cache::get('mqtt:listener:last_message_received_at'),
    'since' => Cache::get('mqtt:listener:last_state_since'),
    'active_camera_count' => Camera::whereNull('decommissioned_at')->count(),
];

return Inertia::render('dispatch/Console', [
    'incidents' => $incidents,
    'units' => $units,
    'agencies' => $agencies,
    'metrics' => $metrics,
    'mqtt_listener_health' => $mqttListenerHealth,
]);
```

---

### `resources/js/components/fras/MqttListenerHealthBanner.vue` (presentational component)

**Analog:** `resources/js/components/ConnectionBanner.vue` (exact role-match — red/amber banner with Transition wrapper).

**Full shape to mirror** (lines 1-48):

```vue
<script setup lang="ts">
import { Loader2, Wifi, WifiOff } from 'lucide-vue-next';
import type { BannerLevel } from '@/composables/useWebSocket';

defineProps<{
    bannerLevel: BannerLevel;
    isSyncing: boolean;
}>();

const bannerClasses: Record<Exclude<BannerLevel, 'none'>, string> = {
    amber: 'bg-amber-500 text-white',
    red: 'bg-red-600 text-white',
    green: 'bg-green-500 text-white',
};
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="max-h-0 opacity-0"
        enter-to-class="max-h-12 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="max-h-12 opacity-100"
        leave-to-class="max-h-0 opacity-0"
    >
        <div
            v-if="bannerLevel !== 'none'"
            :class="[
                'flex items-center justify-center gap-2 overflow-hidden px-4 py-2 text-sm font-medium',
                bannerClasses[bannerLevel],
            ]"
        >
            <!-- per-level icon + label -->
        </div>
    </Transition>
</template>
```

**Apply to `MqttListenerHealthBanner.vue`:**
- `defineProps<{ status: MqttListenerHealthStatus; lastMessageReceivedAt: string | null; since: string }>();`
- Render only when `status === 'SILENT'` (or `'DISCONNECTED'` reserved) — D-13 red banner; renders `null` for `HEALTHY` / `NO_ACTIVE_CAMERAS`.
- Reuse `Transition` enter/leave classes verbatim.
- Tailwind `bg-red-600 text-white` for SILENT; use `AlertTriangle` from `lucide-vue-next` (not WifiOff — semantic mismatch).

---

### `resources/js/composables/useDispatchFeed.ts` (MOD — subscribe to MqttListenerHealthChanged)

**Analog:** same file — existing `useEcho<T>('dispatch.incidents', 'EventName', cb)` blocks (lines 127-209).

**Existing pattern to replicate** (lines 127-130):
```typescript
useEcho<IncidentCreatedPayload>(
    'dispatch.incidents',
    'IncidentCreated',
    (e) => {
        // ... handle event
    },
);
```

**Add after existing `useEcho` blocks (before line 407 `// --- dispatch.units channel ---`):**
```typescript
const mqttListenerHealth = ref<MqttListenerHealth>(initialMqttHealth);

useEcho<MqttListenerHealthPayload>(
    'dispatch.incidents',
    'MqttListenerHealthChanged',
    (e) => {
        mqttListenerHealth.value = {
            status: e.status,
            lastMessageReceivedAt: e.last_message_received_at,
            since: e.since,
            activeCameraCount: e.active_camera_count,
        };
    },
);
```

Then add `mqttListenerHealth` to the returned object (line 518-528 block). Update the composable's function signature to accept `initialMqttHealth: MqttListenerHealth` as a parameter.

---

### `resources/js/types/mqtt.ts` (NEW type-defs)

**Analog:** `resources/js/types/dispatch.ts` (lines 1-40).

**IRMS convention to mirror:**
```typescript
import type { /* ... */ } from '@/types/incident';

export type UnitStatus =
    | 'AVAILABLE'
    | 'DISPATCHED'
    // ...
    ;

export interface AssignedUnitPivot {
    unit_id: string;
    // ...
}
```

**Apply to `mqtt.ts`:**
```typescript
export type MqttListenerHealthStatus =
    | 'HEALTHY'
    | 'SILENT'
    | 'DISCONNECTED'
    | 'NO_ACTIVE_CAMERAS';

export interface MqttListenerHealth {
    status: MqttListenerHealthStatus;
    lastMessageReceivedAt: string | null;
    since: string;
    activeCameraCount: number;
}

export interface MqttListenerHealthPayload {
    status: MqttListenerHealthStatus;
    last_message_received_at: string | null;  // snake_case from Laravel broadcast
    since: string;
    active_camera_count: number;
}
```

---

### `tests/Feature/Mqtt/*.php` (test suite)

**Analog:** `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` (Pest + factory + PostgreSQL-aware group).

**Convention from `RecognitionEventIdempotencyTest.php` lines 1-42:**

```php
<?php

use App\Models\Camera;
use App\Models\RecognitionEvent;
use Illuminate\Database\UniqueConstraintViolationException;

pest()->group('fras');

it('rejects duplicate (camera_id, record_id) via DB UNIQUE constraint', function () {
    $camera = Camera::factory()->create();

    RecognitionEvent::factory()
        ->for($camera)
        ->create(['record_id' => 123456]);

    expect(fn () => RecognitionEvent::factory()
        ->for($camera)
        ->create(['record_id' => 123456])
    )->toThrow(UniqueConstraintViolationException::class);
});
```

**Apply to Phase 19 tests:**
- `pest()->group('mqtt');` at top of each file (new group).
- Factory idiom: `Camera::factory()->create([...])` for setup.
- Assertion idiom: `expect(...)` for object shape, `it('does X', function () { ... });` for test names.
- For handler tests: build topic + JSON message strings, instantiate handler via `app(RecognitionHandler::class)`, call `->handle($topic, $message)`, assert DB state and `Storage::disk('fras_events')->assertExists(...)`.
- For `TopicRouterTest`: mock handler via `$this->app->instance(RecognitionHandler::class, $mock)` (D-04 explicit guidance).
- For `MqttListenerWatchdogTest`: `Event::fake([MqttListenerHealthChanged::class])`, then `Event::assertDispatched(MqttListenerHealthChanged::class, fn ($e) => $e->status === 'SILENT')`.

---

### `docs/operations/irms-mqtt.md` (operational doc)

**Analog:** `docs/operations/laravel-13-upgrade.md` §8 Supervisor Configuration Reference (referenced in CONTEXT canonical refs; file not read here — planner should mirror §8 structure verbatim).

**Structure to mirror:**
- Dev prerequisites (mosquitto install per OS).
- `[program:irms-mqtt]` Supervisor block (mirror `[program:irms-horizon]` from §8 with `stopwaitsecs=30`, `autorestart=unexpected`).
- Deploy protocol: `supervisorctl restart irms-mqtt:*` addendum to deploy script.
- Post-deploy smoke test (`mosquitto_pub` one-liner with sentinel `device_id='irms-smoketest'`).

---

## Shared Patterns

### MQTT Logging (D-17)

**Source:** new `config/logging.php` `channels.mqtt` (see MOD above).
**Apply to:** `TopicRouter`, `FrasMqttListenCommand`, `FrasMqttListenerWatchdogCommand`, all four handlers.

```php
use Illuminate\Support\Facades\Log;
// ...
Log::channel('mqtt')->info('Event description', ['context' => $value]);
Log::channel('mqtt')->warning('Unmatched MQTT topic', ['topic' => $topic]);
```

**Never use:** `Log::info(...)` (goes to default stack — defeats Pitfall 6). Always `Log::channel('mqtt')->...`.

### Liveness Cache Key (D-05)

**Source:** `TopicRouter::dispatch()` (new).
**Apply to:** `TopicRouter` (writer), `FrasMqttListenerWatchdogCommand` (reader), `DispatchConsoleController::show` (reader).

```php
use Illuminate\Support\Facades\Cache;

// Writer (TopicRouter::dispatch):
Cache::put('mqtt:listener:last_message_received_at', now()->toIso8601String(), now()->addSeconds(120));

// Reader (watchdog + controller):
$lastSeen = Cache::get('mqtt:listener:last_message_received_at');  // ISO8601 string | null
$knownState = Cache::get('mqtt:listener:last_known_state', 'HEALTHY');
```

**Cache key namespace convention:** `mqtt:listener:*` (planner picks TTL 120s; renewed every message).

### Phase 18 Enum Use

**Source:** `app/Enums/CameraStatus.php`, `app/Enums/RecognitionSeverity.php`.
**Apply to:** `OnlineOfflineHandler` (writes only `CameraStatus::Online` / `::Offline` per D-08), `RecognitionHandler` (classifies using `RecognitionSeverity::Info` / `::Warning` / `::Critical`).

```php
use App\Enums\CameraStatus;
use App\Enums\RecognitionSeverity;
// ...
$camera->update(['status' => CameraStatus::Online]);
$event->severity = RecognitionSeverity::Critical;
```

**Note:** `RecognitionSeverity` currently has `label()` + `isCritical()` only. Phase 19 may need a static classifier method `fromEvent(int $personType, int $verifyStatus): self` — confirmed [ASSUMED] in RESEARCH.md line 367. Planner must read `/Users/helderdene/fras/app/Enums/AlertSeverity.php` first.

### Broadcast Channel Reuse (D-10)

**Source:** `app/Events/IncidentCreated.php` line 25.
**Apply to:** `MqttListenerHealthChanged` only (Phase 19 reuses the already-authorized `dispatch.incidents` channel — no `routes/channels.php` change).

```php
return [new PrivateChannel('dispatch.incidents')];
```

### Pint + IRMS PHP Conventions

**Source:** All existing IRMS PHP files (e.g., `app/Events/IncidentCreated.php`, `app/Http/Controllers/DispatchConsoleController.php`, `app/Jobs/CheckAckTimeout.php`).
**Apply to:** All new Phase 19 PHP files.

- `<?php` with single blank line after.
- `namespace App\...;` followed by blank line.
- `use` imports: alphabetical, one per line, grouped with no blank lines between (Pint enforces).
- Constructor promotion: `public function __construct(public Type $var, private Type $other) {}`.
- Explicit return types on all methods (`: void`, `: int`, `: Response`, etc.).
- PHPDoc block (not inline comment) above public methods with complex behavior.
- Run `vendor/bin/pint --dirty --format agent` before finalizing any PHP change.

### TypeScript Conventions

**Source:** `resources/js/types/dispatch.ts`, `resources/js/composables/useDispatchFeed.ts`.
**Apply to:** `resources/js/types/mqtt.ts`, `resources/js/composables/useDispatchFeed.ts` (MOD), `resources/js/components/fras/MqttListenerHealthBanner.vue`.

- `import type { ... } from '@/...';` for type-only imports (ESLint `prefer-type-imports` enforces).
- Path alias `@/*` maps to `resources/js/*`.
- `export interface` for object shapes; `export type X = 'A' | 'B'` for string-literal unions.
- Snake_case keys in broadcast payload types (they come from Laravel); camelCase in Vue state.

## No Analog Found

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `app/Mqtt/*` (new directory tree) | domain boundary | event-driven | IRMS has no existing `app/Mqtt/` or analogous ingress sibling to `app/Http/`. Port FRAS structure verbatim; ARCHITECTURE.md explicitly sanctions `app/Mqtt/` as domain-boundary sibling. |
| `app/Console/Commands/` (empty directory) | console commands | long-running + scheduled | No existing IRMS Artisan commands beyond the default `inspire`. Use FRAS `FrasMqttListenCommand` + Laravel `Command` idiom; `app/Jobs/CheckAckTimeout.php` provides the closest "read-state-then-act" class shape. |

**Banner component:** `resources/js/components/AppBanner.vue` does NOT exist. Confirmed existing: `resources/js/components/ConnectionBanner.vue` (role-exact match). Per CONTEXT.md Discretion line 86, Phase 19 creates new `MqttListenerHealthBanner.vue` under `resources/js/components/fras/` and uses `ConnectionBanner.vue` as the pattern source.

## Metadata

**Analog search scope:** `app/`, `config/`, `routes/`, `resources/js/components/`, `resources/js/composables/`, `resources/js/types/`, `tests/Feature/`, `composer.json`, `/Users/helderdene/fras/app/Mqtt/`, `/Users/helderdene/fras/app/Console/Commands/`.
**Files scanned:** ~40 (directly read: 14 IRMS + 3 FRAS reference; directory-listed: 10).
**Pattern extraction date:** 2026-04-21
