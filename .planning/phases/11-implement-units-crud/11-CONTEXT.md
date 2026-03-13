# Phase 11: Implement Units CRUD - Context

**Gathered:** 2026-03-14
**Status:** Ready for planning

<domain>
## Phase Boundary

Admin CRUD for managing response units (AMB-01, FIRE-01, etc.). Admins and supervisors can list, create, edit, and decommission units from the admin panel. Follows the existing admin CRUD pattern (Users, IncidentTypes, Barangays).

</domain>

<decisions>
## Implementation Decisions

### Unit ID generation
- Auto-generated from type + next sequence number (e.g. admin picks Ambulance → system generates AMB-03)
- System queries existing units of that type to determine next number, zero-padded to 2 digits
- Callsign auto-generated as default (e.g. "Ambulance 3") but editable by admin
- Agency field is a dropdown with presets (CDRRMO, BFP, PNP) plus "Other" option that reveals a free-text field
- Shift field included as optional dropdown (Day / Night / Unassigned)

### Crew assignment
- Unit form includes inline multi-select of available responder users for crew assignment
- Assigning a responder sets their unit_id; removing clears it — bidirectional management
- Soft warning (badge) when assigned crew count exceeds crew_capacity, but save is not blocked
- Units index table shows crew as "2/4" format (assigned/capacity)

### Status & coordinates
- Admin can only set Available or Offline status; other statuses (Dispatched, En Route, On Scene) are controlled by dispatch/responder workflow only
- Coordinates are optional — not required on create. Units get real coordinates once responders start GPS tracking
- Units index table shows status as colored badge (green=Available, gray=Offline, blue=En Route, yellow=On Scene, etc.) matching dispatch map marker colors
- Units index table shows type as colored badge (per type)

### Delete behavior (soft-disable)
- "Delete" action decommissions the unit (new status or flag) instead of hard delete — preserves historical references
- Decommissioning automatically unassigns all crew members (sets unit_id to null)
- Decommissioned units appear in the table with muted/faded styling and "Decommissioned" badge
- "Recommission" action button restores a decommissioned unit to Available status

### Claude's Discretion
- Exact type badge color assignments
- Table column ordering
- Form layout and field grouping
- Whether to use a dedicated `decommissioned_at` timestamp or extend UnitStatus enum

</decisions>

<specifics>
## Specific Ideas

- Follow the existing admin CRUD pattern exactly: Route::resource in routes/admin.php, AdminUnitController, Units.vue + UnitForm.vue
- Replace the existing "Coming Soon" placeholder at /units route
- Design system table pattern: Space Mono headers, Level 1 shadow, 7px radius, color-mix() badges

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `AdminUserController` / `AdminBarangayController` / `AdminIncidentTypeController`: Established admin CRUD pattern to follow
- `Users.vue` / `UserForm.vue`: Table + form page pattern with design system styling, Dialog for delete confirmation
- `Unit` model: Already has string PK, fillable fields, UnitType/UnitStatus casts, coordinate serialization, user/incident relationships
- `UnitFactory` / `UnitSeeder`: Existing test data generation
- `UnitType` enum: Ambulance, Fire, Rescue, Police, Boat
- `UnitStatus` enum: Available, Dispatched, EnRoute, OnScene, Offline
- Badge component with color-mix() pattern for role badges
- Select/Input/Label/Dialog Shadcn components in ui/

### Established Patterns
- Admin routes: `Route::resource('units', AdminUnitController::class)` in routes/admin.php
- Admin routes registered via `withRouting(then:)` callback in bootstrap/app.php
- FormRequest classes for validation (array-style rules)
- Wayfinder actions for frontend route generation
- IncidentType destroy uses soft-disable pattern (precedent for decommission)

### Integration Points
- routes/admin.php: Add Route::resource for units
- routes/web.php line 97: Replace "Coming Soon" placeholder with redirect or remove
- Sidebar navigation: Add Units link under admin section
- User model unit_id foreign key: Crew assignment affects Users table

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 11-implement-units-crud*
*Context gathered: 2026-03-14*
