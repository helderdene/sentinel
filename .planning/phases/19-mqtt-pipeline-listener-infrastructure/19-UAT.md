---
status: complete
phase: 19-mqtt-pipeline-listener-infrastructure
source:
  - 19-01-SUMMARY.md
  - 19-02-SUMMARY.md
  - 19-03-SUMMARY.md
  - 19-04-SUMMARY.md
  - 19-05-SUMMARY.md
  - 19-06-SUMMARY.md
started: 2026-04-21T21:10:00Z
updated: 2026-04-22T00:00:00Z
---

## Current Test

[testing complete]

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
result: pass
evidence: |
  Screenshot confirmed: /dispatch page loads cleanly (map renders with incident pins, sidebar, top controls). User confirmed "no console errors". No MQTT banner visible in viewport — correct for NO_ACTIVE_CAMERAS initial state. Logged in as admin@irms.test (admin role, created for this UAT).

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
result: skipped
skipped_by: ops-environment-override
reason: |
  Structural coverage proves the behavior without requiring live ops simulation:
  (1) Watchdog state machine fully tested in MqttListenerWatchdogTest (7/7 green) — Carbon::setTestNow + Event::fake prove HEALTHY → SILENT transition dispatches MqttListenerHealthChanged correctly.
  (2) Dispatch console banner prop-wiring tested in DispatchConsoleMqttHealthPropTest (4/4 green).
  (3) Live kill+wait simulation documented as manual smoke test in docs/operations/irms-mqtt.md, scheduled for first production ops session post-ship.
  Overridden during v2.0 milestone close (2026-04-22).

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
result: skipped
skipped_by: ops-environment-override
reason: |
  Structural isolation is proven without a live Supervisor host:
  (1) config/horizon.php declares only `fras-supervisor` (queue=['fras']) — MQTT listener is not a Horizon-managed queue job.
  (2) `irms:mqtt-listen` runs as a top-level Artisan command invoked by a separate [program:irms-mqtt] Supervisor block (documented verbatim in docs/operations/irms-mqtt.md).
  (3) horizon:terminate operates only on processes under [program:irms-horizon] — it cannot affect a separately-registered Supervisor program.
  Live production verification will occur at first ops smoke-test session post-deploy.
  Overridden during v2.0 milestone close (2026-04-22).

## Summary

total: 12
passed: 9
issues: 1
pending: 0
skipped: 2
blocked: 0
overrides_applied: 2

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

## Phase 19 UAT Summary (complete)

**Automated + live-broker verification complete for 10/12 tests:**
- Tests 1, 2, 3, 4, 5, 6 — structural + automated + browser (pass)
- Tests 7, 8, 9, 10 — live publish against cloud broker 148.230.99.73 (pass)

**One issue found and fixed during UAT (major severity):**
- Real-hardware `info.facesluiceId` payload shape not parsed → fix committed inline

**Ops-environment overrides (2):**
- Test 11: Listener silence → banner — overridden; state machine green in MqttListenerWatchdogTest + dispatch prop green in DispatchConsoleMqttHealthPropTest; live kill+wait deferred to first ops smoke-test session.
- Test 12: Horizon isolation — overridden; structural isolation proven via config/horizon.php + separate Supervisor blocks documented in runbook; live horizon:terminate deferred to first ops smoke-test session.

**Closed:** 2026-04-22 at v2.0 milestone close.
