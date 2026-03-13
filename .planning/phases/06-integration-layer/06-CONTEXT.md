# Phase 6: Integration Layer - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

All external API integrations (Mapbox Directions, PAGASA Weather, Semaphore SMS enhancements, Hospital EHR, NDRRMC, BFP, PNP) are architecturally wired behind PHP interfaces with working stub implementations. Existing Phase 2 stubs (geocoding, SMS) are retrofitted into the unified pattern. Swapping stub for real implementation requires zero business logic changes.

</domain>

<decisions>
## Implementation Decisions

### Stub data realism
- All stubs return **Philippine-specific data**: real Butuan barangay names, PAGASA-style advisory text ("Rainfall Warning Level 2 — Butuan City"), hospital names from `config/hospitals.php`, realistic government report formats
- Stubs should feel convincing for demos and catch format issues early — not generic "Test Hospital" placeholders

### Error simulation
- Claude's discretion per connector — choose the approach that best fits each integration's failure modes

### Logging
- Laravel `Log::info()` only for all stub calls, matching the existing `StubMapboxGeocodingService` pattern
- No new database tables for integration logging — keep it simple and grep-able

### Retrofit existing services
- Unify all services under the interface pattern: existing `SmsParserService` (currently no contract) gets a formal interface
- Review and consolidate existing `GeocodingServiceInterface`, `SmsServiceInterface`, `ProximityServiceInterface` bindings
- All bindings consolidated in `AppServiceProvider` with one consistent architecture

### Claude's Discretion
- Error simulation strategy per connector (always succeed vs configurable failures)
- PAGASA weather data shape and where advisories surface in the system
- Government connector stub depth (how closely to model real NDRRMC XML, BFP sync, PNP e-Blotter schemas)
- Hospital EHR FHIR resource scope (which HL7 FHIR R4 resources to model)

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches. The existing `StubMapboxGeocodingService` and `StubSemaphoreSmsService` in `app/Services/` establish the pattern to follow.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Contracts/GeocodingServiceInterface.php`: Established interface pattern — follow for all new connectors
- `app/Contracts/SmsServiceInterface.php`: SMS send/parse contract — enhance for Phase 6
- `app/Contracts/ProximityServiceInterface.php`: Proximity ranking contract (real PostGIS, not stubbed)
- `app/Services/StubMapboxGeocodingService.php`: Reference stub — returns deterministic Butuan-area coordinates, logs calls
- `app/Services/StubSemaphoreSmsService.php`: Reference stub — logs sends, normalizes inbound payloads
- `app/Services/SmsParserService.php`: Keyword classifier + location extractor — needs formal contract
- `config/hospitals.php`: 5 Butuan hospitals static array — extend for EHR stub data
- `config/sms.php`: Keyword-to-incident-type mappings — reference for config-driven approach

### Established Patterns
- Interface → Stub → `AppServiceProvider::register()` binding with constructor injection
- Config-driven behavior (`config/sms.php`, `config/priority.php`, `config/hospitals.php`)
- `Log::info()` for all stub activity
- Event broadcasting layer (`ShouldBroadcast` + `ShouldDispatchAfterCommit`) available as integration hooks

### Integration Points
- `AppServiceProvider.php`: All service container bindings consolidated here
- `DispatchConsoleController::nearbyUnits()`: Uses ProximityServiceInterface — Directions connector adds road-network ETA
- `ResponderController::resolve()`: Dispatches `GenerateIncidentReport` job — NDRRMC SitRep hook on P1 closure
- `SmsWebhookController`: Uses SmsServiceInterface — enhanced SMS connector plugs in here
- `IoTWebhookController`: HMAC-validated webhooks — BFP fire sync follows same inbound pattern
- Event listeners: `IncidentStatusChanged`, `MutualAidRequested` — hooks for government agency notifications

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 06-integration-layer*
*Context gathered: 2026-03-13*
