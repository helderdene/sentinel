# Phase 19: MQTT Pipeline + Listener Infrastructure - Context

**Gathered:** 2026-04-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 19 delivers the **operational MQTT ingress surface**. A dedicated `php artisan irms:mqtt-listen` command runs under a stand-alone `irms-mqtt` Supervisor program (never under Horizon), subscribes to Mosquitto, and routes topics to four handler classes (`RecognitionHandler`, `AckHandler`, `HeartbeatHandler`, `OnlineOfflineHandler`) via a regex-based `TopicRouter`. Recognition payloads persist to `recognition_events` (schema already frozen in Phase 18: UUID PK, `(camera_id, record_id)` UNIQUE, `raw_payload` JSONB+GIN, `severity` enum, `received_at` µs). Face crops + scene images land on a dedicated private disk. A Redis-backed liveness heartbeat + scheduled watchdog exposes a listener-health banner on the dispatch console within 60s of listener silence.

**Out of scope (guardrails):**
- **No admin CRUD** for cameras or personnel — Phase 20 (`/admin/cameras`, `/admin/personnel`, MapLibre picker, enrollment batches).
- **No Incident bridge** — Phase 21 `FrasIncidentFactory` and `IncidentChannel::IoT` wiring. Phase 19's `RecognitionHandler` writes `recognition_events` only; `incident_id` stays NULL.
- **No alert feed UI, no ACK/dismiss UX, no signed-URL image serving, no DPA audit log** — Phase 22. Phase 19 does not broadcast recognition alerts to any `fras.alerts` channel.
- **No `CameraStatus::Degraded` semantics** — Phase 19 only flips online/offline. Degraded-threshold watchdog is Phase 20.
- **No `EnrollPersonnelBatch` jobs, no `FrasPhotoProcessor`, no `CameraEnrollmentService`** — Phase 20. `AckHandler` in Phase 19 only validates + logs ACK topics; correlation cache + enrollment state updates wait for Phase 20.

The deliverable gate: a test RecPush published via `mosquitto_pub` (both `personName` and `persionName` firmware spellings) produces exactly one `recognition_events` row with `raw_payload` populated and both image paths on disk; the listener rotates hourly and reconnects cleanly after a broker bounce; a `horizon:terminate` + restart cycle leaves the MQTT listener untouched; a simulated listener death shows a red banner on the dispatch console within 60s.

</domain>

<decisions>
## Implementation Decisions

### Handler processing model

- **D-01:** **Everything runs inline inside the MQTT listener process (FRAS-parity).** `RecognitionHandler::handle()` synchronously parses the payload, inserts the `recognition_events` row, base64-decodes + writes face crop + scene image, and (in later phases) broadcasts. At CDRRMO scale (≤8 cameras, QoS 0, bursty-but-low throughput) the inline path stays sub-100ms per event and keeps the blast radius of "something went wrong" inside one process. The FRAS v1.0 production deployment validated this exact shape.
- **D-02:** **Phase 19 registers the dedicated `fras` Horizon queue now, even though Phase 19 handlers are inline.** Add a `fras-supervisor` block to `config/horizon.php` (queue=['fras'], reasonable minProcesses/maxProcesses for CDRRMO scale). No Phase 19 code enqueues to it; the block exists so Phase 20's `EnrollPersonnelBatch` jobs and Phase 22's retention purge have a home without touching horizon config again.
- **D-03:** **Duplicate RecPush handling uses the DB idempotency gate.** Phase 18 D-54 locked `UNIQUE (camera_id, record_id)`. `RecognitionHandler` wraps the `RecognitionEvent::create()` call in a try/catch on `Illuminate\Database\UniqueConstraintViolationException`. On hit: `Log::channel('mqtt')->info('Duplicate RecPush rejected at DB layer', [...])`, return early — no broadcast, no image decode, no re-write. This is the "DB enforces, handler absorbs" model FRAMEWORK-06 was designed for.
- **D-04:** **Keep FRAS's `TopicRouter` shape — regex map + `app($handlerClass)->handle($topic, $message)`.** Handlers implement `App\Mqtt\Contracts\MqttHandler` (single method `handle(string $topic, string $message): void`). The router has a `protected array $routes = [pattern => HandlerClass::class]` property, iterates with `preg_match`, logs unmatched topics at warning level (never silently drops — MQTT-02). Tests swap handlers via `$this->app->instance(HandlerClass::class, $mock)` when needed.

### Listener liveness + health watchdog

- **D-05:** **Liveness state is a Redis cache key bumped on every routed message.** Inside `TopicRouter::dispatch()`, after a handler runs, the router calls `Cache::put('mqtt:listener:last_message_received_at', now()->toIso8601String(), ttl=120)`. Key stored in the default cache store (Redis in dev + prod, v1.0 pattern). No new migration; no new table.
- **D-06:** **The heartbeat signal is last-message-received, not last-loop-tick.** At ≥1 active camera, Mosquitto traffic is near-continuous (camera heartbeats ~30s per camera + online/offline + occasional recognitions). Stale `last_message_received_at` means *either* the process is dead *or* the broker connection is down — both require the same operator response (check Supervisor, restart listener). Loop-tick-only would prove "process alive" while masking "broker disconnected" which is the scenario MQTT-05 actually cares about.
- **D-07:** **Scheduled watchdog: `php artisan irms:mqtt-listener-watchdog` runs `->everyThirtySeconds()` from `routes/console.php`.** The command:
  1. Reads `cameras` active count (`whereNull('decommissioned_at')->count()`).
  2. If 0 active cameras → state = `NO_ACTIVE_CAMERAS`, skip further checks (no banner).
  3. If ≥1 active camera → reads `mqtt:listener:last_message_received_at` from cache.
  4. Computes gap = `now() - last_message_received_at` (or `null → ∞` if key missing).
  5. State: `HEALTHY` if gap < 90s, `SILENT` if gap ≥ 90s (covers the "3 missed heartbeats" MQTT-05 threshold since cameras heartbeat ~30s).
  6. Compares against previous state cached as `mqtt:listener:last_known_state`. On transition, dispatches `MqttListenerHealthChanged`.
  Scheduled every 30s + 90s threshold = detection within 120s worst case, 30s best case — comfortably inside MQTT-05's 60s "3 missed heartbeats" window because the heartbeat signal is continuous, not every-60s.
- **D-08:** **Phase 19 only writes `CameraStatus::Online` / `CameraStatus::Offline` on `camera.status`.** `OnlineOfflineHandler` flips the bool based on `operator ∈ {'Online','Offline'}`; `HeartbeatHandler` only bumps `last_seen_at`. The `Degraded` case declared in Phase 18 D-65 stays reserved for Phase 20's camera watchdog (heartbeat-gap thresholds > 30s but < offline-cutoff).
- **D-09:** **Watchdog arms only when `cameras.active_count ≥ 1`.** Prevents a false-fire banner during clean installs, all-camera maintenance windows, or the pre-Phase-20 state when no cameras have been added yet. Broadcast payload carries `status = NO_ACTIVE_CAMERAS` so the UI can render a neutral state (no banner) rather than "healthy" (misleading) or "silent" (false alarm).

### Banner delivery to dispatch UI

- **D-10:** **`MqttListenerHealthChanged` broadcasts on the existing `dispatch.incidents` private channel.** Reuses the already-authorized `operator|dispatcher|supervisor|admin` role gate (`routes/channels.php` lines 9–11). No new channel to authorize, no new Echo subscription to wire. The watchdog command dispatches the event only on state transitions (not every tick) to keep Reverb traffic proportional to actual signal.
- **D-11:** **Payload is a full enum state, not a boolean.**
  ```php
  public function broadcastWith(): array {
      return [
          'status' => 'HEALTHY' | 'SILENT' | 'DISCONNECTED' | 'NO_ACTIVE_CAMERAS',
          'last_message_received_at' => 'ISO8601 or null',
          'since' => 'ISO8601 — when this state began',
          'active_camera_count' => (int),
      ];
  }
  ```
  `DISCONNECTED` is reserved for Phase 20+ when php-mqtt's auto-reconnect callback can signal broker-specific failures separately from silent listener death. Phase 19 emits HEALTHY / SILENT / NO_ACTIVE_CAMERAS only; the enum shape is forward-compat.
- **D-12:** **Initial state on page load via Inertia shared prop.** `DispatchConsoleController::index` reads `Cache::get('mqtt:listener:last_known_state')` (or computes inline if absent) and passes `mqtt_listener_health` as an Inertia shared prop. Dispatch console mounts with accurate state; the broadcast updates it live. Matches how `useDispatchFeed` already consumes initial incidents/units via shared props.
- **D-13:** **Persistent red top banner on DispatchConsole and any FRAS-facing page.** Phase 19 wires the banner into `DispatchConsole.vue` only; Phase 20 extends to `/admin/cameras`, Phase 22 extends to `/fras/alerts` and `/fras/events`. Banner does NOT render on `/responder`, `/intake`, citizen app, or settings/admin pages outside FRAS — those audiences do not need MQTT infrastructure visibility.

### Unknown-camera RecPush

- **D-14:** **Drop with warning log (FRAS-parity).** When `Camera::where('device_id', $deviceId)->first()` returns null, `RecognitionHandler` calls `Log::channel('mqtt')->warning('RecPush for unknown camera', ['device_id' => ..., 'topic' => ...])` and returns. No `recognition_events` row, no broadcast, no image save. This respects Phase 18's `camera_id NOT NULL ON DELETE RESTRICT` constraint and accepts the tradeoff that pre-camera-registration events are discarded. Operational rule: register the camera *first*, then the camera is configured to publish.

### Photo storage

- **D-15:** **Dedicated `fras_events` disk in `config/filesystems.php`.** Root: `storage_path('app/private/fras_events')`. `visibility: 'private'`, no `url` key. Path convention: `{YYYY-MM-DD}/faces/{event_id}.jpg` and `{YYYY-MM-DD}/scenes/{event_id}.jpg`. Keeps the retention purge (Phase 22) with a clean blast-radius target — `Storage::disk('fras_events')->allDirectories()` walks only recognition images, never dispatches artifacts or anything v1.0 stored on `local`.  Env var `FRAS_EVENT_DISK` reserved for future S3/object-storage swap without code change.

### Local dev process

- **D-16:** **Always-on 6th `composer run dev` process; Mosquitto is a documented dev prerequisite.** `composer.json` `scripts.dev` gains a 6th concurrently target `"php artisan irms:mqtt-listen"` with color `#f59e0b` and name `mqtt`. Dev setup instructions in both `README.md` (quick-start) and `docs/operations/irms-mqtt.md` (full reference) document the `brew install mosquitto` / `apt install mosquitto mosquitto-clients` step. If Mosquitto isn't running, the listener logs a connection-refused error and exits; `concurrently --kill-others` shuts down the rest — failure is loud + early, not silent + weird.

### Logging

- **D-17:** **Dedicated `mqtt` log channel in `config/logging.php`.** New daily channel at `storage/logs/mqtt-{date}.log`. Listener command, `TopicRouter`, and all four handlers use `Log::channel('mqtt')->info(...)` / `->warning(...)`. Keeps v1.0 dispatch/auth/error logs readable and makes post-deploy smoke tests trivial (`tail -f storage/logs/mqtt-*.log`). Pitfall 6 explicitly mandates "separate log file."

### Claude's Discretion

- Exact pattern strings for the `TopicRouter` routes map — port from FRAS verbatim (`mqtt/face/{device_id}/Rec|Ack`, `mqtt/face/basic`, `mqtt/face/heartbeat`) with the prefix pulled from `config('fras.mqtt.topic_prefix')` so tests can override.
- Whether the Redis cache key uses TTL 120s or 300s — planner picks; TTL only matters if the watchdog gets disabled (key expires naturally, no stale-forever risk).
- Horizon `fras-supervisor` block `minProcesses`/`maxProcesses`/`tries`/`timeout` — planner matches v1.0 `supervisor-1` defaults scaled for "low-throughput enrollment work."
- Banner Vue component structure — reuse existing `AppBanner.vue` if present (check `resources/js/components/`), otherwise new `MqttListenerHealthBanner.vue` under `resources/js/components/fras/`.
- Whether the watchdog command's 30s cadence is literal `->everyThirtySeconds()` or uses a `->cron('*/30 * * * * *')` — same outcome.
- Supervisor `[program:irms-mqtt]` block wording — mirror the `[program:irms-horizon]` reference block already documented in `docs/operations/laravel-13-upgrade.md` §8, with `stopwaitsecs=30` (listener shuts down fast; no long-running jobs to drain) and `autorestart=unexpected` (crashes restart; clean exits via `--max-time` also restart).
- Post-deploy smoke-test payload shape for the `mosquitto_pub` validation step — planner picks a sentinel `device_id = 'irms-smoketest'` with a registered test Camera that seeders create conditionally.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 19 goal, requirements, success criteria
- `.planning/ROADMAP.md` §Phase 19 — goal, depends-on (Phase 18), 6 success criteria, requirements list (MQTT-01..06)
- `.planning/REQUIREMENTS.md` §MQTT — acceptance criteria for MQTT-01 through MQTT-06
- `.planning/REQUIREMENTS.md` §Scope Decisions — "MQTT listener under dedicated Supervisor, never under Horizon"

### Phase 18 schema (Phase 19 persists into these tables — do not re-migrate)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` — full schema decisions (D-01..D-70)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-31..D-55 — `recognition_events` columns including `raw_payload` jsonb+GIN, `(camera_id, record_id)` UNIQUE, `received_at` µs, `severity` enum, `face_image_path`/`scene_image_path`
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-09 — `cameras.status` enum (online|offline|degraded) — Phase 19 writes only online/offline
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-61 — `RecognitionEventFactory` emits both `personName` + `persionName` spellings; Phase 19 parser must accept either

### Research — MQTT + operational decisions
- `.planning/research/STACK.md` §Supporting Libraries — `php-mqtt/laravel-client` ^1.8 + Mosquitto 2.0.x; `.env` MQTT block; `config/filesystems.php` `FRAS_EVENT_DISK` naming
- `.planning/research/STACK.md` §Development Tools / Operational — `composer run dev` 6-process pattern; `mosquitto_pub` payload replay for tests
- `.planning/research/ARCHITECTURE.md` §Component Responsibilities — MQTT Listener / TopicRouter / Handlers component breakdown; `app/Mqtt/` sibling of `app/Http/` rationale
- `.planning/research/ARCHITECTURE.md` §Recommended Project Structure — concrete paths for `app/Mqtt/Contracts/MqttHandler.php`, `app/Mqtt/TopicRouter.php`, `app/Mqtt/Handlers/{Recognition,Ack,Heartbeat,OnlineOffline}Handler.php`, `app/Console/Commands/FrasMqttListenCommand.php`
- `.planning/research/PITFALLS.md` §Pitfall 6 — "MQTT listener running under Horizon supervisor config" — mandates dedicated Supervisor program + `--max-time=3600` + separate log file
- `.planning/research/PITFALLS.md` §Pitfall 7 — "Deploying code without restarting the MQTT listener" — `supervisorctl restart irms-mqtt:*` must be in deploy script; post-deploy smoke test required
- `.planning/research/PITFALLS.md` §Pitfall 11 — role-gating recognition image access (operator|supervisor|admin only; Phase 22 enforces, Phase 19 doesn't broadcast)
- `.planning/research/PITFALLS.md` §Pitfall 13 — Reverb throttle concerns under recognition flood (Phase 19 handlers are inline + don't broadcast alerts yet, so Phase 19 is under the throttle concern threshold)
- `.planning/research/PITFALLS.md` §Pitfall 17 — QoS 0 message loss on disconnect; `mqtt_listener_health` exposure to operators; operational SLA < 30s restart
- `.planning/research/PITFALLS.md` §Pitfall 18 — Topic subscription wildcard mismatch; per-pattern Pest `TopicRouter` tests mandated

### FRAS source (verbatim port references)
- `/Users/helderdene/fras/app/Console/Commands/FrasMqttListenCommand.php` — signature, signal handling, subscription loop
- `/Users/helderdene/fras/app/Mqtt/TopicRouter.php` — regex routes, `app($handlerClass)` pattern, warning log on unmatched topic
- `/Users/helderdene/fras/app/Mqtt/Contracts/MqttHandler.php` — single-method interface
- `/Users/helderdene/fras/app/Mqtt/Handlers/RecognitionHandler.php` — RecPush parser with `personName`/`persionName` fallback, base64 image decode with size caps (1 MB face / 2 MB scene), date-partitioned storage
- `/Users/helderdene/fras/app/Mqtt/Handlers/AckHandler.php` — ACK correlation scaffolding (Phase 19 ports the shape; Phase 20 fills in enrollment state)
- `/Users/helderdene/fras/app/Mqtt/Handlers/HeartbeatHandler.php` — last_seen_at bump via `facesluiceId` payload field
- `/Users/helderdene/fras/app/Mqtt/Handlers/OnlineOfflineHandler.php` — status transition logic (Phase 19 ports without the `EnrollPersonnelBatch` dispatch — that's Phase 20)
- `/Users/helderdene/fras/config/mqtt-client.php` — subscriber + publisher connection blocks (two connections; MQTT-06 mandates)
- `/Users/helderdene/fras/config/hds.php` §mqtt — topic_prefix, keepalive, reconnect_delay defaults (will move to `config/fras.php` in IRMS)

### IRMS v1.0 conventions + reference code
- `composer.json` `scripts.dev` — current 5-process concurrently block; Phase 19 adds 6th
- `config/horizon.php` `environments.production.supervisor-1` — supervisor block pattern for the new `fras-supervisor` block
- `config/filesystems.php` `disks.local` — private disk pattern; `fras_events` mirrors the structure (private, no url)
- `config/logging.php` — add `channels.mqtt` daily channel
- `routes/channels.php` lines 9–11 — `dispatch.incidents` PrivateChannel auth (reused for MqttListenerHealthChanged)
- `routes/console.php` — Schedule facade usage pattern (IRMS v1.0 has one monthly schedule today; Phase 19 adds `->everyThirtySeconds()` watchdog)
- `app/Events/IncidentCreated.php` — ShouldBroadcast + ShouldDispatchAfterCommit + PrivateChannel + broadcastWith reference shape for MqttListenerHealthChanged
- `app/Http/Controllers/DispatchConsoleController.php` — Inertia shared-prop pattern for initial state wire-through
- `docs/operations/laravel-13-upgrade.md` §8 Supervisor Configuration Reference — reference `[program:irms-horizon]` + `[program:irms-reverb]` blocks; Phase 19 adds `[program:irms-mqtt]` section + deploy protocol addendum
- `app/Enums/CameraStatus.php` (Phase 18) — enum values online/offline/degraded; Phase 19 writes only first two
- `app/Enums/RecognitionSeverity.php` (Phase 18) — info/warning/critical; Phase 19 classifies but doesn't alert on severity

### Carried milestone-level decisions
- `.planning/STATE.md` §Accumulated Context — v2.0 roadmap-level decisions (UUID PKs, MQTT under Supervisor not Horizon, severity→priority mapping, Inertia v2 retained, mapbox-gl rejected)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`IncidentCreated` event pattern** (`app/Events/IncidentCreated.php`) — copy the ShouldBroadcast + ShouldDispatchAfterCommit + PrivateChannel + broadcastWith() shape directly for `MqttListenerHealthChanged`.
- **`dispatch.incidents` channel auth** — already authorizes the exact role set we want to see the banner. No channels.php changes needed for D-10.
- **`composer run dev` concurrently block** — already has 5-process pattern with color + name args; adding the 6th is a one-line extension.
- **Inertia shared-prop pattern** in `DispatchConsoleController::index` — Phase 19 adds one more shared key (`mqtt_listener_health`).
- **Horizon supervisor block pattern** — `config/horizon.php` `environments.production.supervisor-1` maps 1:1 to the new `fras-supervisor` block with queue=['fras'].
- **`routes/console.php` Schedule facade usage** — v1.0 has `Schedule::job(...)` for monthly DILG report; Phase 19 adds `Schedule::command('irms:mqtt-listener-watchdog')->everyThirtySeconds()`.
- **`Log::channel()` idiom** — Laravel-native; just add a new daily channel to `config/logging.php`.
- **Phase 18 `RecognitionEventFactory::critical()` / `->warning()` / `->blockMatch()` states** — `RecognitionHandlerTest` can use these to feed the handler test payloads without standing up Mosquitto.

### Established Patterns
- **ShouldBroadcast + ShouldDispatchAfterCommit on events** — IRMS v1.0 convention; keeps broadcasts consistent with DB commit boundary.
- **PrivateChannel naming**: dotted lowercase (`dispatch.incidents`, `dispatch.units`, `incident.{id}`). Phase 19 stays on `dispatch.incidents` — no new namespace yet.
- **Enum backing** — `$table->string('col')` + `CHECK` constraint + PHP enum class cast. Phase 19 inherits Phase 18's `RecognitionSeverity` / `CameraStatus` enums without modification.
- **Config facade over direct env access** — Phase 19's new `config/fras.php` (ported/renamed from FRAS's `hds.php`) is the single source for topic prefix, keep-alive, retention defaults. Code reads `config('fras.mqtt.topic_prefix')`, never `env('MQTT_TOPIC_PREFIX')` directly.
- **Artisan command classes in `app/Console/Commands/`** — empty directory today; Phase 19 is the first new command family since Phase 16.

### Integration Points
- `app/Console/Commands/FrasMqttListenCommand.php` (NEW) — signature `irms:mqtt-listen`, accepts `--max-time=3600`
- `app/Console/Commands/FrasMqttListenerWatchdogCommand.php` (NEW) — signature `irms:mqtt-listener-watchdog`
- `app/Mqtt/Contracts/MqttHandler.php` (NEW)
- `app/Mqtt/TopicRouter.php` (NEW)
- `app/Mqtt/Handlers/RecognitionHandler.php`, `AckHandler.php`, `HeartbeatHandler.php`, `OnlineOfflineHandler.php` (NEW)
- `app/Events/MqttListenerHealthChanged.php` (NEW)
- `config/fras.php` (NEW — ported from FRAS `hds.php`, renamed, scoped to topic_prefix/keepalive/reconnect/retention-defaults reserved for Phase 22)
- `config/mqtt-client.php` (NEW — ported from FRAS; subscriber + publisher connections for MQTT-06)
- `config/horizon.php` (MOD — add `fras-supervisor` block)
- `config/logging.php` (MOD — add `channels.mqtt` daily channel)
- `config/filesystems.php` (MOD — add `fras_events` private disk)
- `composer.json` (MOD — add 6th concurrently target to `scripts.dev`)
- `routes/console.php` (MOD — Schedule `irms:mqtt-listener-watchdog` every 30s)
- `app/Http/Controllers/DispatchConsoleController.php` (MOD — add `mqtt_listener_health` Inertia shared prop)
- `resources/js/pages/dispatch/Console.vue` (MOD — render banner)
- `resources/js/components/fras/MqttListenerHealthBanner.vue` (NEW — or reuse existing AppBanner if present)
- `resources/js/composables/useDispatchFeed.ts` (MOD — subscribe to `MqttListenerHealthChanged`)
- `docs/operations/irms-mqtt.md` (NEW — dev prerequisite + Supervisor prod block + deploy protocol + smoke test)
- `tests/Feature/Mqtt/TopicRouterTest.php` (NEW — per-pattern routing assertions per Pitfall 18)
- `tests/Feature/Mqtt/RecognitionHandlerTest.php` (NEW — both `personName`/`persionName`, idempotency, unknown-camera drop)
- `tests/Feature/Mqtt/HeartbeatHandlerTest.php`, `AckHandlerTest.php`, `OnlineOfflineHandlerTest.php` (NEW)
- `tests/Feature/Mqtt/MqttListenerWatchdogTest.php` (NEW — HEALTHY / SILENT / NO_ACTIVE_CAMERAS state transitions + broadcast assertion)

### Known touchpoints that DO NOT change in Phase 19
- `config/broadcasting.php` — no change (Reverb already configured; no new connections)
- `app/Providers/AppServiceProvider.php` — no change in Phase 19 (Phase 21 will bind `FrasIncidentFactoryInterface`; Phase 19 does not introduce the interface)
- `routes/channels.php` — no change (reusing `dispatch.incidents`)
- `bootstrap/app.php` — no change (no new middleware, no new routes outside `web`/`console`)
- `database/migrations/` — no change (Phase 18 schema is frozen)
- `app/Models/Camera.php`, `Personnel.php`, `CameraEnrollment.php`, `RecognitionEvent.php` — no schema-level changes; Phase 19 may add minor scopes/accessors during implementation but no structural edits

</code_context>

<specifics>
## Specific Ideas

- **"Register the camera first, then the camera is configured to publish."** The unknown-camera drop rule (D-14) is operational discipline, not a data loss. CDRRMO admin workflow is: add Camera via `/admin/cameras` (Phase 20) → receive MQTT credentials + topic prefix → configure physical camera to publish. A RecPush from an unknown `device_id` is a misconfiguration signal — surface it in the `mqtt` log so an admin can investigate, don't pollute `cameras` with unclaimed rows.
- **"Inline handlers + fras queue registered = forward compatibility without extra runtime cost."** The `fras-supervisor` block sits idle in Phase 19 (no jobs enqueue to it). Phase 20 `EnrollPersonnelBatch` + Phase 22 retention purge move in without any horizon config change. Decided worth the cosmetic asymmetry.
- **"Watchdog arms only when ≥1 active camera."** Prevents the banner from firing during fresh installs, all-cameras-decommissioned states, or dev environments with no seeded cameras. The `NO_ACTIVE_CAMERAS` enum value on the broadcast lets the UI render nothing (correct) instead of "healthy" (misleading) or "silent" (false alarm).
- **"Last-message-received, not last-loop-tick."** MQTT-05 says "3 missed heartbeats" — heartbeats ARE messages, so tracking last-message unifies process-alive + broker-connected into one signal without false-fire risk at ≥1 active camera.
- **"Dedicated mqtt log channel."** Mandated by Pitfall 6. Keeps dispatch console debugging clean and makes the post-deploy smoke test a one-liner.
- **"Supervisor reference block goes in docs/operations/irms-mqtt.md."** Mirror the pattern in `docs/operations/laravel-13-upgrade.md` §8. Phase 17 established the operations-doc convention; Phase 19 extends it.
- **"D-04 keeps FRAS's TopicRouter shape; D-17 mqtt log channel absorbs what Pitfall 6 calls out."** When in doubt, port FRAS verbatim + add the Pitfall mitigations on top. IRMS-ism is added where it integrates with existing v1.0 patterns (Inertia shared prop, broadcast channel, Schedule facade), not where it would diverge from a working FRAS shape.

</specifics>

<deferred>
## Deferred Ideas

- **`CameraStatus::Degraded` semantics** — Phase 20 watchdog command decides the heartbeat-gap thresholds for degraded vs offline. Phase 19 only writes online/offline.
- **Auto-create stub Camera row from unknown RecPush** — considered and rejected (D-14 drops with warning). Revisit if operational feedback from Phase 20-22 shows "pre-registration events would have been useful history."
- **Dead-letter `fras_unroutable_events` table** — not needed for Phase 19. Add if Phase 21/22 recognition-volume analytics show that dropped events become a forensics gap.
- **Separate loop-tick heartbeat** (process-alive signal independent of message-received) — deferred. Revisit if prod incidents show "broker silent but listener looping" scenarios that D-06's last-message-only signal misses.
- **Topic subscription `retain` flag / ResumefromBreakpoint** — explicitly out of scope per PITFALLS Pitfall 17. FRAS v1.0 decided against, IRMS inherits.
- **Stranger-detection MQTT `Snap` topic** — REQUIREMENTS.md §Out-of-scope: "low value for CDRRMO dispatch, high noise." TopicRouter does not subscribe.
- **`EnrollPersonnelBatch` jobs on the `fras` queue** — Phase 20. Phase 19 registers the queue only.
- **`FrasPhotoProcessor` (Intervention Image v4 resize + MD5)** — Phase 20 enrollment photo pipeline. Phase 19 stores raw base64-decoded bytes via `Storage::disk('fras_events')->put()` with size cap validation only; no resize.
- **`FrasIncidentFactory` + IoT-channel bridge** — Phase 21 integration seam. Phase 19's `RecognitionHandler` writes `recognition_events.incident_id = null`; Phase 21 sets it when Critical severity + confidence threshold triggers.
- **Signed 5-min URLs for image access, `fras_access_log` audit table** — Phase 22 DPA compliance. Phase 19 stores images on a private disk with no HTTP surface.
- **`fras.alerts` / `fras.cameras` / `fras.enrollments` private channels** — Phase 20 (cameras), Phase 22 (alerts). Phase 19 reuses `dispatch.incidents` for the listener-health banner only.
- **Post-deploy smoke test as a CI step** — worth a dedicated runbook task in Phase 19 (`docs/operations/irms-mqtt.md` §Deploy Smoke Test), but not a CI gate. Revisit in v2.1.
- **TLS posture for cameras on a routed subnet** — `MQTT_TLS_ENABLED=false` default for CDRRMO's LAN-only deployment. Documented in the deploy runbook; decision flag for future multi-site deployments.
- **php-mqtt reconnect exponential backoff** — the library supports `max_reconnect_attempts` + `delay_between_reconnect_attempts` config; Phase 19 uses the FRAS defaults (10 attempts / 5s delay). Revisit if prod broker instability shows thundering-herd reconnects.

</deferred>

---

*Phase: 19-mqtt-pipeline-listener-infrastructure*
*Context gathered: 2026-04-21*
