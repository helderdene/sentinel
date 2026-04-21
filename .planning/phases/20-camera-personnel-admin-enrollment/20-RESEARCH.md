# Phase 20: Camera + Personnel Admin + Enrollment - Research

**Researched:** 2026-04-21
**Domain:** Laravel 13 admin CRUD + MQTT publish-ACK correlation + Intervention Image v4 + mapbox-gl overlay + Echo Vue broadcast + Horizon job mutex + scheduled commands
**Confidence:** HIGH (most surface area is verbatim FRAS port with published library contracts)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Map stack — milestone scope override**
- **D-01: v2.0 milestone Scope Decision "`mapbox-gl` explicitly rejected" is REVERTED.** `mapbox-gl` is retained across the IRMS codebase. User-directed decision 2026-04-21 during phase-20 discussion. Rationale: v1.0 already ships `mapbox-gl ^3.20.0`; a full migration to `maplibre-gl` is scope creep the milestone does not need.
- **D-02:** Phase 20 SC3 "CI bundle-check fails if any code path imports mapbox-gl" is **DROPPED**. ROADMAP.md §Phase 20 SC3 + REQUIREMENTS.md §Scope Decisions must be updated.
- **D-03:** Camera picker implemented with `mapbox-gl` mirroring `resources/js/components/intake/LocationMapPicker.vue` verbatim: `BUTUAN_CENTER = [125.5406, 8.9475]`, `BUTUAN_ZOOM = 13`, Mapbox Studio styles (`helderdene/cmmq06eqr005j01skbwodfq08` light / `cmns77fv5004e01sr9hh5bcqq` dark), `reverseGeocode` via `fetch` to `api.mapbox.com/geocoding/v5/mapbox.places/`.
- **D-04:** Cameras dispatch-map layer added to `useDispatchMap.ts` — same `mapbox-gl` Map instance, add `cameras` GeoJSON source + symbol layer alongside existing incidents/units layers. Toggle via new `cameras-visible` local state in `DispatchConsole.vue`.
- **D-05:** No map library migration work in Phase 20.

**Admin page structure**
- **D-06:** Mirror IRMS v1.0 Unit admin pattern verbatim — controllers, pages, requests.
- **D-07:** `routes/admin.php` extensions: `Route::resource('cameras'...)`, `recommission` POST, same for personnel, plus signed photo route.
- **D-08:** Table columns per resource; matches `Units.vue` visual language.
- **D-09:** Camera display-ID auto-sequencing in `AdminCameraController::store()` mirrors `AdminUnitController::store` lines 67-80 verbatim (regex-based `MAX+1` → `CAM-%02d`).
- **D-10:** `PersonnelForm.vue` embeds `<EnrollmentProgressPanel>` on edit mode only — Echo-subscribed to `fras.enrollments` private channel.

**Enrollment service + jobs + observer**
- **D-11:** `App\Services\CameraEnrollmentService` ported from FRAS verbatim with IRMS tweaks (UUID FKs, config reads `config('fras.*')` not `hds.*`, broadcasts `EnrollmentProgressed` per transition).
- **D-12:** `App\Jobs\EnrollPersonnelBatch` on `fras` Horizon queue. `WithoutOverlapping('enrollment-camera-{id}')->releaseAfter(30)->expireAfter(300)`. Public ctor: `Camera $camera, array $personnelIds`.
- **D-13:** `App\Observers\PersonnelObserver` — `saved` fires `CameraEnrollmentService::enrollPersonnel($personnel)` ONLY if `wasChanged(['photo_hash', 'category'])`. `deleted` fires `deleteFromAllCameras`.
- **D-14:** Admin creates new Camera → trigger `CameraEnrollmentService::enrollAllToCamera($camera)` in store.

**ACK correlation — filling in Phase 19 AckHandler**
- **D-15:** `CameraEnrollmentService::upsertBatch` writes Redis correlation `enrollment-ack:{camera_id}:{messageId}` with TTL = `config('fras.enrollment.ack_timeout_minutes') * 60`. MessageId format: `'EditPersonsNew'.now()->format('Y-m-d\\TH:i:s').'_'.Str::random(6)`.
- **D-16:** Phase 20 fills `AckHandler::handle()` body — parse topic + payload, lookup camera, read cache, update `camera_enrollments` rows, broadcast `EnrollmentProgressed`, `Cache::forget($key)`.
- **D-17:** Per-error-code retry policy: 464/465/466 transient → `$this->release(60)` up to `$tries = 3`; 461/463/467/468/474/478/default terminal → `status=failed`.

**Photo processing + two-namespace URL scheme**
- **D-18:** `App\Services\FrasPhotoProcessor` ported from FRAS PhotoProcessor with Intervention Image v4 API: `Image::decodePath()` → `orient()` → `scaleDown(width: 1080, height: 1080)` → `encode(new JpegEncoder(quality: 85))`. Quality-degradation loop while bytes > max_size and quality > 40. MD5 hash. Store on `fras_photos` disk at `personnel/{personnel->id}.jpg`.
- **D-19:** New `fras_photos` disk in `config/filesystems.php` — private, root `storage_path('app/private/fras_photos')`, env var `FRAS_PHOTO_DISK` reserved.
- **D-20:** **NEW migration required:** `personnel.photo_access_token` uuid column, nullable, unique. Rotated on every photo re-upload via `Str::uuid()`. Migration timestamp: `2026_04_2x_add_photo_access_token_to_personnel_table.php`.
- **D-21:** Public camera-fetch URL: `/fras/photo/{token}` served by `FrasPhotoAccessController@show` — no auth, lookup by token, require at least one pending/syncing enrollment, DPA preamble log, stream from `fras_photos` disk.
- **D-22:** Operator-facing URL: `/admin/personnel/{personnel}/photo` served by `AdminPersonnelPhotoController@show` — `role:operator,supervisor,admin` (responders + dispatchers blocked), uses `signed` middleware, 5-minute TTL via `URL::temporarySignedRoute`.
- **D-23:** URL revocation sequence — new photo rotates token → prior URL 404s; all enrollments ACK → route-level check fails → URL 404s.

**Camera watchdog + status state machine**
- **D-24:** Keep Phase 18 column name `last_seen_at`. Update SC5 text to match (was `last_heartbeat_at`).
- **D-25:** `App\Console\Commands\CameraWatchdogCommand` (`irms:camera-watchdog`) scheduled `->everyMinute()`. Gap ≤ `config('fras.cameras.degraded_gap_s', 30)` → online; ≤ `offline_gap_s (90)` → degraded; else offline. On state change → update + broadcast `CameraStatusChanged`.
- **D-26:** Map marker render — online green, degraded amber, offline gray (consistent with existing `STATUS_COLORS`).
- **D-27:** Phase 19's `OnlineOfflineHandler` writes only `online`/`offline` on direct MQTT Online/Offline. Phase 20's watchdog is the ONLY writer of `degraded`. Last-writer-wins at column level.

**Camera deletion rule (SC1)**
- **D-28:** `AdminCameraController::destroy()` pre-check blocks soft-decommission when pending/syncing enrollments exist — returns 422 with operator-friendly copy.
- **D-29:** Hard-delete NOT exposed in UI. Admin clicks "Decommission" → sets `decommissioned_at`, scoped out via `scopeActive()`. `recommission` clears `decommissioned_at`.
- **D-30:** Phase 18 D-22 `ON DELETE CASCADE` on `camera_enrollments.camera_id` FK retained.

**BOLO expiry auto-unenroll**
- **D-31:** `App\Console\Commands\PersonnelExpireSweepCommand` (`irms:personnel-expire-sweep`) scheduled `->hourly()`.
- **D-32:** Iterate expired personnel → `deleteFromAllCameras()` → `decommissioned_at = now()` → mark enrollments `status=done` → audit log.
- **D-33:** Personnel row preserved (decommission only). "Expired" badge + filter toggle. Hard-delete deferred to Phase 22 DPA.
- **D-34:** `expires_at` in the past at create → accepted; sweep unenrolls on next hourly run.

**Personnel custom_id generation**
- **D-35:** `AdminPersonnelController::store`: `$validated['custom_id'] = str_replace('-', '', (string) $personnel->id)` → 32-char lowercase hex, deterministic from UUID.

**Broadcast channels + events**
- **D-36:** Two new private channels in `routes/channels.php`: `fras.cameras` (operator/dispatcher/supervisor/admin), `fras.enrollments` (supervisor/admin only).
- **D-37:** `App\Events\CameraStatusChanged` — `ShouldBroadcast` + `ShouldDispatchAfterCommit`, `PrivateChannel('fras.cameras')`, payload `camera_id, camera_id_display, status, last_seen_at, location`. Dispatched only on state transitions.
- **D-38:** `App\Events\EnrollmentProgressed` — same base, `PrivateChannel('fras.enrollments')`, payload `personnel_id, camera_id, camera_id_display, status, last_error`. Fires per row on every transition.

**Configuration (`config/fras.php` extensions)**
- **D-39:** Extend `config/fras.php` with `cameras`, `enrollment`, `photo` sections and respective env vars.

**Milestone-level updates required during planning**
- **D-40:** REQUIREMENTS.md §Scope Decisions line replace "Map baseline: MapLibre GL JS. mapbox-gl explicitly rejected." → "Map baseline: `mapbox-gl` retained (v1.0 continuity). MapLibre path deprecated."
- **D-41:** ROADMAP.md §Phase 20 SC3 — remove "CI bundle-check fails if any code path imports `mapbox-gl`" clause. Retain the heartbeat-watchdog + `CameraStatusChanged` broadcast portion.
- **D-42:** ROADMAP.md §Phase 20 SC2 — replace "MapLibre camera picker" → "Mapbox-GL camera picker (port of existing `LocationMapPicker.vue` shape)".
- **D-43:** ROADMAP.md §Phase 20 SC5 — update `last_heartbeat_at` → `last_seen_at` (Phase 18 D-10 column name).

### Claude's Discretion

- Exact camera marker SVG shape + color tokens — planner picks consistent with `useDispatchMap.ts` existing priority/status color patterns.
- Form field ordering + sections in `CameraForm.vue` / `PersonnelForm.vue` — planner mirrors `UnitForm.vue` layout conventions (UI-SPEC already locks most).
- Number of retry attempts for transient ACK errors (default 3) — planner picks.
- Batch size for pre-existing-personnel sync to a newly added camera — default from `config('fras.enrollment.batch_size', 10)`.
- 422 error copy on blocked camera deletion — planner picks (UI-SPEC already locks copy).
- Whether `EnrollmentProgressPanel.vue` lives in `components/fras/` — UI-SPEC locks to `components/fras/`.
- Whether `PersonnelExpireSweep` sweeps all cameras in one command run or batches by camera — planner picks; low volume at CDRRMO scale.

### Deferred Ideas (OUT OF SCOPE)

- Full mapbox-gl → maplibre-gl migration — v2.1+ territory.
- `/admin/enrollments` fleet-wide dashboard.
- Debounced/batched `EnrollmentProgressed` broadcast.
- Hard-delete personnel on expiry — Phase 22 DPA.
- `EnrollmentAckLog` audit table — Phase 22 DPA.
- WebP photo encoding.
- Signed operator photo URL TTL tuning.
- `CameraForm.vue` advanced fields (RTSP URL, credentials, firmware version).
- Personnel photo thumbnail column.
- Camera marker clustering.
- Camera deletion via API (hard-delete force flag).
- Force-decommission bypass.
- Multi-cascade personnel edit transactionality.
- `PersonnelExpireSweep` telemetry to dispatch banner.
- `custom_id` format upgrade to human-readable.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| CAMERA-01 | Admin CRUD for cameras mirroring `AdminUnitController` pattern with auto-`CAM-01`/`CAM-02` IDs | D-06, D-09 — verbatim port of `AdminUnitController` including `selectRaw("MAX(CAST(SUBSTRING(camera_id_display FROM '[0-9]+$') AS INTEGER))")` |
| CAMERA-02 | Camera picker populates forward-geocoded address + PostGIS barangay | D-03 (mapbox-gl picker port) + `BarangayLookupService` exists and uses `ST_Contains` — blocking call inside controller is acceptable |
| CAMERA-03 | Cameras render as WebGL symbol layer on dispatch console map | D-04 — additive source+layer in `useDispatchMap.ts`; v1.0 `buildCircleIconSvg` pattern reused |
| CAMERA-04 | Live per-camera status indicator via `CameraStatusChanged` on `fras.cameras` | D-36, D-37 — `ShouldBroadcast` + `ShouldDispatchAfterCommit`, v1.0 `IncidentCreated` is reference shape |
| CAMERA-05 | Scheduled watchdog flips camera to degraded/offline on gap + broadcast | D-24, D-25 — `CameraWatchdogCommand` `->everyMinute()`; only writes `degraded`; last-writer-wins with Phase 19 handler |
| CAMERA-06 | Camera deletion blocked when pending/syncing enrollments exist | D-28 — controller pre-check in `destroy()`, returns 422 with inline `<Alert>` |
| PERSONNEL-01 | Admin CRUD for personnel | D-06, D-35 — same pattern; custom_id derived deterministically from UUID |
| PERSONNEL-02 | `FrasPhotoProcessor` with Intervention v4 validates/resizes/hashes | D-18 — v4 API confirmed (see gap 1 below); quality-degradation loop preserved |
| PERSONNEL-03 | Unguessable-UUID public URL auto-revoked post-ACK | D-20, D-21, D-23 — new `photo_access_token` column + route-level check on pending/syncing enrollments |
| PERSONNEL-04 | Create/update/delete enqueues `EnrollPersonnelBatch` per active camera with `WithoutOverlapping` | D-12, D-13 — observer gate on `wasChanged(['photo_hash', 'category'])`; middleware semantics verified (see gap 2) |
| PERSONNEL-05 | Per-camera enrollment progress with retry + resync | D-10, D-38 — `EnrollmentProgressPanel.vue` subscribes via `useEcho` from `@laravel/echo-vue` (see gap 3) |
| PERSONNEL-06 | Scheduled job auto-unenrolls expired personnel | D-31, D-32 — `PersonnelExpireSweepCommand` `->hourly()` |
| PERSONNEL-07 | `AckHandler` correlates ACKs via cache + per-error-code retry policy | D-15, D-16, D-17 — FRAS AckHandler body ports almost verbatim; `Cache::pull` consumes correlation atomically |
</phase_requirements>

## Summary

Phase 20 is **95% verbatim port** from FRAS with three narrow zones of genuine new research: (1) the Intervention Image v3 → v4 API shift (verified against Context7 — the FRAS code is ALREADY v4-compatible, see gap 1), (2) the `WithoutOverlapping` middleware interaction with `$tries = 3` + `releaseAfter` / `expireAfter` (verified against Laravel 13 docs, see gap 2), and (3) the two-namespace photo URL scheme which is IRMS-native (no FRAS precedent — the `photo_access_token` column + route-level revocation is a fresh design and the only new migration). Every other deliverable either ports a known FRAS shape or mirrors an IRMS v1.0 admin pattern (`AdminUnitController`, `Units.vue`/`UnitForm.vue`, `LocationMapPicker.vue`, `IncidentCreated` broadcast event). The planner should favor verbatim porting over "improving" FRAS; the decisions table locks the deltas explicitly.

**Primary recommendation:** Wave 0 installs Intervention Image v3 + Laravel wrapper package (**not yet in composer.json**); Wave 1 migrates `photo_access_token` + ports `CameraEnrollmentService` + `FrasPhotoProcessor`; Wave 2 builds the admin controllers + Inertia pages; Wave 3 wires the broadcasts + scheduled commands + AckHandler body; Wave 4 wires the dispatch-map cameras layer + EnrollmentProgressPanel. Nyquist validation samples on ACK idempotency, mutex contention, URL revocation, deletion gate, watchdog atomicity, sweep expiry.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Camera / Personnel CRUD | API / Backend | Frontend Server (Inertia) | Business logic + DB writes on backend; Inertia renders Vue pages server-side |
| Auto-ID sequencing (`CAM-##`) | Database / Storage | API / Backend | SQL `MAX+1` happens in DB; controller wraps; mirror AdminUnitController |
| Photo processing (resize/encode/hash) | API / Backend | Database / Storage | Intervention Image runs server-side; output stored on `fras_photos` disk |
| Photo serving (public, token-gated) | API / Backend | — | Token lookup + enrollment-state gate is route-level auth; filesystem stream |
| Photo serving (operator, signed) | API / Backend | — | Signed-route verification + role gate; filesystem stream |
| Enrollment orchestration | API / Backend | Database / Storage | Service class + Eloquent + Cache; Horizon queue consumes jobs |
| MQTT publish (EditPersonsNew / DeletePersons) | API / Backend | — | `MQTT::connection('publisher')` from within job handler |
| ACK correlation cache | Database / Storage (Redis) | API / Backend | Redis TTL-backed key; AckHandler reads + deletes |
| Camera / personnel state broadcasting | API / Backend | Browser / Client | `ShouldBroadcast` dispatches to Reverb; Echo Vue subscribes in components |
| Scheduled watchdog + sweep | API / Backend | — | Laravel scheduler owns; Artisan commands |
| Dispatch-map cameras layer | Browser / Client | Frontend Server | Inertia ships initial `cameras` prop; mapbox-gl renders; Echo updates live |
| Camera location picker | Browser / Client | API / Backend (reverseGeocode via Mapbox API) | mapbox-gl runs in browser; reverseGeocode calls external API client-side |
| Barangay auto-assignment | API / Backend | Database / Storage (PostGIS) | `BarangayLookupService` runs PostGIS `ST_Contains` in controller |
| Enrollment live progress UI | Browser / Client | API / Backend | Vue component + `useEcho`; initial state via Inertia prop; mutates local map |

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | `^13.0` (installed) | Backend framework | Phase 17 upgrade locked |
| `php-mqtt/laravel-client` | `^1.8` (installed) | MQTT publish | Phase 19 locked |
| `intervention/image` | `^3.11` (**NOT INSTALLED**) | Photo decode/resize/encode | D-18; **Wave 0 gap** — v4 branch is the current major for the underlying lib but `intervention/image-laravel` ^1.3 is the current Laravel wrapper shipping the `Intervention\Image\Laravel\Facades\Image` facade used by FRAS `[CITED: context7 /intervention/image-laravel]` |
| `intervention/image-laravel` | `^1.3` (**NOT INSTALLED**) | Laravel facade + config | D-18; **Wave 0 gap** |
| `laravel/horizon` | `^5.45.6` (installed) | Queue supervisor | Phase 19 registered `fras-supervisor` block |
| `laravel/reverb` | `^1.10` (installed) | Broadcast server | CAMERA-04, PERSONNEL-05 |
| `clickbar/laravel-magellan` | `^2.1` (installed) | PostGIS `geography` types | Existing `BarangayLookupService` uses raw SQL; no change needed |
| `@laravel/echo-vue` | `^2.3.x` (installed as `laravel-echo ^2.3.1`) | Vue composable for Reverb subscriptions | `useEcho` already in production use in `useDispatchFeed.ts` |
| `mapbox-gl` | `^3.20.0` (installed) | WebGL map | D-01 reverted rejection; v1.0 continuity |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `predis/predis` | `^3.4` (installed) | Redis client | ACK correlation cache store (D-15) |
| `pestphp/pest` | `^4.6` (installed) | Testing | All Phase 20 tests |
| `laravel/wayfinder` | `^0.1.14` (installed) | TypeScript route functions | Inertia forms + Link refs |
| `lucide-vue-next` | existing | Icons | Camera marker glyph, EnrollmentProgressPanel controls |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Redis cache for ACK correlation | A new `enrollment_ack_log` table | Table gives durable audit trail; TTL-cache matches FRAS parity and avoids schema churn. Specific ideas say "no new table" (CONTEXT "Redis cache is the only ACK correlation store") |
| `URL::temporarySignedRoute` 5-min TTL | Storage disk pre-signed URLs | Pre-signed URLs need S3-compatible driver; `fras_photos` is local filesystem in Phase 20 so signed routes are the correct match |
| Per-camera enrollment job | Bulk multi-camera job | Per-camera matches `WithoutOverlapping('enrollment-camera-{id}')` mutex granularity; bulk would lose the mutex key |

**Installation (Wave 0):**
```bash
composer require intervention/image-laravel:^1.3
php artisan vendor:publish --provider="Intervention\\Image\\Laravel\\ServiceProvider"
```
`intervention/image-laravel` pulls `intervention/image` ^3 transitively `[CITED: context7 /intervention/image-laravel]`.

**Version verification:**
```bash
composer show intervention/image-laravel
# confirm ^1.3 resolves; confirm intervention/image ^3.x pulled transitively
```

## Architecture Patterns

### System Architecture Diagram

```
ADMIN ACTIONS (Browser)
  │
  ├── Cameras.vue/CameraForm.vue ──POST──► AdminCameraController
  │                                               │
  │                                               ├── BarangayLookupService.findByCoordinates
  │                                               │     └─► PostGIS ST_Contains (blocking, ~1ms)
  │                                               ├── Camera::create (auto-sequence CAM-##)
  │                                               └── CameraEnrollmentService::enrollAllToCamera
  │                                                     └── dispatch EnrollPersonnelBatch (per chunk)
  │                                                           └── Horizon 'fras' queue
  │
  └── Personnel.vue/PersonnelForm.vue ──POST──► AdminPersonnelController
                                                  │
                                                  ├── FrasPhotoProcessor::process (Intervention v4)
                                                  │     ├── decodePath → orient → scaleDown(1080)
                                                  │     ├── encode(JpegEncoder(quality=85))
                                                  │     ├── while size > 1MB && q > 40: q -= 10, re-encode
                                                  │     └── md5 hash + Storage::disk('fras_photos')
                                                  ├── Personnel::updateOrCreate  (photo_hash + custom_id)
                                                  └── PersonnelObserver::saved
                                                        └── IF wasChanged(['photo_hash','category'])
                                                              └── CameraEnrollmentService::enrollPersonnel

HORIZON WORKER ('fras' queue)
  │
  └── EnrollPersonnelBatch::handle
        ├── WithoutOverlapping('enrollment-camera-{id}')
        │     • releaseAfter(30) — retry after 30s if lock held
        │     • expireAfter(300) — force-release lock after 5min
        │     • tries=3 — max attempts
        └── CameraEnrollmentService::upsertBatch
              ├── chunk by config('fras.enrollment.batch_size', 10)
              ├── buildEditPersonsNewPayload($camera, $chunk, $messageId)
              ├── Cache::put("enrollment-ack:{cam_id}:{messageId}", {...}, ttl)
              └── MQTT::connection('publisher')->publish("mqtt/face/{device_id}", JSON)
                    │
                    │ (camera processes, ACKs)
                    ▼
MQTT LISTENER (Supervisor, separate process)
  │
  └── TopicRouter → AckHandler::handle
        ├── Cache::pull("enrollment-ack:{cam_id}:{messageId}")  ← consumes atomically
        ├── For AddSucInfo: camera_enrollments::update(status=done, enrolled_at, photo_hash)
        ├── For AddErrInfo: translateErrorCode($code) → status=failed, last_error
        └── broadcast EnrollmentProgressed per row → fras.enrollments

BROADCAST (Reverb)
  │
  ├── fras.enrollments ──► EnrollmentProgressPanel.vue (useEcho, personnel edit page)
  └── fras.cameras    ──► DispatchConsole cameras layer (useEcho, live status)

SCHEDULED (cron → Laravel scheduler)
  │
  ├── irms:camera-watchdog        ->everyMinute()  — state-machine transitions → CameraStatusChanged
  └── irms:personnel-expire-sweep ->hourly()       — unenroll expired personnel

PHOTO SERVING
  │
  ├── /fras/photo/{token} (PUBLIC)
  │     FrasPhotoAccessController::show
  │     ├── Personnel::where('photo_access_token', $token)->first() or 404
  │     ├── enrollments pending|syncing exists? or 404  (revocation gate)
  │     ├── Log::channel('mqtt')->info('fras.photo.access', ...)
  │     └── Storage::disk('fras_photos')->response("personnel/{id}.jpg")
  │
  └── /admin/personnel/{id}/photo (OPERATOR)
        AdminPersonnelPhotoController::show
        ├── middleware('signed')  — verifies URL signature + expiry
        ├── middleware('role:operator,supervisor,admin')
        └── Storage::disk('fras_photos')->response("personnel/{id}.jpg")
```

### Recommended Project Structure

```
app/
├── Console/Commands/
│   ├── CameraWatchdogCommand.php         (NEW, irms:camera-watchdog)
│   └── PersonnelExpireSweepCommand.php   (NEW, irms:personnel-expire-sweep)
├── Events/
│   ├── CameraStatusChanged.php           (NEW, ShouldBroadcast + ShouldDispatchAfterCommit)
│   └── EnrollmentProgressed.php          (NEW, same contract)
├── Http/Controllers/
│   ├── Admin/
│   │   ├── AdminCameraController.php     (NEW, mirrors AdminUnitController)
│   │   ├── AdminPersonnelController.php  (NEW)
│   │   └── AdminPersonnelPhotoController.php (NEW, signed route)
│   └── Fras/
│       └── FrasPhotoAccessController.php (NEW, public token-gated)
├── Http/Requests/Admin/
│   ├── StoreCameraRequest.php / UpdateCameraRequest.php   (NEW)
│   └── StorePersonnelRequest.php / UpdatePersonnelRequest.php (NEW)
├── Jobs/
│   └── EnrollPersonnelBatch.php          (NEW, FRAS port)
├── Mqtt/Handlers/
│   └── AckHandler.php                     (MOD, fill in handle() body)
├── Observers/
│   └── PersonnelObserver.php             (NEW)
└── Services/
    ├── CameraEnrollmentService.php       (NEW, FRAS port)
    └── FrasPhotoProcessor.php            (NEW, FRAS port w/ IRMS path)

resources/js/
├── components/
│   ├── admin/CameraLocationPicker.vue   (NEW, ports LocationMapPicker)
│   └── fras/EnrollmentProgressPanel.vue (NEW)
│   └── fras/CameraStatusBadge.vue       (NEW)
├── composables/
│   ├── useEnrollmentProgress.ts         (NEW)
│   └── useDispatchMap.ts                 (MOD, add cameras layer)
└── pages/admin/
    ├── Cameras.vue / CameraForm.vue     (NEW)
    └── Personnel.vue / PersonnelForm.vue (NEW)

database/migrations/
└── 2026_04_2x_add_photo_access_token_to_personnel_table.php (NEW)

config/
├── fras.php          (MOD, add cameras/enrollment/photo sections)
├── filesystems.php   (MOD, add fras_photos disk)
└── image.php         (PUBLISHED by Intervention v4 install, Wave 0)

routes/
├── admin.php   (MOD, add cameras + personnel resources + signed photo)
├── channels.php (MOD, add fras.cameras + fras.enrollments)
└── console.php (MOD, schedule camera-watchdog + personnel-expire-sweep + signed fras/photo route)
```

### Pattern 1: Camera auto-ID sequencing (verbatim port of AdminUnitController::store lines 67-80)

**What:** Controller computes next display ID via SQL regex on existing rows.
**When to use:** `AdminCameraController::store()`, after validation, before `Camera::create()`.
**Example:**
```php
// Source: /Users/helderdene/IRMS/app/Http/Controllers/Admin/AdminUnitController.php lines 76-83
$maxSequence = Camera::query()
    ->selectRaw("MAX(CAST(SUBSTRING(camera_id_display FROM '[0-9]+$') AS INTEGER)) as max_seq")
    ->value('max_seq');

$nextNumber = ($maxSequence ?? 0) + 1;
$paddedNumber = str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
$validated['camera_id_display'] = "CAM-{$paddedNumber}";
```
**Notes:** Postgres-native regex (`FROM '[0-9]+$'`). v1.0 ships this; Phase 18 D-05 reserves the `camera_id_display` nullable column for this population.

### Pattern 2: Intervention Image v4 photo processing

**What:** Decode → orient → scale down → encode with quality-degradation loop.
**When to use:** `FrasPhotoProcessor::process()` on every photo upload.
**Example:**
```php
// Source: context7 /intervention/image (v4 API reference)
//         /Users/helderdene/fras/app/Services/PhotoProcessor.php (shape verified v4-compatible)
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

$image = Image::decodePath($file->path());  // Laravel facade = ImageManager::decodePath in v4
$image->orient();                            // v4 method (auto-orient via EXIF)
$image->scaleDown(width: 1080, height: 1080); // v4 method — does not upscale

$encoded = $image->encode(new JpegEncoder(quality: 85));  // EncodedImage

while (strlen((string) $encoded) > $maxBytes && $quality > 40) {
    $quality -= 10;
    $encoded = $image->encode(new JpegEncoder(quality: $quality));
}

$hash = md5((string) $encoded);
Storage::disk('fras_photos')->put("personnel/{$personnel->id}.jpg", (string) $encoded);
```
**v4 notes:** `encode()` returns an `EncodedImage` object that casts to string (binary). `(string) $encoded` is the byte stream. `strlen()` on it gives byte count. `$image` is reusable — calling `encode()` multiple times on the same `Image` re-encodes from the in-memory buffer, which is why the quality loop works.

### Pattern 3: `WithoutOverlapping` mutex + retry policy composition

**What:** Prevents concurrent enrollment publishes to the same camera, auto-releases the lock on failure/timeout.
**When to use:** `EnrollPersonnelBatch::middleware()` returns the middleware instance.
**Example:**
```php
// Source: /Users/helderdene/fras/app/Jobs/EnrollPersonnelBatch.php
//         https://laravel.com/docs/13.x/queues#preventing-job-overlaps
use Illuminate\Queue\Middleware\WithoutOverlapping;

public int $tries = 3;

public function middleware(): array
{
    return [
        (new WithoutOverlapping('enrollment-camera-'.$this->camera->id))
            ->releaseAfter(30)     // If lock held by another job, release back to queue after 30s
            ->expireAfter(300),    // Lock auto-expires after 5 min (safety net for crashed workers)
    ];
}
```
**Semantic composition:**
- `WithoutOverlapping($key)` — acquires a cache-lock on the key. If held, the middleware **releases the job back to the queue immediately** (default behavior: job doesn't count as a "try") `[CITED: laravel.com/docs/13.x/queues]`.
- `->releaseAfter(30)` — configures the release delay (wait 30s before the queue re-picks this job). This makes `$tries` composition correct: each *actual execution* counts toward $tries, not each "could-not-acquire-lock" release.
- `->expireAfter(300)` — protects against worker crashes. If a worker dies holding the lock, the cache-lock TTL expires after 5 min and the next attempt proceeds.
- `$tries = 3` — total **execution attempts** (not including lock-bumped releases). On the 3rd failed attempt the job lands in `failed_jobs`.
- **Transient ACK errors (D-17 codes 464/465/466)**: in `AckHandler` we do NOT call `$this->release(60)` on the job (AckHandler is not inside the job context — it's an MQTT message handler). Instead we mark `camera_enrollments.status=failed` with `last_error`, and the operator's "Retry this camera" button re-queues a fresh `EnrollPersonnelBatch`. This makes the per-error retry a UX feature, not a queue feature. `$tries = 3` here protects against MQTT publish failures inside the job itself (e.g., broker disconnect mid-publish).

### Pattern 4: `useEcho` composable subscription in a Vue component

**What:** Private channel listener with auto-cleanup on unmount.
**When to use:** `EnrollmentProgressPanel.vue`, `useEnrollmentProgress.ts` composable.
**Example:**
```typescript
// Source: context7 /laravel/docs broadcasting.md (13.x useEcho Vue section)
//         resources/js/composables/useDispatchFeed.ts:133 (production pattern in this codebase)
import { useEcho } from '@laravel/echo-vue';

export function useEnrollmentProgress(personnelId: string, initialRows: EnrollmentRow[]) {
    const rows = ref(new Map(initialRows.map(r => [r.camera_id, r])));

    const { leaveChannel, stopListening } = useEcho<EnrollmentProgressedPayload>(
        'fras.enrollments',
        'EnrollmentProgressed',
        (e) => {
            if (e.personnel_id !== personnelId) return;   // filter to this personnel
            rows.value.set(e.camera_id, {
                camera_id: e.camera_id,
                camera_id_display: e.camera_id_display,
                status: e.status,
                last_error: e.last_error,
                // name + enrolled_at preserved from existing row or backfilled
                ...(rows.value.get(e.camera_id) ?? {}),
                status: e.status,
            });
        },
    );

    // useEcho auto-cleans up on component unmount via Vue's onScopeDispose.
    // Manual control available via stopListening() / leaveChannel() if needed.

    return { rows: readonly(rows) };
}
```
**Cleanup:** `useEcho` is reactive-scope-aware — it calls `stopListening` + `leaveChannel` automatically when the component/composable scope is disposed. No manual `onUnmounted` needed `[CITED: context7 /laravel/docs broadcasting.md]`.
**Reconnection:** Echo's underlying Pusher/Reverb connection handles reconnect automatically. `useEcho` re-binds the listener on reconnect without user code.

### Pattern 5: Mapbox-gl GeoJSON source + symbol layer lifecycle

**What:** Add source + layers idempotently, update a single feature in place via `setData`.
**When to use:** `useDispatchMap.ts` extensions (add cameras layer alongside incidents + units).
**Example:**
```typescript
// Source: useDispatchMap.ts existing incidents/units pattern (verbatim idiom used in v1.0)
// Idempotency: check if source/layer exist before adding; re-calling addSource throws.

function setCameraData(cameras: DispatchCamera[]) {
    if (!map.value || !isLoaded.value) {
        // Map not ready — defer until 'load' event. Existing pattern in useDispatchMap.ts buffers initial data.
        pendingCameraData.value = cameras;
        return;
    }

    const featureCollection = {
        type: 'FeatureCollection' as const,
        features: cameras
            .filter(c => c.coordinates)   // decommissioned cameras already filtered server-side
            .map(c => ({
                type: 'Feature' as const,
                id: c.id,
                geometry: { type: 'Point' as const, coordinates: [c.coordinates!.lng, c.coordinates!.lat] },
                properties: { id: c.id, camera_id_display: c.camera_id_display, status: c.status },
            })),
    };

    const source = map.value.getSource('cameras') as GeoJSONSource | undefined;
    if (source) {
        source.setData(featureCollection);   // in-place update, no layer redraw
    } else {
        map.value.addSource('cameras', { type: 'geojson', data: featureCollection, promoteId: 'id' });
        map.value.addLayer({ /* camera-halo circle layer */ });
        map.value.addLayer({ /* camera-body symbol layer with match expression on status */ });
        map.value.addLayer({ /* camera-label symbol layer */ });
    }
}

function updateCameraStatus(cameraId: string, status: string) {
    // CameraStatusChanged broadcast handler — mutate one feature without full redraw.
    if (!map.value) return;
    const source = map.value.getSource('cameras') as GeoJSONSource | undefined;
    if (!source) return;

    // mapbox-gl GeoJSONSource doesn't expose a per-feature update API; we must rebuild the FeatureCollection.
    // Performance-acceptable at ≤8 cameras per CONTEXT scale.
    const current = source._data as any;   // fall back to stored snapshot
    const updated = {
        ...current,
        features: current.features.map((f: any) =>
            f.id === cameraId ? { ...f, properties: { ...f.properties, status } } : f),
    };
    source.setData(updated);
}
```
**Initial-load-order handling:** `useDispatchMap.ts` already uses an `isLoaded` ref that flips on the map's `'load'` event — any `setCameraData` call before `load` must buffer. Follow the existing `setIncidentData` / `setUnitData` buffering pattern.
**mapbox-gl `promoteId: 'id'`** makes the feature's `properties.id` accessible as `['get', 'id']` in match expressions (vs the anonymous numeric auto-id). Matches existing v1.0 pattern.

### Pattern 6: Laravel `temporarySignedRoute` for operator photo URL

**What:** Generate a 5-minute signed URL for operator-only photo access.
**When to use:** `AdminPersonnelController::edit()` passes the URL as an Inertia prop; `AdminPersonnelPhotoController::show()` verifies signature via middleware.
**Example:**
```php
// Source: context7 /laravel/docs urls.md (13.x temporarySignedRoute section)

// In AdminPersonnelController::edit (generate URL for SSR Inertia prop)
use Illuminate\Support\Facades\URL;

return Inertia::render('admin/PersonnelForm', [
    'personnel' => $personnel,
    'photo_url' => $personnel->photo_hash
        ? URL::temporarySignedRoute('admin.personnel.photo', now()->addMinutes(5), ['personnel' => $personnel])
        : null,
    // ...
]);

// In routes/admin.php
Route::get('personnel/{personnel}/photo', [AdminPersonnelPhotoController::class, 'show'])
    ->middleware('signed')       // validates signature + expiry automatically
    ->name('admin.personnel.photo');

// In AdminPersonnelPhotoController::show
public function show(Request $request, Personnel $personnel): StreamedResponse
{
    // `signed` middleware already verified signature. Role middleware enforced upstream.
    Gate::authorize('view-recognition-image', $personnel);  // optional additional gate
    return Storage::disk('fras_photos')->response("personnel/{$personnel->id}.jpg");
}
```
**TTL note:** On each Inertia edit-page load the URL is regenerated (5-min fresh TTL). If the admin leaves the page open >5 min and then clicks, the image link 404s with a gentle `<img onerror>` fallback `[ASSUMED]` (UI-SPEC specifies `size-24` photo preview — fallback UX is planner discretion).
**Middleware order:** Laravel's `signed` middleware runs before route-bound resolution in the typical stack; combined with Inertia's `role:operator,supervisor,admin` middleware from the route group, both must pass. Verified against Laravel 13 docs `[CITED: context7 /laravel/docs urls.md]`.

### Pattern 7: PHP enum integration in Form Requests + Inertia props

**What:** Validate enum string values; cast on assignment; type Inertia prop with the enum's value.
**When to use:** `StoreCameraRequest`, `StorePersonnelRequest`, `UpdatePersonnelRequest`.
**Example:**
```php
// Source: Phase 18 D-64 sets enum casts on models; standard Laravel 13 enum validation
use App\Enums\PersonnelCategory;
use Illuminate\Validation\Rules\Enum;

public function rules(): array
{
    return [
        'category' => ['required', new Enum(PersonnelCategory::class)],
        'expires_at' => ['nullable', 'date'],
        // ...
    ];
}

// Controller: validated() returns string; cast to enum for assignment if needed
$personnel->category = PersonnelCategory::from($validated['category']);
// OR rely on $casts on the model (Phase 18 D-64 sets `'category' => PersonnelCategory::class`)
$personnel->fill(['category' => $validated['category']]);  // cast handles string→enum
```
**Inertia prop typing (TS):** share enum cases as a prop from the controller:
```php
return Inertia::render('admin/PersonnelForm', [
    'categories' => PersonnelCategory::cases(),   // serializes as [{name:..., value:...}]
    'personnel' => $personnel,  // 'category' already serializes as the string value
]);
```
```typescript
// resources/js/pages/admin/PersonnelForm.vue
type PersonnelCategory = { name: string; value: 'allow'|'block'|'missing'|'lost_child' };
defineProps<{ categories: PersonnelCategory[]; personnel?: Personnel }>();
```

### Anti-Patterns to Avoid

- **Re-enrolling on every `saved` hook fire:** PersonnelObserver must gate on `wasChanged(['photo_hash', 'category'])`. Without the gate, editing `name` or `phone` triggers MQTT storms. See gap 10.
- **Dispatching broadcast events inside an Eloquent transaction without `ShouldDispatchAfterCommit`:** `CameraStatusChanged` and `EnrollmentProgressed` both MUST implement `ShouldDispatchAfterCommit` — otherwise a rolled-back transaction leaves subscribers holding stale state. Reference: existing `IncidentCreated` event.
- **Using `Cache::get` + `Cache::forget` in `AckHandler` (two calls, race-prone):** Use `Cache::pull($key)` which is atomic — FRAS AckHandler already does this. Preserves atomic "read-and-consume" semantics.
- **Hand-rolling mutex with `Cache::lock` inside the job body:** `WithoutOverlapping` middleware is the framework-blessed way and handles worker-crash recovery via `expireAfter` lock TTL. Hand-rolled locks lose the TTL safety net.
- **Hard-coding `config('hds.*')` from FRAS source:** All FRAS calls are `config('hds.enrollment.batch_size')`, `config('hds.mqtt.topic_prefix')`, etc. **IRMS ports rename every `hds.` to `fras.`** per D-11.
- **Hand-rolling image processing (GD `imagecreatefromjpeg` + `imagejpeg`):** Don't. Intervention Image v4 handles EXIF orientation, quality degradation, and memory safety across GD + Imagick drivers. Enumerated in Don't Hand-Roll.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Image resize + quality encoding | GD/Imagick raw calls | `intervention/image-laravel` v4 facade | EXIF orientation, memory safety, driver abstraction, quality-degradation loop pattern — battle-tested since Intervention v1 (2013) |
| Job mutex per camera | `Cache::lock($key)` inside `handle()` | `WithoutOverlapping` middleware | Framework handles `releaseAfter` + `expireAfter` + worker-crash recovery; hand-rolled locks lose TTL safety |
| MD5 photo deduplication | Hand-coded hash-compare loop | `md5((string) $encoded)` + `photo_hash` column + observer `wasChanged` gate | Trivially correct; standard pattern |
| Signed URLs for operator photos | Custom token + expiry column | `URL::temporarySignedRoute` + `signed` middleware | Framework-verified HMAC + timestamp-based expiry; no schema storage |
| ACK correlation | New `enrollment_ack_log` DB table | Redis `Cache::put`/`Cache::pull` | FRAS pattern; TTL-backed; atomic consume; no schema churn |
| Auto-sequenced admin IDs | Counter table with `SELECT FOR UPDATE` | SQL regex `MAX+1` pattern from AdminUnitController | Matches v1.0 idiom; race-free under Postgres's MVCC for admin-rate writes; `UNIQUE` constraint catches edge case |
| PostGIS barangay lookup | Hand-coded bounding-box check | `BarangayLookupService::findByCoordinates` (exists) | PostGIS `ST_Contains` handles polygon geometry; BarangayLookupService already production-tested |
| Vue Echo subscription + cleanup | Manual `Echo.private()` + `onUnmounted` | `useEcho` from `@laravel/echo-vue` | Composable auto-cleans on scope dispose; handles reconnect; 9+ uses in `useDispatchFeed.ts` already |
| Inertia form wire-up | Raw `fetch` POST | `useForm` + Wayfinder action imports | v1.0 convention; CSRF + progress + error handling free |
| Mapbox-gl feature-level updates | Remove + re-add layer | `source.setData(newFeatureCollection)` | In-place update preserves layer style + camera state; ~1ms vs 10ms layer rebuild |

**Key insight:** Phase 20 has near-zero original engineering. Every seemingly-hard problem has an off-the-shelf Laravel/Vue primitive. The engineering value-add is the FRAS→IRMS delta (UUID FKs, config paths, broadcast events, two-namespace photo URL) — nothing else.

## Runtime State Inventory

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | None — Phase 20 is additive. Phase 18 tables are empty; Phase 19 writes only `recognition_events` + listener heartbeat cache; nothing to migrate. New data: `personnel.photo_access_token` (new column, rotated on photo upload). | None (new writes only) |
| Live service config | Mapbox Studio styles referenced by ID (`helderdene/cmmq06eqr005j01skbwodfq08`) — already live + unchanged from v1.0. Mapbox access token via `VITE_MAPBOX_TOKEN` env — already set. No new external service config. | None |
| OS-registered state | Horizon supervisor block `fras-supervisor` — registered in Phase 19, Phase 20 dispatches to it, no re-registration. Scheduled commands (`irms:camera-watchdog`, `irms:personnel-expire-sweep`) are added to `routes/console.php` — Laravel scheduler picks them up via the single cron entry; no new cron line. | None |
| Secrets/env vars | New env vars reserved: `FRAS_CAMERA_DEGRADED_GAP_S`, `FRAS_CAMERA_OFFLINE_GAP_S`, `FRAS_ENROLLMENT_BATCH_SIZE`, `FRAS_ENROLLMENT_ACK_TIMEOUT_MINUTES`, `FRAS_PHOTO_MAX_DIMENSION`, `FRAS_PHOTO_JPEG_QUALITY`, `FRAS_PHOTO_MAX_SIZE_BYTES`, `FRAS_PHOTO_DISK`. Defaults fall back in `config/fras.php` D-39. | Document in `docs/operations/` or .env.example |
| Build artifacts | Wayfinder auto-generated actions (`resources/js/actions/`, `resources/js/routes/`) — regenerate via `php artisan wayfinder:generate` after adding controllers + routes. | Re-run wayfinder generation as part of Wave 2 |

## Common Pitfalls

### Pitfall 1: Observer re-fires on DB hydration
**What goes wrong:** `PersonnelObserver::saved` can fire during model hydration from DB (not just user-initiated saves), causing MQTT publish storms.
**Why it happens:** Eloquent's `retrieved` event is separate from `saved`, but `wasChanged()` **compares against the original attributes loaded from DB** — on hydration, no attributes have changed, so `wasChanged([...])` returns false for **all** fields. Safe by construction.
**How to avoid:** Trust `wasChanged(['photo_hash', 'category'])` — Laravel sets the "original" state at hydration; `wasChanged` only returns true when the current attribute differs from the loaded value AND the model has been saved. Use `isDirty` for "about to save" intent, `wasChanged` for "just saved and changed". For Observer::saved → `wasChanged` is correct `[CITED: laravel.com/docs/13.x/eloquent#events]`.
**Warning signs:** MQTT publish counter jumps during tests that instantiate Personnel factories without saving — use `Personnel::factory()->make()` (doesn't fire observer) unless the test explicitly exercises the observer path.

### Pitfall 2: Broadcast dispatched inside Eloquent transaction
**What goes wrong:** A `CameraStatusChanged` dispatched inside a transaction that later rolls back is still broadcast, leaking stale state to subscribers.
**Why it happens:** Default `ShouldBroadcast` dispatches on event emit; transaction rollback can't unbroadcast.
**How to avoid:** Implement `ShouldDispatchAfterCommit` on `CameraStatusChanged` + `EnrollmentProgressed` (both covered by D-37/D-38). Laravel's event dispatcher holds until transaction commits, drops on rollback.
**Warning signs:** Frontend shows "online" camera that database says "offline" — verify both events include `ShouldDispatchAfterCommit`.

### Pitfall 3: `WithoutOverlapping` + `$tries` composition confusion
**What goes wrong:** Setting `$tries = 3` with `WithoutOverlapping` and expecting "3 attempts" — but every release-due-to-lock-contention doesn't count toward tries; only actual execution failures do. Result: a job can be re-released indefinitely.
**Why it happens:** Misunderstanding: `releaseAfter(30)` releases the job when lock is contended — this is a *queue retry*, not a *try*. `$tries` only caps actual `handle()` calls that threw.
**How to avoid:** Read the semantics: `tries=3` = max 3 calls to `handle()`. Lock-contention releases are unlimited (until `expireAfter(300)` frees the lock on a crashed worker). For Phase 20 this is desired: if camera A is mid-enrollment, job B for camera A waits patiently. For failure-on-publish, 3 actual attempts is sufficient.
**Warning signs:** Horizon dashboard shows one job bouncing between "queued" and "released" forever — check the lock key, confirm the holder is actually working.

### Pitfall 4: Observer registered before model autoload
**What goes wrong:** `Personnel::observe(PersonnelObserver::class)` in `AppServiceProvider::boot()` fires before Personnel model has loaded → observer silently doesn't attach.
**Why it happens:** Rare edge case with certain ServiceProvider ordering.
**How to avoid:** Use `AppServiceProvider::boot()` (not `register()`) — `boot()` runs after all providers registered. IRMS v1.0 pattern uses boot() for model observers; follow it.
**Warning signs:** PersonnelObserver never fires in tests — add `\App\Models\Personnel::observe(\App\Observers\PersonnelObserver::class)` to `Tests\TestCase::setUp` or verify via `dd(Personnel::getObservableEvents())`.

### Pitfall 5: ACK cache key collision across listener restarts
**What goes wrong:** Listener restart mid-enrollment loses the message-id → camera_enrollments correlation. ACK arrives, cache key missing, enrollment stuck in `syncing`.
**Why it happens:** Redis cache survives listener restart (good), but `messageId` format includes `now()->format('Y-m-d\TH:i:s')` + random suffix — deterministic enough NOT to collide across restarts.
**How to avoid:** The current D-15 format is fine. Edge-case: operator retry-button generates a fresh messageId, so stuck `syncing` rows recover via retry. `AckHandler` D-16 step 3 "warning log if missing" is the right trap.
**Warning signs:** Monitor `Log::channel('mqtt')` for "ACK for unknown/expired messageId" — a sudden spike indicates listener lag or Redis eviction under memory pressure.

### Pitfall 6: Mapbox-gl layer order + z-fighting
**What goes wrong:** Cameras layer added after incidents layer but before units layer — symbols overlap inconsistently across browsers.
**Why it happens:** `addLayer(layer, beforeId?)` argument matters. Without it, layers stack in insertion order.
**How to avoid:** Follow the existing `useDispatchMap.ts` convention — incidents render below units (operator priority). Cameras should render BELOW incidents (camera at fixed location is context, not event). Pass `beforeId: 'incident-halo'` when adding camera layers.
**Warning signs:** Camera icons visually above P1 incidents on the dispatch map — reorder via `beforeId`.

### Pitfall 7: Intervention Image v4 facade auto-registration
**What goes wrong:** `intervention/image-laravel` auto-discovery registers the facade but the `Image` namespace can conflict with Laravel's `Storage`/`Http` facade import order.
**Why it happens:** Import collision if any file imports `use Intervention\Image\Image` (the class) instead of the facade.
**How to avoid:** Use the explicit facade path: `use Intervention\Image\Laravel\Facades\Image;` — matches FRAS `PhotoProcessor.php` shape.
**Warning signs:** `Call to undefined method Intervention\Image\Image::decodePath` — it's the class, not the facade. Fix import.

### Pitfall 8: `MQTT::connection('publisher')` inside a job without broker connectivity
**What goes wrong:** Horizon worker tries to publish, broker is down, job fails 3 times, `camera_enrollments.status` stays `pending` forever (ACK never arrives because publish never happened).
**Why it happens:** `php-mqtt/laravel-client` throws on broker unreachable; `$tries = 3` exhausts and the job fails; BUT there's no `failed` method on `EnrollPersonnelBatch` to mark the enrollment row.
**How to avoid:** Add `public function failed(Throwable $e): void` to `EnrollPersonnelBatch` — mark the row `status=failed`, `last_error = 'Unable to reach camera'`, broadcast `EnrollmentProgressed`. Lets the operator retry once the broker is healthy.
**Warning signs:** Personnel rows stuck `syncing` after a broker incident — grep Horizon failed_jobs for `EnrollPersonnelBatch` and add the retry-queue button path.

### Pitfall 9: Camera picker coordinate system (lng,lat vs lat,lng)
**What goes wrong:** Mapbox uses `[lng, lat]`; PostGIS `ST_MakePoint` convention and IRMS's incidents table use `[lng, lat]` too (`ST_MakePoint(longitude, latitude)`). FRAS had `(lat, lng)` in some places — porting verbatim breaks barangay lookup.
**Why it happens:** FRAS reversed the tuple at some seams; IRMS ports must normalize to PostGIS convention.
**How to avoid:** Match `BarangayLookupService::findByCoordinates(float $latitude, float $longitude)` signature verbatim — controller calls `$this->lookup->findByCoordinates($validated['latitude'], $validated['longitude'])`. Mapbox events emit `{lng, lat}` — extract both and pass correctly.
**Warning signs:** Camera saves with wrong barangay — verify the controller extracts `lat` and `lng` correctly from the Inertia form payload.

### Pitfall 10: `photo_access_token` uniqueness collision after photo delete
**What goes wrong:** Rotating `photo_access_token = Str::uuid()` on every upload — but if the column has a UNIQUE index and stale tokens aren't cleared, index bloat accumulates.
**Why it happens:** UUIDs don't collide in practice (2^122 space), but the UNIQUE index has to check across all rows on every upload.
**How to avoid:** UNIQUE on UUID is fine at CDRRMO scale (200 personnel × ~1 upload/month); no action needed. If index contention ever appears (it won't), switch to a nullable non-unique token + app-level uniqueness.
**Warning signs:** Not at this scale. No action needed.

## Code Examples

### `CameraEnrollmentService::upsertBatch` (ported verbatim w/ IRMS config + broadcast)

```php
// Source: /Users/helderdene/fras/app/Services/CameraEnrollmentService.php lines 68-92
//         IRMS tweaks: config('hds.*')→config('fras.*'), Dispatch EnrollmentProgressed per row
public function upsertBatch(Camera $camera, array $personnelIds): void
{
    $personnel = Personnel::whereIn('id', $personnelIds)->get();
    $batchSize = config('fras.enrollment.batch_size', 10);

    foreach ($personnel->chunk($batchSize) as $chunk) {
        $messageId = 'EditPersonsNew'.now()->format('Y-m-d\TH:i:s').'_'.Str::random(6);
        $payload = $this->buildEditPersonsNewPayload($camera, $chunk, $messageId);

        Cache::put(
            "enrollment-ack:{$camera->id}:{$messageId}",
            [
                'camera_id' => $camera->id,
                'personnel_ids' => $chunk->pluck('id')->toArray(),
                'photo_hashes' => $chunk->pluck('photo_hash', 'custom_id')->toArray(),
                'dispatched_at' => now()->toIso8601String(),
            ],
            config('fras.enrollment.ack_timeout_minutes', 5) * 60
        );

        // Transition rows to syncing + broadcast per row (IRMS addition)
        foreach ($chunk as $p) {
            CameraEnrollment::where('camera_id', $camera->id)
                ->where('personnel_id', $p->id)
                ->update(['status' => CameraEnrollmentStatus::Syncing]);

            EnrollmentProgressed::dispatch(
                $p->id, $camera->id, $camera->camera_id_display,
                CameraEnrollmentStatus::Syncing->value, null
            );
        }

        $prefix = config('fras.mqtt.topic_prefix');
        MQTT::connection('publisher')->publish(
            "{$prefix}/{$camera->device_id}",
            json_encode($payload, JSON_UNESCAPED_SLASHES)
        );
    }
}
```

### `AckHandler::handle` (body fill-in, ports FRAS shape + IRMS broadcast)

```php
// Source: /Users/helderdene/fras/app/Mqtt/Handlers/AckHandler.php
//         Phase 19 ported shell; Phase 20 fills body
public function handle(string $topic, string $message): void
{
    $data = json_decode($message, true);
    if (!$data) { Log::channel('mqtt')->warning('Invalid ACK payload', ['topic' => $topic]); return; }

    $messageId = $data['messageId'] ?? null;
    if (!$messageId) { Log::channel('mqtt')->warning('ACK missing messageId'); return; }

    $segments = explode('/', $topic);
    $deviceId = $segments[2] ?? null;   // mqtt/face/{device_id}/Ack
    if (!$deviceId) return;

    $camera = Camera::where('device_id', $deviceId)->first();
    if (!$camera) { Log::channel('mqtt')->warning('ACK for unknown camera', ['device_id' => $deviceId]); return; }

    $cacheKey = "enrollment-ack:{$camera->id}:{$messageId}";
    $pending = Cache::pull($cacheKey);     // atomic read + delete
    if (!$pending) { Log::channel('mqtt')->warning('ACK for unknown/expired messageId'); return; }

    $info = $data['info'] ?? [];
    $this->processSuccesses($camera, $info['AddSucInfo'] ?? [], $pending);
    $this->processFailures($camera, $info['AddErrInfo'] ?? []);
}
```

### `CameraWatchdogCommand` (state-machine transition + broadcast)

```php
// New — no FRAS reference (FRAS doesn't have `degraded`)
public function handle(): int
{
    $now = now();
    $degradedGap = config('fras.cameras.degraded_gap_s', 30);
    $offlineGap = config('fras.cameras.offline_gap_s', 90);

    Camera::query()->whereNull('decommissioned_at')->get()->each(function (Camera $camera) use ($now, $degradedGap, $offlineGap) {
        $gap = $camera->last_seen_at ? $now->diffInSeconds($camera->last_seen_at) : PHP_INT_MAX;

        $newStatus = match (true) {
            $gap <= $degradedGap => CameraStatus::Online,
            $gap <= $offlineGap => CameraStatus::Degraded,
            default => CameraStatus::Offline,
        };

        if ($camera->status !== $newStatus) {
            $camera->update(['status' => $newStatus]);
            CameraStatusChanged::dispatch($camera);
        }
    });

    return self::SUCCESS;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Intervention Image v2 `->save()` chain | v4 `encode(new JpegEncoder(quality: 85))` returning `EncodedImage` object | Intervention v3 (2023), v4 (2024) | FRAS already uses v4 API. IRMS adopts same. |
| `Image::make($file)` v2 facade | `Image::decodePath($file->path())` or `Image::read($request->file)` v3/v4 | Intervention v3 | FRAS uses v4; IRMS ports verbatim. |
| `Event::dispatch()` inside transaction | `ShouldDispatchAfterCommit` interface | Laravel 8+ | v1.0 `IncidentCreated` already uses this. |
| Manual `Echo.private(...).listen(...)` + `onUnmounted` | `useEcho` composable | `@laravel/echo-vue` 2.x (2024) | v1.0 `useDispatchFeed.ts` uses `useEcho` 9×. |

**Deprecated/outdated:**
- FRAS's `config('hds.*')` paths — IRMS uses `config('fras.*')` (D-11).
- FRAS's bigint `Camera::id` FK — IRMS uses UUID FKs (Phase 18 D-01).
- FRAS's `person_type` tinyint — IRMS replaces with `category` enum (Phase 18 D-16).
- Intervention Image v2 facade imports — v4 uses `Intervention\Image\Laravel\Facades\Image` (facade) or `Intervention\Image\ImageManager` (direct).

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `useEcho` from `@laravel/echo-vue` auto-cleans via `onScopeDispose` — no manual unmount hook needed for `EnrollmentProgressPanel` | Pattern 4 | Memory leaks if wrong; easy to fix post-facto by wrapping in `onUnmounted(() => leaveChannel())` |
| A2 | `StreamedResponse` return type on photo controllers is the correct signature (`Storage::disk(...)->response(...)` returns `BinaryFileResponse` in newer Laravel versions) | Pattern 6 | Type mismatch in controller return type only; runtime works fine. Planner should check current Laravel 13 return type before strict-typing. |
| A3 | `URL::temporarySignedRoute` 5-minute TTL + Inertia prop regeneration on every page load is acceptable UX | Pattern 6 | Admin leaves page open >5 min, image 404s with no fallback. Low-severity; acceptable per UI-SPEC photo thumbnail being optional. |
| A4 | Intervention Image v4 uses **`intervention/image` ^3** as of April 2026 (not actually "v4" on the PHP package — the branding differs from the version tag). The Laravel wrapper `intervention/image-laravel` ^1.3 is the current Laravel binding. | Standard Stack | If wrong, composer pulls older version; API may differ slightly. Verify via `composer show intervention/image` after install. Context7 docs confirmed current API includes `decodePath`, `scaleDown`, `orient`, `JpegEncoder` |
| A5 | `$camera->status !== $newStatus` comparison works with PHP enum cast (Phase 18 D-64 sets `CameraStatus::class` cast on the column) | CameraWatchdogCommand example | Backed Enum strict comparison works natively in PHP 8.1+. IRMS runs PHP 8.3+. |

**Signal:** A1-A5 are all low-risk (syntax confirmations, not design decisions). No assumption requires user confirmation before planning; they'll be caught by the planner's code review or by Wave 0 framework install.

## Open Questions (RESOLVED)

1. **Does `AdminCameraController::store` barangay lookup block the request?**
   - What we know: `BarangayLookupService::findByCoordinates` is a synchronous `DB::select` against a PostGIS `ST_Contains` query. On Butuan's ~88 barangays with GIST index, latency is ~1-3ms.
   - What's unclear: Whether CAMERA-02 acceptance requires the barangay to be set **before** the response returns (synchronous) or may be deferred to a job.
   - RESOLVED: Synchronous barangay lookup in the controller — Latency is negligible at CDRRMO scale; admin workflow expects the saved camera to show the correct barangay on the redirect-to-index. FRAS has no precedent. Planner can override if UX feedback shows the save feels slow.

2. **`PersonnelExpireSweep`: one command run sweeps all, or chunked by camera?**
   - What we know: CONTEXT discretion area. Low volume (<200 personnel, most won't expire in a given hour).
   - What's unclear: Nothing material — CDRRMO scale doesn't stress either approach.
   - RESOLVED: PersonnelExpireSweep single-pass loop — single-command iteration over `Personnel::whereNotNull('expires_at')->where('expires_at', '<', now())->whereNull('decommissioned_at')->get()`. Inside the loop, call `deleteFromAllCameras` (which already chunks per-camera MQTT publishes). No outer chunking needed.

3. **Mapbox camera marker click behavior — popup contents?**
   - What we know: UI-SPEC section 7 specifies "clicking opens a lightweight Popup with name, status, last-seen relative time, and a Link to `/admin/cameras/{id}/edit`".
   - What's unclear: Whether the Popup uses Mapbox's native `new Popup()` or a Vue component wrapped in a Teleport. UI-SPEC says "mapbox-gl `new Popup()`" — native.
   - RESOLVED: Camera marker click → native mapbox-gl Popup with innerHTML strings. If richer interactivity ever needed, escalate to a Vue-teleport-based popup.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PostgreSQL + PostGIS | Barangay lookup, camera.location geography | ✓ | 16.x (inherited from v1.0) | — |
| Redis | ACK correlation cache, Horizon | ✓ | (inherited) | — |
| Reverb | Broadcast events | ✓ | `^1.10` | — |
| Mosquitto MQTT broker | EnrollPersonnelBatch publish | ✓ | (Phase 19 dev prereq) | Job marks status=failed with `last_error = 'broker unreachable'` and operator retries |
| Mapbox access token | Camera picker + reverseGeocode + geocoding search | ✓ | (v1.0 continuity) | — |
| `intervention/image-laravel` | `FrasPhotoProcessor` | ✗ (NOT INSTALLED) | — | **Wave 0 install:** `composer require intervention/image-laravel:^1.3` |
| `intervention/image` (transitive) | Used via Laravel wrapper | ✗ (NOT INSTALLED) | — | Pulled transitively by above |
| GD or Imagick PHP extension | Intervention Image driver | GD (assumed — default PHP-Herd includes) `[ASSUMED]` | — | If only Imagick, set `config/image.php` driver; both work for JPEG |

**Missing dependencies with no fallback:**
- None — Wave 0 installs the single missing package.

**Missing dependencies with fallback:**
- `intervention/image-laravel` — Wave 0 `composer require` resolves it. Fallback in case of repo issues: use `intervention/image` raw with `ImageManager::usingDriver(Driver::class)` instead of the facade (FRAS's original pre-Laravel-wrapper shape).

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 (pestphp/pest ^4.6, installed) |
| Config file | `phpunit.xml` + `tests/Pest.php` (pre-existing from v1.0 / Phase 17) |
| Quick run command | `php artisan test --compact --filter={TestName}` |
| Full suite command | `php artisan test --compact` |
| FRAS-specific group | `php artisan test --compact --group=fras` (Phase 18 D-60 registered the group) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|--------------|
| CAMERA-01 | Admin CRUD + auto CAM-## sequencing + recommission | Feature | `php artisan test --compact tests/Feature/Admin/AdminCameraControllerTest.php` | ❌ Wave 0 |
| CAMERA-02 | Camera save populates address + barangay via PostGIS | Feature | `php artisan test --compact tests/Feature/Admin/AdminCameraControllerTest.php --filter=barangay_auto_assigned` | ❌ Wave 0 |
| CAMERA-03 | Cameras layer renders as GeoJSON source on dispatch map | Manual UAT | N/A (Inertia prop wiring verified via feature test; WebGL render is manual) | ❌ Wave 0 (`DispatchConsoleControllerTest::test_cameras_prop_wired`) |
| CAMERA-04 | `CameraStatusChanged` broadcast payload shape | Feature | `php artisan test --compact tests/Feature/Fras/CameraStatusChangedBroadcastTest.php` | ❌ Wave 0 |
| CAMERA-05 | Watchdog flips status on gap → broadcast | Feature | `php artisan test --compact tests/Feature/Fras/CameraWatchdogTest.php` | ❌ Wave 0 |
| CAMERA-06 | Deletion blocked when pending/syncing enrollments | Feature | `php artisan test --compact tests/Feature/Admin/AdminCameraControllerTest.php --filter=destroy_blocked_when_in_flight` | ❌ Wave 0 |
| PERSONNEL-01 | Admin CRUD for personnel | Feature | `php artisan test --compact tests/Feature/Admin/AdminPersonnelControllerTest.php` | ❌ Wave 0 |
| PERSONNEL-02 | `FrasPhotoProcessor` validates, resizes, hashes | Unit | `php artisan test --compact tests/Unit/Services/FrasPhotoProcessorTest.php` | ❌ Wave 0 |
| PERSONNEL-03 | Public photo URL revoked when no pending/syncing | Feature | `php artisan test --compact tests/Feature/Fras/FrasPhotoAccessControllerTest.php` | ❌ Wave 0 |
| PERSONNEL-04 | Observer + job dispatch + WithoutOverlapping mutex | Feature | `php artisan test --compact tests/Feature/Fras/EnrollPersonnelBatchTest.php` | ❌ Wave 0 |
| PERSONNEL-05 | `EnrollmentProgressed` broadcast payload shape | Feature | `php artisan test --compact tests/Feature/Fras/EnrollmentProgressedBroadcastTest.php` | ❌ Wave 0 |
| PERSONNEL-06 | Personnel expire sweep unenrolls + decommissions | Feature | `php artisan test --compact tests/Feature/Fras/PersonnelExpireSweepTest.php` | ❌ Wave 0 |
| PERSONNEL-07 | `AckHandler` correlates cache → updates row → broadcasts + `Cache::pull` atomicity | Feature | `php artisan test --compact tests/Feature/Fras/AckHandlerTest.php` (extend Phase 19 stub) | ⚠️ stub exists; extend |

### Sampling Rate
- **Per task commit:** `php artisan test --compact tests/Feature/{ChangedArea}*` — scoped to the controller/service/job under change.
- **Per wave merge:** `php artisan test --compact --group=fras` + `php artisan test --compact tests/Feature/Admin/` — FRAS + admin tests together (watchdog + enrollment + admin CRUD).
- **Phase gate:** `composer run ci:check` (full suite green) + manual UAT of dispatch-map cameras layer + EnrollmentProgressPanel live update.

### Wave 0 Gaps

**Framework installs:**
- [ ] `composer require intervention/image-laravel:^1.3` — pulls `intervention/image ^3`
- [ ] `php artisan vendor:publish --provider="Intervention\Image\Laravel\ServiceProvider"` — creates `config/image.php`

**New test files:**
- [ ] `tests/Feature/Admin/AdminCameraControllerTest.php` — index / create / store (CAM-## sequence) / edit / update / destroy (block-rule) / recommission
- [ ] `tests/Feature/Admin/AdminPersonnelControllerTest.php` — same shape + photo upload path
- [ ] `tests/Unit/Services/FrasPhotoProcessorTest.php` — processes a fixture JPEG, asserts dimensions ≤1080, bytes ≤1MB, hash stability
- [ ] `tests/Feature/Fras/CameraEnrollmentServiceTest.php` — `enrollPersonnel` / `enrollAllToCamera` / `upsertBatch` / `deleteFromAllCameras` / `translateErrorCode` table
- [ ] `tests/Feature/Fras/EnrollPersonnelBatchTest.php` — dispatch, WithoutOverlapping mutex key, retry path, failed() method
- [ ] `tests/Feature/Fras/AckHandlerTest.php` — **extend** Phase 19 stub with the Phase 20 body: cache round-trip (put → handle → pull), idempotency (double-ACK = one update), missing-cache warn log, unknown-device warn log
- [ ] `tests/Feature/Fras/CameraWatchdogTest.php` — inject Carbon::setTestNow, verify online→degraded→offline transitions, broadcast assertion
- [ ] `tests/Feature/Fras/PersonnelExpireSweepTest.php` — expired personnel → decommissioned, enrollments marked done, `deleteFromAllCameras` publish
- [ ] `tests/Feature/Fras/FrasPhotoAccessControllerTest.php` — token valid + pending enrollment → 200; token valid + no pending → 404; token unknown → 404
- [ ] `tests/Feature/Fras/CameraStatusChangedBroadcastTest.php` — dispatch event, assert `ShouldBroadcast` + `ShouldDispatchAfterCommit` + private channel + payload shape
- [ ] `tests/Feature/Fras/EnrollmentProgressedBroadcastTest.php` — same shape

**Shared fixtures:**
- [ ] `tests/fixtures/personnel-photo-sample.jpg` — real 2000×1500 JPEG >1.5MB for photo processor degradation loop test
- [ ] Test helper: `Pest\freezeTime()` wrapper for watchdog + expire-sweep determinism

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Fortify (existing); admin routes use `auth` + `role:supervisor,admin` middleware |
| V3 Session Management | yes | Laravel session (existing); CSRF on all admin POST/PATCH/DELETE |
| V4 Access Control | yes | Route-level `role:*` gates (D-07); `role:operator,supervisor,admin` on operator photo URL (D-22); `role:supervisor,admin` on fras.enrollments channel (D-36) |
| V5 Input Validation | yes | `StoreCameraRequest` / `StorePersonnelRequest` Form Requests with `Rule::enum(PersonnelCategory::class)`, `mimes:jpeg` + `max:1024` on photo, `date` on expires_at |
| V6 Cryptography | yes | `URL::temporarySignedRoute` (HMAC-SHA256 from `APP_KEY`); `Str::uuid()` for photo_access_token; `md5` for **content dedup only** (not a security hash — acceptable per FRAS contract) |
| V12 File Upload | yes | `fras_photos` disk is private (no public URL); MIME validation server-side via `validated()['photo']->isValid()` + `mimes:jpeg`; Intervention Image re-encodes (strips EXIF per config option) |

### Known Threat Patterns for Laravel 13 + Vue 3 + mapbox-gl

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Public photo URL enumeration | Information Disclosure | Unguessable UUID token (2^122 space) + enrollment-state revocation gate (D-21/D-23) |
| Responder bypassing role gate to see raw scene images | Elevation | `role:operator,supervisor,admin` middleware on `/admin/personnel/*/photo` explicitly excludes responders + dispatchers (D-22) |
| SQL injection via search / filter params | Tampering | Eloquent parameter binding + `->where('name', 'ILIKE', '%...%')` — never raw-interpolated |
| File upload as JPEG but actual EXE | Tampering | Intervention `decodePath` fails on non-image bytes → exception before storage write |
| Privileged channel subscription by lower-role user | Information Disclosure | `routes/channels.php` `fras.enrollments` gate `supervisor|admin` only (D-36) |
| Mass assignment on personnel photo_access_token | Tampering | Token rotation only inside `FrasPhotoProcessor::process` + model `$guarded` (not `$fillable`); never controller-accepted |
| CSRF on decommission/recommission | Tampering | Laravel CSRF middleware on all POST routes (default in `admin.php` group) |
| Broadcast event data leakage | Information Disclosure | `broadcastWith()` whitelists exactly the fields sent; no full model serialization (D-37, D-38) |

## Sources

### Primary (HIGH confidence)
- Context7 `/intervention/image` — `scaleDown`, `encode`, `JpegEncoder`, `decodePath` — all confirmed current v4 API
- Context7 `/intervention/image-laravel` — facade import path `Intervention\Image\Laravel\Facades\Image` + config file shape
- Context7 `/laravel/docs` broadcasting.md 13.x — `useEcho` Vue composable with `leaveChannel` / `stopListening` controls
- Context7 `/laravel/docs` queues.md 13.x — `WithoutOverlapping` + `expireAfter` + `releaseAfter` semantics
- Context7 `/laravel/docs` urls.md 13.x — `URL::temporarySignedRoute` + `signed` middleware
- `/Users/helderdene/fras/app/Services/CameraEnrollmentService.php` — port target (206 lines, read fully)
- `/Users/helderdene/fras/app/Services/PhotoProcessor.php` — port target, confirmed v4 API already in use
- `/Users/helderdene/fras/app/Jobs/EnrollPersonnelBatch.php` — port target
- `/Users/helderdene/fras/app/Mqtt/Handlers/AckHandler.php` — shell ported in Phase 19; body-fill reference
- `/Users/helderdene/IRMS/app/Http/Controllers/Admin/AdminUnitController.php` — verbatim mirror target
- `/Users/helderdene/IRMS/app/Services/BarangayLookupService.php` — PostGIS ST_Contains pattern, production-tested
- `/Users/helderdene/IRMS/app/Events/IncidentCreated.php` — broadcast event reference shape
- `/Users/helderdene/IRMS/resources/js/composables/useDispatchFeed.ts` — `useEcho` production pattern (9× uses)
- `/Users/helderdene/IRMS/resources/js/composables/useDispatchMap.ts` — GeoJSON source + match expression idiom
- `/Users/helderdene/IRMS/resources/js/components/intake/LocationMapPicker.vue` — camera picker port target
- `/Users/helderdene/IRMS/composer.json` — verified installed: Laravel 13, Horizon 5.45.6, Reverb 1.10, Magellan 2.1, php-mqtt 1.8, Pest 4.6; verified NOT installed: intervention/image, intervention/image-laravel
- `/Users/helderdene/IRMS/package.json` — `laravel-echo ^2.3.1`, `mapbox-gl ^3.20.0`

### Secondary (MEDIUM confidence)
- Intervention Image homepage (WebFetch) — v4 is current major; exact installation URL 404'd but context7 coverage filled gap
- Phase 18 CONTEXT.md — schema freeze baseline for column names + enum casts

### Tertiary (LOW confidence)
- None — every design decision tracks to CONTEXT.md (frozen), UI-SPEC.md (frozen), or verified library behavior

## Risk Hotspots (top 5)

Ranked by likelihood × impact during execution.

### 1. Intervention Image install Wave 0 blocker
**Likelihood:** High (package not installed; no composer.lock entry). **Impact:** Blocks `FrasPhotoProcessor` port → blocks PERSONNEL-02 → blocks PERSONNEL-04 (observer re-enroll on photo change).
**Mitigation:** Wave 0 task: `composer require intervention/image-laravel:^1.3`. Include version-verification command. If version resolution picks up a major version ≠3 (unlikely but possible), fall back to explicit `intervention/image:^3` constraint.

### 2. AckHandler cache-key correlation under listener restart
**Likelihood:** Medium (normal dev workflow restarts the listener frequently). **Impact:** Enrollment rows stuck `syncing` forever; operator confusion; requires retry-button recovery.
**Mitigation:** Pitfall 5 coverage; AckHandlerTest must assert warn-log path + retry recovery path. Document in ops runbook: "After listener restart, inspect Horizon `fras` queue + enrollments stuck in `syncing`; trigger Resync for affected personnel."

### 3. `useEcho` channel subscription leak or double-bind on personnel edit navigation
**Likelihood:** Medium (SPA-style navigation in Inertia can re-mount components). **Impact:** Memory leak / duplicate broadcast handlers; duplicated status transitions in UI.
**Mitigation:** Use the `useEcho` composable (Pattern 4) — its `onScopeDispose` hook is reliable. Add a feature test or dev-only Vue DevTools check that toggling between PersonnelForm edit pages doesn't accumulate Echo listeners. (Empirically v1.0 `useDispatchFeed` runs stable.)

### 4. Mapbox-gl layer-order z-fighting between incidents, units, cameras
**Likelihood:** Medium (adding a 3rd layer family to `useDispatchMap.ts` for the first time). **Impact:** Visual regressions on dispatch console; operator confusion about icon priority.
**Mitigation:** Pitfall 6 — pass `beforeId` to `addLayer` calls. Test manually on dispatch console UAT. Follow locked UI-SPEC section 7 step 2 explicitly.

### 5. Watchdog state-machine thrash near threshold boundaries
**Likelihood:** Low (threshold gaps of 30s / 90s are wide). **Impact:** A camera with flaky heartbeat around the 30s mark could oscillate online↔degraded, flooding `CameraStatusChanged` broadcasts.
**Mitigation:** `CameraWatchdogCommand` dispatches only on **transitions** (D-25); no-op on steady state. If oscillation proves to be real at CDRRMO, add hysteresis (e.g., require 2 consecutive ticks in the new state before transitioning) — defer to operational feedback, not Phase 20 build.

## Testing Strategy

### Unit vs Feature split

| Test | Type | Mock? | Why |
|------|------|-------|-----|
| `FrasPhotoProcessorTest` | Unit | `Storage::fake('fras_photos')` | Pure service — exercise with a real JPEG fixture, assert hash/size/path; no DB needed |
| `CameraEnrollmentServiceTest` | Feature | `Event::fake([EnrollmentProgressed::class])`, `Queue::fake([EnrollPersonnelBatch::class])`, `Cache::spy()`, `MQTT::fake()` or equivalent | Touches cache + queue + MQTT — all mocked; DB real for enrollment rows |
| `EnrollPersonnelBatchTest` | Feature | `Cache::spy`, `MQTT::shouldReceive('connection->publish')` | Verify mutex key shape; swap service for mock to assert `upsertBatch` called with right args; `Queue::after(callback)` for mutex validation |
| `AckHandlerTest` | Feature | `Cache::put` real (use array cache in test); `Event::fake([EnrollmentProgressed::class])` | Round-trip cache; double-ACK idempotency; warn-log assertion via `Log::spy()` |
| `CameraWatchdogTest` | Feature | `Carbon::setTestNow`, `Event::fake([CameraStatusChanged::class])` | Deterministic time control |
| `PersonnelExpireSweepTest` | Feature | `Carbon::setTestNow`, `MQTT::fake`, `Event::fake` | Similar time control |
| `AdminCameraControllerTest` | Feature | Actor via `$this->actingAs(User::factory()->admin()->create())`; DB real (RefreshDatabase) | Full HTTP shape + Inertia response assertion |
| `AdminPersonnelControllerTest` | Feature | Same + `Storage::fake('fras_photos')` | Photo upload real-ish; Intervention runs with GD |
| `FrasPhotoAccessControllerTest` | Feature | DB real; `Storage::fake` for the stream response | Assert 404 on revoked token + 200 on valid |
| Broadcast tests | Feature | `Event::fake` + `Event::assertDispatched` | Pure payload shape + channel assertion |

### Mock vs Real

**Mock:**
- MQTT broker (`MQTT::shouldReceive('connection->publish')` — php-mqtt package doesn't ship a `::fake()` so use Mockery directly)
- Reverb (via `Event::fake` — we never actually connect to Reverb in tests)
- Mapbox reverseGeocode API (if ever tested on backend, which we won't — it's a client-side fetch)
- Redis cache — use `array` store via `CACHE_STORE=array` in `.env.testing` (already v1.0 convention)

**Real:**
- PostgreSQL (Phase 18 D-58 confirmed — `.env.testing: DB_CONNECTION=pgsql`)
- Horizon queue dispatcher (tests use `Queue::fake` for isolation; integration tests skip queue and run job synchronously via `EnrollPersonnelBatch::dispatchSync`)
- Intervention Image (real processing — fast enough with GD)
- Laravel scheduler command invocation — call `Artisan::call('irms:camera-watchdog')` directly

### Testing scheduled commands deterministically

```php
// Pattern: freeze time, seed state, invoke command, assert transitions
it('flips a camera from online to degraded after 60s gap', function () {
    Carbon::setTestNow('2026-04-21 10:00:00');
    $camera = Camera::factory()->create(['status' => 'online', 'last_seen_at' => now()]);

    Carbon::setTestNow('2026-04-21 10:01:00');  // +60s gap
    Event::fake([CameraStatusChanged::class]);

    Artisan::call('irms:camera-watchdog');

    expect($camera->refresh()->status)->toBe(CameraStatus::Degraded);
    Event::assertDispatched(CameraStatusChanged::class, fn ($e) => $e->camera->id === $camera->id);
});
```

### Testing ACK correlation without a real MQTT broker

```php
it('correlates an ACK to a pending enrollment and broadcasts progress', function () {
    $camera = Camera::factory()->create(['device_id' => 'cam-42']);
    $personnel = Personnel::factory()->create(['custom_id' => 'abc123']);
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);

    // Simulate what upsertBatch would have cached
    Cache::put("enrollment-ack:{$camera->id}:msg-42", [
        'camera_id' => $camera->id,
        'personnel_ids' => [$personnel->id],
        'photo_hashes' => ['abc123' => 'hashvalue'],
    ], 300);

    Event::fake([EnrollmentProgressed::class]);

    app(AckHandler::class)->handle(
        'mqtt/face/cam-42/Ack',
        json_encode([
            'messageId' => 'msg-42',
            'info' => ['AddSucInfo' => [['customId' => 'abc123']]],
        ])
    );

    $enrollment = CameraEnrollment::where('camera_id', $camera->id)->where('personnel_id', $personnel->id)->first();
    expect($enrollment->status)->toBe(CameraEnrollmentStatus::Done)
        ->and($enrollment->photo_hash)->toBe('hashvalue')
        ->and($enrollment->enrolled_at)->not->toBeNull();

    expect(Cache::has("enrollment-ack:{$camera->id}:msg-42"))->toBeFalse();   // consumed
    Event::assertDispatched(EnrollmentProgressed::class);
});
```

### Broadcast assertion shape

```php
Event::fake([CameraStatusChanged::class]);
// ... trigger transition ...
Event::assertDispatched(CameraStatusChanged::class, function ($event) use ($camera) {
    return $event->camera->id === $camera->id
        && $event->camera->status === CameraStatus::Offline;
});

// Also verify contract (interfaces)
$event = new CameraStatusChanged($camera);
expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
expect($event)->toBeInstanceOf(\Illuminate\Contracts\Events\ShouldDispatchAfterCommit::class);
expect($event->broadcastOn())->toEqual([new \Illuminate\Broadcasting\PrivateChannel('fras.cameras')]);
expect($event->broadcastWith())->toHaveKeys(['camera_id', 'camera_id_display', 'status', 'last_seen_at', 'location']);
```

## Per-Requirement Coverage Map

| Req ID | Implementation Artifact(s) | Test Artifact |
|--------|---------------------------|---------------|
| **CAMERA-01** | `AdminCameraController` (index/create/store w/ auto-sequence/edit/update/destroy/recommission), `StoreCameraRequest`, `UpdateCameraRequest`, `Cameras.vue`, `CameraForm.vue`, `routes/admin.php` extensions | `AdminCameraControllerTest` — full 7-method CRUD matrix |
| **CAMERA-02** | `AdminCameraController::store` calls `BarangayLookupService::findByCoordinates($lat, $lng)` (existing service); `CameraLocationPicker.vue` emits address via `reverseGeocode`; controller persists `address` + `barangay_id` | `AdminCameraControllerTest::test_barangay_auto_assigned_on_store` |
| **CAMERA-03** | `useDispatchMap.ts` gains `setCameraData` + `addLayer` for `camera-halo`/`camera-body`/`camera-label`; `DispatchConsoleController::index` adds `cameras` Inertia prop; toggle button in `Console.vue`; `CAMERA_STATUS_COLORS` match expression | `DispatchConsoleControllerTest::test_cameras_prop_contains_active_cameras_only` + manual WebGL UAT |
| **CAMERA-04** | `CameraStatusChanged` event (PrivateChannel `fras.cameras`, `ShouldBroadcast + ShouldDispatchAfterCommit`); `routes/channels.php` authorizes `fras.cameras`; `useDispatchFeed.ts` (or new `useCameraFeed.ts`) subscribes via `useEcho` | `CameraStatusChangedBroadcastTest` — payload shape + channel + contract assertions |
| **CAMERA-05** | `CameraWatchdogCommand` `->everyMinute()` in `routes/console.php`; state-machine per D-25; dispatches `CameraStatusChanged` on transition | `CameraWatchdogTest` — time-frozen scenarios for online/degraded/offline boundaries |
| **CAMERA-06** | `AdminCameraController::destroy` pre-check `enrollments()->whereIn('status', ['pending','syncing'])->count() > 0` → 422 with error bag | `AdminCameraControllerTest::test_destroy_blocked_when_enrollments_in_flight` + `test_destroy_allowed_when_all_done_or_failed` |
| **PERSONNEL-01** | `AdminPersonnelController` (7-method), `StorePersonnelRequest`, `UpdatePersonnelRequest`, `Personnel.vue`, `PersonnelForm.vue`; `custom_id` derivation from UUID (D-35) | `AdminPersonnelControllerTest` — full CRUD matrix |
| **PERSONNEL-02** | `FrasPhotoProcessor` (Intervention v4 port); `StorePersonnelRequest` validates `mimes:jpeg\|max:1024` client-side; server-side processor enforces 1080p + 1MB via quality-degradation loop; `fras_photos` disk in `config/filesystems.php` | `FrasPhotoProcessorTest` (unit) — fixture-based dimensions + size + hash; `AdminPersonnelControllerTest::test_photo_upload_path` |
| **PERSONNEL-03** | `personnel.photo_access_token` migration (D-20); `FrasPhotoAccessController@show` at `/fras/photo/{token}`; revocation check on pending/syncing enrollments; token rotation in `FrasPhotoProcessor` | `FrasPhotoAccessControllerTest` — 4 scenarios (valid+pending=200, valid+none=404, invalid=404, rotated token=404) |
| **PERSONNEL-04** | `PersonnelObserver::saved` gated by `wasChanged(['photo_hash','category'])`; `CameraEnrollmentService::enrollPersonnel` per-camera dispatch; `EnrollPersonnelBatch` with `WithoutOverlapping('enrollment-camera-{id}')->releaseAfter(30)->expireAfter(300)`, `$tries = 3` | `EnrollPersonnelBatchTest` — mutex key shape, tries semantics, `failed()` handler; `CameraEnrollmentServiceTest::test_enrollPersonnel_dispatches_per_active_camera` |
| **PERSONNEL-05** | `EnrollmentProgressed` event (PrivateChannel `fras.enrollments`, same contract); `EnrollmentProgressPanel.vue` + `useEnrollmentProgress.ts` composable; retry + resync action routes at `/admin/personnel/{id}/enrollments/{camera}/retry` + `/resync` | `EnrollmentProgressedBroadcastTest`; `AdminPersonnelControllerTest::test_retry_endpoint` |
| **PERSONNEL-06** | `PersonnelExpireSweepCommand` `->hourly()` in `routes/console.php`; iterates expired personnel → `deleteFromAllCameras` → mark decommissioned + enrollments done | `PersonnelExpireSweepTest` — time-frozen scenarios |
| **PERSONNEL-07** | `AckHandler::handle` body fill-in per D-16; `CameraEnrollmentService::translateErrorCode` 10-entry map (D-11); per-error-code classification in handler (transient vs terminal) | `AckHandlerTest` — cache round-trip + idempotency + error-translation; unit test for `translateErrorCode` table |

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — every library is installed (or Wave 0-install with Context7-verified install command)
- Architecture patterns: HIGH — all 7 patterns either port verbatim from FRAS or mirror v1.0 production code (`AdminUnitController`, `useDispatchFeed`, `IncidentCreated`, `BarangayLookupService`)
- Pitfalls: MEDIUM — Pitfalls 1-6 are documented Laravel/Vue gotchas with citations; Pitfalls 7-10 are inference from the IRMS codebase conventions; risk-graded low because FRAS parity reduces surface area
- Validation architecture: HIGH — Pest 4 is already running FRAS group; test shapes are direct translations of the requirements

**Research date:** 2026-04-21
**Valid until:** 2026-05-21 (30 days — Laravel/Intervention/mapbox-gl all on stable majors; no breaking changes expected)
