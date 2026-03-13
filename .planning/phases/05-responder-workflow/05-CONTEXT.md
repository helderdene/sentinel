# Phase 5: Responder Workflow - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Field responders can receive assignments on mobile, navigate to scenes, document what they find (checklists, vitals, assessment tags), communicate with dispatch via messaging, request additional resources, and close incidents with structured outcome data. Includes auto-generated incident report PDF on closure. External API integrations (real Mapbox Directions ETA, real SMS notifications) are Phase 6. Analytics and compliance reports are Phase 7.

</domain>

<decisions>
## Implementation Decisions

### App Structure & Navigation
- Dedicated `ResponderLayout.vue` — full-screen mobile-first layout with compact topbar (44px) + bottom tab bar. No sidebar. Follows the pattern of IntakeLayout/DispatchLayout where each role gets a purpose-built layout
- Compact topbar shows: unit callsign (AMB-01), current incident number + priority badge, current status chip. Maximizes content area on small screens
- Bottom tab bar with 3 contextual tabs: Assignment + (Nav OR Scene) + Chat
  - Before arrival (Acknowledged/En Route): Assignment | Nav | Chat
  - After arrival (On Scene/Resolving): Assignment | Nav swaps to Scene | Chat — middle tab changes purpose, positions don't shift
- Chat tab shows unread message count badge (red dot with number)
- Status transition button ("ARRIVED ON SCENE →") fixed above tab bar on every tab — responder can advance status regardless of which tab they're viewing
- Standby screen (no active assignment): clean screen showing unit callsign, "Standing By" status, connection status dot, waiting animation. Assignment notification takes over this screen

### Assignment Notification UX
- Full-screen takeover when assignment arrives: incident type, priority, location, barangay, notes displayed with large ACKNOWLEDGE button
- ACKNOWLEDGE button shows 90-second countdown timer (1:28, 1:27...)
- Screen border colored by priority: P1 red pulse, P2 orange, P3 amber, P4 green
- Priority-matched audio tones reused from Phase 4 dispatch alert system (P1: 3 alternating pulses 880/660Hz, P2: 2 pulses 700Hz, etc.). Loops every 15s until acknowledged
- One assignment at a time — responder handles one incident before receiving another
- After acknowledging: auto-transition to Nav tab with Google Maps deep-link and mini-map. Gets responder moving immediately
- Assignment tab (when responder switches back): full incident summary card — type, priority, location, barangay, caller info, notes, assigned units, timeline. Reference card available anytime
- Resource request button at bottom of Assignment tab's incident summary. Opens modal with 6 resource types (additional ambulance, fire unit, police backup, rescue boat, medical officer, medevac) as large touch-friendly buttons

### Scene Documentation Flow
- Scene tab organized as accordion sections (collapsible): Checklist, Vitals, Assessment Tags
- One section open at a time. Progress indicators on each section header (e.g., "Checklist 3/7", "Vitals 0/4", "Assessment 2/11")
- Checklist completion encouraged, not required — shows warning ("Checklist incomplete — continue?") but allows advancing to Resolving. Progress broadcasts to dispatch
- 4 checklist templates hardcoded for v1: cardiac, road accident, structure fire, default. Admin-configurable checklists deferred to future
- Assessment tag toggles auto-save on each toggle — immediately sends to server and broadcasts to dispatch. No save button
- Single vitals reading per incident for v1 (BP, HR, SpO2, GCS). Multiple timestamped readings deferred to v2
- Vitals required only for medical outcomes (Treated On Scene, Transported to Hospital). Not required for False Alarm, Refused Treatment, or DOA

### Navigation Tab
- Embedded MapLibre mini-map showing route polyline, responder position (unit marker), and incident location (pulse ring marker) with live ETA countdown
- Large "OPEN IN GOOGLE MAPS" button for turn-by-turn navigation deep-link
- Reuses existing maplibre-gl dependency from Phase 4
- GPS auto-broadcast via Browser Geolocation API: every 10s en route, 60s on scene. Broadcasts UnitLocationUpdated event — dispatch console already consumes this

### Messaging
- Quick-reply chips: single-tap = message sent immediately (no confirmation). 8 contextual presets: "On scene", "Need backup", "Patient stable", "Transporting", "All clear", "Copy that", "Stand by", "Negative"
- Free text input below quick-reply chips for custom messages
- In-app slide-down banner for incoming dispatch messages when responder is on a different tab. No browser Notification API (that's v2 PWA scope)
- Unread count badge on Chat tab

### Closure & PDF Report
- Outcome selection via bottom sheet when advancing to Resolving: 5 outcomes as large touch-friendly cards (Treated On Scene, Transported to Hospital, Refused Treatment, Declared DOA, False Alarm/Stand Down)
- "Transported to Hospital" expands searchable dropdown with pre-seeded Butuan City hospitals (BCDH, Manuel J. Santos, RTR Medical Center, etc.) — follows SearchableSelect pattern from Phase 9
- Post-closure summary screen: incident number, outcome, scene time, checklist %, vitals summary. "Done" button returns to standby screen
- PDF auto-generated server-side on closure as a queued job (Laravel DomPDF via barryvdh/laravel-dompdf)
- PDF is a structured report with CDRRMO letterhead/branding: incident ID, type, priority, all lifecycle timestamps, location, assigned units, checklist %, vitals, assessment tags, outcome, hospital if applicable, notes. Single page if possible
- PDF stored as file path on incident record. Viewable/downloadable from dispatch console and admin area only — responders don't access the PDF

### Claude's Discretion
- Exact tab bar icon design and animation
- Standby screen waiting animation
- Bottom sheet implementation approach (CSS transition vs library)
- MapLibre mini-map zoom level and route polyline styling
- Accordion section animation timing
- Checklist item content for each of the 4 templates (cardiac, road accident, structure fire, default)
- Quick-reply chip styling and layout
- PDF Blade template layout and typography
- Hospital seeder data (specific Butuan City hospitals)
- Exact summary screen layout after closure
- Assignment card layout details
- Resource request modal design

</decisions>

<specifics>
## Specific Ideas

- Follow the "Refined Government Ops" aesthetic from Phase 8 — professional, high-clarity, large touch targets (44px min per spec)
- "Color carries meaning" — priority colors on borders/badges, status colors on chips, consistent with dispatch console visual language
- "Monospace for data, sans-serif for content" — Space Mono for timestamps/IDs/stats, DM Sans for human-readable content
- Mobile-first: everything designed for thumb-reachable interaction on smartphones (Safari 16.4+ on iOS is the target responder browser)
- Priority-matched audio tones create a consistent audio language — same sounds mean the same thing whether you're dispatch or a responder

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `useWebSocket` composable: Connection status, state sync, reconnection — reuse for responder WebSocket subscriptions
- `useAlertSystem` composable: Priority-matched audio tones via Web Audio API — reuse directly for assignment notification audio
- `useAckTimer` composable: 90-second countdown with `@vueuse/core` useIntervalFn — reuse for responder-side ack countdown display
- `useDispatchMap` composable: MapLibre GL JS patterns — reference for mini-map implementation
- `AssignmentPushed` event: Already broadcasts to `user.{userId}` channel with incident payload — responder consumes this
- `MessageSent` event: Already broadcasts to `user.{recipientId}` with message body — responder consumes this
- `UnitLocationUpdated` event: Already defined — responder will fire this from GPS tracking
- `IncidentMessage` model: Polymorphic sender, quick_reply flag, read_at tracking — backend ready for messaging
- `IncidentStatus` enum: All responder statuses exist (Acknowledged, EnRoute, OnScene, Resolving, Resolved)
- `IntakeLayout.vue` / `DispatchLayout.vue`: Full-screen layout patterns with provide/inject — reference for ResponderLayout
- `PriBadge.vue` / `ChBadge.vue`: Priority and channel badge components — reuse directly
- `SearchableSelect` component (Phase 9): Reuse for hospital picker
- maplibre-gl: Already installed from Phase 4 — reuse for nav mini-map

### Established Patterns
- Service layer: Contracts/ + Services/ bound in AppServiceProvider::register()
- Echo composables with `useEcho()` from `@laravel/echo-vue` for WebSocket subscriptions
- Reactive local copies of Inertia props for WebSocket mutation without page reload
- CSS custom properties with @theme inline indirection for design tokens
- color-mix() for opacity tints
- Role middleware: `middleware(['role:responder,supervisor,admin'])` pattern
- Gates defined in AppServiceProvider::boot()
- Wayfinder actions for all frontend → backend calls
- Forward-only status transitions enforced via allowedTransitions map (from Phase 4)

### Integration Points
- `routes/web.php`: Responder route at `/responder` (currently placeholder) with responder middleware
- `routes/channels.php`: `user.{id}` channel already authorized for assignment pushes and messages
- `app/Http/Controllers/DispatchConsoleController.php`: Status advancement endpoint exists — extend or create parallel responder endpoint
- `app/Models/Incident.php`: vitals JSONB column, assessment_tags TEXT[], lifecycle timestamps — all ready for responder scene documentation
- `app/Models/IncidentUnit.php`: Pivot with acknowledged_at, unassigned_at — ready for ack tracking
- `resources/css/app.css`: Design system tokens already in place
- `package.json`: maplibre-gl already installed; need to add barryvdh/laravel-dompdf for PDF generation

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 05-responder-workflow*
*Context gathered: 2026-03-13*
