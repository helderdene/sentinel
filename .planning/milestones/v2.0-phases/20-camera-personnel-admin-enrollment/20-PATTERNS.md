# Phase 20: Camera + Personnel Admin + Enrollment - Pattern Map

**Mapped:** 2026-04-21
**Files analyzed:** 35 (26 NEW, 9 MOD) + 7 tests
**Analogs found:** 34 / 35 (1 no close analog)

Phase 20 is unusually analog-rich: CONTEXT.md explicitly identifies verbatim port targets for every major artefact. This map binds each target to concrete file paths and line ranges — IRMS v1.0 analogs for structure/auth/convention, and FRAS source files for verbatim port content (service body, job shape, handler body, controller flow).

---

## File Classification

### NEW files

| File | Role | Data Flow | Closest IRMS Analog | FRAS Port Source | Match Quality |
|---|---|---|---|---|---|
| `app/Http/Controllers/Admin/AdminCameraController.php` | controller | CRUD + request-response | `app/Http/Controllers/Admin/AdminUnitController.php` | `/Users/helderdene/fras/app/Http/Controllers/CameraController.php` | exact (structure) + port (store flow) |
| `app/Http/Controllers/Admin/AdminPersonnelController.php` | controller | CRUD + request-response + file-I/O | `app/Http/Controllers/Admin/AdminUnitController.php` | `/Users/helderdene/fras/app/Http/Controllers/PersonnelController.php` | exact (structure) + port (photo flow) |
| `app/Http/Controllers/Admin/AdminPersonnelPhotoController.php` | controller | streaming (signed) | `app/Http/Controllers/Admin/AdminBarangayController.php` (shape) | — (IRMS-native route) | role-match |
| `app/Http/Controllers/Fras/FrasPhotoAccessController.php` | controller | streaming (token-gated) | — | — (IRMS-native design, D-21) | **no close analog** |
| `app/Http/Controllers/Admin/EnrollmentController.php` (for retry / resync endpoints, D-10) | controller | request-response | `app/Http/Controllers/Admin/AdminUnitController.php::recommission` | `/Users/helderdene/fras/app/Http/Controllers/EnrollmentController.php` | role-match + port |
| `app/Http/Requests/Admin/StoreCameraRequest.php` | form-request | validation | `app/Http/Requests/Admin/StoreUnitRequest.php` | — | exact |
| `app/Http/Requests/Admin/UpdateCameraRequest.php` | form-request | validation | `app/Http/Requests/Admin/UpdateUnitRequest.php` | — | exact |
| `app/Http/Requests/Admin/StorePersonnelRequest.php` | form-request | validation + file | `app/Http/Requests/Admin/StoreUnitRequest.php` | — | role-match |
| `app/Http/Requests/Admin/UpdatePersonnelRequest.php` | form-request | validation + file | `app/Http/Requests/Admin/UpdateUnitRequest.php` | — | role-match |
| `app/Services/CameraEnrollmentService.php` | service | event-driven + pub-sub | `app/Services/BarangayLookupService.php` (shape) | `/Users/helderdene/fras/app/Services/CameraEnrollmentService.php` | **verbatim port source** |
| `app/Services/FrasPhotoProcessor.php` | service | transform + file-I/O | — (no image-processing service in IRMS) | `/Users/helderdene/fras/app/Services/PhotoProcessor.php` | **verbatim port source (upgraded to Intervention v4)** |
| `app/Jobs/EnrollPersonnelBatch.php` | job | pub-sub | `app/Jobs/GenerateIncidentReport.php` (Queueable shape) | `/Users/helderdene/fras/app/Jobs/EnrollPersonnelBatch.php` | **verbatim port source** |
| `app/Observers/PersonnelObserver.php` | observer | event-driven | — (no observers in IRMS v1.0) | — (logic specified in D-13) | role-match (Laravel idiom) |
| `app/Events/CameraStatusChanged.php` | event | pub-sub | `app/Events/MqttListenerHealthChanged.php` | — | exact |
| `app/Events/EnrollmentProgressed.php` | event | pub-sub | `app/Events/IncidentCreated.php` | — | exact |
| `app/Console/Commands/CameraWatchdogCommand.php` | command | batch (scheduled) | `app/Console/Commands/FrasMqttListenerWatchdogCommand.php` | — | exact |
| `app/Console/Commands/PersonnelExpireSweepCommand.php` | command | batch (scheduled) | `app/Console/Commands/FrasMqttListenerWatchdogCommand.php` | — | role-match |
| `app/Enums/CameraEnrollmentStatus.php` (already exists Phase 18) | enum | — | `app/Enums/CameraStatus.php` | — | exact |
| `resources/js/pages/admin/Cameras.vue` | page | CRUD (read) | `resources/js/pages/admin/Units.vue` | `/Users/helderdene/fras/resources/js/pages/cameras/Index.vue` (low quality, replaced) | exact (v1.0 over FRAS) |
| `resources/js/pages/admin/CameraForm.vue` | page | CRUD (write) | `resources/js/pages/admin/UnitForm.vue` | — | exact |
| `resources/js/pages/admin/Personnel.vue` | page | CRUD (read) | `resources/js/pages/admin/Units.vue` | — | exact |
| `resources/js/pages/admin/PersonnelForm.vue` | page | CRUD (write) + file-I/O | `resources/js/pages/admin/UnitForm.vue` | `/Users/helderdene/fras/resources/js/pages/personnel/Edit.vue` (photo drop-zone only) | exact (v1.0 over FRAS) |
| `resources/js/components/admin/CameraLocationPicker.vue` | component | event-driven | `resources/js/components/intake/LocationMapPicker.vue` | — | **exact — verbatim port of shape** |
| `resources/js/components/fras/EnrollmentProgressPanel.vue` | component | pub-sub (Echo) | `resources/js/components/fras/MqttListenerHealthBanner.vue` (shape only) | — | role-match |
| `resources/js/components/fras/CameraStatusBadge.vue` | component | — | inline status-badge idiom in `resources/js/pages/admin/Units.vue` lines 173-187 | — | role-match (extract) |
| `resources/js/composables/useEnrollmentProgress.ts` | composable | pub-sub (Echo) | `resources/js/composables/useIntakeFeed.ts` (useEcho pattern) | — | role-match |
| `config/fras.php` (MOD — extend) | config | — | existing `config/fras.php` | — | exact (extend) |
| `config/filesystems.php` (MOD — add `fras_photos` disk) | config | — | existing `fras_events` disk block in same file | — | exact |
| `database/migrations/2026_04_2x_add_photo_access_token_to_personnel_table.php` | migration | — | any additive column migration in `database/migrations/` | — | exact (convention) |
| `routes/admin.php` (MOD) | routes | — | same file — lines 17-18 (Unit resource + recommission) | — | exact (extend) |
| `routes/channels.php` (MOD) | routes | — | same file — lines 9-11 (`dispatch.incidents` / `dispatch.units`) | — | exact (extend) |
| `routes/console.php` (MOD) | routes | — | same file — lines 12-19 (schedule block) | — | exact (extend) |
| `app/Providers/AppServiceProvider.php` (MOD — register Observer) | provider | — | same file — `configureEventListeners()` lines 212-223 | — | role-match (add boot method) |
| `app/Models/Camera.php` (MOD — add `enrollments()` relation) | model | — | existing `app/Models/Camera.php` | — | exact (extend) |
| `app/Models/Personnel.php` (MOD — add `photo_access_token` + relation + fillable) | model | — | existing `app/Models/Personnel.php` | — | exact (extend) |
| `app/Mqtt/Handlers/AckHandler.php` (MOD — fill `handle()` body) | handler | event-driven | existing `app/Mqtt/Handlers/AckHandler.php` (shell) | `/Users/helderdene/fras/app/Mqtt/Handlers/AckHandler.php` | **verbatim port source (body fill-in)** |
| `resources/js/composables/useDispatchMap.ts` (MOD — add cameras layer) | composable | transform | same file — lines 255-418 (existing source/layer idiom) | — | exact (extend) |
| `resources/js/pages/dispatch/Console.vue` (MOD — toggle button) | page | — | existing page | — | exact (extend) |

### Test files (NEW)

| Test file | Analog |
|---|---|
| `tests/Feature/Admin/AdminCameraControllerTest.php` | `tests/Feature/Admin/AdminUnitTest.php` |
| `tests/Feature/Admin/AdminPersonnelControllerTest.php` | `tests/Feature/Admin/AdminUnitTest.php` |
| `tests/Feature/Fras/CameraEnrollmentServiceTest.php` | `tests/Feature/Fras/CameraSpatialQueryTest.php` |
| `tests/Feature/Fras/FrasPhotoProcessorTest.php` | `tests/Feature/Fras/SchemaTest.php` (Pest feature shape) |
| `tests/Feature/Fras/AckHandlerTest.php` (MOD) | `tests/Feature/Fras/RecognitionEventIdempotencyTest.php` |
| `tests/Feature/Fras/CameraWatchdogTest.php` | `tests/Feature/Fras/SchemaTest.php` |
| `tests/Feature/Fras/PersonnelExpireSweepTest.php` | `tests/Feature/Fras/SchemaTest.php` |
| `tests/Feature/Fras/FrasPhotoAccessControllerTest.php` | `tests/Feature/Admin/AdminUnitTest.php` (HTTP assertion shape) |

---

## Pattern Assignments

### `app/Http/Controllers/Admin/AdminCameraController.php` (controller, CRUD + request-response)

**Primary IRMS analog:** `app/Http/Controllers/Admin/AdminUnitController.php`
**FRAS port reference:** `/Users/helderdene/fras/app/Http/Controllers/CameraController.php` (store-side enrollAllToCamera call + decommission flow informed by FRAS destroy)

**Imports pattern** (AdminUnitController.php lines 1-15):
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
```
Phase 20 equivalent: swap `UnitStatus/UnitType` → `CameraStatus`, `Unit` → `Camera`, inject `CameraEnrollmentService` + `BarangayLookupService` constructor-promoted.

**Index method pattern** (AdminUnitController.php lines 22-41):
```php
public function index(): Response
{
    $units = Unit::query()
        ->withCount('users')
        ->with('users:id,name,unit_id')
        ->orderBy('type')
        ->orderBy('id')
        ->get();

    return Inertia::render('admin/Units', [
        'units' => $units,
        'types' => UnitType::cases(),
        'statuses' => [UnitStatus::Available, UnitStatus::Offline],
        'responders' => User::query()->where('role', UserRole::Responder)->...->get(),
    ]);
}
```
Phase 20 equivalent: `Camera::withCount('enrollments')->with('barangay:id,name')->orderBy('camera_id_display')->get()` → `Inertia::render('admin/Cameras', ...)`.

**Auto-sequence store pattern — COPY VERBATIM** (AdminUnitController.php lines 62-106):
```php
public function store(StoreUnitRequest $request): RedirectResponse
{
    $validated = $request->validated();

    // ...prefix map removed for Phase 20 (single prefix 'CAM')...

    $maxSequence = Unit::query()
        ->where('type', $type)
        ->selectRaw("MAX(CAST(SUBSTRING(id FROM '[0-9]+$') AS INTEGER)) as max_seq")
        ->value('max_seq');

    $nextNumber = ($maxSequence ?? 0) + 1;
    $paddedNumber = str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
    $unitId = "{$prefix}-{$paddedNumber}";
    // ...

    $unit = Unit::query()->create([
        'id' => $unitId,
        // ...
    ]);

    return redirect()->route('admin.units.index')
        ->with('success', 'Unit created successfully.');
}
```
Phase 20 translation (per D-09):
```php
$maxSequence = Camera::query()
    ->selectRaw("MAX(CAST(SUBSTRING(camera_id_display FROM '[0-9]+$') AS INTEGER)) as max_seq")
    ->value('max_seq');
$validated['camera_id_display'] = sprintf('CAM-%02d', ($maxSequence ?? 0) + 1);
```
No `where('type', ...)` filter — single prefix namespace for cameras.

**Destroy with guard pattern** (AdminUnitController.php lines 167-180):
```php
public function destroy(Unit $unit): RedirectResponse
{
    if ($unit->activeIncidents()->count() > 0) {
        return redirect()->route('admin.units.index')
            ->with('error', 'Cannot decommission unit with active incidents.');
    }

    $unit->update(['decommissioned_at' => now()]);
    // ...
    return redirect()->route('admin.units.index')
        ->with('success', 'Unit decommissioned successfully.');
}
```
Phase 20 adaptation (per D-28): replace guard with `$camera->enrollments()->whereIn('status', ['pending', 'syncing'])->count()` → return `back()->withErrors([...])` with 422 copy from UI-SPEC.

**Recommission pattern** (AdminUnitController.php lines 185-194): copy verbatim — clears `decommissioned_at`, sets status to a safe default.

**Constructor DI (new for Phase 20, not in Unit controller):**
```php
public function __construct(
    private CameraEnrollmentService $enrollmentService,
    private BarangayLookupService $barangayLookup,
) {}
```
Called in `store()` after save: `$this->enrollmentService->enrollAllToCamera($camera)` (D-14).
Called in `store()` / `update()` to derive `barangay_id`: `$this->barangayLookup->findByCoordinates($lat, $lng)?->id`.

---

### `app/Http/Controllers/Admin/AdminPersonnelController.php` (controller, CRUD + file-I/O)

**Primary IRMS analog:** `app/Http/Controllers/Admin/AdminUnitController.php` (structure)
**FRAS port reference:** `/Users/helderdene/fras/app/Http/Controllers/PersonnelController.php` lines 80-99 (store flow with photo), 136-159 (update flow with old-file cleanup)

**Photo flow — port from FRAS PersonnelController verbatim** (lines 80-98):
```php
public function store(StorePersonnelRequest $request): RedirectResponse
{
    $data = $request->validated();

    if ($request->hasFile('photo')) {
        $result = app(PhotoProcessor::class)->process($request->file('photo'));
        $data = array_merge($data, $result);
    }

    unset($data['photo']);

    $personnel = Personnel::create($data);

    app(CameraEnrollmentService::class)->enrollPersonnel($personnel);

    return to_route('personnel.index');
}
```
Phase 20 tweaks:
- `app(PhotoProcessor::class)` → `app(FrasPhotoProcessor::class)` (renamed for namespace clarity)
- After `Personnel::create`, set `photo_access_token` = `Str::uuid()` (D-20) — do this by writing into `$data` before create, or a second `update()` call
- Derive `custom_id` after create per D-35: `$personnel->update(['custom_id' => str_replace('-', '', (string) $personnel->id)])`
- Replace `to_route('personnel.index')` with `redirect()->route('admin.personnel.index')->with('success', ...)` to match IRMS redirect idiom (AdminUnitController.php line 104)

**Update photo-replace pattern — port FRAS lines 141-148:**
```php
if ($request->hasFile('photo')) {
    $oldPath = $personnel->photo_path;
    $result = app(PhotoProcessor::class)->process($request->file('photo'));
    $data = array_merge($data, $result);
    app(PhotoProcessor::class)->delete($oldPath);
}
```
Phase 20 addition (D-23): after successful photo replace, set `$data['photo_access_token'] = Str::uuid()` to rotate the public URL token immediately.

**Destroy — port FRAS lines 162-173** with IRMS tweak: D-33 says decommission (not hard-delete). Replace `$personnel->delete()` with `$personnel->update(['decommissioned_at' => now()])`. Keep the `deleteFromAllCameras` call.

---

### `app/Http/Controllers/Fras/FrasPhotoAccessController.php` (controller, streaming — token-gated public)

**No direct analog.** This is Phase 20's novel design (D-21..D-23). Build from scratch per this skeleton:

```php
<?php
namespace App\Http\Controllers\Fras;

use App\Http\Controllers\Controller;
use App\Models\Personnel;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FrasPhotoAccessController extends Controller
{
    public function show(string $token, \Illuminate\Http\Request $request)
    {
        $personnel = Personnel::where('photo_access_token', $token)->first();
        if (! $personnel) {
            abort(404);
        }
        $hasLive = $personnel->enrollments()
            ->whereIn('status', ['pending', 'syncing'])
            ->exists();
        if (! $hasLive) {
            abort(404);
        }
        Log::channel('mqtt')->info('fras.photo.access', [
            'personnel_id' => $personnel->id,
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
        ]);
        return Storage::disk('fras_photos')->response("personnel/{$personnel->id}.jpg");
    }
}
```
**Route (no auth middleware)** — add in `routes/web.php`:
```php
Route::get('/fras/photo/{token}', [FrasPhotoAccessController::class, 'show'])
    ->name('fras.photo.show');
```
Token IS the access control — unguessable UUID per D-21.

---

### `app/Http/Controllers/Admin/AdminPersonnelPhotoController.php` (controller, signed-URL streaming)

**Analog:** `app/Http/Controllers/Admin/AdminBarangayController.php` (minimal show-only shape)

Register in `routes/admin.php`:
```php
Route::get('personnel/{personnel}/photo', [AdminPersonnelPhotoController::class, 'show'])
    ->middleware('signed')
    ->name('personnel.photo');
```
The `signed` middleware is Laravel-native — no custom code. Generate the signed URL in `AdminPersonnelController::edit()` Inertia prop via `URL::temporarySignedRoute('admin.personnel.photo', now()->addMinutes(5), ['personnel' => $personnel])` (D-22).

Controller body:
```php
public function show(Personnel $personnel)
{
    return Storage::disk('fras_photos')->response("personnel/{$personnel->id}.jpg");
}
```

---

### `app/Http/Requests/Admin/StoreCameraRequest.php` / `UpdateCameraRequest.php`

**Analog:** `app/Http/Requests/Admin/StoreUnitRequest.php`, `UpdateUnitRequest.php`

**Pattern (StoreUnitRequest.php full file):**
```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\UnitType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_column(UnitType::cases(), 'value'))],
            'callsign' => ['nullable', 'string', 'max:50'],
            'agency' => ['required', 'string', 'max:50'],
            // ...
        ];
    }
}
```

Phase 20 translations:
- `StoreCameraRequest`: `name` required max:100; `device_id` required max:64 unique; `latitude` required numeric between:-90,90; `longitude` required numeric between:-180,180; `location_label` nullable max:255; `notes` nullable max:1000.
- `StorePersonnelRequest`: `name` required max:100; `category` required `Rule::in(PersonnelCategory::cases())`; `photo` required|file|mimes:jpeg,jpg|max:1024 (1 MB); `expires_at` nullable date; `consent_basis` required string max:2000; optional fields per UI-SPEC §PersonnelForm sections 3-4.
- Update variants: remove `required` on photo; keep everything else.

Authorize returns `true` because the admin middleware gate (`role:admin`) is already enforced by `bootstrap/app.php` line 23-26 admin route group.

---

### `app/Services/CameraEnrollmentService.php` (service, pub-sub + event-driven)

**Verbatim port from:** `/Users/helderdene/fras/app/Services/CameraEnrollmentService.php` (206 lines)

**Port diff — only four IRMS tweaks (per D-11):**

1. **UUID FKs — remove int casts:** FRAS line 33 `EnrollPersonnelBatch::dispatch($camera, [$personnel->id])` — `$personnel->id` is already a UUID string in IRMS, ship as-is.
2. **Config path rewrite:** `hds.*` → `fras.*` everywhere (5 call sites: lines 55, 71, 86, 89, 174).
3. **Broadcast `EnrollmentProgressed` on status transitions:** FRAS dispatches `EnrollmentStatusChanged` from AckHandler only. For IRMS D-11 and D-38, dispatch `EnrollmentProgressed::dispatch($enrollment)` at these additional sites:
   - Inside `enrollPersonnel()` after each `CameraEnrollment::updateOrCreate` (status transitions to pending)
   - Inside `enrollAllToCamera()` after each `updateOrCreate`
   - Inside AckHandler `processSuccesses` / `processFailures` (already in FRAS — just rename event class)
4. **"is_online" check replacement:** FRAS uses `$camera->is_online` (boolean). IRMS uses `$camera->status === CameraStatus::Online` (enum). Replace all 3 call sites (lines 32, 54, 177).

**Critical service body to port VERBATIM (upsertBatch, lines 68-92):**
```php
public function upsertBatch(Camera $camera, array $personnelIds): void
{
    $personnel = Personnel::whereIn('id', $personnelIds)->get();
    $batchSize = config('fras.enrollment.batch_size');  // was hds.*

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
            config('fras.enrollment.ack_timeout_minutes') * 60  // was hds.*
        );

        $prefix = config('fras.mqtt.topic_prefix');
        MQTT::connection('publisher')->publish("{$prefix}/{$camera->device_id}", json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}
```

**Photo URL in buildEditPersonsNewPayload (FRAS line 112):**
```php
if ($personnel->photo_path) {
    $entry['picURI'] = $personnel->photo_url;
}
```
IRMS tweak: derive `$personnel->photo_url` as a Personnel model accessor that returns `route('fras.photo.show', ['token' => $personnel->photo_access_token])`. Camera fetches this URL → `FrasPhotoAccessController` streams bytes.

**translateErrorCode (FRAS lines 191-205) — port verbatim.** 10-entry map (461, 463, 464, 465, 466, 467, 468, 474, 478, default). Operator-facing strings.

---

### `app/Services/FrasPhotoProcessor.php` (service, transform + file-I/O)

**Verbatim port from:** `/Users/helderdene/fras/app/Services/PhotoProcessor.php` (53 lines)

**IRMS tweaks (per D-18/D-19):**
1. Class rename `PhotoProcessor` → `FrasPhotoProcessor` (namespace collision avoidance with any future generic PhotoProcessor)
2. Storage disk: `Storage::disk('public')` → `Storage::disk('fras_photos')` (D-19)
3. Filename: `Str::uuid().'.jpg'` → `"{$personnel->id}.jpg"` (D-18 uses Personnel UUID as filename — deterministic, no separate lookup needed for FrasPhotoAccessController)
   — this requires the method signature change: `process(UploadedFile $file, Personnel $personnel): array` (add second arg)
4. Config reads: `hds.photo.*` → `fras.photo.*` (3 call sites: lines 20, 21, 22)
5. Intervention v4 API — FRAS already uses v4-compatible shape (`Image::decodePath`, `encode(new JpegEncoder)`), so the processing loop ports as-is:

```php
$image = Image::decodePath($file->path());
$image->orient();
$image->scaleDown(width: $maxDim, height: $maxDim);
$encoded = $image->encode(new JpegEncoder(quality: $quality));

while (strlen((string) $encoded) > $maxBytes && $quality > 40) {
    $quality -= 10;
    $encoded = $image->encode(new JpegEncoder(quality: $quality));
}

$path = "personnel/{$personnel->id}.jpg";
Storage::disk('fras_photos')->put($path, (string) $encoded);

return [
    'photo_path' => $path,
    'photo_hash' => md5((string) $encoded),
];
```

---

### `app/Jobs/EnrollPersonnelBatch.php` (job, pub-sub)

**Verbatim port from:** `/Users/helderdene/fras/app/Jobs/EnrollPersonnelBatch.php` (44 lines)

**Port verbatim — no structural changes:**
```php
<?php
namespace App\Jobs;

use App\Models\Camera;
use App\Services\CameraEnrollmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class EnrollPersonnelBatch implements ShouldQueue
{
    use Queueable;

    /** @param array<string> $personnelIds */   // was array<int> in FRAS
    public function __construct(
        public Camera $camera,
        public array $personnelIds,
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('enrollment-camera-'.$this->camera->id))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(CameraEnrollmentService $service): void
    {
        $service->upsertBatch($this->camera, $this->personnelIds);
    }
}
```

**IRMS tweaks (per D-12):**
1. Change PHPDoc `@param array<int>` → `@param array<string>` (UUIDs)
2. Add property `public int $tries = 3;` (D-17 transient error retry policy)
3. Dispatch target queue: `.onQueue('fras')` at the dispatch site, OR add `public string $queue = 'fras';` here — picks up Phase 19's `fras-supervisor` Horizon block. Confirm in `config/horizon.php` whether the supervisor is named/auto-balancing.

---

### `app/Mqtt/Handlers/AckHandler.php` MOD (handler, event-driven)

**Existing IRMS shell:** `app/Mqtt/Handlers/AckHandler.php` (27 lines — log-only scaffold)
**Verbatim port source:** `/Users/helderdene/fras/app/Mqtt/Handlers/AckHandler.php` (154 lines)

**Port the entire body** from FRAS AckHandler lines 16-75 (`handle()`) + lines 83-113 (`processSuccesses`) + lines 121-152 (`processFailures`).

**IRMS tweaks (per D-16):**
1. Event class rename: FRAS's `EnrollmentStatusChanged` → IRMS's `EnrollmentProgressed` (D-38). Update 2 dispatch sites (FRAS lines 106-112 and 145-151).
2. Broadcast signature change: FRAS dispatches with 5 positional args. IRMS `EnrollmentProgressed::dispatch($cameraEnrollment)` should accept the full pivot row — richer payload per D-38 `broadcastWith()`.
3. Enrollment status values: FRAS uses `STATUS_ENROLLED` constant; IRMS uses `CameraEnrollmentStatus::Done` enum. Replace constants with enum cases.
4. Log channel: IRMS uses `Log::channel('mqtt')` (Phase 19 idiom) — keep the FRAS `Log::warning` / `Log::info` calls but route via `mqtt` channel.
5. UUID casing in camera lookup: FRAS line 44 `Camera::where('device_id', $deviceId)->first()` ports as-is (device_id is string in both).

**Retain the shell comment replacement** in IRMS's existing file — remove the "Phase 20 fills in" placeholder at line 25 once the body is ported.

---

### `app/Observers/PersonnelObserver.php` (observer, event-driven)

**No existing observer in IRMS v1.0.** This is a standard Laravel idiom; scaffold:

```php
<?php
namespace App\Observers;

use App\Models\Personnel;
use App\Services\CameraEnrollmentService;

class PersonnelObserver
{
    public function __construct(private CameraEnrollmentService $enrollmentService) {}

    public function saved(Personnel $personnel): void
    {
        if ($personnel->wasChanged(['photo_hash', 'category'])) {
            $this->enrollmentService->enrollPersonnel($personnel);
        }
    }

    public function deleted(Personnel $personnel): void
    {
        $this->enrollmentService->deleteFromAllCameras($personnel);
    }
}
```
Register in `AppServiceProvider::boot()` (NOT constructor) via `Personnel::observe(PersonnelObserver::class);` — follow the `configureEventListeners()` idiom (AppServiceProvider.php lines 212-223):
```php
protected function configureObservers(): void
{
    Personnel::observe(PersonnelObserver::class);
}
```
And call from `boot()` alongside the other `configureX()` methods.

**Pitfall guard (per RESEARCH Pitfall 1 + 4):** register the observer in `boot()`, not `register()`. `wasChanged()` only fires on `save` not on `refresh/hydrate`, so no re-fire risk.

---

### `app/Events/CameraStatusChanged.php` + `EnrollmentProgressed.php` (events, pub-sub)

**Analog for CameraStatusChanged:** `app/Events/MqttListenerHealthChanged.php` (44 lines) — scalar-args constructor shape.
**Analog for EnrollmentProgressed:** `app/Events/IncidentCreated.php` (57 lines) — model-arg constructor + rich `broadcastWith()`.

**Pattern (MqttListenerHealthChanged.php full file):**
```php
<?php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MqttListenerHealthChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $status,
        public ?string $lastMessageReceivedAt,
        public string $since,
        public int $activeCameraCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('dispatch.incidents')];
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'last_message_received_at' => $this->lastMessageReceivedAt,
            'since' => $this->since,
            'active_camera_count' => $this->activeCameraCount,
        ];
    }
}
```

**CameraStatusChanged (Phase 20 per D-37):**
- Constructor: `public function __construct(public Camera $camera)`
- Channel: `new PrivateChannel('fras.cameras')`
- `broadcastWith()`: `['camera_id' => $this->camera->id, 'camera_id_display' => $this->camera->camera_id_display, 'status' => $this->camera->status->value, 'last_seen_at' => $this->camera->last_seen_at?->toIso8601String(), 'location' => $this->camera->location ? ['lat' => ..., 'lng' => ...] : null]`

**EnrollmentProgressed (per D-38):**
- Constructor: `public function __construct(public CameraEnrollment $enrollment)`
- Channel: `new PrivateChannel('fras.enrollments')`
- `broadcastWith()`: `['personnel_id' => ..., 'camera_id' => ..., 'camera_id_display' => $this->enrollment->camera->camera_id_display, 'status' => $this->enrollment->status->value, 'last_error' => $this->enrollment->last_error]`

**`IncidentCreated::broadcastWith` (IncidentCreated.php lines 33-55)** is the closest shape — rich object payload with nested accessors. Copy its style for EnrollmentProgressed.

---

### `app/Console/Commands/CameraWatchdogCommand.php` + `PersonnelExpireSweepCommand.php`

**Analog:** `app/Console/Commands/FrasMqttListenerWatchdogCommand.php` (56 lines)

**Pattern (FrasMqttListenerWatchdogCommand full file) — state-machine transition idiom:**
```php
<?php
namespace App\Console\Commands;

use App\Events\MqttListenerHealthChanged;
use App\Models\Camera;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FrasMqttListenerWatchdogCommand extends Command
{
    private const SILENCE_THRESHOLD_SECONDS = 90;

    protected $signature = 'irms:mqtt-listener-watchdog';
    protected $description = 'Detect MQTT listener silence and broadcast health transitions';

    public function handle(): int
    {
        $activeCount = Camera::whereNull('decommissioned_at')->count();
        // ...gap calculation...
        $state = $gapSeconds < self::SILENCE_THRESHOLD_SECONDS ? 'HEALTHY' : 'SILENT';
        $this->transition($state, $lastMessageAt, $activeCount);
        return self::SUCCESS;
    }

    private function transition(string $state, ?string $lastMessageAt, int $activeCount): void
    {
        $previous = Cache::get('mqtt:listener:last_known_state');
        if ($previous === $state) {
            return;   // no-op on unchanged state — broadcast ONLY on transition (D-25)
        }
        Cache::put('mqtt:listener:last_known_state', $state);
        MqttListenerHealthChanged::dispatch($state, $lastMessageAt, $since, $activeCount);
    }
}
```

**Phase 20 CameraWatchdogCommand** (D-25):
```php
protected $signature = 'irms:camera-watchdog';

public function handle(): int
{
    $degradedGap = config('fras.cameras.degraded_gap_s', 30);
    $offlineGap = config('fras.cameras.offline_gap_s', 90);

    foreach (Camera::active()->get() as $camera) {
        $gap = $camera->last_seen_at
            ? now()->diffInSeconds($camera->last_seen_at, true)
            : PHP_INT_MAX;

        $newStatus = match (true) {
            $gap <= $degradedGap => CameraStatus::Online,
            $gap <= $offlineGap => CameraStatus::Degraded,
            default => CameraStatus::Offline,
        };

        if ($camera->status !== $newStatus) {
            $camera->update(['status' => $newStatus]);
            CameraStatusChanged::dispatch($camera->fresh());
        }
    }
    return self::SUCCESS;
}
```
**No Cache shared-state needed** — state lives on the `cameras.status` column; transition detection is `$camera->status !== $newStatus`.

**PersonnelExpireSweepCommand** (D-31..D-34):
```php
protected $signature = 'irms:personnel-expire-sweep';

public function handle(CameraEnrollmentService $service): int
{
    $expired = Personnel::whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->whereNull('decommissioned_at')
        ->get();

    foreach ($expired as $personnel) {
        $service->deleteFromAllCameras($personnel);
        $personnel->update(['decommissioned_at' => now()]);
        CameraEnrollment::where('personnel_id', $personnel->id)
            ->update(['status' => CameraEnrollmentStatus::Done]);
        Log::channel('mqtt')->info('fras.personnel.expired', ['id' => $personnel->id]);
    }
    return self::SUCCESS;
}
```

**Schedule registration** (routes/console.php, append after existing watchdog block at lines 16-19):
```php
Schedule::command('irms:camera-watchdog')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Flip camera status between online/degraded/offline based on heartbeat gap');

Schedule::command('irms:personnel-expire-sweep')
    ->hourly()
    ->withoutOverlapping()
    ->description('Unenroll personnel whose BOLO expiry has passed');
```

---

### `resources/js/pages/admin/Cameras.vue` / `Personnel.vue` (page, CRUD-read)

**Verbatim analog:** `resources/js/pages/admin/Units.vue` (278 lines)

**Imports pattern** (Units.vue lines 1-24):
```typescript
import { Head, Link, router } from '@inertiajs/vue3';
import {
    destroy,
    edit,
    recommission,
} from '@/actions/App/Http/Controllers/Admin/AdminUnitController';
import AdminUnitController from '@/actions/App/Http/Controllers/Admin/AdminUnitController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogClose, DialogContent, DialogDescription,
    DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as unitsIndex } from '@/routes/admin/units';
import type { BreadcrumbItem } from '@/types';
```
Phase 20: replace `AdminUnitController` imports with `AdminCameraController` (Wayfinder-generated) and `@/routes/admin/cameras`.

**Status-color lookup pattern** (Units.vue lines 53-64):
```typescript
const statusColors: Record<string, string> = {
    AVAILABLE: 'bg-[color-mix(in_srgb,var(--t-unit-available)_12%,transparent)] text-t-unit-available',
    // ...
    OFFLINE: 'bg-[color-mix(in_srgb,var(--t-unit-offline)_12%,transparent)] text-t-unit-offline',
};
```
Phase 20 Cameras.vue: bind to UI-SPEC §Color table. Extract into `components/fras/CameraStatusBadge.vue` per UI-SPEC §Components — map online/degraded/offline/decommissioned to t-online/t-unit-onscene/t-unit-offline tokens.

**Action mutations pattern** (Units.vue lines 74-82):
```typescript
function decommissionUnit(unit: AdminUnit): void {
    router.delete(destroy(unit.id).url, { preserveScroll: true });
}

function recommissionUnit(unit: AdminUnit): void {
    router.post(recommission(unit.id).url, {}, { preserveScroll: true });
}
```

**Table + Dialog template pattern** (Units.vue lines 89-277) — copy verbatim. Replace column set per UI-SPEC §Surface contracts (Cameras: ID / Name / Status / Device ID / Location / Enrollments / Actions). Personnel columns per UI-SPEC §2: Name / Category / Expires / Enrollments / Consent / Actions.

**Dialog confirm copy** (Units.vue lines 222-261) — Phase 20 swaps title+description per UI-SPEC §Destructive action confirmations (exact strings provided).

---

### `resources/js/pages/admin/CameraForm.vue` / `PersonnelForm.vue` (page, CRUD-write)

**Verbatim analog:** `resources/js/pages/admin/UnitForm.vue` (470 lines)

**Imports + form setup pattern** (UnitForm.vue lines 1-30, 63-72):
```typescript
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { store, update } from '@/actions/App/Http/Controllers/Admin/AdminUnitController';
// ...UI imports...
import AppLayout from '@/layouts/AppLayout.vue';
import { index as unitsIndex } from '@/routes/admin/units';

const form = useForm({
    type: props.unit?.type ?? '',
    // ...field defaults from props.unit or blank...
});
```

**Section wrapper pattern** (UnitForm.vue lines 195-198):
```vue
<form
    class="space-y-6 rounded-[var(--radius)] border border-border bg-card p-6 shadow-[var(--shadow-1)]"
    @submit.prevent="submit"
>
```

**Mono-caps section header pattern** (UnitForm.vue lines 200-205, repeated per section):
```vue
<div class="space-y-4">
    <h3 class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase">
        Unit Identity
    </h3>
    <!-- grid gap-2 field stacks -->
</div>
```
Phase 20 uses this exact micro-caps token for every section header per UI-SPEC §Typography.

**Submit pattern with create/update branch** (UnitForm.vue lines 167-178):
```typescript
function submit(): void {
    form.transform((data) => ({ ...data, shift: data.shift === 'none' ? '' : data.shift }));
    if (isEditing.value && props.unit) {
        form.submit(update(props.unit.id));
    } else {
        form.submit(store());
    }
}
```

**Submit bar pattern** (UnitForm.vue lines 457-466):
```vue
<div class="flex items-center gap-4">
    <Button :disabled="form.processing">
        {{ isEditing ? 'Update Unit' : 'Create Unit' }}
    </Button>
    <Link :href="unitsIndex.url()">
        <Button variant="outline" type="button">Cancel</Button>
    </Link>
</div>
```
Phase 20 copy per UI-SPEC §Buttons: "Create Camera" / "Update Camera" / "Create Personnel" / "Update Personnel" / "Cancel".

**PersonnelForm.vue extras** (beyond UnitForm analog):
- Section 1 Photo dropzone — if rebuilding, reference FRAS `/Users/helderdene/fras/resources/js/pages/personnel/Create.vue` photo dropzone shape (client-side MIME + size guard).
- Section 6 EnrollmentProgressPanel — edit mode only; see component contract below.
- Signed URL for photo preview: comes in as `photo_signed_url` Inertia prop from `AdminPersonnelController::edit()`.

---

### `resources/js/components/admin/CameraLocationPicker.vue` (component, event-driven)

**Verbatim port shape:** `resources/js/components/intake/LocationMapPicker.vue` (181 lines)

**Entire file is the pattern.** Copy verbatim (constants, script, template) and extend with the UI-SPEC additions:
1. **Imports + constants (LocationMapPicker.vue lines 1-21):** copy as-is including `BUTUAN_CENTER: [125.5406, 8.9475]`, `BUTUAN_ZOOM = 13`, `PIN_COLOR = '#E24B4A'`, `MAP_STYLE = 'mapbox://styles/helderdene/cmmq06eqr005j01skbwodfq08'`.
2. **reverseGeocode (lines 31-55):** copy verbatim. `api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${token}&country=ph&limit=1` is the exact fetch.
3. **ensureMarker + drag handlers (lines 57-89):** copy verbatim.
4. **onMounted map init (lines 96-131) + click handler:** copy verbatim.
5. **onUnmounted cleanup (lines 157-161):** copy verbatim.

**Phase 20 additions on top of this base (UI-SPEC §5):**
- Props extended: `address: string | null` (for pre-fill display in edit mode)
- Search bar above the map (new addition): `<Input>` with debounced 300ms `useGeocodingSearch` integration → `<DropdownMenu>` suggestions → `flyTo` + marker drop
- Template additions: `<Input>` with `<Search>` icon prefix above the existing `<div ref="mapContainer">`
- Emit additional signal from reverseGeocode success to trigger PostGIS barangay lookup on parent form

**Composable reference:** `resources/js/composables/useGeocodingSearch.ts` — forward-geocoding already exists; import and wire to the new search bar.

---

### `resources/js/components/fras/EnrollmentProgressPanel.vue` (component, pub-sub)

**Closest analog for broadcast/subscribe + container card:** `resources/js/components/fras/MqttListenerHealthBanner.vue` (shape only — it's a Transition-wrapped alert, not a panel)

**Better structural analog for card + list + row actions:** extract the row pattern from `resources/js/pages/admin/Units.vue` lines 147-266 (row-with-actions-cluster).

**Composable binding analog:** `resources/js/composables/useIntakeFeed.ts` lines 73-80 — the `useEcho` filter-and-mutate pattern:
```typescript
useEcho<IncidentCreatedPayload>(
    'dispatch.incidents',
    'IncidentCreated',
    (e) => {
        if (e.status !== 'PENDING' && e.status !== 'TRIAGED') return;
        // ...mutate local reactive state in place...
    },
);
```

Phase 20 composable shape (in `useEnrollmentProgress.ts`):
```typescript
import { useEcho } from '@laravel/echo-vue';
import { ref } from 'vue';

export function useEnrollmentProgress(personnelId: string, initialRows: EnrollmentRow[]) {
    const rows = ref(new Map(initialRows.map((r) => [r.camera_id, r])));

    useEcho<EnrollmentProgressedPayload>(
        'fras.enrollments',
        'EnrollmentProgressed',
        (e) => {
            if (e.personnel_id !== personnelId) return;
            const existing = rows.value.get(e.camera_id);
            rows.value.set(e.camera_id, {
                ...(existing ?? { camera_id: e.camera_id, camera_id_display: e.camera_id_display }),
                status: e.status,
                last_error: e.last_error,
            });
        },
    );

    return { rows };
}
```
`useEcho` auto-cleans up on scope dispose (per RESEARCH pattern 4) — no manual `onUnmounted` needed.

---

### `resources/js/composables/useDispatchMap.ts` MOD (composable, transform)

**Existing file, extend only.** Analog idioms live inside the same file.

**Existing constants (lines 17-45) — extend with CAMERA_STATUS_COLORS:**
```typescript
const CAMERA_STATUS_COLORS: ExpressionSpecification = [
    'match',
    ['get', 'status'],
    'online', '#1D9E75',
    'degraded', '#EF9F27',
    'offline', '#6B7280',
    '#888888',
];
```
Mirror the exact structure of `STATUS_COLORS` (line 31-45). Uppercase hex to match existing convention.

**Existing icon-loading idiom (lines 145-204):** add a third loop for cameras alongside units + categories:
```typescript
for (const [key, color] of Object.entries({ online: '#1D9E75', degraded: '#EF9F27', offline: '#6B7280' })) {
    const name = `camera-${key}`;
    if (!map.value.hasImage(name)) {
        promises.push(loadSvgAsImage(buildCircleIconSvg(CAMERA_ICON_PATH, color)).then((img) => {
            if (!m.hasImage(name)) m.addImage(name, img);
        }));
    }
}
```
`buildCircleIconSvg` (lines 58-74) is the shared helper — reuse as-is. Define `CAMERA_ICON_PATH` as a new constant near `UNIT_ICON_PATH` (line 52) — SVG path for a camera glyph (lucide `Camera` or custom).

**Existing addSources idiom (lines 255-276):** add `cameras` source:
```typescript
map.value.addSource('cameras', {
    type: 'geojson',
    data: currentCameraData,
    promoteId: 'id',
});
```

**Existing addLayers idiom (lines 369-418, unit-glow → unit-body → unit-label trio):** copy the three-layer pattern (halo → body → label) verbatim for cameras. Layer ids: `camera-halo`, `camera-body`, `camera-label`. Icon expression: `['concat', 'camera-', ['get', 'status']]`. Text field: `['get', 'camera_id_display']`.

**Layer order pitfall (per RESEARCH Pitfall 6):** add camera layers AFTER units (so cameras render on top of units) but BEFORE `incident-label` (so incident IDs stay readable). Explicit layer-order: `connection-glow` → `connection-lines` → `unit-glow` → `unit-body` → `camera-halo` → `camera-body` → `camera-label` → `unit-label` → `incident-halo` → `incident-core` → `incident-label`. Planner picks final order consistent with UI-SPEC §Dispatch-map cameras layer.

**Expose setCameraData + updateCameraStatus** in the returned API object.

---

### `resources/js/pages/dispatch/Console.vue` MOD

**Analog:** existing file (no external analog needed)

Add per UI-SPEC §7:
- `const camerasVisible = ref(true)` near other layer toggles
- `<Button variant="ghost" size="icon" :class="{ 'bg-accent': camerasVisible }" @click="camerasVisible = !camerasVisible">` with a lucide `Camera` icon, wrapped in `<Tooltip>`
- Watch `camerasVisible` → call `map.setLayoutProperty('camera-body'|'camera-halo'|'camera-label', 'visibility', value ? 'visible' : 'none')`
- Wire SSR `cameras` prop → `useDispatchMap`'s `setCameraData(cameras)` on mount
- Subscribe to `fras.cameras` private channel via `useEcho<CameraStatusChangedPayload>` → call `updateCameraStatus(e.camera_id, e.status)`

---

### `routes/admin.php` MOD

**Analog:** same file lines 17-18 (Unit resource registration pattern):
```php
Route::resource('units', AdminUnitController::class);
Route::post('units/{unit}/recommission', [AdminUnitController::class, 'recommission'])->name('units.recommission');
```

Phase 20 extensions (per D-07):
```php
Route::resource('cameras', AdminCameraController::class);
Route::post('cameras/{camera}/recommission', [AdminCameraController::class, 'recommission'])->name('cameras.recommission');

Route::resource('personnel', AdminPersonnelController::class);
Route::post('personnel/{personnel}/recommission', [AdminPersonnelController::class, 'recommission'])->name('personnel.recommission');

Route::get('personnel/{personnel}/photo', [AdminPersonnelPhotoController::class, 'show'])
    ->middleware('signed')->name('personnel.photo');

// Enrollment retry/resync (D-10 per-panel actions)
Route::post('personnel/{personnel}/enrollments/{camera}/retry', [EnrollmentController::class, 'retry'])
    ->name('personnel.enrollment.retry');
Route::post('personnel/{personnel}/enrollments/resync', [EnrollmentController::class, 'resyncAll'])
    ->name('personnel.enrollment.resync');
```
All inherit the outer `role:admin` group from `bootstrap/app.php` lines 23-26.

---

### `routes/channels.php` MOD

**Analog:** same file lines 9-15 (private-channel + `in_array($user->role, $dispatchRoles)` gate):
```php
Broadcast::channel('dispatch.incidents', function (User $user) use ($dispatchRoles): bool {
    return in_array($user->role, $dispatchRoles);
});
```

Phase 20 additions (per D-36):
```php
Broadcast::channel('fras.cameras', function (User $user): bool {
    return in_array($user->role, [UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin]);
});

Broadcast::channel('fras.enrollments', function (User $user): bool {
    return in_array($user->role, [UserRole::Supervisor, UserRole::Admin]);
});
```

---

### `routes/console.php` MOD

**Analog:** same file lines 16-19 (`Schedule::command(...)->everyThirtySeconds()->withoutOverlapping()`):
```php
Schedule::command('irms:mqtt-listener-watchdog')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->description('Detect MQTT listener silence and broadcast health transitions');
```

Phase 20 additions:
```php
Schedule::command('irms:camera-watchdog')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Flip camera status between online/degraded/offline based on heartbeat gap');

Schedule::command('irms:personnel-expire-sweep')
    ->hourly()
    ->withoutOverlapping()
    ->description('Unenroll personnel whose BOLO expiry has passed');
```

---

### `config/fras.php` MOD (extend)

**Analog:** same file lines 19-23 (existing `mqtt` block):
```php
'mqtt' => [
    'topic_prefix' => env('FRAS_MQTT_TOPIC_PREFIX', 'mqtt/face'),
    'keepalive' => (int) env('FRAS_MQTT_KEEPALIVE', 60),
    'reconnect_delay' => (int) env('FRAS_MQTT_RECONNECT_DELAY', 5),
],
```

Phase 20 extensions (per D-39) — append to the return array:
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
    'max_dimension'  => (int) env('FRAS_PHOTO_MAX_DIMENSION', 1080),
    'jpeg_quality'   => (int) env('FRAS_PHOTO_JPEG_QUALITY', 85),
    'max_size_bytes' => (int) env('FRAS_PHOTO_MAX_SIZE_BYTES', 1_048_576),
],
```

---

### `config/filesystems.php` MOD (add `fras_photos` disk)

**Analog:** same file lines 63-69 (`fras_events` disk block):
```php
'fras_events' => [
    'driver' => env('FRAS_EVENT_DISK', 'local'),
    'root' => storage_path('app/private/fras_events'),
    'visibility' => 'private',
    'throw' => false,
    'report' => false,
],
```

Phase 20 addition (per D-19) — append in the `disks` array:
```php
'fras_photos' => [
    'driver' => env('FRAS_PHOTO_DISK', 'local'),
    'root' => storage_path('app/private/fras_photos'),
    'visibility' => 'private',
    'throw' => false,
    'report' => false,
],
```

---

### `database/migrations/2026_04_2x_add_photo_access_token_to_personnel_table.php`

**Analog:** any Phase 18 additive column migration in `database/migrations/`.

Pattern (one-liner up/down):
```php
public function up(): void
{
    Schema::table('personnel', function (Blueprint $table) {
        $table->uuid('photo_access_token')->nullable()->unique()->after('photo_hash');
    });
}

public function down(): void
{
    Schema::table('personnel', function (Blueprint $table) {
        $table->dropUnique(['photo_access_token']);
        $table->dropColumn('photo_access_token');
    });
}
```
Per D-20: justified break of Phase 18's schema freeze — additive, nullable, unique.

---

### `app/Models/Camera.php` MOD (add enrollments relation)

**Analog:** same file (60 lines, clean model).

Add after `scopeActive()`:
```php
/** @return HasMany<CameraEnrollment> */
public function enrollments(): HasMany
{
    return $this->hasMany(CameraEnrollment::class);
}
```
Import `Illuminate\Database\Eloquent\Relations\HasMany`.

---

### `app/Models/Personnel.php` MOD

**Analog:** same file (66 lines).

Changes (D-20, D-35):
1. Add `'photo_access_token'` to `$fillable` array (line 24-38)
2. Add `enrollments()` relation (same shape as Camera)
3. Add `photo_url` accessor:
   ```php
   protected function photoUrl(): Attribute
   {
       return Attribute::make(
           get: fn () => $this->photo_access_token
               ? route('fras.photo.show', ['token' => $this->photo_access_token])
               : null,
       );
   }
   ```

---

## Shared Patterns

### Admin route registration convention

**Source:** `routes/admin.php` lines 12-21 + `bootstrap/app.php` lines 23-26
**Apply to:** All new admin routes

All admin routes live under the `role:admin`-gated prefix group registered in bootstrap. The file itself uses bare `Route::resource(...)` calls — the middleware comes from the outer group. Phase 20 adds bare resources without re-declaring the group.

### Form Request validation idiom

**Source:** `app/Http/Requests/Admin/StoreUnitRequest.php` (full file — 39 lines)
**Apply to:** Every `app/Http/Requests/Admin/*.php` in Phase 20

```php
public function authorize(): bool { return true; }

public function rules(): array {
    return [
        'field' => ['required', 'string', 'max:100'],
        'enum_field' => ['required', Rule::in(array_column(MyEnum::cases(), 'value'))],
    ];
}
```
`authorize() === true` because the admin middleware enforces role at the route layer.

### Inertia redirect + flash pattern

**Source:** `app/Http/Controllers/Admin/AdminUnitController.php` lines 104-105 (verbatim idiom used across every admin action)
```php
return redirect()->route('admin.units.index')
    ->with('success', 'Unit created successfully.');
```
**Apply to:** All `store/update/destroy/recommission` methods in Phase 20 admin controllers. Use `'success'` key for positive, `'error'` key for negative. UI-SPEC §Error states copy applies to the `error` case.

### Mono-caps section header token

**Source:** `resources/js/pages/admin/UnitForm.vue` lines 201-205 + `resources/js/pages/admin/Units.vue` lines 110-113
**Apply to:** Every form section header AND every table column header in Phase 20 admin pages

```vue
<h3 class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase">
    Section Name
</h3>
```
```vue
<th class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase">
    Column Header
</th>
```
**This is not arbitrary — it is the Sentinel DS locked micro-caps token (UI-SPEC §Typography).**

### Status-badge color-mix tint

**Source:** `resources/js/pages/admin/Units.vue` lines 53-64 + verified in UI-SPEC §Color
**Apply to:** All Phase 20 status badges (CameraStatusBadge, category badge, expired badge, enrollment status badge)

```typescript
'{STATE}': 'bg-[color-mix(in_srgb,var(--{token})_12%,transparent)] text-{token}',
```
12% tint over `--t-surface` meets WCAG AA for every token UI-SPEC declared EXCEPT `t-unit-onscene` (amber) which requires a `●` prefix.

### Dialog destructive-confirm pattern

**Source:** `resources/js/pages/admin/Units.vue` lines 222-262 (verbatim template block)
**Apply to:** Camera decommission, Personnel remove, Camera recommission, Personnel restore

Initial focus lands on Cancel (not destructive button) per UI-SPEC §Accessibility. Use `DialogClose as-child` + `<Button variant="outline">Cancel</Button>` for cancel path. Destructive button has `variant="destructive"` + mirrors trigger label.

### `ShouldBroadcast + ShouldDispatchAfterCommit` event shape

**Source:** `app/Events/IncidentCreated.php` + `app/Events/MqttListenerHealthChanged.php` (both 44-57 lines)
**Apply to:** `CameraStatusChanged`, `EnrollmentProgressed`

Always implement BOTH contracts. `ShouldDispatchAfterCommit` prevents the "dispatch inside transaction" pitfall (RESEARCH Pitfall 2).

### `useEcho` subscription in Vue scope

**Source:** `resources/js/composables/useIntakeFeed.ts` lines 73-80 + `resources/js/composables/useDispatchFeed.ts` line 133, 217, 382, 417, 432, 449 (production idiom in this codebase)
**Apply to:** `useEnrollmentProgress.ts`, dispatch-console cameras-channel subscription

Auto-cleanup via Vue scope disposal — no manual `onUnmounted`.

### Wayfinder-generated action imports

**Source:** `resources/js/pages/admin/Units.vue` lines 4-8 + `resources/js/pages/admin/UnitForm.vue` lines 4-8
**Apply to:** All Phase 20 Vue pages calling backend routes

```typescript
import {
    destroy, edit, recommission,
} from '@/actions/App/Http/Controllers/Admin/AdminUnitController';
import AdminUnitController from '@/actions/App/Http/Controllers/Admin/AdminUnitController';
```
Prefer named imports for action URLs; default import for `create()` access. Always regenerate Wayfinder after controller changes.

### Pest Feature test shape

**Source:** `tests/Feature/Admin/AdminUnitTest.php` lines 1-22 (Inertia assertion pattern)
**Apply to:** All Phase 20 controller tests

```php
it('allows admin to list cameras', function () {
    $admin = User::factory()->admin()->create();
    Camera::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.cameras.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Cameras')
            ->has('cameras', 3)
        );
});
```

### Schedule registration idiom

**Source:** `routes/console.php` lines 16-19
**Apply to:** Both new Phase 20 commands

```php
Schedule::command('irms:command-name')
    ->{interval}()
    ->withoutOverlapping()
    ->description('...');
```

### Log channel for MQTT/FRAS operations

**Source:** `app/Mqtt/Handlers/AckHandler.php` line 20 + `config/logging.php` (Phase 19 `mqtt` channel)
**Apply to:** AckHandler body, FrasPhotoAccessController access audit, PersonnelExpireSweepCommand, photo-access token events

```php
Log::channel('mqtt')->info('event.name', ['context' => ...]);
```

---

## No Analog Found

| File | Role | Reason |
|---|---|---|
| `app/Http/Controllers/Fras/FrasPhotoAccessController.php` | controller (token-gated streaming) | No existing public token-gated endpoint in IRMS. Design is Phase 20-native (D-21). Pattern is simple Laravel: `Route::get('/fras/photo/{token}', ...)` + Storage disk response + Log::info. Use skeleton provided above. |

---

## Metadata

**Analog search scope:**
- `app/Http/Controllers/Admin/*.php`
- `app/Http/Requests/Admin/*.php`
- `resources/js/pages/admin/*.vue`
- `resources/js/components/intake/`, `resources/js/components/fras/`
- `app/Events/*.php`
- `app/Services/*.php`, `app/Jobs/*.php`, `app/Mqtt/Handlers/*.php`, `app/Console/Commands/*.php`
- `resources/js/composables/*.ts`
- `routes/admin.php`, `routes/channels.php`, `routes/console.php`, `routes/web.php`
- `config/fras.php`, `config/filesystems.php`
- `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`
- `/Users/helderdene/fras/app/**` (verbatim port sources)

**Files scanned:** ~50 IRMS + 8 FRAS (explicit port targets)
**Pattern extraction date:** 2026-04-21
**Closest-analog count by match quality:**
- exact: 20
- role-match: 11
- verbatim port (FRAS): 5 (CameraEnrollmentService, FrasPhotoProcessor, EnrollPersonnelBatch, AckHandler body, CameraLocationPicker port shape)
- no close analog: 1 (FrasPhotoAccessController)

---

## Summary for Planner

Phase 20 is a **copy-and-tweak phase**, not a design-and-build phase. The planner should structure each plan around:

1. **v1.0 structural analog** — which IRMS file dictates the skeleton (controllers, requests, pages, events, commands)
2. **FRAS verbatim port source** — which FRAS file dictates the body (enrollment service, photo processor, job, ACK handler)
3. **IRMS tweaks at seams** — UUID FKs, config path (`fras.*` vs `hds.*`), broadcast event rename, Intervention v4 API, Personnel UUID as filename, route-level revocation check

The UI-SPEC freezes all visual/copy/a11y decisions, so plans should reference `20-UI-SPEC.md §Surface contracts` for Vue pages and `20-UI-SPEC.md §Color` for every status badge. CONTEXT.md's D-01..D-43 are the locked behavior decisions.

**Two novel designs not covered by analogs:**
- Two-namespace photo URL scheme (`FrasPhotoAccessController` + route-level revocation via `photo_access_token` rotation) — D-20..D-23
- Route-level operator-signed photo URL via `temporarySignedRoute` — D-22

Both are standard Laravel patterns (`Storage::disk()->response()`, `URL::temporarySignedRoute`, `->middleware('signed')`), not custom inventions.
