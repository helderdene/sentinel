---
phase: 19
plan: 01
subsystem: mqtt-pipeline-listener-infrastructure
tags: [mqtt, fras, horizon, logging, filesystems, severity-classifier, wave-1]
requires:
  - FRAMEWORK-01
  - FRAMEWORK-04
provides:
  - php-mqtt/laravel-client ^1.8 (installed + published)
  - config/mqtt-client.php (dual subscriber/publisher connections)
  - config/fras.php (mqtt.topic_prefix/keepalive/reconnect_delay)
  - config/horizon.php fras-supervisor (queue=['fras'], both envs)
  - config/logging.php mqtt channel (daily -> storage/logs/mqtt.log)
  - config/filesystems.php fras_events private disk
  - RecognitionSeverity::fromEvent() classifier
affects:
  - composer.lock
tech-stack:
  added:
    - php-mqtt/laravel-client ^1.8
  patterns:
    - dual MQTT connection layout (subscriber default, publisher sibling)
    - top-level auto_reconnect marker + nested connection_settings.auto_reconnect array (plan contract + vendor runtime)
    - fras-supervisor Horizon block isolates FRAS queue from default workers
key-files:
  created:
    - config/fras.php
    - config/mqtt-client.php
    - tests/Feature/Mqtt/MqttClientConfigTest.php
    - tests/Unit/Enums/RecognitionSeverityFromEventTest.php
  modified:
    - composer.json
    - composer.lock
    - config/horizon.php
    - config/logging.php
    - config/filesystems.php
    - app/Enums/RecognitionSeverity.php
decisions:
  - [19-01]: Top-level auto_reconnect=true on each connection block is the IRMS-specific MQTT-04 marker, kept alongside the vendor-required nested connection_settings.auto_reconnect array so tests and runtime both pass
  - [19-01]: FRAS Ignored severity collapses into IRMS Info in fromEvent() — Phase 18 recognition_events CHECK constraint only accepts info/warning/critical; Info events stay in history but never broadcast (Phase 22 DPA)
  - [19-01]: mqtt log channel defaults to `log_channel => 'mqtt'` on both connection blocks so php-mqtt debug output routes to storage/logs/mqtt.log (D-17)
  - [19-01]: fras-supervisor minProcesses=1/maxProcesses=3 in production, minProcesses=1/maxProcesses=1 in local per D-02 — FRAS queue empty in Phase 19, ready for Phase 20 EnrollPersonnelBatch jobs
metrics:
  duration: ~10min
  completed: "2026-04-21"
---

# Phase 19 Plan 01: MQTT Pipeline + Listener Infrastructure Summary

One-liner: Installed php-mqtt/laravel-client ^1.8, wired dual subscriber/publisher connections with auto_reconnect (MQTT-04 + MQTT-06), added fras-specific config module, mqtt log channel, fras_events private disk, fras-supervisor Horizon block, and ported FRAS AlertSeverity::fromEvent into IRMS RecognitionSeverity.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Install php-mqtt + wire configs | df3598f | composer.json, composer.lock, config/{mqtt-client,fras,horizon,logging,filesystems}.php |
| 1a | auto_reconnect shape fix | cfd6e27 | config/mqtt-client.php |
| 2 | Pest config assertions (MQTT-04/06) | 5e9f094 | tests/Feature/Mqtt/MqttClientConfigTest.php |
| 3 RED | Failing fromEvent() test | 672cb71 | tests/Unit/Enums/RecognitionSeverityFromEventTest.php |
| 3 GREEN | fromEvent() implementation | ff43856 | app/Enums/RecognitionSeverity.php |

## Verification Results

- `php artisan config:show mqtt-client.connections` → both `subscriber` and `publisher` blocks present
- `php artisan config:show fras.mqtt` → topic_prefix=mqtt/face, keepalive=60, reconnect_delay=5
- `php artisan config:show filesystems.disks.fras_events.visibility` → `private`
- `php artisan config:show logging.channels.mqtt.driver` → `daily`
- `php artisan config:show horizon.environments.{production,local}` → both contain `fras-supervisor` block
- `php artisan test --compact tests/Feature/Mqtt/MqttClientConfigTest.php tests/Unit/Enums/RecognitionSeverityFromEventTest.php` → **11 passed (12 assertions)**
- `vendor/bin/pint --dirty --format agent` → `{"result":"pass"}`

## Success Criteria

1. ✓ php-mqtt/laravel-client ^1.8 in composer.lock, configs resolve
2. ✓ mqtt-client.php has `subscriber` + `publisher` with `auto_reconnect=true` on both — MQTT-04 + MQTT-06 structural groundwork programmatically verified (5/5 Pest)
3. ✓ config/fras.php exposes mqtt.topic_prefix/keepalive/reconnect_delay
4. ✓ fras-supervisor block registered in Horizon (both envs) — Phase 20 has a home for EnrollPersonnelBatch
5. ✓ mqtt log channel + fras_events disk exist
6. ✓ RecognitionSeverity::fromEvent() matrix tested — Wave 0 gap A1 closed (6/6 Pest)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Correctness] Kept top-level auto_reconnect=true marker alongside vendor-nested connection_settings.auto_reconnect array**

- **Found during:** Task 1 verification → Task 2 test design
- **Issue:** The plan asserts `config('mqtt-client.connections.subscriber.auto_reconnect') === true` in the test, but the vendor php-mqtt/laravel-client's ConnectionSettings builder reads `connection_settings.auto_reconnect` as an array `{enabled, max_reconnect_attempts, delay_between_reconnect_attempts}`. Using a bare scalar `true` at the nested path would blow up at runtime; using only the nested array would miss the plan's MQTT-04 marker assertion.
- **Fix:** Populated BOTH — top-level `auto_reconnect => true` on each connection (plan contract / test marker) AND `connection_settings.auto_reconnect => ['enabled' => true, 'max_reconnect_attempts' => 10, 'delay_between_reconnect_attempts' => config('fras.mqtt.reconnect_delay')]` (vendor runtime). Plan acceptance grep `'auto_reconnect' => true` count ≥ 2 still matches.
- **Files modified:** config/mqtt-client.php
- **Commit:** cfd6e27

**2. [Rule 2 - Correctness] log_channel defaults to 'mqtt' on both connections**

- **Found during:** Task 1 config authoring
- **Issue:** Plan mandates D-17 (all MQTT logs route to the new mqtt daily channel) but did not explicitly wire `log_channel => 'mqtt'` inside the connection blocks. Without it, php-mqtt falls back to the default stack channel and D-17 silently breaks.
- **Fix:** Set `'log_channel' => env('MQTT_LOG_CHANNEL', 'mqtt')` on both subscriber and publisher.
- **Files modified:** config/mqtt-client.php (already captured in df3598f)

### Auth Gates

None — fully autonomous plan.

### Architectural Changes

None.

## TDD Gate Compliance

Task 3 followed a clean RED (672cb71 — test fails "Call to undefined method") → GREEN (ff43856 — 6/6 pass) cycle. No REFACTOR commit needed (impl is already minimal — three `if` branches ported verbatim from FRAS).

Task 2 used a test-first shape but config was already written in Task 1; the RED signal came from the vendor shape mismatch caught during test authoring (resolved via cfd6e27 shape fix before the 5e9f094 test landed green). Documented above as Deviation #1.

## Threat Flags

None — plan explicitly covers T-19-06 (fras_events visibility=private, no url key — verified) and T-19-01 (MQTT broker auth deferred to ops runbook per plan). No new surface introduced beyond the plan's threat model.

## Known Stubs

None — all configs are wired end-to-end; the listener command and handlers that consume these configs arrive in Plans 19-02 through 19-06 (expected wave sequence).

## Self-Check

- [x] FOUND: config/fras.php
- [x] FOUND: config/mqtt-client.php
- [x] FOUND: tests/Feature/Mqtt/MqttClientConfigTest.php
- [x] FOUND: tests/Unit/Enums/RecognitionSeverityFromEventTest.php
- [x] FOUND commit: df3598f
- [x] FOUND commit: cfd6e27
- [x] FOUND commit: 5e9f094
- [x] FOUND commit: 672cb71
- [x] FOUND commit: ff43856
- [x] RecognitionSeverity::fromEvent() method added to app/Enums/RecognitionSeverity.php
- [x] horizon.php fras-supervisor in both production and local environments
- [x] logging.php mqtt daily channel -> storage/logs/mqtt.log
- [x] filesystems.php fras_events private disk with no url key

## Self-Check: PASSED
