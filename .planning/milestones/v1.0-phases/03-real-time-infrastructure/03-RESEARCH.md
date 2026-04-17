# Phase 3: Real-Time Infrastructure - Research

**Researched:** 2026-03-13
**Domain:** WebSocket infrastructure (Laravel Reverb, Echo, Redis, Horizon)
**Confidence:** HIGH

## Summary

Phase 3 establishes the real-time communication backbone for IRMS. The stack is entirely first-party Laravel: Reverb for WebSocket server, Echo for client-side subscriptions, Horizon for queue monitoring, and Redis as the unified backend for cache, queue, and pub/sub. All components are mature, well-documented, and designed to work together seamlessly.

The key technical challenge is wiring together six different broadcast events, four channel types (global incidents, global units, per-user personal, presence dispatch), and a reconnection UX that automatically syncs state. The official `@laravel/echo-vue` package provides Vue 3 composables (`useEcho`, `useEchoPresence`, `useConnectionStatus`) that eliminate the need for a custom Echo integration layer. The `configureEcho` function initializes the Echo instance once, and composables handle automatic cleanup on component unmount.

**Primary recommendation:** Use `php artisan install:broadcasting --reverb --no-interaction` to scaffold Reverb + Echo + channels.php in one command. Layer `@laravel/echo-vue` for Vue composables. Build a single `useWebSocket` composable that wraps `useConnectionStatus` with the reconnection banner logic and state-sync API call.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Global dispatch channel (`dispatch.incidents`) for all incident events -- all dispatchers/supervisors/admins subscribe
- Global units channel (`dispatch.units`) for all unit location/status updates -- same audience
- Personal channel per user (`user.{id}`) for direct pushes (assignment notifications to responders)
- Presence channel (`presence-dispatch`) to track which dispatchers/supervisors are online -- shift awareness and oversight
- Role-based channel authorization: dispatcher, supervisor, and admin can subscribe to dispatch channels; responders only receive on their personal channel
- Top amber banner on disconnect: "Reconnecting..." with spinner -- non-blocking, user can still interact with cached data
- After 30s disconnected: banner escalates from amber to red -- "Connection lost -- data may be outdated"
- On reconnect: auto state-sync via API endpoint fetching current active incidents and units -- banner turns green briefly ("Connected -- syncing...") then disappears
- No user action needed for reconnection or state-sync -- fully automatic
- Build a `useWebSocket` composable that handles connection state, reconnection banner, and state-sync
- All pages import the composable and subscribe to specific channels
- Single source of truth for connection status across the application
- Replace Phase 2's 10s Inertia polling on dispatch queue and channel monitor with WebSocket push
- IncidentCreated and IncidentStatusChanged events update queue and channel monitor in real-time
- New incidents auto-insert into dispatch queue at correct priority position with brief highlight animation (yellow flash ~3s)
- Basic audio notification for P1/P2 incidents arriving (simple alert sound, not the full Phase 4 audio system)
- Define all 6 broadcast event classes in this phase (IncidentCreated, IncidentStatusChanged, UnitLocationUpdated, UnitStatusChanged, AssignmentPushed, MessageSent)
- Only IncidentCreated and IncidentStatusChanged are wired to UI in this phase
- Remaining 4 events are tested but not consumed until Phase 4/5
- Full Redis migration: cache, queue, and Reverb pub/sub all on Redis
- Install Laravel Horizon for queue monitoring with web dashboard at `/horizon` (admin role only)
- QUEUE_CONNECTION -> redis, CACHE_STORE -> redis, BROADCAST_CONNECTION -> reverb
- Update `composer run dev` to concurrently run: Laravel server, Vite, Reverb, Horizon, and Pail logs -- one command starts everything

### Claude's Discretion
- Reconnection retry strategy (exponential backoff timing, max retries)
- State-sync API endpoint design (what data shape, pagination)
- Echo/Reverb client configuration details
- Horizon queue configuration (workers, timeouts, retry policies)
- Event payload shapes and serialization
- Basic audio implementation approach for P1/P2 alerts

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| FNDTN-09 | Laravel Reverb WebSocket server configured with channel authorization and presence channels | Reverb install command, channel authorization patterns in routes/channels.php, presence channel callbacks returning user data arrays, role-based gate checks |
| FNDTN-10 | Redis configured for cache, queue (Horizon), and Reverb pub/sub | Redis already running on port 6379, phpredis extension installed, config/database.php has Redis connections, Horizon install scaffolds queue config |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/reverb | latest (via install:broadcasting) | WebSocket server | First-party Laravel, uses Pusher protocol, built-in Redis scaling |
| laravel/horizon | latest | Redis queue dashboard + supervisor | First-party, admin dashboard, auto-balancing workers |
| laravel-echo | ^2.1 | Client-side WebSocket library | First-party, Reverb broadcaster built-in |
| @laravel/echo-vue | ^0.x | Vue 3 composables for Echo | Official Vue hooks: useEcho, useEchoPresence, useConnectionStatus |
| pusher-js | ^8.x | WebSocket client (Echo dependency) | Required by Echo for Pusher protocol (which Reverb implements) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Redis (phpredis) | Already installed | Cache, queue, pub/sub backend | All Redis operations -- already configured in database.php |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| @laravel/echo-vue composables | Manual Echo + onMounted/onUnmounted | More code, no auto-cleanup, must manage subscriptions manually |
| Horizon | queue:work + manual monitoring | No dashboard, no auto-balancing, no metrics |
| ShouldBroadcast (queued) | ShouldBroadcastNow (sync) | Queued is better for resilience; Now bypasses queue but blocks request |

**Installation:**
```bash
# Backend packages
composer require laravel/reverb laravel/horizon
php artisan install:broadcasting --reverb --no-interaction
php artisan horizon:install --no-interaction
php artisan migrate

# Frontend packages (install:broadcasting adds laravel-echo + pusher-js, but we also need echo-vue)
npm install @laravel/echo-vue
```

Note: `php artisan install:broadcasting --reverb` will:
- Create `config/broadcasting.php`
- Create `config/reverb.php`
- Create `routes/channels.php`
- Add REVERB_* and VITE_REVERB_* env variables to `.env`
- Install `laravel-echo` and `pusher-js` npm packages

## Architecture Patterns

### Recommended Project Structure
```
app/
  Events/
    IncidentCreated.php          # ShouldBroadcast on dispatch.incidents
    IncidentStatusChanged.php    # ShouldBroadcast on dispatch.incidents
    UnitLocationUpdated.php      # ShouldBroadcast on dispatch.units
    UnitStatusChanged.php        # ShouldBroadcast on dispatch.units
    AssignmentPushed.php         # ShouldBroadcast on user.{id}
    MessageSent.php              # ShouldBroadcast on user.{id}
  Http/
    Controllers/
      StateSyncController.php    # GET endpoint for reconnection state-sync
  Providers/
    HorizonServiceProvider.php   # Dashboard auth (admin role gate)
config/
  broadcasting.php               # Reverb connection config
  reverb.php                     # Reverb server config
  horizon.php                    # Queue workers, balancing strategy
routes/
  channels.php                   # Channel authorization callbacks
resources/js/
  composables/
    useWebSocket.ts              # Connection state, banner, state-sync
  components/
    ConnectionBanner.vue         # Amber -> Red -> Green banner
    incidents/
      ChannelMonitor.vue         # Updated: WebSocket instead of polling
  pages/
    incidents/
      Queue.vue                  # Updated: WebSocket instead of usePoll
```

### Pattern 1: Broadcast Event with ShouldBroadcast
**What:** Event class that implements ShouldBroadcast for queued broadcasting
**When to use:** All 6 broadcast events in this phase
**Example:**
```php
// Source: Laravel 12.x Broadcasting docs
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

    public function __construct(
        public Incident $incident,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
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
        return [
            'id' => $this->incident->id,
            'incident_no' => $this->incident->incident_no,
            'priority' => $this->incident->priority->value,
            'status' => $this->incident->status->value,
            'incident_type' => $this->incident->incidentType?->name,
            'location_text' => $this->incident->location_text,
            'barangay' => $this->incident->barangay?->name,
            'channel' => $this->incident->channel->value,
            'created_at' => $this->incident->created_at->toISOString(),
        ];
    }
}
```

### Pattern 2: Channel Authorization in routes/channels.php
**What:** Role-based authorization for private and presence channels
**When to use:** All 4 channel types
**Example:**
```php
// Source: Laravel 12.x Broadcasting docs
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Private channel: dispatch incidents (dispatcher, supervisor, admin)
Broadcast::channel('dispatch.incidents', function (User $user) {
    return in_array($user->role, [
        UserRole::Dispatcher,
        UserRole::Supervisor,
        UserRole::Admin,
    ], true);
});

// Private channel: dispatch units (same audience)
Broadcast::channel('dispatch.units', function (User $user) {
    return in_array($user->role, [
        UserRole::Dispatcher,
        UserRole::Supervisor,
        UserRole::Admin,
    ], true);
});

// Private channel: personal user channel (only the user themselves)
Broadcast::channel('user.{id}', function (User $user, string $id) {
    return $user->id === $id;
});

// Presence channel: dispatch room (returns user info for online tracking)
Broadcast::channel('presence-dispatch', function (User $user) {
    if (in_array($user->role, [
        UserRole::Dispatcher,
        UserRole::Supervisor,
        UserRole::Admin,
    ], true)) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role->value,
        ];
    }
    return false;
});
```

### Pattern 3: Vue Composable with @laravel/echo-vue
**What:** Using official Echo Vue composables for channel subscriptions
**When to use:** All pages that need real-time updates
**Example:**
```typescript
// Source: Laravel 12.x Broadcasting docs + @laravel/echo-vue
import { useEcho, useConnectionStatus } from '@laravel/echo-vue';

// In app.ts (once, before any component uses useEcho):
import { configureEcho } from '@laravel/echo-vue';
configureEcho({
    broadcaster: 'reverb',
});

// In a Vue component:
useEcho(
    'dispatch.incidents',
    'IncidentCreated',
    (e) => {
        // e contains broadcastWith() data
        console.log('New incident:', e.incident_no);
    },
);

// Connection status for banner:
const status = useConnectionStatus();
// Returns: 'connected' | 'connecting' | 'reconnecting' | 'disconnected' | 'failed'
```

### Pattern 4: Presence Channel with Vue
**What:** Using useEchoPresence for tracking online dispatchers
**When to use:** Presence-dispatch channel
**Example:**
```typescript
// Source: Laravel 12.x Broadcasting docs
import { useEchoPresence } from '@laravel/echo-vue';

// Listen to presence channel events
useEchoPresence('dispatch', 'SomeEvent', (e) => {
    console.log(e);
});

// For .here()/.joining()/.leaving(), use the Echo instance directly:
import { echo } from '@laravel/echo-vue';

const channel = echo().join('presence-dispatch')
    .here((users) => {
        onlineDispatchers.value = users;
    })
    .joining((user) => {
        onlineDispatchers.value.push(user);
    })
    .leaving((user) => {
        onlineDispatchers.value = onlineDispatchers.value.filter(
            (u) => u.id !== user.id
        );
    });
```

### Pattern 5: State-Sync API Endpoint
**What:** Dedicated endpoint that returns current state after reconnection
**When to use:** On WebSocket reconnection to fill the gap
**Example:**
```php
// StateSyncController.php
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Unit;

class StateSyncController extends Controller
{
    public function __invoke(): \Illuminate\Http\JsonResponse
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
            ->whereNot('status', 'OFFLINE')
            ->get(['id', 'callsign', 'type', 'status', 'coordinates']);

        return response()->json([
            'incidents' => $incidents,
            'channelCounts' => $channelCounts,
            'units' => $units,
        ]);
    }
}
```

### Anti-Patterns to Avoid
- **Subscribing in onMounted without cleanup:** The `useEcho` composable handles this automatically -- do NOT manually call `Echo.private()` in `onMounted` without matching `leaveChannel()` in `onUnmounted`
- **Using ShouldBroadcastNow for regular events:** This bypasses the queue and blocks the HTTP request. Use `ShouldBroadcast` (queued) for all events. Only use `ShouldBroadcastNow` if latency is more critical than reliability
- **Dispatching events before database commit:** Always implement `ShouldDispatchAfterCommit` on broadcast events to ensure the database state is committed before the event is broadcast. Without this, clients may query for data that does not yet exist
- **Polling AND WebSocket simultaneously:** When replacing `usePoll(10000)` on Queue.vue, remove the polling entirely. Do not keep polling as a "fallback" -- the state-sync on reconnect handles the gap
- **Global window.Echo:** The old pattern of `window.Echo = new Echo(...)` in bootstrap.js is outdated. Use `configureEcho()` from `@laravel/echo-vue` which manages the singleton internally

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| WebSocket server | Custom WebSocket with Ratchet/Swoole | Laravel Reverb | Handles connection mgmt, scaling, auth, Pusher protocol |
| Channel subscriptions in Vue | Manual Echo.private() with onMounted/onUnmounted | @laravel/echo-vue useEcho composable | Auto-cleanup, TypeScript types, reactive status |
| Connection status tracking | Custom ping/pong with timers | useConnectionStatus() from @laravel/echo-vue | Built-in reactive ref: connected/connecting/reconnecting/disconnected |
| Queue monitoring dashboard | Custom admin page querying jobs table | Laravel Horizon | Dashboard, metrics, auto-balancing, retry policies |
| Exponential backoff for reconnect | Custom retry timer logic | pusher-js built-in reconnection | Echo/pusher-js already implements exponential backoff with jitter |
| Audio playback | Custom Audio() constructor management | Web Audio API with simple helper | Browsers require user interaction before audio; need unlock pattern |

**Key insight:** The entire Reverb + Echo + Horizon stack is designed to work together with minimal glue code. The `install:broadcasting --reverb` command scaffolds 90% of the configuration. The Vue composables from `@laravel/echo-vue` handle subscription lifecycle automatically.

## Common Pitfalls

### Pitfall 1: CSRF/Auth Mismatch on Channel Authorization
**What goes wrong:** Private channel subscriptions fail with 403 errors
**Why it happens:** Reverb uses the Pusher protocol for auth, which sends a POST to `/broadcasting/auth`. If the web middleware group (session, CSRF) is not properly applied to this route, auth fails
**How to avoid:** The `install:broadcasting` command registers the broadcasting auth route automatically. Verify it is registered with `php artisan route:list | grep broadcasting`. Ensure the route uses the `web` middleware group (default for Inertia apps)
**Warning signs:** Console errors like "Unable to subscribe to private channel" or 403 responses to `/broadcasting/auth`

### Pitfall 2: Event Not Received -- Queue Not Running
**What goes wrong:** Events dispatch but never reach clients
**Why it happens:** `ShouldBroadcast` events are queued. If no queue worker is running, events sit in Redis forever. With the old `database` queue, `queue:listen` was sufficient. With Redis + Horizon, you need `php artisan horizon` running
**How to avoid:** Update `composer run dev` script to include Horizon. Verify Horizon is running via the `/horizon` dashboard
**Warning signs:** Events dispatch without error but clients never receive them; Horizon dashboard shows pending jobs

### Pitfall 3: Redis Not Available in Test Environment
**What goes wrong:** Tests fail because they try to connect to Redis
**Why it happens:** Feature tests use RefreshDatabase with SQLite in-memory. Switching QUEUE_CONNECTION to redis means tests need Redis. But broadcast testing should use Event::fake()
**How to avoid:** In `phpunit.xml` or `.env.testing`, set `BROADCAST_CONNECTION=log` and `QUEUE_CONNECTION=sync`. Use `Event::fake()` to assert events are dispatched without actually broadcasting. Test channel authorization separately with HTTP tests to `/broadcasting/auth`
**Warning signs:** Test failures mentioning Redis connection refused

### Pitfall 4: Reverb Port Conflict with Herd
**What goes wrong:** Reverb server fails to start or connects on wrong port
**Why it happens:** Laravel Herd serves the app on a custom port. Reverb defaults to 8080. The VITE_REVERB_HOST must match what the browser can reach
**How to avoid:** Set `REVERB_SERVER_HOST=0.0.0.0`, `REVERB_SERVER_PORT=8080`, `REVERB_HOST=localhost`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`. For HTTPS with Herd, use the `--hostname` flag: `php artisan reverb:start --hostname=irms.test`
**Warning signs:** WebSocket connection errors in browser console, "ws://localhost:8080 failed"

### Pitfall 5: SerializesModels Loading Stale Data
**What goes wrong:** Broadcast payload contains outdated model data
**Why it happens:** The `SerializesModels` trait serializes only the model ID and reloads from DB when the job runs. If the model was updated between dispatch and broadcast, you get the latest data (usually fine). But if relations were loaded in the controller, they are NOT serialized -- must be re-loaded in `broadcastWith()`
**How to avoid:** Always define explicit `broadcastWith()` on events to control the payload. Load relations inside `broadcastWith()` if needed. Do NOT rely on pre-loaded relations surviving serialization
**Warning signs:** Missing relation data in broadcast payload, or unexpected null values

### Pitfall 6: Forgetting ShouldDispatchAfterCommit
**What goes wrong:** Client receives event, queries API for the new data, but gets 404 or stale state
**Why it happens:** Event dispatched inside a database transaction. The queue worker picks it up before the transaction commits. Client queries the DB but the row does not exist yet
**How to avoid:** Always implement `ShouldDispatchAfterCommit` alongside `ShouldBroadcast` on all events that relate to database changes
**Warning signs:** Intermittent 404s on state-sync, race conditions where data "appears" after a slight delay

### Pitfall 7: Browser Audio Autoplay Policy
**What goes wrong:** Audio notification for P1/P2 does not play
**Why it happens:** Browsers block audio playback until the user has interacted with the page. On first page load, `new Audio().play()` throws a DOMException
**How to avoid:** "Unlock" the audio context on the first user interaction (click/keypress). Use the Web Audio API with a pre-created AudioContext. Resume the context on user interaction, then play sounds freely
**Warning signs:** Console error "play() failed because the user didn't interact with the document first"

## Code Examples

Verified patterns from official sources:

### Echo Configuration in app.ts
```typescript
// Source: Laravel 12.x Broadcasting docs
// resources/js/app.ts - add before createInertiaApp()
import { configureEcho } from '@laravel/echo-vue';

configureEcho({
    broadcaster: 'reverb',
    // Reverb auto-reads VITE_REVERB_* env vars when not specified
});
```

### useWebSocket Composable (recommended design)
```typescript
// resources/js/composables/useWebSocket.ts
import type { Ref } from 'vue';
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useConnectionStatus } from '@laravel/echo-vue';

export type ConnectionState = 'connected' | 'connecting' | 'reconnecting' | 'disconnected' | 'failed';
export type BannerLevel = 'none' | 'amber' | 'red' | 'green';

export function useWebSocket() {
    const status = useConnectionStatus();
    const bannerLevel = ref<BannerLevel>('none');
    const isSyncing = ref(false);
    let disconnectTimer: ReturnType<typeof setTimeout> | null = null;

    watch(status, (newStatus: ConnectionState) => {
        if (disconnectTimer) {
            clearTimeout(disconnectTimer);
            disconnectTimer = null;
        }

        switch (newStatus) {
            case 'connected':
                if (bannerLevel.value !== 'none') {
                    // Was disconnected, now reconnected -- sync state
                    bannerLevel.value = 'green';
                    isSyncing.value = true;
                    syncState().finally(() => {
                        isSyncing.value = false;
                        setTimeout(() => {
                            bannerLevel.value = 'none';
                        }, 2000);
                    });
                }
                break;
            case 'reconnecting':
                bannerLevel.value = 'amber';
                disconnectTimer = setTimeout(() => {
                    bannerLevel.value = 'red';
                }, 30_000);
                break;
            case 'disconnected':
            case 'failed':
                bannerLevel.value = 'red';
                break;
        }
    });

    async function syncState(): Promise<void> {
        // Call state-sync endpoint via Wayfinder action
        // Update reactive stores with latest data
    }

    return {
        status,
        bannerLevel,
        isSyncing,
    };
}
```

### Horizon Authorization (admin role only)
```php
// app/Providers/HorizonServiceProvider.php
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

protected function gate(): void
{
    Gate::define('viewHorizon', function (User $user): bool {
        return $user->role === UserRole::Admin;
    });
}
```

### Updated composer.json dev script
```json
{
    "dev": [
        "Composer\\Config::disableProcessTimeout",
        "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac\" \"php artisan serve\" \"php artisan reverb:start\" \"php artisan horizon\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,reverb,horizon,logs,vite --kill-others"
    ]
}
```

### Testing Broadcast Events with Event::fake()
```php
// Source: Laravel 12.x Events testing docs
use App\Events\IncidentCreated;
use App\Models\Incident;
use Illuminate\Support\Facades\Event;

test('creating incident dispatches IncidentCreated event', function () {
    Event::fake([IncidentCreated::class]);

    // Create incident via the store endpoint
    $this->actingAs($dispatcher)->post(route('incidents.store'), $validData);

    Event::assertDispatched(IncidentCreated::class, function ($event) {
        return $event->incident->priority->value === 'P1';
    });
});

test('IncidentCreated broadcasts on dispatch.incidents channel', function () {
    $incident = Incident::factory()->create();
    $event = new IncidentCreated($incident);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('private-dispatch.incidents');
});

test('IncidentCreated broadcastWith returns correct payload shape', function () {
    $incident = Incident::factory()->create();
    $incident->load('incidentType', 'barangay');

    $event = new IncidentCreated($incident);
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys([
        'id', 'incident_no', 'priority', 'status',
        'incident_type', 'location_text', 'channel', 'created_at',
    ]);
});
```

### Testing Channel Authorization
```php
// Source: Laravel 12.x Broadcasting docs
use App\Models\User;

test('dispatchers can subscribe to dispatch.incidents channel', function () {
    $dispatcher = User::factory()->create(['role' => 'dispatcher']);

    $this->actingAs($dispatcher)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-dispatch.incidents',
        ])
        ->assertOk();
});

test('responders cannot subscribe to dispatch.incidents channel', function () {
    $responder = User::factory()->create(['role' => 'responder']);

    $this->actingAs($responder)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-dispatch.incidents',
        ])
        ->assertForbidden();
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| window.Echo = new Echo() in bootstrap.js | configureEcho() from @laravel/echo-vue | Echo 2.1 (2025) | Composable-first, auto-cleanup, reactive connection status |
| Manual Echo.private() in onMounted | useEcho() composable | Echo 2.1 (2025) | Automatic subscription/unsubscription lifecycle |
| No connection status API | useConnectionStatus() returns reactive ref | Echo 2.1 (2025) | Enables reconnection UX without custom websocket monitoring |
| Pusher/Ably hosted services | Laravel Reverb (self-hosted) | Laravel 11 (2024) | No third-party dependency, full control, Redis scaling |
| queue:work for queue processing | Laravel Horizon | Stable since 2018 | Dashboard, auto-balancing, metrics, retry policies |

**Deprecated/outdated:**
- `beyondcode/laravel-websockets`: Abandoned, replaced by Laravel Reverb
- Manual Echo setup in bootstrap.js: Still works but @laravel/echo-vue composables are the recommended approach
- `window.Pusher = Pusher` global: No longer needed with configureEcho()

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `phpunit.xml` (exists) |
| Quick run command | `php artisan test --compact tests/Feature/RealTime/` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FNDTN-09 | Reverb accepts connections with channel auth | feature | `php artisan test --compact tests/Feature/RealTime/ChannelAuthorizationTest.php -x` | Wave 0 |
| FNDTN-09 | Private channels enforce role-based access | feature | `php artisan test --compact tests/Feature/RealTime/ChannelAuthorizationTest.php -x` | Wave 0 |
| FNDTN-09 | Presence channel returns user data | feature | `php artisan test --compact tests/Feature/RealTime/ChannelAuthorizationTest.php -x` | Wave 0 |
| FNDTN-09 | 6 broadcast events dispatch correctly | unit | `php artisan test --compact tests/Unit/BroadcastEventTest.php -x` | Wave 0 |
| FNDTN-09 | Events broadcast on correct channels | unit | `php artisan test --compact tests/Unit/BroadcastEventTest.php -x` | Wave 0 |
| FNDTN-09 | Events have correct broadcastWith payload | unit | `php artisan test --compact tests/Unit/BroadcastEventTest.php -x` | Wave 0 |
| FNDTN-10 | Redis cache store operational | feature | `php artisan test --compact tests/Feature/RealTime/RedisConfigTest.php -x` | Wave 0 |
| FNDTN-10 | Redis queue connection operational | feature | `php artisan test --compact tests/Feature/RealTime/RedisConfigTest.php -x` | Wave 0 |
| FNDTN-10 | Horizon dashboard accessible to admin | feature | `php artisan test --compact tests/Feature/RealTime/HorizonAccessTest.php -x` | Wave 0 |
| -- | State-sync endpoint returns correct data | feature | `php artisan test --compact tests/Feature/RealTime/StateSyncTest.php -x` | Wave 0 |
| -- | IncidentCreated event dispatched on incident store | feature | `php artisan test --compact tests/Feature/RealTime/BroadcastIntegrationTest.php -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact tests/Feature/RealTime/ tests/Unit/BroadcastEventTest.php`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/RealTime/ChannelAuthorizationTest.php` -- covers FNDTN-09 channel auth
- [ ] `tests/Feature/RealTime/RedisConfigTest.php` -- covers FNDTN-10 Redis migration
- [ ] `tests/Feature/RealTime/HorizonAccessTest.php` -- covers FNDTN-10 Horizon access
- [ ] `tests/Feature/RealTime/StateSyncTest.php` -- covers state-sync endpoint
- [ ] `tests/Feature/RealTime/BroadcastIntegrationTest.php` -- covers event dispatch integration
- [ ] `tests/Unit/BroadcastEventTest.php` -- covers event classes, channels, payloads
- [ ] Test env: `BROADCAST_CONNECTION=log`, `QUEUE_CONNECTION=sync` in phpunit.xml

## Discretion Recommendations

### Reconnection Retry Strategy
**Recommendation:** Rely on pusher-js built-in exponential backoff. Default behavior: reconnect attempts at ~1s, ~2s, ~4s, ~8s, ~16s, then cap at ~30s intervals. Do not override unless testing reveals issues. The 30s timer for banner escalation is separate from the connection retry timing.

### State-Sync API Endpoint Design
**Recommendation:** Single `GET /api/state-sync` endpoint returning:
```json
{
    "incidents": [...],       // All PENDING incidents with type + barangay
    "channelCounts": {...},   // Channel count breakdown
    "units": [...]            // All non-OFFLINE units (id, callsign, type, status, coordinates)
}
```
No pagination needed -- active incident count is small (tens, not thousands). This mirrors the existing `queue()` method data plus unit positions for future dispatch map. Add `role:dispatcher,supervisor,admin` middleware.

### Horizon Queue Configuration
**Recommendation:**
```php
'environments' => [
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
],
```

### Event Payload Shapes
**Recommendation:** Keep payloads flat and minimal. Include only the data the frontend needs to update its UI without additional API calls. Use `broadcastWith()` to control exactly what goes over the wire:

| Event | Payload Keys |
|-------|-------------|
| IncidentCreated | id, incident_no, priority, status, incident_type, location_text, barangay, channel, created_at |
| IncidentStatusChanged | id, incident_no, old_status, new_status, priority |
| UnitLocationUpdated | id, callsign, latitude, longitude, updated_at |
| UnitStatusChanged | id, callsign, old_status, new_status |
| AssignmentPushed | incident (full incident data), unit_id |
| MessageSent | incident_id, sender_id, sender_name, body, sent_at |

### Basic Audio for P1/P2
**Recommendation:** Use Web Audio API with a pre-created AudioContext. On first user interaction (click anywhere in the app), call `audioContext.resume()`. Store a small alert .mp3 (< 50KB) in `public/sounds/alert.mp3`. Play it via fetch + decodeAudioData for reliability. Keep it simple -- Phase 4 will build the full priority-tiered audio system.

## Open Questions

1. **@laravel/echo-vue presence channel .here()/.joining()/.leaving() API**
   - What we know: `useEchoPresence` exists for listening to events ON a presence channel. The `.here()`, `.joining()`, `.leaving()` callbacks are part of the Echo instance `join()` method.
   - What's unclear: Whether `@laravel/echo-vue` provides a composable that wraps the presence join lifecycle, or if we need to use `echo().join()` directly.
   - Recommendation: Try `useEchoPresence` first. If it only handles event listening (not member tracking), fall back to `echo().join()` with manual `onUnmounted` cleanup. This is a small amount of code (~15 lines).

2. **Reverb with HTTPS (Herd secure site)**
   - What we know: Herd can serve sites over HTTPS. Reverb supports TLS via `--hostname` flag and Herd certificate auto-detection.
   - What's unclear: Whether the dev environment currently uses HTTP or HTTPS (APP_URL shows `http://irms.test`).
   - Recommendation: Start with HTTP (`ws://`). The .env already shows `http://irms.test`. Configure REVERB_SCHEME=http. If HTTPS is needed later, switch to `--hostname=irms.test` which auto-detects Herd certificates.

3. **Redis port: 6379 vs Herd's 6138**
   - What we know: `redis-cli ping` responds on port 6379 (standard). Herd Pro's Redis uses 6138 by default.
   - What's unclear: Whether the running Redis is Homebrew-installed or Herd-managed.
   - Recommendation: Keep REDIS_PORT=6379 as currently configured. Redis is confirmed running and responding. No change needed.

## Sources

### Primary (HIGH confidence)
- [Laravel 12.x Reverb docs](https://laravel.com/docs/12.x/reverb) -- Installation, configuration, server management, scaling
- [Laravel 12.x Broadcasting docs](https://laravel.com/docs/12.x/broadcasting) -- Event classes, channel types, authorization, Echo setup, Vue composables
- [Laravel 12.x Horizon docs](https://laravel.com/docs/12.x/horizon) -- Installation, configuration, dashboard auth, queue management
- Codebase analysis -- Existing models, controllers, middleware, composable patterns, test structure

### Secondary (MEDIUM confidence)
- [@laravel/echo-vue npm](https://www.npmjs.com/package/@laravel/echo-vue) -- Composable API (useEcho, useConnectionStatus, useEchoPresence)
- [Laravel News Echo 2.1](https://laravel-news.com/laravel-echo-2-1-0) -- New composable hooks announcement
- [Laravel Herd Redis docs](https://herd.laravel.com/docs/macos/herd-pro-services/redis) -- Herd Redis configuration

### Tertiary (LOW confidence)
- Presence channel composable API details -- needs hands-on validation for `.here()`/`.joining()`/`.leaving()` pattern with `@laravel/echo-vue`

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- All first-party Laravel packages, officially documented, verified via current docs
- Architecture: HIGH -- Patterns follow existing codebase conventions (service layer, composables, middleware), verified against Laravel 12 docs
- Pitfalls: HIGH -- Common issues documented in official docs and verified through multiple community sources
- @laravel/echo-vue composable details: MEDIUM -- Package exists and is documented, but presence channel member tracking composable needs validation
- Audio implementation: MEDIUM -- Browser autoplay policies are well-known, but the exact unlock pattern needs testing

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable ecosystem, monthly validity)
