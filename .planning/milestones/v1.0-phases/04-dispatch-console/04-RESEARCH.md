# Phase 4: Dispatch Console - Research

**Researched:** 2026-03-13
**Domain:** MapLibre GL JS mapping, WebSocket real-time dispatch, PostGIS proximity queries, Web Audio API alerts
**Confidence:** HIGH

## Summary

Phase 4 builds the dispatch console -- a full-screen MapLibre map with collapsible overlay panels for incident queue, incident detail, unit roster, and assignment workflow. The phase is primarily a frontend-heavy build with significant backend additions for unit assignment, proximity ranking, and mutual aid. The WebSocket infrastructure (Reverb, Echo, channel authorization) is already in place from Phase 3. All broadcast events (`IncidentCreated`, `IncidentStatusChanged`, `UnitLocationUpdated`, `UnitStatusChanged`, `AssignmentPushed`) exist and broadcast on the correct channels. The main work is consuming these events in the dispatch console UI and building the assignment workflow.

The current data model has a single `assigned_unit` FK on the `incidents` table, but the CONTEXT.md requires multi-unit assignment ("assign one or more units"). This requires a new `incident_unit` pivot table and relationship changes. The proximity ranking service needs raw PostGIS `ST_DWithin` + `ST_Distance` queries against geography columns, following the established pattern from `BarangayLookupService`. The `agencies` table and `agency_incident_type` pivot table need to be created for the mutual aid protocol.

**Primary recommendation:** Use `maplibre-gl` v5.x directly (not vue-maplibre-gl wrapper) for maximum control over WebGL layers, GeoJSON source updates, and animation. The wrapper adds abstraction overhead that conflicts with the low-level WebGL circle layer customization and real-time `updateData()` calls this phase requires. Build composables (`useDispatchMap`, `useDispatchFeed`, `useDispatchSession`, `useAlertSystem`) following the established patterns from Phase 8's intake composables.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Map-dominant layout: full-screen 2D MapLibre map with collapsible overlay panels
- Left panel (320px): incident queue with filter tabs (ALL, P1, P1-2, ACTIVE)
- Right panel (360px): contextual -- defaults to UNIT STATUS; switches to INCIDENT DETAIL when incident selected; switches to unit detail when unit marker clicked
- New `DispatchLayout.vue` separate from IntakeLayout, same design system (DM Sans + Space Mono, 56px topbar, 24px statusbar)
- Topbar: DISPATCH branding, ACTIVE/CRITICAL/TOTAL stats, unit availability ratio, live clock, live ticker
- Statusbar: system status, dispatcher name, CDRRMO label, connection status
- 2D only -- no pitch/rotation/3D camera controls
- Dark map style as default, follows app-wide dark/light toggle
- No barangay boundary overlay on map
- No NEW INCIDENT button -- incident creation stays in intake station
- Map legend overlay (bottom-left)
- Incident Detail Panel: full incident info, SLA WINDOW progress bar (P1=5m, P2=10m, P3=20m, P4=30m), status progression pipeline, ADVANCE button, ASSIGNEES with ack timer, DISPATCH with proximity chips, TIMELINE, REQUEST MUTUAL AID button
- Unit assignment: proximity-sorted chips, one-click assign (no confirmation), click assigned unit to unassign (with confirmation)
- ETA: straight-line distance / 30km/h (labeled with ~, real Mapbox Directions in Phase 6)
- Panel-only assignment -- no assigning by clicking map markers
- Assignment pushed via WebSocket (AssignmentPushed event)
- Animated dashed connection lines between assigned units and incidents
- Incident markers: WebGL circle layers with halo + pulse rings, priority colors (P1 red, P2 orange, P3 amber, P4 green)
- Unit markers: WebGL circle layers with glow + border, status colors (available green, en route blue, on scene yellow/amber, offline gray)
- Frequency-based audio tones via Web Audio API with specific Hz/duration per priority
- P1 red screen flash: inset box-shadow border pulse, non-blocking
- Audio always on, no mute control
- 90-second ack timer with circular progress ring or text countdown, green > 30s, red <= 30s
- Mutual aid: modal with type-based agency suggestions, 5 agencies (BFP, PNP, DSWD, DOH, Adjacent LGU), stored in DB
- Session metrics: ACTIVE, CRITICAL, TOTAL, unit ratio -- all real-time via WebSocket

### Claude's Discretion
- MapLibre tile style URL and customization (dark/light vector tiles)
- Exact overlay panel collapse/expand animation
- Map zoom level and bounds for Butuan City
- WebGL marker layer implementation details (circle layer spec)
- Marker smooth animation technique between GPS positions
- Connection line rendering approach (GeoJSON line layer with dash-array animation)
- Unit detail panel content and layout
- SLA window calculation method (from which timestamp)
- Topbar stat pill layout and styling
- Live ticker implementation
- Queue card layout details
- Bottom-left legend design
- Status advancement validation (which transitions are allowed from dispatch)

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DSPTCH-01 | 2D dispatch map with MapLibre GL JS, zoom 13, centered on Butuan City, dark/light styles | MapLibre GL JS v5.x, CartoCDN Dark Matter/Positron tiles, map configuration patterns |
| DSPTCH-02 | Incident markers as WebGL circle layers colored by priority | GeoJSON source + multiple circle layers for halo/pulse/dot, priority-keyed paint properties |
| DSPTCH-03 | Unit markers as WebGL circle layers colored by status | Same GeoJSON source pattern, status-keyed paint properties |
| DSPTCH-04 | Unit GPS positions update in real-time via WebSocket, smooth animation | `updateData()` on GeoJSON source for partial updates, `requestAnimationFrame` interpolation |
| DSPTCH-05 | Dispatcher can assign one or more available units to incidents | New `incident_unit` pivot table, `AssignUnitRequest` form request, assignment controller actions |
| DSPTCH-06 | Units ranked by proximity via PostGIS ST_DWithin with distance and ETA | `ProximityRankingService` with raw SQL ST_DWithin + ST_Distance, straight-line ETA calculation |
| DSPTCH-07 | Assignment pushed to responder via WebSocket | Existing `AssignmentPushed` event already broadcasts -- just need to dispatch it on assignment |
| DSPTCH-08 | 90-second ack timer with visual countdown and audio alert on expiry | Client-side timer composable, ack received via `IncidentStatusChanged` or dedicated event |
| DSPTCH-09 | Audio alerts via Web Audio API with distinct priority tones; P1 red screen flash | Extend existing `useWebSocket` audio infrastructure with per-priority oscillator configs |
| DSPTCH-10 | Session metrics in console header, real-time updates | Computed from initial Inertia props + WebSocket mutations, following `useIntakeSession` pattern |
| DSPTCH-11 | Mutual aid modal with agency suggestions based on incident type | New `agencies` table, `agency_incident_type` pivot, `AgencySeeder`, modal component |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| maplibre-gl | ^5.20.0 | WebGL map rendering | Open-source Mapbox GL fork, free vector tiles, WebGL circle layers, GeoJSON sources with updateData() |
| @laravel/echo-vue | ^2.3.1 | WebSocket composables | Already installed, useEcho/useEchoPresence for dispatch channel subscriptions |
| laravel-echo | ^2.3.1 | Echo client | Already installed, Reverb broadcaster |
| pusher-js | ^8.4.0 | WebSocket transport | Already installed, Reverb compatible |
| clickbar/laravel-magellan | existing | PostGIS model casts | Already installed, Point::makeGeodetic for geography columns |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @vueuse/core | ^12.8.2 | Vue utilities | Already installed, useIntervalFn for timers, useEventListener |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| maplibre-gl direct | vue-maplibre-gl wrapper | Wrapper adds component abstraction but limits control over WebGL layers, GeoJSON source.updateData(), and animation timing. Direct use is better for this performance-critical real-time scenario |
| requestAnimationFrame interpolation | CSS transitions on markers | CSS transitions only work with DOM markers, not WebGL layers. RAF interpolation is the only option for GeoJSON source-based circle layers |

**Installation:**
```bash
npm install maplibre-gl
```

Note: `maplibre-gl` CSS must be imported in the component or app.css:
```css
@import 'maplibre-gl/dist/maplibre-gl.css';
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Contracts/
│   └── ProximityServiceInterface.php    # Interface for proximity ranking
├── Events/
│   ├── MutualAidRequested.php           # New broadcast event
│   └── (existing events unchanged)
├── Http/
│   ├── Controllers/
│   │   └── DispatchConsoleController.php # Dispatch page + assignment actions
│   └── Requests/
│       ├── AssignUnitRequest.php         # Validate assignment
│       └── MutualAidRequest.php         # Validate mutual aid request
├── Models/
│   ├── Agency.php                       # New model for mutual aid agencies
│   └── IncidentUnit.php                 # Pivot model for multi-unit assignment
├── Services/
│   └── ProximityRankingService.php      # PostGIS ST_DWithin + distance ranking
database/
├── migrations/
│   ├── create_incident_unit_table.php   # Pivot: incident_id + unit_id + timestamps
│   ├── create_agencies_table.php        # Agency info for mutual aid
│   └── create_agency_incident_type_table.php  # Pivot: agency_id + incident_type_id
├── seeders/
│   └── AgencySeeder.php                 # BFP, PNP, DSWD, DOH, LGU Cabadbaran
resources/js/
├── composables/
│   ├── useDispatchMap.ts                # Map initialization, layers, markers, flyTo
│   ├── useDispatchFeed.ts               # Incident list with WebSocket mutations
│   ├── useDispatchSession.ts            # Session metrics with real-time updates
│   ├── useAckTimer.ts                   # 90-second acknowledgement timer
│   └── useAlertSystem.ts               # Per-priority audio tones + P1 flash
├── layouts/
│   └── DispatchLayout.vue               # Full-screen dispatch with topbar + statusbar
├── pages/
│   └── dispatch/
│       └── Console.vue                  # Main dispatch console page
├── components/
│   └── dispatch/
│       ├── DispatchTopbar.vue           # DISPATCH branding + metrics + ticker
│       ├── DispatchStatusbar.vue        # Connection status + dispatcher info
│       ├── DispatchQueuePanel.vue       # Left panel: incident queue + filters
│       ├── IncidentDetailPanel.vue      # Right panel: incident detail + assignment
│       ├── UnitStatusPanel.vue          # Right panel default: unit roster by agency
│       ├── UnitDetailPanel.vue          # Right panel: unit detail when marker clicked
│       ├── AssignmentChip.vue           # Unit chip in dispatch section
│       ├── AckTimerRing.vue             # Circular countdown timer
│       ├── SlaProgressBar.vue           # SLA window progress bar
│       ├── StatusPipeline.vue           # REPORTED > DISPATCHED > ... > RESOLVED
│       ├── MutualAidModal.vue           # Modal for requesting mutual aid
│       ├── MapLegend.vue                # Bottom-left legend overlay
│       └── ConnectionLine.vue           # (logical, not a Vue component -- GeoJSON layer)
└── types/
    └── dispatch.ts                      # TypeScript types for dispatch domain
```

### Pattern 1: Direct MapLibre GL JS in Vue Composable
**What:** Initialize MapLibre map instance in a composable, manage GeoJSON sources and layers programmatically
**When to use:** When needing full control over WebGL layers, real-time source updates, and animation
**Example:**
```typescript
// resources/js/composables/useDispatchMap.ts
import maplibregl from 'maplibre-gl';
import type { Map, GeoJSONSource } from 'maplibre-gl';
import { onMounted, onUnmounted, ref, shallowRef } from 'vue';

export function useDispatchMap(containerId: string) {
    const map = shallowRef<Map | null>(null);
    const isLoaded = ref(false);

    onMounted(() => {
        map.value = new maplibregl.Map({
            container: containerId,
            style: 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json',
            center: [125.5406, 8.9475], // Butuan City
            zoom: 13,
            maxPitch: 0,       // 2D only
            dragRotate: false, // No rotation
        });

        map.value.on('load', () => {
            isLoaded.value = true;
            addIncidentSource();
            addUnitSource();
            addConnectionLineSource();
        });
    });

    onUnmounted(() => {
        map.value?.remove();
    });

    function updateIncidentData(geojson: GeoJSON.FeatureCollection) {
        const source = map.value?.getSource('incidents') as GeoJSONSource;
        source?.setData(geojson);
    }

    function updateUnitPosition(unitId: string, lng: number, lat: number) {
        const source = map.value?.getSource('units') as GeoJSONSource;
        source?.updateData({
            type: 'FeatureCollection',
            features: [{
                type: 'Feature',
                id: unitId,
                geometry: { type: 'Point', coordinates: [lng, lat] },
                properties: {} // keep existing properties
            }]
        });
    }

    return { map, isLoaded, updateIncidentData, updateUnitPosition };
}
```

### Pattern 2: PostGIS Proximity Ranking Service
**What:** Raw SQL with ST_DWithin and ST_Distance for proximity queries on geography columns
**When to use:** Finding available units near an incident, ranked by distance
**Example:**
```php
// app/Services/ProximityRankingService.php
// Follows established pattern from BarangayLookupService
use Illuminate\Support\Facades\DB;

class ProximityRankingService implements ProximityServiceInterface
{
    public function rankNearbyUnits(
        float $latitude,
        float $longitude,
        float $radiusMeters = 50000.0
    ): array {
        return DB::select("
            SELECT
                id, callsign, type, agency, crew_capacity, status,
                ST_Y(coordinates::geometry) as latitude,
                ST_X(coordinates::geometry) as longitude,
                ST_Distance(coordinates, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance_meters
            FROM units
            WHERE status = 'AVAILABLE'
              AND coordinates IS NOT NULL
              AND ST_DWithin(
                  coordinates,
                  ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                  ?
              )
            ORDER BY distance_meters ASC
        ", [$longitude, $latitude, $longitude, $latitude, $radiusMeters]);
    }
}
```

### Pattern 3: Multi-Unit Assignment via Pivot Table
**What:** `incident_unit` pivot table to support multiple units per incident
**When to use:** Assigning/unassigning units to incidents
**Example:**
```php
// Migration
Schema::create('incident_unit', function (Blueprint $table) {
    $table->uuid('incident_id');
    $table->string('unit_id', 20);
    $table->timestamp('assigned_at');
    $table->timestamp('acknowledged_at')->nullable();
    $table->timestamp('unassigned_at')->nullable();
    $table->foreignId('assigned_by')->constrained('users');
    $table->primary(['incident_id', 'unit_id']);
    $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
    $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
});

// Incident model relationship
public function assignedUnits(): BelongsToMany
{
    return $this->belongsToMany(Unit::class, 'incident_unit')
        ->withPivot('assigned_at', 'acknowledged_at', 'unassigned_at', 'assigned_by')
        ->wherePivotNull('unassigned_at');
}
```

### Pattern 4: Per-Priority Audio Tones
**What:** Extend existing Web Audio API infrastructure with configurable oscillator patterns
**When to use:** Playing distinct alert tones for different incident priorities
**Example:**
```typescript
// resources/js/composables/useAlertSystem.ts
type PriorityTone = {
    frequencies: number[];
    durations: number[];
    totalDuration: number;
};

const PRIORITY_TONES: Record<string, PriorityTone> = {
    P1: { frequencies: [880, 660, 880, 660, 880, 660], durations: [0.25, 0.25, 0.25, 0.25, 0.25, 0.25], totalDuration: 1.5 },
    P2: { frequencies: [700, 700], durations: [0.3, 0.3], totalDuration: 0.6 },
    P3: { frequencies: [550], durations: [0.3], totalDuration: 0.3 },
    P4: { frequencies: [440], durations: [0.2], totalDuration: 0.2 },
};

function playPriorityTone(priority: string): void {
    const tone = PRIORITY_TONES[priority];
    if (!tone || !audioContext) return;

    let offset = 0;
    for (let i = 0; i < tone.frequencies.length; i++) {
        const osc = audioContext.createOscillator();
        const gain = audioContext.createGain();
        osc.connect(gain);
        gain.connect(audioContext.destination);
        osc.frequency.value = tone.frequencies[i];
        gain.gain.value = 0.3;
        osc.start(audioContext.currentTime + offset);
        gain.gain.exponentialRampToValueAtTime(
            0.01, audioContext.currentTime + offset + tone.durations[i]
        );
        osc.stop(audioContext.currentTime + offset + tone.durations[i]);
        offset += tone.durations[i];
    }
}
```

### Pattern 5: Smooth Marker Animation via requestAnimationFrame
**What:** Interpolate GPS positions over frames for smooth visual movement
**When to use:** When UnitLocationUpdated WebSocket event arrives with new coordinates
**Example:**
```typescript
function animateUnitPosition(
    unitId: string,
    fromLng: number, fromLat: number,
    toLng: number, toLat: number,
    durationMs: number = 1000
): void {
    const startTime = performance.now();

    function step(currentTime: number) {
        const elapsed = currentTime - startTime;
        const t = Math.min(elapsed / durationMs, 1);
        // Ease-out cubic
        const eased = 1 - Math.pow(1 - t, 3);

        const lng = fromLng + (toLng - fromLng) * eased;
        const lat = fromLat + (toLat - fromLat) * eased;

        updateUnitPosition(unitId, lng, lat);

        if (t < 1) {
            requestAnimationFrame(step);
        }
    }

    requestAnimationFrame(step);
}
```

### Anti-Patterns to Avoid
- **Using vue-maplibre-gl for real-time WebGL layers:** The wrapper's reactive data binding conflicts with the performance requirements of `updateData()` on every GPS event. Use maplibre-gl directly.
- **Polling for unit positions:** Unit positions come via WebSocket (`UnitLocationUpdated` on `dispatch.units` channel). Never poll the API for this.
- **Using HTML markers instead of WebGL layers:** HTML markers hit DOM limits at ~50 markers. WebGL circle layers handle thousands with zero DOM overhead.
- **Storing timer state on the server:** The 90-second ack timer is purely client-side. The server stores `assigned_at` timestamp; the client computes the countdown.
- **Using Inertia router.reload for real-time data:** The dispatch console receives all updates via WebSocket mutations on reactive local copies, not Inertia prop reloads. Following the Phase 3 pattern.
- **Removing the existing `assigned_unit` FK:** Keep the single `assigned_unit` column as the "primary" assigned unit for backward compatibility with existing code. The new `incident_unit` pivot handles multi-assignment.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Map rendering | Custom canvas drawing | maplibre-gl WebGL layers | GPU-accelerated, handles thousands of features, built-in interaction handlers |
| Proximity queries | Haversine formula in PHP | PostGIS ST_DWithin + ST_Distance | Spatial index utilization (GiST), correct spheroid calculations, sub-ms query times |
| WebSocket subscriptions | Manual pusher-js channels | @laravel/echo-vue composables (useEcho) | Auto-subscribe/unsubscribe lifecycle, type-safe callbacks, established project pattern |
| Audio generation | Audio file playback | Web Audio API OscillatorNode | No audio files to load, configurable frequency/duration, latency-free in-browser synthesis |
| GeoJSON feature management | Manual coordinate arrays | MapLibre GeoJSON source + updateData | Handles geometry serialization, spatial indexing, efficient partial updates |
| State sync on reconnect | Custom polling loop | Existing useWebSocket + StateSyncController | Already built in Phase 3, just need to extend the sync response for dispatch needs |

**Key insight:** The map rendering and spatial query layers have enormous hidden complexity (coordinate projections, spatial indexing, GPU buffer management). MapLibre and PostGIS handle these correctly; custom implementations will have bugs with edge cases like antimeridian wrapping, SRID mismatches, and float precision.

## Common Pitfalls

### Pitfall 1: MapLibre `load` Event Race Condition
**What goes wrong:** Adding sources/layers before the map style has loaded throws errors
**Why it happens:** `new maplibregl.Map()` is async; the style loads over the network
**How to avoid:** Always add sources and layers inside `map.on('load', ...)` callback. Use the `isLoaded` ref to gate any source mutations.
**Warning signs:** "Source already exists" or "Style is not done loading" console errors

### Pitfall 2: GeoJSON `updateData()` Requires Feature IDs
**What goes wrong:** `updateData()` silently ignores features without IDs, falling back to full `setData()` performance
**Why it happens:** MapLibre needs IDs to match which features to update incrementally
**How to avoid:** Set `id` on every GeoJSON feature. For incident features use the incident UUID; for unit features use the unit callsign (AMB-01 etc). Also set `promoteId` on the source.
**Warning signs:** Map visually flickers on every GPS update instead of smoothly updating

### Pitfall 3: PostGIS Geography vs Geometry for ST_DWithin
**What goes wrong:** ST_DWithin with geometry type uses SRID units (degrees, not meters), giving meaningless results
**Why it happens:** The `coordinates` column is `geography(Point, 4326)` but if you cast to geometry for the query, units change to degrees
**How to avoid:** Use geography type consistently: `ST_DWithin(coordinates, ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography, distance_in_meters)`. Note: ST_MakePoint takes longitude first, then latitude.
**Warning signs:** All units appear "within range" regardless of distance, or no units found within reasonable radius

### Pitfall 4: MapLibre Coordinate Order (LngLat, not LatLng)
**What goes wrong:** Markers appear in the wrong location (e.g., somewhere in the ocean)
**Why it happens:** MapLibre uses `[longitude, latitude]` order (GeoJSON standard), while many APIs use `[latitude, longitude]`
**How to avoid:** Always convert when moving between server (PostGIS returns lat/lng) and client (MapLibre expects lng/lat). The existing `UnitLocationUpdated` event returns `latitude` and `longitude` as separate fields -- use them explicitly.
**Warning signs:** Markers appearing near [0,0] or in symmetrically wrong locations

### Pitfall 5: Web Audio API Auto-Play Policy
**What goes wrong:** Audio does not play on page load
**Why it happens:** Browsers require user interaction before allowing AudioContext to start
**How to avoid:** The existing `useWebSocket.ts` already handles this with the `initAudio()` unlock pattern (click/keydown event listener). Reuse this same AudioContext instance. The dispatch page should display a brief "Click to enable audio alerts" overlay on first load if AudioContext is suspended.
**Warning signs:** `audioContext.state === 'suspended'` after page load

### Pitfall 6: Echo Event Name Without Namespace
**What goes wrong:** WebSocket events are not received
**Why it happens:** Per project decision [03-02], `@laravel/echo-vue` auto-prepends the namespace. If you add `App\\Events\\` prefix manually, it double-prefixes.
**How to avoid:** Use bare event names: `useEcho('dispatch.incidents', 'IncidentCreated', ...)` not `useEcho('dispatch.incidents', 'App\\Events\\IncidentCreated', ...)`
**Warning signs:** No callbacks fire despite events being broadcast (visible in Reverb/Horizon logs)

### Pitfall 7: Multi-Unit Assignment Data Model Gap
**What goes wrong:** Existing `assigned_unit` FK on incidents table only supports one unit
**Why it happens:** Phase 1 created a simple single-FK design; Phase 4 requires multi-unit assignment
**How to avoid:** Create `incident_unit` pivot table. Keep existing `assigned_unit` column for backward compatibility (it can represent the "primary" assigned unit). The pivot table is the source of truth for all assignments.
**Warning signs:** Assigning a second unit overwrites the first assignment

### Pitfall 8: Memory Leak from Map Event Listeners
**What goes wrong:** Map click/hover handlers accumulate on component re-mount
**Why it happens:** MapLibre event listeners are not automatically cleaned up when Vue components unmount
**How to avoid:** Store references to event handler functions and call `map.off()` in `onUnmounted()`. Use the composable pattern that returns cleanup functions.
**Warning signs:** Multiple flyTo animations firing simultaneously, increasing CPU usage over time

## Code Examples

### MapLibre Circle Layer Configuration for Incident Markers
```typescript
// Incident marker layers (from inside map 'load' handler)
// Source: MapLibre GL JS circle layer documentation
map.addSource('incidents', {
    type: 'geojson',
    data: { type: 'FeatureCollection', features: [] },
    promoteId: 'id',
});

// Halo ring (outer glow)
map.addLayer({
    id: 'incident-halo',
    type: 'circle',
    source: 'incidents',
    paint: {
        'circle-radius': 18,
        'circle-color': ['match', ['get', 'priority'],
            'P1', 'var(--t-p1)', // #dc2626
            'P2', 'var(--t-p2)', // #ea580c
            'P3', 'var(--t-p3)', // #ca8a04
            'P4', 'var(--t-p4)', // #16a34a
            '#888'
        ],
        'circle-opacity': 0.15,
        'circle-blur': 1,
    },
});

// Core dot
map.addLayer({
    id: 'incident-core',
    type: 'circle',
    source: 'incidents',
    paint: {
        'circle-radius': 7,
        'circle-color': ['match', ['get', 'priority'],
            'P1', '#dc2626',
            'P2', '#ea580c',
            'P3', '#ca8a04',
            'P4', '#16a34a',
            '#888'
        ],
        'circle-stroke-width': 2,
        'circle-stroke-color': '#ffffff',
    },
});
```

### Connection Line Layer with Dash Animation
```typescript
// Animated dashed lines between assigned units and their incidents
map.addSource('connections', {
    type: 'geojson',
    data: { type: 'FeatureCollection', features: [] },
});

map.addLayer({
    id: 'connection-lines',
    type: 'line',
    source: 'connections',
    paint: {
        'line-color': ['match', ['get', 'priority'],
            'P1', '#dc2626',
            'P2', '#ea580c',
            'P3', '#ca8a04',
            'P4', '#16a34a',
            '#888'
        ],
        'line-width': 2,
        'line-dasharray': [2, 4],
    },
});

// Animate dash offset
let dashOffset = 0;
function animateDash() {
    dashOffset = (dashOffset + 0.5) % 6;
    map.setPaintProperty('connection-lines', 'line-dasharray', [2, 4]);
    // MapLibre repaints on property change
    requestAnimationFrame(animateDash);
}
```

### Dispatch Console Inertia Page Props
```php
// DispatchConsoleController.php
public function show(): Response
{
    $incidents = Incident::query()
        ->with('incidentType', 'barangay', 'assignedUnits')
        ->whereIn('status', [
            IncidentStatus::Triaged,
            IncidentStatus::Dispatched,
            IncidentStatus::EnRoute,
            IncidentStatus::OnScene,
        ])
        ->orderByRaw("CASE priority WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 WHEN 'P3' THEN 3 WHEN 'P4' THEN 4 END")
        ->orderBy('created_at', 'asc')
        ->get();

    $units = Unit::query()
        ->select('id', 'callsign', 'type', 'agency', 'crew_capacity', 'status', 'coordinates')
        ->get();

    $agencies = Agency::query()->with('incidentTypes')->get();

    return Inertia::render('dispatch/Console', [
        'incidents' => $incidents,
        'units' => $units,
        'agencies' => $agencies,
    ]);
}
```

### Unit Assignment Endpoint
```php
// DispatchConsoleController.php
public function assignUnit(AssignUnitRequest $request, Incident $incident): JsonResponse
{
    $unitId = $request->validated()['unit_id'];
    $unit = Unit::findOrFail($unitId);

    // Attach to pivot (multi-unit)
    $incident->assignedUnits()->attach($unitId, [
        'assigned_at' => now(),
        'assigned_by' => $request->user()->id,
    ]);

    // Update unit status
    $oldStatus = $unit->status;
    $unit->update(['status' => UnitStatus::Dispatched]);

    // Update incident status if first assignment
    if ($incident->status === IncidentStatus::Triaged) {
        $oldIncidentStatus = $incident->status;
        $incident->update([
            'status' => IncidentStatus::Dispatched,
            'dispatched_at' => now(),
            'assigned_unit' => $unitId, // backward compat
        ]);
        IncidentStatusChanged::dispatch($incident, $oldIncidentStatus);
    }

    // Log to timeline
    IncidentTimeline::create([
        'incident_id' => $incident->id,
        'event_type' => 'unit_assigned',
        'event_data' => ['unit_id' => $unitId, 'callsign' => $unit->callsign],
        'actor_type' => User::class,
        'actor_id' => $request->user()->id,
    ]);

    // Push to responder(s) on this unit
    $unit->users->each(function (User $user) use ($incident, $unitId) {
        AssignmentPushed::dispatch($incident, $unitId, $user->id);
    });

    UnitStatusChanged::dispatch($unit, $oldStatus);

    return response()->json(['success' => true]);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| maplibre-gl setData() for all updates | updateData() for partial updates | maplibre-gl v4+ | Significant perf gain for frequent GPS updates (only changed features re-processed) |
| Separate markers via DOM elements | WebGL circle layers via GeoJSON source | MapLibre best practice | 10x+ performance for 50+ markers, zero DOM overhead |
| vue-maplibre-gl component wrapper | Direct maplibre-gl in composables | Project decision | Better control, simpler debugging, no wrapper update lag |
| CartoCDN v2 GL styles | CartoCDN Dark Matter / Positron GL styles | Ongoing | Free, no API key, good for development and production |

**Deprecated/outdated:**
- `mapbox-gl`: Proprietary after v2, use `maplibre-gl` (the open-source fork) instead
- `vue-mapbox` (soal): Abandoned, replaced by vue-maplibre-gl or direct maplibre-gl usage

## Open Questions

1. **Pulse ring animation for incident markers**
   - What we know: MapLibre circle layers support `circle-radius` with data-driven expressions. Animated pulse requires either CSS (only for HTML markers) or a requestAnimationFrame loop updating the paint property.
   - What's unclear: Whether updating `circle-radius` paint property every frame causes perf issues with 50+ incidents
   - Recommendation: Test with a separate `incident-pulse` layer that uses `circle-opacity` animation (cheaper than radius animation). If performance is an issue, limit pulse to selected incident only.

2. **Dark/light map style switching**
   - What we know: CartoCDN offers Dark Matter (dark) and Positron (light) as separate style URLs. MapLibre supports `map.setStyle()` to switch styles.
   - What's unclear: Whether `setStyle()` preserves custom sources/layers or requires re-adding them
   - Recommendation: `setStyle()` removes all sources/layers. Listen for `style.load` event after `setStyle()` and re-add all custom sources/layers. Store current GeoJSON data in composable state so it can be re-applied.

3. **SLA window calculation base timestamp**
   - What we know: SLA targets are P1=5min, P2=10min, P3=20min, P4=30min
   - What's unclear: Whether to calculate from `created_at` (when incident was created/reported) or from `dispatched_at` (when unit was assigned)
   - Recommendation: Calculate from `created_at` -- SLA measures total response time from report to resolution. The progress bar shows how much of the SLA window has elapsed since the incident was first reported.

4. **Incident status transitions allowed from dispatch console**
   - What we know: Status pipeline is REPORTED > DISPATCHED > EN ROUTE > ON SCENE > RESOLVED. Dispatcher has "ADVANCE" button.
   - What's unclear: Which status transitions the dispatcher should be allowed to perform (vs. only the responder)
   - Recommendation: Dispatcher can advance from DISPATCHED to any subsequent status via the "ADVANCE" button (useful for radio confirmations when responders are using radio instead of app). Validate that status can only move forward, never backward (except via supervisor override).

## Tile Style URLs

**Dark (default):**
```
https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json
```

**Light:**
```
https://basemaps.cartocdn.com/gl/positron-gl-style/style.json
```

These are free, no API key required, vector tiles with global coverage. CartoCDN is maintained by CARTO and uses OpenStreetMap data.

## Butuan City Map Configuration

```typescript
const BUTUAN_CENTER: [number, number] = [125.5406, 8.9475];
const BUTUAN_ZOOM = 13;
const BUTUAN_BOUNDS: [[number, number], [number, number]] = [
    [125.40, 8.85],  // Southwest corner
    [125.68, 9.05],  // Northeast corner
];
```

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=DispatchConsole` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DSPTCH-01 | Dispatch console page renders for dispatcher | feature | `php artisan test --compact tests/Feature/Dispatch/DispatchConsolePageTest.php -x` | Wave 0 |
| DSPTCH-02 | Incident markers data includes priority in GeoJSON props | feature | Covered by page render test (props include incidents with priority) | Wave 0 |
| DSPTCH-03 | Unit markers data includes status in GeoJSON props | feature | Covered by page render test (props include units with status) | Wave 0 |
| DSPTCH-04 | Unit location update event has correct payload | unit | `php artisan test --compact tests/Unit/BroadcastEventTest.php --filter=UnitLocationUpdated -x` | Exists |
| DSPTCH-05 | Dispatcher can assign unit to incident | feature | `php artisan test --compact tests/Feature/Dispatch/UnitAssignmentTest.php -x` | Wave 0 |
| DSPTCH-06 | Proximity ranking returns units sorted by distance | feature | `php artisan test --compact tests/Feature/Dispatch/ProximityRankingTest.php -x` | Wave 0 |
| DSPTCH-07 | Assignment dispatches AssignmentPushed event | feature | `php artisan test --compact tests/Feature/Dispatch/UnitAssignmentTest.php --filter=AssignmentPushed -x` | Wave 0 |
| DSPTCH-08 | Ack timer behavior (client-side) | manual-only | Client-side timer -- no backend test needed. Visual verification. | N/A |
| DSPTCH-09 | Audio alerts (client-side Web Audio API) | manual-only | Browser-only feature -- no backend test. Visual/audio verification. | N/A |
| DSPTCH-10 | Session metrics return correct counts | feature | `php artisan test --compact tests/Feature/Dispatch/DispatchConsolePageTest.php --filter=metrics -x` | Wave 0 |
| DSPTCH-11 | Mutual aid request creates timeline entry | feature | `php artisan test --compact tests/Feature/Dispatch/MutualAidTest.php -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact tests/Feature/Dispatch/`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Dispatch/DispatchConsolePageTest.php` -- covers DSPTCH-01, DSPTCH-02, DSPTCH-03, DSPTCH-10
- [ ] `tests/Feature/Dispatch/UnitAssignmentTest.php` -- covers DSPTCH-05, DSPTCH-07
- [ ] `tests/Feature/Dispatch/ProximityRankingTest.php` -- covers DSPTCH-06
- [ ] `tests/Feature/Dispatch/MutualAidTest.php` -- covers DSPTCH-11
- [ ] `tests/Feature/Dispatch/StatusAdvancementTest.php` -- covers status transition validation

## Sources

### Primary (HIGH confidence)
- MapLibre GL JS official docs: GeoJSONSource API (`setData`, `updateData` methods), Map API (`flyTo`, `addLayer`, `addSource`), circle layer paint properties
- PostGIS official docs: ST_DWithin syntax for geography types (distance in meters)
- Existing codebase: `useWebSocket.ts` (audio pattern), `useIntakeFeed.ts` (Echo composable pattern), `BarangayLookupService.php` (raw PostGIS query pattern), all broadcast events, channel authorization
- @laravel/echo-vue SKILL.md: useEcho, useEchoPresence composable API

### Secondary (MEDIUM confidence)
- [CartoCDN basemap tiles](https://medium.com/@go2garret/free-basemap-tiles-for-maplibre-18374fab60cb) -- free Dark Matter / Positron styles, no API key
- [vue-maplibre-gl GitHub](https://github.com/razorness/vue-maplibre-gl) -- latest v5.6.1, confirmed active maintenance
- [maplibre-gl npm](https://www.npmjs.com/package/maplibre-gl) -- latest v5.20.0
- [MapLibre performance guide](https://maplibre.org/maplibre-gl-js/docs/guides/large-data/) -- GeoJSON optimization tips

### Tertiary (LOW confidence)
- Dash animation technique for connection lines -- based on MapLibre examples, needs hands-on validation for line-dasharray animation performance with many connections
- Pulse ring animation via paint property updates per frame -- needs perf benchmarking

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- maplibre-gl is the clear choice, all other libraries already installed
- Architecture: HIGH -- follows established project patterns (composables, service layer, raw PostGIS, Echo)
- Pitfalls: HIGH -- based on MapLibre official docs, PostGIS docs, and existing codebase patterns
- Multi-unit assignment model: HIGH -- clear gap identified, straightforward pivot table solution
- Map animation performance: MEDIUM -- updateData() is documented but pulse animation perf is untested

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable libraries, no fast-moving changes expected)
