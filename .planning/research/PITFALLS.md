# Domain Pitfalls

**Domain:** Emergency Incident Response Management System (IRMS)
**Project:** IRMS for CDRRMO Butuan City
**Researched:** 2026-03-12

---

## Critical Pitfalls

Mistakes that cause rewrites, data loss, or system failures during active emergencies.

---

### Pitfall 1: WebSocket Message Loss During Reconnection

**What goes wrong:** Dispatchers and responders miss critical real-time updates (unit assignments, status changes, new incidents) when their WebSocket connection drops momentarily -- common on 4G mobile networks in the Philippines. Laravel Reverb is fire-and-forget: once a broadcast event is emitted, it goes to currently connected clients. If a responder is disconnected for even 5 seconds during a network handoff, they never receive events broadcast during that window. The responder's view becomes stale and they may not know they have been assigned an incident.

**Why it happens:** WebSocket protocols are inherently ephemeral. Reverb (Pusher protocol) does not persist or replay missed messages. Developers assume "real-time" means "reliable delivery" -- it does not. Laravel Echo handles reconnection, but not message replay.

**Consequences:** Responders miss dispatch assignments. Dispatchers see stale unit statuses. Two dispatchers assign the same unit because neither sees the other's assignment. During multi-incident emergencies, the system becomes an unreliable source of truth.

**Prevention:**
1. Implement a "state sync" endpoint that clients call immediately after reconnection -- a single API call that returns current incident assignments, unit statuses, and a sequence number.
2. Add a `last_event_id` or monotonic sequence counter to broadcast events. On reconnect, clients request events since their last seen ID from a short-lived Redis stream (XRANGE) or a database event log.
3. Use Inertia polling (every 15-30s) as a fallback consistency layer alongside WebSocket for critical state (dispatch board, assignment status). WebSocket provides low-latency; polling provides consistency.
4. Show a visible "Reconnecting..." banner in the UI when the WebSocket connection drops. Never silently degrade.

**Detection:** Test by throttling network in Chrome DevTools to simulate 4G disconnects. Monitor `Echo.connector.pusher.connection.state` changes in the client. Alert on Reverb connection churn rates in production.

**Confidence:** HIGH -- this is a well-documented limitation of all Pusher-protocol WebSocket systems.

**Phase relevance:** Dispatch Layer (WebSocket integration). Must be addressed from day one, not retrofitted.

---

### Pitfall 2: Dual-Dispatch Race Condition (Two Dispatchers Assign the Same Unit)

**What goes wrong:** Two dispatchers viewing the dispatch console simultaneously both see Unit Alpha as "Available." Both click "Assign" to different incidents within milliseconds. Without server-side concurrency control, both assignments succeed, and Unit Alpha is now double-booked. In an emergency, this means one incident has no actual responder while everyone thinks it does.

**Why it happens:** The dispatch UI shows a point-in-time snapshot. Optimistic updates on the frontend create the illusion that the assignment is confirmed before the server validates it. Without database-level locking or optimistic concurrency control, the server processes both requests as valid.

**Consequences:** Ghost assignments (incident shows a unit assigned, but unit is actually elsewhere). Delayed response to the second incident. Eroded dispatcher trust in the system.

**Prevention:**
1. Use database-level pessimistic locking: `Unit::where('id', $unitId)->where('status', 'available')->lockForUpdate()->first()`. If the unit is no longer available, reject the assignment and return a conflict response.
2. Implement optimistic concurrency with a `version` column on the units table. The assignment request includes the version the dispatcher saw; if it has changed, reject with HTTP 409 Conflict.
3. Broadcast unit status changes immediately via Reverb so the second dispatcher's UI updates before they can click "Assign."
4. On the frontend, disable the "Assign" button immediately on click and show a pending state until the server confirms.
5. Return clear error messaging: "Unit Alpha was assigned to Incident #42 by Dispatcher Cruz 3 seconds ago."

**Detection:** Write a Pest feature test that fires two concurrent assignment requests for the same unit using `DB::beginTransaction()` timing. If both succeed, the test fails.

**Confidence:** HIGH -- standard concurrency problem in any multi-user dispatch system.

**Phase relevance:** Dispatch Layer. Must be in the initial assignment workflow, not added later.

---

### Pitfall 3: PostGIS Geometry vs Geography Type Confusion

**What goes wrong:** Developers store coordinates as PostGIS `geometry` type with SRID 4326 and assume distance calculations return meters. They do not. Geometry with SRID 4326 returns distances in degrees (1 degree ~ 111km at the equator, but varies by latitude). A proximity query like "find units within 2km" using `ST_Distance` on a geometry column returns nonsensical results. Butuan City (latitude ~8.9N) is especially affected because the degree-to-km ratio differs significantly from mid-latitudes.

**Why it happens:** SRID 4326 is the coordinate reference system that uses latitude/longitude -- the same as GPS. Developers see familiar lat/lng values and assume the column is "doing the right thing." But SRID 4326 on a geometry column means calculations are Cartesian (flat-plane math on a sphere). Only the `geography` type does proper geodesic calculations in meters.

**Consequences:** Proximity-based unit dispatch is wrong. "Nearest available unit" calculations are inaccurate. Barangay boundary ST_Contains checks may have edge-case errors near polygon boundaries. These bugs are subtle -- they produce results that look approximately right but are consistently off.

**Prevention:**
1. Use `geography` type (not `geometry`) for all point locations (incident coordinates, unit GPS positions). Geography always calculates in meters on the WGS84 spheroid.
2. Use `geometry` type only for barangay boundary polygons (complex shapes where geography functions are limited), but always cast to geography for distance/containment operations: `ST_DWithin(unit.location::geography, incident.location::geography, 2000)`.
3. In Laravel migrations, use the Magellan package (`clickbar/laravel-magellan`) which provides proper geography column support, or use raw SQL in migrations: `$table->rawColumn('location', 'geography(Point, 4326)')`.
4. Write a seed/test that verifies a known distance (e.g., Butuan City Hall to Agusan del Norte Provincial Capitol is ~2.1km) returns the correct value within 1% tolerance.

**Detection:** Run `SELECT ST_Distance(ST_MakePoint(125.5, 8.9)::geography, ST_MakePoint(125.52, 8.92)::geography);` and verify the result is in meters (~2800m), not in degrees (~0.028).

**Confidence:** HIGH -- this is one of the most documented PostGIS mistakes. The blog post "It's a Trap! PostGIS Geometry with SRID 4326 is not a Geography" describes this exact issue.

**Phase relevance:** Database setup / PostGIS migration phase. Must be correct from the first migration. Changing column types after data exists requires a migration with data transformation.

---

### Pitfall 4: GPS Update Frequency Destroying Responder Battery Life

**What goes wrong:** The system uses `navigator.geolocation.watchPosition` with high accuracy and sends GPS coordinates to the server every 3-5 seconds. Responders' phones die within 2-3 hours of a shift. In a real emergency, a dead phone means a lost responder.

**Why it happens:** Developers optimize for map accuracy ("we need real-time tracking!") without considering that the Geolocation API with `enableHighAccuracy: true` keeps the GPS radio active continuously. Studies show GPS tracking drains 13-38% of battery per hour depending on signal quality. The Philippines' mobile infrastructure means signal quality is often poor, increasing drain.

**Consequences:** Responder phones die during active incidents. Responders disable location sharing to save battery, defeating the purpose. The system's "real-time" tracking becomes unreliable precisely when it matters most.

**Prevention:**
1. Implement adaptive update frequency based on responder status:
   - **Standby:** Every 60 seconds, low accuracy (`enableHighAccuracy: false`)
   - **En Route:** Every 10 seconds, high accuracy (they are moving, accuracy matters)
   - **On Scene:** Every 30 seconds, low accuracy (they are stationary)
   - **Off Duty:** No tracking
2. Use the `maximumAge` option (e.g., 30000ms for standby) to reuse cached positions instead of forcing a fresh GPS fix.
3. Implement client-side dead reckoning: only send updates when the position has changed by more than 50 meters, regardless of timer.
4. Batch GPS updates: queue 3-5 position updates locally and send them in a single HTTP request instead of one WebSocket message per update.
5. Show battery level in the dispatch console (the Battery Status API is deprecated on web but available through the responder reporting their battery on check-in).

**Detection:** Test with a real Android phone on 4G for 4 hours. Measure battery drain at different update frequencies. If drain exceeds 8% per hour in standby mode, the frequency is too high.

**Confidence:** HIGH -- well-established mobile geolocation constraint. Multiple sources confirm 25% of smartphone power drain comes from location services.

**Phase relevance:** Responder Layer. Design the adaptive frequency system before building the tracking feature, not as an optimization after complaints.

---

### Pitfall 5: Mutable Incident Records Breaking Audit Compliance

**What goes wrong:** The incident model uses standard Eloquent `update()` calls that overwrite previous values. A dispatcher changes an incident priority from P2 to P1, and the original P2 classification is lost. Six months later, a DILG audit or legal proceeding asks "what was the original priority assessment?" and the system cannot answer.

**Why it happens:** Standard CRUD patterns treat records as mutable state. Emergency response systems are legally required to maintain complete, immutable audit trails. Philippine DRRM regulations (RA 10121) and DILG reporting requirements expect that every action, decision, and status change is permanently recorded with who did it and when.

**Consequences:** Non-compliance with DILG reporting requirements. Legal liability if incident response decisions are questioned and no audit trail exists. Loss of data integrity for post-incident analysis and KPI calculation.

**Prevention:**
1. Implement an event-sourced incident timeline: every state change is an INSERT into an `incident_events` table, never an UPDATE to the incidents table. The current state is derived from the latest event.
2. At minimum, use a separate `incident_timeline` table (as specified in PROJECT.md) that logs every status transition, assignment change, priority change, and note with `user_id`, `timestamp`, `old_value`, `new_value`.
3. Make the timeline table append-only: no UPDATE or DELETE operations. Enforce this with a database trigger or a model observer that prevents modifications.
4. Store the complete state snapshot in JSONB at each transition point for complex fields (vitals, assessment tags).
5. Add `created_by` and `updated_by` to all models via a global model observer or trait (e.g., `HasAuditFields`).

**Detection:** Write a test that creates an incident, changes its priority three times, and asserts that the timeline contains all four states (initial + 3 changes) with correct timestamps and user attribution.

**Confidence:** HIGH -- this is a fundamental requirement for emergency management software, not an optional feature.

**Phase relevance:** Data model / Incident model phase. The timeline table must exist from the first incident migration. Retrofitting audit trails onto existing mutable records is extremely painful.

---

## Moderate Pitfalls

---

### Pitfall 6: MapLibre `setData()` Full Re-render on Every GPS Update

**What goes wrong:** The map uses a GeoJSON source and calls `source.setData(entireFeatureCollection)` every time any unit's GPS position changes. With 30+ units and updates every 10 seconds, this means the entire GeoJSON is serialized, transferred to the Web Worker, parsed, and re-rendered 3+ times per second. The map stutters, CPU spikes, and the dispatch console becomes unusable.

**Why it happens:** MapLibre's `setData()` replaces the entire GeoJSON source, triggering a full re-parse and re-render. For small datasets this is fine. At scale with frequent updates, `JSON.stringify` alone can take 200ms for complex feature collections, blocking the main thread.

**Prevention:**
1. Use `source.updateData()` (available in MapLibre GL JS v3+) instead of `setData()`. This performs partial updates and requires each feature to have a unique `id` property (use the unit ID).
2. Structure the GeoJSON so each unit is a Feature with `id: unit.id`. When a GPS update arrives, call `updateData({ type: 'FeatureCollection', features: [updatedFeature] })` with only the changed feature.
3. Debounce incoming GPS updates on the client: collect all updates received within a 500ms window, then apply them as a single `updateData()` batch.
4. Use symbol layers (WebGL-rendered) instead of HTML Marker elements. The project spec already mandates this -- enforce it strictly. One HTML marker = one DOM element; 100 markers = 100 DOM reflows per update.
5. Disable overlap detection for unit markers (`icon-allow-overlap: true`, `icon-ignore-placement: true`) to avoid MapLibre's expensive collision detection on every frame.

**Detection:** Open Chrome DevTools Performance tab. If `setData` calls show >50ms in the flame chart, the approach needs optimization. Monitor `map.on('render')` frequency.

**Confidence:** HIGH -- MapLibre GitHub issue #106 documents `setData` taking "surprisingly long" with large datasets. The `updateData` method was specifically added to address this.

**Phase relevance:** Dispatch Layer (map console). Implement `updateData()` from the start; migrating from `setData()` later requires restructuring GeoJSON to include feature IDs.

---

### Pitfall 7: Laravel Reverb Connection Limit (1,024 Default)

**What goes wrong:** In production, Reverb stops accepting new WebSocket connections after ~1,000 concurrent users. Dispatchers report "connection failed" errors during a major incident when all responders, supervisors, and the Mayor's Office are simultaneously connected.

**Why it happens:** Reverb uses ReactPHP with `stream_select` by default, which is limited to 1,024 file descriptors (one per connection). Each WebSocket connection = one open file. The OS default `ulimit -n` is often 1,024 as well. Developers never test with >50 concurrent connections in development.

**Prevention:**
1. Install `ext-uv` (libuv PHP extension) on the production server. Reverb automatically switches from `stream_select` to `ext-uv` when available, removing the 1,024 limit.
2. Set `ulimit -n 65536` in the Reverb Supervisor configuration (`minfds=10000` in supervisor conf).
3. Configure `/etc/security/limits.conf` on the DigitalOcean droplet to allow the Reverb process user high file descriptor limits.
4. In `config/reverb.php`, set appropriate `max_request_size` to prevent memory exhaustion from oversized payloads.
5. If horizontal scaling is needed later, configure Reverb with Redis pub/sub so multiple Reverb instances share channel state.

**Detection:** Run `ulimit -n` on the production server -- if it returns 1024, you have a problem. Load test with 200+ concurrent WebSocket connections using a tool like `wscat` or Artillery.

**Confidence:** HIGH -- explicitly documented in Laravel Reverb official documentation.

**Phase relevance:** Infrastructure / deployment configuration. Must be part of the DigitalOcean provisioning checklist, not discovered during the first real emergency.

---

### Pitfall 8: ST_Distance Without Index (Full Table Scan on Every Dispatch)

**What goes wrong:** The "find nearest available unit" query uses `ORDER BY ST_Distance(unit.location, incident.location) LIMIT 5`. This performs a full table scan, calculating distance to every unit in the database. With 200 units it takes 50ms. With spatial joins across incidents and barangay polygons, it compounds to seconds.

**Why it happens:** `ST_Distance()` cannot use spatial indexes. It computes the exact distance between every pair of geometries. Developers write the intuitive query and it works fast in development with 10 test units, then degrades in production.

**Prevention:**
1. Always use `ST_DWithin(unit.location, incident.location, radius)` for proximity filtering. `ST_DWithin` uses the GiST spatial index for a bounding-box pre-filter, then computes exact distances only on the candidates.
2. Pattern: filter with `ST_DWithin` first, then order by `ST_Distance` on the filtered set:
   ```sql
   SELECT * FROM units
   WHERE ST_DWithin(location::geography, ST_MakePoint(125.5, 8.9)::geography, 10000)
     AND status = 'available'
   ORDER BY ST_Distance(location::geography, ST_MakePoint(125.5, 8.9)::geography)
   LIMIT 5;
   ```
3. Create GiST indexes on all geography/geometry columns in migrations:
   ```sql
   CREATE INDEX units_location_gist ON units USING GIST (location);
   ```
4. For barangay polygon lookups (`ST_Contains`), always have a GiST index on the `boundary` column.

**Detection:** Run `EXPLAIN ANALYZE` on proximity queries. If you see "Seq Scan" instead of "Index Scan using [gist_index]", the query is not using the spatial index.

**Confidence:** HIGH -- Paul Ramsey's "Spatial Indexes and Bad Queries" blog post documents this as the most common PostGIS performance mistake.

**Phase relevance:** Database setup and Dispatch Layer. Index creation must be in the same migration that creates the spatial columns.

---

### Pitfall 9: Role/Permission Complexity Creep

**What goes wrong:** The system starts with 4 clean roles (dispatcher, responder, supervisor, admin). Then requests come in: "Dispatcher A should only see incidents in Barangay X," "Supervisor B needs dispatch access but only for fire incidents," "The Mayor's Office needs read-only analytics but not incident details." Each exception becomes a new permission, a new role, or worse, a hardcoded `if` check. Within 6 months, the permission system is an unmaintainable spaghetti of 50+ granular permissions with unclear role-permission mappings.

**Why it happens:** Emergency management organizations have complex, hierarchical command structures. Filipino LGU organizational politics add informal authority relationships. Each stakeholder request seems small ("just add one permission") but the combinatorial complexity grows exponentially.

**Prevention:**
1. Use Spatie Laravel Permission for the database-driven role/permission system, but define a strict permission taxonomy upfront:
   - Resource-based: `incidents.view`, `incidents.create`, `incidents.dispatch`
   - Action-based: `units.assign`, `units.track`, `analytics.view`
   - Keep permissions under 25 total for v1.
2. Roles are collections of permissions. Do NOT create one-off roles for individual users. If a user needs a unique set, they need a new named role with a clear purpose.
3. Use Laravel Policies (not inline permission checks) for all authorization logic. Policies are testable, discoverable, and centralized.
4. Implement geographic scoping (barangay-level access) as a separate concern from role permissions. Use a `user_barangays` pivot table, not a permission-per-barangay.
5. Document the permission matrix in a single source of truth (database seeder) that is version-controlled. Never create permissions through the UI in v1.
6. Say NO to one-off permission requests. If a request does not fit the existing taxonomy, it probably indicates a UI/UX problem, not a permission problem.

**Detection:** If the permissions table has more than 30 rows, or if any controller has more than 2 `$this->authorize()` calls, complexity is creeping.

**Confidence:** MEDIUM -- based on general RBAC patterns and Spatie community guidance, not specific incident management case studies.

**Phase relevance:** RBAC phase (earliest operational layer). Define the taxonomy before writing any authorization checks.

---

### Pitfall 10: WebSocket Channel Authorization Leaking Incident Data

**What goes wrong:** Broadcast channels are configured too broadly. All dispatchers listen to a single `dispatch` channel, which is correct. But incident-specific channels (e.g., `incident.{id}`) are authorized with a simple "is authenticated" check instead of "is this user assigned to or supervising this incident." A curious responder can listen to any incident channel and see sensitive data (victim details, location of domestic violence calls).

**Why it happens:** Developers focus on making real-time features work, not on channel-level authorization. Laravel's `Broadcast::channel()` authorization callbacks are easy to skip during prototyping. The Reverb issue #272 reveals that while listening is checked by channel rules, publishing is not checked server-side.

**Consequences:** Data privacy violations. In emergency response, incident details can include sensitive personal information, domestic violence locations, or mental health crisis details. Leaking this data violates Philippine Data Privacy Act (RA 10173).

**Prevention:**
1. Define explicit authorization in `routes/channels.php` for every private/presence channel:
   ```php
   Broadcast::channel('incident.{incident}', function (User $user, Incident $incident) {
       return $user->isAssignedTo($incident) || $user->isSupervisor() || $user->isDispatcher();
   });
   ```
2. Use presence channels (not private channels) for the dispatch board -- this provides who-is-watching context.
3. Never broadcast sensitive victim data over WebSocket channels. Broadcast only IDs and status changes; let the client fetch sensitive details via an authorized API endpoint.
4. Server-side event validation: even though Reverb does not validate client-published messages, restrict client-to-server communication to Whisper events on presence channels for non-sensitive data (typing indicators, cursor positions).
5. Audit channel subscriptions in production logs.

**Detection:** Write a Pest test that authenticates as a responder not assigned to an incident and attempts to subscribe to that incident's channel. It must be rejected.

**Confidence:** MEDIUM -- the Reverb publishing vulnerability (issue #272) is documented but may be addressed in future versions. Channel authorization is standard Laravel but often overlooked.

**Phase relevance:** Dispatch Layer and Responder Layer. Channel authorization rules must be written alongside the broadcast event implementation, not after.

---

## Minor Pitfalls

---

### Pitfall 11: Inertia SSR Memory Leaks on Long-Running Dispatch Sessions

**What goes wrong:** The dispatch console is a long-running SPA session (dispatchers keep it open for 8-12 hour shifts). Vue components accumulate event listeners, WebSocket subscriptions, and GeoJSON data in memory. After several hours, the browser tab consumes 2GB+ of memory and becomes sluggish.

**Prevention:**
1. Clean up all `Echo.channel()` subscriptions in `onUnmounted()` lifecycle hooks.
2. Use `shallowRef` instead of `ref` for large GeoJSON feature collections to prevent Vue's deep reactivity proxy from wrapping every nested coordinate.
3. Implement incident archival: remove resolved incidents from the reactive state after 30 minutes. They remain accessible via search, not in the active map/list.
4. Periodically (every 2 hours) prompt or auto-refresh the dispatch page to reset memory. Use Inertia `router.reload()` to do this without losing position.

**Confidence:** MEDIUM -- standard SPA concern, heightened by the long-session dispatch use case.

**Phase relevance:** Dispatch Layer. Implement cleanup patterns from the first component, not as a post-launch optimization.

---

### Pitfall 12: Mapbox API Key Exposure in Client-Side Code

**What goes wrong:** The Mapbox access token for geocoding and basemap tiles is embedded in the Vue frontend JavaScript bundle. Anyone can inspect the page source, extract the token, and use it for their own projects, running up the project's Mapbox bill.

**Prevention:**
1. Use Mapbox's URL restriction feature to limit the token to `irms.test` and the production domain.
2. For geocoding API calls, proxy them through a Laravel API endpoint so the token stays server-side. The frontend calls `/api/geocode?q=...`, the server calls Mapbox.
3. Use a separate Mapbox token for the basemap (client-side, URL-restricted) and for geocoding/directions API (server-side only, never exposed to client).
4. Set Mapbox spending limits on the account.

**Confidence:** HIGH -- standard security practice for any Mapbox/Google Maps integration.

**Phase relevance:** Integration Layer (Mapbox setup). Configure token restrictions during initial Mapbox integration.

---

### Pitfall 13: IoT Webhook Ingestion Without Rate Limiting or Validation

**What goes wrong:** Flood gauge sensors, fire alarms, and weather stations send webhooks to the IRMS. A malfunctioning sensor sends thousands of duplicate alerts per minute, flooding the incident queue with false alarms and overwhelming the dispatch console.

**Prevention:**
1. Implement per-sensor rate limiting: max 1 incident creation per sensor per 5-minute window. Queue subsequent alerts as updates to the existing incident.
2. Validate webhook payloads with schema validation (required fields, value ranges). Reject payloads where a flood gauge reports water level of -500m.
3. Implement webhook signature verification (HMAC) to prevent spoofed alerts.
4. Use a dedicated Laravel queue for IoT ingestion, separate from the dispatch queue, so a sensor flood does not delay human-reported incidents.
5. Add a "sensor health" dashboard showing alert frequency per sensor to catch malfunctions early.

**Confidence:** MEDIUM -- based on general IoT integration patterns. Specific sensor behavior depends on the hardware deployed.

**Phase relevance:** Intake Layer (IoT sensor ingestion). Rate limiting must be in the webhook controller from day one.

---

### Pitfall 14: Time Zone Handling in Incident Timestamps

**What goes wrong:** The server stores timestamps in UTC (Laravel default), but dispatchers and reports expect Philippine Standard Time (PST, UTC+8). A dispatcher sees an incident "created at 14:30" but the DILG report shows "06:30" because the report generator forgot to convert from UTC.

**Prevention:**
1. Store all timestamps in UTC in the database (non-negotiable).
2. Set `APP_TIMEZONE=Asia/Manila` for display formatting, but keep `DB_TIMEZONE=UTC` (or do not set it, letting PostgreSQL default to UTC).
3. Create a `FormatsTimestamp` trait or use Inertia's shared data to pass the user's timezone. Format all dates in the Vue frontend using a consistent helper that converts from UTC to Asia/Manila.
4. In PDF report generation, explicitly convert: `$incident->created_at->setTimezone('Asia/Manila')->format('M d, Y H:i')`.
5. For the incident timeline, display relative times ("3 minutes ago") in the UI but absolute PST times in exports.

**Confidence:** HIGH -- universal web application concern, but especially important for compliance reporting.

**Phase relevance:** Data model phase (earliest). Establish the timezone convention before any timestamp is displayed.

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation | Severity |
|-------------|---------------|------------|----------|
| Database / PostGIS setup | Geometry vs Geography confusion (Pitfall 3) | Use geography for points, cast for polygon ops | Critical |
| Database / PostGIS setup | Missing GiST indexes (Pitfall 8) | Create indexes in same migration as columns | Moderate |
| Database / PostGIS setup | Timezone convention (Pitfall 14) | Establish UTC storage + PST display from first migration | Minor |
| RBAC / Permissions | Complexity creep (Pitfall 9) | Define strict taxonomy of <25 permissions upfront | Moderate |
| Intake Layer | IoT webhook flooding (Pitfall 13) | Per-sensor rate limiting in webhook controller | Moderate |
| Dispatch Layer (WebSocket) | Message loss on reconnect (Pitfall 1) | State sync endpoint + Redis event stream | Critical |
| Dispatch Layer (WebSocket) | Reverb 1,024 connection limit (Pitfall 7) | Install ext-uv, configure ulimit | Moderate |
| Dispatch Layer (WebSocket) | Channel data leakage (Pitfall 10) | Explicit per-channel authorization rules | Moderate |
| Dispatch Layer (Map) | setData full re-render (Pitfall 6) | Use updateData() with feature IDs from start | Moderate |
| Dispatch Layer (Assignment) | Dual-dispatch race condition (Pitfall 2) | Pessimistic locking on unit assignment | Critical |
| Dispatch Layer (UI) | Memory leaks in long sessions (Pitfall 11) | Cleanup in onUnmounted, shallowRef for GeoJSON | Minor |
| Responder Layer (GPS) | Battery drain from tracking (Pitfall 4) | Adaptive frequency by responder status | Critical |
| Responder Layer (Map) | Mapbox token exposure (Pitfall 12) | URL-restrict client token, proxy server-side API calls | Minor |
| Incident Data Model | Mutable records breaking audit (Pitfall 5) | Append-only timeline table from first migration | Critical |
| All real-time features | Assuming WebSocket = reliable delivery | Polling fallback + state sync on reconnect | Critical |

---

## Sources

- [Laravel Reverb Documentation (12.x)](https://laravel.com/docs/12.x/reverb) -- connection limits, ext-uv, scaling
- [Laravel Broadcasting Documentation (12.x)](https://laravel.com/docs/12.x/broadcasting) -- channel authorization
- [Reverb Issue #272: Publishing without auth](https://github.com/laravel/reverb/issues/272) -- channel security concern
- [Reverb Issue #307: Rate limiting WebSocket messages](https://github.com/laravel/reverb/issues/307)
- [MapLibre GL JS: Optimising Performance with Large GeoJSON Datasets](https://maplibre.org/maplibre-gl-js/docs/guides/large-data/) -- setData vs updateData
- [MapLibre Issue #106: setData can take surprisingly long](https://github.com/maplibre/maplibre-gl-js/issues/106)
- [MapLibre Issue #1236: Partial updates for GeoJSON sources](https://github.com/maplibre/maplibre-gl-js/issues/1236)
- ["It's a Trap! PostGIS Geometry with SRID 4326 is not a Geography"](https://blog.frank-mich.com/its-a-trap-postgis-geometry-with-srid-4326-is-not-a-geography/)
- [PostGIS: Use ST_DWithin for radius queries](https://postgis.net/documentation/tips/st-dwithin/)
- [Paul Ramsey: Spatial Indexes and Bad Queries](http://blog.cleverelephant.ca/2021/05/indexes-and-queries.html)
- [Spatie Laravel Permission: Performance Tips](https://spatie.be/docs/laravel-permission/v7/best-practices/performance)
- [clickbar/laravel-magellan: PostGIS toolbox for Laravel](https://github.com/clickbar/laravel-magellan)
- [Metova: Geolocation Without Draining Battery](https://metova.com/how-to-implement-geolocation-without-draining-your-users-battery/)
- [DHS Computer Aided Dispatch Systems TechNote](https://www.dhs.gov/sites/default/files/publications/CAD_TN_0911-508.pdf)
- [5 Principles for High-Performance PostGIS Queries](https://medium.com/@cfvandersluijs/5-principles-for-writing-high-performance-queries-in-postgis-bbea3ffb9830)
