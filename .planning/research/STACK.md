# Stack Research — IRMS v2.0 FRAS Integration

**Domain:** Emergency incident response (IRMS v1.0) + AI face-recognition event ingestion (FRAS port)
**Researched:** 2026-04-21
**Confidence:** HIGH

> Scope note: This document only covers NEW stack additions and CHANGES required to integrate FRAS v1.0 capabilities into IRMS. The existing IRMS v1.0 stack (Laravel 12, Vue 3 + Inertia v2 + TS strict, Tailwind v4 + Reka UI, Reverb, Fortify, Horizon, Wayfinder, MapLibre GL JS, PostgreSQL/PostGIS via Magellan, Pest 4, vite-plugin-pwa) is treated as fixed except where this milestone explicitly upgrades it.

---

## Recommended Stack

### Core Additions (NEW to IRMS)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| `php-mqtt/laravel-client` | `^1.8.0` | Laravel wrapper around `php-mqtt/client`; subscribes to camera topics (`Rec`, `Ack`, `basic`, `heartbeat`) and publishes enrollment/delete commands | Identical package FRAS v1.0 shipped with. v1.8.0 (released 2026-03-27) declares `illuminate/*: ^10│11│12│13`, so it works both before and after the Laravel upgrade without a second change. Auto-discovered `MQTT` facade + `config/mqtt-client.php` (auto-reconnect, TLS, LWT). Handles long-running `loop(true)` + keep-alive that FRAS's `fras:mqtt-listen` relies on. |
| `intervention/image-laravel` | `^4.0` | Resize enrolment photos to ≤1080p, compress to ≤1 MB JPEG, compute MD5 hash for dedupe/change detection | PROJECT.md v2.0 target says "Intervention Image v3"; FRAS v1.0 actually shipped and validated **v4** (see `/Users/helderdene/fras/composer.json` and v1.0 accomplishments list). v4 is the correct port target. v4 adds `libvips` driver alongside GD/Imagick, uses driver class names (not strings), and is PHP 8.3+ native. GD is sufficient for FRAS's 1080p JPEG workload — no libvips dependency needed. |
| Mosquitto broker (external) | `2.0.x` (LTS) | TCP 1883/8883 MQTT broker on the Laravel host (or adjacent node); cameras connect, Laravel subscribes | Matches FRAS v1.0 production ("Single Linux server running Laravel, Mosquitto MQTT broker, MySQL, and Reverb. Up to 8 cameras"). CDRRMO's camera fleet is small (single-site, ≤8-20 cameras, ≤200 personnel) — Mosquitto's single-thread / no-cluster limits are irrelevant at this scale. Ships with every Linux distro, zero-cost. EMQX/HiveMQ warranted only for 10k+ concurrent clients. |

### Framework Upgrade (Laravel 12 → 13)

| Change | From | To | Notes |
|--------|------|----|-------|
| `laravel/framework` | `^12.0` | `^13.0` | Released 2026-03-17 at Laracon EU. Officially "zero breaking changes" posture — vast majority of apps upgrade in ~10 minutes. |
| PHP minimum | `^8.2` (declared) / 8.4.19 (actual) | `^8.3` | Host already runs 8.4.19; just bump `composer.json` constraint. |
| CSRF middleware class | `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` | `Illuminate\Foundation\Http\Middleware\PreventRequestForgery` | Grep for `VerifyCsrfToken` in `bootstrap/app.php` + tests; replace. Now also does origin-aware verification on top of token-based CSRF. |
| `config/cache.php` | — | add `'serializable_classes' => false` | New L13 security default blocks PHP deserialization gadget chains; explicitly opt in classes you cache-serialize. IRMS uses `database` cache store, so blast radius is small. |
| `laravel/tinker` | `^2.10` | `^3.0` | Required by L13; runtime-equivalent API. |
| `laravel/boost` | `^2.0` | `^2.0` (keep; run `php artisan boost:update` post-upgrade) | Boost's `post-update-cmd` already includes this. |
| `laravel/horizon` | `^5.45` | `^6.0` (if released) else pin `^5.x` that declares L13 compat | Verify at upgrade time — keep Horizon, do not swap. |
| `laravel/reverb` | `^1.8` | `^1.10+` | FRAS already ships `^1.10`; broadcasting surface unchanged. |
| `laravel/fortify` | `^1.30` | `^1.34` | FRAS ships `^1.34`; 2FA/TOTP/Inertia view bindings unchanged. |
| `laravel/wayfinder` | `^0.1.9` | `^0.1.14` | FRAS ships `^0.1.14`; re-run `php artisan wayfinder:generate` after upgrade. |
| `pestphp/pest` | `^4.4` | `^4.4+` (no change) | Pest 4 supports both. |
| `phpunit/phpunit` | (bundled) | `^12` | Pinned by Pest 4. |

**Inertia v2 → v3: OPTIONAL, RECOMMENDED DEFER.** The milestone context flags this as a question to surface. Recommendation: **stay on Inertia v2** for v2.0 FRAS Integration.

- IRMS has 50+ page components, `<Form>` + `useForm` idioms throughout, 123 traced requirements, and 6 Reverb-backed composables (`useDispatchFeed`, `useResponderSession`, etc.) that all pass props through Inertia shared state.
- Inertia v3 renames events (`invalid` → `httpException`, `exception` → `networkError`), removes `Inertia::lazy()` / `LazyProp` in favour of `Inertia::optional()`, drops bundled Axios, removes `router.cancel()` in favour of `cancelAll()`, and requires republishing config + clearing compiled views.
- FRAS is green-field Inertia v3 but uses none of v2's deprecated APIs, so porting FRAS controllers into v2 idioms is straightforward.
- A v3 upgrade is a milestone of its own — combine with a quiet sprint, not with a simultaneous framework bump + MQTT pipeline + MySQL→Postgres port.

### Supporting Libraries (NEW)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `php-mqtt/client` | `^2.0` | Pure-PHP MQTT v3.1.1/v5 client (transitive dep of `php-mqtt/laravel-client`) | Do not require directly; `laravel-client` pulls it. Listed here only so version locking during composer-update is deliberate. |
| Supervisor (process manager) | `4.2.x` (apt/yum) | Keeps `php artisan iot:mqtt-listen`, `reverb:start`, `horizon` alive in production; restart-on-crash | Required for production deploy. FRAS v1.0 "Supervisor production process configs" validated this exact pattern. Dev uses `composer run dev` with `concurrently` instead. |
| `@vueuse/core` | `^12.8.2` (already present) | Reuse `useEventSource`, `useLocalStorage`, etc., for alert-feed UI | Already shipped in IRMS. No net-new frontend package is strictly needed for the FRAS port. |

### Development Tools / Operational

| Tool | Purpose | Notes |
|------|---------|-------|
| `mosquitto_pub` / `mosquitto_sub` CLI | Inject simulated camera MQTT payloads during local dev | Install alongside broker. Pair with a `database/seeders/FakeCameraPayloadSeeder.php` that pushes canned `Rec`/`heartbeat` frames for offline testing. |
| Updated `composer run dev` | Add `php artisan iot:mqtt-listen` as a 6th concurrently process, alongside existing `server,reverb,horizon,logs,vite` | FRAS pattern is exact: `concurrently ... "php artisan fras:mqtt-listen" --names=...,mqtt`. Pick the command name deliberately (e.g. `iot:mqtt-listen` to match IRMS's existing "IoT intake channel" vocabulary rather than the FRAS-branded name). |
| `php artisan tinker --execute` for payload replay | Drop a JSON payload into `RecPushHandler` without a live broker | FRAS v1.0 used this pattern for firmware-quirk regression (`personName` vs `persionName`). |

---

## Installation

```bash
# === Composer (Laravel 12 → 13 + FRAS packages) ===
# Bump PHP minimum in composer.json: "php": "^8.3"
composer require laravel/framework:^13.0 \
    laravel/tinker:^3.0 \
    laravel/horizon:^6.0 \
    laravel/reverb:^1.10 \
    laravel/fortify:^1.34 \
    laravel/wayfinder:^0.1.14 \
    php-mqtt/laravel-client:^1.8 \
    intervention/image-laravel:^4.0

# Publish new config files
php artisan vendor:publish --provider="PhpMqtt\Client\MqttClientServiceProvider" --tag="config"
php artisan vendor:publish --provider="Intervention\Image\Laravel\ServiceProvider"

# Regenerate typed routes
php artisan wayfinder:generate

# === NPM (no net-new packages needed) ===
# IRMS already has laravel-echo, pusher-js, maplibre-gl, and all shared UI deps.
# DO NOT install mapbox-gl — IRMS uses MapLibre GL JS.
npm install   # refresh lockfile only

# === System (production) ===
sudo apt install mosquitto mosquitto-clients supervisor
sudo systemctl enable --now mosquitto
# Supervisor conf files for: reverb, horizon, mqtt-listener (one process each)
```

`.env` additions (mirror FRAS, scoped to IRMS naming):

```env
# MQTT
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
MQTT_CLIENT_ID=irms-dispatch-01
MQTT_CLEAN_SESSION=true
MQTT_AUTH_USERNAME=irms
MQTT_AUTH_PASSWORD=            # mosquitto password_file entry
MQTT_AUTO_RECONNECT_ENABLED=true
MQTT_AUTO_RECONNECT_MAX_RECONNECT_ATTEMPTS=5

# TLS (off on internal LAN v1, on for any routed subnet)
MQTT_TLS_ENABLED=false
MQTT_TLS_CA_FILE=
MQTT_TLS_VERIFY_PEER=true

# Image driver (match FRAS v1.0)
IMAGE_DRIVER=gd

# Camera photo storage — separate disk, publicly reachable from cameras
FRAS_PHOTO_DISK=fras_photos
FRAS_PHOTO_URL=https://irms.test/fras/photos   # must be LAN-reachable from camera subnet
FRAS_EVENT_DISK=fras_events
```

`config/filesystems.php` additions:

```php
'disks' => [
    // ... existing disks
    'fras_photos' => [
        'driver' => 'local',
        'root' => storage_path('app/public/fras/photos'),
        'url' => env('FRAS_PHOTO_URL'),
        'visibility' => 'public',
        'throw' => false,
    ],
    'fras_events' => [
        'driver' => 'local',
        'root' => storage_path('app/fras/events'),   // private — served via auth-gated controller
        'visibility' => 'private',
        'throw' => false,
    ],
],
```

---

## Integration Points with Existing IRMS v1.0 Stack

| FRAS Capability | Plugs Into (existing IRMS) | Adaptation Required |
|-----------------|----------------------------|---------------------|
| `RecPush` recognition event | v1.0 IoT intake channel (`routes/web.php` IoT webhook + `Incident::channel='iot'` + HMAC-SHA256 gate) | Recognition events become "IoT-channel incidents" with a camera-match subtype. Reuse the bilingual keyword classifier for priority, but add short-circuit: block-list match → P1 automatically. No new channel row in the channel monitor, no new intake UI column. |
| Camera pins on map | Existing MapLibre GL JS dispatch console (`useDispatchFeed` composable, WebGL marker layers) | Add a **cameras** source + WebGL marker layer alongside incidents/units layers. Reuse same dark-mode Mapbox style (already switched in commit `ea52f22`). Do NOT import `mapbox-gl`. |
| `RecognitionAlert` broadcast | Existing Laravel Reverb + 6 channel-authorised events + `useEcho` composables | Add a 7th broadcast event (e.g. `RecognitionAlertBroadcast`) on a private `dispatch.cameras` or `intake.recognition` channel. Gate through existing `routes/channels.php` auth closures (dispatcher/operator/supervisor roles). |
| `EditPersonsNew` / `DeletePersons` enrollment jobs | Existing Horizon queue workers | Register a new queue (`cameras`) in `config/horizon.php` so enrollment batches don't starve dispatch jobs. Apply `WithoutOverlapping` middleware keyed by `camera_id` — mandatory per FRAS constraint (one batch in-flight per camera). |
| Heartbeat / offline detection | Existing `routes/console.php` schedule (analytics jobs already run there) | Register a ≥30 s cadence `cameras:mark-offline` command (ported from FRAS v1.0 Phase 2). Broadcast `CameraStatusChanged` to map via Reverb. |
| Photo + scene image storage | Existing `storage/app/public` + `vite-plugin-pwa` public assets | Photos on **public** disk (cameras must HTTP-fetch them). Face crops + scene images on **private** disk, served through an authorised `ImageController::show()` that streams via `response()->file()` for operator UIs. Retention: face crops 90 d, scene images 30 d, via existing scheduled cleanup pattern. |
| Personnel CRUD (admin) | Existing admin controllers + Reka UI data-table + Inertia Form + Wayfinder | Use exact same structure as `AdminUnitController`. No new UI primitive required. |

---

## PostgreSQL Port of FRAS MySQL Schema

Port the 4 FRAS v1.0 tables (`cameras`, `personnel`, `recognition_events`, `camera_enrollments`) using these explicit rewrites. `pgloader` is the recommended bulk tool if migrating live data, but for IRMS we are porting **schema + empty tables** because FRAS stays standalone.

| MySQL Construct (FRAS) | PostgreSQL Equivalent (IRMS) | Laravel Schema Builder | Notes |
|------------------------|-------------------------------|------------------------|-------|
| `INT AUTO_INCREMENT PRIMARY KEY` | `BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY` | `$table->id()` (→ `bigIncrements`) | Default. IRMS v1.0 already uses `bigIncrements` across 7 models — stay consistent. Avoid `SERIAL` (legacy; sequence + default-expression hack). |
| `TINYINT(1)` booleans | `BOOLEAN` | `$table->boolean('is_online')` | **Application change:** anywhere FRAS compared `=== 1` / `=== 0`, must become `=== true` / `=== false`. Pest tests must assert `true`, not `1`. Eloquent's `boolean` cast masks this for model attributes but NOT for raw `DB::select` rows. |
| `DATETIME(6)` (microsecond-precision timestamps used for MQTT event correlation) | `TIMESTAMP(6) WITHOUT TIME ZONE` or `TIMESTAMPTZ(6)` | `$table->timestamp('received_at', 6)` or `$table->timestampTz('received_at', 6)` | Use `TIMESTAMPTZ` for anything operator-visible (matches IRMS v1.0 convention — dispatch timeline is timezone-aware). For internal camera-reported `capturedAt` (camera clock), store as **`TIMESTAMP` without timezone + explicit `camera_clock_skew_ms` column** rather than pretending camera timestamps are UTC. |
| `JSON` (MySQL's native JSON, not indexed by default) | `JSONB` (indexable via GIN) | `$table->jsonb('raw_payload')` | Use **`jsonb`** not `json`. FRAS stored raw MQTT payloads for replay/debugging — with `jsonb` IRMS can add a GIN index on `raw_payload->>'cameraDeviceId'` for efficient reverse lookup without a separate column. Eloquent's `array` cast works identically. |
| `VARCHAR(n)` | `VARCHAR(n)` or `TEXT` | Same — but prefer `TEXT` for unbounded strings | PostgreSQL has no storage cost penalty for `TEXT` vs `VARCHAR(n)`; many FRAS fields (e.g. `person_name`) should become `TEXT`. |
| `ENUM('allow','block','refused')` | `VARCHAR` + `CHECK (…)` constraint, OR dedicated Postgres ENUM type, OR **app-level PHP enum** | `$table->string('person_type')` + app enum | IRMS v1.0 convention uses PHP 8.1 enums (`IncidentOutcome`, `ResourceType`, `AlertSeverity`) backed by `string` columns. Mirror this — do NOT port MySQL ENUMs directly. |
| `TEXT utf8mb4_unicode_ci` collation | `TEXT` (default UTF-8) | `$table->text()` | Postgres is UTF-8 everywhere by default. No collation attribute needed. Case-insensitive search → use `ILIKE` (available everywhere) or the `citext` extension — not `LOWER()` + function index. |
| `UNSIGNED INT/BIGINT` | No equivalent — just use `BIGINT` | `$table->unsignedBigInteger('camera_id')` | Laravel's builder emits plain `bigint` on Postgres; value is signed but 2^63 range is plenty. Keep the `unsignedBigInteger()` builder call for MySQL-portability of migration files. |
| `created_at` / `updated_at` | `TIMESTAMP(0) WITHOUT TIME ZONE` (Laravel default) | `$table->timestamps()` | No change — keep Laravel's standard. |
| Spatial: camera lat/lng as two `DECIMAL(10,7)` columns in FRAS | Single `POINT GEOGRAPHY` column via clickbar/laravel-magellan | `$table->geography('location', 'POINT', 4326)` | **Upgrade opportunity.** IRMS v1.0 uses Magellan for units/incidents/barangays. Port cameras into Magellan on day 1 so `ST_DWithin(camera.location, incident.location, 500)` becomes a single query for "cameras near this incident". |
| `FULLTEXT` index on `person_name` (if present) | `tsvector` + `GIN` index | `$table->index(...)` + raw `DB::statement` or use `laravel/scout` | Low priority — 200 personnel at v1 scale, `ILIKE '%name%'` is fine. |
| MySQL's implicit `ON UPDATE CURRENT_TIMESTAMP` | Not available in Postgres — Laravel handles it in PHP | (no change needed) | Eloquent updates `updated_at` via the `HasTimestamps` trait regardless of DB. |
| `BLOB` (if any) | `BYTEA` | `$table->binary('thumbnail')` | FRAS stored images on disk, not in DB — unlikely to hit this. |

**Migration file strategy:** write **new** Postgres-native migrations (timestamps 2026-04-2x) rather than porting FRAS migration files verbatim. Keeps `composer run ci:check` clean and lets PostGIS decisions be made per-column.

**Test DB:** IRMS v1.0 uses **SQLite in-memory** for Pest feature tests (`RefreshDatabase`). This is a real porting risk — SQLite has no `JSONB`, no `GEOGRAPHY`, no `ILIKE`. Two options:

1. (Recommended) Switch Pest suite to Postgres via `testing` connection in `phpunit.xml`, running against a transient Postgres container (or Herd's Postgres service). Matches v1.0 Magellan tests.
2. Mark MQTT/spatial tests with `uses(RefreshDatabase::class)->group('postgres')` and skip on SQLite — messy, not recommended.

v1.0 already chose option (1) for Magellan work — continue.

---

## MQTT Broker Choice

| Broker | Verdict | Rationale |
|--------|---------|-----------|
| **Mosquitto 2.0.x** | **RECOMMENDED** | FRAS v1.0 validated this for identical scale (8 cameras, QoS 0). Shipped in Ubuntu/Debian default repos. `mosquitto.conf` + `password_file` + `acl_file` gets you auth + per-topic ACL in ~20 lines. Single-threaded and no clustering is not a concern at CDRRMO's single-site scale. |
| EMQX 5.x | Not warranted | Shines at 100k-100M concurrent clients. Over-provisioned for ≤20 cameras. Adds Erlang runtime to the ops surface. Revisit only if CDRRMO ever federates with other LGUs (ruled out in PROJECT.md "Out of Scope"). |
| HiveMQ 4.x (Community or Commercial) | Not warranted | Commercial enterprise broker. No licensing budget for single-LGU deploy; Community Edition is fine but offers no advantage over Mosquitto at this scale. |
| Cloud-managed (HiveMQ Cloud, EMQX Cloud, AWS IoT Core) | NOT SUITABLE | Constraint: "Camera subnet must reach the Laravel server … No NAT translation awareness on cameras" (PROJECT.md line 234). Cameras can't egress to the public internet for MQTT in the CDRRMO LAN. Broker must be on the LAN. |

**Hosting model:** same Droplet as Laravel (single DigitalOcean box per v1.0 deployment target). Mosquitto's footprint is <50 MB RSS; colocating is fine.

**Auth:** `password_file` with bcrypt (via `mosquitto_passwd`). One account per camera + one for Laravel. ACL file: cameras publish only to their own `cameras/{deviceId}/#` subtree, Laravel subscribes to all and publishes only to `cameras/+/commands/#`.

**TLS decision:** v2.0 starts with plain MQTT (port 1883) on the LAN, mirroring FRAS v1.0's "MQTT TLS (mqtts://) — plain MQTT on internal network only" out-of-scope decision. BUT: because IRMS is a government (CDRRMO) system handling citizen PII (recognition events tied to persons), **flag TLS for v2.1**. Mosquitto supports TLS with self-signed CA in 5 lines of config; `php-mqtt/laravel-client` already exposes `MQTT_TLS_*` env vars.

---

## Queue Worker / Supervisor Configuration

FRAS ships three long-running processes; IRMS already runs two (Reverb, Horizon). Add one (MQTT listener) and adjust Horizon to include a `cameras` queue.

**Supervisor confs** (`/etc/supervisor/conf.d/`):

```ini
; irms-reverb.conf  (already exists from v1.0 — unchanged)
[program:irms-reverb]
command=php /srv/irms/current/artisan reverb:start --host=0.0.0.0 --port=6001
autostart=true
autorestart=true
user=www-data
stopwaitsecs=10

; irms-horizon.conf  (already exists — update horizon.php for cameras queue)
[program:irms-horizon]
command=php /srv/irms/current/artisan horizon
autostart=true
autorestart=true
user=www-data
stopwaitsecs=3600
stopsignal=TERM

; irms-mqtt.conf  (NEW — ports fras:mqtt-listen)
[program:irms-mqtt]
command=php /srv/irms/current/artisan iot:mqtt-listen
autostart=true
autorestart=true
user=www-data
stopwaitsecs=10
stdout_logfile=/var/log/irms/mqtt.log
stderr_logfile=/var/log/irms/mqtt.err.log
stopasgroup=true
killasgroup=true
```

**`config/horizon.php` addition** (new queue, keeps v1.0 dispatch queues untouched):

```php
'production' => [
    'supervisor-dispatch' => [
        'connection' => 'redis',
        'queue' => ['default', 'broadcasts'],   // existing
        'maxProcesses' => 4,
    ],
    'supervisor-cameras' => [                     // NEW
        'connection' => 'redis',
        'queue' => ['cameras'],                   // enrollment, delete-sync, photo-reprocess
        'maxProcesses' => 2,
        'balance' => 'simple',
        'maxTime' => 0,
    ],
],
```

**Listener command pattern** (mirrors FRAS): a `Command` that opens an MQTT connection, subscribes to 4 topics, routes messages to handler classes via `TopicRouter`, and calls `$mqtt->loop(true)`. MUST dispatch **every handler** to the `cameras` queue — do NOT process inline. FRAS learned this the hard way (heartbeat-blocking-RecPush); keep the listener thin.

**Graceful reload on deploy:** `supervisorctl restart irms-mqtt` + `supervisorctl restart irms-horizon` + `php artisan reverb:restart` in the deploy script; `--stopwaitsecs=10` lets in-flight `loop()` tick close cleanly.

---

## Image Storage: Local vs S3, Public vs Signed

**Verdict:** **local disk, two disks** (one public, one private), **no S3**, **no signed URLs** for camera photos.

| Asset | Disk | Visibility | Rationale |
|-------|------|------------|-----------|
| Enrollment photos (`personnel.photo_path`) | `fras_photos` (local, `storage/app/public/fras/photos`, symlinked via `php artisan storage:link`) | **Public (no auth, direct URL)** | Cameras fetch photos via HTTP `picURI` — must be network-reachable from camera subnet with no AWS SDK, no signature algorithm, no clock skew tolerance. Public URL is the only option. FRAS v1.0 key decision: "Public personnel photos (no auth) — Camera must fetch via HTTP URL; accepted trade-off for v1 — Validated". |
| Face crops (from `RecPush` — cropped match face) | `fras_events` (local, `storage/app/fras/events/faces/YYYY/MM/DD/`) | **Private**, served via `Route::get('/fras/events/{event}/face', [ImageController::class, 'face'])->middleware('auth')` | Contains PII; only dispatchers/operators view. `response()->file()` streams with `Cache-Control: private`. No temporary URLs needed. |
| Scene images (from `RecPush` — full camera frame) | `fras_events/scenes/YYYY/MM/DD/` | **Private**, same auth-gated pattern | Up to 2 MB. Retention: 30 days (scheduled cleanup). |

**Why not S3?**
- Cameras can't reach S3 (same LAN-only constraint as MQTT broker). You'd have to proxy via Laravel, erasing the cost/scale argument for S3.
- Single-site deployment has ≤20 cameras × ~200 events/day × ~3 MB ≈ 12 GB/month peak, well below Droplet disk sizing.
- Retention cleanup is trivial with `Storage::disk('fras_events')->delete(…)` in a scheduled job.

**Why not signed URLs for camera photos?**
- Cameras don't parse query-string-signed URLs; they treat `picURI` as an opaque blob to fetch.
- `Storage::temporaryUrl()` requires `S3` / S3-compatible driver; local disk signed URLs exist via signed-route middleware but cameras can't regenerate them on refresh.

**Future migration path:** if CDRRMO ever expands beyond one site (ruled out in Out of Scope, but possible), switch `fras_photos` to `public`-visibility S3 bucket behind CloudFront — single `.env` change, no code change. Keep `fras_events` local-private forever (PII locality).

---

## What NOT to Add / Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `mapbox-gl` (the GL JS SDK) | IRMS v1.0 committed to MapLibre GL JS with Mapbox *basemap/Directions/geocoding* APIs — they're compatible at the tile/GeoJSON layer. Adding `mapbox-gl` alongside `maplibre-gl` doubles bundle size (~800 KB gzipped), splits Mapbox style loading logic, and breaks commit `ea52f22`'s "dark-mode map to custom Mapbox style" consolidation. FRAS used Mapbox GL JS because it had no MapLibre precedent; IRMS does. | Reuse existing `maplibre-gl` + existing custom Mapbox dark style. Render camera markers as another WebGL source on the same Map instance. |
| `laravel-echo-server` / hosted Pusher | IRMS v1.0 already runs **Laravel Reverb** (the first-party Pusher-protocol server). Switching to hosted Pusher adds ~$50/mo for nothing. | Keep Reverb; add FRAS recognition channels to it. |
| Inertia v3 in this milestone | ~80 components to audit for `Inertia::lazy()`/`LazyProp`/event-name drift while simultaneously porting MQTT + MySQL→Postgres + framework upgrade. Too many axes of change at once. | Stay on Inertia v2; schedule v3 as its own milestone after v2.0 ships. |
| `mapbox-gl-directions` / re-introducing Directions SDK on frontend | IRMS v1.0 already has Mapbox Directions **backend-side** (commit `5b4967e`) + solid route polylines (`cfe2c46`). FRAS didn't need routing at all — cameras have fixed locations. | No directions needed for FRAS port; any route rendering stays in existing responder nav code. |
| MQTT v5 features (shared subscriptions, user properties, AUTH packets) | Cameras speak **v3.1.1 only** (PROJECT.md constraint line 234). v5 would require firmware upgrade CDRRMO can't apply. | Stay on v3.1.1 in `config/mqtt-client.php` (`'protocol' => 0x04`). |
| `spatie/laravel-permission` | IRMS v1.0 decision: "Custom role enum + gates (not Spatie) … gates remained legible". FRAS is single-admin and has no permission model to port. | Extend existing 9-gate enum with `manage_cameras`, `manage_personnel`, `view_recognition_events` — same pattern. |
| AWS IoT Core, Azure IoT Hub, Google Pub/Sub | Cameras can't egress from LAN. See Broker section above. | Mosquitto on the LAN. |
| Intervention Image v2 | End of life; PHP 8.0+ incompatible in places; static facade baggage. FRAS chose v4; port v4. | `intervention/image-laravel` v4. |
| `SERIAL` (legacy Postgres auto-increment) | Leaky abstraction (sequence + default expression), ownership-transfer issues when dropping tables. | `$table->id()` (→ `bigIncrements`, emits `GENERATED ALWAYS AS IDENTITY` on Postgres via Laravel's builder). |
| `Storage::temporaryUrl()` for camera photos | Cameras can't refresh expired URLs; signed-URL dance fails on clock skew + no SDK parsing. | Public disk, direct `Storage::url()` returning LAN-reachable HTTPS. |

---

## Version Compatibility Matrix

| Package | Version | Compatible With | Verified |
|---------|---------|-----------------|----------|
| `laravel/framework` | `^13.0` | PHP 8.3/8.4, Pest 4, PHPUnit 12, Reverb 1.10+, Horizon 6, Fortify 1.34+ | HIGH (laravel.com/docs/13.x/upgrade, Boost L13 guidelines) |
| `php-mqtt/laravel-client` | `^1.8.0` | `illuminate/*: ^10│11│12│13`, `php-mqtt/client: ^2.0`, PHP 8.0+ | HIGH (Packagist fetched 2026-04-21: latest 1.8.0 released 2026-03-27) |
| `intervention/image-laravel` | `^4.0` | PHP 8.1+, GD/Imagick/libvips; Laravel 10-13 | HIGH (FRAS v1.0 running + Context7 /intervention/image-laravel) |
| `laravel/reverb` | `^1.10` | Laravel 12/13, PHP 8.2+, Echo 2.x client; `pusher-js` 8.x | HIGH (FRAS v1.0 running) |
| `inertiajs/inertia-laravel` | `^2.0` (keep) | Laravel 12/13, `@inertiajs/vue3: ^2.x`; both Wayfinder 0.1.9 and 0.1.14 | HIGH (IRMS v1.0 running) |
| `laravel/horizon` | `^6.0` (verify at upgrade) or `^5.45` | Redis 6+, L13 | MEDIUM — pin at upgrade time; Horizon historically cuts a major for each L-major. |
| `clickbar/laravel-magellan` | `^2.0` (keep) | Laravel 12/13, PostgreSQL 13+, PostGIS 3.x | MEDIUM — verify L13 compat in changelog before bumping (IRMS v1.0 shipped `^2.0`). |
| Mosquitto | 2.0.x | MQTT v3.1.1 + v5, TLS 1.2+, Linux/macOS | HIGH (FRAS v1.0 production) |
| Supervisor | 4.2+ | Any Linux | HIGH |

**Flag for upgrade time:** confirm `laravel/horizon` and `clickbar/laravel-magellan` both declare `^13.0` in their current releases' `composer.json`. If either hasn't cut an L13 release, pin to existing compatible minor + wait 1-2 weeks or contribute a compat PR.

---

## Stack Patterns by Variant

**If CDRRMO enables MQTT TLS in v2.1:**
- Flip `MQTT_TLS_ENABLED=true`, provision self-signed CA with `mosquitto` + `openssl`, push CA cert to every camera via their admin UI.
- `php-mqtt/laravel-client` needs `MQTT_TLS_CA_FILE=/etc/ssl/certs/mosquitto-ca.crt`, `MQTT_TLS_VERIFY_PEER=true`.
- No Laravel-side code change.

**If camera fleet grows past ~50:**
- Mosquitto is still fine up to ~1000 concurrent clients.
- Horizon `supervisor-cameras` → bump `maxProcesses` from 2 to 4.
- Consider a dedicated Horizon supervisor with `balance=auto` + `minProcesses=2, maxProcesses=8` for enrollment bursts.

**If we later upgrade to Inertia v3:**
- Separate milestone. Execute in order: (1) swap `router.on('invalid')` → `httpException` + `exception` → `networkError`, (2) search & replace `Inertia::lazy()` → `Inertia::optional()`, (3) add `@inertiajs/vite` plugin, (4) republish Inertia config, (5) clear compiled views. FRAS code can serve as a reference implementation for v3 idioms.

---

## Sources

- `/php-mqtt/laravel-client` (Context7) — install, config, auto-reconnect, TLS, LWT, subscriber loop — **HIGH** (2026-04-21 fetch)
- [Packagist — php-mqtt/laravel-client](https://packagist.org/packages/php-mqtt/laravel-client) — verified v1.8.0 (2026-03-27) requires `illuminate/*: ^10│11│12│13` — **HIGH**
- `/websites/laravel_13_x` (Context7) — upgrade guide, `PreventRequestForgery` rename, service-provider registration, `serializable_classes` cache default — **HIGH** (2026-04-21 fetch)
- [Laravel 13 release notes](https://laravel.com/docs/13.x/releases) — "minimal breaking changes" posture — **HIGH**
- [hafiz.dev — Laravel 12 to 13 upgrade: zero breaking changes doesn't mean zero work](https://hafiz.dev/blog/laravel-12-to-13-upgrade-guide) — **MEDIUM** (corroborates Laravel docs)
- [Laravel 13 new features (pola5h.github.io, March 2026)](https://pola5h.github.io/blog/laravel-13-new-features/) — PHP 8.3 floor, `serializable_classes` default — **MEDIUM**
- `/intervention/image-laravel` (Context7) — install, driver class names, facade usage, Laravel integration — **HIGH**
- [intervention.io v3 upgrade guide](https://image.intervention.io/v3/getting-started/upgrade) — v2→v3 breaking changes (v4 continues same patterns) — **HIGH**
- `/websites/inertiajs_v3` (Context7) — v2→v3 upgrade: event renames, `lazy`→`optional`, axios removal, `router.cancelAll()` — **HIGH** (informs decision to defer)
- [Laravel 13.x filesystem docs](https://laravel.com/docs/13.x/filesystem) — public vs private disk, `temporaryUrl()` limitations on local driver — **HIGH**
- [Laravel 13.x migrations](https://laravel.com/docs/13.x/migrations) — `bigIncrements`, `jsonb`, `boolean` Postgres mappings — **HIGH**
- [Laravel Cloud — Migrate MySQL to PostgreSQL](https://cloud.laravel.com/docs/knowledge-base/migrating-mysql-to-postgresql) — type mapping guidance (`AUTO_INCREMENT`→`IDENTITY`, `TINYINT(1)`→`BOOLEAN`, JSON handling) — **HIGH**
- [AI2SQL — MySQL to PostgreSQL syntax (2026)](https://builder.ai2sql.io/blog/mysql-to-postgresql-migration) — identifier quoting, ENUM handling, `SERIAL` vs `GENERATED AS IDENTITY` — **MEDIUM**
- [EMQX — Open-source MQTT broker comparison](https://www.emqx.com/en/blog/a-comprehensive-comparison-of-open-source-mqtt-brokers-in-2023) — Mosquitto/EMQX/HiveMQ tradeoffs at scale — **MEDIUM**
- [manubes — MQTT broker comparison](https://www.manubes.com/mqtt-brokers-comparison/) — Mosquitto's single-thread/no-cluster limits vs scale — **MEDIUM**
- [codegive — Laravel MQTT production patterns](https://codegive.com/blog/php_mqtt_laravel_client.php) — listener + queue dispatch pattern, Supervisor conf — **LOW** (corroborating only)
- [Laravel 13.x queues / Horizon](https://laravel.com/docs/13.x/horizon) — supervisor definitions, named queues — **HIGH**
- [greeden blog — Laravel S3 + signed URLs practical guide](https://blog.greeden.me/en/2026/03/04/complete-practical-guide-laravel-file-upload-delivery-storage-s3-presigned-urls-image-optimization-pdfs-video-virus-scanning-authorization-caching-and-accessible-alternative-t/) — signed URL vs public disk decision framework — **MEDIUM**
- **FRAS v1.0 codebase** (`/Users/helderdene/fras/composer.json`, `/Users/helderdene/fras/.planning/PROJECT.md`, `/Users/helderdene/fras/.planning/MILESTONES.md`) — reference implementation: exact package versions shipped, validated constraints, Supervisor process list — **HIGH**
- **IRMS v1.0 codebase** (`/Users/helderdene/IRMS/composer.json`, `/Users/helderdene/IRMS/.planning/PROJECT.md`, `/Users/helderdene/IRMS/CLAUDE.md`) — existing stack invariants — **HIGH**

---
*Stack research for: IRMS v2.0 FRAS Integration*
*Researched: 2026-04-21*
