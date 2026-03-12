# Phase 1: Foundation - Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

<domain>
## Phase Boundary

PostgreSQL + PostGIS database, core data models (incidents, units, barangays, timeline, messages), RBAC with 4 roles (dispatcher, responder, supervisor, admin), barangay boundary seeding, and admin panel for user/role/type management. This phase delivers the data foundation and role system that all subsequent layers build on.

</domain>

<decisions>
## Implementation Decisions

### Role Management
- Full admin panel UI for user creation and role assignment
- Admin-only account creation — disable public registration (Fortify registration route removed)
- One role per user (not multiple roles)
- Admin can create new user accounts and assign a role in one flow
- Four roles: dispatcher, responder, supervisor, admin

### Incident Type Taxonomy
- Incident types stored in a database table (categories + types), not enums or config
- Seeded from the IRMS specification's 8 categories with 40+ types (Medical, Fire, Natural Disaster, Vehicular, Crime/Security, Hazmat, Water Rescue, Public Disturbance)
- Each type has a default priority suggestion (e.g., Structure Fire → P1, Minor Injury → P3)
- Admin can manage incident types in the admin panel (add, edit, disable)

### Barangay Data
- Source 86 boundary polygons from OpenStreetMap (Overpass API) or PSA shapefiles
- Each barangay record includes: name, district, boundary polygon (geography), risk level (low/moderate/high/very high), population
- Risk levels seeded from known CDRRMO/DENR hazard assessment data (flood-prone, landslide-prone classifications)
- Admin can edit barangay metadata (risk level, population, district) but not boundary polygons

### Role-Based Navigation
- Dispatcher: Dashboard, Dispatch Console (map), Incident Queue, Incidents List, Messages
- Responder: Active Assignment (primary), My Incidents (history), Messages, Profile/Settings — mobile-first, minimal nav
- Supervisor: Dashboard (KPIs), Dispatch Console (read-only map), All Incidents, Units, Analytics/Reports — oversight role
- Admin: Full system access + Admin Panel (users, roles, incident types, barangay metadata)
- Phase 1 shows the full navigation per role with placeholder "Coming Soon" pages for features built in later phases — proves the role system works end-to-end

### Claude's Discretion
- Permission implementation approach (Spatie, custom gates/policies, or simple role checks)
- Database migration strategy for switching from SQLite to PostgreSQL
- Admin panel UI component choices and layout
- Placeholder page design
- Unit types seeder content (AMB, RESCUE, FIRE, etc.)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- User model (app/Models/User.php): Extend with role field or relationship; already has Fortify 2FA
- AppLayout (resources/js/layouts/AppLayout.vue): Authenticated layout with sidebar — extend for role-based nav items
- AuthLayout (resources/js/layouts/AuthLayout.vue): Guest layout — still used for login (registration disabled)
- Reka UI components (resources/js/components/ui/): Headless UI primitives for admin panel forms
- HandleInertiaRequests middleware: Share role/permissions data with frontend via Inertia props

### Established Patterns
- FormRequest classes for validation (app/Http/Requests/Settings/)
- Fortify actions pattern (app/Actions/Fortify/) for auth flows
- Concerns/traits for shared validation rules (app/Concerns/)
- Casts defined as method on model, not property

### Integration Points
- bootstrap/app.php: Register role-checking middleware
- routes/web.php → routes/settings.php: Add admin routes, role-restricted route groups
- .env: DB_CONNECTION already set to pgsql (port needs fixing: 3306 → 5432)
- database/migrations/: New migrations for roles, incidents, units, barangays, timeline, messages tables

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches for the admin panel and data modeling. Follow existing Laravel conventions in the codebase.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 01-foundation*
*Context gathered: 2026-03-12*
