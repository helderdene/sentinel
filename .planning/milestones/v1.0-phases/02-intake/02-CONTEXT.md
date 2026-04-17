# Phase 2: Intake - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Dispatchers can receive incident reports from multiple channels (Phone, SMS, App, IoT, Radio), triage them with auto-suggested priority, geocode locations to barangay boundaries, and view a priority-ordered dispatch queue. Unit assignment and real-time WebSocket push are separate phases (Phase 4 and Phase 3 respectively).

</domain>

<decisions>
## Implementation Decisions

### Triage Form Design
- Single-page form at `/incidents/create` (dedicated page, not modal or slide-over)
- Accessible via "+ New Incident" button on the dispatch queue page and sidebar
- Sections in order: Channel + Caller Info, Incident Details (type + priority), Location, Notes
- Incident type selection: grouped combobox searchable by keyword, grouped by 8 categories (Medical, Fire, Natural Disaster, etc.)
- All 5 channels available in dropdown: Phone, SMS, App (Walk-in/Web), IoT Sensor, Radio
- Uses `useForm` + Wayfinder actions (consistent with existing settings pattern from Phase 1)

### Priority Auto-Suggestion
- Inline suggestion with override: when incident type is selected, priority auto-fills as colored button group (P1-P4) with the suggested one pre-selected + confidence percentage
- Confidence calculated via keyword matching on notes: base confidence from incident type's `default_priority`, then scan notes for escalation/de-escalation keywords (e.g., "trapped" +10%, "minor" -20%)
- Supports both Filipino and English keywords (e.g., "sunog" = fire, "baha" = flood)
- Real-time debounced (500ms): priority and confidence update as dispatcher types notes
- One-click override: dispatcher clicks a different P1-P4 button to change
- Override logged to incident timeline: "Priority overridden from P3 (suggested) to P1 by [Dispatcher Name]"

### Location & Geocoding
- Type-ahead autocomplete for location: dispatcher types address, sees suggestions from Mapbox geocoding (stubbed with Philippines filter)
- Selecting a suggestion auto-populates coordinates
- PostGIS ST_Contains auto-assigns barangay from coordinates; dispatcher can manually correct
- Barangay shown as read-only field that updates when coordinates are set

### Dispatch Queue
- Table layout with colored left border stripe per priority (red P1, orange P2, amber P3, green P4)
- Columns: Incident #, Type, Priority badge, Location/Barangay, Channel, Time Elapsed (age), Status
- Sorted by priority (P1 first) then FIFO within same priority
- Queue shows only PENDING incidents; separate "Incidents List" page shows all incidents with status filters
- Clicking a row navigates to `/incidents/{id}` (incident detail page with full info + timeline)
- Inertia v2 polling (10s interval) for live updates until Phase 3 adds WebSocket push

### Channel Ingestion — IoT Sensor Webhook (INTK-07)
- Webhook endpoint accepts IoT sensor payloads with HMAC-SHA256 validation
- Auto-creates incidents as PENDING with channel='IoT Sensor' and IoT badge in queue
- 5 hardcoded sensor type mappings: flood_gauge → Flooding (P2), fire_alarm → Structure Fire (P1), weather → Severe Weather (P2), seismic → Earthquake (P1), cctv_analytics → General Emergency (P3)
- Stubbed integration: endpoint validates structure and creates incident, no real sensor connection

### Channel Ingestion — SMS Inbound Webhook (INTK-08)
- Webhook endpoint parses incoming SMS messages (stubbed Semaphore integration)
- Keyword-to-incident-type map with Filipino and English support: "sunog/fire" → Structure Fire, "baha/flood" → Flooding, "aksidente" → Vehicular Accident, "ambulansya" → Medical Emergency
- Unmatched messages assigned "General Emergency" type
- Raw SMS message preserved in incident record
- Location text extracted from message for geocoding attempt
- Auto-reply SMS on incident creation (stubbed)

### Channel Monitor Panel (INTK-09)
- Dashboard widget (not a separate page): 5 channel cards showing pending incident counts per channel
- Channels: Phone, SMS, App, IoT, Radio with icons and pending count badges
- Refreshes via Inertia polling (same as queue)

### Claude's Discretion
- Exact keyword lists for priority escalation/de-escalation
- Geocoding autocomplete debounce timing and result count
- Table pagination strategy (if needed for large incident counts)
- Incident detail page layout and timeline rendering
- SMS keyword map structure (database table, config file, or enum)
- HMAC-SHA256 implementation details for IoT webhook
- Loading/empty states for queue and dashboard

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Incident` model (`app/Models/Incident.php`): All fields ready, auto-generates INC-YYYY-NNNNN via `booted()` hook
- `IncidentType` model: Has `category`, `name`, `code`, `default_priority`, `is_active`, `scopeActive()`, `scopeByCategory()`
- `IncidentPriority` enum: P1-P4 with `label()` and `color()` methods
- `IncidentStatus` enum: Full lifecycle (PENDING through RESOLVED)
- `IncidentTimeline` model: Append-only audit log with `event_type`, `event_data` JSONB
- `Barangay` model: 86 boundaries seeded with PostGIS polygons, risk levels
- Reka UI components: card, button, input, select, badge, dialog, alert, skeleton, spinner, dropdown-menu
- `PlaceholderPattern.vue` and `ComingSoon.vue`: Existing placeholder components

### Established Patterns
- `useForm` + Wayfinder actions for form submissions (not `<Form>` component)
- FormRequest classes for validation with array-style rules
- Admin routes via `withRouting(then:)` callback (keep dispatcher routes in `web.php`)
- Role-based sidebar navigation with computed `Record<UserRole, NavItem[]>`
- `clickbar/laravel-magellan` for PostGIS model casts (Point geography)
- Casts defined as `casts()` method on model

### Integration Points
- Sidebar nav: Add Incident Queue, Incidents List entries for dispatcher role (Phase 1 defined the nav structure)
- `routes/web.php`: Add incident CRUD routes with dispatcher middleware
- Dashboard page: Add channel monitor widget
- `HandleInertiaRequests`: Share queue counts, channel stats as Inertia props

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches. Follow existing Laravel + Vue + Inertia conventions established in Phase 1.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 02-intake*
*Context gathered: 2026-03-13*
