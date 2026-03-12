---
phase: 03-real-time-infrastructure
plan: 01
subsystem: infra
tags: [reverb, horizon, redis, websocket, broadcasting, events]

requires:
  - phase: 02-intake
    provides: IncidentController store endpoint, Incident/Unit models with factories

provides:
  - Reverb WebSocket server configuration
  - Horizon queue dashboard with admin-only gate
  - Redis cache/queue/pub-sub configuration
  - 6 broadcast event classes with channel routing
  - 4 authorized channel types (private + presence)
  - State-sync endpoint for WebSocket reconnection
  - IncidentCreated event dispatch on incident creation
  - Full backend test coverage for real-time infrastructure

affects: [03-02-frontend-echo, 04-dispatch-console, 05-responder-mobile]

tech-stack:
  added: [laravel/reverb, laravel/horizon, laravel-echo, pusher-js, "@laravel/echo-vue"]
  patterns: [ShouldBroadcast + ShouldDispatchAfterCommit events, role-based channel authorization, state-sync for reconnection]

key-files:
  created:
    - config/broadcasting.php
    - config/reverb.php
    - config/horizon.php
    - app/Providers/HorizonServiceProvider.php
    - app/Events/IncidentCreated.php
    - app/Events/IncidentStatusChanged.php
    - app/Events/UnitLocationUpdated.php
    - app/Events/UnitStatusChanged.php
    - app/Events/AssignmentPushed.php
    - app/Events/MessageSent.php
    - app/Http/Controllers/StateSyncController.php
    - routes/channels.php
    - tests/Unit/BroadcastEventTest.php
    - tests/Feature/RealTime/ChannelAuthorizationTest.php
    - tests/Feature/RealTime/StateSyncTest.php
    - tests/Feature/RealTime/HorizonAccessTest.php
    - tests/Feature/RealTime/BroadcastIntegrationTest.php
  modified:
    - composer.json
    - package.json
    - bootstrap/app.php
    - bootstrap/providers.php
    - phpunit.xml
    - .env.example
    - app/Http/Controllers/IncidentController.php
    - resources/js/app.ts
    - tests/Feature/Intake/CreateIncidentTest.php
    - tests/Feature/Intake/BarangayAssignmentTest.php

key-decisions:
  - "phpunit.xml uses BROADCAST_CONNECTION=reverb with test credentials for channel auth validation"
  - "Magellan Point uses getLatitude()/getLongitude() methods not property access"
  - "Existing intake tests use Event::fake([IncidentCreated]) to prevent broadcast errors in test env"
  - "Presence channel returns user id, name, role for dispatch console user awareness"

patterns-established:
  - "Broadcast events: implement ShouldBroadcast + ShouldDispatchAfterCommit, use broadcastWith() for explicit payloads"
  - "Channel authorization: role-based access in routes/channels.php with UserRole enum array comparison"
  - "State-sync endpoint pattern: invokable controller returning JSON with active entities for WebSocket reconnection"
  - "Test isolation: Event::fake() for specific event classes when tests trigger broadcast events"

requirements-completed: [FNDTN-09, FNDTN-10]

duration: 16min
completed: 2026-03-13
---

# Phase 3 Plan 1: Real-Time Backend Infrastructure Summary

**Reverb WebSocket server + Horizon queue dashboard + 6 broadcast events with role-based channel auth and state-sync reconnection endpoint**

## Performance

- **Duration:** 16 min
- **Started:** 2026-03-12T19:17:41Z
- **Completed:** 2026-03-12T19:34:11Z
- **Tasks:** 3
- **Files modified:** 27

## Accomplishments
- Reverb WebSocket server installed and configured with private + presence channel authorization for 4 channel types
- Redis configured for cache, queue, and Reverb pub/sub (replacing database driver)
- Horizon queue dashboard accessible at /horizon with admin-only gate
- 6 broadcast events with correct ShouldBroadcast + ShouldDispatchAfterCommit + broadcastOn + broadcastWith
- State-sync endpoint returns current active incidents and units for WebSocket reconnection
- IncidentCreated event dispatched on incident creation in IncidentController::store()
- composer run dev starts server + reverb + horizon + pail + vite concurrently
- 35 new tests passing (10 unit + 25 feature)

## Task Commits

Each task was committed atomically:

1. **Task 1: Install packages and configure Reverb + Redis + Horizon infrastructure** - `425349b` (feat)
2. **Task 2: Create broadcast events, channel authorization, and state-sync endpoint** - `18da0e7` (feat)
3. **Task 3: Backend tests for broadcast events, channel auth, state-sync, and Horizon access** - `17f9e9a` (test)

## Files Created/Modified
- `config/broadcasting.php` - Reverb broadcaster configuration
- `config/reverb.php` - Reverb server configuration with Redis scaling
- `config/horizon.php` - Horizon supervisor config (local: 1-3 processes, prod: 2-10)
- `app/Providers/HorizonServiceProvider.php` - Admin-only gate for Horizon dashboard
- `app/Events/IncidentCreated.php` - Broadcasts on private-dispatch.incidents with full incident data
- `app/Events/IncidentStatusChanged.php` - Broadcasts old/new status on dispatch.incidents
- `app/Events/UnitLocationUpdated.php` - Broadcasts lat/lng on dispatch.units
- `app/Events/UnitStatusChanged.php` - Broadcasts old/new status on dispatch.units
- `app/Events/AssignmentPushed.php` - Broadcasts assignment to private-user.{userId}
- `app/Events/MessageSent.php` - Broadcasts message to private-user.{recipientId}
- `app/Http/Controllers/StateSyncController.php` - Reconnection endpoint with incidents + units
- `routes/channels.php` - Channel authorization for 4 channel types with role-based access
- `routes/web.php` - Added state-sync route
- `app/Http/Controllers/IncidentController.php` - IncidentCreated::dispatch wired into store()
- `bootstrap/app.php` - Channels route registration
- `bootstrap/providers.php` - HorizonServiceProvider registration
- `phpunit.xml` - Reverb test config with REVERB_* env vars
- `.env.example` - Redis + Reverb + VITE_REVERB_* environment variables
- `composer.json` - Updated dev script, added reverb + horizon packages
- `resources/js/app.ts` - Echo Vue configuration added by broadcasting installer
- `tests/Unit/BroadcastEventTest.php` - 10 unit tests for 6 event classes
- `tests/Feature/RealTime/ChannelAuthorizationTest.php` - 10 tests for channel auth by role
- `tests/Feature/RealTime/StateSyncTest.php` - 6 tests for state-sync endpoint
- `tests/Feature/RealTime/HorizonAccessTest.php` - 2 tests for admin-only Horizon access
- `tests/Feature/RealTime/BroadcastIntegrationTest.php` - 2 tests for event dispatch integration

## Decisions Made
- **phpunit.xml uses reverb broadcaster**: Needed for channel authorization validation in tests (log broadcaster doesn't enforce channel auth). Test REVERB_* credentials provided so Pusher auth signatures work without a running server.
- **Magellan Point access via methods**: Point objects use `getLatitude()`/`getLongitude()` methods, not `latitude`/`longitude` properties. Discovered during testing.
- **Event::fake in existing intake tests**: Existing tests that create incidents via `incidents.store` now need `Event::fake([IncidentCreated::class])` since the store method dispatches a broadcast event that would fail without a running Reverb server.
- **Presence channel user data**: Returns `{id, name, role}` for dispatch console user awareness (e.g., showing who else is online).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed Magellan Point property access in UnitLocationUpdated and AssignmentPushed**
- **Found during:** Task 3 (writing tests)
- **Issue:** UnitLocationUpdated used `$unit->coordinates->latitude` which is undefined; Magellan Point uses `getLatitude()` method
- **Fix:** Changed to `$unit->coordinates?->getLatitude()` and `$unit->coordinates?->getLongitude()` in both events
- **Files modified:** app/Events/UnitLocationUpdated.php, app/Events/AssignmentPushed.php
- **Verification:** BroadcastEventTest passes with correct lat/lng values
- **Committed in:** 17f9e9a (Task 3 commit)

**2. [Rule 3 - Blocking] Updated phpunit.xml broadcaster and added Event::fake to existing tests**
- **Found during:** Task 3 (running full test suite)
- **Issue:** Using `log` broadcaster didn't enforce channel auth (all channels returned 200). Using `reverb` broadcaster caused existing incident creation tests to fail (broadcast error without running server)
- **Fix:** Set BROADCAST_CONNECTION=reverb in phpunit.xml with test credentials. Added Event::fake([IncidentCreated::class]) to CreateIncidentTest and BarangayAssignmentTest
- **Files modified:** phpunit.xml, tests/Feature/Intake/CreateIncidentTest.php, tests/Feature/Intake/BarangayAssignmentTest.php
- **Verification:** Full test suite passes (228 tests)
- **Committed in:** 17f9e9a (Task 3 commit)

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking)
**Impact on plan:** Both fixes necessary for test correctness. No scope creep.

## Issues Encountered
- `install:broadcasting --reverb` TTY error in non-interactive shell -- worked around by manually publishing reverb config and installing npm packages separately
- REVERB_* env vars needed to be set before any artisan command that loads broadcasting config, otherwise Pusher constructor throws on null app_key

## User Setup Required
None - no external service configuration required. Redis must be running locally for queue/cache/Reverb pub-sub.

## Next Phase Readiness
- All backend real-time infrastructure ready for Plan 02 (frontend Echo/Vue integration)
- 6 event classes ready to be consumed via Echo channels
- State-sync endpoint ready for frontend reconnection logic
- Channel authorization enforced -- frontend will use broadcasting/auth route automatically

---
*Phase: 03-real-time-infrastructure*
*Completed: 2026-03-13*
