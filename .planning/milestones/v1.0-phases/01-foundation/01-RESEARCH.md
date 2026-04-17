# Phase 1: Foundation - Research

**Researched:** 2026-03-12
**Domain:** PostgreSQL + PostGIS, RBAC, Laravel data models, barangay spatial data
**Confidence:** HIGH

## Summary

Phase 1 establishes the data layer and role system for the entire IRMS platform. The core work involves: (1) switching the development database from SQLite to PostgreSQL with PostGIS for spatial queries, (2) creating migrations for 6+ tables (incidents, units, barangays, incident_timeline, incident_messages, incident_types), (3) implementing role-based access control with 4 roles and corresponding navigation, (4) seeding 86 Butuan City barangay boundary polygons, and (5) building an admin panel for user/role/type/barangay management.

The project already has a working Laravel 12 application with Fortify authentication, Vue 3 + Inertia.js frontend, Reka UI components, and a sidebar layout. The existing User model needs a `role` column (not a separate roles table -- the CONTEXT.md specifies one role per user). The IRMS specification (Section 5) provides exact SQL schemas for all tables. PostGIS extension must be installed on the local PostgreSQL 18 instance at `/Library/PostgreSQL/18/`. The test suite currently uses SQLite in-memory and will need a separate PostgreSQL test database for PostGIS-dependent tests.

**Primary recommendation:** Use Laravel's native `geography()` column type in migrations (no third-party PostGIS package needed for column creation), use `clickbar/laravel-magellan` v2 for model casts and PostGIS function access in the query builder, and use custom gates/policies + a role-check middleware for RBAC (no Spatie -- overkill for 4 fixed roles with one role per user). Keep non-spatial tests on SQLite for speed; create a separate PostgreSQL test database for spatial-specific feature tests.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Full admin panel UI for user creation and role assignment
- Admin-only account creation -- disable public registration (Fortify registration route removed)
- One role per user (not multiple roles)
- Admin can create new user accounts and assign a role in one flow
- Four roles: dispatcher, responder, supervisor, admin
- Incident types stored in a database table (categories + types), not enums or config
- Seeded from the IRMS specification's 8 categories with 40+ types (Medical, Fire, Natural Disaster, Vehicular, Crime/Security, Hazmat, Water Rescue, Public Disturbance)
- Each type has a default priority suggestion (e.g., Structure Fire -> P1, Minor Injury -> P3)
- Admin can manage incident types in the admin panel (add, edit, disable)
- Source 86 boundary polygons from OpenStreetMap (Overpass API) or PSA shapefiles
- Each barangay record includes: name, district, boundary polygon (geography), risk level (low/moderate/high/very high), population
- Risk levels seeded from known CDRRMO/DENR hazard assessment data
- Admin can edit barangay metadata (risk level, population, district) but not boundary polygons
- Dispatcher nav: Dashboard, Dispatch Console, Incident Queue, Incidents List, Messages
- Responder nav: Active Assignment, My Incidents, Messages, Profile/Settings (mobile-first)
- Supervisor nav: Dashboard (KPIs), Dispatch Console (read-only), All Incidents, Units, Analytics/Reports
- Admin nav: Full system access + Admin Panel (users, roles, incident types, barangay metadata)
- Phase 1 shows full navigation per role with placeholder "Coming Soon" pages

### Claude's Discretion
- Permission implementation approach (Spatie, custom gates/policies, or simple role checks)
- Database migration strategy for switching from SQLite to PostgreSQL
- Admin panel UI component choices and layout
- Placeholder page design
- Unit types seeder content (AMB, RESCUE, FIRE, etc.)

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| FNDTN-01 | PostgreSQL with PostGIS extension for all spatial queries | Native Laravel 12 `geography()` migration method + PostGIS CREATE EXTENSION; PostGIS 3.x on PostgreSQL 18; .env port fix 3306->5432 |
| FNDTN-02 | Barangay reference table with 86 boundary polygons, district, risk level, GiST spatial index | faeldon/philippines-json-maps GeoJSON source or Overpass API admin_level=10; `$table->spatialIndex()` for GiST; seeder parses GeoJSON |
| FNDTN-03 | RBAC with four roles and permissions matrix per spec Section 9 | Custom `role` enum column on users table + `EnsureUserHasRole` middleware + Laravel Gates for fine-grained checks; spec Section 9 defines 10 permissions |
| FNDTN-04 | User associated with unit (responders linked to AMB-01, etc.) | `unit_id` foreign key on users table referencing units table; nullable for non-responder roles |
| FNDTN-05 | Incident data model with lifecycle timestamps, vitals JSONB, assessment_tags, coordinates geography, timeline | Full migration from spec Section 5.1; `geography('coordinates', 'point', 4326)`; JSONB cast; TEXT[] via custom cast or Magellan |
| FNDTN-06 | Units data model with GPS coordinates (geography), status, type, agency, crew, shift, GiST index | Migration from spec Section 5.2; `geography('coordinates', 'point', 4326)` + `spatialIndex()` |
| FNDTN-07 | Incident timeline table (append-only audit log) | Migration from spec Section 5.3; incident_id FK, event_type, event_data JSONB, actor polymorphic fields |
| FNDTN-08 | Incident messages table for bi-directional communication | Migration from spec Section 5.4; incident_id FK, from_id/from_type polymorphic, body, read_at |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel 12 | ^12.0 | Application framework | Already installed; native `geography()` migration support |
| PostgreSQL | 18 | Primary database | Already installed at `/Library/PostgreSQL/18/`; required for PostGIS |
| PostGIS | 3.x | Spatial extension | Required for geography types, ST_Contains, ST_DWithin, GiST indexes |
| clickbar/laravel-magellan | ^2.0 | PostGIS model casts + query builder | Laravel 12 compatible; typed Point/Polygon casts; ST_* functions in query builder |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel Fortify | ^1.30 | Authentication (already installed) | Login, 2FA -- disable registration feature |
| Inertia.js v2 | ^2.0 | SPA routing (already installed) | All page rendering |
| Reka UI | existing | Headless UI components | Admin panel forms, tables, modals |
| lucide-vue-next | existing | Icons | Navigation icons per role |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| clickbar/laravel-magellan | Raw DB::select for spatial queries | Magellan provides typed casts and query builder integration; raw SQL works but loses Eloquent ergonomics |
| Custom gates/middleware | Spatie laravel-permission v7 | Spatie is overkill for 4 fixed roles with 1 role per user; adds unnecessary tables (roles, permissions, model_has_roles, etc.) |
| Custom gates/middleware | Laravel Policies only | Policies are model-scoped; we also need route-level role gating which requires middleware |

**Installation:**
```bash
composer require clickbar/laravel-magellan
php artisan vendor:publish --tag="magellan-migrations"
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Enums/
│   ├── UserRole.php              # Backed enum: Admin, Dispatcher, Responder, Supervisor
│   ├── IncidentStatus.php        # Pending, Dispatched, Acknowledged, EnRoute, OnScene, Resolving, Resolved
│   ├── IncidentPriority.php      # P1, P2, P3, P4
│   ├── UnitStatus.php            # Available, Dispatched, EnRoute, OnScene, Offline
│   └── UnitType.php              # Ambulance, Fire, Rescue, Police, Boat
├── Models/
│   ├── User.php                  # Extended with role enum cast, unit relationship
│   ├── Incident.php              # Geography cast, JSONB casts, timeline/messages relationships
│   ├── Unit.php                  # Geography cast, user relationship
│   ├── Barangay.php              # Geography polygon cast, incidents relationship
│   ├── IncidentTimeline.php      # Append-only, belongs to incident
│   ├── IncidentMessage.php       # Belongs to incident, polymorphic sender
│   └── IncidentType.php          # Category + type name + default priority
├── Http/
│   ├── Controllers/
│   │   └── Admin/                # AdminUserController, AdminIncidentTypeController, AdminBarangayController
│   ├── Middleware/
│   │   └── EnsureUserHasRole.php # Route middleware: role:admin, role:dispatcher,supervisor
│   └── Requests/
│       └── Admin/                # FormRequest classes for admin panel
├── Policies/
│   ├── UserPolicy.php            # Gate checks for user management
│   └── IncidentTypePolicy.php    # Gate checks for type management
database/
├── migrations/
│   ├── XXXX_enable_postgis_extension.php
│   ├── XXXX_add_role_fields_to_users_table.php
│   ├── XXXX_create_units_table.php
│   ├── XXXX_create_barangays_table.php
│   ├── XXXX_create_incident_types_table.php
│   ├── XXXX_create_incidents_table.php
│   ├── XXXX_create_incident_timeline_table.php
│   └── XXXX_create_incident_messages_table.php
├── seeders/
│   ├── BarangaySeeder.php        # Parse GeoJSON, insert 86 barangays
│   ├── IncidentTypeSeeder.php    # 8 categories, 40+ types with default priority
│   ├── UnitSeeder.php            # Sample units: AMB-01, RESCUE-01, FIRE-01, etc.
│   └── AdminUserSeeder.php       # Create initial admin user
├── data/
│   └── butuan-barangays.geojson  # 86 barangay boundary polygons
resources/js/
├── pages/
│   ├── admin/                    # Admin panel pages
│   │   ├── Users.vue             # User list + create/edit
│   │   ├── IncidentTypes.vue     # Type management
│   │   └── Barangays.vue         # Barangay metadata management
│   ├── dashboard/
│   │   └── Dashboard.vue         # Role-aware dashboard (replaces existing)
│   └── placeholder/
│       └── ComingSoon.vue        # Reusable placeholder for unbuilt features
├── components/
│   ├── AppSidebar.vue            # Modified for role-based nav items
│   └── admin/                    # Admin panel components
routes/
├── web.php                       # Updated with role middleware groups
└── admin.php                     # Admin-only routes
```

### Pattern 1: Role Enum + Column on Users Table
**What:** A PHP backed enum for the 4 roles, stored as a string column directly on the users table. No separate roles/permissions tables.
**When to use:** When roles are fixed (not user-configurable), one role per user, and fewer than ~10 roles.
**Example:**
```php
// app/Enums/UserRole.php
enum UserRole: string
{
    case Admin = 'admin';
    case Dispatcher = 'dispatcher';
    case Responder = 'responder';
    case Supervisor = 'supervisor';
}

// In User model casts() method:
protected function casts(): array
{
    return [
        'role' => UserRole::class,
        // ...existing casts
    ];
}

// Usage:
$user->role === UserRole::Admin
$user->role->value // 'admin'
```

### Pattern 2: Role-Check Middleware
**What:** Custom middleware that checks if the authenticated user has one of the allowed roles for a route group.
**When to use:** Route-level access control. Register as alias in bootstrap/app.php.
**Example:**
```php
// app/Http/Middleware/EnsureUserHasRole.php
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role->value, $roles, true)) {
            abort(403);
        }
        return $next($request);
    }
}

// bootstrap/app.php -- register alias:
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureUserHasRole::class,
    ]);
})

// routes/admin.php:
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    // admin-only routes
});
```

### Pattern 3: Laravel Gates for Permission Checks
**What:** Define gates in AuthServiceProvider or AppServiceProvider that map spec Section 9 permissions to role checks.
**When to use:** For Blade/Inertia conditional rendering and controller-level authorization beyond route middleware.
**Example:**
```php
// In AppServiceProvider boot():
Gate::define('manage-users', fn (User $user) => $user->role === UserRole::Admin);
Gate::define('create-incidents', fn (User $user) => in_array($user->role, [
    UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin,
], true));
Gate::define('view-analytics', fn (User $user) => in_array($user->role, [
    UserRole::Supervisor, UserRole::Admin,
], true));
```

### Pattern 4: Geography Columns with Magellan Casts
**What:** Laravel 12 native `geography()` in migrations + Magellan Point/Polygon casts on models.
**When to use:** All models with spatial columns.
**Example:**
```php
// Migration:
$table->geography('coordinates', subtype: 'point', srid: 4326)->nullable();
$table->spatialIndex('coordinates');

// Model:
use Clickbar\Magellan\Data\Geometries\Point;
protected $casts = [
    'coordinates' => Point::class,
];

// Creating:
use Clickbar\Magellan\Data\Geometries\Point;
$unit = Unit::create([
    'id' => 'AMB-01',
    'coordinates' => Point::makeGeodetic(8.9475, 125.5406), // lat, lng for Butuan
]);
```

### Pattern 5: Sharing Role Data via Inertia
**What:** Pass user role and permissions to all frontend pages via HandleInertiaRequests middleware.
**When to use:** Frontend needs to conditionally render navigation and UI based on user role.
**Example:**
```php
// In HandleInertiaRequests share():
'auth' => [
    'user' => $request->user() ? array_merge(
        $request->user()->only('id', 'name', 'email', 'role'),
        ['can' => [
            'manage_users' => $request->user()->can('manage-users'),
            'create_incidents' => $request->user()->can('create-incidents'),
            'view_analytics' => $request->user()->can('view-analytics'),
            // ... other gates
        ]]
    ) : null,
],
```

### Anti-Patterns to Avoid
- **Multiple role tables for fixed roles:** Do not create `roles`, `permissions`, `role_user` tables when you have 4 fixed roles and one per user. A single `role` column on users is sufficient.
- **Hardcoded role strings in controllers:** Use the `UserRole` enum everywhere, never raw strings like `'admin'`.
- **Checking roles in Vue instead of server:** Always enforce role checks server-side (middleware + gates). Frontend checks are UX-only.
- **Using `DB::select()` for spatial queries:** Use Magellan's query builder integration instead to keep Eloquent consistency.
- **Storing coordinates as two separate lat/lng float columns:** Use PostGIS geography type for proper spatial indexing and functions.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Geography/geometry type handling | Custom WKT/WKB serialization | clickbar/laravel-magellan v2 | Handles casting, SRID, serialization, query builder ST_* functions |
| PostGIS spatial functions | Raw `DB::select('SELECT ST_Contains(...)')` | Magellan query builder: `ST::contains()`, `ST::dWithin()` | Type-safe, composable with Eloquent, handles parameter binding |
| GeoJSON parsing for seeder | Custom JSON traversal + coordinate extraction | PHP `json_decode` + Magellan `Polygon::fromJson()` | Magellan handles coordinate ring ordering and SRID |
| Password hashing & auth | Custom auth system | Laravel Fortify (already configured) | Battle-tested, 2FA support already working |
| Form validation | Inline controller validation | FormRequest classes (existing pattern) | Matches existing codebase convention |

**Key insight:** The PostGIS spatial domain is where hand-rolling is most dangerous. Point-in-polygon, distance calculations, and coordinate system handling have subtle edge cases (coordinate ring winding order, SRID mismatches, geography vs geometry distance units). Magellan handles these correctly.

## Common Pitfalls

### Pitfall 1: PostGIS Extension Not Enabled
**What goes wrong:** Migrations fail with `type "geography" does not exist` error.
**Why it happens:** PostGIS extension must be created before any geography columns are used.
**How to avoid:** First migration must run `CREATE EXTENSION IF NOT EXISTS postgis;` before any table creation. Use a raw statement in a migration.
**Warning signs:** Migration errors mentioning unknown type "geography" or "geometry".

### Pitfall 2: .env Port Mismatch
**What goes wrong:** Laravel cannot connect to PostgreSQL; connection refused or timeout.
**Why it happens:** The current `.env` has `DB_PORT=3306` (MySQL default) but PostgreSQL uses 5432.
**How to avoid:** Update `.env` to `DB_PORT=5432` and verify `DB_CONNECTION=pgsql`. Also update `DB_USERNAME` and `DB_PASSWORD` to match PostgreSQL credentials.
**Warning signs:** `SQLSTATE[08006]` or connection refused errors.

### Pitfall 3: SQLite Test Suite Breaks on PostGIS Features
**What goes wrong:** Tests that use geography columns, spatial indexes, or PostGIS functions fail on SQLite.
**Why it happens:** SQLite does not support geography types or PostGIS functions. The current test suite uses `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`.
**How to avoid:** Dual testing strategy: (1) Keep non-spatial tests on SQLite for speed, (2) Create a PostgreSQL test database `irms_testing` and a separate test group or `.env.testing` for spatial tests. Alternatively, switch all tests to PostgreSQL using an `.env.testing` file.
**Warning signs:** Tests passing locally but failing on spatial assertions, or migration errors in test runs.

### Pitfall 4: Geography vs Geometry Confusion
**What goes wrong:** Distance queries return wrong units (degrees instead of meters) or spatial index not used.
**Why it happens:** `geometry` uses planar coordinates (degrees for SRID 4326), `geography` uses spheroidal calculations (meters). ST_DWithin on geography uses meters; on geometry uses the coordinate system units.
**How to avoid:** Always use `geography` type with SRID 4326 for real-world lat/lng data. The spec uses GEOGRAPHY(POINT, 4326) and GEOGRAPHY(POLYGON, 4326) consistently -- follow this.
**Warning signs:** Distances returned in seemingly tiny numbers (those are degrees, not meters).

### Pitfall 5: Fortify Registration Still Enabled
**What goes wrong:** Public users can create accounts, bypassing admin-only account creation.
**Why it happens:** `config/fortify.php` still has `Features::registration()` in the features array.
**How to avoid:** Remove `Features::registration()` from the features array in `config/fortify.php`. Also remove or guard the register view route from FortifyServiceProvider.
**Warning signs:** `/register` route still accessible.

### Pitfall 6: Barangay GeoJSON Coordinate Order
**What goes wrong:** Barangay polygons appear in wrong location or ST_Contains returns incorrect results.
**Why it happens:** GeoJSON uses [longitude, latitude] order, but some sources may have [latitude, longitude]. PostGIS with SRID 4326 geography type expects (longitude, latitude).
**How to avoid:** Verify a sample coordinate pair from the GeoJSON (Butuan City is approximately lat 8.95, lng 125.54). If the first number is ~8.x, coordinates are in [lat, lng] order and must be swapped.
**Warning signs:** Polygons rendering in the wrong hemisphere or ocean.

### Pitfall 7: RefreshDatabase + PostGIS `migrate:fresh` Conflict
**What goes wrong:** `migrate:fresh` drops all tables but PostGIS system tables (`spatial_ref_sys`) persist, causing type conflicts on re-migration.
**Why it happens:** PostGIS creates its own system tables that should not be dropped.
**How to avoid:** Use `RefreshDatabase` trait (wraps in transactions, avoids fresh migration), not `DatabaseMigrations`. If fresh migration is needed, the PostGIS extension migration should use `CREATE EXTENSION IF NOT EXISTS`.
**Warning signs:** "relation spatial_ref_sys already exists" or "type geography already exists" errors during test runs.

## Code Examples

Verified patterns from official sources:

### Enable PostGIS Extension (First Migration)
```php
// Source: PostGIS docs + Laravel 12 raw statement migration
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS postgis CASCADE;');
    }
};
```

### Barangay Table Migration
```php
// Source: IRMS Spec Section 5.6 + Laravel 12 migration docs
Schema::create('barangays', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('district', 50)->nullable();
    $table->string('city', 50)->default('Butuan City');
    $table->geography('boundary', subtype: 'polygon', srid: 4326)->nullable();
    $table->integer('population')->nullable();
    $table->string('risk_level', 20)->nullable(); // low, moderate, high, very_high
    $table->timestamps();

    $table->spatialIndex('boundary');
});
```

### Incident Table Migration (Key Fields)
```php
// Source: IRMS Spec Section 5.1
Schema::create('incidents', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('incident_no', 20)->unique();
    $table->foreignId('incident_type_id')->constrained();
    $table->string('priority', 2); // P1, P2, P3, P4
    $table->string('status', 30)->default('PENDING');
    $table->string('channel', 20);
    $table->text('location_text')->nullable();
    $table->geography('coordinates', subtype: 'point', srid: 4326)->nullable();
    $table->foreignId('barangay_id')->nullable()->constrained();
    $table->string('caller_name', 100)->nullable();
    $table->string('caller_contact', 30)->nullable();
    $table->text('raw_message')->nullable();
    $table->text('notes')->nullable();
    $table->string('assigned_unit', 20)->nullable();
    $table->timestamp('dispatched_at')->nullable();
    $table->timestamp('acknowledged_at')->nullable();
    $table->timestamp('en_route_at')->nullable();
    $table->timestamp('on_scene_at')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->string('outcome', 50)->nullable();
    $table->string('hospital', 100)->nullable();
    $table->integer('scene_time_sec')->nullable();
    $table->smallInteger('checklist_pct')->nullable();
    $table->jsonb('vitals')->nullable();
    $table->text('assessment_tags')->nullable(); // PostgreSQL TEXT[] via custom cast
    $table->text('closure_notes')->nullable();
    $table->string('report_pdf_url', 255)->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamps();

    $table->spatialIndex('coordinates');
    $table->index('priority');
    $table->index('status');
    $table->index('created_at');
});
```

### Point-in-Polygon Query (Barangay Lookup)
```php
// Source: PostGIS docs ST_Contains
use Clickbar\Magellan\Data\Geometries\Point;
use App\Models\Barangay;

$point = Point::makeGeodetic(8.9475, 125.5406); // lat, lng

$barangay = Barangay::query()
    ->whereRaw('ST_Contains(boundary::geometry, ?::geometry)', [
        $point->toWkt(),
    ])
    ->first();
```

### Nearby Units Query (ST_DWithin)
```php
// Source: PostGIS docs ST_DWithin -- distance in meters for geography type
use App\Models\Unit;

$nearbyUnits = Unit::query()
    ->where('status', 'AVAILABLE')
    ->whereRaw('ST_DWithin(coordinates, ST_MakePoint(?, ?)::geography, ?)', [
        $longitude, $latitude, 10000, // 10km radius in meters
    ])
    ->orderByRaw('ST_Distance(coordinates, ST_MakePoint(?, ?)::geography)', [
        $longitude, $latitude,
    ])
    ->get();
```

### Role-Based Navigation (Vue Frontend)
```typescript
// Source: Existing AppSidebar.vue pattern + role-based extension
import type { NavItem } from '@/types';
import type { UserRole } from '@/types/auth';

const navItemsByRole: Record<UserRole, NavItem[]> = {
    admin: [
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
        { title: 'Dispatch Console', href: '/dispatch', icon: Map },
        // ... all items + admin panel
    ],
    dispatcher: [
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
        { title: 'Dispatch Console', href: '/dispatch', icon: Map },
        { title: 'Incident Queue', href: '/incidents/queue', icon: ListOrdered },
        { title: 'Incidents', href: '/incidents', icon: AlertTriangle },
        { title: 'Messages', href: '/messages', icon: MessageSquare },
    ],
    responder: [
        { title: 'Active Assignment', href: '/assignment', icon: RadioTower },
        { title: 'My Incidents', href: '/my-incidents', icon: ClipboardList },
        { title: 'Messages', href: '/messages', icon: MessageSquare },
    ],
    supervisor: [
        { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid },
        { title: 'Dispatch Console', href: '/dispatch', icon: Map },
        { title: 'All Incidents', href: '/incidents', icon: AlertTriangle },
        { title: 'Units', href: '/units', icon: Truck },
        { title: 'Analytics', href: '/analytics', icon: BarChart3 },
    ],
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `$table->point()` / `$table->polygon()` | `$table->geography('col', subtype: 'point', srid: 4326)` | Laravel 11 | Consistent spatial column API across all databases |
| `$casts` property on models | `casts()` method on models | Laravel 11 | Project already follows this convention |
| `app/Http/Kernel.php` middleware | `bootstrap/app.php` `withMiddleware()` | Laravel 11 | Project already uses this pattern |
| Spatie laravel-permission for all RBAC | Custom enum + gates for simple cases | Always valid | Spatie still recommended for complex multi-role/multi-permission setups |
| mstaack/laravel-postgis `$postgisFields` | clickbar/laravel-magellan v2 native casts | 2024 | Magellan v2 removed `HasPostgisColumns` trait; uses standard Laravel casts |

**Deprecated/outdated:**
- `mstaack/laravel-postgis`: Last updated 2023; `$postgisFields` pattern replaced by Magellan's native casts
- `phaza/laravel-postgis`: Abandoned; do not use
- `$casts` property: Still works but `casts()` method is the project convention

## Existing Codebase Integration Points

### Files That Must Change
| File | Change | Reason |
|------|--------|--------|
| `.env` | `DB_PORT=5432`, update `DB_USERNAME`/`DB_PASSWORD` for PostgreSQL | Port mismatch (currently 3306) |
| `config/fortify.php` | Remove `Features::registration()` | Disable public registration per CONTEXT.md |
| `app/Providers/FortifyServiceProvider.php` | Remove `registerView` | No registration page |
| `app/Models/User.php` | Add `role` column, `unit_id` FK, enum cast, unit relationship | RBAC + responder-unit association |
| `database/factories/UserFactory.php` | Add `role` default + role state methods | Testing needs role variants |
| `bootstrap/app.php` | Register `role` middleware alias, add `admin.php` route file | Role-based routing |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share role + permissions via `auth.user.can` | Frontend role-based rendering |
| `resources/js/components/AppSidebar.vue` | Role-based nav items from auth.user.role | Different nav per role |
| `resources/js/types/auth.ts` | Add `role` and `can` to User type | TypeScript type safety |
| `phpunit.xml` | Update for PostgreSQL test database (or add .env.testing) | PostGIS-dependent tests |

### Files That Stay the Same
- `resources/js/layouts/AppLayout.vue` -- wrapper delegates to AppSidebarLayout, no changes needed
- `resources/js/layouts/AuthLayout.vue` -- guest layout stays for login
- `app/Actions/Fortify/` -- auth actions stay (except CreateNewUser may be repurposed or bypassed)

## Barangay Data Strategy

### Source Options (ranked by reliability)
1. **faeldon/philippines-json-maps** (GitHub): Pre-processed GeoJSON at multiple resolutions; PSA-sourced; includes barangay-level boundaries. Download the Butuan City barangay subset.
2. **altcoder/philippines-psgc-shapefiles** (GitHub): High-resolution shapefiles from PSA PSGC data. Requires shapefile-to-GeoJSON conversion.
3. **Overpass API query**: `[out:json]; area["name"="Butuan"]["admin_level"="4"]->.city; rel(area.city)["admin_level"="10"]; out geom;` -- real-time OSM data but coverage/accuracy varies.

### Recommended Approach
Use `faeldon/philippines-json-maps` 2023 edition. Download the barangay-level GeoJSON for Region XIII (Caraga), filter to Butuan City (PSGC code 163040000), and store as `database/data/butuan-barangays.geojson`. The seeder will parse this file and insert 86 records.

### Data Enrichment
Population data from PSA 2024 Census (385,530 total, per-barangay available from citypopulation.de). District assignments and risk levels need manual mapping based on CDRRMO hazard assessment data. The seeder should accept a secondary JSON mapping file for risk levels and district assignments.

## Unit Type Seeder Content (Claude's Discretion)

Recommended unit types based on CDRRMO Butuan organizational structure:

| ID | Name | Type | Agency | Crew |
|----|------|------|--------|------|
| AMB-01 | Ambulance 1 | ambulance | CDRRMO | 3 |
| AMB-02 | Ambulance 2 | ambulance | CDRRMO | 3 |
| AMB-03 | Ambulance 3 | ambulance | CDRRMO | 3 |
| RESCUE-01 | Rescue Unit 1 | rescue | CDRRMO | 4 |
| RESCUE-02 | Rescue Unit 2 | rescue | CDRRMO | 4 |
| FIRE-01 | Fire Engine 1 | fire | BFP | 6 |
| FIRE-02 | Fire Engine 2 | fire | BFP | 6 |
| POLICE-01 | Patrol Unit 1 | police | PNP | 2 |
| POLICE-02 | Patrol Unit 2 | police | PNP | 2 |
| BOAT-01 | Rescue Boat 1 | boat | CDRRMO | 4 |

## Permission Approach Recommendation (Claude's Discretion)

**Recommendation: Custom role enum + middleware + Laravel Gates.**

Rationale:
- Spatie laravel-permission v7 requires PHP ^8.4 and creates 5 extra database tables (roles, permissions, model_has_roles, model_has_permissions, role_has_permissions). This is unnecessary when we have exactly 4 fixed roles with one role per user.
- A `role` string column on users + a PHP backed enum provides type-safe role checks with zero additional tables.
- A custom `EnsureUserHasRole` middleware handles route-level access.
- Laravel Gates (defined in AppServiceProvider) handle the 10 permissions from the spec Section 9 matrix.
- If requirements grow later (dynamic roles, per-user permissions), Spatie can be added without major refactoring.

## Testing Strategy

### Dual Database Approach
Since PostGIS functions (ST_Contains, ST_DWithin) are not available in SQLite, the test suite needs a split strategy:

1. **Non-spatial tests (majority):** Keep using SQLite in-memory via `phpunit.xml` for speed. Covers auth, RBAC middleware, admin panel CRUD, validation, navigation rendering.
2. **Spatial tests:** Create a dedicated `.env.testing` or a test group using PostgreSQL with PostGIS. Covers barangay point-in-polygon, nearby unit queries, coordinate storage/retrieval.

### Recommended Configuration
Create `.env.testing`:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=irms_testing
DB_USERNAME=postgres
DB_PASSWORD=<local_password>
```

Update `phpunit.xml` to remove the SQLite overrides (DB_CONNECTION, DB_DATABASE, DB_URL lines), so `.env.testing` takes precedence. Alternatively, keep SQLite as default in phpunit.xml and use `--env=testing` flag for spatial test runs.

### Preferred: Switch All Tests to PostgreSQL
Given that the production database is PostgreSQL, switching all tests to PostgreSQL is actually the safer choice (avoids SQLite/PostgreSQL behavior differences). The speed penalty is acceptable for this project size. The PostGIS extension migration ensures the extension is available before spatial tables are created.

## Open Questions

1. **PostgreSQL credentials on this machine**
   - What we know: PostgreSQL 18 is running at `/Library/PostgreSQL/18/` on port 5432. The .env has `DB_USERNAME=root` and `DB_PASSWORD=password` which are likely incorrect for PostgreSQL (default user is `postgres`).
   - What's unclear: The actual PostgreSQL superuser password for the local installation.
   - Recommendation: The implementer will need to verify credentials and create the `irms` and `irms_testing` databases. Command: `/Library/PostgreSQL/18/bin/createdb -U postgres irms`

2. **PostGIS installation on PostgreSQL 18**
   - What we know: PostGIS extension files are not present at `/Library/PostgreSQL/18/share/extension/`. PostgreSQL 18 is installed via EDB installer.
   - What's unclear: Whether PostGIS can be installed via EDB StackBuilder or if Homebrew `brew install postgis` would target this PostgreSQL 18 instance.
   - Recommendation: Use EDB StackBuilder (included with the PostgreSQL installer) to add PostGIS 3.x, or install via `brew install postgis` if using Homebrew PostgreSQL. This is a prerequisite before any migrations can run.

3. **Barangay boundary data completeness**
   - What we know: Butuan City has 86 barangays per PSA. GeoJSON sources exist (faeldon/philippines-json-maps, Overpass API).
   - What's unclear: Whether all 86 boundaries are present in the GeoJSON source, and whether the polygon accuracy is sufficient for point-in-polygon dispatch.
   - Recommendation: Download and validate the data before writing the seeder. Count records and visually inspect a few polygons.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 (with PHPUnit 12 backend) |
| Config file | `phpunit.xml` + `tests/Pest.php` |
| Quick run command | `php artisan test --compact --filter=testName` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| FNDTN-01 | PostGIS extension active, geography columns exist | feature | `php artisan test --compact --filter=PostgisSetup` | Wave 0 |
| FNDTN-02 | 86 barangays seeded, point-in-polygon query works | feature | `php artisan test --compact --filter=BarangaySpatial` | Wave 0 |
| FNDTN-03 | Role middleware blocks/allows correctly, gates enforce permissions | feature | `php artisan test --compact --filter=RoleAccess` | Wave 0 |
| FNDTN-04 | Responder has unit_id, unit relationship works | feature | `php artisan test --compact --filter=UserUnit` | Wave 0 |
| FNDTN-05 | Incident model creates with all fields, geography stored, JSONB casts | feature | `php artisan test --compact --filter=IncidentModel` | Wave 0 |
| FNDTN-06 | Unit model with geography coordinates, GiST index, status enum | feature | `php artisan test --compact --filter=UnitModel` | Wave 0 |
| FNDTN-07 | Timeline entry appended on incident creation, immutable | feature | `php artisan test --compact --filter=IncidentTimeline` | Wave 0 |
| FNDTN-08 | Message created on incident, bi-directional query works | feature | `php artisan test --compact --filter=IncidentMessage` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=<relevant_test_class>`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Foundation/PostgisSetupTest.php` -- covers FNDTN-01
- [ ] `tests/Feature/Foundation/BarangaySpatialTest.php` -- covers FNDTN-02
- [ ] `tests/Feature/Foundation/RoleAccessTest.php` -- covers FNDTN-03
- [ ] `tests/Feature/Foundation/UserUnitTest.php` -- covers FNDTN-04
- [ ] `tests/Feature/Foundation/IncidentModelTest.php` -- covers FNDTN-05
- [ ] `tests/Feature/Foundation/UnitModelTest.php` -- covers FNDTN-06
- [ ] `tests/Feature/Foundation/IncidentTimelineTest.php` -- covers FNDTN-07
- [ ] `tests/Feature/Foundation/IncidentMessageTest.php` -- covers FNDTN-08
- [ ] `.env.testing` -- PostgreSQL test database configuration
- [ ] Factory files for all new models: `IncidentFactory`, `UnitFactory`, `BarangayFactory`, `IncidentTypeFactory`, `IncidentTimelineFactory`, `IncidentMessageFactory`
- [ ] Update `UserFactory` with role states: `->admin()`, `->dispatcher()`, `->responder()`, `->supervisor()`

## Sources

### Primary (HIGH confidence)
- [Laravel 12 Migrations docs](https://laravel.com/docs/12.x/migrations) - `geography()` method syntax, `spatialIndex()`, column types
- [Laravel 12 Authorization docs](https://laravel.com/docs/12.x/authorization) - Gates, policies, middleware pattern
- [PostGIS official docs](https://postgis.net/docs/) - ST_Contains, ST_DWithin, geography vs geometry, GiST indexes
- IRMS Specification `docs/IRMS-Specification.md` Sections 5 (Data Models), 8 (Priority/Classification), 9 (Roles/Permissions)
- Existing codebase: `app/Models/User.php`, `bootstrap/app.php`, `routes/web.php`, `phpunit.xml`, `tests/Pest.php`

### Secondary (MEDIUM confidence)
- [clickbar/laravel-magellan](https://github.com/clickbar/laravel-magellan) - v2.0.1 on Packagist, Laravel 12 compatible, PHP ^8.2
- [spatie/laravel-permission](https://packagist.org/packages/spatie/laravel-permission) - v7.2.3, requires PHP ^8.4, Laravel 12 compatible (evaluated but not recommended)
- [faeldon/philippines-json-maps](https://github.com/faeldon/philippines-json-maps) - Philippine barangay GeoJSON boundaries, PSA-sourced
- [PSA Butuan City population data](https://rssocaraga.psa.gov.ph/content/psa-adn-highlights-population-city-butuan) - 2024 Census, 385,530 population, per-barangay breakdown
- [citypopulation.de Butuan](https://www.citypopulation.de/en/philippines/butuan/) - Per-barangay population figures

### Tertiary (LOW confidence)
- PostGIS install method for EDB PostgreSQL 18 on macOS -- needs hands-on verification
- Completeness of 86 barangay boundaries in faeldon/philippines-json-maps -- needs download and validation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Laravel 12 native geography support verified via official docs; Magellan v2 confirmed on Packagist with Laravel 12 support
- Architecture: HIGH - Patterns follow existing codebase conventions (enum casts, middleware registration, Inertia prop sharing); IRMS spec provides exact schemas
- Pitfalls: HIGH - PostGIS extension issues, port mismatch, and SQLite testing limitations are well-documented problems with known solutions
- Barangay data: MEDIUM - Sources identified but actual data completeness unverified until download

**Research date:** 2026-03-12
**Valid until:** 2026-04-12 (30 days -- stable domain, libraries have long-term support)
