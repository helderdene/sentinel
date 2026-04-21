
## 2026-04-21 Plan 20-06

**Pre-existing failures in tests/Feature/Mqtt/AckHandlerTest.php (2 cases):**
- `it logs ACK received on the mqtt channel with no state change`
- `it logs without error when fed the ack.json fixture`

These tests pre-date Plan 20-03's rewrite of `AckHandler::handle()` (see app/Mqtt/Handlers/AckHandler.php) and assumed the Phase 19 log-only scaffold. After 20-03, the handler emits `warning` log calls on unknown-camera / bad-payload paths, but the Phase 19 test fixtures (`ack.json`) drive one of those paths, so the log spy now receives unexpected `warning()` calls.

**Verified pre-existing** via `git stash && php artisan test tests/Feature/Mqtt/AckHandlerTest.php` on the base commit — same 2 failures before my changes.

**Out of scope** for Plan 20-06 (Plan 20-03 should have updated these legacy MQTT-group tests to match the new warn-log surface, but the Plan 20-03 SUMMARY only records the fras-group AckHandlerTest). Track for a Phase 20 cleanup pass.
