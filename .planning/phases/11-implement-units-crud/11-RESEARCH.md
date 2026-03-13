# Phase 11: Implement Units CRUD - Research

**Researched:** 2026-03-14
**Domain:** Laravel admin CRUD + Inertia/Vue table/form pages following established project patterns
**Confidence:** HIGH

## Summary

This phase implements a standard admin CRUD for response units (AMB-01, FIRE-01, etc.). The project already has three working admin CRUD implementations (Users, IncidentTypes, Barangays) that establish a clear, replicable pattern. The Unit model, factory, seeder, enums, and migration all exist from Phase 1 -- no schema changes are needed beyond potentially adding a `decommissioned_at` timestamp column.

The core work is: (1) create AdminUnitController following the AdminUserController/AdminIncidentTypeController pattern, (2) create StoreUnitRequest/UpdateUnitRequest form requests, (3) create Units.vue index page and UnitForm.vue create/edit page following the established design system table pattern, (4) add a Route::resource entry in routes/admin.php, (5) update the sidebar `/units` link to point to the admin units page, (6) handle crew assignment via inline multi-select of responder users, and (7) implement decommission/recommission instead of hard delete.

**Primary recommendation:** Follow the existing admin CRUD pattern exactly. The Unit model already exists with all needed fields. The main complexity is the crew assignment multi-select (bidirectional unit_id management on User records) and the decommission soft-disable pattern (precedent exists in IncidentType's is_active pattern).

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Unit ID auto-generated from type + next sequence number (e.g. admin picks Ambulance -> system generates AMB-03)
- System queries existing units of that type to determine next number, zero-padded to 2 digits
- Callsign auto-generated as default (e.g. "Ambulance 3") but editable by admin
- Agency field is a dropdown with presets (CDRRMO, BFP, PNP) plus "Other" option that reveals a free-text field
- Shift field included as optional dropdown (Day / Night / Unassigned)
- Unit form includes inline multi-select of available responder users for crew assignment
- Assigning a responder sets their unit_id; removing clears it -- bidirectional management
- Soft warning (badge) when assigned crew count exceeds crew_capacity, but save is not blocked
- Units index table shows crew as "2/4" format (assigned/capacity)
- Admin can only set Available or Offline status; other statuses (Dispatched, En Route, On Scene) are controlled by dispatch/responder workflow only
- Coordinates are optional -- not required on create. Units get real coordinates once responders start GPS tracking
- Units index table shows status as colored badge (green=Available, gray=Offline, blue=En Route, yellow=On Scene, etc.) matching dispatch map marker colors
- Units index table shows type as colored badge (per type)
- "Delete" action decommissions the unit instead of hard delete -- preserves historical references
- Decommissioning automatically unassigns all crew members (sets unit_id to null)
- Decommissioned units appear in the table with muted/faded styling and "Decommissioned" badge
- "Recommission" action button restores a decommissioned unit to Available status
- Follow the existing admin CRUD pattern exactly: Route::resource in routes/admin.php, AdminUnitController, Units.vue + UnitForm.vue
- Replace the existing "Coming Soon" placeholder at /units route
- Design system table pattern: Space Mono headers, Level 1 shadow, 7px radius, color-mix() badges

### Claude's Discretion
- Exact type badge color assignments
- Table column ordering
- Form layout and field grouping
- Whether to use a dedicated `decommissioned_at` timestamp or extend UnitStatus enum

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel 12 | v12 | Backend framework, Eloquent ORM, routing | Project foundation |
| Inertia.js | v2 | Server-driven SPA rendering | Established pattern for all admin pages |
| Vue 3 | v3 | Frontend components | All admin pages use Vue 3 + script setup |
| Reka UI | latest | Combobox (multi-select for crew) | Already in project as ui/combobox |
| Tailwind CSS | v4 | Styling with design system tokens | Design system alignment from Phase 10 |
| Wayfinder | v0 | TypeScript route generation | Used by all admin form pages |
| Pest | v4 | Feature tests | Admin test pattern established |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| lucide-vue-next | installed | Icons (Truck icon for units) | Sidebar navigation |
| Reka UI ComboboxRoot | installed | Multi-select for crew assignment | UnitForm.vue crew field |

### Alternatives Considered
None -- all decisions locked to existing project patterns.

**Installation:**
No new packages needed. Everything required is already installed.

## Architecture Patterns

### Recommended Project Structure
```
app/Http/Controllers/Admin/
    AdminUnitController.php         # Resource controller (index, create, store, edit, update, destroy)
app/Http/Requests/Admin/
    StoreUnitRequest.php            # Validation for create
    UpdateUnitRequest.php           # Validation for update
resources/js/pages/admin/
    Units.vue                       # Index page with data table
    UnitForm.vue                    # Create/edit form page
routes/
    admin.php                       # Add Route::resource('units', ...)
database/migrations/
    YYYY_MM_DD_XXXXXX_add_decommissioned_at_to_units_table.php  # If using timestamp approach
tests/Feature/Admin/
    AdminUnitTest.php               # Feature tests
```

### Pattern 1: Admin Resource Controller
**What:** Standard Laravel resource controller with Inertia rendering
**When to use:** All admin CRUD operations
**Example (from AdminUserController):**
```php
// index: Query with eager loading, render Inertia page with supporting data
public function index(): Response
{
    $units = Unit::query()
        ->withCount('users')
        ->with('users:id,name,unit_id')
        ->orderBy('type')
        ->orderBy('id')
        ->get();

    return Inertia::render('admin/Units', [
        'units' => $units,
        'types' => UnitType::cases(),
        'statuses' => [UnitStatus::Available, UnitStatus::Offline],
    ]);
}

// store: FormRequest validated, create model, redirect with flash
public function store(StoreUnitRequest $request): RedirectResponse
{
    $validated = $request->validated();
    Unit::query()->create($validated);
    // Sync crew via User::where(unit_id)->update + User::whereIn(crew_ids)->update
    return redirect()->route('admin.units.index')
        ->with('success', 'Unit created successfully.');
}

// destroy: Soft-disable (decommission) instead of delete
public function destroy(Unit $unit): RedirectResponse
{
    $unit->update(['decommissioned_at' => now()]);
    $unit->users()->update(['unit_id' => null]); // Unassign all crew
    return redirect()->route('admin.units.index')
        ->with('success', 'Unit decommissioned successfully.');
}
```

### Pattern 2: FormRequest Validation (Array-Style Rules)
**What:** Dedicated request classes with array-based validation rules
**When to use:** All store/update operations
**Example (from StoreUserRequest):**
```php
public function rules(): array
{
    return [
        'callsign' => ['required', 'string', 'max:50'],
        'type' => ['required', Rule::in(array_column(UnitType::cases(), 'value'))],
        'agency' => ['required', 'string', 'max:50'],
        'crew_capacity' => ['required', 'integer', 'min:1', 'max:20'],
        'status' => ['required', Rule::in(['AVAILABLE', 'OFFLINE'])],
        'shift' => ['nullable', Rule::in(['day', 'night'])],
        'notes' => ['nullable', 'string', 'max:1000'],
        'crew_ids' => ['nullable', 'array'],
        'crew_ids.*' => ['exists:users,id'],
    ];
}
```

### Pattern 3: Inertia Table Page with Design System
**What:** Vue page with table following DS-08 pattern (Space Mono headers, Level 1 shadow, color-mix badges)
**When to use:** All admin index pages
**Example (from Users.vue):**
```html
<div class="overflow-hidden rounded-[7px] border border-border bg-card shadow-[var(--shadow-1)]">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-border bg-card">
            <tr>
                <th class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase">
                    Column Name
                </th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-border transition-colors hover:bg-accent">
                <!-- cells -->
            </tr>
        </tbody>
    </table>
</div>
```

### Pattern 4: Inertia Form Page with useForm + Wayfinder
**What:** Vue form using Inertia useForm with Wayfinder action imports
**When to use:** All admin create/edit pages
**Example (from UserForm.vue):**
```typescript
import { store, update } from '@/actions/App/Http/Controllers/Admin/AdminUnitController';

const form = useForm({
    callsign: props.unit?.callsign ?? '',
    type: props.unit?.type ?? '',
    // ...
});

function submit(): void {
    if (isEditing.value && props.unit) {
        form.submit(update(props.unit.id));
    } else {
        form.submit(store());
    }
}
```

### Pattern 5: Soft-Disable with Visual Feedback
**What:** Decommission pattern following IncidentType's `is_active` / `opacity-50` approach
**When to use:** Delete action on units
**Example (from IncidentTypes.vue):**
```html
<tr :class="{ 'opacity-50': !type.is_active }">
    <!-- ... -->
    <Button v-if="type.is_active" @click="disableType(type)" class="text-destructive">
        Disable
    </Button>
    <Button v-else @click="enableType(type)" class="text-t-online">
        Enable
    </Button>
</tr>
```

### Pattern 6: Auto-Generated Unit ID
**What:** Server-side ID generation from type prefix + next sequence number
**When to use:** Unit creation (not editable after create)
**Logic:**
```php
// In controller store() or a service method:
$prefix = match($type) {
    UnitType::Ambulance => 'AMB',
    UnitType::Fire => 'FIRE',
    UnitType::Rescue => 'RESCUE',
    UnitType::Police => 'POLICE',
    UnitType::Boat => 'BOAT',
};

$maxNumber = Unit::query()
    ->where('type', $type)
    ->selectRaw("MAX(CAST(SUBSTRING(id FROM '[0-9]+$') AS INTEGER)) as max_num")
    ->value('max_num') ?? 0;

$nextNumber = str_pad((string)($maxNumber + 1), 2, '0', STR_PAD_LEFT);
$unitId = "{$prefix}-{$nextNumber}";
```

### Anti-Patterns to Avoid
- **Allowing admin to set Dispatched/EnRoute/OnScene status:** These are workflow-only statuses. Admin form restricts to Available/Offline.
- **Hard deleting units:** Breaks foreign key references in incident_unit pivot and historical data. Always soft-disable.
- **Building custom multi-select from scratch:** Reka UI Combobox with `multiple` prop already exists in project.
- **Allowing unit ID editing after creation:** The ID is used as a string PK referenced throughout the system.
- **Blocking save when crew exceeds capacity:** Per user decision, show warning badge only, allow save.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Multi-select dropdown | Custom checkbox list | Reka UI Combobox with `multiple` prop | Already wrapped in `ui/combobox`, handles search/filter |
| Form validation | Inline controller validation | FormRequest classes (StoreUnitRequest, UpdateUnitRequest) | Project convention, cleaner controllers |
| Route generation | Manual URL construction | Wayfinder auto-generated actions/routes | All admin pages use this pattern |
| Colored badges | Custom badge components | Existing `Badge` component + `color-mix()` pattern | Established in Users.vue, IncidentTypes.vue |
| Delete confirmation | Custom modal | Existing `Dialog` component from `ui/dialog` | Used in Users.vue for delete confirmation |

**Key insight:** This phase has zero architectural novelty. Every pattern needed already exists in the codebase. The task is replication with unit-specific business logic.

## Common Pitfalls

### Pitfall 1: String Primary Key Model Binding
**What goes wrong:** Laravel route model binding may not work with string PKs out of the box if not configured.
**Why it happens:** Unit model uses `$incrementing = false` and `$keyType = 'string'`.
**How to avoid:** The Unit model already sets `$incrementing = false` and `$keyType = 'string'`. Route model binding will work correctly because Laravel checks the model's key type. Just use `Route::resource('units', AdminUnitController::class)` and type-hint `Unit $unit` in controller methods.
**Warning signs:** 404 errors on edit/update/destroy routes.

### Pitfall 2: Unit ID Uniqueness Race Condition
**What goes wrong:** Two simultaneous creates could generate the same ID.
**Why it happens:** SELECT max then INSERT is not atomic.
**How to avoid:** The `id` column is a primary key, so the database will reject duplicates. Wrap in try/catch and retry, or use a DB-level unique constraint (already enforced by PK). In practice, admin creates units infrequently, so this is LOW risk.
**Warning signs:** Integrity constraint violation on store.

### Pitfall 3: Crew Assignment Bidirectionality
**What goes wrong:** Assigning crew to a unit doesn't clear their previous unit_id, or removing from form doesn't clear unit_id.
**Why it happens:** The User.unit_id foreign key is on the Users table, not a pivot.
**How to avoid:** On store/update, perform a two-step sync: (1) clear unit_id for all users previously on this unit who are NOT in the new crew list, (2) set unit_id for all users in the new crew list. Use two queries:
```php
User::where('unit_id', $unit->id)->whereNotIn('id', $crewIds)->update(['unit_id' => null]);
User::whereIn('id', $crewIds)->update(['unit_id' => $unit->id]);
```
**Warning signs:** Responders appearing in multiple units or orphaned from their unit.

### Pitfall 4: Decommission vs. Active Dispatch Assignments
**What goes wrong:** Decommissioning a unit that is currently dispatched to an active incident.
**Why it happens:** Admin might not realize the unit is on an active call.
**How to avoid:** Check `activeIncidents()` relationship before allowing decommission. If the unit has active incidents (unassigned_at is null on pivot), show an error or warning. The controller destroy method should check this.
**Warning signs:** Decommissioned units still appearing on dispatch map with active assignments.

### Pitfall 5: Sidebar Navigation Link Mismatch
**What goes wrong:** The existing `/units` link in the sidebar (for admin and supervisor roles) points to the old ComingSoon placeholder, not the new admin units page.
**Why it happens:** The sidebar currently uses `href: '/units'` which routes to the web.php placeholder, but the new admin page will be at `/admin/units`.
**How to avoid:** Update the sidebar `href` from `/units` to `/admin/units` for the Units nav item. Also update or remove the old placeholder route in web.php.
**Warning signs:** Clicking "Units" in sidebar shows ComingSoon page instead of the new CRUD.

### Pitfall 6: Responder Users Filter for Crew Assignment
**What goes wrong:** The crew multi-select shows all users instead of only responders.
**Why it happens:** Not filtering by role when querying available responders.
**How to avoid:** Query `User::where('role', UserRole::Responder)` for the crew assignment options. Include currently assigned users (even if they're on another unit) to allow reassignment.
**Warning signs:** Non-responder users appearing in crew assignment dropdown.

## Code Examples

Verified patterns from the existing codebase:

### Admin Route Registration (routes/admin.php)
```php
// Source: routes/admin.php (existing pattern)
Route::resource('users', AdminUserController::class);
Route::resource('incident-types', AdminIncidentTypeController::class);
Route::resource('barangays', AdminBarangayController::class)->only(['index', 'edit', 'update']);
Route::resource('units', AdminUnitController::class);
// Add custom recommission route:
Route::post('units/{unit}/recommission', [AdminUnitController::class, 'recommission'])->name('units.recommission');
```

### Color-Mix Badge Pattern (design system)
```html
<!-- Source: Users.vue roleColors pattern -->
<!-- Status badge colors matching dispatch map -->
<Badge variant="secondary" :class="statusColors[unit.status] ?? ''">
    {{ unit.status }}
</Badge>

<!-- In script: -->
const statusColors: Record<string, string> = {
    AVAILABLE: 'bg-[color-mix(in_srgb,var(--t-unit-available)_12%,transparent)] text-t-unit-available',
    DISPATCHED: 'bg-[color-mix(in_srgb,var(--t-unit-dispatched)_12%,transparent)] text-t-unit-dispatched',
    EN_ROUTE: 'bg-[color-mix(in_srgb,var(--t-unit-enroute)_12%,transparent)] text-t-unit-enroute',
    ON_SCENE: 'bg-[color-mix(in_srgb,var(--t-unit-onscene)_12%,transparent)] text-t-unit-onscene',
    OFFLINE: 'bg-[color-mix(in_srgb,var(--t-unit-offline)_12%,transparent)] text-t-unit-offline',
};
```

### Reka UI Combobox Multi-Select (crew assignment)
```html
<!-- Source: Reka UI ComboboxRoot supports multiple prop (verified via ComboboxRootProps type) -->
<Combobox v-model="form.crew_ids" multiple>
    <ComboboxInput placeholder="Search responders..." />
    <ComboboxContent>
        <ComboboxEmpty>No responders found.</ComboboxEmpty>
        <ComboboxItem v-for="user in responders" :key="user.id" :value="user.id">
            {{ user.name }}
        </ComboboxItem>
    </ComboboxContent>
</Combobox>
```

### Decommission Button Pattern (from IncidentTypes.vue disable/enable)
```html
<!-- Source: IncidentTypes.vue disableType/enableType pattern -->
<Button v-if="!unit.decommissioned_at" variant="ghost" size="sm"
    class="text-destructive hover:text-destructive" @click="decommissionUnit(unit)">
    Decommission
</Button>
<Button v-else variant="ghost" size="sm"
    class="text-t-online hover:text-t-online" @click="recommissionUnit(unit)">
    Recommission
</Button>
```

### Unit ID Prefix Mapping (from UnitFactory)
```php
// Source: database/factories/UnitFactory.php
$prefixes = [
    UnitType::Ambulance->value => 'AMB',
    UnitType::Fire->value => 'FIRE',
    UnitType::Rescue->value => 'RESCUE',
    UnitType::Police->value => 'POLICE',
    UnitType::Boat->value => 'BOAT',
];
```

### Test Pattern (from AdminUserTest.php / AdminIncidentTypeTest.php)
```php
// Source: tests/Feature/Admin/AdminUserTest.php
it('allows admin to list units', function () {
    $admin = User::factory()->admin()->create();
    Unit::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.units.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Units')
            ->has('units', 3)
            ->has('types')
        );
});

it('blocks non-admin from unit routes', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('admin.units.index'))
        ->assertStatus(403);
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Placeholder `/units` route | Admin CRUD at `/admin/units` | Phase 11 (this phase) | Units become manageable |
| Manual unit creation via seeder only | Admin UI for create/edit/decommission | Phase 11 | Admins self-service unit management |

**Deprecated/outdated:**
- The `/units` route in `routes/web.php` line 97 serving ComingSoon placeholder -- to be replaced/removed

## Design Decision: Decommission Implementation

**Option A: `decommissioned_at` timestamp column** (RECOMMENDED)
- Add nullable `decommissioned_at` timestamp to units table via migration
- Pros: Tracks when decommission happened, clear semantic meaning, does not pollute UnitStatus enum
- Cons: Requires migration, one more column
- Pattern precedent: Soft deletes use the same timestamp approach

**Option B: Extend UnitStatus enum with Decommissioned case**
- Add `case Decommissioned = 'DECOMMISSIONED'` to UnitStatus enum
- Pros: No migration needed, status is already a column
- Cons: Mixes admin-managed state (Available/Offline/Decommissioned) with workflow-managed state (Dispatched/EnRoute/OnScene), could confuse dispatch logic that filters by status

**Recommendation:** Option A (`decommissioned_at` timestamp). It cleanly separates admin lifecycle management from operational status. A decommissioned unit has no status -- it's removed from service entirely. This avoids any chance of decommissioned units appearing in dispatch queries that filter by status.

## Type Badge Color Assignments (Claude's Discretion)

Recommended type badge colors using existing design system tokens:

| Unit Type | Token | Color | Rationale |
|-----------|-------|-------|-----------|
| Ambulance | `t-p1` | Red (#dc2626) | Medical emergency association |
| Fire | `t-p2` | Orange (#ea580c) | Fire association |
| Rescue | `t-accent` | Blue (#2563eb) | General rescue/utility |
| Police | `t-role-supervisor` | Purple (#7c3aed) | Distinct from other types |
| Boat | `t-ch-sms` | Teal/Emerald (#059669) | Water/maritime association |

These use existing CSS custom properties -- no new tokens needed.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact tests/Feature/Admin/AdminUnitTest.php` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| UNIT-01 | Admin can list all units with crew count, status, type | Feature | `php artisan test --compact --filter="admin to list units"` | No - Wave 0 |
| UNIT-02 | Admin can create unit with auto-generated ID | Feature | `php artisan test --compact --filter="admin to create unit"` | No - Wave 0 |
| UNIT-03 | Admin can edit unit callsign, agency, capacity, status, shift | Feature | `php artisan test --compact --filter="admin to update unit"` | No - Wave 0 |
| UNIT-04 | Decommission sets decommissioned_at and unassigns crew | Feature | `php artisan test --compact --filter="decommission"` | No - Wave 0 |
| UNIT-05 | Recommission clears decommissioned_at and sets Available | Feature | `php artisan test --compact --filter="recommission"` | No - Wave 0 |
| UNIT-06 | Crew assignment syncs User.unit_id bidirectionally | Feature | `php artisan test --compact --filter="crew"` | No - Wave 0 |
| UNIT-07 | Non-admin users blocked from admin unit routes | Feature | `php artisan test --compact --filter="blocks non-admin"` | No - Wave 0 |
| UNIT-08 | Unit ID uniqueness enforced (type prefix + sequence) | Feature | `php artisan test --compact --filter="auto-generated"` | No - Wave 0 |
| UNIT-09 | Admin can only set Available or Offline status | Feature | `php artisan test --compact --filter="status validation"` | No - Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact tests/Feature/Admin/AdminUnitTest.php`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Admin/AdminUnitTest.php` -- covers UNIT-01 through UNIT-09
- [ ] Migration for `decommissioned_at` column (if timestamp approach chosen)

## Open Questions

1. **Should the `/units` route in web.php be removed or redirected?**
   - What we know: Currently at line 97 in web.php, serves ComingSoon placeholder for supervisor+admin roles
   - What's unclear: Should it redirect to `/admin/units` or be removed entirely?
   - Recommendation: Replace with a redirect to `route('admin.units.index')` so bookmarks and the sidebar link both work. Update the sidebar href to `/admin/units` as well.

2. **Should decommissioned units be excluded from dispatch queries?**
   - What we know: Dispatch console queries units for the map and nearby-unit suggestions
   - What's unclear: Do existing dispatch queries already filter by status (which wouldn't catch decommissioned)?
   - Recommendation: If using `decommissioned_at` timestamp, add a `scopeActive()` to Unit model that filters `whereNull('decommissioned_at')`. Update dispatch queries to use this scope.

## Sources

### Primary (HIGH confidence)
- Existing codebase: `app/Http/Controllers/Admin/AdminUserController.php` -- established controller pattern
- Existing codebase: `app/Http/Controllers/Admin/AdminIncidentTypeController.php` -- soft-disable pattern
- Existing codebase: `app/Models/Unit.php` -- model with string PK, enums, relationships
- Existing codebase: `database/factories/UnitFactory.php` -- ID generation prefix logic
- Existing codebase: `resources/js/pages/admin/Users.vue` -- design system table pattern
- Existing codebase: `resources/js/pages/admin/UserForm.vue` -- form with useForm + Wayfinder
- Existing codebase: `resources/js/pages/admin/IncidentTypes.vue` -- disable/enable toggle pattern
- Existing codebase: `resources/css/app.css` -- design system tokens including `--t-unit-*` status colors
- Existing codebase: `routes/admin.php` -- Route::resource pattern with admin middleware
- Existing codebase: `bootstrap/app.php` -- admin route group registration
- Existing codebase: `tests/Feature/Admin/AdminUserTest.php` -- test pattern with assertInertia
- Existing codebase: `resources/js/components/ui/combobox/` -- Reka UI Combobox wrapper (supports multiple)

### Secondary (MEDIUM confidence)
- Reka UI documentation: ComboboxRoot supports `multiple` prop for multi-select behavior

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all libraries already in use, no new dependencies
- Architecture: HIGH -- exact pattern replication from 3 existing admin CRUDs
- Pitfalls: HIGH -- identified from analyzing existing code relationships and constraints
- Decommission approach: MEDIUM -- recommendation is sound but "Claude's discretion" area

**Research date:** 2026-03-14
**Valid until:** Indefinite -- based entirely on stable project codebase patterns
