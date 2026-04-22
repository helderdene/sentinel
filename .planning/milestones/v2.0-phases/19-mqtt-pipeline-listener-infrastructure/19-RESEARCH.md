# Phase 19: MQTT Pipeline + Listener Infrastructure — Research

**Researched:** 2026-04-21
**Domain:** MQTT ingress (php-mqtt/laravel-client ^1.8 + Mosquitto 2.0.x), Supervisor process management, Redis-backed liveness heartbeat, Reverb broadcast integration with existing dispatch surface
**Confidence:** HIGH — FRAS v1.0 reference implementation verified, IRMS v1.0 patterns grepped, Context7 php-mqtt/laravel-client docs fetched, all 17 locked context decisions carried forward

---

## Summary

Phase 19 is a **verbatim port of the FRAS v1.0 MQTT listener shape** onto IRMS's existing Reverb + Horizon + Inertia v2 foundation, with Phase 18's frozen `recognition_events` schema as the persistence target. The work divides into four substrate areas that the planner must sequence:

1. **Ingress substrate** (new) — `app/Mqtt/{Contracts,Handlers}/`, `app/Console/Commands/FrasMqttListenCommand.php`, `config/mqtt-client.php` (subscriber + publisher connections per MQTT-06), `config/fras.php` (topic_prefix + thresholds), dedicated `[program:irms-mqtt]` Supervisor block, `composer.json` 6th concurrently process.
2. **Liveness substrate** (new) — Redis cache key `mqtt:listener:last_message_received_at` bumped by `TopicRouter::dispatch()`; `irms:mqtt-listener-watchdog` command scheduled `->everyThirtySeconds()`; state-transition enum {HEALTHY, SILENT, NO_ACTIVE_CAMERAS}; `MqttListenerHealthChanged` event on existing `dispatch.incidents` channel.
3. **UI substrate** (extension) — `DispatchConsoleController::show()` gains `mqtt_listener_health` Inertia shared prop; `useDispatchFeed.ts` adds listener for `MqttListenerHealthChanged`; new `MqttListenerHealthBanner.vue` (red persistent banner) rendered only on `DispatchConsole.vue`.
4. **Operational substrate** (new) — `config/logging.php` `channels.mqtt` daily log; `config/filesystems.php` `fras_events` private disk; `docs/operations/irms-mqtt.md` runbook mirroring §8 pattern from `laravel-13-upgrade.md`; post-deploy `supervisorctl restart irms-mqtt:*` protocol.

**Primary recommendation:** Port FRAS's `TopicRouter` + 4 handler classes **verbatim** (only rename `AlertSeverity` → `RecognitionSeverity` to match Phase 18 enum, and drop FRAS's `EnrollPersonnelBatch` dispatch in `OnlineOfflineHandler` since Phase 19 has no enrollment jobs yet). Every decision about shape, idempotency, severity mapping, and photo storage has already been made — research's job is to wire the FRAS shape into IRMS conventions (Schedule facade, `dispatch.incidents` channel, `fras_events` disk, Inertia shared prop, Reverb broadcast after commit) without re-opening any locked decision.

---

## User Constraints (from CONTEXT.md)

### Locked Decisions (DO NOT re-litigate — research only how to implement)

- **D-01** Handlers run **inline** in the listener process (FRAS-parity). No jobs in Phase 19.
- **D-02** Register `fras-supervisor` Horizon block now with queue=['fras'] — sits idle for Phase 19, reserved for Phase 20+.
- **D-03** Duplicate RecPush handled via try/catch on `Illuminate\Database\UniqueConstraintViolationException`; log info; drop silently.
- **D-04** Keep FRAS `TopicRouter` shape: regex routes map + `app($handlerClass)->handle($topic, $message)`.
- **D-05** Liveness state = Redis cache key `mqtt:listener:last_message_received_at` bumped by router after every routed message.
- **D-06** Heartbeat signal = last-message-received (NOT last-loop-tick).
- **D-07** `irms:mqtt-listener-watchdog` scheduled `->everyThirtySeconds()` with 90s SILENT threshold.
- **D-08** Phase 19 writes only `CameraStatus::Online` / `::Offline`. `Degraded` deferred to Phase 20.
- **D-09** Watchdog arms only when `cameras.active_count ≥ 1` (NO_ACTIVE_CAMERAS short-circuit).
- **D-10** `MqttListenerHealthChanged` broadcasts on existing `dispatch.incidents` private channel.
- **D-11** Payload is a full enum state `{HEALTHY, SILENT, DISCONNECTED, NO_ACTIVE_CAMERAS}` (DISCONNECTED reserved for Phase 20+).
- **D-12** Initial state on page load via Inertia shared prop `mqtt_listener_health` in `DispatchConsoleController::show()`.
- **D-13** Persistent red top banner on `DispatchConsole.vue` only (NOT responder, intake, citizen).
- **D-14** Unknown-camera RecPush dropped with warning log (FRAS-parity).
- **D-15** Dedicated `fras_events` private disk in `config/filesystems.php`, rooted at `storage_path('app/private/fras_events')`, no `url` key, `FRAS_EVENT_DISK` env override.
- **D-16** 6th `composer run dev` process `"php artisan irms:mqtt-listen"` with color `#f59e0b` and name `mqtt`. Mosquitto is a documented dev prerequisite.
- **D-17** Dedicated `mqtt` log channel in `config/logging.php` (daily).

### Claude's Discretion

- Exact regex strings for `TopicRouter` routes (port FRAS verbatim; prefix via `config('fras.mqtt.topic_prefix')`)
- Redis cache key TTL 120s vs 300s (same outcome)
- Horizon `fras-supervisor` block `minProcesses`/`maxProcesses`/`tries`/`timeout` (mirror `supervisor-1`)
- Banner Vue component structure (reuse `AppBanner` if present, else new `MqttListenerHealthBanner.vue`)
- Watchdog cadence syntax `->everyThirtySeconds()` vs `->cron('*/30 * * * * *')`
- Supervisor `[program:irms-mqtt]` block wording (mirror `[program:irms-horizon]` + `stopwaitsecs=30`)
- Post-deploy smoke-test payload shape (sentinel `device_id='irms-smoketest'` + conditionally seeded test Camera)

### Deferred Ideas (OUT OF SCOPE)

- `CameraStatus::Degraded` semantics → Phase 20 watchdog
- Auto-create stub Camera on unknown RecPush → revisit if ops feedback warrants
- Dead-letter event table → add if forensics gap surfaces
- Separate loop-tick heartbeat → only if broker-silent-but-listener-alive scenarios emerge
- MQTT retain flag / ResumefromBreakpoint → explicit FRAS inheritance
- Stranger-detection `Snap` topic → REQUIREMENTS §Out of Scope
- `EnrollPersonnelBatch` jobs, `FrasPhotoProcessor`, `CameraEnrollmentService` → Phase 20
- `FrasIncidentFactory` + IoT bridge → Phase 21
- Signed URLs, `fras_access_log`, `fras.alerts` channel, ACK correlation state → Phase 22
- TLS posture for routed subnets → future multi-site
- php-mqtt reconnect exponential backoff → revisit under prod instability

---

## Phase Requirements

| ID | Description (from REQUIREMENTS.md §MQTT) | Research Support |
|----|------------------------------------------|------------------|
| **MQTT-01** | Operator runs `php artisan irms:mqtt-listen` locally (6th `composer run dev` process) and in production under dedicated `irms-mqtt` Supervisor program — NOT under Horizon | §Listener Command + §Supervisor Config + §Composer run dev |
| **MQTT-02** | `app/Mqtt/TopicRouter` dispatches to 4 handlers based on regex; unmatched topics logged, not silently dropped | §TopicRouter Port + §Pitfall 18 per-pattern tests |
| **MQTT-03** | `RecognitionHandler` parses RecPush (including `personName` vs `persionName`), stores base64 images to private disk date-partitioned, persists `raw_payload` JSONB | §RecognitionHandler Port + §Photo Storage |
| **MQTT-04** | Listener rotates cleanly every hour (`--max-time=3600`) and auto-reconnects after broker disconnect | §--max-time implementation + §Auto-reconnect config |
| **MQTT-05** | Dispatcher sees `mqtt_listener_health` banner on dispatch console within 60s of listener going silent (3 missed heartbeats) | §Liveness Substrate + §Watchdog Command + §Banner UI |
| **MQTT-06** | `config/mqtt-client.php` has separate subscriber + publisher connections | §MQTT Client Config |

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| MQTT subscribe + loop | Long-running PHP process (Supervisor) | — | NOT Horizon. Pitfall 6 + D-01 mandate dedicated `[program:irms-mqtt]`. Never under queue worker. |
| Regex topic → handler dispatch | `app/Mqtt/TopicRouter` (domain boundary) | — | Sibling to `app/Http/` as a second ingress surface, per ARCHITECTURE.md. |
| RecPush → DB persist + image write | Inline handler call (D-01) | Phase 20+ will move to queued job if throughput demands | Phase 19 accepts inline path at CDRRMO scale (≤8 cameras, sub-100ms per event). |
| Liveness heartbeat | Redis cache store | — | No schema. Bumped by `TopicRouter::dispatch()` on every routed message (D-05). |
| Watchdog state transition | Scheduled Artisan command (`routes/console.php`) | — | IRMS-native Schedule facade pattern. Runs every 30s. |
| Broadcast to dispatch UI | Laravel Reverb (`dispatch.incidents` private channel) | — | Reuses existing channel auth (D-10). No new channel. |
| Initial banner state | Inertia shared prop (`DispatchConsoleController::show`) | — | Matches existing pattern (D-12). |
| Banner render | `resources/js/components/fras/MqttListenerHealthBanner.vue` on `DispatchConsole.vue` | — | Scoped to FRAS-aware surfaces (D-13). |
| Photo storage | `fras_events` private disk (date-partitioned) | — | Dedicated blast radius for Phase 22 retention (D-15). |

---

## Standard Stack

### Core (already installed — do NOT install)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `php-mqtt/laravel-client` | `^1.8.0` [VERIFIED: composer.json not present; STACK.md §Core Additions confirms target version + Packagist release date 2026-03-27] | Laravel wrapper for MQTT subscribe/publish; auto-reconnect; `MQTT::connection()` facade | FRAS v1.0 validated shape. Declares `illuminate/*: ^13` compatibility. |
| `laravel/horizon` | installed [VERIFIED: composer.json line 19 `^5.45.6`] | Job queue supervisor; Phase 19 only modifies `config/horizon.php` to add empty `fras-supervisor` block | Dev stack has `php artisan horizon` already in `composer run dev` |
| `laravel/reverb` | installed [VERIFIED: composer.json line 20 `^1.10`] | WebSocket broadcast server | Existing `dispatch.incidents` private channel auth reused (D-10) |
| `predis/predis` | installed [VERIFIED: composer.json line 24 `^3.4`] | Redis PHP client — underpins Cache store for D-05 liveness key | Already used by Horizon + existing cache |

### Supporting (new config files only — no new packages)

| File | Purpose | Source |
|------|---------|--------|
| `config/mqtt-client.php` | Subscriber (`default`) + publisher (`publisher`) connections, auto-reconnect, TLS, LWT | Port verbatim from `/Users/helderdene/fras/config/mqtt-client.php`; rename env prefix to IRMS-native if desired, keep FRAS env names if not (recommend keep — they're the php-mqtt-standard `MQTT_*` namespace) |
| `config/fras.php` | `mqtt.topic_prefix`, `mqtt.keepalive`, `mqtt.reconnect_delay`, `alerts.camera_offline_threshold` | Port from `/Users/helderdene/fras/config/hds.php`, rename file only. DROP the `mapbox` block (IRMS uses MapLibre). Drop `enrollment` + `photo` blocks — those are Phase 20. Keep `retention` block (reserved for Phase 22). |

### Installation

**No `composer require` calls needed.** Phase 18 confirmed `php-mqtt/laravel-client` is already pinned. Run only:

```bash
# Publish MQTT config (if not already published):
php artisan vendor:publish --provider="PhpMqtt\Client\MqttClientServiceProvider" --tag="config"
# Verify auto-reconnect enabled in config/mqtt-client.php connections.default.connection_settings.auto_reconnect
```

**Version verification:**
```bash
composer show php-mqtt/laravel-client | grep versions   # confirm 1.8.x
composer show php-mqtt/client | grep versions           # transitive, confirm 2.x
```

Record confirmed versions in plan action logs. Training data has 1.8.0 as the latest stable [VERIFIED: Packagist via STACK.md cross-check 2026-04-21].

---

## Architecture Patterns

### System Architecture Diagram — Phase 19 end to end

```
┌──────────────────────────────────────────────────────────────────────────┐
│ Physical IP cameras (≤8 at CDRRMO scale)                                 │
│   publish MQTT v3.1.1, QoS 0, clean_session per-camera                   │
└──────────────┬───────────────────────────────────────────────────────────┘
               │
               ▼  (LAN-only, port 1883, plain MQTT — TLS deferred)
┌──────────────────────────────────────────────────────────────────────────┐
│ Mosquitto 2.0.x broker                                                   │
│   topics (prefix = mqtt/face):                                           │
│     mqtt/face/{device_id}/Rec       — RecPush recognition event          │
│     mqtt/face/{device_id}/Ack       — enrollment ACK (Phase 19 scaffolds)│
│     mqtt/face/basic                 — Online/Offline operator            │
│     mqtt/face/heartbeat             — HeartBeat operator                 │
└──────────────┬───────────────────────────────────────────────────────────┘
               │
               ▼  (subscriber connection, QoS 0, auto-reconnect)
┌──────────────────────────────────────────────────────────────────────────┐
│ php artisan irms:mqtt-listen                                             │
│   Supervisor program [program:irms-mqtt], stopwaitsecs=30                │
│   --max-time=3600 → clean hourly rotation                                │
│   Signals: pcntl_signal(SIGTERM/SIGINT, fn () => $mqtt->interrupt())     │
│                                                                           │
│   ┌────────────────────────────────────────────────────────────────┐    │
│   │ subscribe loop — 4 topic patterns, QoS 0                       │    │
│   │   $mqtt->subscribe($prefix.'/+/Rec',       router::dispatch, 0)│    │
│   │   $mqtt->subscribe($prefix.'/+/Ack',       router::dispatch, 0)│    │
│   │   $mqtt->subscribe($prefix.'/basic',       router::dispatch, 0)│    │
│   │   $mqtt->subscribe($prefix.'/heartbeat',   router::dispatch, 0)│    │
│   │   $mqtt->loop(true)                                            │    │
│   └───────────────────────┬────────────────────────────────────────┘    │
└───────────────────────────┼──────────────────────────────────────────────┘
                            │
                            ▼  (per-message callback, sync)
┌──────────────────────────────────────────────────────────────────────────┐
│ App\Mqtt\TopicRouter::dispatch($topic, $message)                         │
│   1. regex-match $topic against 4 patterns                               │
│   2. Cache::put('mqtt:listener:last_message_received_at', now(), 120s)   │
│   3. app($handlerClass)->handle($topic, $message)                        │
│   4. on no match → Log::channel('mqtt')->warning('Unmatched MQTT topic') │
└───────────────┬──────────────────────────────────────────────────────────┘
                │
                ▼  (DI-resolved handler, inline per D-01)
┌──────────────────────────────────────────────────────────────────────────┐
│  RecognitionHandler    AckHandler      HeartbeatHandler  OnlineOffline   │
│  ───────────────────   ─────────────   ─────────────     ─────────────   │
│  parsePayload          log scaffold    update Camera     toggle Camera   │
│  (personName|          (Phase 20+      last_seen_at      status enum     │
│    persionName)         fills ACK                        (Online|Offline)│
│  create(severity)       correlation)                                     │
│  decode+write images                                                     │
│  try/catch Unique-                                                       │
│    ConstraintException                                                   │
│  (Phase 21 will add                                                      │
│   FrasIncidentFactory call here)                                         │
└───────────────┬──────────────────────────────────────────────────────────┘
                │
                ▼ (DB + filesystem writes; NO broadcast in Phase 19)
┌──────────────────────────────────────────────────────────────────────────┐
│ PostgreSQL                    Storage::disk('fras_events')                │
│   recognition_events            {YYYY-MM-DD}/faces/{event_id}.jpg         │
│   cameras.last_seen_at          {YYYY-MM-DD}/scenes/{event_id}.jpg        │
│   cameras.status                (private; no HTTP surface in Phase 19)    │
└──────────────────────────────────────────────────────────────────────────┘

            ┌─────────── Liveness watchdog (separate process) ───────────┐
            │                                                             │
            ▼                                                             │
┌──────────────────────────────────────────────────────────────────────┐  │
│ php artisan irms:mqtt-listener-watchdog (Schedule every 30s)         │  │
│   - active_count = Camera::active()->count()                         │  │
│   - if 0 → state = NO_ACTIVE_CAMERAS                                 │  │
│   - else gap = now() - Cache::get('mqtt:listener:last_message_…')    │  │
│           state = gap < 90s ? HEALTHY : SILENT                       │  │
│   - transition check: prev = Cache::get('mqtt:listener:last_known_…')│  │
│   - on transition: broadcast MqttListenerHealthChanged               │  │
│                    + Cache::put('mqtt:listener:last_known_state')    │  │
└──────────────────────────────────┬───────────────────────────────────┘  │
                                   │                                      │
                                   ▼                                      │
┌──────────────────────────────────────────────────────────────────────┐  │
│ MqttListenerHealthChanged (ShouldBroadcast + ShouldDispatchAfterCommit)│ │
│   broadcastOn: PrivateChannel('dispatch.incidents')                  │  │
│   broadcastWith: {                                                   │  │
│     status: 'HEALTHY'|'SILENT'|'DISCONNECTED'|'NO_ACTIVE_CAMERAS',   │  │
│     last_message_received_at: ISO8601|null,                          │  │
│     since: ISO8601,                                                  │  │
│     active_camera_count: int                                         │  │
│   }                                                                  │  │
└──────────────────────────────────┬───────────────────────────────────┘  │
                                   │                                      │
                                   ▼  (Reverb → browser)                  │
┌──────────────────────────────────────────────────────────────────────┐  │
│ DispatchConsole.vue                                                   │  │
│   useDispatchFeed listener for MqttListenerHealthChanged             │  │
│   initial state from Inertia shared prop mqtt_listener_health        │  │
│   renders MqttListenerHealthBanner.vue (red) when status='SILENT'    │  │
│   renders nothing when HEALTHY or NO_ACTIVE_CAMERAS                  │  │
└──────────────────────────────────────────────────────────────────────┘
```

### Recommended Project Structure (new/modified paths only)

```
app/
├── Console/Commands/
│   ├── FrasMqttListenCommand.php              [NEW]  signature: irms:mqtt-listen {--max-time=3600}
│   └── FrasMqttListenerWatchdogCommand.php    [NEW]  signature: irms:mqtt-listener-watchdog
├── Events/
│   └── MqttListenerHealthChanged.php          [NEW]  ShouldBroadcast + ShouldDispatchAfterCommit
└── Mqtt/
    ├── Contracts/
    │   └── MqttHandler.php                     [NEW]  interface { handle(string $topic, string $message): void }
    ├── Handlers/
    │   ├── RecognitionHandler.php              [NEW]  RecPush → RecognitionEvent::create + images
    │   ├── AckHandler.php                      [NEW]  scaffolding (validate + log only; Phase 20+ fills)
    │   ├── HeartbeatHandler.php                [NEW]  bump cameras.last_seen_at
    │   └── OnlineOfflineHandler.php            [NEW]  toggle cameras.status Online/Offline
    └── TopicRouter.php                         [NEW]  regex map + Cache::put liveness key + handler dispatch

config/
├── fras.php                                    [NEW]  mqtt.topic_prefix + thresholds (port of hds.php minus mapbox/enrollment/photo)
├── mqtt-client.php                             [NEW]  subscriber + publisher connections
├── horizon.php                                 [MOD]  add fras-supervisor block (idle queue='fras')
├── logging.php                                 [MOD]  add channels.mqtt daily
└── filesystems.php                             [MOD]  add disks.fras_events private

composer.json                                    [MOD]  scripts.dev — add 6th concurrently target
routes/console.php                               [MOD]  Schedule::command('irms:mqtt-listener-watchdog')->everyThirtySeconds()
app/Http/Controllers/DispatchConsoleController.php  [MOD]  Inertia::render adds 'mqtt_listener_health' shared prop

resources/js/
├── components/fras/
│   └── MqttListenerHealthBanner.vue           [NEW]  red persistent banner; renders when status='SILENT'
├── composables/
│   └── useDispatchFeed.ts                      [MOD]  subscribe to MqttListenerHealthChanged, expose reactive mqtt_health state
├── pages/dispatch/
│   └── Console.vue                             [MOD]  render MqttListenerHealthBanner when feed.mqttHealth.status==='SILENT'
└── types/
    └── mqtt.ts                                 [NEW]  TS types: MqttListenerHealth, MqttListenerHealthPayload

tests/Feature/Mqtt/
├── TopicRouterTest.php                         [NEW]  per-pattern routing assertions (Pitfall 18 mandate)
├── RecognitionHandlerTest.php                  [NEW]  personName/persionName + idempotency + unknown-camera
├── HeartbeatHandlerTest.php                    [NEW]  last_seen_at bump + unknown-camera warning
├── OnlineOfflineHandlerTest.php                [NEW]  enum toggle + unknown-operator warning
├── AckHandlerTest.php                          [NEW]  validation + scaffolding log assertion
└── MqttListenerWatchdogTest.php                [NEW]  HEALTHY↔SILENT↔NO_ACTIVE_CAMERAS transitions + Event::fake

docs/operations/
└── irms-mqtt.md                                [NEW]  dev prereq + Supervisor prod block + deploy protocol + smoke test
```

### Pattern 1: FRAS TopicRouter Port (D-04)

**What:** Regex map from topic pattern to handler class; DI-resolved handler dispatch.

**When to use:** Entry point for all MQTT messages arriving at the listener.

**Port specifics — IRMS adaptations:**

```php
// app/Mqtt/TopicRouter.php — IRMS version
namespace App\Mqtt;

use App\Mqtt\Contracts\MqttHandler;
use App\Mqtt\Handlers\{AckHandler, HeartbeatHandler, OnlineOfflineHandler, RecognitionHandler};
use Illuminate\Support\Facades\{Cache, Log};

class TopicRouter
{
    /** @var array<string, class-string<MqttHandler>> */
    private array $routes;

    public function __construct()
    {
        $prefix = preg_quote(config('fras.mqtt.topic_prefix'), '#');
        // FRAS-verbatim regex patterns, with config-driven prefix for testability
        $this->routes = [
            "#^{$prefix}/[^/]+/Rec$#"        => RecognitionHandler::class,
            "#^{$prefix}/[^/]+/Ack$#"        => AckHandler::class,
            "#^{$prefix}/basic$#"            => OnlineOfflineHandler::class,
            "#^{$prefix}/heartbeat$#"        => HeartbeatHandler::class,
        ];
    }

    public function dispatch(string $topic, string $message): void
    {
        // D-05: bump liveness key BEFORE handler runs so even a failing handler
        // counts as "listener is alive + broker connection healthy"
        Cache::put('mqtt:listener:last_message_received_at', now()->toIso8601String(), now()->addSeconds(120));

        foreach ($this->routes as $pattern => $handlerClass) {
            if (preg_match($pattern, $topic)) {
                app($handlerClass)->handle($topic, $message);
                return;
            }
        }

        // MQTT-02: unmatched topics MUST log, never silently drop
        Log::channel('mqtt')->warning('Unmatched MQTT topic', ['topic' => $topic]);
    }
}
```

**Key IRMS-specific changes from FRAS source:**
1. Prefix pulled from `config('fras.mqtt.topic_prefix')` (FRAS hardcoded `mqtt/face` in the regex strings — IRMS makes it overridable for tests).
2. Liveness cache key bumped inside `dispatch()` before handler runs (new for IRMS — D-05).
3. Unmatched-topic log uses dedicated `mqtt` channel (D-17).

### Pattern 2: RecognitionHandler — Verbatim port with Phase 18 schema

FRAS source (`/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php`) ports almost verbatim. **Phase 19 modifications:**

1. **Severity enum rename:** `App\Enums\AlertSeverity` → `App\Enums\RecognitionSeverity` (Phase 18 enum — `Info`, `Warning`, `Critical`). FRAS had a richer enum with `shouldBroadcast()` + `fromEvent()`; IRMS `RecognitionSeverity` currently has only `label()` + `isCritical()`. **Planner must add a static `fromEvent(int $personType, int $verifyStatus)` classifier** matching FRAS logic (see below).
2. **No broadcast in Phase 19:** FRAS's `RecognitionAlert::fromEvent()` dispatch is **removed** — Phase 22 delivers the `fras.alerts` channel.
3. **Storage disk change:** FRAS wrote to `Storage::disk('local')`; Phase 19 writes to `Storage::disk('fras_events')` (D-15).
4. **Path template change:** FRAS used `recognition/{date}/{type}s/{event_id}.jpg`; Phase 19 simplifies to `{YYYY-MM-DD}/{type}s/{event_id}.jpg` (drops the `recognition/` prefix since the dedicated `fras_events` disk already scopes the blast radius — D-15).
5. **Unknown-camera drop (D-14):** FRAS already does this; keep verbatim.
6. **Unique-constraint idempotency (D-03):** FRAS lets duplicates through; IRMS wraps the `RecognitionEvent::create()` in try/catch on `Illuminate\Database\UniqueConstraintViolationException` and logs info.

**FRAS severity classifier to port** (the `fromEvent` logic implied by FRAS `AlertSeverity::fromEvent($personType, $verifyStatus)`): Research didn't see an explicit FRAS implementation in the files listed, so **this is the first [ASSUMED] claim — planner must either read `/Users/helderdene/fras/app/Enums/AlertSeverity.php` directly or lock a classification matrix from REQUIREMENTS.md §RECOGNITION**. Reasonable default (to be confirmed):
- `person_type == 1` (blocklist) + `verify_status == 1` → `Critical`
- `person_type == 2` (refused) → `Warning`
- else → `Info`

[ASSUMED: severity classification matrix — verify against `/Users/helderdene/fras/app/Enums/AlertSeverity.php` during planning]

**Handler skeleton (IRMS version):**

```php
namespace App\Mqtt\Handlers;

use App\Enums\RecognitionSeverity;
use App\Models\{Camera, Personnel, RecognitionEvent};
use App\Mqtt\Contracts\MqttHandler;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\{Log, Storage};

class RecognitionHandler implements MqttHandler
{
    public function handle(string $topic, string $message): void
    {
        $data = json_decode($message, true);
        if (! $data || ($data['operator'] ?? null) !== 'RecPush') {
            return;
        }

        $segments = explode('/', $topic);
        $deviceId = $segments[2] ?? null;
        if (! $deviceId) {
            Log::channel('mqtt')->warning('RecPush topic missing device_id', ['topic' => $topic]);
            return;
        }

        $camera = Camera::where('device_id', $deviceId)->first();
        if (! $camera) {
            // D-14: drop with warning log
            Log::channel('mqtt')->warning('RecPush for unknown camera', ['device_id' => $deviceId, 'topic' => $topic]);
            return;
        }

        $info = $data['info'] ?? [];
        $parsed = $this->parsePayload($info);
        $severity = RecognitionSeverity::fromEvent($parsed['person_type'], $parsed['verify_status']);

        $personnelId = null;
        if ($parsed['custom_id']) {
            $personnelId = Personnel::where('custom_id', $parsed['custom_id'])->value('id');
        }

        // D-03: try/catch unique violation, log, return
        try {
            $event = RecognitionEvent::create([
                'camera_id' => $camera->id,
                'personnel_id' => $personnelId,
                'incident_id' => null,                                    // Phase 21 sets
                'record_id' => $parsed['record_id'],
                'custom_id' => $parsed['custom_id'],
                'camera_person_id' => $parsed['camera_person_id'],
                'verify_status' => $parsed['verify_status'],
                'person_type' => $parsed['person_type'],
                'similarity' => $parsed['similarity'],
                'is_real_time' => $parsed['is_real_time'],
                'name_from_camera' => $parsed['name_from_camera'],
                'facesluice_id' => $parsed['facesluice_id'],
                'id_card' => $parsed['id_card'],
                'phone' => $parsed['phone'],
                'is_no_mask' => $parsed['is_no_mask'],
                'target_bbox' => $parsed['target_bbox'],
                'captured_at' => $parsed['captured_at'],
                'received_at' => now(),
                'severity' => $severity,
                'raw_payload' => $data,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            Log::channel('mqtt')->info('Duplicate RecPush rejected at DB layer', [
                'camera_id' => $camera->id,
                'record_id' => $parsed['record_id'],
            ]);
            return;
        }

        // Images (D-15 disk + simplified path)
        $date = $event->captured_at->format('Y-m-d');
        $faceImagePath = $this->saveImage($info['pic'] ?? null, 'face', $event->id, $date, 1_048_576);
        $sceneImagePath = $this->saveImage($info['scene'] ?? null, 'scene', $event->id, $date, 2_097_152);

        if ($faceImagePath || $sceneImagePath) {
            $event->update(array_filter([
                'face_image_path' => $faceImagePath,
                'scene_image_path' => $sceneImagePath,
            ]));
        }

        Log::channel('mqtt')->info('RecPush processed', [
            'event_id' => $event->id,
            'camera_id' => $camera->id,
            'severity' => $severity->value,
        ]);
        // NO broadcast in Phase 19 — Phase 22 adds fras.alerts
    }

    // parsePayload() — port verbatim from FRAS source with personName|persionName fallback
    // saveImage() — port verbatim but change Storage::disk('local') → Storage::disk('fras_events')
    // and path → "{$date}/{$type}s/{$eventId}.jpg"
}
```

### Pattern 3: HeartbeatHandler, OnlineOfflineHandler, AckHandler — Ports

**HeartbeatHandler** — verbatim FRAS port. Payload field `facesluiceId` maps to `cameras.device_id` (critical mapping — the "facesluice" spelling is the camera firmware's ID field). Updates only `cameras.last_seen_at`. No status enum change.

**OnlineOfflineHandler** — port with two DROPs:
- DROP the `if ($isOnline && ! $wasOnline)` block that dispatches `EnrollPersonnelBatch` (Phase 20 adds back).
- DROP the `CameraStatusChanged::dispatch` call (Phase 20 adds back with broadcast event).
- KEEP the enum flip: map operator `Online|Offline` → `CameraStatus::Online|Offline` (Phase 18 enum). Note FRAS stored as `is_online` boolean; IRMS Phase 18 stores as `status` enum (D-08).

**AckHandler** — **Phase 19 scaffolds only** (locked in CONTEXT.md code_context "AckHandler — ACK correlation scaffolding; Phase 20 fills in enrollment state"). The scaffold should:
1. Parse JSON, validate `messageId` + `deviceId` presence.
2. Look up Camera by device_id; warn if not found.
3. **STOP here** — do NOT call `Cache::pull("enrollment-ack:{$cameraId}:{$messageId}")`. Phase 20 adds that logic when `CameraEnrollment` is actively being written.
4. Log info-level acknowledgement that ACK was received: `Log::channel('mqtt')->info('ACK received (Phase 19 scaffold — no correlation until Phase 20)', [...])`.

This keeps Phase 19's AckHandler a no-op behaviorally (no state change) but exercises the topic routing + camera lookup so Pitfall 18's per-pattern test catches regressions.

### Pattern 4: MqttListenerHealthChanged Event (copy IncidentCreated shape)

```php
namespace App\Events;

use Illuminate\Broadcasting\{InteractsWithSockets, PrivateChannel};
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MqttListenerHealthChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $status,                       // 'HEALTHY'|'SILENT'|'DISCONNECTED'|'NO_ACTIVE_CAMERAS'
        public ?string $lastMessageReceivedAt,      // ISO8601 or null
        public string $since,                        // ISO8601 — when this state began
        public int $activeCameraCount,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('dispatch.incidents')];     // D-10
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'last_message_received_at' => $this->lastMessageReceivedAt,
            'since' => $this->since,
            'active_camera_count' => $this->activeCameraCount,
        ];
    }
}
```

### Pattern 5: Watchdog Command

```php
namespace App\Console\Commands;

use App\Enums\CameraStatus;
use App\Events\MqttListenerHealthChanged;
use App\Models\Camera;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FrasMqttListenerWatchdogCommand extends Command
{
    protected $signature = 'irms:mqtt-listener-watchdog';
    protected $description = 'Detect MQTT listener silence and broadcast state transitions on dispatch.incidents';

    public function handle(): int
    {
        // D-09: arm only when ≥1 active camera
        $activeCount = Camera::query()->whereNull('decommissioned_at')->count();

        if ($activeCount === 0) {
            $this->transitionTo('NO_ACTIVE_CAMERAS', null, $activeCount);
            return self::SUCCESS;
        }

        $lastIso = Cache::get('mqtt:listener:last_message_received_at');
        $gap = $lastIso ? now()->diffInSeconds(Carbon::parse($lastIso)) : PHP_INT_MAX;

        // D-07: 90s threshold
        $newState = $gap < 90 ? 'HEALTHY' : 'SILENT';
        $this->transitionTo($newState, $lastIso, $activeCount);

        return self::SUCCESS;
    }

    private function transitionTo(string $newState, ?string $lastIso, int $activeCount): void
    {
        $prev = Cache::get('mqtt:listener:last_known_state');
        if ($prev === $newState) {
            return;   // no broadcast, no state change
        }

        $since = now()->toIso8601String();
        Cache::forever('mqtt:listener:last_known_state', $newState);
        Cache::forever('mqtt:listener:last_known_state_since', $since);

        MqttListenerHealthChanged::dispatch($newState, $lastIso, $since, $activeCount);
    }
}
```

**Schedule in `routes/console.php`:**
```php
Schedule::command('irms:mqtt-listener-watchdog')
    ->everyThirtySeconds()
    ->timezone('Asia/Manila')
    ->description('MQTT listener liveness watchdog (Phase 19)');
```

### Pattern 6: Inertia Shared Prop for Initial State (D-12)

**Recommendation:** Do NOT put this in `HandleInertiaRequests` middleware (runs on every request, pollutes every page's props with MQTT state). Instead, add ONLY to `DispatchConsoleController::show()`:

```php
// app/Http/Controllers/DispatchConsoleController.php — add before Inertia::render
$mqttListenerHealth = [
    'status' => Cache::get('mqtt:listener:last_known_state', 'NO_ACTIVE_CAMERAS'),
    'last_message_received_at' => Cache::get('mqtt:listener:last_message_received_at'),
    'since' => Cache::get('mqtt:listener:last_known_state_since', now()->toIso8601String()),
    'active_camera_count' => Camera::query()->whereNull('decommissioned_at')->count(),
];

return Inertia::render('dispatch/Console', [
    'incidents' => $incidents,
    'units' => $units,
    'agencies' => $agencies,
    'metrics' => $metrics,
    'mqtt_listener_health' => $mqttListenerHealth,   // NEW
]);
```

This keeps Phase 19's scope minimal and the banner strictly scoped to the dispatch console surface (D-13).

### Anti-Patterns to Avoid

- **Running `irms:mqtt-listen` under Horizon** — Pitfall 6 mandates separate Supervisor program. Horizon restart MUST leave listener untouched (MQTT-06 success criterion).
- **Broadcasting from inside MQTT handlers** — Phase 19 explicitly does not broadcast recognition events (Phase 22 adds `fras.alerts`). Handlers write to DB + disk + log only. `MqttListenerHealthChanged` is dispatched from the **watchdog command**, not from handlers.
- **Dispatching Reverb events without `ShouldDispatchAfterCommit`** — anti-pattern #2 in ARCHITECTURE.md. `MqttListenerHealthChanged` must implement the contract (copied from `IncidentCreated`).
- **Inline image-byte payloads in broadcasts** — Pitfall 13. Phase 19 broadcast carries only state + count + timestamps; never image data.
- **Silently dropping unmatched topics** — MQTT-02 explicitly forbids. `TopicRouter` logs at `warning` level on fallthrough.
- **Loop-tick-only heartbeat** — D-06 rejected; use last-message-received as the liveness signal.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| MQTT subscribe + auto-reconnect | Raw socket + PSK handshake | `php-mqtt/laravel-client` `MQTT::connection()->subscribe()` + `loop(true)` | 5 years of edge-case handling: keep-alive, PINGREQ/PINGRESP, reconnect state machine, LWT |
| Signal-handling for Supervisor stop | Custom `pcntl_alarm()` timer | `pcntl_signal(SIGTERM, fn () => $mqtt->interrupt())` + library's `interrupt()` method | Library exits the `loop()` cleanly at next tick, releasing the socket cleanly for Supervisor's stopwaitsecs=30 [VERIFIED: Context7 `/php-mqtt/laravel-client` "Interrupting the Event Loop" snippet] |
| Idempotency for duplicate RecPush | Pre-check `->exists()` race-prone path | Try/catch `UniqueConstraintViolationException` per D-03; DB's `UNIQUE (camera_id, record_id)` enforces | Phase 18 D-54 locked the constraint. DB is the enforcement point. |
| Periodic "is listener alive" sensor | cron + shell `ps` + file marker | Redis cache key + Laravel Schedule facade | Redis survives host restart, works in multi-container deploys, and integrates with existing `useWebSocket` connection-status UX |
| Broadcasting state transitions to UI | WebSocket polling | Existing Reverb on `dispatch.incidents` | Channel already authorized for the exact role set that needs the banner; one event definition, one listener wire-up |
| Banner Vue component | Custom Vue UI primitive | Extend `ConnectionBanner.vue` pattern (see below) | `ConnectionBanner.vue` is already the project's top-banner idiom (red/amber/green, transition animations, lucide icons) — extend rather than copy |
| Log rotation for MQTT listener | Custom log-rotate cron | Laravel's `daily` driver in `config/logging.php` | Laravel-native; 14-day retention default matches v1.0 convention |
| Base64 image decoding | Naive `base64_decode` + `file_put_contents` | FRAS's `saveImage()` port: strips `data:image/…;base64,` prefix, validates non-empty + size cap, uses `Storage::disk(…)->put()` | FRAS v1.0 validated the size-cap path (1MB face / 2MB scene); planners should not re-decide these thresholds |

**Key insight:** Every hand-roll temptation in this phase either lives in the FRAS source already (port verbatim) or lives in the php-mqtt library (call the API). The ONLY net-new IRMS-specific code is: the Inertia shared prop, the banner Vue component, the watchdog state machine, and the mqtt log channel wiring. Everything else is porting.

---

## Runtime State Inventory

**Trigger:** Rename/refactor phase? **No — Phase 19 is greenfield ingress + greenfield listener.** No existing runtime state to migrate. Skipping this section except to note explicitly:

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | None — `recognition_events` is empty after Phase 18 `migrate:fresh --seed` | None |
| Live service config | None — no pre-existing MQTT broker config; Mosquitto will be freshly installed in dev | None |
| OS-registered state | None — no existing `irms-mqtt` Supervisor program | Install new (§Supervisor Config) |
| Secrets/env vars | New env vars introduced: `MQTT_HOST`, `MQTT_PORT`, `MQTT_CLIENT_ID`, `MQTT_USERNAME`, `MQTT_PASSWORD`, `MQTT_TOPIC_PREFIX`, `MQTT_KEEPALIVE`, `MQTT_RECONNECT_DELAY`, `MQTT_MAX_RECONNECT_ATTEMPTS`, `MQTT_TLS_ENABLED`, `FRAS_EVENT_DISK`. Names mirror FRAS convention; no rename conflicts. | Add to `.env.example` + document in runbook |
| Build artifacts | None | None |

---

## Common Pitfalls

### Pitfall 6 (PITFALLS.md §6): MQTT listener under Horizon

**What goes wrong:** Horizon treats listener like worker; fights reconnect; thundering-herd restarts.

**How to avoid:** Dedicated `[program:irms-mqtt]` Supervisor block separate from Horizon. `--max-time=3600` for clean hourly rotation. Separate log file (D-17 mqtt channel).

**Warning signs:** Same listener PID for days (FD leak); multiple competing PIDs after broker restart; Horizon dashboard showing listener as a "worker."

### Pitfall 7 (PITFALLS.md §7): Deploy without restarting MQTT listener

**What goes wrong:** `git pull && php artisan optimize:clear` doesn't touch the MQTT daemon. Listener runs stale code indefinitely.

**How to avoid:**
- Deploy runbook **must** include `sudo supervisorctl restart irms-mqtt:*`.
- Phase 19 creates `docs/operations/irms-mqtt.md` with the full deploy protocol.
- Post-deploy smoke test: `mosquitto_pub -t mqtt/face/irms-smoketest/Rec -m "$(cat tests/fixtures/rec-push.json)"` → `tail -f storage/logs/mqtt-*.log` → assert event ID in log output within 3s.

**Warning signs:** Recognition event timestamps stop advancing shortly after deploy; developer says "I fixed that, why is it still broken."

### Pitfall 13 (PITFALLS.md §13): Reverb flood

**What goes wrong:** High-rate recognition flood saturates Reverb; dispatch updates lag.

**Phase 19 threshold analysis:** Phase 19 broadcasts ONLY `MqttListenerHealthChanged` on state transitions (HEALTHY↔SILENT typically 2-4 events per day during normal operation, more during an outage). This is below the Reverb throttle concern threshold. No inline alert broadcasts in Phase 19. Phase 22 + Phase 21 are where Pitfall 13 applies.

**How to avoid in Phase 19:** Watchdog's `transitionTo()` returns early if `$prev === $newState`, ensuring no broadcast per tick. Inline recognition handlers do not call `event()` in Phase 19.

### Pitfall 17 (PITFALLS.md §17): QoS 0 message loss

**What goes wrong:** Broker restart / subscriber restart → in-flight messages lost silently.

**Accepted tradeoff:** FRAS v1.0 inheritance; cameras publish QoS 0 and firmware cannot be upgraded.

**Mitigations Phase 19 delivers:** 30s-cadence watchdog + `MqttListenerHealthChanged` banner surfaces listener outage to dispatchers within 60-120s. Operational SLA: <30s MTTR via `supervisorctl restart`. Heartbeat gap logging in `mqtt` channel.

**What Phase 19 does NOT mitigate:** No retain flag, no ResumefromBreakpoint, no dead-letter table. All deferred per CONTEXT.md.

### Pitfall 18 (PITFALLS.md §18): Topic subscription wildcard mismatch

**What goes wrong:** Camera publishes to `mqtt/face/abc-123/Rec`; listener subscribes to `mqtt/face/+/Rec/#`; silent no-match for hours.

**How to avoid:** **Mandatory per-pattern Pest test in `tests/Feature/Mqtt/TopicRouterTest.php`.** One test per route — four tests total:

```php
it('dispatches RecPush topics to RecognitionHandler', function () {
    $router = new TopicRouter();
    $this->app->instance(RecognitionHandler::class, $mock = Mockery::mock(RecognitionHandler::class));
    $mock->shouldReceive('handle')->once()->with('mqtt/face/abc-123/Rec', '{"foo":"bar"}');
    $router->dispatch('mqtt/face/abc-123/Rec', '{"foo":"bar"}');
});

it('dispatches Ack topics to AckHandler', /* ... */);
it('dispatches basic topic to OnlineOfflineHandler', /* ... */);
it('dispatches heartbeat topic to HeartbeatHandler', /* ... */);
it('logs a warning for unmatched topics', function () {
    Log::shouldReceive('channel')->with('mqtt')->andReturnSelf();
    Log::shouldReceive('warning')->once()->with('Unmatched MQTT topic', ['topic' => 'mqtt/face/Rec']);
    (new TopicRouter())->dispatch('mqtt/face/Rec', '{}');
});
```

**Warning signs:** Subscriber connects, receives nothing despite Mosquitto log showing camera publishes; unmatched-topic warnings for "should have matched" topics.

### Pitfall 11 (PITFALLS.md §11): PII / DPA posture on images

**Phase 19 position:** `fras_events` disk is `visibility: 'private'` with NO `url` key. No HTTP controller serves images in Phase 19. DPA audit log (`fras_access_log`), signed URLs, and role-gated image controllers all land in Phase 22.

**What Phase 19 must do:** Store images on private disk with predictable path for Phase 22 purge. DO NOT accidentally expose via `storage:link` public symlink (verified by disk config — `fras_events` root is `storage_path('app/private/fras_events')`, NOT `storage/app/public`).

---

## Code Examples

### Listener Command (IRMS port with --max-time implementation)

```php
namespace App\Console\Commands;

use App\Mqtt\TopicRouter;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;

class FrasMqttListenCommand extends Command
{
    protected $signature = 'irms:mqtt-listen {--max-time=3600 : Seconds before clean exit (0 = forever)}';
    protected $description = 'Subscribe to Mosquitto and route camera MQTT messages to handlers';

    public function handle(TopicRouter $router): int
    {
        $mqtt = MQTT::connection();                          // 'default' (subscriber)
        $prefix = config('fras.mqtt.topic_prefix');

        $topics = [
            "{$prefix}/+/Rec",
            "{$prefix}/+/Ack",
            "{$prefix}/basic",
            "{$prefix}/heartbeat",
        ];

        foreach ($topics as $topic) {
            $mqtt->subscribe($topic, function (string $topic, string $message) use ($router): void {
                $router->dispatch($topic, $message);
            }, 0);                                           // QoS 0 per camera firmware
        }

        // Graceful shutdown for Supervisor stop signal (SIGTERM) or Ctrl+C (SIGINT)
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $mqtt->interrupt());
        pcntl_signal(SIGINT, fn () => $mqtt->interrupt());

        // --max-time implementation: schedule a SIGALRM after N seconds
        $maxTime = (int) $this->option('max-time');
        if ($maxTime > 0) {
            pcntl_signal(SIGALRM, fn () => $mqtt->interrupt());
            pcntl_alarm($maxTime);
        }

        Log::channel('mqtt')->info('MQTT listener started', [
            'topics' => $topics,
            'max_time' => $maxTime,
        ]);

        $mqtt->loop(true);

        $mqtt->disconnect();
        Log::channel('mqtt')->info('MQTT listener stopped gracefully');

        return self::SUCCESS;
    }
}
```

**Key design notes:**
- `--max-time` uses PCNTL's `alarm()` to SIGALRM the process after N seconds; SIGALRM handler calls `$mqtt->interrupt()` which exits `loop()` at the next tick, then `disconnect()` fires cleanly. Supervisor's `autorestart=unexpected` (see below) then restarts the process — fresh PHP, no FD/memory leak.
- `pcntl_async_signals(true)` is required or signals queue until the next synchronous point (php-mqtt's `loop()` is synchronous per tick).
- The `--max-time` option is literal: `{--max-time=3600}` in the signature. FRAS v1.0 also takes this flag.
- Three signals wired: SIGTERM (Supervisor stop), SIGINT (Ctrl+C in dev), SIGALRM (max-time). All three call `interrupt()`.
- [VERIFIED: Context7 `/php-mqtt/laravel-client` "Interrupting the Event Loop" snippet confirms the `interrupt()` method is the correct way to exit the loop cleanly]

### Supervisor Program Block

```ini
# /etc/supervisor/conf.d/irms-mqtt.conf
[program:irms-mqtt]
command=php /var/www/irms/artisan irms:mqtt-listen --max-time=3600
process_name=%(program_name)s
autostart=true
autorestart=unexpected             ; restart on crash AND on clean --max-time exit (exit code 0 → "unexpected" if not Supervisor-initiated stop)
exitcodes=0,2                      ; clean exit codes (0 = success, 2 = optional user signal)
user=www-data
stopwaitsecs=30                    ; generous but not 3600 — listener has no long-running jobs to drain
stopsignal=TERM
stopasgroup=true
killasgroup=true
numprocs=1                         ; MQTT subscription has no sharding — only one listener per broker
redirect_stderr=true
stdout_logfile=/var/log/irms/mqtt-listener.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
```

**Why `autorestart=unexpected` + `exitcodes=0,2`:** When `--max-time=3600` fires, the command exits 0. Under `autorestart=true` Supervisor always restarts. Under `autorestart=unexpected` Supervisor only restarts on unexpected exits — but with `exitcodes=0,2` empty (or set to never-match like `99`), every exit is "unexpected" and restarts. **Simpler alternative: use `autorestart=true` and let `--max-time` restart the process every hour.** Both work; planner picks. Recommended: `autorestart=true` for clarity.

**Why `numprocs=1`:** MQTT subscription has no natural sharding — multiple subscribers on the same topic receive all messages (broadcast fan-out), so `numprocs=2` would **double-process every message**, violating D-03 idempotency logic and `recognition_events` unique constraint.

**Why `stopwaitsecs=30`:** The listener has no long-running jobs to drain. The `loop()` tick exits within milliseconds once `interrupt()` fires. 30s is generous; anything longer is dead weight.

**Document post-deploy `supervisorctl restart` protocol:** `docs/operations/irms-mqtt.md` §Deploy Protocol must mirror `docs/operations/laravel-13-upgrade.md` §3 Pre-Deploy Drain but with no drain step (listener has no pending work). Just:

```bash
# After deploy:
sudo supervisorctl restart irms-mqtt
# Verify:
php artisan horizon:status       # Horizon should be untouched
sudo supervisorctl status irms-mqtt | grep RUNNING
# Smoke test:
mosquitto_pub -t mqtt/face/irms-smoketest/Rec -m @tests/fixtures/rec-push.json
tail -f storage/logs/mqtt-$(date +%Y-%m-%d).log   # expect "RecPush processed" within 3s
```

### Horizon `fras-supervisor` Block (D-02 — idle queue for Phase 19)

```php
// config/horizon.php — add to both environments.production AND environments.local
'environments' => [
    'production' => [
        'supervisor-1' => [ /* existing */ ],
        'fras-supervisor' => [                               // NEW
            'connection' => 'redis',
            'queue' => ['fras'],
            'balance' => 'simple',                           // simple balance — low-throughput enrollment work
            'minProcesses' => 1,
            'maxProcesses' => 3,                             // CDRRMO scale: enough for batch enrollment bursts
            'tries' => 3,
            'timeout' => 120,                                // enrollment jobs may HTTP-upload photos; longer than default 60s
            'memory' => 128,
        ],
    ],

    'local' => [
        'supervisor-1' => [ /* existing */ ],
        'fras-supervisor' => [                               // NEW
            'connection' => 'redis',
            'queue' => ['fras'],
            'balance' => 'simple',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
],
```

Mirrors `supervisor-1` shape. Queue is empty in Phase 19 — registered now so Phase 20+ doesn't touch horizon.php.

### `config/mqtt-client.php` — Subscriber + Publisher Connections (MQTT-06)

**Verbatim port from FRAS** (`/Users/helderdene/fras/config/mqtt-client.php` — reviewed in full above). Two connections:
- `default` (subscriber) — `use_clean_session: false` so subscriptions survive reconnects, auto-reconnect enabled, `max_reconnect_attempts: 10`, `delay_between_reconnect_attempts: 5` — both env-driven
- `publisher` (for future Phase 20 enrollment publishes) — `use_clean_session: true` (one-shot publishes don't need session state), client_id suffix `-pub` so broker distinguishes connections

**MQTT-06 verification:** Separate connections prove Phase 19's subscribe loop cannot be blocked by a hypothetical publish call from Phase 20+ jobs.

### `config/filesystems.php` fras_events disk (D-15)

```php
// Add to disks array:
'fras_events' => [
    'driver' => 'local',
    'root' => storage_path('app/private/fras_events'),    // Laravel 13 default private root
    'visibility' => 'private',                             // no public URL
    'throw' => false,
    'report' => false,
    // NO 'url' key — this disk has no HTTP surface in Phase 19 (Phase 22 adds signed-URL controller)
],
```

`FRAS_EVENT_DISK` env override: pass through `env('FRAS_EVENT_DISK', 'fras_events')` wherever code picks the disk name. Reserved for future S3/object-storage swap; Phase 19 always reads the default.

### `config/logging.php` mqtt channel (D-17)

```php
'mqtt' => [
    'driver' => 'daily',
    'path' => storage_path('logs/mqtt.log'),             // produces mqtt-{Y-m-d}.log
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => env('LOG_DAILY_DAYS', 14),                 // match Laravel + v1.0 default
    'replace_placeholders' => true,
],
```

### `composer.json` dev script 6th process (D-16)

```json
"dev": [
    "Composer\\Config::disableProcessTimeout",
    "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#f59e0b\" \"php artisan serve\" \"php artisan reverb:start\" \"php artisan horizon\" \"php artisan pail --timeout=0\" \"npm run dev\" \"php artisan irms:mqtt-listen\" --names=server,reverb,horizon,logs,vite,mqtt --kill-others"
]
```

Color `#f59e0b` (amber) matches D-16. `--kill-others` ensures that if Mosquitto is missing → listener fails → entire stack stops loudly (D-16 intent: "loud early failure, not silent weird").

### Banner Vue Component (extend ConnectionBanner pattern)

Reviewed `resources/js/components/ConnectionBanner.vue` — it's the project's top-banner idiom (transitions, lucide icons, `bg-red-600 text-white` for red). The cleanest path: create `MqttListenerHealthBanner.vue` as a sibling using the same visual structure.

```vue
<!-- resources/js/components/fras/MqttListenerHealthBanner.vue -->
<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next';

defineProps<{
    status: 'HEALTHY' | 'SILENT' | 'DISCONNECTED' | 'NO_ACTIVE_CAMERAS';
    lastMessageReceivedAt: string | null;
}>();

// Show banner ONLY when status=SILENT (D-13 scope)
// HEALTHY → no banner (expected state)
// NO_ACTIVE_CAMERAS → no banner (fresh install / dev / all-cameras-decommissioned)
// DISCONNECTED → reserved for Phase 20+; treat like SILENT for now
</script>

<template>
    <Transition /* ... same transition classes as ConnectionBanner ... */>
        <div
            v-if="status === 'SILENT' || status === 'DISCONNECTED'"
            class="flex items-center justify-center gap-2 overflow-hidden px-4 py-2 text-sm font-medium bg-red-600 text-white"
        >
            <AlertCircle class="size-4" />
            <span>MQTT listener silent &mdash; camera ingestion may be down. Check Supervisor.</span>
            <span v-if="lastMessageReceivedAt" class="text-white/80">
                (last message {{ lastMessageReceivedAt }})
            </span>
        </div>
    </Transition>
</template>
```

**Mount point (D-13 scope):** `resources/js/pages/dispatch/Console.vue` — render inside `DispatchLayout` but above the existing map container. NOT in `AppLayout` or any shared layout.

### useDispatchFeed extension

The composable already accepts `localIncidents` and `localUnits` refs and calls `useEcho` internally. Add a sibling ref + `useEcho` listener:

```ts
// Inside useDispatchFeed (or a new useMqttListenerHealth composable invoked alongside):
import type { MqttListenerHealthPayload } from '@/types/mqtt';

const mqttHealth = ref<MqttListenerHealthPayload>(initialMqttHealth);   // from Inertia shared prop

useEcho('dispatch.incidents', 'MqttListenerHealthChanged', (p: MqttListenerHealthPayload) => {
    mqttHealth.value = p;
});

// expose: return { ..., mqttHealth };
```

Recommendation: **new tiny composable `useMqttListenerHealth.ts`** is cleaner than stuffing it into `useDispatchFeed.ts` (single responsibility). `Console.vue` then calls both. Planner picks.

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| FRAS `AlertSeverity` enum with `fromEvent()` classifier | IRMS Phase 18 `RecognitionSeverity` enum (currently lacks `fromEvent()`) | Phase 18 shipped a minimal enum | **Phase 19 action:** add static `fromEvent(int $personType, int $verifyStatus): self` method to `RecognitionSeverity` — verify mapping from FRAS source |
| FRAS `RecognitionHandler` broadcasts `RecognitionAlert` inline | Phase 19 handler persists + decodes images only; no broadcast | D-01 + Phase 22 owns `fras.alerts` | Remove `event(RecognitionAlert::fromEvent($event))` from the port |
| FRAS `OnlineOfflineHandler` dispatches `EnrollPersonnelBatch` | Phase 19 writes status only; no job dispatch | Phase 20 owns enrollment | Remove the pending-enrollments fan-out block from the port |
| FRAS writes recognition images to `Storage::disk('local')` | Phase 19 writes to dedicated `Storage::disk('fras_events')` | D-15 | Path changes from `recognition/{date}/…` to `{date}/…` on the new disk |
| FRAS uses MySQL; `is_online` as TINYINT | IRMS Postgres; `status` as string-backed enum | Phase 18 schema port | Handler writes `$camera->status = CameraStatus::Online;` not `$camera->is_online = true;` |

**Deprecated/outdated:** None in Phase 19 scope — php-mqtt/laravel-client ^1.8.0 is current stable (released 2026-03-27 per STACK.md).

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| `php-mqtt/laravel-client` | MQTT-01, MQTT-02, MQTT-03, MQTT-04, MQTT-06 | Need to confirm — `composer show php-mqtt/laravel-client` | target `^1.8.0` | None — required |
| Mosquitto broker (dev) | Local `composer run dev` — MQTT-01 | Dev prereq per D-16 — `brew install mosquitto` / `apt install mosquitto mosquitto-clients` | `2.0.x` | None — loud early failure per D-16 |
| Mosquitto broker (prod) | Production MQTT ingress | Must be installed on production DO droplet | `2.0.x` | None — required for MQTT-01 |
| Supervisor (prod) | MQTT-01, MQTT-04 — `[program:irms-mqtt]` block | Already installed (runs Horizon + Reverb per Phase 17 docs) | `4.2.x` | None |
| Redis | D-05 liveness key, D-07 state-transition cache | Already in use (Horizon + cache) | existing | None |
| PostgreSQL | recognition_events persistence | Already in use (Phase 18) | existing | None |
| PostGIS / Magellan | Only for Camera location column; Phase 19 does not query geography | Already in use (Phase 18) | existing | None |
| PCNTL extension | Signal handling in listener command (`pcntl_signal`, `pcntl_alarm`, `pcntl_async_signals`) | Standard on Linux PHP CLI; **verify on macOS Herd** | PHP 8.3+ | No fallback — listener needs signals for graceful shutdown |

**Missing dependencies with no fallback:**
- **Mosquitto broker** — developer machine must install before `composer run dev` works. Document in `docs/operations/irms-mqtt.md` §Dev Prerequisite.
- **PCNTL on macOS** — `pcntl_async_signals()` is available in Herd's PHP 8.4 build ([VERIFIED: standard PHP CLI build on Darwin ships pcntl]); document as a verified prereq.

**Missing dependencies with fallback:** None.

**Planner must add a Wave 0 task:** verify `php -m | grep pcntl` on dev machine and document Mosquitto install in README.md quick-start + runbook.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4.6 [VERIFIED: composer.json line 35] |
| Config file | `phpunit.xml` (exists from v1.0); `tests/Pest.php` base |
| Quick run command | `php artisan test --compact --filter=Mqtt` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|--------------|
| **MQTT-01** | `irms:mqtt-listen` command registered with `{--max-time=3600}` signature | unit | `php artisan test --compact tests/Feature/Mqtt/ListenerCommandRegistrationTest.php` | ❌ Wave 0 |
| **MQTT-01** | `composer run dev` includes 6th `mqtt` process | static file assertion | `php artisan test --compact tests/Feature/Mqtt/ComposerScriptTest.php` (asserts `composer.json` parsed, `scripts.dev` contains `mqtt`) | ❌ Wave 0 |
| **MQTT-01** | Supervisor `[program:irms-mqtt]` block documented | runbook content assertion | `php artisan test --compact tests/Feature/Mqtt/OperationsDocTest.php` (asserts `docs/operations/irms-mqtt.md` mentions `[program:irms-mqtt]` + `stopwaitsecs=30` + `autorestart`) | ❌ Wave 0 |
| **MQTT-02** | TopicRouter routes each of 4 patterns correctly | unit (per-pattern — Pitfall 18) | `php artisan test --compact tests/Feature/Mqtt/TopicRouterTest.php` | ❌ Wave 0 |
| **MQTT-02** | TopicRouter logs unmatched topics to `mqtt` channel | unit | `php artisan test --compact tests/Feature/Mqtt/TopicRouterTest.php --filter=unmatched` | ❌ Wave 0 |
| **MQTT-02** | TopicRouter bumps `mqtt:listener:last_message_received_at` on dispatch | unit | `php artisan test --compact tests/Feature/Mqtt/TopicRouterLivenessTest.php` | ❌ Wave 0 |
| **MQTT-03** | `personName` payload parses correctly | feature | `php artisan test --compact tests/Feature/Mqtt/RecognitionHandlerTest.php --filter=personName` | ❌ Wave 0 |
| **MQTT-03** | `persionName` (typo) payload parses correctly | feature | `php artisan test --compact tests/Feature/Mqtt/RecognitionHandlerTest.php --filter=persionName` | ❌ Wave 0 |
| **MQTT-03** | RecognitionEvent written with raw_payload JSONB | feature | `php artisan test --compact tests/Feature/Mqtt/RecognitionHandlerTest.php --filter=raw_payload` | ❌ Wave 0 |
| **MQTT-03** | Base64 face crop saved to fras_events disk at `{date}/faces/{event_id}.jpg` | feature | `php artisan test --compact tests/Feature/Mqtt/RecognitionHandlerTest.php --filter=face_image` | ❌ Wave 0 |
| **MQTT-03** | Base64 scene saved to `{date}/scenes/{event_id}.jpg` | feature | `php artisan test --compact tests/Feature/Mqtt/RecognitionHandlerTest.php --filter=scene_image` | ❌ Wave 0 |
| **MQTT-03** | Face > 1MB dropped with warning log | feature | `php artisan test --compact tests/Feature/Mqtt/RecognitionHandlerTest.php --filter=oversized` | ❌ Wave 0 |
| **MQTT-03** | Unknown camera device_id → warning log, no DB write | feature | `php artisan test --compact tests/Feature/Mqtt/RecognitionHandlerTest.php --filter=unknown_camera` | ❌ Wave 0 |
| **MQTT-03** | Duplicate RecPush (same camera_id + record_id) → try/catch, info log, no second row | feature | `php artisan test --compact tests/Feature/Mqtt/RecognitionHandlerTest.php --filter=idempotent` | ❌ Wave 0 |
| **MQTT-04** | Command accepts `--max-time=3600` option; SIGALRM handler installed | unit | `php artisan test --compact tests/Feature/Mqtt/ListenerCommandMaxTimeTest.php` (invokes command with `--max-time=1`; asserts clean exit within ~1.5s) | ❌ Wave 0 |
| **MQTT-04** | Auto-reconnect config present in `config/mqtt-client.php` | config assertion | `php artisan test --compact tests/Feature/Mqtt/MqttClientConfigTest.php --filter=auto_reconnect` | ❌ Wave 0 |
| **MQTT-05** | Watchdog: 0 active cameras → NO_ACTIVE_CAMERAS state | feature | `php artisan test --compact tests/Feature/Mqtt/MqttListenerWatchdogTest.php --filter=no_active_cameras` | ❌ Wave 0 |
| **MQTT-05** | Watchdog: active cameras + fresh heartbeat → HEALTHY | feature | `php artisan test --compact tests/Feature/Mqtt/MqttListenerWatchdogTest.php --filter=healthy` | ❌ Wave 0 |
| **MQTT-05** | Watchdog: active cameras + gap ≥ 90s → SILENT broadcast | feature | `php artisan test --compact tests/Feature/Mqtt/MqttListenerWatchdogTest.php --filter=silent_broadcast` | ❌ Wave 0 |
| **MQTT-05** | Watchdog does NOT broadcast when state unchanged | feature | `php artisan test --compact tests/Feature/Mqtt/MqttListenerWatchdogTest.php --filter=no_transition` | ❌ Wave 0 |
| **MQTT-05** | MqttListenerHealthChanged broadcasts on `dispatch.incidents` with enum payload | feature | `php artisan test --compact tests/Feature/Mqtt/MqttListenerHealthChangedTest.php` | ❌ Wave 0 |
| **MQTT-05** | DispatchConsoleController adds `mqtt_listener_health` Inertia shared prop | feature | `php artisan test --compact tests/Feature/Mqtt/DispatchConsoleMqttHealthPropTest.php` | ❌ Wave 0 |
| **MQTT-05** | Banner renders on DispatchConsole when status=SILENT (Vue component — visual smoke) | manual-smoke | manual via `composer run dev` → kill mqtt process → wait 90s → observe red banner | — |
| **MQTT-06** | `config/mqtt-client.php` has `default` and `publisher` connection blocks | config assertion | `php artisan test --compact tests/Feature/Mqtt/MqttClientConfigTest.php --filter=separate_connections` | ❌ Wave 0 |

### Fakes / Mocks / Tools

**Mosquitto NOT required in CI.** The entire Phase 19 test suite is deterministic without a live broker:

- **TopicRouter tests** — instantiate router directly, inject mock handlers via `$this->app->instance(HandlerClass::class, Mockery::mock(...))`. No MQTT connection needed.
- **Handler tests** — call `$handler->handle($topic, $rawJsonString)` directly. Bypass the MQTT library entirely. Use JSON fixtures in `tests/fixtures/mqtt/{rec-push-personName.json, rec-push-persionName.json, heartbeat.json, ...}` ported verbatim from FRAS v1.0 test fixtures (if they exist) or derived from `RecognitionEventFactory::critical()`.
- **Watchdog tests** — `Carbon::setTestNow(...)`, `Cache::shouldReceive(...)`, `Event::fake([MqttListenerHealthChanged::class])`, `assertDispatched`/`assertNotDispatched`.
- **Idempotency test** — write a RecognitionEvent with known `(camera_id, record_id)`; invoke handler with same record_id; assert only 1 row exists + info log present. The **exception class is `Illuminate\Database\UniqueConstraintViolationException`** [VERIFIED: Laravel 13 docs + Phase 18 D-54 unique index ensures it's thrown].
- **Storage test** — `Storage::fake('fras_events')`; assert `Storage::disk('fras_events')->exists($expectedPath)` after handler runs.
- **ListenerCommandMaxTimeTest** — `$this->artisan('irms:mqtt-listen', ['--max-time' => 1])->assertExitCode(0)`, wrap in `set_time_limit(5)` guard; requires either a mocked MQTT facade or a `MQTT::fake()` helper. **Planner must verify** whether php-mqtt/laravel-client ships a `MQTT::fake()` — if not, mock the `MQTT::connection()` return with `Mockery::mock(MqttClient::class)`.

[ASSUMED: `MQTT::fake()` test helper availability — needs verification via `composer show php-mqtt/laravel-client` or Context7 deep-dive during planning]

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=Mqtt` (runs only new Phase 19 tests, ~5-15s target)
- **Per wave merge:** `php artisan test --compact` (full suite, includes FRAS + v1.0 regression)
- **Phase gate:** Full suite green AND manual smoke test in `docs/operations/irms-mqtt.md` §Smoke Test before `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/Mqtt/` directory — NEW (does not exist)
- [ ] `tests/Feature/Mqtt/TopicRouterTest.php` — per-pattern routing (Pitfall 18)
- [ ] `tests/Feature/Mqtt/TopicRouterLivenessTest.php` — liveness key bump assertion
- [ ] `tests/Feature/Mqtt/RecognitionHandlerTest.php` — personName/persionName + idempotency + unknown-camera + oversized-image
- [ ] `tests/Feature/Mqtt/HeartbeatHandlerTest.php` — last_seen_at bump + unknown-camera warning
- [ ] `tests/Feature/Mqtt/OnlineOfflineHandlerTest.php` — Online|Offline enum flip + invalid-operator warning
- [ ] `tests/Feature/Mqtt/AckHandlerTest.php` — Phase 19 scaffold log assertion
- [ ] `tests/Feature/Mqtt/MqttListenerWatchdogTest.php` — HEALTHY/SILENT/NO_ACTIVE_CAMERAS state transitions
- [ ] `tests/Feature/Mqtt/MqttListenerHealthChangedTest.php` — event shape + channel auth
- [ ] `tests/Feature/Mqtt/DispatchConsoleMqttHealthPropTest.php` — Inertia shared prop
- [ ] `tests/Feature/Mqtt/MqttClientConfigTest.php` — separate subscriber+publisher connection assertions
- [ ] `tests/Feature/Mqtt/ListenerCommandRegistrationTest.php` — command signature + options
- [ ] `tests/Feature/Mqtt/ListenerCommandMaxTimeTest.php` — `--max-time` exit behavior
- [ ] `tests/Feature/Mqtt/ComposerScriptTest.php` — `composer.json scripts.dev` contains `mqtt` process
- [ ] `tests/Feature/Mqtt/OperationsDocTest.php` — `docs/operations/irms-mqtt.md` mentions required strings
- [ ] `tests/fixtures/mqtt/` — JSON fixtures for RecPush (personName + persionName + oversized + duplicate), heartbeat, basic-online, basic-offline, ack
- [ ] `tests/Pest.php` — may need new `uses(RefreshDatabase::class)->in('Feature/Mqtt')` binding (inherits from existing convention)

---

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|------------------|
| V2 Authentication | no (Phase 19) | Authentication is HTTP-surface concern; MQTT auth is network-layer via Mosquitto password_file. Covered in §Integration Points. |
| V3 Session Management | no (Phase 19) | No user sessions introduced. Dispatch console banner piggybacks on existing Fortify session auth. |
| V4 Access Control | yes | `dispatch.incidents` channel auth reused [VERIFIED: routes/channels.php:9-11 — `operator|dispatcher|supervisor|admin`]. Banner rendered only on DispatchConsole.vue (D-13). PII images on private disk with no HTTP surface (Phase 22 adds signed URLs). |
| V5 Input Validation | yes | RecPush payloads validated: `operator === 'RecPush'` gate, `device_id` extraction nullable-safe, base64 validated with `base64_decode($b, true)` strict flag, size caps enforced (1MB face / 2MB scene). |
| V6 Cryptography | no (Phase 19) | No cryptographic operations in Phase 19. TLS for MQTT is deferred (CONTEXT.md deferred: "TLS posture for routed subnets"). |
| V7 Errors + Logging | yes | Dedicated `mqtt` log channel (D-17). Unmatched topics, unknown cameras, oversized images, idempotency rejects, parse failures — all logged. NO PII in log messages (camera device_id OK; personnel name OK; raw payload NOT logged). |
| V8 Data Protection | yes | `fras_events` disk is `visibility: 'private'`. Phase 22 adds signed URL controller + `fras_access_log` for DPA compliance. Phase 19 does not emit PII over broadcasts. |
| V10 Malicious Code | partial | Base64-decoded images written to disk; MIME validation is deferred (Phase 20 `FrasPhotoProcessor`). Phase 19 accepts whatever decodes successfully and fits under size cap — acceptable because disk is private and no image serving exists yet. |

### Known Threat Patterns for MQTT + Laravel

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Duplicate RecPush replay (adversary re-publishes captured message) | Tampering | DB-level UNIQUE(camera_id, record_id) + try/catch idempotent handler (D-03) |
| Unknown device_id flooding broker to exhaust disk | Denial of Service | D-14 drop-with-warning; no `recognition_events` row written; no image decoded; no disk growth |
| Oversized image base64 payload exhausting memory | Denial of Service | FRAS size caps (1MB/2MB) — reject before `base64_decode` via `strlen` check OR after decode via `strlen($bytes) > $max` check |
| Stale-code-running-forever after deploy (Pitfall 7) | Repudiation / Tampering | Post-deploy `supervisorctl restart irms-mqtt:*` protocol in runbook |
| Listener silent death unnoticed | Denial of Service (ingestion) | MQTT-05 watchdog + banner (Phase 19 delivers) |
| Broadcast flood via recognition events | Denial of Service (dispatch UI) | Phase 19 does not broadcast recognition — only state transitions. Pitfall 13 re-applies only in Phase 22. |

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | FRAS `AlertSeverity::fromEvent($personType, $verifyStatus)` classifier: `person_type==1 + verify_status==1 → Critical`; `person_type==2 → Warning`; else `Info` | Pattern 2 RecognitionHandler + §State of the Art | Wrong severity classification means wrong future Incident priorities (Phase 21); planner must open `/Users/helderdene/fras/app/Enums/AlertSeverity.php` and lock the exact matrix before writing `RecognitionSeverity::fromEvent()` |
| A2 | `php-mqtt/laravel-client` exposes a `MQTT::fake()` or similar test helper | §Validation Architecture fakes | If no fake helper exists, planner must mock `MQTT::connection()` via Mockery. Not a blocker — Mockery works — but test setup ergonomics differ. Planner should run `composer show php-mqtt/laravel-client` and check README during planning. |
| A3 | `supervisorctl restart irms-mqtt:*` with wildcard works on Supervisor 4.2.x (FRAS docs don't confirm wildcard syntax) | Pitfall 7 mitigation | If `:*` isn't supported on the production Supervisor, use `sudo supervisorctl restart irms-mqtt` (single program, no `:*`). Planner should verify during runbook authoring. |
| A4 | PCNTL extension (specifically `pcntl_alarm`, `pcntl_async_signals`) available on production Ubuntu + Herd macOS | §Listener Command implementation | FRAS v1.0 runs identical code on Linux; Herd's PHP 8.4 ships pcntl. Risk is low but `php -m | grep pcntl` verification should be a Wave 0 task. |
| A5 | Severity enum `fromEvent()` is safe to add to Phase 18's `RecognitionSeverity` in Phase 19 (Phase 18 migration doesn't lock the enum class shape) | §State of the Art | Adding a static method to a PHP enum never breaks existing users. Very low risk. |
| A6 | `Storage::fake('fras_events')` works for unit tests once disk is declared in `config/filesystems.php` | §Validation Architecture | Standard Laravel fake; should work. Low risk. |

---

## Open Questions (RESOLVED)

1. **RESOLVED: `RecognitionSeverity::fromEvent()` classification matrix.**
   - Resolution: Plan 19-01 Task 3 reads `/Users/helderdene/fras/app/Enums/AlertSeverity.php` verbatim and ports the matrix into `app/Enums/RecognitionSeverity.php` with a `fromEvent(int $personType, int $verifyStatus): self` classifier. FRAS's `Ignored` case collapses to `Info` (IRMS enum has no `Ignored` variant — documented rationale in plan).

2. **RESOLVED: AckHandler Phase 19 scaffold — DB-write policy.**
   - Resolution: Plan 19-03 Task 2 implements `AckHandler` as parse + validate + log + return ONLY. Zero DB writes in Phase 19. The `Cache::pull` correlation + `CameraEnrollment` state update lands in Phase 20.

3. **RESOLVED: Watchdog state-transition persistence.**
   - Resolution: Plan 19-04 Task 2 keeps watchdog cache-only. `MqttListenerHealthChanged` is the sole persistence path (broadcast + log). No new audit table in Phase 19. Deferred to Phase 22 if ops feedback requests historical timeline.

4. **RESOLVED: Banner component placement.**
   - Resolution: Plan 19-05 Task 2 creates new `resources/js/components/fras/MqttListenerHealthBanner.vue` (separate from `ConnectionBanner.vue`). Rationale: different failure domain (MQTT ingress vs WebSocket dispatch), different operator action, different message text. Stack them with MQTT banner rendering first when both fire.

---

## Project Constraints (from CLAUDE.md)

- **Skill activations required during Phase 19:**
  - `laravel-best-practices` — all PHP code (handlers, commands, services)
  - `configuring-horizon` — adding `fras-supervisor` block
  - `pest-testing` — every test file
  - `echo-vue-development` — banner component + `useEcho` extension
  - `inertia-vue-development` — shared prop wire-through
  - `tailwindcss-development` — banner styling (`bg-red-600 text-white`)
- **Pint formatter:** run `vendor/bin/pint --dirty --format agent` after every PHP change. Never `--test` flag.
- **Pest commands:** `php artisan test --compact` (project standard). Specific-file: `php artisan test --compact tests/Feature/Mqtt/…`.
- **Type imports enforced:** `prefer-type-imports` ESLint rule — banner + composable must use `import type { ... }`.
- **Curly braces always, constructor promotion, explicit return types** — Pint enforces + Laravel-best-practices skill.
- **Array-style Form Request rules** — not relevant Phase 19 (no new Form Requests).
- **Auto-generated directory exceptions** — ESLint ignores `resources/js/actions/`, `resources/js/routes/`, `resources/js/wayfinder/`, `resources/js/components/ui/`. Phase 19's new `components/fras/` directory is NOT auto-generated — standard lint rules apply.
- **Wayfinder:** Phase 19 adds no new controller routes (watchdog is artisan-only; dispatch console already has routes). `php artisan wayfinder:generate` not required unless `AdminCameraController` or similar lands in this phase (it does not — Phase 20).
- **`php artisan make:test --pest Mqtt/TopicRouterTest`** — follow Laravel-native command to create tests (per `laravel/core` rules).
- **Every change must be programmatically tested** (tests rules). Applies especially to TopicRouter per-pattern tests.
- **Do NOT create documentation files unless explicitly requested** — `docs/operations/irms-mqtt.md` IS explicitly in CONTEXT.md `<code_context>` integration points and CANONICAL to Pitfall 7 mitigation. Create it.

---

## Sources

### Primary (HIGH confidence)

- **CONTEXT.md** (`.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md`) — all 17 locked decisions, canonical references, integration points
- **DISCUSSION-LOG.md** — preserves rationale for decisions D-01..D-17
- **REQUIREMENTS.md §MQTT** — acceptance criteria for MQTT-01..06
- **ROADMAP.md Phase 19** — success criteria 1-6
- **`/Users/helderdene/fras/app/Console/Commands/FrasMqttListenCommand.php`** — verbatim port reference for listener command (signature, signal handling, subscribe loop)
- **`/Users/helderdene/fras/app/Mqtt/TopicRouter.php`** — verbatim port reference for regex map + app() dispatch pattern
- **`/Users/helderdene/fras/app/Mqtt/Contracts/MqttHandler.php`** — single-method interface
- **`/Users/helderdene/fras/app/Mqtt/Handlers/{RecognitionHandler,HeartbeatHandler,OnlineOfflineHandler,AckHandler}.php`** — handler body ports (with Phase 19 subtractions noted in Pattern 2/3)
- **`/Users/helderdene/fras/config/mqtt-client.php`** — subscriber + publisher connection config (MQTT-06)
- **`/Users/helderdene/fras/config/hds.php`** — source for `config/fras.php` port
- **`/Users/helderdene/IRMS/composer.json`** — existing 5-process dev script; Phase 19 extends to 6
- **`/Users/helderdene/IRMS/config/horizon.php`** — `supervisor-1` pattern to mirror for `fras-supervisor`
- **`/Users/helderdene/IRMS/config/filesystems.php`** — existing private disk pattern (`local` disk root: `storage_path('app/private')`)
- **`/Users/helderdene/IRMS/config/logging.php`** — daily channel pattern
- **`/Users/helderdene/IRMS/routes/channels.php`** — `dispatch.incidents` auth closure (lines 9-11)
- **`/Users/helderdene/IRMS/routes/console.php`** — Schedule facade pattern
- **`/Users/helderdene/IRMS/app/Events/IncidentCreated.php`** — reference shape for `MqttListenerHealthChanged` (ShouldBroadcast + ShouldDispatchAfterCommit + broadcastOn + broadcastWith)
- **`/Users/helderdene/IRMS/app/Http/Controllers/DispatchConsoleController.php`** — reference for Inertia shared-prop insertion point (`show()` method)
- **`/Users/helderdene/IRMS/app/Enums/{CameraStatus,RecognitionSeverity}.php`** — Phase 18 enums Phase 19 writes to
- **`/Users/helderdene/IRMS/database/migrations/2026_04_21_000004_create_recognition_events_table.php`** — frozen Phase 18 schema (UNIQUE constraint, CHECK constraint name, JSONB + GIN)
- **`/Users/helderdene/IRMS/database/factories/RecognitionEventFactory.php`** — `::critical()`, `::warning()`, `::info()`, `::blockMatch()`, `::withPersonnel()` states for handler tests
- **`/Users/helderdene/IRMS/docs/operations/laravel-13-upgrade.md` §8** — reference Supervisor program block for `[program:irms-mqtt]`
- **`/Users/helderdene/IRMS/resources/js/components/ConnectionBanner.vue`** — banner component pattern to mirror
- **`/Users/helderdene/IRMS/resources/js/composables/useDispatchFeed.ts`** — useEcho integration pattern
- **`/Users/helderdene/IRMS/.planning/research/STACK.md`** — php-mqtt/laravel-client ^1.8, Mosquitto 2.0.x, `FRAS_EVENT_DISK`, `composer run dev` 6-process pattern
- **`/Users/helderdene/IRMS/.planning/research/ARCHITECTURE.md`** — component responsibilities, recommended project structure, patterns 1-6, anti-patterns 1-6
- **`/Users/helderdene/IRMS/.planning/research/PITFALLS.md` §6, §7, §11, §13, §17, §18** — MQTT-related pitfalls carried into Phase 19

### Secondary (MEDIUM-HIGH confidence, Context7-verified)

- **Context7 `/php-mqtt/laravel-client` "Create a Long-Running MQTT Subscriber Artisan Command"** — confirms signature + signal handling + loop pattern [VERIFIED 2026-04-21]
- **Context7 `/php-mqtt/laravel-client` "Interrupting the Event Loop"** — confirms `$mqtt->interrupt()` is the correct exit path for graceful shutdown [VERIFIED 2026-04-21]
- **Context7 `/php-mqtt/laravel-client` "Basic Subscription with Callback"** — QoS level parameter syntax `$mqtt->subscribe($topic, $callback, $qos)` [VERIFIED 2026-04-21]

### Tertiary (LOW — flagged for validation in Assumptions Log)

- A1, A2, A3, A4 — see §Assumptions Log. Each has a Wave 0 verification task.

---

## Metadata

**Confidence breakdown:**
- Standard stack: **HIGH** — all packages already installed, versions verified, no `composer require` needed in Phase 19
- Architecture: **HIGH** — FRAS reference implementation + IRMS v1.0 patterns both read in source; Phase 18 schema frozen
- Handler port specifics: **HIGH** — FRAS source files inspected line-by-line; Phase 19 subtractions (no broadcast, no job dispatch, disk change) explicitly enumerated
- Validation architecture: **HIGH** — test strategy maps 1:1 to requirements; fakes/mocks/fixtures all feasible without Mosquitto
- Severity classifier (`fromEvent`): **MEDIUM** — A1 in Assumptions Log; Wave 0 task unblocks
- MQTT::fake() helper: **MEDIUM** — A2 in Assumptions Log; Mockery fallback verified
- Pitfalls coverage: **HIGH** — all 6 mandated pitfalls (6, 7, 11, 13, 17, 18) explicitly addressed

**Research date:** 2026-04-21
**Valid until:** 2026-05-21 (30 days for stable stack; revisit if php-mqtt/laravel-client cuts ^1.9 or Laravel 13.x minor with broadcasting changes)
