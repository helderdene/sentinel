# Phase 8: Implement Operator Role and Intake Layer UI - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Add a new "operator" role (5th role) responsible for the intake layer, and build a completely new full-screen intake station UI following the IRMS Intake Design System (`docs/IRMS-Intake-Design-System.md`). The intake station is a three-column layout (channel feed, triage form, dispatch queue) with a custom topbar and statusbar — replacing the existing sidebar-based intake pages for operators. The dispatcher role remains focused on the dispatch console (Phase 4). Supervisor and admin can also access the intake station with elevated permissions (override priority, recall incidents, view session log).

</domain>

<decisions>
## Implementation Decisions

### Role Restructuring
- Add "operator" as a 5th role alongside admin, dispatcher, responder, supervisor — one role per user
- Operator permissions (intake only): triage, manual entry, submit to dispatch queue
- Dispatcher keeps: dispatch console, unit assignment, map (Phase 4 scope)
- New intake-specific Laravel gates alongside existing ones: `triage-incidents`, `manual-entry`, `submit-dispatch`, `override-priority`, `recall-incident`, `view-session-log`
- Operator granted: triage-incidents, manual-entry, submit-dispatch
- Supervisor/admin granted: all intake gates (including override-priority, recall-incident, view-session-log)
- Existing dispatcher users are NOT migrated — both roles coexist, admin creates operator users separately
- Operator's default landing page: intake station directly (no dashboard step)
- Intake station is operator's entire world — no sidebar, no other pages, no escape to Index.vue or Show.vue

### Layout Architecture
- New `IntakeLayout.vue` — full-screen three-column layout with custom topbar (56px) and statusbar (24px)
- Other roles (dispatcher, responder, supervisor dashboard) continue using the existing `AppSidebarLayout`
- Supervisor/admin see the same intake station layout as operator but with extra controls (Override Priority button, Recall button, Session Log section)
- Fixed panel widths: left 296px, center flex, right 304px — no resize, no collapse
- Single Inertia page (`IntakeStation.vue`) composed from panel components: IntakeTopbar, ChannelFeed (left), TriagePanel (center), DispatchQueue (right), IntakeStatusbar
- Light AND dark mode support — dark mode variants of all design system tokens

### Topbar & Statusbar
- Topbar stat pills (Incoming, Pending, Triaged, Avg Resp) update in real-time via WebSocket
- Live ticker scrolls real incident events via WebSocket
- Clock is a live JS clock
- User chip with initials avatar, name, and role badge
- Statusbar integrates WebSocket connection status — "INTAKE ONLINE" becomes "RECONNECTING..." (amber) or "CONNECTION LOST" (red) on disconnect. Replaces ConnectionBanner for this layout
- Statusbar also shows: role label, user name, "CDRRMO - BUTUAN CITY", version

### Intake Workflow
- Left panel feed shows real incidents (PENDING status) as cards — arriving via WebSocket (IncidentCreated events)
- Filter tabs: All / Pending / Triaged — triaged cards show at 55% opacity
- Clicking a feed card opens it in the center triage form with pre-filled data (channel, caller, type, location, notes)
- "+ Manual Entry" button opens a blank triage form (no pre-filled data) for phone/walk-in reports — operator manually types all fields
- New TRIAGED status added between PENDING and DISPATCHED in IncidentStatus enum: PENDING (incoming, shows in feed) → TRIAGED (classified, moves to dispatch queue) → DISPATCHED (unit assigned, Phase 4)
- Submitting the triage form sets status to TRIAGED and moves incident to right panel dispatch queue
- Dispatch queue (right panel) shows triaged incidents ordered by priority (P1 first) then FIFO
- Triage form rebuilt from scratch following the design system (not adapted from existing Create.vue)
- Existing Create.vue, Queue.vue, Index.vue replaced by the station for operators — other roles keep their existing pages

### Session Metrics & Right Panel
- Session metrics are per-session (reset on login): Received, Triaged, Pending, Avg Handle time
- Priority breakdown bar chart shows distribution of incidents by priority
- Supervisor/admin see an additional session log section at the bottom of the right panel (triage actions, priority overrides, recalls)
- Operator does NOT see the session log

### Design System Adoption
- Fonts: DM Sans + Space Mono adopted app-wide (replace current font stack across entire app)
- Color tokens: Replace existing Tailwind color tokens with design system values app-wide — T.bg, T.surface, T.text, T.accent, T.border, etc.
- Icons: Custom inline SVG Vue components for intake station only (IntakeIconSms, IntakeIconApp, etc.) — other pages continue using Lucide icons
- Styling: Tailwind utilities where possible, custom CSS only for values Tailwind can't express (e.g., border-left: 3px solid priority color, specific box-shadows)
- Elevation: border + shadow combinations per design system shadow scale
- Spacing: 4px base unit system as defined in design system

### Claude's Discretion
- Exact WebSocket event handling for topbar stat updates and live ticker
- Component file structure within intake station
- Dark mode token derivation from the light theme values
- Triage form field ordering and validation behavior
- Feed card animation timing and details
- Session metrics computation approach (in-memory vs backend)
- Priority breakdown chart implementation (CSS bars vs chart library)
- Session log entry format and display

</decisions>

<specifics>
## Specific Ideas

- The design system document at `docs/IRMS-Intake-Design-System.md` is the authoritative reference for all visual implementation — typography, colors, spacing, components, animations, layout architecture, and interaction patterns
- The attached screenshot shows the exact target UI: three-column layout with channel activity bars on left, empty state in center, dispatch queue with session metrics on right
- Design follows "Refined Government Ops" aesthetic — light, professional, high-clarity, information density without cognitive overload
- "Color carries meaning" — every color is functional (priority, channel, role, status), no decorative color
- "Monospace for data, sans-serif for content" — Space Mono for timestamps/IDs/codes/labels/metrics, DM Sans for human-readable content
- Demo users in design system: Santos, M.L. (Operator), Reyes, J.A. (Supervisor), Admin (Admin) — use for seeder/testing

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `UserRole` enum (`app/Enums/UserRole.php`): Currently has Admin, Dispatcher, Responder, Supervisor — add Operator
- `IncidentStatus` enum: Currently PENDING → DISPATCHED → ... — add TRIAGED between PENDING and DISPATCHED
- `useWebSocket` composable: Handles connection state, reconnection, state-sync — reuse for intake station real-time features
- `usePrioritySuggestion` composable: AI priority suggestion — reuse in new triage form
- `useGeocodingSearch` composable: Location search — reuse in new triage form
- `PrioritySelector.vue`: Existing priority selection UI — reference for design system priority picker rebuild
- `ChannelMonitor.vue`: Existing channel counts — logic reusable for left panel channel activity
- `IncidentTimeline.vue`: Timeline rendering — adaptable for session log display
- `HandleInertiaRequests` middleware: Already shares auth/permissions — extend with operator gates

### Established Patterns
- Service layer: Contracts/ + Services/ bound in AppServiceProvider — follow for any new services
- `useForm` + Wayfinder actions for form submissions
- FormRequest classes for validation with array-style rules
- Role middleware: `middleware(['role:operator,supervisor,admin'])` pattern
- Gates defined in AppServiceProvider boot() method
- Composable pattern in `resources/js/composables/`
- WebSocket: Echo channels with `useEcho()` composable

### Integration Points
- `app/Enums/UserRole.php`: Add Operator case
- `app/Enums/IncidentStatus.php`: Add TRIAGED case
- `AppServiceProvider.php`: Add new intake gates, grant to operator/supervisor/admin
- `routes/web.php`: Add intake station route with operator/supervisor/admin middleware
- `AppSidebar.vue`: Add operator nav items (just intake station link)
- `HandleInertiaRequests.php`: Share new intake gate permissions
- `resources/js/types/`: Update UserRole and IncidentStatus TypeScript types
- `resources/css/app.css`: Update Tailwind theme with design system color tokens
- `resources/js/layouts/`: New IntakeLayout.vue alongside existing AppSidebarLayout.vue

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 08-implement-operator-role-and-intake-layer-ui*
*Context gathered: 2026-03-13*
