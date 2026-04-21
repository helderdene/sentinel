---
status: testing
phase: 19-mqtt-pipeline-listener-infrastructure
source:
  - 19-01-SUMMARY.md
  - 19-02-SUMMARY.md
  - 19-03-SUMMARY.md
  - 19-04-SUMMARY.md
  - 19-05-SUMMARY.md
  - 19-06-SUMMARY.md
started: 2026-04-21T21:10:00Z
updated: 2026-04-21T21:10:00Z
---

## Current Test

number: 5
name: Dispatch console loads with banner component present
expected: |
  Visit `/dispatch` while logged in as a user with `operator`, `dispatcher`, `supervisor`, or `admin` role.
  Page loads without console errors.
  No banner is visible (initial state `NO_ACTIVE_CAMERAS` = neutral, no banner rendered per D-09/D-11).
  Open DevTools → Elements: confirm `MqttListenerHealthBanner` component is present but collapsed (guard hides it).
awaiting: user response
note: |
  IMPORTANT: after the handler fix committed during this UAT session, please restart `composer run dev` (Ctrl+C then re-run) so the listener picks up the new Heartbeat/OnlineOffline logic before continuing.
  Alternatively this test only requires the dispatch page to render — you can run it against the current /dispatch route; banner subscription picks up new events via Echo independently of handler changes.

## Tests

### 1. Cold Start Smoke Test
expected: |
  `composer run dev` starts 6 processes. The 6th is `mqtt` (orange, running `php artisan irms:mqtt-listen`). Other 5 start normally. If Mosquitto isn't running, mqtt logs an error to storage/logs/mqtt-*.log and exits — no silent failure.
result: pass
evidence: |
  Screenshot confirmed: concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#f59e0b" launched 6 processes. MQTT listener log: "Connection with broker established successfully", clientId=irms-mqtt-sub-Denes-MacBook-Pro.local, topics=[mqtt/face/+/Rec, mqtt/face/+/Ack, mqtt/face/basic, mqtt/face/heartbeat], max_time=3600. Mosquitto is running locally. All 5 pre-existing processes started normally (server, vite, logs, reverb, horizon).

### 2. Artisan commands registered
expected: |
  Run `php artisan list | grep irms:mqtt`. You should see both:
  - `irms:mqtt-listen` — "Subscribe to Mosquitto, route topics to handlers"
  - `irms:mqtt-listener-watchdog` — "Detect silent MQTT listener and broadcast health"
result: pass
evidence: |
  `php artisan list | grep irms:mqtt` output:
    irms:mqtt-listen             Subscribe to camera MQTT topics and route messages to handlers
    irms:mqtt-listener-watchdog  Detect MQTT listener silence and broadcast health transitions

### 3. Listener --max-time clean exit
expected: |
  Run `php artisan irms:mqtt-listen --max-time=2`.
  The command runs for ~2 seconds and exits with code 0 (even if Mosquitto is unreachable — SIGALRM interrupt is the canonical trigger).
  If Mosquitto is running, no error. If not, the connection error is logged to storage/logs/mqtt-*.log but the command still exits cleanly.
result: pass
evidence: |
  Covered by `tests/Feature/Mqtt/ListenerCommandMaxTimeTest.php` (ran as part of test 4): Mockery-mocks MQTT::connection, invokes command with --max-time=1, asserts exit code 0 within 2s. Live re-run skipped because listener is already active under `composer run dev` and would contend for the same subscription. Automated test provides deterministic coverage.

### 4. Full MQTT test suite green
expected: |
  Run `php artisan test --compact tests/Feature/Mqtt/ tests/Feature/Dispatch/DispatchConsoleMqttHealthPropTest.php tests/Unit/Enums/RecognitionSeverityFromEventTest.php`.
  All tests pass (62 MQTT + 4 dispatch prop + 11 severity classifier = ~77 tests). No failures, no skips.
result: pass
evidence: |
  Output: 71 passed (166 assertions), Duration: 4.03s. Includes TopicRouterTest (7), RecognitionHandlerTest (10), HeartbeatHandlerTest (3), OnlineOfflineHandlerTest (5), AckHandlerTest (2), MqttClientConfigTest (5), MqttListenerHealthChangedTest (4), MqttListenerWatchdogTest (7), ListenerCommandRegistrationTest (4), ListenerCommandMaxTimeTest (4), ComposerScriptTest (4), OperationsDocTest (6), DispatchConsoleMqttHealthPropTest (4), RecognitionSeverityFromEventTest (6).

### 5. Dispatch console loads with banner component present
expected: |
  Visit `/dispatch` while logged in as a user with `operator`, `dispatcher`, `supervisor`, or `admin` role.
  Page loads without console errors.
  No banner is visible (initial state `NO_ACTIVE_CAMERAS` = neutral, no banner rendered per D-09/D-11).
  Open DevTools → Elements: confirm `MqttListenerHealthBanner` component is present but collapsed (guard hides it).
result: [pending]

### 6. Operations runbook exists and is complete
expected: |
  Open `docs/operations/irms-mqtt.md`. You should see:
  - Dev prerequisite section mentioning `brew install mosquitto` / `apt install mosquitto mosquitto-clients`
  - Supervisor `[program:irms-mqtt]` block (not under Horizon — Pitfall 6 mandate)
  - Deploy protocol: `sudo supervisorctl restart irms-mqtt:*` (Pitfall 7)
  - Post-deploy smoke test runbook section with `mosquitto_pub` one-liner
result: pass
evidence: |
  Grep counts: `brew install mosquitto|apt install mosquitto`: 1 match, `[program:irms-mqtt]`: 1 match, `supervisorctl restart irms-mqtt`: 1 match, `smoke test|Smoke Test|mosquitto_pub`: 6 matches. All required sections present. Plan 19-06 `OperationsDocTest.php` locks this structure (included in test 4 suite).

### 7. Live RecPush smoke test — personName spelling (cloud broker 148.230.99.73)
expected: |
  Publish RecPush with camelCase payload fields (deviceId, recordId, personName, personType, verifyStatus, similarity, faceImage, sceneImage, capturedAt).
  Verify: one recognition_events row with raw_payload JSONB, face + scene images on fras_events disk date-partitioned.
result: pass
evidence: |
  Seeded camera device_id=irms-smoketest-01 (uuid 019db008-db4d-712f-a1db-5f9bccea548c). Published recordId=1001 via mosquitto_pub -h 148.230.99.73.
  DB row created: id=019db00a-3479-71ad-8a6a-809a9bcb682c, name_from_camera=John Doe, severity=critical.
  Images on disk: storage/app/private/fras_events/2026-04-21/{faces,scenes}/019db00a-3479-71ad-8a6a-809a9bcb682c.jpg (195 bytes each, mode 0600).

### 8. Live RecPush — persionName firmware typo fallback (cloud broker)
expected: |
  Publish with firmware typo `persionName` key. Handler accepts both spellings (18-04 D-61). Row persists.
result: pass
evidence: |
  Published recordId=1002 with persionName="Jane Typo". Row created id=019db00a-9fbc-721e-a721-f9382b488b30, name_from_camera="Jane Typo". Firmware-typo fallback confirmed.

### 9. Idempotency — duplicate RecPush (cloud broker)
expected: |
  Second publish of same (camera_id + recordId) does not create new row. Log shows "Duplicate RecPush rejected at DB layer" (D-03).
result: pass
evidence: |
  Re-published recordId=1001 with different personName ("Duplicate Attempt"). Final row count for recordId=1001 = 1 (unchanged).
  mqtt log: "Duplicate RecPush rejected at DB layer {camera_id: 019db008-db4d-712f-a1db-5f9bccea548c, record_id: 1001}".

### 10. Unknown camera drop (cloud broker)
expected: |
  RecPush with deviceId NOT in cameras table → no row, no images, warning log only (D-14).
result: pass
evidence: |
  Published deviceId="unknown-camera-xyz" with recordId=9999. 0 rows in recognition_events.
  mqtt log: "RecPush for unknown camera {device_id: unknown-camera-xyz, topic: mqtt/face/unknown-camera-xyz/Rec}".

### 11. Listener silence → dispatch banner (requires Mosquitto + ≥1 active camera)
expected: |
  Kill the `mqtt` process (Ctrl+C or `supervisorctl stop irms-mqtt:*`) while at least one active Camera exists.
  Wait 90-120 seconds (watchdog runs every 30s, silence threshold is 90s).
  Dispatch console banner turns red with status `SILENT` + "Last message: {ISO timestamp}".
  Restarting the listener (`supervisorctl start irms-mqtt:*` or re-running `php artisan irms:mqtt-listen`) clears the banner within 30s (next watchdog tick sees liveness key resume).
result: [pending]

### 12. Horizon isolation — MQTT listener untouched by horizon:terminate (requires Supervisor)
expected: |
  With both Horizon and `irms-mqtt` running under Supervisor:
  ```bash
  sudo supervisorctl status irms-horizon:*
  sudo supervisorctl status irms-mqtt:*
  ```
  Both show RUNNING.
  Run `php artisan horizon:terminate`.
  Re-check status — Horizon processes restart; irms-mqtt PIDs DO NOT change.
  This confirms Pitfall 6 mitigation (listener NOT under Horizon).
result: [pending]

## Summary

total: 12
passed: 8
issues: 1
pending: 3
skipped: 0

## Gaps

- truth: "Heartbeat + basic handlers must parse real-hardware payloads (info.facesluiceId nested, not top-level)"
  status: fixed_during_uat
  reason: "UAT against live broker 148.230.99.73 revealed real cameras (facesluiceId 1026700, 1026701) publish {info: {facesluiceId: ...}, operator: Online, ...} — nested shape. FRAS reference code used `$data['info']['facesluiceId']` verbatim (confirmed in /Users/helderdene/fras/app/Mqtt/Handlers/). IRMS port assumed top-level `facesluiceId` per the plan's synthetic test payloads, which worked in Pest but failed against real hardware. Fixed inline: both HeartbeatHandler and OnlineOfflineHandler now accept `info.facesluiceId` first, falling back to top-level for synthetic test compatibility. Added 2 regression tests (real-hardware shape) to lock the fix. All 10 handler tests pass. Commit: fix(19) accept nested info.facesluiceId"
  severity: major
  test: "Discovered during test 7 execution (visible in mqtt log tail)"
  artifacts:
    - app/Mqtt/Handlers/HeartbeatHandler.php
    - app/Mqtt/Handlers/OnlineOfflineHandler.php
    - tests/Feature/Mqtt/HeartbeatHandlerTest.php
    - tests/Feature/Mqtt/OnlineOfflineHandlerTest.php
  missing: []

## Phase 19 UAT Summary (interim)

**Automated + live-broker verification complete for 8/12 tests:**
- Tests 1, 2, 3, 4, 6 — structural + automated (pass)
- Tests 7, 8, 9, 10 — live publish against cloud broker 148.230.99.73 (pass)

**One issue found and fixed during UAT (major severity):**
- Real-hardware `info.facesluiceId` payload shape not parsed → fix committed inline

**Remaining pending:**
- Test 5: Dispatch console banner visibility (requires browser)
- Test 11: Listener silence → banner (requires killing listener + 90-120s wait; complicated by real-camera traffic on broker — need to stop listener but keep watchdog running)
- Test 12: Horizon isolation under Supervisor (requires production-like Supervisor setup)
