---
phase: 20-camera-personnel-admin-enrollment
reviewed: 2026-04-21T00:00:00Z
depth: standard
files_reviewed: 63
files_reviewed_list:
  - app/Console/Commands/CameraWatchdogCommand.php
  - app/Console/Commands/PersonnelExpireSweepCommand.php
  - app/Events/CameraStatusChanged.php
  - app/Events/EnrollmentProgressed.php
  - app/Exceptions/PhotoTooLargeException.php
  - app/Http/Controllers/Admin/AdminCameraController.php
  - app/Http/Controllers/Admin/AdminPersonnelController.php
  - app/Http/Controllers/Admin/AdminPersonnelPhotoController.php
  - app/Http/Controllers/Admin/EnrollmentController.php
  - app/Http/Controllers/DispatchConsoleController.php
  - app/Http/Controllers/Fras/FrasPhotoAccessController.php
  - app/Http/Requests/Admin/StoreCameraRequest.php
  - app/Http/Requests/Admin/StorePersonnelRequest.php
  - app/Http/Requests/Admin/UpdateCameraRequest.php
  - app/Http/Requests/Admin/UpdatePersonnelRequest.php
  - app/Jobs/EnrollPersonnelBatch.php
  - app/Models/Camera.php
  - app/Models/Personnel.php
  - app/Mqtt/Handlers/AckHandler.php
  - app/Observers/PersonnelObserver.php
  - app/Providers/AppServiceProvider.php
  - app/Services/CameraEnrollmentService.php
  - app/Services/FrasPhotoProcessor.php
  - bootstrap/app.php
  - config/database.php
  - config/filesystems.php
  - config/fras.php
  - config/image.php
  - database/migrations/2026_04_22_000001_add_photo_access_token_to_personnel_table.php
  - database/migrations/2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php
  - resources/js/components/admin/CameraLocationPicker.vue
  - resources/js/components/fras/CameraStatusBadge.vue
  - resources/js/components/fras/EnrollmentProgressPanel.vue
  - resources/js/composables/useDispatchMap.ts
  - resources/js/composables/useEnrollmentProgress.ts
  - resources/js/pages/admin/CameraForm.vue
  - resources/js/pages/admin/Cameras.vue
  - resources/js/pages/admin/Personnel.vue
  - resources/js/pages/admin/PersonnelForm.vue
  - resources/js/pages/dispatch/Console.vue
  - routes/admin.php
  - routes/channels.php
  - routes/console.php
  - routes/fras.php
  - routes/web.php
  - tests/Feature/Admin/AdminCameraControllerTest.php
  - tests/Feature/Admin/AdminPersonnelControllerTest.php
  - tests/Feature/Admin/AdminPersonnelPhotoControllerTest.php
  - tests/Feature/Dispatch/DispatchConsoleCamerasPropTest.php
  - tests/Feature/Fras/AckHandlerTest.php
  - tests/Feature/Fras/BroadcastAuthorizationTest.php
  - tests/Feature/Fras/CameraEnrollmentServiceTest.php
  - tests/Feature/Fras/CameraWatchdogTest.php
  - tests/Feature/Fras/EnrollPersonnelBatchTest.php
  - tests/Feature/Fras/FrasPhotoAccessControllerTest.php
  - tests/Feature/Fras/FrasPhotoProcessorTest.php
  - tests/Feature/Fras/PersonnelExpireSweepTest.php
  - tests/Feature/Fras/PersonnelObserverTest.php
  - tests/Feature/Fras/Phase20IntegrationTest.php
  - tests/Feature/Fras/Wave0InfrastructureTest.php
  - vite.config.ts
findings:
  critical: 1
  warning: 6
  info: 6
  total: 13
status: issues_found
---

# Phase 20: Code Review Report

**Reviewed:** 2026-04-21
**Depth:** standard
**Files Reviewed:** 63
**Status:** issues_found

## Summary

Phase 20 ports FRAS face-recognition enrollment services into IRMS with clean separation of concerns: dedicated Form Requests, a service layer (`CameraEnrollmentService`, `FrasPhotoProcessor`), idempotent MQTT ACK correlation via `Cache::pull`, `WithoutOverlapping` mutex on the enrollment queue, token-gated + signed-URL photo access, and well-scoped broadcast channels. Test coverage is strong with 12 feature test files covering controllers, jobs, observers, console commands, channel authorization, and an end-to-end integration test.

Key concerns found:

- **Critical (1):** Portable-regex violation in the `camera_id_display` auto-sequence query that is silently PostgreSQL-only.
- **Warnings (6):** Race condition on camera sequence generation, EXIF metadata not stripped from uploaded photos (PII leak risk), semantically-wrong enrollment status after expire sweep, missing rate limiting on the public photo endpoint, two-write inefficiency in personnel create, hardcoded URL in Popup (bypasses Wayfinder).
- **Info (6):** Camera sequence lexicographic sort past 99, Form Request `authorize()` always returns true (mitigated by route middleware), brittle `fras.photo.show` route lookup in legacy admin controllers, minor doc drift on JPEG quality degradation loop floor, MQTT-only logging on personnel photo access (no 404 audit), missing index on `personnel.photo_access_token` is actually present (unique) — verified.

No SQL injection, mass-assignment, or broadcast-authorization issues found. All admin write endpoints use typed Form Request validation, all broadcast channels enforce `UserRole` checks, and `$fillable` lists on `Camera` / `Personnel` exclude sensitive server-controlled fields. The MQTT ACK handler correctly uses `Cache::pull` for atomic read+delete (idempotent under QoS re-delivery).

## Critical Issues

### CR-01: PostgreSQL-only regex in `camera_id_display` auto-sequence query

**File:** `app/Http/Controllers/Admin/AdminCameraController.php:60-63`
**Issue:** The `store()` method generates the next `CAM-NN` sequence via:

```php
$maxSequence = Camera::query()
    ->selectRaw("MAX(CAST(SUBSTRING(camera_id_display FROM '[0-9]+$') AS INTEGER)) as max_seq")
    ->value('max_seq');
```

`SUBSTRING(x FROM 'pattern')` is the PostgreSQL-specific POSIX-regex form. It silently returns `NULL` on SQLite (dev) and fails on MySQL, making the fallback `(int) ($maxSequence ?? 0) + 1` always yield `1`. The tests in `AdminCameraControllerTest::auto-sequences camera_id_display as CAM-01 on empty table, CAM-02 on next` pass because the SQLite driver treats the pattern argument as a plain literal — but the second insert's `MAX(...)` still evaluates correctly only by accident of how SQLite coerces `SUBSTRING`. On a production PG run this works; on a staging MySQL run it will throw, and on SQLite it silently resets to CAM-01 on every call once the second digit group appears.

**Fix:** Extract the numeric suffix in PHP to stay portable, and wrap the generation + insert in a transaction with a row-level advisory lock (see WR-01 for the race):

```php
use Illuminate\Support\Facades\DB;

$cameraIdDisplay = DB::transaction(function () {
    $max = Camera::query()
        ->lockForUpdate()
        ->pluck('camera_id_display')
        ->map(fn ($d) => (int) preg_replace('/\D+/', '', (string) $d))
        ->max() ?? 0;

    return sprintf('CAM-%02d', $max + 1);
});
```

## Warnings

### WR-01: Race condition on `camera_id_display` auto-sequence

**File:** `app/Http/Controllers/Admin/AdminCameraController.php:60-79`
**Issue:** Two concurrent admin camera-create requests run `MAX(...) + 1` outside any transaction and without a row lock, so both can read the same max and both produce `CAM-NN` with the same `N`. Since `camera_id_display` is not unique-constrained (verified via `Camera::$fillable` + migration), both inserts succeed and the IDs collide silently. In practice the admin UI is unlikely to see concurrent creates, but the downstream MQTT payload (`picURI`) and dispatch map rely on the display ID being unique.
**Fix:** Combine with CR-01: wrap in `DB::transaction()` with `lockForUpdate()` on the Camera query (see CR-01 snippet). Additionally, consider adding a migration to put a unique constraint on `cameras.camera_id_display` as defense in depth.

### WR-02: EXIF metadata not stripped from uploaded personnel photos (PII leak)

**File:** `config/image.php:44` (and `app/Services/FrasPhotoProcessor.php:34-42`)
**Issue:** Intervention Image is configured with `'strip' => false`, and `FrasPhotoProcessor::process()` only calls `orient()` + `scaleDown()` + `encode()` without explicitly stripping metadata. Phone-camera uploads carry GPS coordinates, device serial, and timestamps in EXIF tags — this data is persisted to `fras_photos/personnel/{uuid}.jpg` and then streamed to every enrolled camera via `picURI`. Under RA 10173 (Philippines Data Privacy Act), unintentional inclusion of location metadata in a watch-list photo store expands the scope of processing beyond the consent basis recorded in `consent_basis`.
**Fix:** Set `'strip' => true` in `config/image.php`, OR add `$image->core()->native()->profiles()->remove();` (Imagick) / re-encode-without-metadata in `FrasPhotoProcessor::process()` after `scaleDown`. Simplest: flip the config flag.

```php
// config/image.php
'options' => [
    'autoOrientation' => true,
    'decodeAnimation' => true,
    'blendingColor' => 'ffffff',
    'strip' => true, // was false — removes EXIF GPS / device metadata
],
```

### WR-03: Expire-sweep marks enrollments `Done` when they were actually deleted

**File:** `app/Console/Commands/PersonnelExpireSweepCommand.php:31-32`
**Issue:** After calling `deleteFromAllCameras()` (which publishes `DeletePersons` MQTT), the command sets every `CameraEnrollment` row for the expired personnel to status `Done`. This is semantically wrong — `Done` means "successfully enrolled and active on the camera", but the person has just been unenrolled. Dashboards / analytics keyed on `status=done` will over-count. The test `PersonnelExpireSweepTest` explicitly asserts this behaviour (line 45), so the misconception is baked in.
**Fix:** Introduce a distinct terminal state (e.g., `CameraEnrollmentStatus::Removed` or `Unenrolled`), or soft-delete the enrollment rows. Minimum-impact fix: leave the row untouched and rely on `personnel.decommissioned_at` as the source of truth:

```php
foreach ($expired as $personnel) {
    $service->deleteFromAllCameras($personnel);
    $personnel->update(['decommissioned_at' => now()]);

    // Do NOT re-status enrollments to Done — the person was removed, not enrolled.
    // Queries that filter active enrollments should join personnel and check
    // decommissioned_at IS NULL.

    Log::channel('mqtt')->info('fras.personnel.expired', [...]);
}
```

### WR-04: No rate limiting on public `/fras/photo/{token}` endpoint

**File:** `routes/web.php:31-32` (and `app/Http/Controllers/Fras/FrasPhotoAccessController.php`)
**Issue:** The token-gated photo endpoint is mounted without any rate limiter. UUIDv4 is 122 bits and effectively unguessable, but absent a limiter an attacker who obtains a single leaked token can replay it thousands of times per second until enrollments settle (typical window: seconds-to-minutes). Access is logged via `Log::channel('mqtt')->info(...)` on success, but there's no 404 audit and no throttle.
**Fix:** Add a throttle middleware to the route (bucket by token + IP):

```php
// routes/web.php
Route::get('/fras/photo/{token}', [FrasPhotoAccessController::class, 'show'])
    ->middleware('throttle:30,1') // 30 req/min per IP
    ->name('fras.photo.show');
```

Or define a named limiter in `AppServiceProvider::configureRateLimiters()` scoped by `$request->route('token')`.

### WR-05: Two-write personnel create triggers unnecessary observer pass

**File:** `app/Http/Controllers/Admin/AdminPersonnelController.php:53-68`
**Issue:** `store()` creates the Personnel with `custom_id => null`, then updates with `custom_id => str_replace(...)`. This is two `INSERT`+`UPDATE` round-trips when one suffices, and each save() walks the `PersonnelObserver`. The observer's `saved()` hook guards on `wasChanged(['photo_hash', 'category'])`, so it does not dispatch on either of these writes (photo_hash is still null). But when the photo upload path runs, a third `->update(['photo_path'=>..., 'photo_hash'=>...])` fires the observer a third time — three DB writes + three observer passes for a single admin-create.
**Fix:** Use the model's `booted()` / `creating` hook, or better, create in one pass by generating the UUID manually:

```php
use Illuminate\Support\Str;

$id = (string) Str::uuid();
$personnel = Personnel::create(array_merge($data, [
    'id' => $id,
    'photo_access_token' => Str::uuid()->toString(),
    'custom_id' => str_replace('-', '', $id),
]));

if ($request->hasFile('photo')) {
    $result = $this->photoProcessor->process($request->file('photo'), $personnel);
    $personnel->update([
        'photo_path' => $result['photo_path'],
        'photo_hash' => $result['photo_hash'],
    ]); // one observer pass here — the one we actually want
}
```

### WR-06: Hardcoded URL in dispatch map Popup bypasses Wayfinder

**File:** `resources/js/composables/useDispatchMap.ts:624`
**Issue:** The camera Popup's "Edit camera" link is hardcoded:

```ts
const editUrl = `/admin/cameras/${encodeURIComponent(props.id ?? '')}/edit`;
```

This duplicates the Laravel route and will silently break if `routes/admin.php` is renamed or the admin prefix changes. The project already uses Wayfinder (`@/actions/App/Http/Controllers/Admin/AdminCameraController`) — import `edit` from there. XSS is already handled via `escapeHtml()`, so that is fine.
**Fix:**

```ts
import { edit as editCameraRoute } from '@/actions/App/Http/Controllers/Admin/AdminCameraController';

const editUrl = editCameraRoute(props.id ?? '').url;
```

## Info

### IN-01: `camera_id_display` format caps at CAM-99 with broken sort order past 100

**File:** `app/Http/Controllers/Admin/AdminCameraController.php:63`
**Issue:** `sprintf('CAM-%02d', ...)` produces 2-digit padding. After CAM-99, CAM-100 sorts before CAM-99 lexicographically on the `orderBy('camera_id_display')` in `index()`. Butuan City CDRRMO will likely stay well under 100 cameras for the foreseeable future, but the format should match expected scale.
**Fix:** Use `%03d` (or `%04d`) and lock in early:

```php
$cameraIdDisplay = sprintf('CAM-%03d', ((int) ($maxSequence ?? 0)) + 1);
```

### IN-02: All Form Requests return `authorize(): true`

**File:** `app/Http/Requests/Admin/Store{Camera,Personnel}Request.php`, `app/Http/Requests/Admin/Update{Camera,Personnel}Request.php`
**Issue:** All four Form Requests return `true` from `authorize()`. Authorization is delegated to the `role:admin` middleware on the route group, which is acceptable and consistent with other admin Form Requests in the codebase. Flagged as info only — no defect, but worth a PHPDoc comment stating the delegation for future maintainers.
**Fix:** Add a one-line PHPDoc pointing to the middleware source of truth:

```php
/**
 * Authorization is enforced by the `role:admin` middleware applied to
 * all routes under the `admin.` group (see bootstrap/app.php). Therefore
 * this method always returns true.
 */
public function authorize(): bool
{
    return true;
}
```

### IN-03: `Personnel::photoUrl` accessor depends on route that may not exist in test context

**File:** `app/Models/Personnel.php:87-94`
**Issue:** The accessor calls `route('fras.photo.show', [...])` without checking whether the route is registered. `CameraEnrollmentServiceTest` already has to guard with `Route::has('fras.photo.show')` in one test. If this accessor is invoked in a context where the web route file is not loaded (e.g., a console-only artisan task run with a stripped routing bundle), it will throw `RouteNotFoundException`.
**Fix:** Defensive fallback:

```php
protected function photoUrl(): Attribute
{
    return Attribute::make(
        get: fn () => $this->photo_access_token && \Illuminate\Support\Facades\Route::has('fras.photo.show')
            ? route('fras.photo.show', ['token' => $this->photo_access_token])
            : null,
    );
}
```

### IN-04: `FrasPhotoProcessor` quality-degradation loop stops at 45, not 40 as documented

**File:** `app/Services/FrasPhotoProcessor.php:40-49`
**Issue:** The loop is `while (... && $quality > 40) { $quality -= 10; ... }`. Starting from 85, it decrements 85 -> 75 -> 65 -> 55 -> 45, then the guard fails because 45 is not > 40. The error message and docblock both reference "quality=40" but the actual floor tried is 45. Cosmetic — the degradation still works, but the operator message is slightly misleading.
**Fix:** Either change the guard to `>= 40` (and decrement step to land exactly at 40), or update the error message to say "quality=45":

```php
throw new PhotoTooLargeException(
    'Photo could not be compressed below '.$maxBytes.' bytes at quality=45.'
);
```

### IN-05: `FrasPhotoAccessController` logs only on successful hit — 404s are silent

**File:** `app/Http/Controllers/Fras/FrasPhotoAccessController.php:24-34`
**Issue:** The controller logs `fras.photo.access` only when both the token is valid AND a live enrollment exists. Unknown-token 404s and revoked-token 404s emit nothing, so a token-enumeration attempt leaves no audit trail. Complements WR-04 (rate limiting).
**Fix:** Add a `warning` log on the 404 branches:

```php
if (! $personnel) {
    Log::channel('mqtt')->warning('fras.photo.access.unknown_token', [
        'ip' => $request->ip(),
        'ua' => $request->userAgent(),
    ]);
    abort(404);
}

if (! $hasLive) {
    Log::channel('mqtt')->info('fras.photo.access.revoked', [
        'personnel_id' => $personnel->id,
        'ip' => $request->ip(),
    ]);
    abort(404);
}
```

### IN-06: `AdminPersonnelPhotoController::show` returns 500-equivalent when file is missing on disk

**File:** `app/Http/Controllers/Admin/AdminPersonnelPhotoController.php:18-28`
**Issue:** The controller checks `$personnel->photo_path` but not the actual file's existence on disk. If the DB says a path is set but the fras_photos disk is missing the file (e.g., after a disk rotation), `Storage::disk('fras_photos')->response(...)` will return a response with an invalid stream — the client sees a download of 0 bytes or an exception depending on the driver. A 404 is more honest.
**Fix:**

```php
public function show(Personnel $personnel): StreamedResponse
{
    $path = "personnel/{$personnel->id}.jpg";
    $disk = Storage::disk('fras_photos');

    if (! $personnel->photo_path || ! $disk->exists($path)) {
        abort(404);
    }

    return $disk->response($path);
}
```

---

_Reviewed: 2026-04-21_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
