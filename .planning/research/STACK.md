# Technology Stack

**Project:** IRMS -- Incident Response Management System
**Researched:** 2026-03-12
**Mode:** Ecosystem research for IRMS operational layers
**Scope:** Additions to existing Laravel 12 + Vue 3 + Inertia v2 stack

## Existing Stack (Not Re-Researched)

Already in place and validated: Laravel 12, Vue 3.5, Inertia.js v2, Laravel Fortify v1, Wayfinder v0,
Tailwind CSS 4, Vite 7, TypeScript 5.2, Pest v4, reka-ui, lucide-vue-next, @vueuse/core.
See `.planning/codebase/STACK.md` for full details.

---

## Recommended Additions

### Database: PostgreSQL + PostGIS

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| PostgreSQL | 16+ | Primary database | Required for PostGIS; production target is DigitalOcean Managed PostgreSQL; superior JSON/JSONB support for vitals and timeline data | HIGH |
| PostGIS | 3.4+ | Spatial extension | Core requirement: barangay boundary polygons, proximity-based unit dispatch (`ST_DWithin`), geocoding fallback (`ST_Contains`), choropleth heatmaps. No alternative exists for this use case within PostgreSQL | HIGH |
| `clickbar/laravel-magellan` | ^2.0 | Laravel PostGIS integration | Modern PostGIS toolbox for Laravel. Provides Eloquent casts for geometry/geography columns, migration helpers (`$table->magellanPoint()`, `$table->magellanPolygon()`), and typed query builder methods (`stWhere`, `stSelect`, `stDistance`) that eliminate raw SQL. Actively maintained, supports Laravel 12 + PHP 8.2+. Successor to the archived `mstaack/laravel-postgis` | HIGH |

**Why not alternatives:**
- `mstaack/laravel-postgis`: Archived, no Laravel 12 support
- `grimzy/laravel-mysql-spatial`: MySQL-focused; PostGIS support is secondary
- `artisanweblab/spatial`: Younger package, less ecosystem adoption than Magellan
- Raw Eloquent `DB::raw()`: Fragile, no type safety, no migration helpers

**Local development:** Laravel Herd Pro includes PostgreSQL with PostGIS support. Alternatively, run `docker run -d -e POSTGRES_PASSWORD=postgres -p 5432:5432 postgis/postgis:16-3.5` for a Docker-based setup.

### Real-Time: Laravel Reverb + Echo

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| `laravel/reverb` | ^1.8 | WebSocket server | First-party Laravel WebSocket server. Self-hosted (no third-party dependency), Pusher-protocol compatible, handles 30K+ concurrent connections on a single server. Required for sub-500ms GPS updates, dispatch events, and responder messaging | HIGH |
| `laravel-echo` | ^2.3 | Client-side WebSocket | Official Laravel broadcasting client. Handles channel subscriptions, presence channels (for unit online/offline tracking), and private channels (for secure dispatch-responder messaging) | HIGH |
| `@laravel/echo-vue` | latest | Vue 3 composables for Echo | Official first-party package. Provides `useEcho()` composable for reactive event listening and `useConnectionStatus()` for connection state. Eliminates boilerplate of manual Echo setup in Vue components | MEDIUM |
| `pusher-js` | ^8.0 | WebSocket protocol client | Required peer dependency for laravel-echo when using Reverb (which speaks the Pusher protocol). Not using Pusher's service -- only the protocol library | HIGH |

**Channel architecture for IRMS:**
- **Presence channel** `dispatch.console`: Tracks which dispatchers and units are online; provides `.here()`, `.joining()`, `.leaving()` events for the dispatch map
- **Private channel** `unit.{unitId}`: GPS location broadcasts, assignment pushes, status updates per unit
- **Private channel** `incident.{incidentId}`: Real-time incident timeline updates, responder messages, status transitions
- **Private channel** `user.{userId}`: Personal notifications, assignment alerts

**Why not alternatives:**
- Pusher/Ably: Adds vendor cost and latency; Reverb runs on your own infrastructure at zero cost
- `beyondcode/laravel-websockets`: Abandoned in favor of the official Reverb package
- Socket.io: Not Laravel-native; would require a separate Node.js process

### Queue Processing: Horizon + Redis

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| `laravel/horizon` | ^5.45 | Queue dashboard and worker management | Code-driven queue worker configuration, real-time throughput metrics, failed job management. Essential for monitoring GPS batch processing, notification dispatch, and PDF generation jobs. Already planned in PROJECT.md deployment target | HIGH |
| Redis | 7+ | Queue backend, cache, broadcasting | Required by both Horizon and Reverb. Sub-millisecond operations for GPS update queuing, rate limiting, and session caching. Also enables Reverb horizontal scaling via pub/sub | HIGH |

**Queue topology for IRMS:**
- `gps` queue: High-frequency GPS location updates (dedicated workers, high throughput)
- `dispatch` queue: Unit assignments, incident creation, priority notifications (medium priority)
- `notifications` queue: SMS/email/push notifications (batched, rate-limited)
- `reports` queue: PDF generation, analytics aggregation (low priority, longer timeout)
- `default` queue: Everything else

**Why not alternatives:**
- Database queue driver: Too slow for GPS update throughput; no dashboard
- Amazon SQS: Adds external dependency; Redis is already needed for Reverb and caching
- Beanstalkd: Less ecosystem support, no dashboard equivalent to Horizon

### Mapping: MapLibre GL JS + Turf.js

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| `maplibre-gl` | ^5.20 | WebGL-based map rendering | Open-source, no license fees. Spec requires 3D map with WebGL-rendered markers (no HTML overlays). Supports terrain, 3D buildings, custom layers, and vector tiles. v5 is actively maintained with frequent releases | HIGH |
| `@indoorequal/vue-maplibre-gl` | ^8.4 | Vue 3 MapLibre components | Fork of vue-maplibre-gl with MapLibre v5 support, TypeScript types, automatic WebGL context recovery (critical for mobile responders backgrounding tabs), and active maintenance. Provides `<MglMap>`, `<MglMarker>`, `<MglGeoJsonSource>`, `<MglLayer>` components | MEDIUM |
| `@turf/turf` | ^7.3 | Client-side geospatial analysis | Distance calculations (unit-to-incident ETA estimation), point-in-polygon (barangay assignment preview), bounding box operations. Tree-shakeable -- import only needed modules. Full TypeScript support | HIGH |
| Mapbox APIs | N/A (service) | Geocoding + Directions | Spec mandates Mapbox for geocoding (`/geocoding/v6/`) and directions (`/directions/v5/`). Free tier: 100K requests/month for each. Adequate for single-LGU deployment. Stubbed initially per project constraints | HIGH |

**Map component architecture:**
- **Dispatch Console (desktop):** Full 3D map, all active incidents as clustered WebGL markers, all units as animated GPS markers updated via Reverb, barangay boundary polygons, incident heatmap layer toggle
- **Responder Mini-Map (mobile):** Lightweight single-incident view, own GPS position, route overlay from Mapbox Directions API, assignment location pin
- **Analytics Heatmap:** Choropleth layer using barangay polygons colored by incident density, filterable by date/type/priority

**Why not alternatives:**
- `vue-maplibre-gl` (razorness): Last updated ~7 months ago, does not confirm MapLibre v5 support, no WebGL context recovery
- Leaflet: DOM-based markers, no WebGL rendering, can not meet spec's 3D map and performance requirements
- Google Maps JS API: License cost, no self-hosted vector tiles, vendor lock-in
- Mapbox GL JS: Proprietary license since v2 (requires Mapbox token even for self-hosted tiles); MapLibre is the open-source fork

### Roles and Permissions

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| `spatie/laravel-permission` | ^7.2 | Role-based access control | De facto standard for Laravel RBAC. Supports roles, direct permissions, and middleware (`role`, `permission`, `role_or_permission`). Works with Laravel 12 via `bootstrap/app.php` middleware configuration. Blade directives (`@role`, `@can`) and Eloquent integration. Over 100M installs | HIGH |

**Roles for IRMS:**
- `admin`: Full system access, user management, system configuration
- `supervisor`: All dispatch + analytics, report generation, unit management
- `dispatcher`: Incident triage, unit assignment, real-time map console, messaging
- `responder`: Assignment receipt, status updates, vitals entry, scene checklists

**Permissions matrix (examples):**
- `incidents.create`, `incidents.view`, `incidents.update`, `incidents.close`
- `units.assign`, `units.track`, `units.manage`
- `analytics.view`, `reports.generate`, `reports.export`
- `users.manage`, `system.configure`

**Why not alternatives:**
- Custom gates/policies only: Sufficient for simple apps, but IRMS has 4+ roles with a complex permission matrix; Spatie provides the database-backed role/permission management, caching, and middleware that would take weeks to build
- `bouncer` (JosephSilber): Good package but less community adoption, fewer tutorials, smaller ecosystem
- `laratrust`: Less actively maintained than Spatie's package

### PDF Generation

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| `spatie/laravel-pdf` | ^2.4 | Incident report and compliance report PDFs | Driver-based architecture: use DomPDF driver for simple incident closure reports (no external dependencies), switch to Browsershot/Gotenberg driver for complex compliance reports with modern CSS. Supports Blade views as input. Single API regardless of driver | HIGH |
| `barryvdh/laravel-dompdf` | ^3.1 | Fallback / simple PDFs | Pure PHP, zero external dependencies. Good for simple tabular incident reports. Installed as a dependency by spatie/laravel-pdf's DomPDF driver | HIGH |

**PDF generation strategy:**
- **Incident closure report:** Auto-generated on incident close. Simple layout (incident details, timeline, vitals, outcome). Use DomPDF driver -- fast, no external dependencies
- **DILG monthly report:** Structured compliance template with tables and charts. Use DomPDF driver
- **NDRRMC SitRep:** More complex layout. DomPDF for initial implementation, upgrade to Browsershot driver if CSS requirements exceed DomPDF capabilities
- **Quarterly/Annual reports:** Complex multi-page analytics summaries. May require Browsershot driver for chart rendering

**Why not alternatives:**
- Browsershot alone: Requires Chromium (~400MB) on server; overkill for simple reports
- Snappy/wkhtmltopdf: Abandoned upstream, WebKit from 2008, poor modern CSS support
- Cloudflare Browser Rendering driver: Adds external API dependency; unnecessary for an on-prem-first deployment

### Activity Logging and Audit Trail

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| `spatie/laravel-activitylog` | ^4.12 | Incident timeline, audit trail | Auto-logs model events (created, updated, deleted) with before/after diffs. Perfect for incident status transitions, assignment changes, and compliance audit trails. Supports custom log names for separating incident activity from system audit | HIGH |

**Usage in IRMS:**
- Every incident status transition logged with actor, timestamp, old/new values
- Unit assignment and reassignment history
- Dispatcher action audit trail (required for government compliance)
- Custom `incident` log name to separate incident activity from system-level logging

**Why not alternatives:**
- Custom event listeners: Would replicate what this package provides; more code, more bugs
- Laravel Telescope: Development tool, not a production audit log

### Analytics and Charts

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| `chart.js` | ^4.4 | Chart rendering | Lightweight, well-documented, supports all chart types needed (line for response time trends, bar for incident counts, doughnut for type distribution, radar for unit performance) | HIGH |
| `vue-chartjs` | ^5.3 | Vue 3 Chart.js wrapper | Reactive chart components for Vue 3 Composition API. Handles Chart.js lifecycle (create, update, destroy) automatically | HIGH |

**Why not alternatives:**
- D3.js: Overpowered for this use case; steep learning curve, more custom code for standard charts
- Apache ECharts: Heavier bundle, Chinese documentation primary
- Recharts: React-only

### Application Monitoring

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| `laravel/pulse` | ^1.0 | Application performance monitoring | First-party Laravel dashboard. Monitors server CPU/memory/disk, slow queries, queue throughput, cache hit rates, exceptions. Essential for an emergency system where downtime costs lives | MEDIUM |

**Why not alternatives:**
- Laravel Telescope: Development/debugging tool, not production monitoring
- External APM (Datadog, New Relic): Cost; Pulse is free and built-in
- Can add external monitoring later as a supplement, not a replacement

### SMS Integration (Stubbed)

| Technology | Version | Purpose | Why | Confidence |
|------------|---------|---------|-----|------------|
| Laravel Notifications (built-in) | N/A | Notification dispatch abstraction | Use Laravel's notification system with custom channels. Build a `SemaphoreChannel` that implements `Illuminate\Notifications\Channels\Channel`. Allows swapping SMS providers without touching notification logic | HIGH |
| Custom Semaphore SMS Channel | N/A | Philippines-specific SMS | Semaphore is the spec-mandated SMS provider. Build a lightweight custom notification channel using Semaphore's REST API via Laravel HTTP client. Stub with a log driver initially | MEDIUM |

**Why not alternatives:**
- `laravel/vonage-notification-channel`: Vonage (Nexmo) is not the required provider; project specifies Semaphore
- `ridvanbaluyos/semaphore`: Laravel 4 era package, unmaintained. Better to write a small custom channel (~50 lines)

---

## Supporting Libraries (Already Installed, Extended Use)

| Library | Current Version | Extended Use in IRMS |
|---------|----------------|---------------------|
| `@vueuse/core` | ^12.8.2 | `useGeolocation()` for responder GPS, `useWebSocket()` as fallback, `useDebounceFn()` for search, `useLocalStorage()` for preferences |
| `reka-ui` | ^2.6.1 | Headless components for dispatch forms, dropdown menus, dialogs, tabs |
| `lucide-vue-next` | ^0.468.0 | Icons throughout IRMS (incident types, unit status, priority badges) |

---

## Alternatives Considered (Full Table)

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| PostGIS integration | `clickbar/laravel-magellan` ^2.0 | `mstaack/laravel-postgis` | Archived, no Laravel 12 support |
| PostGIS integration | `clickbar/laravel-magellan` ^2.0 | Raw `DB::raw()` | No type safety, migration helpers, or Eloquent casting |
| WebSocket server | `laravel/reverb` ^1.8 | Pusher | Vendor cost, added latency, external dependency for critical infrastructure |
| WebSocket server | `laravel/reverb` ^1.8 | `beyondcode/laravel-websockets` | Abandoned package |
| Map library | `maplibre-gl` ^5.20 | Mapbox GL JS v2+ | Proprietary license since v2 |
| Map library | `maplibre-gl` ^5.20 | Leaflet | DOM-based, no WebGL, can not do 3D or handle marker scale |
| Vue map wrapper | `@indoorequal/vue-maplibre-gl` ^8.4 | `vue-maplibre-gl` (razorness) v5.5 | Stale, no confirmed MapLibre v5 support, no WebGL recovery |
| RBAC | `spatie/laravel-permission` ^7.2 | `JosephSilber/bouncer` | Less ecosystem adoption, fewer resources |
| RBAC | `spatie/laravel-permission` ^7.2 | Custom gates only | Too much code for 4-role system with permission matrix |
| PDF | `spatie/laravel-pdf` ^2.4 | `barryvdh/laravel-dompdf` alone | No driver switching; locked to DomPDF's CSS 2.1 limitations |
| PDF | `spatie/laravel-pdf` ^2.4 | Browsershot alone | Requires Chromium install; overkill for simple reports |
| Charts | `chart.js` + `vue-chartjs` | D3.js | Overpowered, steep learning curve for standard dashboard charts |
| Queue dashboard | `laravel/horizon` ^5.45 | Database queue + custom UI | No real-time metrics, no worker management |
| Audit log | `spatie/laravel-activitylog` ^4.12 | Custom event listeners | Replicates existing package; more maintenance burden |

---

## Installation

```bash
# === PHP Dependencies ===
composer require clickbar/laravel-magellan:^2.0
composer require laravel/reverb:^1.8
composer require laravel/horizon:^5.45
composer require spatie/laravel-permission:^7.2
composer require spatie/laravel-pdf:^2.4
composer require spatie/laravel-activitylog:^4.12
composer require laravel/pulse:^1.0

# === JavaScript Dependencies ===
npm install maplibre-gl@^5.20
npm install @indoorequal/vue-maplibre-gl@^8.4
npm install @turf/turf@^7.3
npm install laravel-echo@^2.3
npm install pusher-js@^8.0
npm install @laravel/echo-vue
npm install chart.js@^4.4
npm install vue-chartjs@^5.3
```

**Do NOT install these together.** Add incrementally per phase:
1. Phase 1 (Foundation): PostgreSQL + PostGIS migration, `laravel-magellan`, `spatie/laravel-permission`, `laravel/reverb`, `laravel-echo`, `pusher-js`, `@laravel/echo-vue`, `laravel/horizon`
2. Phase 2 (Intake): No new packages (uses Phase 1 stack)
3. Phase 3 (Dispatch): `maplibre-gl`, `@indoorequal/vue-maplibre-gl`, `@turf/turf`
4. Phase 4 (Responder): No new packages (uses Phase 3 map stack + Phase 1 real-time)
5. Phase 5 (Analytics): `chart.js`, `vue-chartjs`, `spatie/laravel-activitylog`
6. Phase 6 (Reports): `spatie/laravel-pdf`
7. Phase 7 (Monitoring): `laravel/pulse`

---

## Environment Variables (New)

```env
# PostgreSQL + PostGIS
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=irms
DB_USERNAME=postgres
DB_PASSWORD=secret

# Redis (for Horizon, Reverb, Cache)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Laravel Reverb
REVERB_APP_ID=irms-local
REVERB_APP_KEY=irms-reverb-key
REVERB_APP_SECRET=irms-reverb-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Reverb client-side (Vite)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Queue
QUEUE_CONNECTION=redis

# Mapbox (stubbed initially)
MAPBOX_ACCESS_TOKEN=pk.placeholder
VITE_MAPBOX_ACCESS_TOKEN="${MAPBOX_ACCESS_TOKEN}"

# Semaphore SMS (stubbed initially)
SEMAPHORE_API_KEY=placeholder
SEMAPHORE_SENDER_NAME=IRMS
```

---

## Version Verification Sources

| Package | Verified Version | Source | Date Checked |
|---------|-----------------|--------|--------------|
| `clickbar/laravel-magellan` | 2.0.1 | [Packagist](https://packagist.org/packages/clickbar/laravel-magellan) | 2026-03-12 |
| `laravel/reverb` | 1.8.0 | [Packagist](https://packagist.org/packages/laravel/reverb) | 2026-03-12 |
| `laravel/horizon` | 5.45.3 | [Packagist](https://packagist.org/packages/laravel/horizon) | 2026-03-12 |
| `spatie/laravel-permission` | 7.2.3 | [Packagist](https://packagist.org/packages/spatie/laravel-permission) | 2026-03-12 |
| `spatie/laravel-pdf` | 2.4.0 | [Packagist](https://packagist.org/packages/spatie/laravel-pdf) | 2026-03-12 |
| `spatie/laravel-activitylog` | 4.12.1 | [Packagist](https://packagist.org/packages/spatie/laravel-activitylog) | 2026-03-12 |
| `maplibre-gl` | 5.20.0 | [npm](https://www.npmjs.com/package/maplibre-gl) | 2026-03-12 |
| `@indoorequal/vue-maplibre-gl` | 8.4.2 | [npm](https://www.npmjs.com/package/@indoorequal/vue-maplibre-gl) | 2026-03-12 |
| `@turf/turf` | 7.3.4 | [npm](https://www.npmjs.com/package/@turf/turf) | 2026-03-12 |
| `laravel-echo` | 2.3.0 | [npm](https://www.npmjs.com/package/laravel-echo) | 2026-03-12 |
| `chart.js` | 4.4.x | [npm](https://www.npmjs.com/package/chart.js) | 2026-03-12 |
| `vue-chartjs` | 5.3.3 | [npm](https://www.npmjs.com/package/vue-chartjs) | 2026-03-12 |

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| PostgreSQL + PostGIS + Magellan | HIGH | Verified on Packagist; Laravel 12 compatible; well-documented |
| Laravel Reverb + Echo | HIGH | First-party Laravel package; verified on Packagist; extensive documentation |
| MapLibre GL JS | HIGH | Verified latest v5.20 on npm; active development with frequent releases |
| `@indoorequal/vue-maplibre-gl` | MEDIUM | Verified v8.4.2; confirmed MapLibre v5 support; smaller community than MapLibre itself. If issues arise, fall back to direct MapLibre GL JS usage (composable wrapper ~100 lines) |
| Spatie Laravel Permission | HIGH | v7.2.3 verified; Laravel 12 compatible; 100M+ installs |
| Spatie Laravel PDF | HIGH | v2.4.0 verified; driver-based architecture confirmed; Laravel 12 compatible |
| Horizon + Redis | HIGH | First-party; verified v5.45.3; standard production Laravel pattern |
| Chart.js + vue-chartjs | HIGH | Mature, stable ecosystem; verified versions |
| Laravel Pulse | MEDIUM | First-party but less critical than other components; add when monitoring becomes a priority |
| Custom Semaphore SMS channel | MEDIUM | No maintained Laravel package exists; custom channel approach is standard Laravel pattern but needs validation against Semaphore's current API |
| `@laravel/echo-vue` | MEDIUM | Official first-party package; version not independently verified via npm (403 on fetch). Fallback: use `laravel-echo` directly with manual composable setup |

---

## Gaps to Address in Phase-Specific Research

- **Mapbox API pricing at scale:** Free tier (100K req/month) is likely sufficient for Butuan City volume, but verify geocoding request patterns during intake layer design
- **PostGIS index strategy:** Spatial indexes (GiST) on barangay boundary polygons and unit GPS columns need benchmarking with realistic data volumes
- **Reverb horizontal scaling:** Single-server handles 30K connections; verify if single Reverb instance is sufficient for deployment or if Redis pub/sub scaling is needed
- **PDF template design:** DomPDF's CSS 2.1 limitation may require creative layouts for compliance reports; test early in the reports phase
- **Semaphore SMS API:** Current API documentation and rate limits need verification when integration phase begins
- **MapLibre 3D terrain:** Verify Mapbox terrain tiles work with MapLibre's `addTerrain()` for the 3D dispatch console requirement

---

*Stack research completed: 2026-03-12*
