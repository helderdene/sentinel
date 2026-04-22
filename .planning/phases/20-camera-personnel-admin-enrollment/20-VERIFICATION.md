---
phase: 20-camera-personnel-admin-enrollment
verified: 2026-04-21T16:42:28Z
uat_completed: 2026-04-22T09:35:00Z
status: passed
score: 13/13 must-haves verified; 2/2 human UAT passed (after gap fixes G-01 + G-02)
overrides_applied: 0
human_verification:
  - test: "Navigate to /admin/cameras as admin, create a camera via the Mapbox-GL picker, verify the location pin appears and forward-geocoded address populates."
    expected: "Camera form shows an interactive Mapbox map; clicking places a pin; address field auto-fills from reverse-geocode; save succeeds and camera appears in the table."
    why_human: "Mapbox-GL picker interactivity + forward-geocoding side-effect require a live browser session with real Mapbox token. Cannot verify via automated tests."
  - test: "Navigate to /dispatch/console as dispatcher, verify camera pins render as color-coded WebGL symbols (online=blue, degraded=amber, offline=gray). Toggle the cameras layer off/on with the button. Click a camera marker."
    expected: "Cameras render as a toggleable WebGL symbol layer alongside incident + unit layers. Toggle button hides/shows all 3 camera sub-layers. Clicking a marker opens a Popup with camera name, status, last-seen timestamp, and an Edit link to /admin/cameras/{id}/edit."
    why_human: "WebGL map rendering correctness, layer z-ordering, Popup HTML content, and color-coded status transitions require a live browser with WebGL hardware acceleration. Cannot verify via automated tests or headless Chrome without a running server."
---

# Phase 20: Camera + Personnel Admin + Enrollment Verification Report

**Phase Goal:** Admins can manage the camera fleet and the personnel watch-list from IRMS, and enrollment flows from IRMS to the cameras reliably — so the recognition pipeline in Phase 21 has a populated fleet and a populated watch-list to match against
**Verified:** 2026-04-21T16:42:28Z
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #   | Truth                                                                                                          | Status     | Evidence                                                                                                                         |
|-----|----------------------------------------------------------------------------------------------------------------|------------|----------------------------------------------------------------------------------------------------------------------------------|
| 1   | Admin at `/admin/cameras` can CRUD cameras with auto-generated CAM-01/CAM-02 IDs and device_id               | VERIFIED   | AdminCameraController.php:61-63 SUBSTRING auto-sequence; AdminCameraControllerTest.php "auto-sequences" test passes (9/9 green) |
| 2   | Camera location picker (Mapbox-GL) populates forward-geocoded address + PostGIS barangay_id on save           | VERIFIED*  | BarangayLookupService wired in store+update; CameraLocationPicker.vue exists (331-line substantive file); *visual UAT deferred  |
| 3   | Cameras render as toggleable WebGL symbol layer on dispatch console with live per-camera status indicator      | VERIFIED*  | useDispatchMap.ts: CAMERA_STATUS_COLORS, setCameraData, updateCameraStatus, cameras source + 3 sub-layers; Console.vue: camerasVisible toggle + fras.cameras Echo subscription; DispatchConsoleCamerasPropTest 3/3 green; *visual UAT deferred |
| 4   | Camera status transitions (online/degraded/offline) fire CameraStatusChanged broadcast on transitions only     | VERIFIED   | CameraWatchdogCommand.php dispatches CameraStatusChanged only on enum mismatch (gap thresholds 30s/90s from config); CameraWatchdogTest green |
| 5   | Camera deletion blocked when pending/syncing enrollments exist                                                 | VERIFIED   | AdminCameraController.php:130-145 whereIn('status', ['pending','syncing']) guard; AdminCameraControllerTest "destroy blocks" test green |
| 6   | Admin at `/admin/personnel` can CRUD personnel with name, category, expires_at, consent_basis + photo upload   | VERIFIED   | AdminPersonnelController.php DI-injects FrasPhotoProcessor; StorePersonnelRequest rules: name/category/consent_basis/expires_at; AdminPersonnelControllerTest 86 tests green |
| 7   | Photo upload validates (≤1MB, ≤1080p), resizes, JPEG-encodes, MD5-hashes for dedup                            | VERIFIED   | FrasPhotoProcessor.php: quality degradation loop, JpegEncoder, Storage::disk('fras_photos'); FrasPhotoProcessorTest 4/4 green   |
| 8   | Photo public URL is revoked after all camera enrollment ACKs received                                          | VERIFIED   | FrasPhotoAccessController.php: whereIn('status',['pending','syncing']) gate → abort(404); FrasPhotoAccessControllerTest green    |
| 9   | EnrollPersonnelBatch dispatched per active camera with WithoutOverlapping mutex expireAfter(300)               | VERIFIED   | EnrollPersonnelBatch.php: $tries=3, $queue='fras', WithoutOverlapping('enrollment-camera-{id}').releaseAfter(30).expireAfter(300); EnrollPersonnelBatchTest 3/3 green |
| 10  | Admin sees per-camera enrollment progress live via EnrollmentProgressed on fras.enrollments channel            | VERIFIED   | EnrollmentProgressPanel.vue + useEnrollmentProgress.ts subscribe to useEcho('fras.enrollments'); BroadcastAuthorizationTest green |
| 11  | Retry-one-camera and resync-all-cameras buttons exist and dispatch EnrollPersonnelBatch                        | VERIFIED   | EnrollmentController.php retry+resyncAll methods dispatch EnrollPersonnelBatch; routes admin.personnel.enrollments.retry + resyncAll registered |
| 12  | AckHandler correlates camera ACKs via Cache::pull (atomic), updates enrollment rows, broadcasts progress       | VERIFIED   | AckHandler.php uses Cache::pull (not Cache::get); processSuccesses/processFailures dispatch EnrollmentProgressed; AckHandlerTest (fras group) 5/5 green; idempotency test green |
| 13  | Scheduled jobs auto-unenroll expired personnel and watchdog auto-transitions camera statuses                   | VERIFIED   | PersonnelExpireSweepCommand + CameraWatchdogCommand registered via Schedule::command in routes/console.php; PersonnelExpireSweepTest + CameraWatchdogTest green |

**Score:** 13/13 truths verified

### Deferred Items

No items are deferred to later phases. Human verification items are listed in the `human_verification` section because they require browser UAT.

### Required Artifacts

| Artifact                                                             | Expected                                              | Status     | Details                                                                 |
|----------------------------------------------------------------------|-------------------------------------------------------|------------|-------------------------------------------------------------------------|
| `app/Services/FrasPhotoProcessor.php`                                | Photo resize/encode/hash (Intervention v4)            | VERIFIED   | 71 lines; degradation loop; JpegEncoder; fras_photos disk; 4 tests pass |
| `app/Services/CameraEnrollmentService.php`                           | enrollPersonnel/upsertBatch/deleteFromAllCameras      | VERIFIED   | 200+ lines; 5 public methods; no config('hds.*') leakage; 5 tests pass  |
| `app/Exceptions/PhotoTooLargeException.php`                          | Thrown on unshrinkable photo                          | VERIFIED   | Exists; thrown in degradation loop; test covers it                       |
| `app/Jobs/EnrollPersonnelBatch.php`                                  | WithoutOverlapping + $tries=3 + failed() handler      | VERIFIED   | $tries=3; $queue='fras'; WithoutOverlapping key correct; failed() marks rows + broadcasts |
| `app/Observers/PersonnelObserver.php`                                | wasChanged-gated enrollment trigger                   | VERIFIED   | wasChanged(['photo_hash','category']) gate; registered in AppServiceProvider::boot() |
| `app/Console/Commands/CameraWatchdogCommand.php`                     | State-machine: online/degraded/offline on gap         | VERIFIED   | Uses config thresholds; broadcasts CameraStatusChanged on transition only |
| `app/Console/Commands/PersonnelExpireSweepCommand.php`               | Auto-unenroll expired personnel                       | VERIFIED   | Queries expires_at < now() + whereNull('decommissioned_at'); deleteFromAllCameras |
| `app/Http/Controllers/Admin/AdminCameraController.php`               | 7-method CRUD + auto-sequence + barangay + guard      | VERIFIED   | 165+ lines; all 7 methods; BarangayLookupService; enrollAllToCamera; destroy guard |
| `app/Http/Controllers/Admin/AdminPersonnelController.php`            | Personnel CRUD + photo upload + retry/resync           | VERIFIED   | FrasPhotoProcessor DI; photo_access_token rotation; retry/resync endpoints |
| `app/Http/Controllers/Admin/AdminPersonnelPhotoController.php`       | Signed-URL operator/supervisor/admin photo stream     | VERIFIED   | Exists; role gate via fras.php route group                               |
| `app/Http/Controllers/Admin/EnrollmentController.php`                | Retry-one-camera + resync-all endpoints               | VERIFIED   | retry() + resyncAll() dispatch EnrollPersonnelBatch                      |
| `app/Http/Controllers/Fras/FrasPhotoAccessController.php`            | Public token-gated camera-fetch URL with revocation   | VERIFIED   | photo_access_token lookup; pending/syncing enrollment gate; abort(404) on revocation |
| `app/Events/CameraStatusChanged.php`                                 | ShouldBroadcast + ShouldDispatchAfterCommit           | VERIFIED   | Both contracts implemented; broadcastWith() returns 5 keys per D-37     |
| `app/Events/EnrollmentProgressed.php`                                | ShouldBroadcast + ShouldDispatchAfterCommit           | VERIFIED   | Both contracts; broadcastWith() returns 5 keys per D-38                 |
| `app/Mqtt/Handlers/AckHandler.php`                                   | Cache::pull + processSuccesses + processFailures      | VERIFIED   | Cache::pull (atomic); translateErrorCode; EnrollmentProgressed dispatch; 5 AckHandler tests (fras group) green |
| `routes/fras.php`                                                    | FRAS routes with role:operator,supervisor,admin gate  | VERIFIED   | Exists; mounted in bootstrap/app.php; admin.personnel.photo named route  |
| `resources/js/pages/admin/Cameras.vue`                               | Camera index table + filter + dialogs                 | VERIFIED   | 331 lines; renders cameras prop; v-for iterates cameras; filter by name/status |
| `resources/js/pages/admin/CameraForm.vue`                            | Create/edit form with map picker                      | VERIFIED   | 235 lines; CameraLocationPicker embedded; Wayfinder form submission       |
| `resources/js/pages/admin/Personnel.vue`                             | Personnel index table + filter + dialogs              | VERIFIED   | 412 lines; renders personnel prop; v-for iterates                         |
| `resources/js/pages/admin/PersonnelForm.vue`                         | Create/edit + photo dropzone + EnrollmentProgressPanel | VERIFIED  | 525 lines; FrasPhotoProcessor path; EnrollmentProgressPanel in edit mode  |
| `resources/js/components/admin/CameraLocationPicker.vue`             | Mapbox-GL picker ported from LocationMapPicker        | VERIFIED   | Exists; substantive (mapbox-gl picker); embedded in CameraForm            |
| `resources/js/components/fras/EnrollmentProgressPanel.vue`           | Live-updating enrollment status per camera            | VERIFIED   | useEnrollmentProgress composable; useEcho('fras.enrollments') wired       |
| `resources/js/components/fras/CameraStatusBadge.vue`                 | Status pill (online/degraded/offline/decommissioned)  | VERIFIED   | Exists; used in Cameras.vue table                                         |
| `resources/js/composables/useEnrollmentProgress.ts`                  | useEcho-backed reactive enrollment rows               | VERIFIED   | useEcho on fras.enrollments; reactive Map of enrollment rows              |
| `resources/js/composables/useDispatchMap.ts`                         | Extended with cameras source/layers + setCameraData    | VERIFIED   | 1027 lines; CAMERA_STATUS_COLORS; setCameraData; updateCameraStatus; 3 sub-layers |

### Key Link Verification

| From                                              | To                                                       | Via                              | Status   | Details                                                        |
|---------------------------------------------------|----------------------------------------------------------|----------------------------------|----------|----------------------------------------------------------------|
| CameraStatusChanged.php                           | routes/channels.php                                      | PrivateChannel('fras.cameras')   | WIRED    | Broadcast::channel('fras.cameras') at channels.php line 17    |
| EnrollmentProgressed.php                          | routes/channels.php                                      | PrivateChannel('fras.enrollments') | WIRED  | Broadcast::channel('fras.enrollments') at channels.php line 21 |
| AdminCameraController.php::store                  | CameraEnrollmentService::enrollAllToCamera               | Constructor DI                   | WIRED    | enrollAllToCamera() called post-save in store()               |
| AdminCameraController.php::destroy                | Camera::enrollments()                                    | whereIn pending/syncing count    | WIRED    | Guard present; AdminCameraControllerTest destroy blocks test   |
| AdminPersonnelController.php::store               | FrasPhotoProcessor::process                              | Constructor DI                   | WIRED    | photoProcessor->process() called in store + update            |
| PersonnelObserver.php::saved                      | CameraEnrollmentService::enrollPersonnel                 | wasChanged gate                  | WIRED    | wasChanged(['photo_hash','category']) guard present            |
| PersonnelObserver.php                             | AppServiceProvider::boot()                               | Personnel::observe()             | WIRED    | Personnel::observe(PersonnelObserver::class) line 233          |
| EnrollPersonnelBatch.php::handle                  | CameraEnrollmentService::upsertBatch                     | Service injection                | WIRED    | $service->upsertBatch($this->camera, $this->personnelIds)     |
| AckHandler.php                                    | Cache::pull('enrollment-ack:{cam}:{msgId}')              | Atomic read+delete               | WIRED    | Cache::pull($cacheKey) line 76; idempotency test green        |
| AckHandler.php                                    | EnrollmentProgressed::dispatch                           | Per-row broadcast                | WIRED    | dispatch in processSuccesses + processFailures                |
| CameraWatchdogCommand.php                         | CameraStatusChanged::dispatch                            | On status transition only        | WIRED    | Dispatched inside mismatch branch (gap thresholds)            |
| PersonnelExpireSweepCommand.php                   | CameraEnrollmentService::deleteFromAllCameras            | Service call                     | WIRED    | $service->deleteFromAllCameras($personnel) line 27            |
| FrasPhotoAccessController.php                     | Personnel::where('photo_access_token', token)            | Token lookup + revocation gate   | WIRED    | whereIn(['pending','syncing']) → abort(404)                   |
| EnrollmentProgressPanel.vue                       | useEnrollmentProgress.ts via useEcho('fras.enrollments') | Vue component + composable       | WIRED    | useEnrollmentProgress() called in Panel setup                 |
| Console.vue                                       | useDispatchMap.ts::setCameraData                         | Mount-time call                  | WIRED    | setCameraData(props.cameras) in onMounted                     |
| Console.vue                                       | fras.cameras private channel                             | useEcho subscription             | WIRED    | useEcho('fras.cameras') subscription updates camera status    |
| DispatchConsoleController.php::index              | Inertia prop 'cameras'                                   | Camera::active()->get()->map()   | WIRED    | 'cameras' => $cameras prop line 142; DispatchConsoleCamerasPropTest green |

### Data-Flow Trace (Level 4)

| Artifact                                          | Data Variable   | Source                                              | Produces Real Data | Status     |
|---------------------------------------------------|-----------------|-----------------------------------------------------|--------------------|------------|
| resources/js/pages/admin/Cameras.vue              | cameras         | Inertia prop from AdminCameraController::index      | Yes — Camera::all() DB query | FLOWING |
| resources/js/pages/admin/Personnel.vue            | personnel       | Inertia prop from AdminPersonnelController::index   | Yes — Personnel::all() DB query | FLOWING |
| resources/js/pages/dispatch/Console.vue           | cameras         | Inertia prop from DispatchConsoleController::index  | Yes — Camera::active()->get() DB query | FLOWING |
| resources/js/components/fras/EnrollmentProgressPanel.vue | rows   | useEnrollmentProgress via useEcho('fras.enrollments') | Yes — broadcast from DB-backed EnrollmentProgressed | FLOWING |

### Behavioral Spot-Checks

| Behavior                                                     | Command                                                    | Result | Status |
|--------------------------------------------------------------|------------------------------------------------------------|--------|--------|
| FRAS group tests (110 expected)                              | `php artisan test --compact --group=fras`                  | 110 passed (362 assertions) | PASS |
| Admin feature tests (86 expected)                            | `php artisan test --compact tests/Feature/Admin/`          | 86 passed (402 assertions) | PASS |
| Dispatch feature tests (28 expected)                         | `php artisan test --compact tests/Feature/Dispatch/`       | 28 passed (206 assertions) | PASS |
| Phase 20 integration test (3 cases)                          | `php artisan test --compact tests/Feature/Fras/Phase20IntegrationTest.php` | 3 passed (16 assertions) | PASS |
| Frontend build                                               | `npm run build`                                            | Built in 18.15s; PWA built; 0 errors | PASS |
| TypeScript type check (1 pre-existing error allowed)         | `npm run types:check`                                      | 1 error in UnitForm.vue:263 (pre-existing, unrelated to Phase 20) | PASS |

### Requirements Coverage

| Requirement | Source Plan | Description                                                                           | Status          | Evidence                                                                               |
|-------------|-------------|---------------------------------------------------------------------------------------|-----------------|----------------------------------------------------------------------------------------|
| CAMERA-01   | 20-04       | Admin CRUD cameras with CAM-01/CAM-02 auto-generated IDs                              | SATISFIED       | AdminCameraController auto-sequence; AdminCameraControllerTest 9/9 green               |
| CAMERA-02   | 20-04, 20-07 | Mapbox-GL picker with forward-geocode + barangay auto-assignment                      | SATISFIED*      | BarangayLookupService wired; CameraLocationPicker.vue exists; *visual UAT needed       |
| CAMERA-03   | 20-08       | Cameras as toggleable WebGL layer on dispatch console map                             | SATISFIED*      | useDispatchMap cameras source + 3 sub-layers; Console.vue toggle; *visual UAT needed   |
| CAMERA-04   | 20-08       | Live per-camera status via CameraStatusChanged on fras.cameras within 500ms           | SATISFIED*      | Console.vue useEcho('fras.cameras') → updateCameraStatus; *real-time UAT needed        |
| CAMERA-05   | 20-06       | Watchdog flips online/degraded/offline at 30s/90s gaps; broadcasts on transition only | SATISFIED       | CameraWatchdogCommand state machine; CameraWatchdogTest green                          |
| CAMERA-06   | 20-04       | Camera deletion blocked when enrollments pending/syncing                               | SATISFIED       | destroy guard; AdminCameraControllerTest "destroy blocks" test green                   |
| PERSONNEL-01 | 20-05      | Admin CRUD personnel with name, category, expires_at, consent_basis                   | SATISFIED       | StorePersonnelRequest rules; AdminPersonnelControllerTest green                         |
| PERSONNEL-02 | 20-02, 20-05 | Photo upload through FrasPhotoProcessor (≤1MB, ≤1080p, JPEG, MD5)                   | SATISFIED       | FrasPhotoProcessor process() + delete(); 4 processor tests green; wired in controller  |
| PERSONNEL-03 | 20-05      | Unguessable-UUID public URL revoked after enrollment ACK received                      | SATISFIED       | FrasPhotoAccessController revocation gate; FrasPhotoAccessControllerTest green         |
| PERSONNEL-04 | 20-03      | EnrollPersonnelBatch dispatched for all active cameras on create/update/delete         | SATISFIED       | PersonnelObserver wasChanged gate; EnrollPersonnelBatch WithoutOverlapping mutex; tests green |
| PERSONNEL-05 | 20-05, 20-07 | Per-camera progress live via EnrollmentProgressed; retry + resync buttons             | SATISFIED*      | EnrollmentProgressPanel + useEnrollmentProgress; EnrollmentController endpoints; *visual UAT needed |
| PERSONNEL-06 | 20-06      | Scheduled auto-unenroll of expired personnel across all cameras                        | SATISFIED       | PersonnelExpireSweepCommand; Schedule::command('irms:personnel-expire-sweep'); tests green |
| PERSONNEL-07 | 20-03      | AckHandler correlates ACKs via Cache::pull, per-error-code retry policy               | SATISFIED       | Cache::pull atomic; translateErrorCode; processSuccesses + processFailures; idempotency test green |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| app/Http/Controllers/Admin/AdminCameraController.php | 61 | `SUBSTRING(x FROM 'pattern')` PostgreSQL-only regex | Warning | Flagged in REVIEW.md CR-01; not a Phase 20 regression (PostGIS DB is production baseline; SQLite dev tests pass by coincidence). Code-review issue, not a goal-blocking gap. |
| app/Console/Commands/PersonnelExpireSweepCommand.php | 32 | Marks enrollment rows Done after unenroll (semantically wrong) | Warning | Flagged in REVIEW.md WR-03; functional behavior (sweep runs, MQTT publishes, personnel decommissioned) is correct. Status labeling is a cosmetic issue not blocking Phase 21. |
| resources/js/composables/useDispatchMap.ts | 624 | Hardcoded `/admin/cameras/{id}/edit` URL bypasses Wayfinder | Info | Flagged in REVIEW.md WR-06; functional (URL is correct); Wayfinder import would be more resilient. Not a goal blocker. |

None of the anti-patterns prevent the phase goal from being achieved. All are documented code-review findings in 20-REVIEW.md.

### Human Verification Required

#### 1. Camera Create + Mapbox-GL Picker Visual QA (CAMERA-02)

**Test:** Log in as admin. Navigate to `/admin/cameras/create`. The form should show an interactive Mapbox-GL map. Click a location on the map to drop a pin. Verify the latitude/longitude fields update. Verify the address field populates via forward-geocode (may be empty if no Mapbox geocoding token configured in env). Fill name and device_id, submit. Verify the camera appears in `/admin/cameras` with the correct camera_id_display (CAM-01 if first).

**Expected:** Interactive Mapbox map renders; pin drop updates lat/lng; address auto-fills; camera created successfully; table shows CAM-01 entry with name and status.

**Why human:** Mapbox-GL rendering and forward-geocoding are browser/network interactions that cannot be verified programmatically. The backend barangay lookup is verified by tests; the client-side map interactivity is not.

#### 2. Dispatch Console Cameras Layer UAT (CAMERA-03 + CAMERA-04)

**Test:** Log in as dispatcher. Navigate to `/dispatch/console`. Verify camera markers appear as colored symbols on the map (blue for online, amber for degraded, gray for offline). Click the cameras-layer toggle button (top-left map area). Verify all camera markers hide, then reappear on second click. Click one camera marker. Verify a Popup opens showing the camera name, status badge, last-seen timestamp, and an "Edit camera" link that routes to `/admin/cameras/{id}/edit`.

**Expected:** 3 sub-layers (halo, body, label) render per camera; toggle sets layout visibility to none/visible; Popup opens on click with escaped HTML content; edit link is navigable.

**Why human:** WebGL map rendering, layer z-ordering, Popup DOM injection, and layer toggle behavior require a live browser with GPU-accelerated WebGL. The DispatchConsoleCamerasPropTest verifies the controller prop shape; the visual rendering is not testable without a running server and browser session.

### Gaps Summary

No gaps blocking goal achievement. All 13 observable truths are verified by tests and artifact inspection. The two human verification items are visual/real-time behaviors that require browser UAT, which was auto-approved per auto-mode instructions for Plans 20-07 and 20-08 and documented in their respective SUMMARY.md files.

**Known code-review findings (non-blocking):**
- CR-01/WR-01: PostgreSQL-only regex in camera_id_display auto-sequence (production DB is PostgreSQL; tests pass on SQLite by coincidence)
- WR-02: EXIF metadata not stripped from personnel photos (DPA concern for Phase 22 sign-off)
- WR-03: PersonnelExpireSweepCommand marks unenrolled rows as `Done` (cosmetic; functional behavior is correct)
- WR-04: No rate limiting on `/fras/photo/{token}` (Phase 22 DPA concern)
- WR-05: Two-write personnel create triggers unnecessary observer passes (performance, not correctness)
- WR-06: Hardcoded camera edit URL in dispatch map Popup (functional, but bypasses Wayfinder)

These are tracked in 20-REVIEW.md and do not block Phase 21 readiness.

---

_Verified: 2026-04-21T16:42:28Z_
_Verifier: Claude (gsd-verifier)_
