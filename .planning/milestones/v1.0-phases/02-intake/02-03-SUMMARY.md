---
phase: 02-intake
plan: 03
subsystem: api
tags: [laravel, pest, hmac, webhook, sms, iot, middleware, service-layer]

# Dependency graph
requires:
  - phase: 02-intake-plan-01
    provides: Incident model, IncidentType model, IncidentChannel enum, BarangayLookupService, SmsServiceInterface, StubSemaphoreSmsService, config/services.php IoT mappings, config/sms.php keyword map
provides:
  - IoTWebhookController with HMAC-SHA256 signature verification
  - VerifyIoTSignature middleware with timestamp replay protection
  - SmsWebhookController with keyword-based incident classification
  - SmsParserService with bilingual keyword classification and location extraction
  - Webhook routes bypassing CSRF verification
  - 27 new tests (10 IoT, 13 SMS, 4 channel monitor)
affects: [03-realtime, 06-integration]

# Tech tracking
tech-stack:
  added: []
  patterns: [hmac-webhook-signature-verification, sms-keyword-classification, webhook-csrf-exclusion]

key-files:
  created:
    - app/Http/Controllers/IoTWebhookController.php
    - app/Http/Controllers/SmsWebhookController.php
    - app/Http/Middleware/VerifyIoTSignature.php
    - app/Services/SmsParserService.php
    - tests/Feature/Intake/IoTWebhookTest.php
    - tests/Feature/Intake/SmsWebhookTest.php
    - tests/Feature/Intake/ChannelMonitorTest.php
  modified:
    - bootstrap/app.php
    - routes/web.php
    - .env.testing

key-decisions:
  - "SmsParserService as standalone service (not part of SmsServiceInterface) for single-responsibility keyword classification"
  - "Webhook routes registered at top of routes/web.php before auth middleware group to avoid session/auth middleware overhead"
  - "Location extraction uses regex patterns for Filipino prepositions (sa, dito sa) and English (at, near)"

patterns-established:
  - "Webhook HMAC verification: X-Signature-256 + X-Timestamp headers, sha256=hash_hmac(timestamp.body, secret), 5-minute replay window"
  - "Webhook CSRF exclusion: Route::withoutMiddleware([VerifyCsrfToken::class]) per-route"
  - "SMS keyword classification: config-driven keyword map with first-match-wins and configurable default"

requirements-completed: [INTK-07, INTK-08, INTK-09]

# Metrics
duration: 7min
completed: 2026-03-12
---

# Phase 2 Plan 3: Webhook Channels Summary

**IoT sensor and SMS inbound webhooks with HMAC-SHA256 validation, bilingual keyword classification, and channel monitor count verification**

## Performance

- **Duration:** 7 min
- **Started:** 2026-03-12T17:42:40Z
- **Completed:** 2026-03-12T17:49:40Z
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments
- IoT webhook with HMAC-SHA256 signature verification and 5-minute replay protection
- SMS webhook with Filipino/English keyword classification defaulting to General Emergency
- SmsParserService with location extraction from preposition patterns (sa, dito sa, at, near)
- 27 new tests passing (10 IoT + 13 SMS + 4 channel monitor) bringing Intake total to 48

## Task Commits

Each task was committed atomically (TDD: RED then GREEN):

1. **Task 1 RED: IoT webhook tests** - `e2989ac` (test)
2. **Task 1 GREEN: IoT webhook implementation** - `ce134ae` (feat)
3. **Task 2 RED: SMS webhook + channel monitor tests** - `1d96e94` (test)
4. **Task 2 GREEN: SMS webhook implementation** - `8e87bbd` (feat)

## Files Created/Modified
- `app/Http/Middleware/VerifyIoTSignature.php` - HMAC-SHA256 signature verification with timestamp replay protection
- `app/Http/Controllers/IoTWebhookController.php` - Invokable controller creating incidents from IoT sensor payloads
- `app/Http/Controllers/SmsWebhookController.php` - Invokable controller creating incidents from SMS with keyword classification
- `app/Services/SmsParserService.php` - Keyword classifier, location extractor, and payload normalizer
- `bootstrap/app.php` - Added verify-iot-signature middleware alias
- `routes/web.php` - Added webhook route group with CSRF exclusion
- `.env.testing` - Added IOT_WEBHOOK_SECRET
- `tests/Feature/Intake/IoTWebhookTest.php` - 10 tests covering HMAC validation, sensor mapping, incident creation
- `tests/Feature/Intake/SmsWebhookTest.php` - 13 tests covering keyword classification, location extraction, auto-reply
- `tests/Feature/Intake/ChannelMonitorTest.php` - 4 tests covering per-channel pending counts

## Decisions Made
- SmsParserService created as standalone service rather than adding classification to SmsServiceInterface. SmsServiceInterface handles send/parse transport concerns; SmsParserService handles message classification logic. This keeps single responsibility clean.
- Webhook routes placed at top of routes/web.php before auth middleware group. These routes use withoutMiddleware for CSRF exclusion, avoiding unnecessary session/auth middleware overhead for external webhook calls.
- Location extraction uses simple regex patterns for common Filipino (sa, dito sa) and English (at, near) prepositions. Best-effort extraction appropriate for SMS messages; full geocoding deferred to integration phase.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required. IoT webhook secret is already in .env.testing for tests, and production configuration will use IOT_WEBHOOK_SECRET environment variable.

## Next Phase Readiness
- All multi-channel intake endpoints complete: manual triage form (Plan 01), IoT sensor webhook, SMS inbound webhook
- Channel monitor counts verified accurate per channel
- 48 Intake feature tests providing regression safety for Phase 2 Plan 2 (frontend) and Phase 3 (real-time)
- Webhook patterns established for future integration channels (Phase 6)

## Self-Check: PASSED

All 7 created files verified present on disk. All 4 task commits verified in git log.

---
*Phase: 02-intake*
*Completed: 2026-03-12*
