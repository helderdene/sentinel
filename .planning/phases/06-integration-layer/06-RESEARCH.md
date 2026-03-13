# Phase 6: Integration Layer - Research

**Researched:** 2026-03-13
**Domain:** PHP service container architecture, external API integration stubs (Mapbox, Semaphore SMS, PAGASA, HL7 FHIR R4, NDRRMC, BFP, PNP)
**Confidence:** HIGH

## Summary

Phase 6 is a **purely backend** phase focused on establishing a uniform interface-driven integration architecture across all external connectors. The project already has 3 interfaces (`GeocodingServiceInterface`, `SmsServiceInterface`, `ProximityServiceInterface`) and 2 stub implementations (`StubMapboxGeocodingService`, `StubSemaphoreSmsService`) that establish the pattern. This phase extends that pattern to 5 new connectors (Directions, PAGASA Weather, Hospital EHR, NDRRMC SitRep, BFP, PNP) and retrofits the existing `SmsParserService` into the formal interface pattern.

The critical success criterion is zero-business-logic-change swappability: every integration is behind a PHP interface bound in `AppServiceProvider::register()`, and switching from stub to real implementation requires only changing the binding. The stubs must return Philippine-specific, Butuan City-realistic data per the user's decision.

**Primary recommendation:** Follow the established `Contracts/ + Services/ + AppServiceProvider::register()` pattern exactly. Create one interface and one stub per connector. No new database tables, no new dependencies -- just PHP interfaces, stub classes, config files, and Pest tests.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- All stubs return **Philippine-specific data**: real Butuan barangay names, PAGASA-style advisory text ("Rainfall Warning Level 2 -- Butuan City"), hospital names from `config/hospitals.php`, realistic government report formats
- Stubs should feel convincing for demos and catch format issues early -- not generic "Test Hospital" placeholders
- Laravel `Log::info()` only for all stub calls, matching the existing `StubMapboxGeocodingService` pattern
- No new database tables for integration logging -- keep it simple and grep-able
- Unify all services under the interface pattern: existing `SmsParserService` (currently no contract) gets a formal interface
- Review and consolidate existing `GeocodingServiceInterface`, `SmsServiceInterface`, `ProximityServiceInterface` bindings
- All bindings consolidated in `AppServiceProvider` with one consistent architecture

### Claude's Discretion
- Error simulation strategy per connector (always succeed vs configurable failures)
- PAGASA weather data shape and where advisories surface in the system
- Government connector stub depth (how closely to model real NDRRMC XML, BFP sync, PNP e-Blotter schemas)
- Hospital EHR FHIR resource scope (which HL7 FHIR R4 resources to model)

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INTGR-01 | All external integrations behind PHP interfaces bound in service container; stub implementations log calls; real implementations plug in without business logic changes | Core architecture pattern already established in codebase -- extend to all 7+ connectors |
| INTGR-02 | Stubbed Mapbox Geocoding connector for forward geocoding with Philippines country filter | Already implemented as `StubMapboxGeocodingService` -- verify it meets INTGR-02 fully, retrofit if needed |
| INTGR-03 | Stubbed Mapbox Directions connector for road-network ETA calculation | New interface + stub; response shape modeled on Mapbox Directions API v5 (distance meters, duration seconds, geometry) |
| INTGR-04 | Stubbed Semaphore SMS connector for inbound parsing and outbound acknowledgement/status messages | Already partially implemented; `SmsParserService` needs formal interface; SMS status update messages are new |
| INTGR-05 | Stubbed PAGASA Weather connector for rainfall, wind, and flood advisory overlay data | New interface + stub; data shape modeled on PAGASA 3-level color-coded advisory system (Yellow/Orange/Red) |
| INTGR-06 | Stubbed Hospital EHR connector (HL7 FHIR R4) for patient pre-notification on transport outcome | New interface + stub; minimal FHIR R4 Encounter + Patient resources for EMS pre-notification |
| INTGR-07 | Stubbed NDRRMC connector for SitRep XML submission on P1 closure | New interface + stub; models SitRep report fields mapped from IRMS incident data |
| INTGR-08 | Stubbed BFP connector for bidirectional fire incident sync | New interface + stub; inbound (BFP to IRMS) and outbound (IRMS to BFP) fire incident payloads |
| INTGR-09 | Stubbed PNP e-Blotter connector for criminal incident auto-blotter entry | New interface + stub; blotter entry payload with 5W1H framework fields |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | v12 | Service container, interface binding, config | Already installed; `$this->app->bind()` in AppServiceProvider |
| Pest | v4 | Unit + feature tests for all interfaces and stubs | Already installed; project testing standard |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Illuminate\Support\Facades\Log | (framework) | All stub logging | Every stub call logs via `Log::info()` |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Log::info() | Database logging table | User explicitly decided against DB logging -- keep it simple and grep-able |
| Separate service providers | AppServiceProvider | User decided all bindings consolidated in AppServiceProvider |
| Spatie/Laravel Data | Plain PHP arrays | Stubs return arrays matching API response shapes; no need for extra dependency |

**Installation:**
```bash
# No new packages needed -- this phase uses only existing dependencies
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Contracts/
│   ├── GeocodingServiceInterface.php      (existing)
│   ├── SmsServiceInterface.php            (existing)
│   ├── ProximityServiceInterface.php      (existing)
│   ├── SmsParserServiceInterface.php      (NEW - formal contract for SmsParserService)
│   ├── DirectionsServiceInterface.php     (NEW)
│   ├── WeatherServiceInterface.php        (NEW)
│   ├── HospitalEhrServiceInterface.php    (NEW)
│   ├── NdrrmcReportServiceInterface.php   (NEW)
│   ├── BfpSyncServiceInterface.php        (NEW)
│   └── PnpBlotterServiceInterface.php     (NEW)
├── Services/
│   ├── StubMapboxGeocodingService.php     (existing)
│   ├── StubSemaphoreSmsService.php        (existing)
│   ├── SmsParserService.php               (existing - will implement SmsParserServiceInterface)
│   ├── StubMapboxDirectionsService.php    (NEW)
│   ├── StubPagasaWeatherService.php       (NEW)
│   ├── StubHospitalEhrService.php         (NEW)
│   ├── StubNdrrmcReportService.php        (NEW)
│   ├── StubBfpSyncService.php             (NEW)
│   └── StubPnpBlotterService.php          (NEW)
├── Providers/
│   └── AppServiceProvider.php             (extend register() with new bindings)
config/
├── hospitals.php                          (existing - used by EHR stub)
├── sms.php                                (existing)
├── integrations.php                       (NEW - centralized config for all integration endpoints/keys)
tests/
├── Unit/
│   ├── GeocodingServiceTest.php           (existing)
│   ├── DirectionsServiceTest.php          (NEW)
│   ├── WeatherServiceTest.php             (NEW)
│   ├── HospitalEhrServiceTest.php         (NEW)
│   ├── NdrrmcReportServiceTest.php        (NEW)
│   ├── BfpSyncServiceTest.php             (NEW)
│   ├── PnpBlotterServiceTest.php          (NEW)
│   └── SmsParserServiceInterfaceTest.php  (NEW)
```

### Pattern 1: Interface + Stub + Binding (Established Project Pattern)
**What:** Every external integration follows a 3-part structure
**When to use:** Every connector in this phase

1. **Interface** in `app/Contracts/` defines the contract:
```php
// Source: Existing pattern from app/Contracts/GeocodingServiceInterface.php
namespace App\Contracts;

interface DirectionsServiceInterface
{
    /**
     * Calculate road-network ETA between two coordinate pairs.
     *
     * @return array{distance_meters: float, duration_seconds: float, geometry: string}
     */
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): array;
}
```

2. **Stub** in `app/Services/` implements with logging + deterministic data:
```php
// Source: Existing pattern from app/Services/StubMapboxGeocodingService.php
namespace App\Services;

use App\Contracts\DirectionsServiceInterface;
use Illuminate\Support\Facades\Log;

class StubMapboxDirectionsService implements DirectionsServiceInterface
{
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        Log::info('StubMapboxDirectionsService::route', compact(
            'originLat', 'originLng', 'destLat', 'destLng'
        ));

        // Deterministic ETA based on Haversine distance at 30km/h urban speed
        $distanceKm = $this->haversine($originLat, $originLng, $destLat, $destLng);
        $distanceMeters = $distanceKm * 1000;
        $durationSeconds = ($distanceKm / 30) * 3600;

        return [
            'distance_meters' => round($distanceMeters, 1),
            'duration_seconds' => round($durationSeconds),
            'geometry' => '', // Empty polyline for stub
        ];
    }
}
```

3. **Binding** in `AppServiceProvider::register()`:
```php
// Source: Existing pattern from app/Providers/AppServiceProvider.php
$this->app->bind(DirectionsServiceInterface::class, StubMapboxDirectionsService::class);
```

### Pattern 2: Config-Driven Stub Data
**What:** Stub data sourced from config files rather than hardcoded
**When to use:** When realistic data pools are needed (hospitals, barangays, weather stations)

```php
// Example: EHR stub uses config/hospitals.php for realistic hospital names
$hospitals = config('hospitals');
$hospital = collect($hospitals)->firstWhere('id', $hospitalId);
```

### Pattern 3: Event-Driven Integration Hooks
**What:** Existing broadcast events serve as triggers for integration connectors
**When to use:** Government connectors triggered by incident lifecycle events

Key hooks already in the codebase:
- `IncidentStatusChanged` -- trigger NDRRMC SitRep on P1 closure, PNP blotter on criminal incident resolution
- `MutualAidRequested` -- potential trigger for BFP/PNP notifications
- `GenerateIncidentReport` job in `ResponderController::resolve()` -- hook for NDRRMC SitRep

### Anti-Patterns to Avoid
- **Coupling stubs to database state:** Stubs must be pure -- no database queries, no model lookups. Use config arrays and deterministic computation only.
- **Adding HTTP client dependencies to stubs:** Stubs never make real HTTP calls. The real implementations (future) will use Laravel's HTTP client.
- **Putting business logic in stubs:** Stubs are data-returning shells. Business logic (e.g., "should we send a SitRep?") belongs in the caller (controller/job/listener).
- **Creating new event listeners in this phase:** This phase creates the connectors. Wiring them to events (listeners) is a separate concern that can be done in this phase but should be clearly separated from the interface+stub work.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Haversine distance | Custom math lib | Simple PHP function in stub | Only needed for deterministic stub ETA; real Mapbox API handles routing |
| XML generation | Custom XML string builder | PHP's `SimpleXMLElement` or `DOMDocument` | Built into PHP; NDRRMC SitRep stub can generate well-formed XML |
| FHIR JSON | Custom JSON builder | Plain PHP arrays shaped to FHIR R4 spec | Stubs return arrays; no need for a FHIR library when stubbing |
| Service container patterns | Custom factory | Laravel `$this->app->bind()` | Framework provides exactly what's needed |

**Key insight:** This phase is about architecture, not implementation. Every stub returns canned/deterministic data. The complexity is in getting the interfaces right so real implementations drop in cleanly.

## Common Pitfalls

### Pitfall 1: Interface Methods Too Specific to One Provider
**What goes wrong:** Interface method signatures lock in Mapbox-specific or Semaphore-specific parameters, making it impossible to swap providers without changing the interface.
**Why it happens:** Designing the interface while thinking about the stub implementation.
**How to avoid:** Design interfaces around the business need, not the API provider. `forward(string $query, string $country)` is good (business-level). `mapboxGeocode(string $query, string $proximity, string $types)` is bad (provider-level).
**Warning signs:** Interface method names containing vendor names; parameters that only make sense for one provider.

### Pitfall 2: Stub Data That Doesn't Match Real API Shapes
**What goes wrong:** Stub returns `['lat' => ..., 'lng' => ...]` but real Mapbox returns `['latitude' => ..., 'longitude' => ...]`. When you swap to real implementation, consumers break.
**Why it happens:** Stubs designed in isolation without checking the real API response format.
**How to avoid:** Design the interface return type to be a business-level DTO shape, not a raw API shape. The interface is the contract; both stub and real implementation must conform to it.
**Warning signs:** Return type annotations that mirror a specific vendor's API response.

### Pitfall 3: Forgetting to Log All Parameters
**What goes wrong:** Stub logs `Log::info('send')` without the actual parameters, making it impossible to verify in tests that the right data was passed.
**Why it happens:** Logging is treated as an afterthought.
**How to avoid:** Every `Log::info()` call includes the method name as message and all parameters as context array. Tests can then use `Log::spy()` + `Log::shouldHaveReceived()` to verify.
**Warning signs:** Existing test `SmsWebhookTest.php` already uses this Log spy pattern.

### Pitfall 4: Breaking Existing Tests When Retrofitting SmsParserService
**What goes wrong:** Adding interface to `SmsParserService` changes its resolution, breaking `SmsWebhookController` which currently type-hints the concrete class.
**Why it happens:** Controller constructor uses `SmsParserService` (concrete) not an interface.
**How to avoid:** Create `SmsParserServiceInterface`, make `SmsParserService` implement it, bind in AppServiceProvider, update `SmsWebhookController` constructor to type-hint the interface. Run existing SMS webhook tests to confirm nothing breaks.
**Warning signs:** Tests in `tests/Feature/Intake/SmsWebhookTest.php` must still pass after retrofit.

### Pitfall 5: Overly Complex Stub Implementations
**What goes wrong:** Stubs grow to 200+ lines trying to model every edge case of the real API.
**Why it happens:** Enthusiasm for realism exceeds the stub's purpose.
**How to avoid:** Stubs should be 30-80 lines. Return deterministic, realistic data. Log parameters. That's it. Error simulation is optional per user discretion.
**Warning signs:** Stub class longer than the interface it implements by more than 5x.

## Code Examples

Verified patterns from the existing codebase:

### Existing Interface Pattern (Reference)
```php
// Source: app/Contracts/GeocodingServiceInterface.php
interface GeocodingServiceInterface
{
    /**
     * @return array{lat: float, lng: float, display_name: string}[]
     */
    public function forward(string $query, string $country = 'PH'): array;
}
```

### Existing Stub Pattern (Reference)
```php
// Source: app/Services/StubMapboxGeocodingService.php
class StubMapboxGeocodingService implements GeocodingServiceInterface
{
    public function forward(string $query, string $country = 'PH'): array
    {
        Log::info('StubMapboxGeocodingService::forward', compact('query', 'country'));
        // ... deterministic Butuan-area results based on query hash
    }
}
```

### Existing Binding Pattern (Reference)
```php
// Source: app/Providers/AppServiceProvider.php::register()
$this->app->bind(GeocodingServiceInterface::class, StubMapboxGeocodingService::class);
$this->app->bind(ProximityServiceInterface::class, ProximityRankingService::class);
$this->app->bind(SmsServiceInterface::class, StubSemaphoreSmsService::class);
```

### Existing Test Pattern (Reference)
```php
// Source: tests/Unit/GeocodingServiceTest.php
it('implements geocoding service interface', function () {
    $service = new StubMapboxGeocodingService;
    expect($service)->toBeInstanceOf(GeocodingServiceInterface::class);
});

it('returns results with required shape keys', function () {
    $service = new StubMapboxGeocodingService;
    $results = $service->forward('Butuan City Hall');
    foreach ($results as $result) {
        expect($result)->toHaveKeys(['lat', 'lng', 'display_name']);
    }
});
```

### Log Spy Test Pattern (Reference)
```php
// Source: tests/Feature/Intake/SmsWebhookTest.php
it('sends auto-reply via SMS service', function () {
    Log::spy();
    IncidentType::factory()->create(['code' => 'PUB-001']);

    $this->postJson('/webhooks/sms-inbound', [
        'sender' => '09251234567',
        'message' => 'emergency',
    ])->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) {
            return $message === 'StubSemaphoreSmsService::send'
                && $context['to'] === '09251234567';
        })
        ->once();
});
```

## Connector-Specific Research

### INTGR-03: Mapbox Directions (Stub)
**Real API:** `GET https://api.mapbox.com/directions/v5/mapbox/driving-traffic/{lng1},{lat1};{lng2},{lat2}`
**Response shape (relevant fields):**
- `routes[0].distance` (meters)
- `routes[0].duration` (seconds)
- `routes[0].duration_typical` (seconds, driving-traffic profile)
- `routes[0].geometry` (GeoJSON LineString or encoded polyline)

**Stub approach:** Calculate straight-line Haversine distance, apply 30km/h urban speed factor (matching existing `nearbyUnits` ETA logic in `DispatchConsoleController`), return empty geometry string. Confidence: HIGH -- pattern matches existing codebase.

### INTGR-05: PAGASA Weather (Stub)
**Real system:** PAGASA uses 3-level color-coded rainfall warnings (Yellow/Orange/Red) with rainfall thresholds:
- Yellow: slight flooding possible
- Orange: 15-30mm/hr, continue next 2 hours
- Red: serious flooding in flood-prone areas

**Advisory format (wind, floods):** Bulletins issued by municipality/province with timestamps.

**Stub data shape recommendation:**
```php
[
    'advisories' => [
        [
            'type' => 'rainfall',  // rainfall, wind, flood
            'level' => 'orange',   // yellow, orange, red
            'title' => 'Rainfall Warning Level 2 -- Butuan City',
            'description' => 'Intense rainfall of 15-30mm/hr expected...',
            'affected_barangays' => ['Libertad', 'Baan Km 3', 'Baan Riverside'],
            'issued_at' => '2026-03-13T08:00:00+08:00',
            'expires_at' => '2026-03-13T14:00:00+08:00',
        ],
    ],
    'current' => [
        'rainfall_mm_hr' => 22.5,
        'wind_speed_kph' => 45,
        'wind_direction' => 'NE',
        'temperature_c' => 28,
    ],
]
```

Confidence: MEDIUM -- PAGASA API documentation is not publicly accessible; data shape modeled from their advisory format standards.

### INTGR-06: Hospital EHR / HL7 FHIR R4 (Stub)
**Real protocol:** HL7 FHIR R4 Encounter + Patient resources
**Trigger:** Outcome = "Transported to Hospital" + hospital selection (from `ResponderController::resolve()`)
**Payload:** Patient vitals, assessment tags, incident type, ETA, unit ID

**Recommended FHIR R4 resources to model:**
1. **Patient** (minimal): identifier, name (anonymous/unknown for emergencies), gender
2. **Encounter** (pre-notification): status=`planned`, class=`EMER`, subject (Patient ref), period.start, serviceProvider (hospital), reasonCode (incident type)
3. **Observation** (vitals): BP, HR, SpO2, GCS as individual observation resources

**Stub approach:** Build the FHIR JSON payload as PHP arrays, log it, return a success acknowledgment with a stub reference ID. The stub should use hospital names from `config/hospitals.php`.

Confidence: HIGH -- FHIR R4 spec is well-documented and stable.

### INTGR-07: NDRRMC SitRep (Stub)
**Real format:** XML template mapped from IRMS incident record, POST to NDRRMC Disaster Reporting API
**Trigger:** P1 incident closure, or on-demand by supervisor
**Fallback:** Formatted PDF emailed to OCD Caraga

**NDRRMC SitRep fields (modeled from standard Philippine disaster reporting):**
- Report number, date/time
- Type of disaster/incident
- Location (municipality, barangay, region)
- Number of affected families/persons
- Casualties (dead, injured, missing)
- Damage (infrastructure, agriculture)
- Response actions taken
- Resources deployed
- Status of operations

**Stub approach:** Build XML string using PHP's built-in `SimpleXMLElement`, log the XML payload, return a stub submission reference. The XML does not need to match a real NDRRMC schema (which is not publicly documented) -- it should model realistic SitRep fields.

Confidence: LOW for exact schema (NDRRMC XML schema not publicly documented), HIGH for the stub approach.

### INTGR-08: BFP Fire Sync (Stub)
**Real system:** BFP-AIMS (Automated Assets and Incidents Monitoring System) -- REST webhook bidirectional
**Inbound:** BFP fire incidents auto-mirrored into IRMS
**Outbound:** IRMS fire incidents pushed to BFP

**Stub approach:** Two methods: `pushFireIncident()` (outbound, logs payload) and `parseInboundFireIncident()` (inbound, returns normalized incident array from BFP payload). Uses HMAC signature validation pattern matching existing `IoTWebhookController`.

Confidence: LOW for exact BFP API format (not publicly documented), HIGH for the integration pattern.

### INTGR-09: PNP e-Blotter (Stub)
**Real system:** PNP e-Blotter for criminal incident recording
**Trigger:** Criminal incident types (assault, stabbing, homicide) on resolution
**Payload:** 5W1H framework -- Who, What, When, Where, Why, How

**Stub approach:**
```php
[
    'blotter_no' => 'BLT-2026-00001',  // stub reference
    'who' => 'Unknown suspect',
    'what' => $incident->incidentType->name,
    'when' => $incident->created_at->toIso8601String(),
    'where' => $incident->location_text,
    'why' => 'Under investigation',
    'how' => $incident->notes,
    'reporting_unit' => $unit->callsign,
    'status' => 'Filed',
]
```

Confidence: LOW for exact e-Blotter API format, HIGH for the stub pattern.

## Discretion Recommendations

### Error Simulation Strategy
**Recommendation:** All stubs **always succeed** by default. Add optional config flag `config('integrations.{connector}.simulate_errors')` defaulting to `false`. When enabled, stubs throw a connector-specific exception on ~10% of calls (deterministic based on hash). This keeps tests predictable while allowing error-path demonstration.

### PAGASA Weather Data Surfacing
**Recommendation:** The weather interface should be a pull-based service (`getCurrentAdvisories()`, `getCurrentConditions()`) consumed by any controller or job that needs weather context. In this phase, it is only the interface + stub -- wiring to dispatch map overlay is a frontend concern for a future phase or v2 (ADV-06). The stub returns static Butuan-area advisory data.

### Government Connector Stub Depth
**Recommendation:** Model realistic field names and shapes but do not attempt exact schema compliance. The NDRRMC XML, BFP, and PNP APIs are not publicly documented. Stubs should use field names from the spec's description (Section 4.4) and Philippine disaster reporting conventions. When real API agreements are in place, the interface remains stable and only the implementation class changes.

### Hospital EHR FHIR Scope
**Recommendation:** Model 3 FHIR R4 resources as PHP array structures: Patient (minimal demographics), Encounter (emergency pre-notification), and a vitals Bundle (Observation resources for BP, HR, SpO2, GCS). This matches the spec's payload description: "Patient vitals, assessment tags, incident type, ETA, unit ID."

## Integration Points with Existing Code

| Existing Code | New Connector | How They Connect |
|---------------|---------------|-----------------|
| `DispatchConsoleController::nearbyUnits()` | `DirectionsServiceInterface` | Replace hardcoded `30km/h` ETA with `DirectionsServiceInterface::route()` call |
| `SmsWebhookController` | `SmsParserServiceInterface` | Update constructor from concrete `SmsParserService` to interface |
| `ResponderController::resolve()` | `HospitalEhrServiceInterface` | On `TransportedToHospital` outcome, call `preNotify()` |
| `ResponderController::resolve()` | `NdrrmcReportServiceInterface` | On P1 closure, call `submitSitRep()` |
| `IoTWebhookController` (HMAC pattern) | `BfpSyncServiceInterface` | Inbound BFP webhook follows same HMAC validation pattern |
| `IncidentStatusChanged` event | `PnpBlotterServiceInterface` | Listener on resolution of criminal incidents |
| `config/hospitals.php` | `StubHospitalEhrService` | Stub uses hospital array for realistic pre-notification data |

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Concrete class injection | Interface + container binding | Laravel best practice (stable) | Enables swappable implementations |
| Inline service creation | `$this->app->bind()` in ServiceProvider | Standard Laravel pattern | Centralized, testable, swappable |
| Direct HTTP calls in controllers | Service layer abstraction | Project decision (Phase 2) | Business logic decoupled from transport |

**Deprecated/outdated:**
- None relevant -- the patterns used are stable Laravel conventions.

## Open Questions

1. **NDRRMC XML Schema**
   - What we know: NDRRMC accepts SitRep submissions; fields follow standard disaster reporting format
   - What's unclear: Exact XML schema, endpoint URL, authentication method
   - Recommendation: Model realistic fields; stub logs XML payload. Real implementation will be built when NDRRMC API agreement is in place. Already noted as a blocker in STATE.md.

2. **BFP-AIMS Integration Format**
   - What we know: BFP uses REST webhooks with HMAC signatures for fire incident sync
   - What's unclear: Exact payload fields, endpoint structure, HMAC algorithm
   - Recommendation: Model bidirectional sync with realistic fire incident fields. Follow IoTWebhookController's HMAC-SHA256 pattern.

3. **Wiring Connectors to Event Listeners**
   - What we know: Events like `IncidentStatusChanged` are the natural triggers for NDRRMC/PNP/BFP connectors
   - What's unclear: Whether to create listeners in this phase or defer to a future integration wiring phase
   - Recommendation: Create listeners in this phase as they are thin wrappers calling the interface methods. This validates the full integration path end-to-end.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest v4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=ServiceTest` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INTGR-01 | All interfaces resolvable from container, stubs log calls | unit | `php artisan test --compact tests/Unit/IntegrationArchitectureTest.php -x` | Wave 0 |
| INTGR-02 | Geocoding stub returns Philippine results with correct shape | unit | `php artisan test --compact tests/Unit/GeocodingServiceTest.php -x` | Exists |
| INTGR-03 | Directions stub returns distance/duration/geometry | unit | `php artisan test --compact tests/Unit/DirectionsServiceTest.php -x` | Wave 0 |
| INTGR-04 | SMS parser interface + enhanced stub parses/classifies correctly | unit | `php artisan test --compact tests/Unit/SmsParserServiceTest.php -x` | Wave 0 |
| INTGR-04 | Existing SMS webhook tests still pass after retrofit | feature | `php artisan test --compact tests/Feature/Intake/SmsWebhookTest.php -x` | Exists |
| INTGR-05 | Weather stub returns advisories with correct shape | unit | `php artisan test --compact tests/Unit/WeatherServiceTest.php -x` | Wave 0 |
| INTGR-06 | Hospital EHR stub generates FHIR payload and logs | unit | `php artisan test --compact tests/Unit/HospitalEhrServiceTest.php -x` | Wave 0 |
| INTGR-07 | NDRRMC stub generates SitRep XML and logs | unit | `php artisan test --compact tests/Unit/NdrrmcReportServiceTest.php -x` | Wave 0 |
| INTGR-08 | BFP stub handles inbound parse + outbound push | unit | `php artisan test --compact tests/Unit/BfpSyncServiceTest.php -x` | Wave 0 |
| INTGR-09 | PNP stub generates blotter payload and logs | unit | `php artisan test --compact tests/Unit/PnpBlotterServiceTest.php -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=ServiceTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/IntegrationArchitectureTest.php` -- verifies all interfaces resolve from container
- [ ] `tests/Unit/DirectionsServiceTest.php` -- covers INTGR-03
- [ ] `tests/Unit/SmsParserServiceTest.php` -- covers INTGR-04 interface retrofit
- [ ] `tests/Unit/WeatherServiceTest.php` -- covers INTGR-05
- [ ] `tests/Unit/HospitalEhrServiceTest.php` -- covers INTGR-06
- [ ] `tests/Unit/NdrrmcReportServiceTest.php` -- covers INTGR-07
- [ ] `tests/Unit/BfpSyncServiceTest.php` -- covers INTGR-08
- [ ] `tests/Unit/PnpBlotterServiceTest.php` -- covers INTGR-09

## Sources

### Primary (HIGH confidence)
- Existing codebase: `app/Contracts/`, `app/Services/`, `app/Providers/AppServiceProvider.php` -- established interface+stub+binding pattern
- Existing tests: `tests/Unit/GeocodingServiceTest.php`, `tests/Feature/Intake/SmsWebhookTest.php` -- established test patterns
- [HL7 FHIR R4 Encounter](https://hl7.org/fhir/R4/encounter.html) -- Encounter resource structure
- [HL7 FHIR R4 Patient](https://hl7.org/fhir/R4/patient.html) -- Patient resource structure
- [Mapbox Directions API](https://docs.mapbox.com/api/navigation/directions/) -- Response format (distance, duration, geometry)

### Secondary (MEDIUM confidence)
- [PAGASA legend/warning system](https://www.pagasa.dost.gov.ph/learnings/legend) -- 3-level color-coded rainfall warnings
- [PAGASA PANaHON](https://www.panahon.gov.ph/) -- Weather data platform
- `docs/IRMS-Specification.md` Section 4.4 -- Integration Layer requirements and protocol descriptions

### Tertiary (LOW confidence)
- NDRRMC SitRep XML format -- not publicly documented; modeled from standard Philippine disaster reporting conventions
- BFP-AIMS REST API -- not publicly documented; modeled from spec description and IoT webhook pattern
- PNP e-Blotter API -- not publicly documented; modeled from 5W1H recording framework per PNP MC 2014-009

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all existing project dependencies, no new packages
- Architecture: HIGH -- extends proven pattern already used 3 times in codebase
- Interface design: HIGH -- business-level contracts following existing conventions
- Stub data realism: MEDIUM -- Philippine-specific but real API schemas for NDRRMC/BFP/PNP are not public
- Pitfalls: HIGH -- based on direct codebase analysis and established patterns

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable patterns, no fast-moving dependencies)
