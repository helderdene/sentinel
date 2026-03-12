# Phase 2: Intake - Research

**Researched:** 2026-03-13
**Domain:** Incident intake, triage, geocoding, dispatch queue, webhook ingestion
**Confidence:** HIGH

## Summary

Phase 2 builds the core incident intake pipeline for IRMS: a triage form for dispatchers to create incidents, a priority auto-suggestion engine based on keyword matching, geocoding with barangay auto-assignment via PostGIS, a real-time dispatch queue, and two webhook endpoints (IoT sensor, SMS inbound). The foundation from Phase 1 provides all required models (Incident, IncidentType, IncidentTimeline, Barangay), enums (IncidentPriority, IncidentStatus), factories, and the role-based access control system.

The existing codebase establishes clear patterns: controllers use FormRequest validation (array-style rules), Vue pages use `useForm` + Wayfinder actions for submissions, and the UI is built with Reka UI Shadcn-style primitives styled via Tailwind CSS v4. The Incident model already auto-generates INC-YYYY-NNNNN numbers via the `booted()` hook, and PostGIS spatial queries (ST_Contains) are verified working against seeded barangay polygons. No services or contracts exist yet, so this phase will establish the service layer pattern with PHP interfaces and stub implementations.

**Primary recommendation:** Build an `IncidentController` for CRUD operations, a `PrioritySuggestionService` for keyword-based priority auto-suggestion, a `GeocodingService` interface with a stub Mapbox implementation, and webhook controllers for IoT/SMS ingestion. Use Inertia v2 `usePoll` for live queue updates. The combobox component for incident type selection must be added from Reka UI (not yet in the project's UI library).

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Single-page form at `/incidents/create` (dedicated page, not modal or slide-over)
- Accessible via "+ New Incident" button on the dispatch queue page and sidebar
- Sections in order: Channel + Caller Info, Incident Details (type + priority), Location, Notes
- Incident type selection: grouped combobox searchable by keyword, grouped by 8 categories
- All 5 channels available in dropdown: Phone, SMS, App (Walk-in/Web), IoT Sensor, Radio
- Uses `useForm` + Wayfinder actions (consistent with existing settings pattern from Phase 1)
- Inline suggestion with override: when incident type is selected, priority auto-fills as colored button group (P1-P4) with the suggested one pre-selected + confidence percentage
- Confidence calculated via keyword matching on notes: base confidence from incident type's `default_priority`, then scan notes for escalation/de-escalation keywords
- Supports both Filipino and English keywords (e.g., "sunog" = fire, "baha" = flood)
- Real-time debounced (500ms): priority and confidence update as dispatcher types notes
- One-click override: dispatcher clicks a different P1-P4 button to change
- Override logged to incident timeline
- Type-ahead autocomplete for location: dispatcher types address, sees suggestions from Mapbox geocoding (stubbed with Philippines filter)
- PostGIS ST_Contains auto-assigns barangay from coordinates; dispatcher can manually correct
- Barangay shown as read-only field that updates when coordinates are set
- Table layout with colored left border stripe per priority (red P1, orange P2, amber P3, green P4)
- Columns: Incident #, Type, Priority badge, Location/Barangay, Channel, Time Elapsed (age), Status
- Sorted by priority (P1 first) then FIFO within same priority
- Queue shows only PENDING incidents; separate "Incidents List" page shows all incidents with status filters
- Clicking a row navigates to `/incidents/{id}`
- Inertia v2 polling (10s interval) for live updates
- IoT webhook: HMAC-SHA256 validation, 5 hardcoded sensor type mappings, auto-creates PENDING incidents
- SMS webhook: keyword-to-incident-type map (Filipino + English), unmatched = General Emergency, raw SMS preserved, auto-reply stubbed
- Channel monitor panel: dashboard widget with 5 channel cards showing pending counts, refreshes via polling

### Claude's Discretion
- Exact keyword lists for priority escalation/de-escalation
- Geocoding autocomplete debounce timing and result count
- Table pagination strategy (if needed for large incident counts)
- Incident detail page layout and timeline rendering
- SMS keyword map structure (database table, config file, or enum)
- HMAC-SHA256 implementation details for IoT webhook
- Loading/empty states for queue and dashboard

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INTK-01 | Dispatcher can create incident with type (40+ types across 8 categories), priority (P1-P4), location, caller info, channel, and notes | IncidentController store action + StoreIncidentRequest FormRequest; grouped combobox from Reka UI ComboboxRoot/ComboboxGroup; existing Incident model handles all fields |
| INTK-02 | System auto-generates unique incident number (INC-YYYY-NNNNN) on creation | Already implemented in Incident model `booted()` hook -- no additional work needed |
| INTK-03 | System auto-suggests priority (P1-P4) based on incident type keywords with confidence score; dispatcher can override | PrioritySuggestionService with keyword matching; frontend debounced API call or computed from incident type's default_priority + notes keyword scan |
| INTK-04 | Location text is geocoded via Mapbox API with Philippines filter; coordinates auto-populated | GeocodingServiceInterface + StubMapboxGeocodingService; frontend autocomplete with debounced fetch to geocoding endpoint |
| INTK-05 | PostGIS ST_Contains query auto-assigns barangay from geocoded coordinates; dispatcher can manually correct | Magellan ST::contains() query builder or raw SQL (verified pattern from Phase 1 BarangaySpatialTest); BarangayLookupService |
| INTK-06 | Dispatch queue displays all triaged incidents ordered by priority (P1 first) then FIFO within same priority | IncidentController index with priority + created_at ordering; Inertia v2 usePoll(10000, { only: ['incidents'] }) for live updates |
| INTK-07 | IoT sensor webhook endpoint accepts alerts with HMAC-SHA256 validation; auto-creates incidents from threshold exceedances | IoT webhook controller with VerifyIotSignature middleware using hash_hmac + hash_equals; 5 sensor-to-incident-type mappings in config |
| INTK-08 | SMS inbound webhook parses incoming messages with keyword classifier for incident type suggestion; auto-reply on creation | SMS webhook controller; SmsParserService with Filipino/English keyword map; auto-reply via stubbed SmsService |
| INTK-09 | Channel monitor panel shows live feed from all 5 channels with pending count badges | Dashboard widget component using Inertia shared data or deferred props; Incident::query() grouped by channel with count |
</phase_requirements>

## Standard Stack

### Core (Already Installed)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | 12 | Backend framework | Project foundation |
| Inertia.js | v2 | SPA bridge | Existing stack; usePoll for live queue |
| Vue 3 | 3.x | Frontend framework | Existing stack |
| Reka UI | ^2.6.1 | Headless UI primitives | Existing stack; ComboboxRoot for grouped searchable select |
| Tailwind CSS | v4 | Styling | Existing stack |
| Pest | 4 | Testing | Existing stack |
| clickbar/laravel-magellan | installed | PostGIS model casts + query builder | Already used for Point/Polygon casts, ST::contains() |
| Wayfinder | v0 | TypeScript route generation | Existing stack for form submissions |

### New Components Needed
| Component | Source | Purpose | Notes |
|-----------|--------|---------|-------|
| Combobox UI component | Reka UI ComboboxRoot | Grouped searchable incident type selector | Must add to `resources/js/components/ui/combobox/` (Shadcn-vue style wrapper around Reka UI) |
| Badge UI component | Already exists | Priority badges (P1-P4 colored) | Already in `components/ui/badge/` |
| Textarea component | Already exists as raw `<textarea>` | Notes field | Use raw textarea with Tailwind classes (existing pattern from IncidentTypeForm) |

### No New Dependencies Required
The entire phase can be built with existing dependencies. No new npm or composer packages needed.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Contracts/
│   ├── GeocodingServiceInterface.php     # Forward geocoding interface
│   └── SmsServiceInterface.php           # SMS send/parse interface
├── Services/
│   ├── PrioritySuggestionService.php     # Keyword-based priority suggestion
│   ├── BarangayLookupService.php         # PostGIS ST_Contains wrapper
│   ├── StubMapboxGeocodingService.php    # Stubbed Mapbox geocoding
│   └── StubSemaphoreSmsService.php       # Stubbed Semaphore SMS
├── Http/
│   ├── Controllers/
│   │   ├── IncidentController.php        # CRUD for incidents (queue, create, show)
│   │   ├── IncidentQueueController.php   # Dispatch queue page (or combine into IncidentController)
│   │   ├── IoTWebhookController.php      # IoT sensor webhook endpoint
│   │   └── SmsWebhookController.php      # SMS inbound webhook endpoint
│   ├── Requests/
│   │   ├── StoreIncidentRequest.php      # Triage form validation
│   │   └── IoTWebhookRequest.php         # IoT payload validation (optional)
│   └── Middleware/
│       └── VerifyIoTSignature.php        # HMAC-SHA256 validation
├── Enums/
│   └── IncidentChannel.php               # Phone, SMS, App, IoT, Radio
resources/js/
├── pages/
│   ├── incidents/
│   │   ├── Queue.vue                     # Dispatch queue page (replaces ComingSoon)
│   │   ├── Create.vue                    # Triage form page
│   │   ├── Index.vue                     # All incidents list with filters
│   │   └── Show.vue                      # Incident detail with timeline
│   └── Dashboard.vue                     # Add channel monitor widget
├── components/
│   ├── ui/combobox/                      # New Reka UI combobox wrappers
│   ├── incidents/
│   │   ├── PrioritySelector.vue          # P1-P4 button group with confidence
│   │   ├── ChannelMonitor.vue            # 5-channel cards widget
│   │   └── IncidentTimeline.vue          # Timeline entries display
│   └── ...
├── composables/
│   └── usePrioritySuggestion.ts          # Debounced priority suggestion logic
└── types/
    └── incident.ts                       # TypeScript types for incidents
```

### Pattern 1: Service Layer with Interface Binding
**What:** PHP interfaces in `app/Contracts/` with stub implementations in `app/Services/` bound in `AppServiceProvider`.
**When to use:** All external integrations (geocoding, SMS) per INTGR-01 requirement.
**Example:**
```php
// app/Contracts/GeocodingServiceInterface.php
interface GeocodingServiceInterface
{
    /**
     * Forward geocode an address text to coordinates.
     *
     * @return array{lat: float, lng: float, display_name: string}[]
     */
    public function forward(string $query, string $country = 'PH'): array;
}

// app/Services/StubMapboxGeocodingService.php
class StubMapboxGeocodingService implements GeocodingServiceInterface
{
    public function forward(string $query, string $country = 'PH'): array
    {
        Log::info('StubMapboxGeocodingService::forward', compact('query', 'country'));

        // Return Butuan City area results for any query
        return [
            [
                'lat' => 8.9475 + (crc32($query) % 100) / 10000,
                'lng' => 125.5406 + (crc32($query) % 100) / 10000,
                'display_name' => $query . ', Butuan City, Agusan del Norte',
            ],
        ];
    }
}

// AppServiceProvider::register()
$this->app->bind(GeocodingServiceInterface::class, StubMapboxGeocodingService::class);
```

### Pattern 2: Inertia v2 Polling for Live Queue
**What:** Use `usePoll` composable for periodic queue refresh without WebSocket.
**When to use:** Dispatch queue page (10s interval), channel monitor widget.
**Example:**
```typescript
// Source: https://inertiajs.com/polling
import { usePoll } from '@inertiajs/vue3';

// Poll only the incidents prop every 10 seconds
usePoll(10000, { only: ['incidents', 'channelCounts'] });
```

### Pattern 3: Webhook Controller Without Web Middleware
**What:** Webhook endpoints bypass CSRF and session middleware. Register in `routes/web.php` or a separate `routes/api.php` with custom middleware.
**When to use:** IoT sensor webhook, SMS inbound webhook.
**Example:**
```php
// routes/web.php or bootstrap/app.php withRouting
Route::prefix('webhooks')->group(function () {
    Route::post('iot-sensor', IoTWebhookController::class)
        ->middleware('verify-iot-signature')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    Route::post('sms-inbound', SmsWebhookController::class)
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
});
```

### Pattern 4: useForm + Wayfinder Actions (Established Pattern)
**What:** Vue form uses `useForm` composable with Wayfinder-generated action functions for type-safe form submission.
**When to use:** Triage form submission (consistent with existing IncidentTypeForm pattern).
**Example:**
```typescript
import { useForm } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/IncidentController';

const form = useForm({
    incident_type_id: null as number | null,
    priority: '' as string,
    channel: '' as string,
    location_text: '' as string,
    latitude: null as number | null,
    longitude: null as number | null,
    barangay_id: null as number | null,
    caller_name: '' as string,
    caller_contact: '' as string,
    notes: '' as string,
});

function submit(): void {
    form.submit(store());
}
```

### Anti-Patterns to Avoid
- **Raw SQL for spatial queries in controllers:** Use BarangayLookupService to encapsulate PostGIS queries. The controller should call `$service->findBarangay(lat, lng)`, not write raw SQL.
- **Inline validation in webhook controllers:** Use FormRequest or dedicated validation logic in the middleware, not inline `$request->validate()`.
- **Polling all props:** Always use `only: ['incidents']` with `usePoll` to avoid refreshing auth/navigation data on every poll.
- **HMAC secret in code:** Store IoT webhook secret in `.env` as `IOT_WEBHOOK_SECRET`, access via `config('services.iot.webhook_secret')`.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Searchable grouped dropdown | Custom dropdown with filter logic | Reka UI ComboboxRoot + ComboboxGroup + ComboboxInput | Keyboard navigation, ARIA labels, focus management, virtualization for 48+ items |
| HMAC-SHA256 comparison | String comparison (`===`) | `hash_equals()` | Prevents timing attacks; standard PHP function |
| Debounced API calls | Manual setTimeout/clearTimeout | Vue `watchDebounced` from VueUse or custom composable | Edge cases with rapid input, cleanup on unmount |
| Point-in-polygon lookup | Raw `DB::select('SELECT ST_Contains...')` | Magellan `ST::contains()` or dedicated BarangayLookupService | Parameter binding, type safety, testability |
| Priority color mapping | Repeated ternary/switch in templates | IncidentPriority enum `color()` method (already exists) | Single source of truth for P1=red, P2=orange, P3=amber, P4=green |
| Incident number generation | Manual sequence tracking | Existing `Incident::booted()` hook | Already handles INC-YYYY-NNNNN format with sequence gaps |

**Key insight:** The Incident model, IncidentType seeder (48 types), IncidentPriority enum, and PostGIS spatial queries are all already built and tested. This phase should use them as-is, not rebuild or refactor.

## Common Pitfalls

### Pitfall 1: Combobox Not Filtering Correctly with Grouped Items
**What goes wrong:** When grouping 48+ incident types by category in a Reka UI Combobox, the search filter may not work across groups, or empty groups remain visible after filtering.
**Why it happens:** ComboboxGroup doesn't auto-hide when all its items are filtered out.
**How to avoid:** Use `v-if` or `v-show` on ComboboxGroup to hide it when no items in that group match the search. Compute filtered groups reactively.
**Warning signs:** Empty group headers visible in dropdown while searching.

### Pitfall 2: CSRF Token Blocking Webhook Endpoints
**What goes wrong:** IoT and SMS webhook POST requests fail with 419 (CSRF token mismatch).
**Why it happens:** Laravel's web middleware includes CSRF verification by default. Webhooks are external, unauthenticated requests.
**How to avoid:** Exclude webhook routes from CSRF verification using `->withoutMiddleware()` on the route, or list paths in the VerifyCsrfToken middleware's `$except` array. In Laravel 12, use `bootstrap/app.php` to configure CSRF exceptions.
**Warning signs:** 419 responses on webhook test calls.

### Pitfall 3: PostGIS Geography vs Geometry Type Mismatch
**What goes wrong:** ST_Contains query returns no results even when the point is clearly inside a barangay polygon.
**Why it happens:** The Incident `coordinates` column is `geography(point, 4326)` and Barangay `boundary` is `geography(polygon, 4326)`. PostGIS's `ST_Contains()` works on geometry type, not geography directly. Need to cast with `::geometry`.
**How to avoid:** Use the casting pattern already verified in Phase 1: `ST_Contains(boundary::geometry, ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geometry)`. Alternatively, Magellan may handle this automatically -- verify in tests.
**Warning signs:** Empty result set when querying barangay for known coordinates.

### Pitfall 4: Polling Causing Stale Form State
**What goes wrong:** On the dispatch queue page, `usePoll` refreshes page props, which can cause issues if a user has navigated to the create form and back, or if the queue component's local state is reset.
**Why it happens:** Inertia polling reloads server-provided props, potentially clobbering client-side state.
**How to avoid:** Use `only: ['incidents', 'channelCounts']` in usePoll to limit which props are refreshed. Keep form state in a separate page component (the create form is a different Inertia page).
**Warning signs:** Queue table flickering or scroll position resetting on poll.

### Pitfall 5: Webhook Replay Attacks
**What goes wrong:** An attacker captures a valid IoT webhook payload and replays it repeatedly, creating duplicate incidents.
**Why it happens:** HMAC signature validation alone doesn't prevent replay attacks.
**How to avoid:** Include a timestamp in the signed payload and reject requests older than 5 minutes. The HMAC middleware should check `abs(time() - $timestamp) > 300`.
**Warning signs:** Duplicate incidents with identical sensor data.

### Pitfall 6: Priority Suggestion Confidence Calculation Race Condition
**What goes wrong:** Rapid typing in the notes field fires multiple debounced suggestion requests; responses arrive out of order, showing stale confidence scores.
**Why it happens:** Network latency varies; later request may complete before earlier one.
**How to avoid:** Use an AbortController or request counter pattern. The composable should track the latest request ID and discard responses from earlier requests. Or compute priority suggestion entirely on the frontend (no API call needed since keyword lists are small and static).
**Warning signs:** Confidence score jumps erratically while typing.

### Pitfall 7: Missing IncidentChannel Enum Consistency
**What goes wrong:** Channel values stored as free-text strings become inconsistent (e.g., "phone" vs "Phone" vs "PHONE").
**Why it happens:** No enum enforcement on the `channel` column.
**How to avoid:** Create an `IncidentChannel` PHP enum (backed string) with values matching existing factory values. Validate against enum in FormRequest. Cast on the model.
**Warning signs:** Channel monitor counts don't match actual incidents because of case/value mismatches.

## Code Examples

### Barangay Lookup via PostGIS ST_Contains
```php
// Source: Verified pattern from tests/Feature/Foundation/BarangaySpatialTest.php
// Using raw SQL (proven working in Phase 1)
use Illuminate\Support\Facades\DB;

$barangay = DB::select('
    SELECT id, name FROM barangays
    WHERE ST_Contains(
        boundary::geometry,
        ST_SetSRID(ST_MakePoint(?, ?), 4326)::geometry
    )
    LIMIT 1
', [$longitude, $latitude]);

// Using Magellan query builder (preferred, needs verification)
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Clickbar\Magellan\Data\Geometries\Point;

$point = Point::makeGeodetic($latitude, $longitude);
$barangay = Barangay::where(ST::contains('boundary', $point), true)->first();
```

### HMAC-SHA256 Webhook Signature Verification
```php
// app/Http/Middleware/VerifyIoTSignature.php
public function handle(Request $request, Closure $next): Response
{
    $signature = $request->header('X-Signature-256');
    $timestamp = $request->header('X-Timestamp');
    $secret = config('services.iot.webhook_secret');

    if (!$signature || !$timestamp || !$secret) {
        abort(401, 'Missing signature headers');
    }

    // Reject stale requests (> 5 minutes)
    if (abs(time() - (int) $timestamp) > 300) {
        abort(401, 'Request timestamp expired');
    }

    $payload = $timestamp . '.' . $request->getContent();
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    if (!hash_equals($expected, $signature)) {
        abort(401, 'Invalid signature');
    }

    return $next($request);
}
```

### Inertia v2 Polling for Dispatch Queue
```typescript
// Source: https://inertiajs.com/polling
import { usePoll } from '@inertiajs/vue3';

// In Queue.vue <script setup>
usePoll(10000, { only: ['incidents', 'channelCounts'] });
```

### Priority Suggestion (Frontend-Only Approach -- Recommended)
```typescript
// composables/usePrioritySuggestion.ts
import { computed, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core'; // or manual debounce

interface PrioritySuggestion {
    priority: string; // P1-P4
    confidence: number; // 0-100
}

const ESCALATION_KEYWORDS: Record<string, number> = {
    trapped: 10, unconscious: 15, multiple: 10, children: 10,
    fire: 5, sunog: 5, baha: 5, lindol: 5,
    critical: 15, dying: 20, severe: 10, mass: 15,
};

const DEESCALATION_KEYWORDS: Record<string, number> = {
    minor: -15, small: -10, contained: -10, stable: -10,
    false: -20, drill: -25, test: -25, cancel: -30,
};

export function usePrioritySuggestion(
    incidentTypeDefaultPriority: () => string | null,
    notes: () => string,
) {
    // Base confidence from incident type selection
    // Adjust based on keyword matches in notes
    // Return reactive { priority, confidence }
}
```

### IoT Sensor Type Mappings
```php
// config/services.php
'iot' => [
    'webhook_secret' => env('IOT_WEBHOOK_SECRET'),
    'sensor_mappings' => [
        'flood_gauge' => ['incident_type_code' => 'NAT-002', 'priority' => 'P2'],   // Flood
        'fire_alarm' => ['incident_type_code' => 'FIR-001', 'priority' => 'P1'],    // Structure Fire
        'weather' => ['incident_type_code' => 'NAT-004', 'priority' => 'P2'],       // Typhoon (Severe Weather)
        'seismic' => ['incident_type_code' => 'NAT-001', 'priority' => 'P1'],       // Earthquake
        'cctv_analytics' => ['incident_type_code' => 'PUB-001', 'priority' => 'P3'], // General Emergency
    ],
],
```

### IncidentChannel Enum
```php
// app/Enums/IncidentChannel.php
enum IncidentChannel: string
{
    case Phone = 'phone';
    case Sms = 'sms';
    case App = 'app';
    case IoT = 'iot';
    case Radio = 'radio';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Sms => 'SMS',
            self::App => 'App (Walk-in/Web)',
            self::IoT => 'IoT Sensor',
            self::Radio => 'Radio',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Sms => 'MessageSquare',
            self::App => 'Globe',
            self::IoT => 'Cpu',
            self::Radio => 'Radio',
        };
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `setInterval` + `router.reload` for polling | `usePoll(ms, { only: [...] })` composable | Inertia v2 (2025) | Auto cleanup on unmount, background tab throttling, selective prop reload |
| Raw `DB::select('ST_Contains...')` | Magellan `ST::contains()` query builder | laravel-magellan v2 (2025) | Type-safe, composable with Eloquent, no raw SQL needed |
| Manual CSRF exclusion in middleware class | `->withoutMiddleware()` on route definition | Laravel 11+ (2024) | More explicit, per-route CSRF exclusion without modifying middleware class |

**Deprecated/outdated:**
- Magellan v1 `stWhere()` prefixed methods were removed in v2. Use standard `where()` with MagellanExpressions.
- Inertia v1 polling required manual `setInterval` + `router.reload` pattern. v2 provides `usePoll` composable.

## Discretion Recommendations

### Priority Escalation/De-escalation Keywords
**Recommendation:** Start with a concise keyword list stored as a PHP config array (not database). Include both Filipino and English terms. The frontend composable can receive these via Inertia props on the create page.

**Escalation keywords (increase confidence / escalate priority):**
- English: trapped, unconscious, multiple, children, critical, dying, severe, mass, unresponsive, collapse, explosion
- Filipino: nakulong (trapped), walang malay (unconscious), marami (multiple), bata (children), malala (severe), nasusunog (burning), bumaha (flooded)

**De-escalation keywords (decrease confidence / de-escalate priority):**
- English: minor, small, contained, stable, false, drill, test, cancel, resolved, no injury
- Filipino: maliit (small), kontrolado (contained), kaunti (few)

### Geocoding Autocomplete Debounce
**Recommendation:** 300ms debounce, max 5 results. The stub service should return deterministic results based on the query string to enable consistent testing.

### Table Pagination Strategy
**Recommendation:** No pagination for the dispatch queue (PENDING only -- rarely exceeds 50). For the "All Incidents" index page, use Laravel's cursor pagination with 25 per page.

### Incident Detail Page Layout
**Recommendation:** Two-column layout. Left: incident details (type, priority, location, caller info, notes). Right: timeline entries in reverse chronological order. Keep it simple for Phase 2; Phase 4 will add the map and unit assignment.

### SMS Keyword Map Structure
**Recommendation:** PHP config file (`config/sms.php`) with keyword-to-incident-type-code mappings. Simple and sufficient for the stubbed integration. Can be migrated to database in Phase 6 if needed.

### HMAC Implementation
**Recommendation:** Custom middleware `VerifyIoTSignature` (not a FormRequest). Include timestamp in signed payload for replay protection. Store secret in `.env`. See code example above.

### Loading/Empty States
**Recommendation:** Use Skeleton component (already available in `ui/skeleton/`) for queue table rows during initial load. Show "No pending incidents" empty state with an illustration when queue is empty. For the channel monitor, show "0" badges with muted styling when no pending incidents exist.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `tests/Pest.php` + `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=testName` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INTK-01 | Dispatcher can create incident with all fields | Feature | `php artisan test --compact tests/Feature/Intake/CreateIncidentTest.php -x` | Wave 0 |
| INTK-02 | Auto-generates INC-YYYY-NNNNN | Feature | `php artisan test --compact tests/Feature/Foundation/IncidentModelTest.php --filter=auto-generates -x` | Already exists |
| INTK-03 | Priority auto-suggestion with confidence | Unit | `php artisan test --compact tests/Unit/PrioritySuggestionServiceTest.php -x` | Wave 0 |
| INTK-04 | Geocoding stub returns results | Unit + Feature | `php artisan test --compact tests/Unit/GeocodingServiceTest.php -x` | Wave 0 |
| INTK-05 | PostGIS ST_Contains assigns barangay | Feature | `php artisan test --compact tests/Feature/Intake/BarangayAssignmentTest.php -x` | Wave 0 |
| INTK-06 | Queue shows PENDING incidents ordered by priority then FIFO | Feature | `php artisan test --compact tests/Feature/Intake/DispatchQueueTest.php -x` | Wave 0 |
| INTK-07 | IoT webhook creates incident with HMAC validation | Feature | `php artisan test --compact tests/Feature/Intake/IoTWebhookTest.php -x` | Wave 0 |
| INTK-08 | SMS webhook parses keywords and creates incident | Feature | `php artisan test --compact tests/Feature/Intake/SmsWebhookTest.php -x` | Wave 0 |
| INTK-09 | Channel monitor shows pending counts per channel | Feature | `php artisan test --compact tests/Feature/Intake/ChannelMonitorTest.php -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact tests/Feature/Intake/ tests/Unit/ -x`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Intake/CreateIncidentTest.php` -- covers INTK-01, INTK-02
- [ ] `tests/Unit/PrioritySuggestionServiceTest.php` -- covers INTK-03
- [ ] `tests/Unit/GeocodingServiceTest.php` -- covers INTK-04
- [ ] `tests/Feature/Intake/BarangayAssignmentTest.php` -- covers INTK-05
- [ ] `tests/Feature/Intake/DispatchQueueTest.php` -- covers INTK-06
- [ ] `tests/Feature/Intake/IoTWebhookTest.php` -- covers INTK-07
- [ ] `tests/Feature/Intake/SmsWebhookTest.php` -- covers INTK-08
- [ ] `tests/Feature/Intake/ChannelMonitorTest.php` -- covers INTK-09

## Open Questions

1. **Magellan v2 ST::contains() with geography columns**
   - What we know: Phase 1 uses raw SQL with `::geometry` casts for ST_Contains. Magellan v2 provides `ST::contains()` query builder.
   - What's unclear: Whether Magellan v2's `ST::contains()` automatically handles geography-to-geometry casting for the barangay `boundary` column.
   - Recommendation: Test Magellan approach first; fall back to proven raw SQL pattern if it doesn't handle geography casting. Implement in BarangayLookupService so the approach is encapsulated.

2. **VueUse availability for debounce**
   - What we know: The project uses Vue 3 + TypeScript. VueUse provides `useDebounceFn` and `watchDebounced`.
   - What's unclear: Whether VueUse is already installed as a dependency.
   - Recommendation: Check `package.json`. If not installed, use a simple manual debounce utility (standard JS pattern, ~10 lines). Do not add new dependencies without approval per project guidelines.

3. **Webhook route registration approach**
   - What we know: Admin routes use `withRouting(then:)` in `bootstrap/app.php`. Web routes are in `routes/web.php`.
   - What's unclear: Whether webhooks should be added as a separate routing file (like `routes/webhooks.php`) or within `routes/web.php` with CSRF exclusion.
   - Recommendation: Add webhook routes in `routes/web.php` within the dispatcher middleware group with CSRF exclusion. Keep it simple. A separate file could be introduced if webhook routes grow.

## Sources

### Primary (HIGH confidence)
- Existing codebase: `app/Models/Incident.php`, `app/Models/IncidentType.php`, `app/Models/Barangay.php` -- verified model structure, relationships, casts
- Existing codebase: `tests/Feature/Foundation/BarangaySpatialTest.php` -- verified PostGIS ST_Contains pattern
- Existing codebase: `app/Http/Controllers/Admin/AdminIncidentTypeController.php` -- verified controller + FormRequest pattern
- Existing codebase: `resources/js/pages/admin/IncidentTypeForm.vue` -- verified useForm + Wayfinder pattern
- Existing codebase: `database/seeders/IncidentTypeSeeder.php` -- verified 48 incident types across 8 categories
- [Reka UI Combobox docs](https://reka-ui.com/docs/components/combobox) -- ComboboxRoot, ComboboxGroup, ComboboxInput API

### Secondary (MEDIUM confidence)
- [Inertia.js v2 Polling docs](https://inertiajs.com/polling) -- usePoll composable with `only` option
- [clickbar/laravel-magellan GitHub](https://github.com/clickbar/laravel-magellan) -- ST::contains() query builder API
- [Laravel HMAC webhook patterns](https://christalks.dev/post/secure-your-webhooks-in-laravel-preventing-data-spoofing-fe25a70e) -- hash_hmac + hash_equals pattern

### Tertiary (LOW confidence)
- Magellan v2 ST::contains() with geography column casting -- needs hands-on verification (raw SQL fallback available)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- entire stack already installed and verified in Phase 1
- Architecture: HIGH -- patterns (FormRequest, useForm+Wayfinder, PostGIS queries) are all established in existing code
- Pitfalls: HIGH -- PostGIS geography/geometry casting verified, CSRF webhook issue is well-documented
- Service layer pattern: MEDIUM -- first services in the project, pattern is standard Laravel but needs consistency check
- Magellan query builder for ST_Contains: MEDIUM -- raw SQL verified, Magellan v2 API needs runtime verification

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable -- all libraries already installed, patterns established)
