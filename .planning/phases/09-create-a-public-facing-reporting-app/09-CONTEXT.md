# Phase 9: Create a Public Facing Reporting App - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Build a public-facing, mobile-first citizen reporting web app where Butuan City residents can report emergencies directly to CDRRMO. The app is a separate Vue SPA in a monorepo subfolder that communicates with the Laravel backend via versioned API endpoints. Reports create Incidents directly (channel='app', status=PENDING) which appear in the operator intake feed. Citizens can track their report status using a short hash token. No authentication required. The HTML prototype at `docs/irms-report-app.html` is the design reference.

</domain>

<decisions>
## Implementation Decisions

### Identity & Tracking
- No authentication — citizens submit reports without login
- Contact number is required (not optional); name remains optional
- Reports stored in browser localStorage for "My Reports" tab (same device only)
- Additionally, a "Track by ID" lookup page allows checking any report from any device using the tracking token
- Status updates fetched on page visit (poll on visit) — no WebSocket, no auto-refresh for public app

### Report-to-Intake Flow
- Citizen report creates an Incident directly with channel='app' and status=PENDING
- No separate Report model — reuses existing Incident model and intake pipeline
- Short random 8-char hash token generated per report (e.g., A7F2B3K9) for citizen tracking — not the internal INC-YYYY-NNNNN number
- Token stored on incident record; citizens use it for lookup; operators see the normal INC number
- Simplified citizen-facing status mapping:
  - PENDING → "Received"
  - TRIAGED → "Verified"
  - DISPATCHED/ACKNOWLEDGED/EN_ROUTE/ON_SCENE/RESOLVING → "Dispatched"
  - RESOLVED → "Resolved"
- GPS geolocation requested from device; if granted, auto-detect coordinates + PostGIS barangay lookup; if denied, fall back to manual barangay dropdown + address text

### Incident Type Selection
- Curated subset of ~12-15 most common types shown as visual cards (matching prototype grid style)
- Admin-configurable: add `show_in_public_app` boolean flag to incident_types table; admin toggles which types citizens see
- "Other Emergency" catch-all type always visible
- Priority badge shown on each type card (P1 CRITICAL, P2 HIGH, etc.) — auto-set from type, operators can override during triage

### App Architecture
- Separate Vue SPA in monorepo subfolder `/report-app/` with its own package.json, Vite config, and Vue setup
- Shares design tokens (DM Sans + Space Mono fonts, color tokens) via copy or symlink from main app
- API endpoints in main Laravel app under versioned `/api/v1/citizen/*` route group:
  - `POST /api/v1/citizen/reports` — submit a report (creates Incident)
  - `GET /api/v1/citizen/reports/{token}` — track report by token
  - `GET /api/v1/citizen/incident-types` — get curated public types
- No auth middleware on citizen API routes — rate-limited to prevent abuse
- CORS configured to allow citizen app domain
- System-aware dark mode via `prefers-color-scheme` — no manual toggle

### Claude's Discretion
- Exact Vite/Vue configuration for the SPA subfolder
- Rate limiting strategy for public API endpoints
- CORS configuration specifics
- Design token sharing mechanism (symlink vs copy vs npm workspace)
- Screen transition animations matching prototype
- localStorage schema for report tracking
- Hash token generation algorithm (must be URL-safe, collision-resistant)
- Bottom nav implementation and routing (Vue Router)
- Form validation UX (inline errors, step navigation)
- Empty state designs for "My Reports"
- About page content structure

</decisions>

<specifics>
## Specific Ideas

- The HTML prototype at `docs/irms-report-app.html` is the authoritative design reference — follow its visual design, layout, interactions, and flow closely
- Mobile-first phone-frame design (390x844px in prototype) — responsive but primarily mobile
- Three-step report flow: Choose type (grid) → Details form → Confirmation with status pipeline
- Bottom navigation: Home, My Reports, About (matching prototype)
- Hero section on home with "CDRRMO Butuan City" branding and "Report Emergency Now" red CTA button
- Quick tips section on home screen
- Recent reports list on home (from localStorage)
- Emergency hotline card on home screen
- Status pipeline tracker on confirmation screen (RECEIVED → VERIFIED → DISPATCHED → RESOLVED)
- "Refined Government Ops" aesthetic — matching IRMS design system
- "Monospace for data, sans-serif for content" — Space Mono for IDs/timestamps/labels, DM Sans for content

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Incident` model with full lifecycle support — report creates one directly
- `IncidentType` model with categories and default priorities — add `show_in_public_app` flag
- `IncidentStatus` enum — reuse for status mapping to citizen-facing labels
- `IncidentPriority` enum — reuse for priority badges
- `IncidentChannel` enum — has `app` value for citizen reports
- `BarangayLookupService` — PostGIS ST_Contains for GPS → barangay resolution
- `PrioritySuggestionService` — auto-priority from incident type
- Design tokens in `resources/css/app.css` — DM Sans, Space Mono, color variables

### Established Patterns
- Service layer: Contracts/ + Services/ bound in AppServiceProvider
- FormRequest classes for validation with array-style rules
- Incident creation pattern in `IncidentController::store()` — generates INC number, fires IncidentCreated event, logs timeline entry
- Webhook routes (IoT, SMS) as precedent for unauthenticated incident creation

### Integration Points
- `incident_types` migration: add `show_in_public_app` boolean column
- `incidents` migration: add `tracking_token` string column (unique, indexed)
- New `PublicReportController` (or `CitizenReportController`) for API endpoints
- `routes/api.php`: new `/v1/citizen/*` route group with rate limiting
- `IncidentCreated` event fires when report creates incident — operators see it in intake feed via WebSocket
- Admin panel: toggle `show_in_public_app` on incident type management page

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 09-create-a-public-facing-reporting-app*
*Context gathered: 2026-03-13*
