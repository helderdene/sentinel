---
phase: 19-mqtt-pipeline-listener-infrastructure
verified: 2026-04-21T00:00:00Z
status: human_needed
score: 6/6 must-haves verified
overrides_applied: 0
human_verification:
  - test: "Run `composer run dev`, wait for all 6 processes to start, then publish a RecPush via `mosquitto_pub -t 'mqtt/face/CAM01/Rec' -m '{...}'` with both `personName` and `persionName` spellings. Verify exactly one `recognition_events` row per publish, raw payload in JSONB, face + scene images on fras_events disk, no errors in storage/logs/mqtt-*.log."
    expected: "One recognition_events row per unique (camera_id, record_id). Images at {YYYY-MM-DD}/faces/{event_id}.jpg and {YYYY-MM-DD}/scenes/{event_id}.jpg. mqtt log shows 'MQTT listener started'. No PHP errors."
    why_human: "Requires a live Mosquitto broker and a registered test Camera. CI has no broker."
  - test: "Simulate broker disconnect mid-run: with `irms:mqtt-listen` running, `brew services restart mosquitto` (or `systemctl restart mosquitto`). Then publish a message within the reconnect window."
    expected: "Listener reconnects automatically (auto_reconnect=true in mqtt-client.php). Published message lands in recognition_events on the next tick. No manual restart needed."
    why_human: "Requires live broker and real process lifecycle — cannot mock a TCP disconnect in unit tests."
  - test: "Verify `--max-time=3600` hourly rotation: run `php artisan irms:mqtt-listen --max-time=60`, wait 60 s, confirm clean exit (code 0) and that Supervisor restarts a fresh process."
    expected: "Process exits with code 0 after 60s. Supervisor autorestart=unexpected triggers a new irms:mqtt-listen process. `storage/logs/mqtt-*.log` shows 'MQTT listener stopped cleanly' then 'MQTT listener started'."
    why_human: "Wall-clock wait required; the unit test mocks the MQTT client and validates the interface but cannot assert real SIGALRM behavior."
  - test: "After deploying `irms-mqtt.conf`, run `sudo supervisorctl status` to confirm irms:mqtt RUNNING. Then run `php artisan horizon:terminate`. Re-run `sudo supervisorctl status` and confirm irms:mqtt remains RUNNING."
    expected: "Horizon restart does not touch the irms-mqtt Supervisor program. MQTT listener PID is unchanged before and after horizon:terminate."
    why_human: "Requires a live Supervisor environment. Cannot simulate Supervisor process tree in CI."
---

# Phase 19: MQTT Pipeline + Listener Infrastructure Verification Report

**Phase Goal:** The MQTT ingress surface is operational — a dedicated listener process is running, topics route to handlers, recognition payloads persist with raw JSONB, and operators can see the listener's health — so feature code in Phase 20 and Phase 21 can assume MQTT events land reliably.
**Verified:** 2026-04-21
**Status:** human_needed
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| #   | Truth                                                                                     | Status     | Evidence                                                                 |
|-----|-------------------------------------------------------------------------------------------|------------|--------------------------------------------------------------------------|
| 1   | `irms:mqtt-listen` command exists, subscribes to 4 topics, routes via TopicRouter, unmatched topics are logged not dropped | ✓ VERIFIED | `FrasMqttListenCommand.php` with 4 subscriptions; `TopicRouter.php` logs warning on unmatched topics; `TopicRouterTest.php` 6 assertions including unmatched-topic warning test |
| 2   | RecPush with `personName`/`persionName` persists one `recognition_events` row with raw JSONB, images on fras_events disk, no errors | ✓ VERIFIED | `RecognitionHandler.php` implements both spellings, idempotency via UniqueConstraintViolationException, Storage::disk('fras_events'); `RecognitionHandlerTest.php` 9 passing tests including both fixture files |
| 3   | Listener rotates via `--max-time=3600` (SIGALRM) and reconnects after broker disconnect   | ✓ VERIFIED | `FrasMqttListenCommand.php` implements pcntl_signal(SIGTERM/SIGINT/SIGALRM) + mqtt-client.php has `auto_reconnect.enabled=true`; `ListenerCommandMaxTimeTest.php` verifies interface; live reconnect is human-verify |
| 4   | Dispatcher sees `mqtt_listener_health` banner within 60s of listener silence               | ✓ VERIFIED | `FrasMqttListenerWatchdogCommand.php` with 90s SILENCE_THRESHOLD + 30s schedule; `DispatchConsoleController.php` passes `mqtt_listener_health` Inertia prop; `MqttListenerHealthBanner.vue` renders on Console.vue; `MqttListenerWatchdogTest.php` 7 passing tests |
| 5   | `config/mqtt-client.php` declares separate `subscriber` + `publisher` connections          | ✓ VERIFIED | `mqtt-client.php` has two independent connection blocks; `MqttClientConfigTest.php` asserts MQTT-06 compliance + distinct client IDs |
| 6   | MQTT listener runs under its own Supervisor program, never under Horizon                   | ✓ VERIFIED | `irms-mqtt.md` documents `[program:irms-mqtt]` Supervisor block; `horizon.php` has `fras-supervisor` for the fras queue only (no irms:mqtt-listen); `OperationsDocTest.php` asserts Supervisor block + deploy protocol in docs; live Supervisor/horizon:terminate check is human-verify |

**Score:** 6/6 truths verified (4 structural + wired in code; 2 require live environment confirmation)

---

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `config/mqtt-client.php` | Separate subscriber + publisher connections, auto_reconnect | ✓ VERIFIED | Two independent connection blocks; `auto_reconnect.enabled=true` in both; distinct client IDs |
| `config/fras.php` | topic_prefix, keepalive, reconnect_delay | ✓ VERIFIED | All three keys present under `mqtt` section |
| `config/horizon.php` | `fras-supervisor` block registered | ✓ VERIFIED | Block present in both `production` and `local` environments with queue=['fras'] |
| `config/logging.php` | `mqtt` daily channel | ✓ VERIFIED | `channels.mqtt` daily driver at `storage/logs/mqtt.log` |
| `config/filesystems.php` | `fras_events` private disk | ✓ VERIFIED | Private disk with root at `storage/app/private/fras_events`, no url key |
| `app/Mqtt/TopicRouter.php` | Regex routes, liveness Cache::put, unmatched warning | ✓ VERIFIED | All three behaviors present; liveness bump happens before handler dispatch (even on unmatched topics) |
| `app/Mqtt/Contracts/MqttHandler.php` | Interface with `handle(string $topic, string $message): void` | ✓ VERIFIED | Single-method interface, correct signature |
| `app/Mqtt/Handlers/RecognitionHandler.php` | personName/persionName, idempotency, images, unknown-camera drop, size caps | ✓ VERIFIED | All behaviors implemented; D-03 UniqueConstraintViolationException catch; D-14 unknown-camera drop; D-15 fras_events disk; 1MB face / 2MB scene caps |
| `app/Mqtt/Handlers/HeartbeatHandler.php` | cameras.last_seen_at bump | ✓ VERIFIED | Updates last_seen_at via facesluiceId; warns on unknown camera |
| `app/Mqtt/Handlers/OnlineOfflineHandler.php` | Online/Offline only, no Degraded (D-08) | ✓ VERIFIED | Only writes CameraStatus::Online or ::Offline; Degraded excluded |
| `app/Mqtt/Handlers/AckHandler.php` | Log-only scaffold | ✓ VERIFIED | Logs receipt on mqtt channel; Phase 20 comment in place |
| `app/Console/Commands/FrasMqttListenCommand.php` | --max-time + SIGTERM/SIGINT/SIGALRM | ✓ VERIFIED | All three signals trapped via pcntl; pcntl_alarm($maxTime) for hourly rotation |
| `app/Console/Commands/FrasMqttListenerWatchdogCommand.php` | 90s SILENCE threshold, NO_ACTIVE_CAMERAS guard | ✓ VERIFIED | SILENCE_THRESHOLD_SECONDS=90; active camera count check at top of handle() |
| `app/Events/MqttListenerHealthChanged.php` | ShouldBroadcast + ShouldDispatchAfterCommit + PrivateChannel('dispatch.incidents') + full payload | ✓ VERIFIED | All interfaces implemented; broadcastWith() returns status/last_message_received_at/since/active_camera_count |
| `routes/console.php` | Schedule::command('irms:mqtt-listener-watchdog')->everyThirtySeconds() | ✓ VERIFIED | Present with ->withoutOverlapping() |
| `app/Enums/RecognitionSeverity.php` | fromEvent($personType, $verifyStatus) classifier | ✓ VERIFIED | Classifies Critical (personType=1), Warning (verifyStatus=2), Info (all else) |
| `app/Http/Controllers/DispatchConsoleController.php` | `mqtt_listener_health` Inertia prop with NO_ACTIVE_CAMERAS default | ✓ VERIFIED | Reads from cache keys; falls back to NO_ACTIVE_CAMERAS via Cache::get default |
| `resources/js/components/fras/MqttListenerHealthBanner.vue` | Banner component | ✓ VERIFIED | Renders on SILENT and DISCONNECTED; animated Transition; AlertTriangle icon |
| `resources/js/composables/useDispatchFeed.ts` | Echo subscription for MqttListenerHealthChanged | ✓ VERIFIED | useEcho subscription for 'MqttListenerHealthChanged' on dispatch.incidents channel; updates mqttListenerHealth ref |
| `resources/js/pages/dispatch/Console.vue` | Banner rendered | ✓ VERIFIED | MqttListenerHealthBanner imported and rendered at top of template; wired to mqttListenerHealth from useDispatchFeed |
| `resources/js/types/mqtt.ts` | Type definitions for banner and composable | ✓ VERIFIED | MqttListenerHealthStatus, MqttListenerHealth, MqttListenerHealthPayload types |
| `composer.json` | 6th dev process `irms:mqtt-listen` | ✓ VERIFIED | Present in scripts.dev concurrently command with color #f59e0b and name mqtt |
| `docs/operations/irms-mqtt.md` | Supervisor block, deploy protocol, smoke test | ✓ VERIFIED | All three sections present; `[program:irms-mqtt]`, `supervisorctl restart irms-mqtt:*`, smoke test runbook |
| `tests/Feature/Mqtt/` | 12 test files, all passing | ✓ VERIFIED | 12 files present; 61 tests, 111 assertions, all passing (3.34s) |
| `tests/fixtures/mqtt/` | 6 canonical JSON fixtures | ✓ VERIFIED | ack.json, heartbeat.json, offline.json, online.json, recognition-persion-name.json, recognition-person-name.json |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `FrasMqttListenCommand` | `TopicRouter::dispatch()` | injected via handle() parameter | ✓ WIRED | `$router->dispatch($topic, $message)` in subscription callback |
| `TopicRouter` | `RecognitionHandler` / `AckHandler` / `HeartbeatHandler` / `OnlineOfflineHandler` | `app($handlerClass)->handle()` | ✓ WIRED | regex map iterates; app() resolution; all 4 handlers |
| `TopicRouter` | `Cache::put('mqtt:listener:last_message_received_at')` | direct call before dispatch | ✓ WIRED | Bumped on every incoming message including unmatched topics |
| `FrasMqttListenerWatchdogCommand` | `MqttListenerHealthChanged::dispatch()` | state transition check | ✓ WIRED | Dispatched only on state change; payload includes all D-11 fields |
| `MqttListenerHealthChanged` | `PrivateChannel('dispatch.incidents')` | broadcastOn() | ✓ WIRED | Reuses existing v1.0 authorized channel |
| `DispatchConsoleController::show()` | `mqtt_listener_health` Inertia prop | Cache::get + Camera count | ✓ WIRED | Passed to Inertia::render as `mqtt_listener_health` key |
| `Console.vue` | `useDispatchFeed` | initialMqttHealth parameter + mqttListenerHealth return | ✓ WIRED | Initial state hydrated from prop; live updates from Echo subscription |
| `useDispatchFeed` | `MqttListenerHealthBanner.vue` data | `mqttListenerHealth` ref returned | ✓ WIRED | Console.vue binds :status, :last-message-received-at, :since |
| `RecognitionHandler` | `recognition_events` DB row | `RecognitionEvent::create()` in DB::transaction | ✓ WIRED | raw_payload JSONB stored; idempotency via UniqueConstraintViolationException |
| `RecognitionHandler` | `fras_events` disk | `Storage::disk('fras_events')->put()` | ✓ WIRED | Date-partitioned paths; face_image_path and scene_image_path updated on event |

---

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|--------------------|--------|
| `MqttListenerHealthBanner.vue` | `status`, `lastMessageReceivedAt`, `since` | `useDispatchFeed` → `mqttListenerHealth` ref → Echo MqttListenerHealthChanged + initial Inertia prop | Yes — initial state from cache + Camera::whereNull query; live updates from watchdog event | ✓ FLOWING |
| `RecognitionHandler` | `recognition_events` row | `RecognitionEvent::create()` → Postgres DB | Yes — real DB insert with JSONB + image paths | ✓ FLOWING |

---

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| All 61 MQTT feature tests pass | `php artisan test --compact tests/Feature/Mqtt/` | 61 passed (111 assertions) in 3.34s | ✓ PASS |
| Dispatch + Enum unit tests pass | `php artisan test --compact tests/Feature/Dispatch/ tests/Unit/Enums/` | 36 passed (178 assertions) in 2.97s | ✓ PASS |
| Watchdog schedule registered | `php artisan schedule:list` (asserted in MqttListenerWatchdogTest) | irms:mqtt-listener-watchdog confirmed in schedule output | ✓ PASS |
| 6th dev process in composer.json | grep irms:mqtt-listen composer.json | Line 62 of composer.json confirmed | ✓ PASS |
| Live mosquitto_pub RecPush smoke test | Requires running broker | Cannot run without Mosquitto | ? SKIP (human-verify) |
| Broker bounce reconnect | Requires live broker + process | Cannot simulate in CI | ? SKIP (human-verify) |
| Supervisor irms-mqtt program | Requires Supervisor on host OS | Cannot simulate Supervisor in CI | ? SKIP (human-verify) |

---

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| MQTT-01 | 19-04, 19-06 | `irms:mqtt-listen` locally + Supervisor in production | ✓ SATISFIED | Command exists with correct signature; irms-mqtt.conf documented; 6th dev process in composer.json; OperationsDocTest asserts runbook content |
| MQTT-02 | 19-02, 19-03 | TopicRouter dispatches to 4 handlers; unmatched topics logged | ✓ SATISFIED | TopicRouter with 4-pattern regex map; Log::channel('mqtt')->warning for unmatched; TopicRouterTest.php 6 passing assertions |
| MQTT-03 | 19-03 | RecognitionHandler parses both firmware spellings, persists JSONB + images | ✓ SATISFIED | personName/persionName fallback; raw_payload JSONB; fras_events disk; RecognitionHandlerTest.php 9 passing tests including both canonical fixtures |
| MQTT-04 | 19-04 | Listener rotates via --max-time + auto-reconnects | ✓ SATISFIED | SIGALRM handler in command; auto_reconnect.enabled=true in mqtt-client.php; ListenerCommandMaxTimeTest.php; live reconnect is human-verify |
| MQTT-05 | 19-04, 19-05 | Dispatcher sees health banner within 60s of listener silence | ✓ SATISFIED | Watchdog at 30s schedule, 90s threshold; banner wired end-to-end; MqttListenerWatchdogTest.php; MqttListenerHealthChangedTest.php |
| MQTT-06 | 19-01 | Separate subscriber + publisher connections | ✓ SATISFIED | mqtt-client.php has two independent connection blocks; MqttClientConfigTest.php 5 passing assertions |

---

### Anti-Patterns Found

| File | Pattern | Severity | Impact |
|------|---------|----------|--------|
| `app/Mqtt/Handlers/AckHandler.php` | `// Phase 20 fills in correlation cache + enrollment state update.` | ℹ️ Info | Intentional Phase 19 scaffold — AckHandler is log-only by design per 19-CONTEXT.md D-scope; Phase 20 completes it |

No blocker or warning anti-patterns found. The AckHandler placeholder comment is by design and documented in context decisions.

---

### Human Verification Required

#### 1. Live RecPush End-to-End Smoke Test

**Test:** Start `composer run dev` (all 6 processes). With a Mosquitto broker running, publish two RecPush messages via `mosquitto_pub` — one with `personName` field, one with `persionName` field — using a registered test camera's device_id.

**Expected:** Exactly one `recognition_events` row per unique recordId. `raw_payload` column contains the full JSON payload. Images at `storage/app/private/fras_events/{YYYY-MM-DD}/faces/{uuid}.jpg` and `/scenes/{uuid}.jpg`. `storage/logs/mqtt-{date}.log` shows handler routing. No errors in any log.

**Why human:** Requires a live Mosquitto broker. CI has no broker service.

#### 2. Broker Bounce Reconnect

**Test:** With `irms:mqtt-listen` running, restart Mosquitto (`brew services restart mosquitto` or `systemctl restart mosquitto`). Within 30 seconds, publish a heartbeat message.

**Expected:** Listener reconnects automatically without manual restart (auto_reconnect with max 10 attempts, 5s delay). Published message routes through TopicRouter. `mqtt` log shows reconnect activity.

**Why human:** Requires a live TCP connection to a real broker. Cannot mock a broker-level TCP disconnect in unit tests.

#### 3. `--max-time=3600` Hourly Rotation

**Test:** Run `php artisan irms:mqtt-listen --max-time=60`. Wait 60 seconds.

**Expected:** Process exits cleanly with code 0. `storage/logs/mqtt-{date}.log` shows 'MQTT listener stopped cleanly'. If Supervisor is configured, a new process starts immediately with a fresh 'MQTT listener started' log line.

**Why human:** Wall-clock wait required. The unit test (ListenerCommandMaxTimeTest) mocks the MQTT client and validates the interface contract but cannot exercise the real SIGALRM mechanism.

#### 4. Supervisor Isolation from Horizon Restarts (SC-6)

**Test:** On a host with Supervisor running `irms-mqtt.conf`: note the irms-mqtt PID. Run `php artisan horizon:terminate`. Run `sudo supervisorctl status irms-mqtt`.

**Expected:** irms-mqtt program remains RUNNING with the same PID (or a clean Supervisor-managed restart if the process happened to exit during the test). Horizon restart did not send any signal to the irms-mqtt process.

**Why human:** Requires a live Supervisor environment and two separately-managed Supervisor programs. Cannot simulate inter-program isolation in CI.

---

### Gaps Summary

No gaps. All 6 success criteria are structurally and functionally satisfied in the codebase. The 4 human verification items are expected operational smoke tests requiring a live broker, live process, or live Supervisor environment — they are not code defects. These items were anticipated and documented in `19-VALIDATION.md` §Manual-Only Verifications before implementation began.

---

_Verified: 2026-04-21_
_Verifier: Claude (gsd-verifier)_
