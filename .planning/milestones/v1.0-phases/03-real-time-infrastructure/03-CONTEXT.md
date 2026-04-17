# Phase 3: Real-Time Infrastructure - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

WebSocket infrastructure is operational so all subsequent layers can push and receive real-time updates without polling. Includes Laravel Reverb server, broadcast events, channel authorization, reconnection strategy, Redis migration (cache + queue + pub/sub), and Horizon queue monitoring. Also replaces Phase 2's Inertia polling with WebSocket push for the dispatch queue and channel monitor.

</domain>

<decisions>
## Implementation Decisions

### Channel Structure
- Global dispatch channel (`dispatch.incidents`) for all incident events — all dispatchers/supervisors/admins subscribe
- Global units channel (`dispatch.units`) for all unit location/status updates — same audience
- Personal channel per user (`user.{id}`) for direct pushes (assignment notifications to responders)
- Presence channel (`presence-dispatch`) to track which dispatchers/supervisors are online — shift awareness and oversight
- Role-based channel authorization: dispatcher, supervisor, and admin can subscribe to dispatch channels; responders only receive on their personal channel

### Reconnection UX
- Top amber banner on disconnect: "Reconnecting..." with spinner — non-blocking, user can still interact with cached data
- After 30s disconnected: banner escalates from amber to red — "Connection lost — data may be outdated"
- On reconnect: auto state-sync via API endpoint fetching current active incidents and units — banner turns green briefly ("Connected — syncing...") then disappears
- No user action needed for reconnection or state-sync — fully automatic

### Shared Composable
- Build a `useWebSocket` composable that handles connection state, reconnection banner, and state-sync
- All pages import the composable and subscribe to specific channels
- Single source of truth for connection status across the application

### Polling Replacement
- Replace Phase 2's 10s Inertia polling on dispatch queue and channel monitor with WebSocket push
- IncidentCreated and IncidentStatusChanged events update queue and channel monitor in real-time
- New incidents auto-insert into dispatch queue at correct priority position with brief highlight animation (yellow flash ~3s)
- Basic audio notification for P1/P2 incidents arriving (simple alert sound, not the full Phase 4 audio system)

### Broadcast Events
- Define all 6 broadcast event classes in this phase:
  1. IncidentCreated — new incident triaged
  2. IncidentStatusChanged — incident status transition
  3. UnitLocationUpdated — GPS position update
  4. UnitStatusChanged — unit availability/status change
  5. AssignmentPushed — unit assigned to incident
  6. MessageSent — dispatch-responder message
- Only IncidentCreated and IncidentStatusChanged are wired to UI in this phase
- Remaining 4 events are tested but not consumed until Phase 4/5

### Redis & Queue Migration
- Full Redis migration: cache, queue, and Reverb pub/sub all on Redis
- Install Laravel Horizon for queue monitoring with web dashboard at `/horizon` (admin role only)
- QUEUE_CONNECTION → redis, CACHE_STORE → redis, BROADCAST_CONNECTION → reverb

### Developer Experience
- Update `composer run dev` to concurrently run: Laravel server, Vite, Reverb, Horizon, and Pail logs — one command starts everything

### Claude's Discretion
- Reconnection retry strategy (exponential backoff timing, max retries)
- State-sync API endpoint design (what data shape, pagination)
- Echo/Reverb client configuration details
- Horizon queue configuration (workers, timeouts, retry policies)
- Event payload shapes and serialization
- Basic audio implementation approach for P1/P2 alerts

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `IncidentStatus` enum (`app/Enums/IncidentStatus.php`): Status values for IncidentStatusChanged event
- `IncidentPriority` enum (`app/Enums/IncidentPriority.php`): P1-P4 with `color()` method — used for highlight styling
- `Incident` model: All fields ready, used as event payload source
- `Unit` model: GPS coordinates as geography, status field — payload for unit events
- Reka UI components: banner/alert patterns exist in `components/ui/`
- `HandleInertiaRequests` middleware: Already shares auth data — add connection status if needed

### Established Patterns
- Service layer: `Contracts/` interfaces + `Services/` implementations bound in `AppServiceProvider::register()` — follow for any new services
- `useForm` + Wayfinder actions for API calls — state-sync endpoint should follow same pattern
- Composable pattern in `resources/js/composables/` — `useWebSocket` fits here
- Middleware registration in `bootstrap/app.php`
- Routes in `routes/web.php` with role middleware groups

### Integration Points
- `bootstrap/app.php`: Register broadcasting routes and middleware
- `routes/channels.php`: Define channel authorization callbacks
- `.env`: Update BROADCAST_CONNECTION, QUEUE_CONNECTION, CACHE_STORE, REDIS_* vars
- `composer.json`: Add `laravel/reverb`, `laravel/horizon` packages
- `package.json`: Add `laravel-echo`, `pusher-js` (Echo dependency)
- `resources/js/app.ts`: Initialize Echo client
- `resources/js/pages/incidents/Queue.vue`: Replace polling with WebSocket listeners
- `resources/js/components/incidents/ChannelMonitor.vue`: Replace polling with WebSocket listeners
- `resources/js/layouts/AppLayout.vue`: Mount reconnection banner component

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard Laravel Reverb + Echo approaches. Follow existing codebase conventions for service registration and composable patterns.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 03-real-time-infrastructure*
*Context gathered: 2026-03-13*
