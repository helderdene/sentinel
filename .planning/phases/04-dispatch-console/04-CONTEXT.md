# Phase 4: Dispatch Console - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Dispatchers can see all incidents and units on a live 2D map, assign the nearest available unit to an incident, and track response progress in real-time with audio/visual alerts. Includes the full dispatch console UI with MapLibre map, incident queue, unit management, assignment workflow, acknowledgement timer, audio alerts, session metrics, and mutual aid protocol. Responder mobile workflow is Phase 5. External API integrations (real Mapbox Directions ETA) are Phase 6.

</domain>

<decisions>
## Implementation Decisions

### Console Layout
- Map-dominant layout: full-screen 2D MapLibre map with collapsible overlay panels
- Left panel (320px): incident queue with filter tabs (ALL, P1, P1-2, ACTIVE), showing ALL non-resolved incidents with status badges, priority border colors, elapsed time, and assigned unit
- Right panel (360px): contextual — defaults to UNIT STATUS (grouped by agency with status dots and crew count); switches to INCIDENT DETAIL when an incident is selected; switches to unit detail when a unit marker is clicked on the map
- New `DispatchLayout.vue` (separate from IntakeLayout), following the same design system (DM Sans + Space Mono, 56px topbar, 24px statusbar)
- Topbar: DISPATCH branding, ACTIVE/CRITICAL/TOTAL stats, unit availability ratio (e.g., 6/9), live clock, live ticker
- Statusbar: system status, dispatcher name, CDRRMO label, connection status
- 2D only — no pitch/rotation/3D camera controls (per roadmap decision)
- Dark map style as default (easier on eyes for long shifts, markers pop). Follows app-wide dark/light toggle
- No barangay boundary overlay on map — barangay info appears in incident cards and detail panels
- No NEW INCIDENT button — incident creation stays in intake station (operator role). Clean role separation
- Map legend overlay (bottom-left): incident priority colors + unit status colors
- Reference mockup: `dispatch-3d-console_2.html` screenshots provided during discussion

### Incident Detail Panel
- Full incident info: type, priority badge, ID, status, reported time, elapsed time
- SLA WINDOW progress bar with priority-based targets: P1 = 5min, P2 = 10min, P3 = 20min, P4 = 30min
- Location, caller, barangay, coordinates
- Description/notes text
- STATUS PROGRESSION pipeline: REPORTED → DISPATCHED → EN ROUTE → ON SCENE → RESOLVED
- "ADVANCE → [next status]" button — dispatcher CAN advance incident status from the console (useful for radio confirmations)
- ASSIGNEES section: assigned units with status and 90-second ack timer countdown
- DISPATCH section: proximity-ranked available unit chips with distance + estimated ETA
- TIMELINE section: chronological incident events with timestamps and actor names
- REQUEST MUTUAL AID button at bottom

### Unit Assignment Workflow
- Unit chips in incident detail sorted by proximity (PostGIS ST_DWithin), closest first
- Each chip shows: callsign, distance (km), estimated ETA (~Xmin)
- ETA calculated as straight-line distance / 30km/h average urban speed (labeled with ~ to indicate estimate; real Mapbox Directions ETA wired in Phase 6)
- Only AVAILABLE units shown in dispatch chips
- One-click assign: click chip → immediately assigned, no confirmation modal. Speed is critical in dispatch
- Chip moves from DISPATCH section to ASSIGNEES section with status badge
- Click assigned unit to unassign (with confirmation for unassign since it's destructive)
- Panel-only assignment — no assigning by clicking map markers
- Assignment pushed to responder via WebSocket (AssignmentPushed event from Phase 3)
- Assignment logged to incident timeline

### Map Interaction
- Click queue item → map flyTo (smooth animation) centers on incident marker, right panel shows incident detail
- Click incident marker on map → same behavior (flyTo + detail panel)
- Click unit marker on map → right panel switches to unit detail (callsign, type, status, crew, current assignment, recent timeline)
- Click empty map area → deselect, right panel returns to UNIT STATUS roster
- Animated dashed connection lines between assigned unit markers and incident markers (line color matches priority, dash offset animation)
- Clicking connection line shows ETA tooltip
- Incident markers: WebGL circle layers with halo + pulse rings, colored by priority (P1 red, P2 orange, P3 amber, P4 green)
- Unit markers: WebGL circle layers with glow + border, colored by status (available green, en route blue, on scene yellow/amber, offline gray)
- Unit positions update in real-time via WebSocket (UnitLocationUpdated event), markers animate smoothly between GPS positions

### Alert System
- Frequency-based audio tones via Web Audio API (extending existing useWebSocket infrastructure):
  - P1 CRITICAL: 3 alternating pulses at 880Hz/660Hz, 1.5s total duration
  - P2 HIGH: 2 pulses at 700Hz, 0.6s total
  - P3 MEDIUM: 1 pulse at 550Hz, 0.3s total
  - P4 LOW: soft chime at 440Hz, 0.2s with gentle decay
- P1 red screen flash: inset box-shadow border pulse (3 pulses over ~2s), non-blocking — map and panels remain visible and interactive
- Audio always on, no mute control — dispatchers must hear alerts
- Audio triggers on: new incident arriving, ack timer expiry, status changes

### Acknowledgement Timer
- 90-second countdown displayed inline next to assigned unit in ASSIGNEES section
- Visual: circular progress ring or text countdown [1:12]
- Color: green when > 30s remaining, red when ≤ 30s
- On expiry: warning indicator + audio alert + action buttons: [REASSIGN] [ESCALATE] [EXTEND]
- Timer starts when assignment is pushed via WebSocket
- Acknowledgement from responder closes the timer (Phase 5 wires the responder side)

### Mutual Aid Protocol
- "REQUEST MUTUAL AID" button in incident detail panel, below dispatch chips
- Opens modal with type-based agency suggestions:
  - Fire incidents → BFP (Bureau of Fire Protection) highlighted
  - Crime/Security → PNP (Philippine National Police) highlighted
  - Mass casualty/Medical → DOH (Department of Health) highlighted
  - Natural disaster → adjacent LGU + DSWD highlighted
- All 5 agencies always available: BFP, PNP, DSWD, DOH, Adjacent LGU (Cabadbaran)
- Suggested agencies highlighted with star; others listed below
- Each agency card shows: name, code, contact phone, contact email, radio channel
- Free-text notes field for specific requests
- Agencies stored in database table (`agencies`) with seeder: BFP Caraga, PNP Butuan, DSWD Caraga, DOH Caraga, LGU Cabadbaran
- Agency-incident type mapping via pivot table (`agency_incident_type`)
- Request logged to incident timeline as event with timestamp, dispatcher name, agency, and notes
- Request broadcast via WebSocket so other dispatchers see it in real-time

### Session Metrics (Topbar)
- ACTIVE: count of non-resolved incidents (DISPATCHED + EN_ROUTE + ON_SCENE)
- CRITICAL: count of P1 active incidents (red text)
- TOTAL: total incidents in current session/shift
- Unit ratio: available / total units (e.g., 6/9)
- All metrics update in real-time via WebSocket

### Claude's Discretion
- MapLibre tile style URL and customization (dark/light vector tiles)
- Exact overlay panel collapse/expand animation
- Map zoom level and bounds for Butuan City
- WebGL marker layer implementation details (circle layer spec)
- Marker smooth animation technique between GPS positions
- Connection line rendering approach (GeoJSON line layer with dash-array animation)
- Unit detail panel content and layout
- SLA window calculation method (from which timestamp)
- Topbar stat pill layout and styling
- Live ticker implementation
- Queue card layout details (following mockup reference)
- Bottom-left legend design
- Status advancement validation (which transitions are allowed from dispatch)

</decisions>

<specifics>
## Specific Ideas

- Design reference: user provided screenshots of `dispatch-3d-console_2.html` showing the exact target layout — three-zone design with left queue, center map, right contextual panel
- Screenshot 1: incident selected — right panel shows INCIDENT DETAIL with SLA window, status progression, assignees, dispatch chips, and timeline
- Screenshot 2: no incident selected — right panel shows UNIT STATUS grouped by agency (AMB, BFP, PNP, RESCUE) with status dots and crew count
- Follow the "Refined Government Ops" aesthetic from Phase 8's intake design system — professional, high-clarity, information density without cognitive overload
- "Color carries meaning" — priority colors on incident markers/badges, status colors on unit markers/badges
- "Monospace for data, sans-serif for content" — Space Mono for timestamps/IDs/stats/metrics, DM Sans for human-readable content
- Connection lines between assigned units and incidents provide instant visual dispatch awareness on the map

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `useWebSocket` composable (`resources/js/composables/useWebSocket.ts`): Connection status, state sync, basic 880Hz audio alert — extend with per-priority tones and P1 flash
- `useIntakeFeed` composable: Echo listener pattern with `useEcho<PayloadType>()` — reuse for dispatch channel subscriptions
- `useIntakeSession` composable: Session metrics tracking — reference for dispatch metrics
- `StateSyncController` (`app/Http/Controllers/StateSyncController.php`): Returns current incidents + units for WebSocket reconnection — extend for dispatch console needs
- `IncidentCreated`, `IncidentStatusChanged` events: Already broadcast on `dispatch.incidents` channel
- `UnitLocationUpdated`, `UnitStatusChanged` events: Already broadcast on `dispatch.units` channel — not yet consumed by any UI
- `AssignmentPushed` event: Already broadcast on `user.{userId}` channel — not yet consumed
- `IntakeTopbar.vue`: Reference for topbar stat pills and live ticker pattern
- `IntakeLayout.vue`: Reference for full-screen layout with provide/inject for stats
- `DispatchQueuePanel.vue` / `QueueRow.vue`: Queue card patterns from intake station — reference for dispatch queue cards
- `PriBadge.vue` / `ChBadge.vue`: Priority and channel badge components — reuse directly
- `IntakeIconShield.vue` and other SVG icons: Reference for custom dispatch icons

### Established Patterns
- Service layer: `Contracts/` interfaces + `Services/` implementations bound in `AppServiceProvider::register()` — follow for proximity ranking service
- Echo composables with `useEcho()` from `@laravel/echo-vue` — follow for all dispatch WebSocket subscriptions
- Reactive local copies of Inertia props for WebSocket mutation without full page reload (from Phase 3)
- CSS custom properties with `@theme` inline indirection for design tokens (from Phase 8)
- `color-mix()` for opacity tints (from Phase 8)
- Role middleware: `middleware(['role:dispatcher,supervisor,admin'])` pattern
- Gates defined in `AppServiceProvider::boot()` — add dispatch-specific gates if needed
- Wayfinder actions for all frontend → backend calls

### Integration Points
- `routes/web.php`: Dispatch console route at `/dispatch` (currently placeholder) with dispatcher/supervisor/admin middleware
- `routes/channels.php`: `dispatch.incidents`, `dispatch.units`, `user.{id}`, and `dispatch` (presence) channels already authorized
- `app/Models/Unit.php`: GPS coordinates as geography(Point, 4326) with GiST index — ready for ST_DWithin proximity queries
- `app/Models/Incident.php`: `assigned_unit` foreign key, lifecycle timestamps — ready for assignment workflow
- `resources/css/app.css`: Design system tokens already in place (priority colors, fonts, dark mode)
- `package.json`: Need to add `maplibre-gl` and `vue-maplibre-gl` (not yet installed)
- `resources/js/types/incident.ts`: StateSyncResponse includes units with coordinates — extend for dispatch types

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 04-dispatch-console*
*Context gathered: 2026-03-13*
