# Phase 9: Create a Public Facing Reporting App - Research

**Researched:** 2026-03-13
**Domain:** Public-facing Vue SPA + Laravel API (citizen emergency reporting)
**Confidence:** HIGH

## Summary

This phase builds a separate Vue 3 SPA in a `/report-app/` monorepo subfolder that communicates with the main Laravel backend via versioned `/api/v1/citizen/*` REST endpoints. The citizen app has no authentication requirement. Citizens select an incident type from a curated visual grid, provide location (via GPS or manual barangay selection) and details, then submit. The report creates an Incident record directly (channel='app', status=PENDING) which appears in the operator intake feed via the existing WebSocket IncidentCreated event. Citizens track reports using an 8-character tracking token.

The main technical domains are: (1) setting up a standalone Vue 3 + Vue Router 4 SPA with its own Vite build separate from the Inertia app, (2) adding Laravel API routes with rate limiting and CORS for the public-facing endpoints, (3) database migrations to add `tracking_token` to incidents and `show_in_public_app` to incident_types, (4) an API controller and Eloquent API Resources for the citizen endpoints, and (5) browser Geolocation API integration with PostGIS barangay lookup fallback.

**Primary recommendation:** Build the report-app as a standalone Vue 3 + Vue Router 4 + Tailwind CSS v4 SPA in `/report-app/` with its own package.json and vite.config.ts, sharing design tokens via a copied CSS file. Laravel backend adds `routes/api.php` with versioned citizen endpoints, API Resources, and throttle middleware.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- No authentication -- citizens submit reports without login
- Contact number is required (not optional); name remains optional
- Reports stored in browser localStorage for "My Reports" tab (same device only)
- Additionally, a "Track by ID" lookup page allows checking any report from any device using the tracking token
- Status updates fetched on page visit (poll on visit) -- no WebSocket, no auto-refresh for public app
- Citizen report creates an Incident directly with channel='app' and status=PENDING
- No separate Report model -- reuses existing Incident model and intake pipeline
- Short random 8-char hash token generated per report (e.g., A7F2B3K9) for citizen tracking -- not the internal INC-YYYY-NNNNN number
- Token stored on incident record; citizens use it for lookup; operators see the normal INC number
- Simplified citizen-facing status mapping: PENDING -> "Received", TRIAGED -> "Verified", DISPATCHED/ACKNOWLEDGED/EN_ROUTE/ON_SCENE/RESOLVING -> "Dispatched", RESOLVED -> "Resolved"
- GPS geolocation requested from device; if granted, auto-detect coordinates + PostGIS barangay lookup; if denied, fall back to manual barangay dropdown + address text
- Curated subset of ~12-15 most common types shown as visual cards (matching prototype grid style)
- Admin-configurable: add `show_in_public_app` boolean flag to incident_types table; admin toggles which types citizens see
- "Other Emergency" catch-all type always visible
- Priority badge shown on each type card (P1 CRITICAL, P2 HIGH, etc.) -- auto-set from type, operators can override during triage
- Separate Vue SPA in monorepo subfolder `/report-app/` with its own package.json, Vite config, and Vue setup
- Shares design tokens (DM Sans + Space Mono fonts, color tokens) via copy or symlink from main app
- API endpoints in main Laravel app under versioned `/api/v1/citizen/*` route group
- No auth middleware on citizen API routes -- rate-limited to prevent abuse
- CORS configured to allow citizen app domain
- System-aware dark mode via `prefers-color-scheme` -- no manual toggle
- The HTML prototype at `docs/irms-report-app.html` is the authoritative design reference

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

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

## Standard Stack

### Core (Report App -- `/report-app/`)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| vue | ^3.5 | UI framework | Same as main app, consistent ecosystem |
| vue-router | ^4.5 | Client-side routing | Standard for standalone Vue SPAs (NOT Inertia) |
| tailwindcss | ^4.1 | Styling | Same as main app, shared design tokens |
| vite | ^7.0 | Build tool | Same as main app |
| @vitejs/plugin-vue | ^6.0 | Vue SFC compilation | Same as main app |
| @tailwindcss/vite | ^4.1 | Tailwind Vite plugin | Same as main app |
| typescript | ^5.2 | Type safety | Same as main app |
| lucide-vue-next | ^0.468 | Icons | Same as main app |

### Core (Backend -- API additions to main Laravel app)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel framework | v12 | API routes, rate limiting, CORS | Already installed |
| Eloquent API Resources | built-in | JSON response transformation | Laravel convention for APIs |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| class-variance-authority | ^0.7 | Component variant styling | Optional -- only if reusing Shadcn-style pattern |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Standalone Vue SPA | Inertia page within main app | Decision locked: separate SPA for public-facing, no auth |
| Vue Router | No router (single page) | Need bottom nav with Home/Reports/About + multi-step report flow |
| Copied CSS tokens | npm workspace sharing | Copy is simpler for a single shared file; workspace adds tooling overhead |

**Installation (report-app):**
```bash
cd report-app
npm init -y
npm install vue vue-router tailwindcss lucide-vue-next
npm install -D vite @vitejs/plugin-vue @tailwindcss/vite typescript vue-tsc
```

## Architecture Patterns

### Recommended Project Structure

```
report-app/
  package.json
  vite.config.ts
  tsconfig.json
  index.html                     # SPA entry point
  src/
    main.ts                      # Vue app + router mount
    App.vue                      # Root component with bottom nav + router-view
    router/
      index.ts                   # Vue Router config with createWebHistory
    views/
      HomeView.vue               # Hero, CTA button, quick tips, recent reports, hotline
      ReportTypeView.vue         # Step 1: incident type grid selection
      ReportDetailsView.vue      # Step 2: location + description form
      ReportConfirmView.vue      # Step 3: submission confirmation + status pipeline
      MyReportsView.vue          # localStorage report list + Track by ID
      TrackReportView.vue        # Single report tracking (token lookup)
      AboutView.vue              # CDRRMO info, app info, data privacy
    components/
      BottomNav.vue              # Home | My Reports | About
      PriorityBadge.vue          # P1/P2/P3/P4 badge
      StatusBadge.vue            # RECEIVED/VERIFIED/DISPATCHED/RESOLVED badge
      StatusPipeline.vue         # Vertical pipeline tracker
      TypeCard.vue               # Incident type selection card with icon
      StepIndicator.vue          # 3-step progress bar
    composables/
      useGeolocation.ts          # Browser Geolocation API wrapper
      useReportStorage.ts        # localStorage CRUD for report tracking
      useApi.ts                  # fetch wrapper for citizen API calls
    types/
      index.ts                   # TypeScript interfaces (Report, IncidentType, etc.)
    assets/
      tokens.css                 # Design tokens (copied from main app)
      app.css                    # Tailwind imports + token integration
```

### Pattern 1: Standalone Vue SPA with Vue Router 4
**What:** A Vue 3 app using `createRouter` + `createWebHistory` for client-side routing, NOT Inertia.
**When to use:** Public-facing app without authentication, separate from the Inertia admin app.
**Example:**
```typescript
// report-app/src/router/index.ts
import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'home', component: () => import('@/views/HomeView.vue') },
        { path: '/report/type', name: 'report-type', component: () => import('@/views/ReportTypeView.vue') },
        { path: '/report/details', name: 'report-details', component: () => import('@/views/ReportDetailsView.vue') },
        { path: '/report/confirm', name: 'report-confirm', component: () => import('@/views/ReportConfirmView.vue') },
        { path: '/reports', name: 'my-reports', component: () => import('@/views/MyReportsView.vue') },
        { path: '/track/:token', name: 'track-report', component: () => import('@/views/TrackReportView.vue') },
        { path: '/about', name: 'about', component: () => import('@/views/AboutView.vue') },
    ],
});
```

### Pattern 2: Laravel API Routes with Rate Limiting
**What:** Stateless API routes under `/api/v1/citizen/*` with throttle middleware.
**When to use:** Public unauthenticated endpoints that need abuse prevention.
**Example:**
```php
// routes/api.php
use App\Http\Controllers\Api\V1\CitizenReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/citizen')->middleware('throttle:citizen-reports')->group(function () {
    Route::get('incident-types', [CitizenReportController::class, 'incidentTypes'])
        ->name('api.citizen.incident-types');
    Route::post('reports', [CitizenReportController::class, 'store'])
        ->name('api.citizen.reports.store');
    Route::get('reports/{token}', [CitizenReportController::class, 'show'])
        ->name('api.citizen.reports.show');
    Route::get('barangays', [CitizenReportController::class, 'barangays'])
        ->name('api.citizen.barangays');
});
```

### Pattern 3: Eloquent API Resources for JSON Responses
**What:** Transform models to JSON using dedicated Resource classes, following Laravel convention.
**When to use:** All API endpoints returning model data.
**Example:**
```php
// app/Http/Resources/V1/CitizenIncidentTypeResource.php
namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CitizenIncidentTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'code' => $this->code,
            'default_priority' => $this->default_priority,
            'description' => $this->description,
        ];
    }
}
```

### Pattern 4: Tracking Token Generation
**What:** Generate URL-safe, collision-resistant 8-character alphanumeric tokens for citizen report tracking.
**When to use:** On incident creation via citizen app.
**Example:**
```php
// In CitizenReportController or as a model boot hook
use Illuminate\Support\Str;

/**
 * Generate a unique 8-character URL-safe tracking token.
 * Uses uppercase alphanumeric characters (A-Z, 0-9) excluding ambiguous chars (0/O, 1/I/L).
 */
public static function generateTrackingToken(): string
{
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // 30 chars, no 0/O/1/I/L
    do {
        $token = '';
        $bytes = random_bytes(8);
        for ($i = 0; $i < 8; $i++) {
            $token .= $chars[ord($bytes[$i]) % strlen($chars)];
        }
    } while (Incident::where('tracking_token', $token)->exists());

    return $token;
}
```

**Analysis:** 30^8 = ~656 billion combinations. At 100K incidents, collision probability is ~0.000008%. The do-while loop provides absolute collision guarantee. Using `random_bytes()` (CSPRNG) ensures cryptographic randomness.

### Pattern 5: Geolocation Composable with Fallback
**What:** Browser Geolocation API wrapper that requests GPS, then sends coordinates to backend for PostGIS barangay lookup, with manual fallback.
**When to use:** Report details form location field.
**Example:**
```typescript
// report-app/src/composables/useGeolocation.ts
import { ref } from 'vue';

export function useGeolocation() {
    const latitude = ref<number | null>(null);
    const longitude = ref<number | null>(null);
    const status = ref<'idle' | 'requesting' | 'granted' | 'denied'>('idle');
    const error = ref<string | null>(null);

    async function requestLocation(): Promise<boolean> {
        if (!('geolocation' in navigator)) {
            status.value = 'denied';
            error.value = 'Geolocation not available';
            return false;
        }
        status.value = 'requesting';
        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    latitude.value = position.coords.latitude;
                    longitude.value = position.coords.longitude;
                    status.value = 'granted';
                    resolve(true);
                },
                (err) => {
                    status.value = 'denied';
                    error.value = err.message;
                    resolve(false);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
            );
        });
    }

    return { latitude, longitude, status, error, requestLocation };
}
```

### Pattern 6: localStorage Report Storage
**What:** Store submitted reports in localStorage with the tracking token for "My Reports" tab.
**When to use:** After successful report submission.
**Example:**
```typescript
// report-app/src/composables/useReportStorage.ts
interface StoredReport {
    token: string;
    type: string;
    priority: string;
    barangay: string;
    status: string;
    submittedAt: string;
    description: string;
}

const STORAGE_KEY = 'irms-citizen-reports';

export function useReportStorage() {
    function getReports(): StoredReport[] {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    }

    function addReport(report: StoredReport): void {
        const reports = getReports();
        reports.unshift(report);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(reports.slice(0, 50)));
    }

    function updateReportStatus(token: string, status: string): void {
        const reports = getReports();
        const idx = reports.findIndex((r) => r.token === token);
        if (idx !== -1) {
            reports[idx].status = status;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(reports));
        }
    }

    return { getReports, addReport, updateReportStatus };
}
```

### Anti-Patterns to Avoid
- **Sharing node_modules between main app and report-app:** Keep dependency trees separate. The report-app is a standalone SPA with no Inertia dependency.
- **Using Inertia in the report-app:** This is a pure Vue SPA talking to a REST API. No server-side routing, no Inertia page props.
- **Exposing internal incident IDs or INC numbers to citizens:** Always use the 8-char tracking token for citizen-facing operations.
- **WebSocket in citizen app:** Decision locked: poll on visit only. No Echo, no Reverb connection from the public app.
- **Putting API routes in web.php:** Use `routes/api.php` which automatically gets the `api` middleware group (stateless, no session).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Rate limiting | Custom IP tracking | Laravel `RateLimiter::for()` + `throttle:` middleware | Built-in, Redis-backed, handles X-RateLimit headers |
| CORS | Manual header injection | Laravel built-in CORS config (`config/cors.php`) | Framework handles preflight, caching, credentials |
| JSON transformation | Manual array building in controllers | Eloquent API Resources (`JsonResource`) | Consistent format, reusable, conditional fields |
| Token generation | `rand()` or `uniqid()` | `random_bytes()` with collision check loop | CSPRNG for unpredictability, DB uniqueness guarantee |
| Geolocation permission | Custom permission API | Browser `navigator.geolocation` with feature detection | Standard Web API, handles all permission states |
| Form validation (client) | Manual if/else checks | Vue reactive computed validators | Reactive, composable, template-bindable |
| API fetch wrapper | Raw fetch everywhere | Centralized `useApi` composable with base URL and error handling | DRY, consistent error handling, base URL config |

**Key insight:** The citizen app is deliberately simple -- no auth, no real-time, no complex state management. Resist over-engineering. Vue's built-in reactivity + Vue Router + localStorage covers all client-side needs without Pinia or other state libraries.

## Common Pitfalls

### Pitfall 1: CORS Misconfiguration
**What goes wrong:** Report app on a different origin (e.g., `report.irms.test`) gets CORS-blocked when calling `irms.test/api/v1/citizen/*`.
**Why it happens:** Laravel's default CORS config allows `api/*` paths but `allowed_origins` must include the report app's origin.
**How to avoid:** Publish `config/cors.php` with `php artisan config:publish cors`. Set `allowed_origins` to include the report app domain. In dev, use `['*']` or `['https://report.irms.test']`.
**Warning signs:** Browser console shows "Access-Control-Allow-Origin" errors on API calls.

### Pitfall 2: Missing `routes/api.php` Registration
**What goes wrong:** API routes return 404.
**Why it happens:** Laravel 12 does not create `routes/api.php` by default. The file must exist AND be registered in `bootstrap/app.php`.
**How to avoid:** Create `routes/api.php` and add `api: __DIR__.'/../routes/api.php'` to the `withRouting()` call in `bootstrap/app.php`. The `/api` prefix is automatically applied.
**Warning signs:** `php artisan route:list` does not show the citizen API routes.

### Pitfall 3: Geolocation Requires HTTPS
**What goes wrong:** `navigator.geolocation.getCurrentPosition()` silently fails or the browser blocks it.
**Why it happens:** The Geolocation API is only available in secure contexts (HTTPS). `localhost` is exempt, but custom domains like `report.irms.test` need HTTPS.
**How to avoid:** Laravel Herd serves sites over HTTPS by default (`https://report.irms.test`). Ensure Vite dev server also proxies or serves over HTTPS.
**Warning signs:** `status` stays on 'requesting' forever; no permission prompt appears.

### Pitfall 4: Vue Router History Mode Requires Server Fallback
**What goes wrong:** Direct URL access (e.g., refresh on `/reports` or `/track/ABC123`) returns 404.
**Why it happens:** `createWebHistory()` uses clean URLs, but the server needs to serve `index.html` for all routes.
**How to avoid:** Configure the server (Herd/Nginx) to fallback to `index.html` for the report app. In dev, Vite handles this automatically. In production, add a catch-all rewrite rule.
**Warning signs:** Pages work via navigation but 404 on direct URL access or refresh.

### Pitfall 5: Tracking Token Exposure in Logs
**What goes wrong:** Tracking tokens appear in server logs, error reports, or URL parameters visible to third parties.
**Why it happens:** Token is in the URL path (`/api/v1/citizen/reports/{token}`) and may be logged.
**How to avoid:** This is acceptable for the use case (tokens are not authentication secrets, just lookup keys). However, ensure tokens are NOT the internal UUID or INC number. The 8-char token has limited information value.
**Warning signs:** N/A -- this is a conscious tradeoff, not a bug.

### Pitfall 6: Incident Model Fillable Missing New Columns
**What goes wrong:** `Illuminate\Database\Eloquent\MassAssignmentException` when creating incidents with tracking_token.
**Why it happens:** New `tracking_token` column not added to `$fillable` array in Incident model.
**How to avoid:** Add `tracking_token` to the Incident model's `$fillable` array in the same task that creates the migration.
**Warning signs:** 500 error on citizen report submission.

## Code Examples

### Laravel: Register API Routes in bootstrap/app.php
```php
// bootstrap/app.php - add api route registration
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',  // ADD THIS
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // existing admin routes...
        },
    )
    // ... rest unchanged
```

### Laravel: Custom Rate Limiter for Citizen API
```php
// app/Providers/AppServiceProvider.php boot() method
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

RateLimiter::for('citizen-reports', function (Request $request) {
    return [
        // 5 report submissions per minute per IP
        Limit::perMinute(5)->by($request->ip())->response(function () {
            return response()->json([
                'message' => 'Too many reports submitted. Please wait before trying again.',
            ], 429);
        }),
    ];
});

RateLimiter::for('citizen-reads', function (Request $request) {
    // 30 reads per minute per IP (type listing, report tracking)
    return Limit::perMinute(30)->by($request->ip());
});
```

### Laravel: Migration for tracking_token and show_in_public_app
```php
// database/migrations/xxxx_add_citizen_reporting_columns.php
Schema::table('incidents', function (Blueprint $table) {
    $table->string('tracking_token', 8)->nullable()->unique()->after('incident_no');
    $table->index('tracking_token');
});

Schema::table('incident_types', function (Blueprint $table) {
    $table->boolean('show_in_public_app')->default(false)->after('is_active');
});
```

### Laravel: CitizenReportController
```php
// app/Http/Controllers/Api/V1/CitizenReportController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CitizenIncidentTypeResource;
use App\Http\Resources\V1\CitizenReportResource;

class CitizenReportController extends Controller
{
    public function incidentTypes(): AnonymousResourceCollection
    {
        $types = IncidentType::query()
            ->active()
            ->where('show_in_public_app', true)
            ->orderBy('sort_order')
            ->get();

        return CitizenIncidentTypeResource::collection($types);
    }

    public function store(CitizenReportRequest $request): CitizenReportResource
    {
        // Validate, create incident with channel='app', status=PENDING
        // Generate tracking_token, fire IncidentCreated event
        // Return CitizenReportResource with tracking_token
    }

    public function show(string $token): CitizenReportResource
    {
        $incident = Incident::where('tracking_token', $token)->firstOrFail();
        return new CitizenReportResource($incident);
    }
}
```

### Vue: Report App Entry Point
```typescript
// report-app/src/main.ts
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import './assets/app.css';

createApp(App).use(router).mount('#app');
```

### Vue: Citizen Status Mapping
```typescript
// report-app/src/types/index.ts
export const CITIZEN_STATUS_MAP: Record<string, string> = {
    PENDING: 'Received',
    TRIAGED: 'Verified',
    DISPATCHED: 'Dispatched',
    ACKNOWLEDGED: 'Dispatched',
    EN_ROUTE: 'Dispatched',
    ON_SCENE: 'Dispatched',
    RESOLVING: 'Dispatched',
    RESOLVED: 'Resolved',
};

export const STATUS_SEQUENCE = ['Received', 'Verified', 'Dispatched', 'Resolved'] as const;
```

### Vite Config for Report App
```typescript
// report-app/vite.config.ts
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
        },
    },
    server: {
        port: 5174,  // Different port from main app (5173)
        proxy: {
            '/api': {
                target: 'https://irms.test',
                changeOrigin: true,
                secure: false,
            },
        },
    },
    build: {
        outDir: 'dist',
    },
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `routes/api.php` auto-created | Must manually create and register in `bootstrap/app.php` | Laravel 11+ | Must explicitly add api route file |
| CORS package (fruitcake) | Built-in Laravel CORS middleware | Laravel 7+ | No extra package needed |
| RouteServiceProvider rate limiters | RateLimiter::for() in AppServiceProvider boot() | Laravel 8+ | Define in boot(), attach via throttle middleware |
| API tokens (Passport) | Stateless API (no auth for public) | N/A | Citizen endpoints need no auth at all |
| Separate server for SPA | Vite dev server proxy to Laravel API | Vite 4+ | Proxy `/api` calls to Laravel during development |

**Deprecated/outdated:**
- `fruitcake/laravel-cors` package: Merged into Laravel core, no longer needed
- `RouteServiceProvider` for rate limiters: Use `AppServiceProvider::boot()` in Laravel 11/12

## Open Questions

1. **Report App Hosting/Serving in Production**
   - What we know: In dev, Vite dev server on port 5174 with proxy to irms.test works. The report-app builds to static HTML/JS/CSS.
   - What's unclear: Production serving -- will it be a separate Herd site (e.g., `report.irms.test`), a subdomain, or served from a subfolder of the main app?
   - Recommendation: For now, configure as separate Herd site at `report.irms.test` (Herd supports multiple sites). Build outputs to `report-app/dist/`. Production deployment is a future concern.

2. **Barangay Dropdown Data Source**
   - What we know: 86 barangays exist in the `barangays` table. Citizens need a dropdown when GPS is denied.
   - What's unclear: Whether to add a barangays API endpoint or embed the list in the incident-types response.
   - Recommendation: Add a separate `GET /api/v1/citizen/barangays` endpoint returning `id` and `name` only (exclude boundary geometry). Cache aggressively (barangay list is static).

3. **Admin Toggle UI for `show_in_public_app`**
   - What we know: The flag needs to be on the incident_types table. Admin should toggle it.
   - What's unclear: Whether to update the existing admin incident-type edit page in this phase or defer.
   - Recommendation: Add the migration and model update in this phase. The admin UI toggle is a simple boolean field addition to the existing AdminIncidentTypeController -- include it.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 (PHP) |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --compact --filter=CitizenReport` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ADV-04 (v2) | Citizen report submission creates Incident | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php -x` | Wave 0 |
| ADV-04.1 | Tracking token generated on creation | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php -x` | Wave 0 |
| ADV-04.2 | Report lookup by tracking token | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php -x` | Wave 0 |
| ADV-04.3 | Public incident types filtered by show_in_public_app | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php -x` | Wave 0 |
| ADV-04.4 | Rate limiting on citizen endpoints | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php -x` | Wave 0 |
| ADV-04.5 | IncidentCreated event dispatched on citizen report | feature | `php artisan test --compact tests/Feature/CitizenReportTest.php -x` | Wave 0 |
| ADV-04.6 | GPS coordinates trigger barangay lookup | unit | `php artisan test --compact tests/Unit/CitizenReportServiceTest.php -x` | Wave 0 |
| ADV-04.7 | Status mapping (internal -> citizen-facing) | unit | `php artisan test --compact tests/Unit/CitizenStatusMappingTest.php -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=CitizenReport`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/CitizenReportTest.php` -- covers API endpoint tests for all citizen routes
- [ ] `tests/Unit/CitizenReportServiceTest.php` -- covers token generation, coordinate handling
- [ ] `tests/Unit/CitizenStatusMappingTest.php` -- covers status enum to citizen label mapping

## Sources

### Primary (HIGH confidence)
- Codebase inspection: `app/Models/Incident.php`, `app/Enums/IncidentStatus.php`, `app/Enums/IncidentChannel.php` -- verified existing model structure, enum values, fillable fields
- Codebase inspection: `app/Http/Controllers/IoTWebhookController.php`, `app/Http/Controllers/SmsWebhookController.php` -- verified pattern for unauthenticated incident creation with IncidentCreated event dispatch
- Codebase inspection: `bootstrap/app.php` -- verified current routing registration (no api routes yet, admin routes via `then:` callback)
- Codebase inspection: `docs/irms-report-app.html` -- verified all 6 screens (Home, Type Selection, Details, Submitted, My Reports, About), visual design, tokens, interactions
- Codebase inspection: `resources/css/app.css` -- verified design tokens (DM Sans, Space Mono, color variables for light/dark modes)
- [Laravel 12 Routing docs](https://laravel.com/docs/12.x/routing) -- API route registration in bootstrap/app.php
- [Laravel 12 Rate Limiting docs](https://laravel.com/docs/12.x/rate-limiting) -- RateLimiter::for() and throttle middleware

### Secondary (MEDIUM confidence)
- [Vue Router 4 Getting Started](https://router.vuejs.org/guide/) -- createWebHistory, lazy loading routes
- [MDN Geolocation API](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation_API) -- getCurrentPosition, HTTPS requirement, permission handling
- [Laravel 12 Eloquent API Resources](https://laravel.com/docs/12.x/eloquent-resources) -- JsonResource pattern for versioned APIs

### Tertiary (LOW confidence)
- None -- all findings verified against codebase or official docs

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all libraries already used in main app or are standard Vue ecosystem
- Architecture: HIGH -- patterns derived from existing codebase (webhook controllers, service layer, Inertia patterns adapted for API)
- Pitfalls: HIGH -- CORS, route registration, geolocation HTTPS verified against official docs
- Token generation: HIGH -- `random_bytes()` is PHP CSPRNG, collision math verified

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable patterns, no fast-moving dependencies)
