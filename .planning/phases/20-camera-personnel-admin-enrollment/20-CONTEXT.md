# Phase 20: Camera + Personnel Admin + Enrollment - Context

**Gathered:** 2026-04-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 20 delivers the **admin surface** for the camera fleet and personnel watch-list, plus the **enrollment pipeline** that syncs personnel to cameras over MQTT. Concretely:

- `/admin/cameras` + `/admin/personnel` Inertia pages following the v1.0 AdminUnitController / Units.vue / UnitForm.vue split
- `AdminCameraController` with auto-sequenced `CAM-01`/`CAM-02` display IDs (mirroring AdminUnitController::store) + a Mapbox-GL picker (reuses the existing `LocationMapPicker.vue` pattern) + forward-geocoding + PostGIS barangay auto-assignment
- `AdminPersonnelController` with photo upload routed through `FrasPhotoProcessor` (Intervention Image v4, 1080p cap, 1MB cap with quality-degradation loop, MD5 dedup) + `PersonnelObserver` that enqueues `EnrollPersonnelBatch` on `photo_hash` or `category` changes only
- `CameraEnrollmentService` ported from FRAS verbatim (with IRMS UUID FKs + `config/fras.*` reads) + `EnrollPersonnelBatch` job on the `fras` Horizon queue Phase 19 already registered
- `AckHandler` body filled in: Redis cache `enrollment-ack:{camera_id}:{messageId}` → `camera_enrollments.status` transitions + `EnrollmentProgressed` broadcast + photo URL revocation via route-level enrollment-state check
- `CameraWatchdog` scheduled command flips status between `online` / `degraded` / `offline` based on `last_seen_at` gap, broadcasting `CameraStatusChanged` on transitions
- `PersonnelExpireSweep` scheduled command unenrolls personnel whose `expires_at` has passed (MQTT DeletePersons + mark decommissioned; personnel row preserved)
- Cameras render as a toggleable WebGL symbol layer on the existing mapbox-gl dispatch console map; per-camera status color updates live via `CameraStatusChanged` on a new `fras.cameras` private channel

**Out of scope (guardrails):**
- Recognition → Incident bridge — Phase 21 (`FrasIncidentFactory`, `IncidentChannel::IoT`, escalate-to-P1 button)
- Alert feed UI, ACK/dismiss UX, signed-URL retention semantics, `fras_access_log` DPA audit table — Phase 22
- Hard-delete personnel on expiry — deferred to Phase 22 DPA right-to-erasure rules
- Stranger-detection `Snap` topic — REQUIREMENTS.md out-of-scope
- Dead-letter `fras_unroutable_events` table — Phase 19 deferred idea, not resurrected here

The deliverable gate: an admin at `/admin/cameras` places a camera on the picker → save → camera appears as a pin on dispatch console; an admin at `/admin/personnel` uploads a photo → within seconds the enrollment progress panel shows all active cameras transitioning pending→syncing→done; deleting the camera while an enrollment is in flight returns 422; a personnel record with `expires_at = yesterday` gets auto-unenrolled within the hour.

</domain>

<decisions>
## Implementation Decisions

### Map stack — milestone scope override

- **D-01: v2.0 milestone Scope Decision "`mapbox-gl` explicitly rejected" is REVERTED.** `mapbox-gl` is retained across the IRMS codebase. User-directed decision 2026-04-21 during phase-20 discussion. Rationale: v1.0 already ships `mapbox-gl ^3.20.0` in `useDispatchMap`, `useAnalyticsMap`, `LocationMapPicker`, `StandbyScreen`, `NavTab`, `useGeocodingSearch`; a full migration to `maplibre-gl` is scope creep the milestone does not need.
- **D-02:** Phase 20 SC3 "CI bundle-check fails if any code path imports mapbox-gl" is **DROPPED**. Planning must update ROADMAP.md §Phase 20 SC3 text to remove this clause. REQUIREMENTS.md §Scope Decisions must be updated: "Map baseline: MapLibre GL JS" → "Map baseline: mapbox-gl retained (v1.0 continuity)".
- **D-03:** Camera picker implemented with `mapbox-gl` mirroring `resources/js/components/intake/LocationMapPicker.vue` verbatim: `BUTUAN_CENTER = [125.5406, 8.9475]`, `BUTUAN_ZOOM = 13`, Mapbox Studio styles (`helderdene/cmmq06eqr005j01skbwodfq08` light / `cmns77fv5004e01sr9hh5bcqq` dark), `reverseGeocode` via `fetch` to `api.mapbox.com/geocoding/v5/mapbox.places/`.
- **D-04:** Cameras dispatch-map layer added to `useDispatchMap.ts` — same `mapbox-gl` Map instance, add `cameras` GeoJSON source + symbol layer alongside existing incidents/units layers. Toggle via new `cameras-visible` local state in `DispatchConsole.vue`.
- **D-05:** No map library migration work in Phase 20. Existing `useDispatchMap`, `useAnalyticsMap`, `LocationMapPicker`, `NavTab`, `StandbyScreen`, `useGeocodingSearch` unchanged.

### Admin page structure

- **D-06:** Mirror IRMS v1.0 Unit admin pattern verbatim for both resources:
  - Controllers: `app/Http/Controllers/Admin/AdminCameraController.php` + `app/Http/Controllers/Admin/AdminPersonnelController.php`
  - Pages: `resources/js/pages/admin/Cameras.vue` + `CameraForm.vue` + `Personnel.vue` + `PersonnelForm.vue`
  - Requests: `app/Http/Requests/Admin/StoreCameraRequest.php`, `UpdateCameraRequest.php`, `StorePersonnelRequest.php`, `UpdatePersonnelRequest.php`
- **D-07:** `routes/admin.php` extensions:
  ```php
  Route::resource('cameras', AdminCameraController::class);
  Route::post('cameras/{camera}/recommission', [AdminCameraController::class, 'recommission'])->name('cameras.recommission');
  Route::resource('personnel', AdminPersonnelController::class);
  Route::post('personnel/{personnel}/recommission', [AdminPersonnelController::class, 'recommission'])->name('personnel.recommission');
  Route::get('personnel/{personnel}/photo', [AdminPersonnelPhotoController::class, 'show'])->middleware('signed')->name('personnel.photo');
  ```
  All under the existing `role:supervisor,admin` group (D-09-channels extends to operator for cameras).
- **D-08:** Both resources render as tables in `Cameras.vue` / `Personnel.vue`. Column sets:
  - Cameras: `camera_id_display`, `name`, `status` badge, `device_id`, `location_label`, `enrollments_count`, action buttons
  - Personnel: `name`, `category` badge, `expires_at` (with "Expired" badge), `enrolled_count`, `consent_basis` presence indicator, action buttons
  Filter + search headers, click-through to edit, dialog-based delete/recommission confirmations. Matches `Units.vue` visual language.
- **D-09:** Camera display-ID auto-sequencing in `AdminCameraController::store()` mirrors `AdminUnitController::store` lines 67-80 verbatim:
  ```php
  $maxSequence = Camera::query()
      ->selectRaw("MAX(CAST(SUBSTRING(camera_id_display FROM '[0-9]+$') AS INTEGER)) as max_seq")
      ->value('max_seq');
  $validated['camera_id_display'] = sprintf('CAM-%02d', ($maxSequence ?? 0) + 1);
  ```
  Persisted to Phase 18 `cameras.camera_id_display` column (D-05).
- **D-10:** `PersonnelForm.vue` embeds an inline `<EnrollmentProgressPanel>` on edit mode only. Panel shows per-camera status rows (pending / syncing / done / failed + `last_error` text on failed) + "Retry this camera" per-row button + "Resync all cameras" header button. Echo-subscribed to `fras.enrollments` private channel for live updates.

### Enrollment service + jobs + observer

- **D-11:** `App\Services\CameraEnrollmentService` ported from `/Users/helderdene/fras/app/Services/CameraEnrollmentService.php` verbatim with IRMS tweaks:
  - UUID FKs (camera_id / personnel_id are UUIDs, not bigints)
  - Config reads `config('fras.enrollment.batch_size')`, `config('fras.enrollment.ack_timeout_minutes')`, `config('fras.mqtt.topic_prefix')` (not `hds.*`)
  - Each transition broadcasts `EnrollmentProgressed` (event defined in D-25)
  - Methods ported verbatim: `enrollPersonnel`, `enrollAllToCamera`, `upsertBatch`, `buildEditPersonsNewPayload`, `buildDeletePersonsPayload`, `deleteFromAllCameras`, `translateErrorCode` (10-entry error map: 461, 463, 464, 465, 466, 467, 468, 474, 478 + default)
- **D-12:** `App\Jobs\EnrollPersonnelBatch` ported on the `fras` Horizon queue (Phase 19 D-02 registered the supervisor). `WithoutOverlapping('enrollment-camera-{id}')->releaseAfter(30)->expireAfter(300)`. Public constructor: `Camera $camera, array $personnelIds`.
- **D-13:** `App\Observers\PersonnelObserver` triggers enrollment:
  - `saved` hook: if `wasChanged(['photo_hash', 'category'])` → `CameraEnrollmentService::enrollPersonnel($personnel)`. Other field edits (name, phone, address, id_card, birthday) skip MQTT traffic.
  - `deleted` hook: `CameraEnrollmentService::deleteFromAllCameras($personnel)`
  Registered in `AppServiceProvider::boot()` via `Personnel::observe(PersonnelObserver::class)`.
- **D-14:** When admin creates a new `Camera`, trigger `CameraEnrollmentService::enrollAllToCamera($camera)` in `AdminCameraController::store` (after save). Pre-existing personnel list syncs to the new camera on first online.

### ACK correlation — filling in Phase 19 AckHandler

- **D-15:** `CameraEnrollmentService::upsertBatch` writes request-ID correlation to Redis:
  ```php
  Cache::put(
      "enrollment-ack:{$camera->id}:{$messageId}",
      ['camera_id' => $camera->id, 'personnel_ids' => [...], 'photo_hashes' => [...], 'dispatched_at' => now()->toIso8601String()],
      config('fras.enrollment.ack_timeout_minutes') * 60
  );
  ```
  MessageId format: `'EditPersonsNew'.now()->format('Y-m-d\\TH:i:s').'_'.Str::random(6)` (FRAS verbatim).
- **D-16:** Phase 19 `AckHandler` ported only the shell. Phase 20 fills `handle()`:
  1. Parse ACK topic + payload — extract `messageId`, `camera_device_id`, result code (per FRAS ACK spec)
  2. Lookup `Camera::where('device_id', $camera_device_id)->first()` — warning log if unknown
  3. Read cache key `enrollment-ack:{$camera->id}:{$messageId}` — warning log if missing (timeout already fired)
  4. For each `personnel_id` in the cached payload → update `camera_enrollments` row:
     - Success → `status=done`, `enrolled_at=now()`, `photo_hash=<cached>`, `last_error=null`
     - Failure → `status=failed`, `last_error=translateErrorCode($code)`
  5. Broadcast `EnrollmentProgressed` per row
  6. `Cache::forget($key)` to mark correlation consumed
- **D-17:** Per-error-code retry policy:
  - Transient (464, 465, 466) — "camera couldn't resolve/download/read photo" → job releases back to queue via `$this->release(60)` up to 3 attempts (`$tries = 3` on the job class). After exhaustion → `status=failed`.
  - Terminal (461, 463, 467, 468, 474, 478, default) → `status=failed` immediately, surfaced via EnrollmentProgressed with `last_error` text.

### Photo processing + two-namespace URL scheme

- **D-18:** `App\Services\FrasPhotoProcessor` ported from `/Users/helderdene/fras/app/Services/PhotoProcessor.php` with Intervention Image v4 API:
  - `Image::decodePath($file->path())` → `orient()` → `scaleDown(width: 1080, height: 1080)` → `encode(new JpegEncoder(quality: 85))`
  - Quality degradation loop: while encoded byte length > `config('fras.photo.max_size_bytes')` and quality > 40 → reduce quality by 10 and re-encode
  - Hash: `md5((string) $encoded)`
  - Store: `Storage::disk('fras_photos')->put("personnel/{$personnel->id}.jpg", (string) $encoded)` (using Personnel UUID as filename)
  - Return: `['photo_path' => $path, 'photo_hash' => $hash]`
- **D-19:** New `fras_photos` disk in `config/filesystems.php`:
  ```php
  'fras_photos' => [
      'driver' => 'local',
      'root' => storage_path('app/private/fras_photos'),
      'visibility' => 'private',
      'throw' => false,
  ],
  ```
  Env var `FRAS_PHOTO_DISK` reserved for future S3 swap. Mirrors Phase 19 `fras_events` disk pattern.
- **D-20:** **NEW migration required:** `personnel.photo_access_token` uuid column, nullable, unique. Rotated on every photo re-upload via `Str::uuid()`. Crosses Phase 18 "schema frozen" line — justified because Phase 18 did not anticipate the two-namespace URL design when reserving columns. Migration timestamp: `2026_04_2x_add_photo_access_token_to_personnel_table.php`. One-liner up/down.
- **D-21:** Public camera-fetch URL: `/fras/photo/{token}` served by `FrasPhotoAccessController@show`:
  - No auth middleware (unguessable token IS the access control)
  - Lookup `Personnel::where('photo_access_token', $token)->first()` → 404 if not found
  - Verify at-least-one `camera_enrollments::where('personnel_id', $personnel->id)->whereIn('status', ['pending', 'syncing'])->exists()` → 404 if none (revocation)
  - `Log::channel('mqtt')->info('fras.photo.access', ['personnel_id' => ..., 'ip' => request()->ip(), 'ua' => request()->userAgent()])` — DPA audit preamble for Phase 22
  - Stream file from `Storage::disk('fras_photos')->response("personnel/{$personnel->id}.jpg")`
- **D-22:** Operator-facing URL: `/admin/personnel/{personnel}/photo` served by `AdminPersonnelPhotoController@show`:
  - Route gate: `role:operator,supervisor,admin` (responders + dispatchers blocked)
  - Uses `signed` middleware — links generated via `URL::temporarySignedRoute('admin.personnel.photo', now()->addMinutes(5), ['personnel' => $personnel])` in the PersonnelForm.vue Inertia prop
  - Streams from `fras_photos` disk
- **D-23:** URL revocation sequence: admin uploads new photo → `photo_access_token` rotated → prior URL 404s instantly at next camera fetch (no filesystem move, no TTL race). When all enrollments ACK → route-level check fails on "no pending/syncing" → URL 404s.

### Camera watchdog + status state machine

- **D-24:** Keep Phase 18 column name `last_seen_at`. Phase 20 SC5 text references `last_heartbeat_at` (FRAS-verbatim) — update SC5 during planning to reference `last_seen_at` to match the frozen schema.
- **D-25:** `App\Console\Commands\CameraWatchdogCommand` (`irms:camera-watchdog`) scheduled `->everyMinute()` in `routes/console.php`. Iterates `Camera::active()->get()`:
  - gap = `now() - last_seen_at` (or `∞` if null)
  - gap ≤ `config('fras.cameras.degraded_gap_s', 30)` → status=online
  - gap ≤ `config('fras.cameras.offline_gap_s', 90)` → status=degraded
  - else → status=offline
  - On state change → `camera->update(['status' => $new])` + broadcast `CameraStatusChanged`
- **D-26:** Map marker render:
  - `online` → green
  - `degraded` → amber
  - `offline` → gray
  Exact design-system tokens chosen by planner consistent with existing `PRIORITY_COLORS` / `STATUS_COLORS` constants in `useDispatchMap.ts`.
- **D-27:** Phase 19's `OnlineOfflineHandler` continues to write only `online` / `offline` in response to direct camera Online/Offline MQTT messages. Phase 20's watchdog is the ONLY writer of `degraded`. No conflict — watchdog runs every minute, OnlineOfflineHandler is event-driven; last writer wins at the column level.

### Camera deletion rule (SC1)

- **D-28:** `AdminCameraController::destroy()` pre-check blocks soft-decommission when pending/syncing enrollments exist:
  ```php
  $blocked = $camera->enrollments()->whereIn('status', ['pending', 'syncing'])->count();
  if ($blocked > 0) {
      return back()->withErrors(['camera' => "This camera has {$blocked} in-flight enrollments. Wait for them to complete or retry any failed enrollments first."], 422);
  }
  ```
- **D-29:** Hard-delete is explicitly NOT exposed in UI. Admin clicks "Decommission" → sets `decommissioned_at`, scoped out via `scopeActive()`. Matches Unit pattern. `AdminCameraController::recommission($camera)` clears `decommissioned_at` → mirror `AdminUnitController::recommission` verbatim.
- **D-30:** Phase 18 D-22 `ON DELETE CASCADE` on `camera_enrollments.camera_id` FK retained — unreachable under normal operation but semantically correct for Phase 22 retention purge paths.

### BOLO expiry auto-unenroll

- **D-31:** `App\Console\Commands\PersonnelExpireSweepCommand` (`irms:personnel-expire-sweep`) scheduled `->hourly()` in `routes/console.php`.
- **D-32:** Command iterates `Personnel::whereNotNull('expires_at')->where('expires_at', '<', now())->whereNull('decommissioned_at')->get()`:
  - `CameraEnrollmentService::deleteFromAllCameras($personnel)` (fire-and-forget MQTT DeletePersons publish per FRAS D-12)
  - `$personnel->update(['decommissioned_at' => now()])`
  - Mark all `camera_enrollments::where('personnel_id', $personnel->id)` rows `status=done` (represents successful unenroll; FRAS doesn't track ACK on deletes)
  - `Log::channel('mqtt')->info('fras.personnel.expired', [...])` for audit
- **D-33:** Personnel row **preserved** (decommission only). Admin list shows with "Expired" badge + a filter toggle "Hide expired". Hard-delete deferred to Phase 22 DPA retention.
- **D-34:** `expires_at` set in the past at create time → accepted; sweep unenrolls on next hourly run. No special UI block; relies on operator intent.

### Personnel custom_id generation

- **D-35:** `AdminPersonnelController::store` generates `custom_id` once at create:
  ```php
  $validated['custom_id'] = str_replace('-', '', (string) $personnel->id);
  ```
  → 32-char lowercase hex. Globally unique (UUID-derived). Fits Phase 18 D-14 `varchar(48)`. Never changes post-create. Sent to cameras in `EditPersonsNew` payload.

### Broadcast channels + events

- **D-36:** Two new private channels authorized in `routes/channels.php`:
  ```php
  Broadcast::channel('fras.cameras', fn (User $user) => in_array($user->role->value, ['operator','dispatcher','supervisor','admin']));
  Broadcast::channel('fras.enrollments', fn (User $user) => in_array($user->role->value, ['supervisor','admin']));
  ```
- **D-37:** `App\Events\CameraStatusChanged`:
  - Implements `ShouldBroadcast` + `ShouldDispatchAfterCommit`
  - `broadcastOn()`: `new PrivateChannel('fras.cameras')`
  - `broadcastWith()`: `['camera_id' => $camera->id, 'camera_id_display' => $camera->camera_id_display, 'status' => $camera->status->value, 'last_seen_at' => $camera->last_seen_at?->toIso8601String(), 'location' => [lng, lat]]`
  - Dispatched only on state transitions (not every watchdog tick)
- **D-38:** `App\Events\EnrollmentProgressed`:
  - Same base contracts
  - `broadcastOn()`: `new PrivateChannel('fras.enrollments')`
  - `broadcastWith()`: `['personnel_id' => ..., 'camera_id' => ..., 'camera_id_display' => ..., 'status' => ..., 'last_error' => ...]`
  - Fires per row on every transition (pending→syncing→done/failed). At CDRRMO scale (≤8 cameras × ≤200 personnel = ≤1,600 rows per full resync) Reverb throttle handles it.

### Configuration (`config/fras.php` extensions)

- **D-39:** Extend the `config/fras.php` file Phase 19 created with:
  ```php
  'cameras' => [
      'degraded_gap_s' => (int) env('FRAS_CAMERA_DEGRADED_GAP_S', 30),
      'offline_gap_s'  => (int) env('FRAS_CAMERA_OFFLINE_GAP_S', 90),
  ],
  'enrollment' => [
      'batch_size'          => (int) env('FRAS_ENROLLMENT_BATCH_SIZE', 10),
      'ack_timeout_minutes' => (int) env('FRAS_ENROLLMENT_ACK_TIMEOUT_MINUTES', 5),
  ],
  'photo' => [
      'max_dimension' => (int) env('FRAS_PHOTO_MAX_DIMENSION', 1080),
      'jpeg_quality'  => (int) env('FRAS_PHOTO_JPEG_QUALITY', 85),
      'max_size_bytes' => (int) env('FRAS_PHOTO_MAX_SIZE_BYTES', 1_048_576),
  ],
  ```

### Milestone-level updates required during planning

- **D-40:** `.planning/REQUIREMENTS.md §Scope Decisions` line "Map baseline: MapLibre GL JS. `mapbox-gl` explicitly rejected. CI bundle-check must fail if imported." → **replace with** "Map baseline: `mapbox-gl` retained (v1.0 continuity). MapLibre path deprecated." (user directive 2026-04-21 during phase-20 discuss).
- **D-41:** `.planning/ROADMAP.md §Phase 20 Success Criteria #3` — remove "CI bundle-check fails if any code path imports `mapbox-gl`" clause. Retain the heartbeat-watchdog + `CameraStatusChanged` broadcast portion.
- **D-42:** `.planning/ROADMAP.md §Phase 20 Success Criteria #2` — replace "MapLibre camera picker (rewritten from FRAS's Mapbox picker)" with "Mapbox-GL camera picker (port of existing `LocationMapPicker.vue` shape)".
- **D-43:** `.planning/ROADMAP.md §Phase 20 Success Criteria #5` — update `last_heartbeat_at` reference to `last_seen_at` (Phase 18 D-10 column name).

### Claude's Discretion

- Exact camera marker SVG shape + color tokens (online/degraded/offline) — planner picks consistent with `useDispatchMap.ts` existing priority/status color patterns
- Form field ordering + sections in `CameraForm.vue` / `PersonnelForm.vue` — planner mirrors `UnitForm.vue` layout conventions
- Number of retry attempts for transient ACK errors (default 3) — planner picks
- Batch size for pre-existing-personnel sync to a newly added camera — default from `config('fras.enrollment.batch_size', 10)`, planner confirms via FRAS reference
- 422 error copy on blocked camera deletion — planner picks operator-friendly wording matching IRMS validation message tone
- Whether `EnrollmentProgressPanel.vue` lives in `components/fras/` or `components/admin/` — planner picks based on existing admin component locations
- Whether `PersonnelExpireSweep` sweeps all cameras in one command run or batches by camera — planner picks; FRAS doesn't specify, low volume at CDRRMO scale

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 20 goal, requirements, success criteria
- `.planning/ROADMAP.md §Phase 20` — goal, depends-on (Phase 18), 7 success criteria, requirements list (CAMERA-01..06, PERSONNEL-01..07). **NOTE:** D-41/D-42/D-43 require SC text amendments before planning locks.
- `.planning/REQUIREMENTS.md §CAMERA` — CAMERA-01..06 acceptance criteria
- `.planning/REQUIREMENTS.md §PERSONNEL` — PERSONNEL-01..07 acceptance criteria
- `.planning/REQUIREMENTS.md §Scope Decisions` — **NOTE:** D-40 requires line amendment (mapbox-gl stays)

### Phase 18 schema (Phase 20 persists into these tables — no re-migration except photo_access_token)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` — full schema freeze (D-01..D-70)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-05 — `camera_id_display` varchar(10) nullable (Phase 20 fills via regex auto-sequence)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-10 — `last_seen_at` column name (NOT `last_heartbeat_at`)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-14 — `personnel.custom_id` varchar(48) nullable
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-16 — `category` enum `allow|block|missing|lost_child`
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-17 — `expires_at` TIMESTAMPTZ reserved
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-22..D-30 — `camera_enrollments` pivot schema (status enum, UNIQUE camera_id+personnel_id, `last_error` TEXT)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` D-65 — `CameraEnrollmentStatus` enum + `CameraStatus::Degraded` reserved

### Phase 19 shape (Phase 20 extends these artifacts)
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` — full MQTT listener + handler shape
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` D-02 — `fras` Horizon queue supervisor registered but empty (Phase 20 populates via `EnrollPersonnelBatch`)
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` D-08 — Phase 19 writes only `online`/`offline`; Phase 20 owns `degraded` transitions
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` D-14 — unknown-camera RecPush drop rule (relevant because Phase 20 registers new cameras)
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` `AckHandler` reference — Phase 19 ported only the shell; D-16/D-17 here fill it in
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` `config/fras.php` existence — D-39 here extends it

### FRAS source (verbatim port references)
- `/Users/helderdene/fras/app/Http/Controllers/CameraController.php` — Camera CRUD shape (103 lines)
- `/Users/helderdene/fras/app/Http/Controllers/PersonnelController.php` — Personnel CRUD + photo upload shape (174 lines)
- `/Users/helderdene/fras/app/Http/Controllers/EnrollmentController.php` — retry-one-camera + resync-all endpoints (70 lines)
- `/Users/helderdene/fras/app/Services/CameraEnrollmentService.php` — service port target (206 lines, includes buildEditPersonsNewPayload + translateErrorCode)
- `/Users/helderdene/fras/app/Services/PhotoProcessor.php` — FrasPhotoProcessor port target (53 lines, Intervention v3 → upgrade v4 API)
- `/Users/helderdene/fras/app/Jobs/EnrollPersonnelBatch.php` — job port target (44 lines, WithoutOverlapping + middleware)
- `/Users/helderdene/fras/app/Mqtt/Handlers/AckHandler.php` — Phase 20 fills in body
- `/Users/helderdene/fras/resources/js/pages/personnel/Create.vue`, `Edit.vue`, `Index.vue` — reference Vue shape (port to IRMS admin/Personnel.vue + PersonnelForm.vue split)
- `/Users/helderdene/fras/resources/js/pages/cameras/Create.vue`, `Edit.vue`, `Index.vue` — reference Vue shape
- `/Users/helderdene/fras/resources/js/components/MapboxMap.vue` — FRAS Mapbox picker (IRMS already has equivalent in `resources/js/components/intake/LocationMapPicker.vue` — prefer IRMS shape)

### IRMS v1.0 conventions + reference code
- `app/Http/Controllers/Admin/AdminUnitController.php` — controller pattern (index/create/store/edit/update/destroy/recommission); regex auto-sequence at lines 76-80
- `app/Http/Requests/Admin/StoreUnitRequest.php`, `UpdateUnitRequest.php` — form request pattern
- `resources/js/pages/admin/Units.vue` — index page pattern (table + delete/recommission dialogs)
- `resources/js/pages/admin/UnitForm.vue` — shared Create/Edit form page pattern
- `routes/admin.php` — admin resource registration (`Route::resource('units', AdminUnitController::class) + recommission POST`)
- `resources/js/components/intake/LocationMapPicker.vue` — **Mapbox-GL picker reference shape** for camera picker (D-03); reuses Mapbox Studio styles + reverseGeocode
- `resources/js/composables/useDispatchMap.ts` — existing `mapbox-gl` dispatch map instance; D-04 adds cameras layer here
- `resources/js/composables/useGeocodingSearch.ts` — forward-geocoding reference for camera location autocomplete
- `app/Services/BarangayLookupService.php` — PostGIS `ST_Contains` for barangay auto-assignment on camera save (SC2)
- `app/Events/IncidentCreated.php` — ShouldBroadcast + ShouldDispatchAfterCommit + PrivateChannel + broadcastWith reference shape for `CameraStatusChanged` / `EnrollmentProgressed`
- `app/Events/MqttListenerHealthChanged.php` (Phase 19) — closest broadcast-on-dispatch reference
- `routes/channels.php` lines 9-11 — existing `dispatch.incidents` / `dispatch.units` private channel auth pattern
- `routes/console.php` — Schedule facade usage (Phase 19 added `->everyThirtySeconds()` watchdog; Phase 20 adds `->everyMinute()` camera watchdog + `->hourly()` personnel sweep)
- `config/horizon.php` `environments.*.fras-supervisor` block (Phase 19) — Phase 20 jobs land on this queue without config change
- `config/fras.php` (Phase 19 created) — Phase 20 extends with cameras/enrollment/photo sections (D-39)
- `config/filesystems.php` `fras_events` disk (Phase 19) — Phase 20 adds `fras_photos` disk (D-19) in the same file
- `app/Enums/CameraStatus.php`, `PersonnelCategory.php`, `CameraEnrollmentStatus.php` (Phase 18) — enums already exist, Phase 20 uses them
- `package.json` — `mapbox-gl ^3.20.0` dep retained (D-01)

### Carried milestone-level decisions
- `.planning/STATE.md §Accumulated Context` — v2.0 roadmap-level decisions (UUID PKs confirmed, severity→priority mapping, Inertia v2 retained). **NOTE:** mapbox-gl rejection line is overridden by D-01.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`AdminUnitController` pattern** — copy verbatim for `AdminCameraController` + `AdminPersonnelController`. Same index / create / store / edit / update / destroy / recommission shape. Same regex auto-sequence query. Same `StoreXRequest` / `UpdateXRequest` split.
- **`Units.vue` + `UnitForm.vue` split** — copy verbatim for `Cameras.vue` + `CameraForm.vue` + `Personnel.vue` + `PersonnelForm.vue`. Same Dialog-based delete/recommission confirmations. Same `useForm` + Wayfinder action import pattern.
- **`LocationMapPicker.vue`** — Mapbox-GL camera picker ports this verbatim shape: BUTUAN_CENTER + BUTUAN_ZOOM + existing Mapbox Studio styles + `reverseGeocode` via fetch. Only change: emit barangay lookup alongside `update:address`.
- **`useDispatchMap.ts`** — existing mapbox-gl Map instance. Add `cameras` GeoJSON source + symbol layer alongside incidents/units. Reuse icon-loading pattern at lines 58-74.
- **`BarangayLookupService`** — PostGIS `ST_Contains` barangay assignment; call from `AdminCameraController::store` after location save.
- **`IncidentCreated` event pattern** — copy for `CameraStatusChanged` + `EnrollmentProgressed`. Same ShouldBroadcast + ShouldDispatchAfterCommit + PrivateChannel + broadcastWith().
- **`MqttListenerHealthChanged` event** (Phase 19) — recent broadcast-on-schedule reference; watchdog command dispatches on state transition.
- **Phase 19 `fras-supervisor` Horizon block** — Phase 20 `EnrollPersonnelBatch` dispatches to `'fras'` queue, consumed by this supervisor. No config change.
- **Phase 19 `mqtt` log channel** — Phase 20 photo access logs + enrollment errors log to this channel for operational coherence.

### Established Patterns
- **`Route::resource` + extra POST for recommission** — `routes/admin.php` convention. Phase 20 extends with `cameras` + `personnel` resources + matching recommission routes + signed photo route.
- **Form request validation** — `app/Http/Requests/Admin/Store*Request.php` array-style rules, authorize via middleware, type-declare return via `$request->validated()`.
- **Inertia shared prop for initial state** — `DispatchConsoleController::index` already wires initial data; Phase 20 adds `cameras` prop for map layer + optional `mqtt_listener_health` already wired.
- **PHP enum backed by string column** — Phase 18 already defined the four enums; Phase 20 uses them via model `$casts` (already set in Phase 18 D-64).
- **Scoped query `scopeActive`** — Phase 18 D-66 declared; Phase 20 uses in AdminController::index to filter decommissioned cameras/personnel.
- **Observer registration in `AppServiceProvider::boot()`** — standard Laravel pattern; Phase 20 registers `PersonnelObserver`.
- **Echo subscription in composables** — e.g., `useDispatchFeed.ts` Echo pattern; Phase 20's `EnrollmentProgressPanel.vue` subscribes to `fras.enrollments` directly or via a new `useEnrollmentProgress.ts` composable.

### Integration Points
- `app/Http/Controllers/Admin/AdminCameraController.php` (NEW)
- `app/Http/Controllers/Admin/AdminPersonnelController.php` (NEW)
- `app/Http/Controllers/Admin/AdminPersonnelPhotoController.php` (NEW, signed-URL operator surface)
- `app/Http/Controllers/Fras/FrasPhotoAccessController.php` (NEW, public token-gated camera-fetch)
- `app/Http/Requests/Admin/StoreCameraRequest.php`, `UpdateCameraRequest.php` (NEW)
- `app/Http/Requests/Admin/StorePersonnelRequest.php`, `UpdatePersonnelRequest.php` (NEW)
- `app/Services/CameraEnrollmentService.php` (NEW, port from FRAS)
- `app/Services/FrasPhotoProcessor.php` (NEW, port from FRAS PhotoProcessor with Intervention v4)
- `app/Jobs/EnrollPersonnelBatch.php` (NEW, port from FRAS)
- `app/Observers/PersonnelObserver.php` (NEW)
- `app/Events/CameraStatusChanged.php` (NEW)
- `app/Events/EnrollmentProgressed.php` (NEW)
- `app/Console/Commands/CameraWatchdogCommand.php` (NEW)
- `app/Console/Commands/PersonnelExpireSweepCommand.php` (NEW)
- `resources/js/pages/admin/Cameras.vue` (NEW), `CameraForm.vue` (NEW)
- `resources/js/pages/admin/Personnel.vue` (NEW), `PersonnelForm.vue` (NEW)
- `resources/js/components/fras/EnrollmentProgressPanel.vue` (NEW)
- `resources/js/components/admin/CameraLocationPicker.vue` (NEW, ports LocationMapPicker + emits barangay)
- `resources/js/composables/useEnrollmentProgress.ts` (NEW)
- `resources/js/composables/useDispatchMap.ts` (MOD — add cameras layer)
- `resources/js/pages/dispatch/Console.vue` (MOD — camera layer toggle UI)
- `routes/admin.php` (MOD — add cameras + personnel resources + recommission + signed photo route)
- `routes/channels.php` (MOD — add `fras.cameras` + `fras.enrollments` gates)
- `routes/console.php` (MOD — schedule camera-watchdog + personnel-expire-sweep)
- `config/fras.php` (MOD — extend with cameras/enrollment/photo config)
- `config/filesystems.php` (MOD — add `fras_photos` disk)
- `database/migrations/2026_04_2x_add_photo_access_token_to_personnel_table.php` (NEW)
- `bootstrap/app.php` or `AppServiceProvider::boot()` (MOD — register PersonnelObserver)
- `app/Models/Personnel.php` (MOD — add `photo_access_token` to `$fillable`/`$casts` + `getEnrollmentsAttribute` relation)
- `app/Models/Camera.php` (MOD — add `enrollments()` relation)
- `tests/Feature/Admin/AdminCameraControllerTest.php` (NEW)
- `tests/Feature/Admin/AdminPersonnelControllerTest.php` (NEW)
- `tests/Feature/Fras/CameraEnrollmentServiceTest.php` (NEW)
- `tests/Feature/Fras/FrasPhotoProcessorTest.php` (NEW)
- `tests/Feature/Fras/AckHandlerTest.php` (MOD — Phase 19 stub test extended with ACK cache round-trip)
- `tests/Feature/Fras/CameraWatchdogTest.php` (NEW)
- `tests/Feature/Fras/PersonnelExpireSweepTest.php` (NEW)
- `tests/Feature/Fras/FrasPhotoAccessControllerTest.php` (NEW — token revocation coverage)

### Known touchpoints that DO NOT change in Phase 20
- `app/Http/Controllers/Admin/AdminUnitController.php` — reference only, not edited
- `useDispatchFeed.ts` — cameras are dispatch-map-only, not part of the incident feed
- Phase 18 migrations — frozen, except the new `photo_access_token` migration which is additive
- `config/horizon.php` — `fras-supervisor` block stays as Phase 19 left it
- `config/mqtt-client.php` — Phase 19 published subscriber + publisher connections; Phase 20 reuses via `MQTT::connection('publisher')`
- `resources/js/composables/useAnalyticsMap.ts`, `resources/js/components/intake/LocationMapPicker.vue`, `StandbyScreen.vue`, `NavTab.vue` — unchanged (no MapLibre migration, D-05)

</code_context>

<specifics>
## Specific Ideas

- **"mapbox-gl stays."** The single most load-bearing decision. Overrides the v2.0 milestone scope line, drops SC3 CI bundle-check, saves a migration-shaped scope balloon. Camera picker and dispatch-map camera layer both live on the same mapbox-gl instance v1.0 already ships. REQUIREMENTS.md + ROADMAP.md text gets amended during planning (D-40..D-43).
- **"Port FRAS verbatim where possible; add IRMS-native tweaks at seams."** CameraEnrollmentService, EnrollPersonnelBatch, PhotoProcessor, AckHandler body — all port the FRAS shape. Only diverge for UUID FKs, config path (`fras.*` not `hds.*`), broadcast events, and Intervention v4 API. Matches Phase 19's successful FRAS-verbatim strategy.
- **"Route-level revocation, no filesystem move."** Public photo URL stays pointing at the same disk file; route checks enrollment state on every request. Rotation of `photo_access_token` on re-upload auto-invalidates cached camera fetches. Simpler than filesystem juggling; stronger DPA posture than TTL-only.
- **"Only photo_hash or category changes trigger re-enrollment."** Prevents MQTT storms on typo-fixes. FRAS naïvely enrolls on every save; IRMS uses `wasChanged(['photo_hash', 'category'])` gate in PersonnelObserver.
- **"Degraded is owned by the watchdog, not by Phase 19's OnlineOfflineHandler."** Phase 19 keeps writing online/offline on explicit MQTT Online/Offline messages; Phase 20's watchdog writes degraded on gap thresholds. Last-writer-wins is fine because they observe different signals.
- **"Personnel row is decommissioned, not hard-deleted, on expire sweep."** History preservation + admin visibility (Expired badge) + audit trail. Hard-delete is Phase 22's DPA right-to-erasure concern.
- **"Redis cache is the only ACK correlation store."** No new `enrollment_ack_log` table. Matches FRAS. Cache TTL = ack_timeout_minutes × 60. Expired correlations → enrollment stays `syncing` forever — operator hits "Retry this camera" to re-publish.
- **"Camera auto-ID follows the Unit regex pattern."** `SUBSTRING('[0-9]+$') AS INTEGER)` for MAX+1 in AdminCameraController::store. Matches v1.0 AMB-01/FIRE-02. 99 cameras max with 2-digit format (CAM-01..CAM-99); if that ceiling ever bites, upgrade format via a follow-up migration.
- **"custom_id = UUID without dashes."** 32-char hex lowercase, derived from Personnel.id. Deterministic (regeneratable from PK). Fits FRAS firmware's 48-char column. No sequencing, no collision risk.
- **"EnrollmentProgressPanel subscribes to fras.enrollments — not the whole admin page."** Only the panel component opens the Echo subscription, and only on personnel edit page. No subscription on index page (no live rows to update there). Bounded WS pressure.

</specifics>

<deferred>
## Deferred Ideas

- **Full mapbox-gl → maplibre-gl migration** — user directive 2026-04-21 reverted the v2.0 decision. Can be revisited in v2.1+ if Mapbox token costs, DPA posture around tile requests, or vendor independence become concerns. See D-01 for full rationale.
- **`/admin/enrollments` fleet-wide dashboard** — considered for cross-personnel visibility; rejected in favor of per-personnel inline panel at CDRRMO scale. Reconsider if Phase 22 alert volume demands a "fleet enrollment health" surface.
- **Debounced/batched `EnrollmentProgressed` broadcast** — at CDRRMO scale (≤1,600 rows) per-row broadcast works. Revisit if Phase 22 monitoring shows Reverb throttle stress under 50+ camera bulk operations.
- **Hard-delete personnel on expiry** — Phase 22 DPA retention rules own this decision. Phase 20 preserves personnel rows with `decommissioned_at`.
- **`EnrollmentAckLog` audit table** — Phase 22 DPA compliance may require durable ACK audit history. Phase 20 uses Redis cache only.
- **WebP photo encoding** — FRAS firmware contract requires JPEG. Rejected.
- **Signed operator photo URL TTL tuning** — defaulted to 5 minutes; tune in Phase 22 if operator UX shows friction.
- **`CameraForm.vue` advanced fields** — RTSP URL, credentials, firmware version. FRAS firmware doesn't expose these over MQTT; add only if a concrete operational need emerges.
- **Personnel photo thumbnail column** — FRAS has `photo_hash` but no thumbnail. Defer until admin list pagination UX shows filmstrip need.
- **Camera marker clustering on dispatch map** — at 8-camera CDRRMO scale, clustering is overkill. Revisit if fleet ever exceeds ~30.
- **Camera deletion via API (hard-delete force flag)** — UI blocks hard-delete; if an operational need arises, surface via a supervisor-only action + confirmation. Not in Phase 20 scope.
- **Force-decommission bypass** — if a camera is permanently dead while enrollments are pending, admin currently has no clean path. Defer; consider adding "force decommission (marks pending enrollments as failed)" admin action if operational feedback shows need.
- **Multi-cascade personnel edit (edit affects all cameras transactionally)** — FRAS doesn't guarantee this either. Deferred unless Phase 22 alert-ing reveals split-brain scenarios.
- **`PersonnelExpireSweep` telemetry to dispatch banner** — if sweep fails or lags, surface via banner. Defer; Phase 22 observability territory.
- **`custom_id` format upgrade to human-readable (CDRRMO-001)** — deterministic UUID-hex works for firmware + audit. Human readability not needed in logs. Defer unless operator friction emerges.

</deferred>

---

*Phase: 20-camera-personnel-admin-enrollment*
*Context gathered: 2026-04-21*
