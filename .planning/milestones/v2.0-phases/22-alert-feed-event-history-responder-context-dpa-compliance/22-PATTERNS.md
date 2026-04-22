# Phase 22: Alert Feed + Event History + Responder Context + DPA Compliance — Pattern Map

**Mapped:** 2026-04-22
**Files analyzed:** 60 target files (44 NEW, 16 MOD)
**Analog coverage:** 56 / 60 with a strong in-repo analog; 4 files have no prior in-repo analog (see `## No Analog Found`).
**Source-of-truth docs consumed:** `22-CONTEXT.md` (D-01..D-39), `22-RESEARCH.md`, `22-UI-SPEC.md`, `CLAUDE.md`.

This map groups every target file into one of four execution waves aligned with `22-RESEARCH.md`'s recommended decomposition, so each wave lands as a coherent vertical slice. Every row cites a concrete existing analog (file + lines) and the exact code excerpt the planner should copy.

---

## File Classification (full index)

| # | Target path | Role | Data flow | Closest analog | Match quality | Wave |
|---|------|------|-----------|----------------|---------------|------|
| 1 | `database/migrations/2026_*_add_dismissed_by_and_reason_to_recognition_events.php` | migration | write | `database/migrations/2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php` | exact (ALTER pattern) | 1 |
| 2 | `database/migrations/2026_*_create_fras_access_log_table.php` | migration | write | `database/migrations/2026_04_21_000004_create_recognition_events_table.php` | role-match | 1 |
| 3 | `database/migrations/2026_*_create_fras_purge_runs_table.php` | migration | write | same | role-match | 1 |
| 4 | `database/migrations/2026_*_create_fras_legal_signoffs_table.php` | migration | write | same | role-match | 1 |
| 5 | `database/migrations/2026_*_add_fras_audio_muted_to_users.php` | migration | write | `database/migrations/2026_04_22_000001_add_photo_access_token_to_personnel_table.php` | exact | 1 |
| 6 | `app/Enums/FrasDismissReason.php` | enum | read-only | `app/Enums/PersonnelCategory.php` | exact | 1 |
| 7 | `app/Enums/FrasAccessSubject.php` | enum | read-only | `app/Enums/RecognitionSeverity.php` | exact | 1 |
| 8 | `app/Enums/FrasAccessAction.php` | enum | read-only | `app/Enums/CameraStatus.php` | exact | 1 |
| 9 | `app/Providers/AppServiceProvider.php` (MOD) | policy-gate | policy-gate | `app/Providers/AppServiceProvider.php:116-189` | self (extend) | 1 |
| 10 | `app/Http/Middleware/HandleInertiaRequests.php` (MOD) | middleware | read | `app/Http/Middleware/HandleInertiaRequests.php:46-68` | self (extend) | 1 |
| 11 | `app/Models/RecognitionEvent.php` (MOD) | model | CRUD | `app/Models/RecognitionEvent.php` (self) | self (extend) | 1 |
| 12 | `app/Models/User.php` (MOD) | model | CRUD | `app/Models/User.php` (self) | self (extend) | 1 |
| 13 | `app/Models/FrasAccessLog.php` | model | audit-log | `app/Models/RecognitionEvent.php` | role-match | 1 |
| 14 | `app/Models/FrasPurgeRun.php` | model | CRUD | `app/Models/CameraEnrollment.php` | role-match | 1 |
| 15 | `app/Models/FrasLegalSignoff.php` | model | CRUD | `app/Models/CameraEnrollment.php` | role-match | 1 |
| 16 | `app/Events/FrasAlertAcknowledged.php` | event | broadcast | `app/Events/RecognitionAlertReceived.php` | exact | 1 |
| 17 | `tests/Feature/Fras/FrasGatesTest.php` | pest-feature-test | policy-gate | `tests/Feature/Fras/BroadcastAuthorizationTest.php` | role-match | 1 |
| 18 | `app/Http/Controllers/FrasEventFaceController.php` (MOD) | controller | signed-url-hydrate + audit-log | self | self (extend) | 2 |
| 19 | `app/Http/Controllers/FrasEventSceneController.php` | controller | signed-url-hydrate + audit-log | `app/Http/Controllers/FrasEventFaceController.php` | exact | 2 |
| 20 | `app/Console/Commands/FrasPurgeExpired.php` | console-command | schedule + audit-log | `app/Console/Commands/PersonnelExpireSweepCommand.php` | role-match | 2 |
| 21 | `config/fras.php` (MOD) | config | read-only | `config/fras.php:41-52` | self (extend) | 2 |
| 22 | `routes/console.php` (MOD) | scheduler | schedule | `routes/console.php:16-29` | self (extend) | 2 |
| 23 | `tests/Feature/Fras/FrasAccessLogTest.php` | pest-feature-test | audit-log | `tests/Feature/Fras/FrasPhotoAccessControllerTest.php` | role-match | 2 |
| 24 | `tests/Feature/Fras/FrasPurgeExpiredCommandTest.php` | pest-feature-test | schedule + write | `tests/Feature/Fras/PersonnelExpireSweepTest.php` | exact | 2 |
| 25 | `app/Http/Controllers/FrasAlertFeedController.php` | controller | request-response + broadcast | `app/Http/Controllers/IntakeStationController.php:31-112` + Phase 21 `FrasEventFaceController` | role-match | 3 |
| 26 | `app/Http/Controllers/FrasEventHistoryController.php` | controller | read + write | `app/Http/Controllers/IntakeStationController.php` | role-match | 3 |
| 27 | `app/Http/Controllers/FrasAudioMuteController.php` | controller | request-response | `app/Http/Controllers/PushSubscriptionController.php` | role-match | 3 |
| 28 | `app/Http/Requests/Fras/AcknowledgeFrasAlertRequest.php` | form-request | policy-gate | `app/Http/Requests/TriageIncidentRequest.php` | exact | 3 |
| 29 | `app/Http/Requests/Fras/DismissFrasAlertRequest.php` | form-request | policy-gate | `app/Http/Requests/TriageIncidentRequest.php` | exact | 3 |
| 30 | `app/Http/Requests/Fras/PromoteRecognitionEventRequest.php` | form-request | policy-gate | `app/Http/Requests/TriageIncidentRequest.php` | exact | 3 |
| 31 | `app/Http/Requests/Fras/UpdateFrasAudioMuteRequest.php` | form-request | policy-gate | `app/Http/Requests/TriageIncidentRequest.php` | exact | 3 |
| 32 | `app/Services/FrasIncidentFactory.php` (MOD) | service | CRUD + broadcast | `app/Services/FrasIncidentFactory.php::createFromRecognition` (self) | self (extend) | 3 |
| 33 | `routes/web.php` (MOD) | route | request-response | `routes/web.php:89-168` | self (extend) | 3 |
| 34 | `resources/js/composables/useFrasFeed.ts` | composable | event-driven | `resources/js/composables/useFrasAlerts.ts` + `resources/js/composables/useIntakeFeed.ts:14-139` | exact | 3 |
| 35 | `resources/js/pages/fras/Alerts.vue` | inertia-page | event-driven | `resources/js/pages/intake/IntakeStation.vue` | role-match | 3 |
| 36 | `resources/js/pages/fras/Events.vue` | inertia-page | read | `resources/js/pages/intake/IntakeStation.vue` | role-match | 3 |
| 37 | `resources/js/components/fras/AlertCard.vue` | vue-component | read | `resources/js/components/fras/FrasSeverityBadge.vue` + intake card conventions | partial | 3 |
| 38 | `resources/js/components/fras/DismissReasonModal.vue` | vue-component | request-response | `resources/js/components/ui/dialog/*` + `TriagePanel` | partial | 3 |
| 39 | `resources/js/components/fras/PromoteIncidentModal.vue` | vue-component | request-response | same | partial | 3 |
| 40 | `resources/js/components/fras/FrasEventDetailModal.vue` | vue-component | read | `resources/js/components/intake/FrasEventDetailModal.vue` (existing Phase 21) | exact | 3 |
| 41 | `resources/js/components/fras/EventHistoryTable.vue` | vue-component | read | none direct; compose from `ui/` primitives | none | 3 |
| 42 | `resources/js/components/fras/EventHistoryFilters.vue` | vue-component | read | none direct; pattern from `useDebounceFn` + Inertia `router.get` | none | 3 |
| 43 | `resources/js/components/fras/ReplayBadge.vue` | vue-component | read | `resources/js/components/fras/FrasSeverityBadge.vue` | role-match | 3 |
| 44 | `resources/js/components/fras/AudioMuteToggle.vue` | vue-component | request-response | `resources/js/components/ui/button/Button.vue` | partial | 3 |
| 45 | `resources/js/components/fras/ImagePurgedPlaceholder.vue` | vue-component | read | none direct (utility micro-component) | none | 3 |
| 46 | `tests/Feature/Fras/FrasAlertFeedTest.php` | pest-feature-test | request-response + broadcast | `tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php` | exact | 3 |
| 47 | `tests/Feature/Fras/FrasEventHistoryTest.php` | pest-feature-test | read | `tests/Feature/Fras/IntakeStationFrasRailTest.php` | role-match | 3 |
| 48 | `tests/Feature/Fras/PromoteRecognitionEventTest.php` | pest-feature-test | CRUD + broadcast | `tests/Feature/Fras/FrasIncidentFactoryTest.php` | exact | 3 |
| 49 | `app/Http/Controllers/ResponderController.php` (MOD) | controller | signed-url-hydrate | `app/Http/Controllers/IntakeStationController.php:69-100` | role-match | 4 |
| 50 | `resources/js/components/responder/SceneTab.vue` (MOD) | vue-component | read | `resources/js/components/responder/SceneTab.vue` (self) | self (extend) | 4 |
| 51 | `resources/js/pages/responder/Station.vue` (MOD) | inertia-page | read | self | self (pass-through) | 4 |
| 52 | `resources/js/components/fras/PersonOfInterestAccordion.vue` | vue-component | read | `resources/js/components/ui/collapsible/*` + SceneTab accordion pattern | partial | 4 |
| 53 | `app/Http/Controllers/PrivacyNoticeController.php` | controller | read | `app/Http/Controllers/IntakeStationController.php` (Inertia::render shape only) | partial | 4 |
| 54 | `resources/js/pages/Privacy.vue` | inertia-page | read | `resources/js/pages/Welcome.vue` (public-facing) | partial | 4 |
| 55 | `resources/js/layouts/PublicLayout.vue` | layout | read | `resources/js/layouts/AuthLayout.vue` | role-match | 4 |
| 56 | `resources/privacy/privacy-notice.md` | doc | read | none | none | 4 |
| 57 | `resources/privacy/privacy-notice.tl.md` | doc | read | none | none | 4 |
| 58 | `app/Console/Commands/FrasDpaExport.php` | console-command | file-I/O | `app/Console/Commands/PersonnelExpireSweepCommand.php` | role-match | 4 |
| 59 | `resources/views/dpa/export.blade.php` | blade-template | read | none direct | none | 4 |
| 60 | `docs/dpa/PIA-template.md` + `signage-template.md` + `signage-template.tl.md` + `operator-training.md` | doc | read | none | none | 4 |
| 61 | `tests/Feature/Fras/ResponderSceneTabTest.php` | pest-feature-test | read + policy-gate | `tests/Feature/Responder/*` + `BroadcastAuthorizationTest.php` | role-match | 4 |
| 62 | `tests/Feature/Fras/PrivacyNoticeTest.php` | pest-feature-test | read | `tests/Feature/DashboardTest.php` | role-match | 4 |

---

# Wave 1 — Schema + Gates + Primitives

Files in Wave 1 establish the storage + enum + gate substrate every later wave binds to. No controllers or Vue pages yet; this wave is migrations + models + enums + the 5 new Gate::define calls + HandleInertiaRequests extension + the FrasAlertAcknowledged broadcast event (because Wave 3's controllers dispatch it).

---

### `database/migrations/YYYY_MM_DD_add_dismissed_by_and_reason_to_recognition_events.php`

- **Role:** migration
- **Data flow:** write
- **Closest analog:** `database/migrations/2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php`
- **CRITICAL RECONCILIATION** (per `22-RESEARCH.md` finding #2): the `recognition_events` table **ALREADY HAS** `acknowledged_by` (`foreignId` → `users.id`, bigint), `acknowledged_at`, and `dismissed_at` columns (shipped Phase 18 — see excerpt below). Phase 22 **must not** re-create them. Add only the THREE missing columns + two indexes.

**Analog excerpt — Phase 18 baseline** (`database/migrations/2026_04_21_000004_create_recognition_events_table.php:46-51`):
```php
$table->string('severity', 10)->default('info');
$table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestampTz('acknowledged_at', precision: 0)->nullable();
$table->timestampTz('dismissed_at', precision: 0)->nullable();
```

**ALTER pattern to copy** (from `2026_04_22_000002_add_barangay_and_notes_to_cameras_table.php`):
```php
return new class extends Migration {
    public function up(): void {
        Schema::table('recognition_events', function (Blueprint $table) {
            $table->foreignId('dismissed_by')->nullable()->after('dismissed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('dismiss_reason', 32)->nullable()->after('dismissed_by');
            $table->text('dismiss_reason_note')->nullable()->after('dismiss_reason');
            $table->index('acknowledged_at');
            $table->index('dismissed_at');
        });

        DB::statement(
            'ALTER TABLE recognition_events ADD CONSTRAINT recognition_events_dismiss_reason_check '
            ."CHECK (dismiss_reason IS NULL OR dismiss_reason IN ('false_match','test_event','duplicate','other'))"
        );
    }
    public function down(): void { /* drop in reverse, including dropping the CHECK first */ }
};
```

**Adaptations for Phase 22:**
- Use `foreignId` (bigint), NOT `foreignUuid` — `users` uses `$table->id()`.
- Add the CHECK constraint via raw `DB::statement` to mirror Phase 18's `severity` CHECK idiom (line 62-65 of baseline migration).
- Do NOT touch `acknowledged_by` / `acknowledged_at` / `dismissed_at` — they already exist.

---

### `database/migrations/YYYY_MM_DD_create_fras_access_log_table.php`

- **Role:** migration
- **Data flow:** write (audit-log)
- **Closest analog:** `database/migrations/2026_04_21_000004_create_recognition_events_table.php`

**Pattern excerpt** (UUID PK + timestamptz + indexes + PostgreSQL CHECK):
```php
Schema::create('fras_access_log', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete(); // bigint
    $table->ipAddress('ip_address');
    $table->string('user_agent', 255)->nullable();
    $table->string('subject_type', 48);              // FrasAccessSubject enum values
    $table->uuid('subject_id');                       // RecognitionEvent.id OR Personnel.id
    $table->string('action', 16);                     // FrasAccessAction enum values
    $table->timestampTz('accessed_at', precision: 0)->index();
    $table->timestamps();

    $table->index(['subject_type', 'subject_id']);
    $table->index(['actor_user_id', 'accessed_at']);
});

DB::statement(
    'ALTER TABLE fras_access_log ADD CONSTRAINT fras_access_log_subject_type_check '
    ."CHECK (subject_type IN ('recognition_event_face','recognition_event_scene','personnel_photo'))"
);
DB::statement(
    'ALTER TABLE fras_access_log ADD CONSTRAINT fras_access_log_action_check '
    ."CHECK (action IN ('view','download'))"
);
```

**Adaptations for Phase 22:**
- `foreignId` for `actor_user_id` (per RESEARCH.md FK reconciliation #2).
- No FK on `subject_id` — polymorphic across two parent tables (`recognition_events.id` and `personnel.id`); enforcement is at the enum CHECK + application layer.
- `cascadeOnDelete` on actor to preserve table integrity when a user is deleted (v1.0 hasn't deleted users but the FK must be valid).

---

### `database/migrations/YYYY_MM_DD_create_fras_purge_runs_table.php`

- **Role:** migration
- **Data flow:** write
- **Closest analog:** `database/migrations/2026_04_21_000004_create_recognition_events_table.php`

**Pattern excerpt** (verbatim from CONTEXT D-24 with timestamptz idiom applied):
```php
Schema::create('fras_purge_runs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->timestampTz('started_at', precision: 0);
    $table->timestampTz('finished_at', precision: 0)->nullable();
    $table->boolean('dry_run')->default(false);
    $table->unsignedInteger('face_crops_purged')->default(0);
    $table->unsignedInteger('scene_images_purged')->default(0);
    $table->unsignedInteger('skipped_for_active_incident')->default(0);
    $table->unsignedInteger('access_log_rows_purged')->default(0);
    $table->text('error_summary')->nullable();
    $table->timestamps();
});
```

**Adaptations for Phase 22:** add `timestamps()` (v1.0 convention on every table; CONTEXT omitted).

---

### `database/migrations/YYYY_MM_DD_create_fras_legal_signoffs_table.php`

- **Role:** migration
- **Data flow:** write
- **Closest analog:** same as above
- **Pattern excerpt:**
```php
Schema::create('fras_legal_signoffs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('signed_by_name', 150);
    $table->string('contact', 150);
    $table->timestampTz('signed_at', precision: 0);
    $table->text('notes')->nullable();
    $table->timestamps();
});
```
**Adaptations:** Pure vanilla; no enum/CHECK. Plain append-only audit table.

---

### `database/migrations/YYYY_MM_DD_add_fras_audio_muted_to_users.php`

- **Role:** migration
- **Data flow:** write
- **Closest analog:** `database/migrations/2026_04_22_000001_add_photo_access_token_to_personnel_table.php`
- **Pattern excerpt:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('fras_audio_muted')->default(false)->after('role');
});
```
**Adaptations:** boolean default false. `nullable` unnecessary — default false is semantic.

---

### `app/Enums/FrasDismissReason.php`

- **Role:** enum
- **Data flow:** read-only
- **Closest analog:** `app/Enums/PersonnelCategory.php`
- **Pattern excerpt** (canonical enum shape used across IRMS):
```php
<?php
namespace App\Enums;

enum FrasDismissReason: string
{
    case FalseMatch = 'false_match';
    case TestEvent = 'test_event';
    case Duplicate = 'duplicate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FalseMatch => 'False match',
            self::TestEvent => 'Test event',
            self::Duplicate => 'Duplicate alert',
            self::Other => 'Other',
        };
    }
}
```
**Adaptations:** `string`-backed, `TitleCase` keys (CLAUDE.md convention), `label()` method for UI (copy from UI-SPEC §DismissReasonModal).

---

### `app/Enums/FrasAccessSubject.php` & `app/Enums/FrasAccessAction.php`

- **Role:** enum
- **Data flow:** read-only
- **Closest analog:** `app/Enums/RecognitionSeverity.php` (for 3-case string-backed) + `app/Enums/CameraStatus.php` (for 2-case)
- **Pattern excerpt:**
```php
enum FrasAccessSubject: string
{
    case RecognitionEventFace = 'recognition_event_face';
    case RecognitionEventScene = 'recognition_event_scene';
    case PersonnelPhoto = 'personnel_photo';
}

enum FrasAccessAction: string
{
    case View = 'view';
    case Download = 'download';
}
```
**Adaptations:** Wave 2 controllers reference `FrasAccessSubject::RecognitionEventFace` and `FrasAccessAction::View` when writing audit rows.

---

### `app/Providers/AppServiceProvider.php` (MOD — append 5 gates)

- **Role:** policy-gate
- **Data flow:** policy-gate
- **Closest analog:** self — `app/Providers/AppServiceProvider.php:116-189` (existing 15 gates)
- **Pattern excerpt — v1.0 multi-role gate shape** (`AppServiceProvider.php:122-124`):
```php
Gate::define('create-incidents', fn (User $user): bool => in_array($user->role, [
    UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin,
], true));
```
**Adaptations for Phase 22 — append after line 189** (end of `configureGates()` closure, NOT "after line 167" as CONTEXT D-27 suggests — line 167 is inside `download-incident-report`; RESEARCH.md reconciled this):
```php
Gate::define('view-fras-alerts', fn (User $user): bool => in_array($user->role, [
    UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
], true));

Gate::define('manage-cameras', fn (User $user): bool => in_array($user->role, [
    UserRole::Supervisor, UserRole::Admin,
], true));

Gate::define('manage-personnel', fn (User $user): bool => in_array($user->role, [
    UserRole::Supervisor, UserRole::Admin,
], true));

Gate::define('trigger-enrollment-retry', fn (User $user): bool => in_array($user->role, [
    UserRole::Supervisor, UserRole::Admin,
], true));

Gate::define('view-recognition-image', fn (User $user): bool => in_array($user->role, [
    UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
], true));
```

---

### `app/Http/Middleware/HandleInertiaRequests.php` (MOD)

- **Role:** middleware
- **Data flow:** read
- **Closest analog:** self — `HandleInertiaRequests.php:52-68`
- **Pattern excerpt** (existing `can` shape):
```php
'can' => [
    'manage_users' => $user->can('manage-users'),
    // ...
    'view_session_log' => $user->can('view-session-log'),
],
```
**Adaptations for Phase 22 — append 5 keys to the `can` array:**
```php
'view_fras_alerts' => $user->can('view-fras-alerts'),
'manage_cameras' => $user->can('manage-cameras'),
'manage_personnel' => $user->can('manage-personnel'),
'trigger_enrollment_retry' => $user->can('trigger-enrollment-retry'),
'view_recognition_image' => $user->can('view-recognition-image'),
```
Also: expose `fras_audio_muted` on `user->only(...)` at line 51 so `usePage().props.auth.user.fras_audio_muted` is available for `useFrasFeed.ts` (D-06). Add to the `only()` list: `'fras_audio_muted'`.

---

### `app/Models/RecognitionEvent.php` (MOD)

- **Role:** model
- **Data flow:** CRUD
- **Closest analog:** self
- **Pattern:** extend `$fillable` and `$casts` and add two relations.
```php
// $fillable additions:
'dismissed_by', 'dismiss_reason', 'dismiss_reason_note',

// $casts additions:
'dismiss_reason' => \App\Enums\FrasDismissReason::class,

// new relations:
public function dismissedBy(): BelongsTo
{
    return $this->belongsTo(\App\Models\User::class, 'dismissed_by');
}
```
(Model already has `acknowledgedBy()` and `acknowledged_at` / `dismissed_at` casts from Phase 18; only the Phase 22-added columns need wiring.)

---

### `app/Models/User.php` (MOD)

- **Role:** model
- **Data flow:** CRUD
- **Pattern:**
```php
// $fillable additions:
'fras_audio_muted',

// $casts additions:
'fras_audio_muted' => 'bool',
```

---

### `app/Models/FrasAccessLog.php`

- **Role:** model (audit-log)
- **Data flow:** write-only
- **Closest analog:** `app/Models/RecognitionEvent.php` (UUID PK + timestamptz casts)
- **Pattern excerpt** (UUID PK + HasUuids trait + enum casts):
```php
<?php
namespace App\Models;

use App\Enums\FrasAccessAction;
use App\Enums\FrasAccessSubject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrasAccessLog extends Model
{
    use HasUuids;

    protected $table = 'fras_access_log';

    protected $fillable = [
        'actor_user_id', 'ip_address', 'user_agent',
        'subject_type', 'subject_id', 'action', 'accessed_at',
    ];

    protected $casts = [
        'subject_type' => FrasAccessSubject::class,
        'action' => FrasAccessAction::class,
        'accessed_at' => 'immutable_datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
```
**Adaptations:** No `subject()` morph relation (subject_id is UUID; parent is determined by `subject_type`). Access log is append-only in application — no `update()` or `delete()` methods in controller code.

---

### `app/Models/FrasPurgeRun.php` & `app/Models/FrasLegalSignoff.php`

- **Role:** model
- **Data flow:** CRUD
- **Closest analog:** `app/Models/CameraEnrollment.php` (UUID PK + HasUuids)
- **Pattern:** same HasUuids + `$fillable` skeleton; no enum casts beyond `dry_run => bool` and timestamp casts. Straightforward vanilla Eloquent.

---

### `app/Events/FrasAlertAcknowledged.php`

- **Role:** event
- **Data flow:** broadcast
- **Closest analog:** `app/Events/RecognitionAlertReceived.php`
- **Pattern excerpt** (full file):
```php
<?php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class FrasAlertAcknowledged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $eventId,
        public string $action,        // 'ack' | 'dismiss'
        public int $actorUserId,
        public string $actorName,
        public ?string $reason = null,      // FrasDismissReason value when dismiss
        public ?string $reasonNote = null,  // dismiss_reason_note when reason='other'
        public ?string $actedAt = null,     // ISO8601
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('fras.alerts')];
    }

    public function broadcastAs(): string
    {
        return 'FrasAlertAcknowledged';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'action' => $this->action,
            'actor_user_id' => $this->actorUserId,
            'actor_name' => $this->actorName,
            'reason' => $this->reason,
            'reason_note' => $this->reasonNote,
            'acted_at' => $this->actedAt ?? now()->toIso8601String(),
        ];
    }
}
```
**Adaptations for Phase 22:**
- Explicit `broadcastAs()` returns `'FrasAlertAcknowledged'` so the Vue `useEcho` handler binds by name (pattern `useFrasAlerts.ts:24` binds to the literal string).
- Uses scalar primitive constructor (no Eloquent model) — the event can fire after the RecognitionEvent row has been updated without re-hydrating it from DB.
- `ShouldDispatchAfterCommit` ensures the column write commits before the broadcast; operators on other workstations always see the fresh state.

---

### `tests/Feature/Fras/FrasGatesTest.php`

- **Role:** pest-feature-test
- **Data flow:** policy-gate
- **Closest analog:** `tests/Feature/Fras/BroadcastAuthorizationTest.php`
- **Pattern excerpt** (Pest role-matrix pattern):
```php
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);
pest()->group('fras');

dataset('role_matrix', [
    'admin' => [\App\Enums\UserRole::Admin, true],
    'supervisor' => [\App\Enums\UserRole::Supervisor, true],
    'operator' => [\App\Enums\UserRole::Operator, true],
    'dispatcher' => [\App\Enums\UserRole::Dispatcher, false],
    'responder' => [\App\Enums\UserRole::Responder, false],
]);

it('enforces view-fras-alerts gate per role', function ($role, $expected) {
    $user = \App\Models\User::factory()->create(['role' => $role]);
    expect($user->can('view-fras-alerts'))->toBe($expected);
})->with('role_matrix');
```
**Adaptations:** repeat the `it('enforces … gate', fn)` for each of the 5 gates with the appropriate role matrix. Reuse `dataset`.

---

# Wave 2 — Signed URLs + Audit + Retention

Files in Wave 2 deliver the DPA guarantees: sync audit log on every image fetch, the new scene-image controller, the daily retention purge command, and the config surface that drives them. This wave depends ONLY on Wave 1's primitives.

---

### `app/Http/Controllers/FrasEventFaceController.php` (MOD)

- **Role:** controller
- **Data flow:** signed-url-hydrate + audit-log
- **Closest analog:** self (extend — remove the `TODO(Phase 22)` comment at line 35 and wrap in `DB::transaction`)
- **Pattern excerpt** (current body — lines 23-42):
```php
public function show(Request $request, RecognitionEvent $event): StreamedResponse
{
    $user = $request->user();
    $allowedRoles = [UserRole::Operator, UserRole::Supervisor, UserRole::Admin];

    abort_unless($user && in_array($user->role, $allowedRoles, true), 403);
    abort_unless($event->face_image_path, 404);

    $disk = Storage::disk('fras_events');
    abort_unless($disk->exists($event->face_image_path), 404);

    // TODO(Phase 22): append row to fras_access_log capturing actor + IP + image ref + timestamp.

    return $disk->response($event->face_image_path, basename($event->face_image_path), [
        'Content-Type' => 'image/jpeg',
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, max-age=60',
    ]);
}
```
**Adaptations for Phase 22** (replace the TODO line; use `DB::transaction` per CONTEXT D-16 + tighten cache header per RESEARCH.md):
```php
use App\Enums\FrasAccessAction;
use App\Enums\FrasAccessSubject;
use App\Models\FrasAccessLog;
use Illuminate\Support\Facades\DB;

// ... after the two abort_unless above ...
DB::transaction(function () use ($request, $user, $event) {
    FrasAccessLog::create([
        'actor_user_id' => $user->id,
        'ip_address' => $request->ip(),
        'user_agent' => substr((string) $request->userAgent(), 0, 255),
        'subject_type' => FrasAccessSubject::RecognitionEventFace->value,
        'subject_id' => $event->id,
        'action' => FrasAccessAction::View->value,
        'accessed_at' => now(),
    ]);
});

return $disk->response($event->face_image_path, basename($event->face_image_path), [
    'Content-Type' => 'image/jpeg',
    'X-Content-Type-Options' => 'nosniff',
    'Cache-Control' => 'private, no-store, max-age=0', // DPA: no proxy caching beyond TTL
]);
```

---

### `app/Http/Controllers/FrasEventSceneController.php`

- **Role:** controller
- **Data flow:** signed-url-hydrate + audit-log
- **Closest analog:** `app/Http/Controllers/FrasEventFaceController.php` (just-modified above)
- **Pattern excerpt:** clone the Phase 22 modified face controller verbatim, changing:
  - `$event->face_image_path` → `$event->scene_image_path`
  - `FrasAccessSubject::RecognitionEventFace` → `FrasAccessSubject::RecognitionEventScene`
- **Adaptations for Phase 22:**
  - Namespace `App\Http\Controllers` (NOT `App\Http\Controllers\Fras\` — face controller sits at top level; keep consistent).
  - The route middleware chain MUST include `signed` (per D-36) — the signed URL is the second access guard after role.
  - **Responder exclusion is implicit:** the role gate `[Operator, Supervisor, Admin]` excludes Responder → 403. This is defense-in-depth layer 1 of the 3 layers per D-26.

---

### `app/Console/Commands/FrasPurgeExpired.php`

- **Role:** console-command
- **Data flow:** schedule + audit-log
- **Closest analog:** `app/Console/Commands/PersonnelExpireSweepCommand.php`
- **Pattern excerpt** (v1.0 sweep command shape — full file shown above):
```php
class PersonnelExpireSweepCommand extends Command
{
    protected $signature = 'irms:personnel-expire-sweep';
    protected $description = 'Unenroll personnel whose BOLO expiry has passed and soft-decommission the record';

    public function handle(CameraEnrollmentService $service): int
    {
        $expired = Personnel::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('decommissioned_at')
            ->get();

        foreach ($expired as $personnel) {
            // ... per-record work
            Log::channel('mqtt')->info('fras.personnel.expired', [...]);
        }
        return self::SUCCESS;
    }
}
```
**Adaptations for Phase 22:**
- Signature: `fras:purge-expired {--dry-run} {--verbose}`.
- Description: `Purge expired FRAS face crops and scene images per DPA retention policy`.
- Injected dependency: `Storage` facade (no service class — mirrors sweep command's simplicity).
- Write `FrasPurgeRun` summary row on start (`started_at = now()`, `dry_run = $this->option('dry-run')`) and update on finish (`finished_at`, counts, `error_summary`).
- Use `->cursor()` on the query (RESEARCH.md §5) to avoid loading all expired rows into memory.
- Per-event `DB::transaction`: `Storage::disk('fras_events')->delete($path)` then `$event->update([col => null])`. Both inside the transaction.
- Active-incident-protection query (per CONTEXT D-22):
  ```php
  ->where(fn ($q) => $q
      ->whereNull('incident_id')
      ->orWhereHas('incident', fn ($i) => $i->whereIn('status', [
          IncidentStatus::Resolved, IncidentStatus::Cancelled,
      ]))
  )
  ```
- Also purge `fras_access_log` rows older than `config('fras.retention.access_log_retention_days', 730)` days — increment `access_log_rows_purged` counter.
- Return `self::SUCCESS` / `self::FAILURE` per sweep convention.

---

### `config/fras.php` (MOD)

- **Role:** config
- **Data flow:** read-only
- **Closest analog:** self — `config/fras.php:41-52` (the `recognition` section shape)
- **Pattern excerpt:**
```php
'recognition' => [
    'confidence_threshold' => (float) env('FRAS_CONFIDENCE_THRESHOLD', 0.75),
    // ...
],
```
**Adaptations for Phase 22 — append a `retention` section before the closing `];`:**
```php
'retention' => [
    'scene_image_days' => (int) env('FRAS_RETENTION_SCENE_IMAGE_DAYS', 30),
    'face_crop_days' => (int) env('FRAS_RETENTION_FACE_CROP_DAYS', 90),
    'purge_run_schedule' => env('FRAS_PURGE_RUN_SCHEDULE', '02:00'),
    'access_log_retention_days' => (int) env('FRAS_ACCESS_LOG_RETENTION_DAYS', 730),
],
```

---

### `routes/console.php` (MOD)

- **Role:** scheduler
- **Data flow:** schedule
- **Closest analog:** self — `routes/console.php:16-29`
- **Pattern excerpt** (existing pattern):
```php
Schedule::command('irms:personnel-expire-sweep')
    ->hourly()
    ->withoutOverlapping()
    ->description('Unenroll personnel whose BOLO expiry has passed');
```
**Adaptations for Phase 22 — append:**
```php
Schedule::command('fras:purge-expired')
    ->dailyAt((string) config('fras.retention.purge_run_schedule', '02:00'))
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('FRAS retention purge failed'))
    ->description('Purge expired FRAS face/scene images per DPA retention policy');
```

---

### `tests/Feature/Fras/FrasAccessLogTest.php`

- **Role:** pest-feature-test
- **Data flow:** audit-log
- **Closest analog:** `tests/Feature/Fras/FrasPhotoAccessControllerTest.php`
- **Pattern excerpt** (Pest storage-fake + `$this->get()` shape — lines 10-30 of analog):
```php
pest()->group('fras');

beforeEach(function () {
    Storage::fake('fras_events'); // Phase 22 uses fras_events disk, not fras_photos
});

it('writes a fras_access_log row before streaming the face crop', function () {
    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/example.jpg',
    ]);
    Storage::disk('fras_events')->put('face/example.jpg', 'fake-jpeg');
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $url = URL::temporarySignedRoute('fras.events.face.show', now()->addMinutes(5), ['event' => $event->id]);
    $response = $this->actingAs($user)->get($url);

    $response->assertOk();
    expect(FrasAccessLog::count())->toBe(1);
    expect(FrasAccessLog::first())
        ->actor_user_id->toBe($user->id)
        ->subject_type->toBe(FrasAccessSubject::RecognitionEventFace)
        ->subject_id->toBe($event->id);
});
```
**Adaptations for Phase 22:** use `actingAs()` (the new controller is role-gated, not token-gated); use `URL::temporarySignedRoute` to build the URL (so `signed` middleware passes).

---

### `tests/Feature/Fras/FrasPurgeExpiredCommandTest.php`

- **Role:** pest-feature-test
- **Data flow:** schedule + write
- **Closest analog:** `tests/Feature/Fras/PersonnelExpireSweepTest.php`
- **Pattern excerpt:**
```php
pest()->group('fras');
beforeEach(fn () => Storage::fake('fras_events'));

it('purges face crops older than retention window', function () {
    config(['fras.retention.face_crop_days' => 90]);
    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/old.jpg',
        'captured_at' => now()->subDays(100),
    ]);
    Storage::disk('fras_events')->put('face/old.jpg', 'x');

    $this->artisan('fras:purge-expired')->assertSuccessful();

    expect($event->fresh()->face_image_path)->toBeNull();
    expect(Storage::disk('fras_events')->exists('face/old.jpg'))->toBeFalse();
    expect(FrasPurgeRun::latest()->first()->face_crops_purged)->toBe(1);
});

it('survives expired scene image when linked Incident is still Dispatched', function () {
    // ... (verbatim from RESEARCH.md §5 — mandatory SC5 test)
});

it('respects --dry-run by leaving files + columns untouched', function () { /* ... */ });
```
**Adaptations:** one test per branch — retention hit, active-incident protection, dry-run, access-log self-purge.

---

# Wave 3 — Controllers + Routes + Inertia Pages

Files in Wave 3 deliver the user-facing FRAS operator surface: the `/fras/alerts` live feed, the `/fras/events` history, ACK/Dismiss + Promote flows, the new composable, and all form request classes + the routes that wire them.

---

### `app/Http/Controllers/FrasAlertFeedController.php`

- **Role:** controller
- **Data flow:** request-response + broadcast
- **Closest analog:** `app/Http/Controllers/IntakeStationController.php:31-112` (for signed-URL hydration) + `app/Http/Controllers/FrasEventFaceController.php` (for role abort) + RESEARCH.md §1 (for ACK/Dismiss actions)
- **Pattern excerpt — index signed-URL hydration** (from `IntakeStationController.php:69-100`):
```php
$recentFrasEvents = RecognitionEvent::query()
    ->with(['camera:id,camera_id_display,name', 'personnel:id,name,category'])
    ->whereIn('severity', [RecognitionSeverity::Critical, RecognitionSeverity::Warning])
    ->orderByDesc('received_at')
    ->limit(50)
    ->get()
    ->map(function (RecognitionEvent $event) {
        $faceImagePath = $event->face_image_path;
        $faceImageUrl = $faceImagePath
            ? URL::temporarySignedRoute(
                'fras.event.face',
                now()->addMinutes(5),
                ['event' => $event->id],
            )
            : null;

        return [
            'event_id' => $event->id,
            'severity' => $event->severity->value,
            // ... denorm camera/personnel fields
            'face_image_url' => $faceImageUrl,
        ];
    });
```
**Adaptations for Phase 22:**
- `index()` — query: last 100 rows WHERE `acknowledged_at IS NULL AND dismissed_at IS NULL AND severity IN [critical, warning]`, `orderByDesc('captured_at')`. Signed-URL hydrate face crops into `face_image_url`. Render `Inertia::render('fras/Alerts', ['initialAlerts' => $mapped])`.
- `acknowledge(AcknowledgeFrasAlertRequest $request, RecognitionEvent $event)`:
  - `$event->update(['acknowledged_by' => $user->id, 'acknowledged_at' => now()])`
  - Dispatch `FrasAlertAcknowledged::dispatch($event->id, 'ack', $user->id, $user->name, null, null, now()->toIso8601String())`
  - Return `back()` (Inertia redirect).
- `dismiss(DismissFrasAlertRequest $request, RecognitionEvent $event)`:
  - `$event->update(['dismissed_by' => $user->id, 'dismissed_at' => now(), 'dismiss_reason' => $request->validated('reason'), 'dismiss_reason_note' => $request->validated('reason_note')])`
  - Dispatch `FrasAlertAcknowledged` with `action='dismiss'` + reason fields.
- No service class — state update is single-table.
- Route is already `auth` + `can:view-fras-alerts` middleware-gated per D-36; controller can skip `$this->authorize()` but adds it for defense-in-depth layer 2 per D-29.

---

### `app/Http/Controllers/FrasEventHistoryController.php`

- **Role:** controller
- **Data flow:** read + write
- **Closest analog:** `app/Http/Controllers/IntakeStationController.php` (for Inertia::render pattern) + CONTEXT D-10 query (verbatim)
- **Pattern excerpt — index query** (verbatim from CONTEXT D-10):
```php
$events = RecognitionEvent::query()
    ->with([
        'camera:id,camera_id_display,name',
        'personnel:id,name,category',
        'incident:id,incident_no,priority,status',
    ])
    ->when($severity, fn ($q, $s) => $q->whereIn('severity', $s))
    ->when($cameraId, fn ($q, $id) => $q->where('camera_id', $id))
    ->when($from, fn ($q, $d) => $q->where('captured_at', '>=', $d))
    ->when($to, fn ($q, $d) => $q->where('captured_at', '<=', $d))
    ->when($q, fn ($query, $term) => $query->where(fn ($w) =>
        $w->whereHas('personnel', fn ($p) => $p->where('name', 'ilike', "%{$term}%"))
          ->orWhereHas('camera', fn ($c) => $c
              ->where('camera_id_display', 'ilike', "%{$term}%")
              ->orWhere('name', 'ilike', "%{$term}%")
          )
    ))
    ->orderByDesc('captured_at')
    ->paginate(25);
```
**Adaptations for Phase 22:**
- Three actions: `index` (filters + pagination + replay-count hydrate), `show` (single event prop w/ signed face URL + signed scene URL + access-log meta), `promote` (delegates to `FrasIncidentFactory::createFromRecognitionManual`).
- Replay-count hydration: per RESEARCH.md §2, use the two-query hydrate approach (simpler than `COUNT(*) OVER`). Planner may pick window function if preferred.
- `promote` action flow:
  ```php
  public function promote(PromoteRecognitionEventRequest $request, RecognitionEvent $event): RedirectResponse
  {
      $incident = $this->factory->createFromRecognitionManual(
          $event,
          IncidentPriority::from($request->validated('priority')),
          $request->validated('reason'),
          $request->user(),
      );
      return redirect()->route('incidents.show', $incident);
  }
  ```
  Constructor-inject `FrasIncidentFactory` (v1.0 DI convention).

---

### `app/Http/Controllers/FrasAudioMuteController.php`

- **Role:** controller (minimal)
- **Data flow:** request-response
- **Closest analog:** `app/Http/Controllers/PushSubscriptionController.php` (another single-action auth-scoped user-preference write)
- **Pattern excerpt:**
```php
final class FrasAudioMuteController extends Controller
{
    public function update(UpdateFrasAudioMuteRequest $request): RedirectResponse
    {
        $request->user()->update([
            'fras_audio_muted' => $request->validated('muted'),
        ]);

        return back();
    }
}
```
**Adaptations:** Minimal — single method, thin. No Fortify dependency (CONTEXT D-37 explicitly chooses bespoke endpoint over Fortify integration).

---

### `app/Http/Requests/Fras/AcknowledgeFrasAlertRequest.php`

- **Role:** form-request
- **Data flow:** policy-gate
- **Closest analog:** `app/Http/Requests/TriageIncidentRequest.php`
- **Pattern excerpt** (full file shown earlier — verbatim structure):
```php
class TriageIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('triage-incidents');
    }

    public function rules(): array
    {
        return [
            'incident_type_id' => ['required', 'exists:incident_types,id'],
            'priority' => ['required', Rule::in(array_column(IncidentPriority::cases(), 'value'))],
            // ...
        ];
    }
}
```
**Adaptations for Phase 22 — four new form requests:**

**AcknowledgeFrasAlertRequest** — `authorize`: `Gate::allows('view-fras-alerts')`; `rules`: none (ACK is zero-body — event in route binding is the whole payload).

**DismissFrasAlertRequest** — `authorize`: same gate; `rules`:
```php
'reason' => ['required', Rule::in(array_column(FrasDismissReason::cases(), 'value'))],
'reason_note' => ['nullable', 'string', 'max:500', 'required_if:reason,other'],
```

**PromoteRecognitionEventRequest** — `authorize`: same gate; `rules`:
```php
'priority' => ['required', Rule::in(array_column(IncidentPriority::cases(), 'value'))],
'reason' => ['required', 'string', 'min:8', 'max:500'],
```

**UpdateFrasAudioMuteRequest** — `authorize`: `return $request->user() !== null;`; `rules`: `['muted' => ['required', 'boolean']]`.

---

### `app/Services/FrasIncidentFactory.php` (MOD — add `createFromRecognitionManual`)

- **Role:** service (extend)
- **Data flow:** CRUD + broadcast
- **Closest analog:** self — `createFromRecognition` at lines 105-187
- **Pattern excerpt** (write path of `createFromRecognition` — lines 142-186):
```php
return DB::transaction(function () use ($event, $personnel) {
    $type = $this->personOfInterestType ??= IncidentType::query()
        ->where('code', 'person_of_interest')
        ->firstOrFail();

    $priority = $this->resolvePriority($event->severity, $personnel->category);
    $camera = $event->camera()->first();

    $incident = Incident::query()->create([
        'incident_type_id' => $type->id,
        'priority' => $priority,
        'status' => IncidentStatus::Pending,
        'channel' => IncidentChannel::IoT,
        'coordinates' => $camera?->location,
        'barangay_id' => $camera?->barangay_id,
        'location_text' => $camera?->name ?? $camera?->camera_id_display,
        'notes' => $this->formatNotes($event, $personnel, $camera),
        'raw_message' => json_encode($event->raw_payload ?? []),
    ]);

    IncidentTimeline::query()->create([
        'incident_id' => $incident->id,
        'event_type' => 'incident_created',
        'event_data' => [
            'source' => 'fras_recognition',
            'recognition_event_id' => $event->id,
            // ... other fields
        ],
    ]);

    $event->incident_id = $incident->id;
    $event->save();

    $incident->load('incidentType', 'barangay');
    IncidentCreated::dispatch($incident);
    RecognitionAlertReceived::dispatch($event, $incident);

    return $incident;
});
```
**Adaptations for Phase 22** (new method `createFromRecognitionManual`):
```php
public function createFromRecognitionManual(
    RecognitionEvent $event,
    IncidentPriority $priority,
    string $reason,
    User $actor,
): Incident {
    // Gate: reject null personnel / allow-category (nothing to promote).
    $personnel = $event->personnel_id
        ? Personnel::query()->findOrFail($event->personnel_id)
        : abort(422, 'Cannot promote: no personnel match.');
    abort_if($personnel->category === PersonnelCategory::Allow, 422, 'Cannot promote: allow-list match.');

    // Skip severity + confidence + dedup gates — operator override.

    return DB::transaction(function () use ($event, $personnel, $priority, $reason, $actor) {
        $type = $this->personOfInterestType ??= IncidentType::query()
            ->where('code', 'person_of_interest')->firstOrFail();
        $camera = $event->camera()->first();

        $incident = Incident::query()->create([
            'incident_type_id' => $type->id,
            'priority' => $priority,                           // operator-picked
            'status' => IncidentStatus::Pending,
            'channel' => IncidentChannel::IoT,
            'coordinates' => $camera?->location,
            'barangay_id' => $camera?->barangay_id,
            'location_text' => $camera?->name ?? $camera?->camera_id_display,
            'notes' => $this->formatNotes($event, $personnel, $camera)
                . " — Manually promoted by {$actor->name}: {$reason}",
            'raw_message' => json_encode($event->raw_payload ?? []),
        ]);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'incident_created',
            'event_data' => [
                'source' => 'fras_recognition',
                'trigger' => 'fras_operator_promote',             // D-13 marker
                'recognition_event_id' => $event->id,
                'camera_id' => $event->camera_id,
                'personnel_id' => $event->personnel_id,
                'personnel_category' => $personnel->category->value,
                'confidence' => (float) $event->similarity,
                'captured_at' => $event->captured_at->toIso8601String(),
                'promoted_by_user_id' => $actor->id,
                'promoted_priority' => $priority->value,
                'promotion_reason' => $reason,
            ],
        ]);

        $event->incident_id = $incident->id;
        $event->save();

        $incident->load('incidentType', 'barangay');
        IncidentCreated::dispatch($incident);
        RecognitionAlertReceived::dispatch($event, $incident);

        return $incident;
    });
}
```
**Do NOT modify** the existing `createFromSensor` or `createFromRecognition` methods — additive only.

---

### `routes/web.php` (MOD)

- **Role:** route
- **Data flow:** request-response
- **Closest analog:** self — `routes/web.php:97-103` (the intake role-middleware group) and `routes/web.php:31-32` (public FRAS photo route)
- **Pattern excerpt — intake group** (existing):
```php
Route::middleware(['role:operator,supervisor,admin'])->group(function () {
    Route::get('intake', [IntakeStationController::class, 'show'])->name('intake.station');
    Route::post('intake/{incident}/triage', [IntakeStationController::class, 'triage'])->name('intake.triage');
    // ...
});
```
**Adaptations for Phase 22 — add inside the `auth,verified` group:**
```php
// Phase 22 FRAS alerts + events — operator/supervisor/admin with gate for 3-layer defense
Route::middleware(['role:operator,supervisor,admin', 'can:view-fras-alerts'])
    ->prefix('fras')->name('fras.')->group(function () {
        Route::get('alerts', [FrasAlertFeedController::class, 'index'])->name('alerts.index');
        Route::post('alerts/{event}/ack', [FrasAlertFeedController::class, 'acknowledge'])->name('alerts.ack');
        Route::post('alerts/{event}/dismiss', [FrasAlertFeedController::class, 'dismiss'])->name('alerts.dismiss');
        Route::get('events', [FrasEventHistoryController::class, 'index'])->name('events.index');
        Route::get('events/{event}', [FrasEventHistoryController::class, 'show'])->name('events.show');
        Route::post('events/{event}/promote', [FrasEventHistoryController::class, 'promote'])->name('events.promote');
        Route::get('events/{event}/scene', [FrasEventSceneController::class, 'show'])
            ->middleware('signed')
            ->name('events.scene.show');
    });

// User audio-mute preference — any auth'd user
Route::post('fras/settings/audio-mute', [FrasAudioMuteController::class, 'update'])
    ->name('fras.settings.audio-mute.update');
```
**Also add (public, OUTSIDE the auth group — before line 17 next to FRAS photo or inside a new public block):**
```php
Route::get('privacy', [PrivacyNoticeController::class, 'show'])->name('privacy');
```
**Wayfinder regenerates** TypeScript actions automatically after route save.

---

### `resources/js/composables/useFrasFeed.ts`

- **Role:** composable
- **Data flow:** event-driven
- **Closest analog:** `resources/js/composables/useFrasAlerts.ts` (Echo subscription shape) + `resources/js/composables/useIntakeFeed.ts:14-139` (ring-buffer + `unshift/pop` pattern)
- **Pattern excerpt — useFrasAlerts Echo subscription** (full analog file):
```ts
import { useEcho } from '@laravel/echo-vue';
import type { RecognitionAlertPayload } from '@/types/fras';

export function useFrasAlerts(
    pulseCamera: (cameraId: string, severity: 'critical' | 'warning') => void,
): void {
    useEcho<RecognitionAlertPayload>(
        'fras.alerts',
        'RecognitionAlertReceived',
        (payload) => {
            if (payload.severity === 'critical' || payload.severity === 'warning') {
                pulseCamera(payload.camera_id, payload.severity);
            }
        },
    );
}
```
**Pattern excerpt — useIntakeFeed ring-buffer** (`useIntakeFeed.ts:14-16,133-137`):
```ts
const MAX_FEED_SIZE = 100;
// ...
target.value.unshift(newIncident);
if (target.value.length > MAX_FEED_SIZE) {
    target.value.pop();
}
```
**Adaptations for Phase 22** (synthesize both patterns):
```ts
import { useEcho } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';
import { ref, type Ref } from 'vue';
import { useAlertSystem } from '@/composables/useAlertSystem';
import type { FrasAlertItem, RecognitionAlertPayload, FrasAckPayload } from '@/types/fras';

const MAX_ALERTS = 100;

export function useFrasFeed(initialAlerts: FrasAlertItem[] = []) {
    const alerts: Ref<FrasAlertItem[]> = ref([...initialAlerts].slice(0, MAX_ALERTS));
    const page = usePage();
    const { playPriorityTone } = useAlertSystem();

    useEcho<RecognitionAlertPayload>('fras.alerts', 'RecognitionAlertReceived', (payload) => {
        if (payload.severity !== 'critical' && payload.severity !== 'warning') return;
        alerts.value.unshift(mapPayloadToAlert(payload));
        if (alerts.value.length > MAX_ALERTS) alerts.value.length = MAX_ALERTS;

        if (
            payload.severity === 'critical'
            && document.visibilityState === 'visible'
            && !page.props.auth?.user?.fras_audio_muted
        ) {
            playPriorityTone('P1');
        }
    });

    useEcho<FrasAckPayload>('fras.alerts', 'FrasAlertAcknowledged', (payload) => {
        alerts.value = alerts.value.filter(a => a.event_id !== payload.event_id);
    });

    return { alerts };
}
```
**Adaptations for Phase 22:**
- SIBLING of `useFrasAlerts.ts` — that file is NOT modified (Phase 21 map-pulse consumer is preserved per CONTEXT "out of scope").
- Audio playback guarded by **three** conditions: severity=critical AND tab visible AND user preference unmuted.
- Echo channel `fras.alerts` requires no changes to `routes/channels.php` (reuses Phase 21 auth).

---

### `resources/js/pages/fras/Alerts.vue`

- **Role:** inertia-page
- **Data flow:** event-driven
- **Closest analog:** `resources/js/pages/intake/IntakeStation.vue` (page structure + `useEcho` + Inertia prop hydration)
- **Pattern excerpt — page <script setup> opener:**
```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useFrasFeed } from '@/composables/useFrasFeed';
import AlertCard from '@/components/fras/AlertCard.vue';
import AudioMuteToggle from '@/components/fras/AudioMuteToggle.vue';
import type { FrasAlertItem } from '@/types/fras';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    initialAlerts: FrasAlertItem[];
}>();

const { alerts } = useFrasFeed(props.initialAlerts);
</script>
```
**Adaptations for Phase 22:**
- Uses `AppLayout` (NOT a new layout); FRAS Alerts is an operator-surface page, same chrome as intake/dispatch.
- Per UI-SPEC §Layout containers — outer `<div class="p-6 lg:p-8 space-y-6">`.
- ACK/Dismiss use Inertia `useForm` → `post(route('fras.alerts.ack', event.id))` and `post(route('fras.alerts.dismiss', event.id), { reason, reason_note })`. Copy the `useForm` pattern from `pages/auth/Login.vue` or `pages/settings/Password.vue`.

---

### `resources/js/pages/fras/Events.vue`

- **Role:** inertia-page
- **Data flow:** read
- **Closest analog:** `resources/js/pages/intake/IntakeStation.vue` (for `usePage` + prop pattern). URL-driven filter pattern has **no prior analog in repo** — CONTEXT D-09 is the spec; RESEARCH.md §2 gives the Inertia `router.get` snippet.
- **Pattern excerpt — URL-driven filter + debounced search (from RESEARCH.md §2):**
```vue
<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import { router } from '@inertiajs/vue3';

const applyFilters = (params, opts = {}) => router.get('/fras/events', params, {
    preserveState: true, preserveScroll: true, ...opts,
});
const applyTextSearch = useDebounceFn((q: string) => applyFilters(
    { ...currentParams, q },
    { replace: true },
), 300);
</script>
```
**Adaptations for Phase 22:**
- Debounce 300ms on `q` with `replace: true` so keystrokes don't pile up in history.
- Severity pills / camera / dates → `replace: false` so back-button works on deliberate filter changes.
- Pagination: Inertia `<Pagination>` component (if one exists — else roll from Laravel paginator props).

---

### `resources/js/components/fras/AlertCard.vue`, `DismissReasonModal.vue`, `PromoteIncidentModal.vue`

- **Role:** vue-component
- **Data flow:** request-response
- **Closest analog:** `resources/js/components/ui/dialog/*` primitives + `resources/js/components/intake/FrasEventDetailModal.vue` (existing Phase 21 modal)
- **Reka UI Dialog structure** (standard shadcn-vue pattern — planner composes):
```vue
<Dialog v-model:open="isOpen">
    <DialogContent class="max-w-2xl p-6 space-y-6">
        <DialogHeader>
            <DialogTitle>Dismiss Alert</DialogTitle>
            <DialogDescription>Dismissed alerts are removed from the live feed for all operators.</DialogDescription>
        </DialogHeader>
        <!-- radio group + textarea -->
        <DialogFooter>
            <Button variant="outline" @click="isOpen = false">Cancel</Button>
            <Button variant="destructive" @click="submit">Dismiss Alert</Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```
**Adaptations for Phase 22:**
- All 3 modals follow UI-SPEC §Copywriting Contract verbatim for labels, placeholders, and button text.
- Submit uses Inertia `useForm` against the Wayfinder-generated action (e.g., `@/actions/App/Http/Controllers/FrasAlertFeedController::dismiss`).
- Form state: use `form.processing` to drive the `variant="destructive"`'s disabled state + `{loading ? 'Dismissing…' : 'Dismiss Alert'}` label.
- `PromoteIncidentModal`: default P2 priority, live char counter on reason (min 8 / max 500), red when out of range.
- `AlertCard`: reuses `<FrasSeverityBadge>` (existing); ACK button styled with `--t-online` per UI-SPEC Color §ACK / Dismiss button color contract; opens `DismissReasonModal` from the Dismiss click.

---

### `resources/js/components/fras/FrasEventDetailModal.vue`

- **Role:** vue-component
- **Data flow:** read
- **Closest analog:** `resources/js/components/intake/FrasEventDetailModal.vue` (existing Phase 21)
- **Relocation note** (per CONTEXT D-12): Phase 21 has an intake-scoped modal at `components/intake/FrasEventDetailModal.vue`. Phase 22 wants a shared modal reused by both `/fras/alerts` and `/fras/events` pages. **Two options** (Claude's Discretion):
  1. Move the existing component from `components/intake/` → `components/fras/` and update the single import in `IntakeStation.vue`. Zero behavior change; one import churn.
  2. Create a new `components/fras/FrasEventDetailModal.vue` with extended props (adds access-log strip, scene-image section with `ImagePurgedPlaceholder` fallback, Promote button footer).
- **Recommend:** Option 2 (additive — leaves Phase 21 intake modal untouched; new Phase 22 modal has more props and conditional slots).

---

### `resources/js/components/fras/EventHistoryTable.vue`, `EventHistoryFilters.vue`, `ReplayBadge.vue`, `AudioMuteToggle.vue`, `ImagePurgedPlaceholder.vue`

- **Role:** vue-component
- **Data flow:** read (history table, filters, replay-badge, placeholder) / request-response (audio toggle)
- **Closest analog:** No direct analog for table + filters (URL-driven filter surface is new to the repo). For individual primitives: reuse `ui/badge`, `ui/button`, `ui/select`, `ui/input`, `ui/combobox`, `ui/tooltip` as per UI-SPEC §Design System reuse list.
- **Pattern excerpt — ReplayBadge** (copy structure from `FrasSeverityBadge.vue`):
```vue
<script setup lang="ts">
defineProps<{ count: number }>();
</script>
<template>
    <span
        v-if="count >= 2"
        class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-mono uppercase tracking-[1.5px]"
        :class="[
            'bg-[color-mix(in_srgb,var(--t-accent)_15%,transparent)]',
            'text-t-accent',
            'border-[color-mix(in_srgb,var(--t-accent)_40%,transparent)]',
        ]"
        :aria-label="`This personnel has appeared on this camera ${count} times in the last 24 hours`"
    >
        ×{{ count }} today
    </span>
</template>
```
**Adaptations for Phase 22:**
- All color references use `--t-accent`/`--t-online`/`--t-p1` per UI-SPEC — NO new CSS tokens.
- `EventHistoryFilters`: loader spinner (`lucide:Loader2`) visible only while the 300ms debounce + request is in flight; mapped from `router.get` `onStart`/`onFinish` callbacks.
- `AudioMuteToggle` posts to `fras.settings.audio-mute.update` via Inertia `router.post`.
- `ImagePurgedPlaceholder`: single UI utility component; accepts no props; copy verbatim from UI-SPEC §IMAGE PURGED placeholder color contract.

---

### `tests/Feature/Fras/FrasAlertFeedTest.php`

- **Role:** pest-feature-test
- **Data flow:** request-response + broadcast
- **Closest analog:** `tests/Feature/Fras/RecognitionAlertReceivedBroadcastTest.php` (for broadcast assertions) + `tests/Feature/Fras/FrasPhotoAccessControllerTest.php` (for auth + storage assertions)
- **Pattern excerpt:**
```php
pest()->group('fras');
uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake([FrasAlertAcknowledged::class]));

it('acknowledges an unACKd event and broadcasts', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $event = RecognitionEvent::factory()->create(['acknowledged_at' => null]);

    $this->actingAs($operator)
        ->post(route('fras.alerts.ack', $event->id))
        ->assertRedirect();

    expect($event->fresh()->acknowledged_by)->toBe($operator->id);
    Event::assertDispatched(FrasAlertAcknowledged::class, fn ($e) => $e->eventId === $event->id && $e->action === 'ack');
});

it('dismisses with a reason and broadcasts', function () { /* ... */ });
it('rejects ACK from a responder (403)', function () { /* ... */ });
```

---

### `tests/Feature/Fras/FrasEventHistoryTest.php`

- **Role:** pest-feature-test
- **Data flow:** read
- **Closest analog:** `tests/Feature/Fras/IntakeStationFrasRailTest.php` (for Inertia prop assertions on paginated events)
- **Pattern:** one test per filter axis (severity, camera, date range, free-text ILIKE on personnel name / camera label), one test for 25-per-page, one test for replay badge `count >= 2`, one test for the URL-preserved filter round-trip.

---

### `tests/Feature/Fras/PromoteRecognitionEventTest.php`

- **Role:** pest-feature-test
- **Data flow:** CRUD + broadcast
- **Closest analog:** `tests/Feature/Fras/FrasIncidentFactoryTest.php` (same DB seeding for `person_of_interest` IncidentType)
- **Pattern excerpt — beforeEach from analog (lines 24-59):**
```php
beforeEach(function () {
    Event::fake([IncidentCreated::class, RecognitionAlertReceived::class]);
    $category = IncidentCategory::firstOrCreate(
        ['name' => 'Crime / Security'],
        ['icon' => 'Shield', 'is_active' => true, 'sort_order' => 4]
    );
    IncidentType::updateOrCreate(
        ['code' => 'person_of_interest'],
        [/* ... */]
    );
});
```
**Adaptations:** one test per Wave 3 contract: `promotes with picked priority + reason`, `skips severity gate (promotes warning)`, `rejects null personnel (422)`, `rejects allow-category (422)`, `timeline contains trigger=fras_operator_promote + promoted_by_user_id`.

---

# Wave 4 — Responder Surface + DPA Docs + Legal Sign-off

Files in Wave 4 deliver the responder SceneTab Person-of-Interest accordion, the citizen-facing `/privacy` notice, the `docs/dpa/` package + export command.

---

### `app/Http/Controllers/ResponderController.php` (MOD — hydrate person_of_interest prop)

- **Role:** controller (extend `show()`)
- **Data flow:** signed-url-hydrate
- **Closest analog:** `app/Http/Controllers/IntakeStationController.php:69-100` (the exact signed-URL hydration Phase 22 mirrors)
- **Pattern excerpt — signed-URL generation** (from `IntakeStationController.php:80-86`):
```php
$faceImageUrl = $faceImagePath
    ? URL::temporarySignedRoute(
        'fras.event.face',
        now()->addMinutes(5),
        ['event' => $event->id],
    )
    : null;
```
**Adaptations for Phase 22 — inside `ResponderController::show()`, after the incident is loaded:**
```php
// D-25 + D-26: if the incident was born from a FRAS recognition, hydrate the
// Person-of-Interest context — face URL ONLY, never scene URL.
$personOfInterest = null;
$firstTimeline = $incident->timeline->first();
if ($firstTimeline && ($firstTimeline->event_data['source'] ?? null) === 'fras_recognition') {
    $recognitionEventId = $firstTimeline->event_data['recognition_event_id'] ?? null;
    if ($recognitionEventId) {
        $rec = RecognitionEvent::query()
            ->with(['camera:id,camera_id_display,name', 'personnel:id,name,category'])
            ->find($recognitionEventId);
        if ($rec && $rec->face_image_path) {
            $personOfInterest = [
                'face_image_url' => URL::temporarySignedRoute(
                    'fras.event.face', now()->addMinutes(5), ['event' => $rec->id],
                ),
                'personnel_name' => $rec->personnel?->name,
                'personnel_category' => $rec->personnel?->category?->value,
                'camera_label' => $rec->camera?->camera_id_display,
                'camera_name' => $rec->camera?->name,
                'captured_at' => $rec->captured_at?->toIso8601String(),
            ];
        }
    }
}
// Add $personOfInterest to the Inertia prop; NEVER include any 'scene_image_url' field.
```
**Adaptations for Phase 22:**
- No `scene_image_url` ever — defense-in-depth layer 3 per CONTEXT D-26.
- Null-safe all the way down (personnel / camera may be missing on older events).
- Wave 3's `FrasEventFaceController` (wrapped with `fras_access_log` write) will log the face URL fetch if/when the responder taps through — that's the DPA audit for responder image views.

---

### `resources/js/components/responder/SceneTab.vue` (MOD) and `resources/js/components/fras/PersonOfInterestAccordion.vue`

- **Role:** vue-component (extend existing SceneTab + create new accordion)
- **Data flow:** read
- **Closest analog:** `resources/js/components/ui/collapsible/*` (the ONLY collapse primitive in `ui/`; no `accordion` primitive exists — UI-SPEC §Design System confirms composing `collapsible` is the project-native choice)
- **Pattern excerpt — existing SceneTab section pattern** (from `SceneTab.vue:8-22`):
```vue
const openSection = ref<'checklist' | 'vitals' | 'assessment' | null>('checklist');
function toggleSection(section: 'checklist' | 'vitals' | 'assessment'): void {
    openSection.value = openSection.value === section ? null : section;
}
```
**Adaptations for Phase 22:**
- `SceneTab.vue` MOD — conditionally render `<PersonOfInterestAccordion :data="incident.person_of_interest" />` when `incident.person_of_interest` is truthy (backend-controlled per ResponderController MOD above). Collapsed by default per D-25.
- `PersonOfInterestAccordion.vue` NEW — composes `Collapsible` + `CollapsibleTrigger` + `CollapsibleContent` from `components/ui/collapsible`. Header: "Person of Interest" + category-colored chip. Contents: 80×80 face thumbnail (signed URL) + personnel name + category chip + camera label + captured_at relative time. No scene image template branch — defense-in-depth layer 4 per D-26.
- Reka UI Accordion explicitly ruled out per UI-SPEC §Design System ("no new ui/ primitives needed") — compose Collapsible instead.

---

### `app/Http/Controllers/PrivacyNoticeController.php`

- **Role:** controller (minimal, public-facing)
- **Data flow:** read
- **Closest analog:** `app/Http/Controllers/IntakeStationController.php::show()` for the `Inertia::render` shape only; Markdown compilation has no prior analog in repo.
- **Pattern excerpt** (from RESEARCH.md §6):
```php
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Inertia\Inertia;

final class PrivacyNoticeController extends Controller
{
    public function show(Request $request): Response
    {
        $lang = in_array($request->query('lang'), ['en', 'tl'], true) ? $request->query('lang') : 'en';
        $path = resource_path('privacy/privacy-notice' . ($lang === 'tl' ? '.tl' : '') . '.md');
        $md = file_get_contents($path);
        $html = (new GithubFlavoredMarkdownConverter(['html_input' => 'strip']))->convert($md);

        return Inertia::render('Privacy', [
            'content' => (string) $html,
            'availableLangs' => ['en', 'tl'],
            'currentLang' => $lang,
        ]);
    }
}
```
**Adaptations for Phase 22:**
- `'html_input' => 'strip'` sanitizes any inline HTML in the Markdown (defense against a malicious PR landing raw HTML — zero XSS surface).
- `league/commonmark` already vendored transitively per RESEARCH.md §Existing Codebase Baseline; Phase 22 can add it as an explicit `composer require` for dep-graph clarity (Claude's Discretion).

---

### `resources/js/pages/Privacy.vue` and `resources/js/layouts/PublicLayout.vue`

- **Role:** inertia-page (public) + layout
- **Data flow:** read
- **Closest analog:** `resources/js/pages/Welcome.vue` (existing public page) + `resources/js/layouts/AuthLayout.vue` (for minimal layout shape)
- **Pattern excerpt — Privacy.vue structure:**
```vue
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });
const props = defineProps<{
    content: string;          // pre-compiled, sanitized HTML
    availableLangs: string[];
    currentLang: 'en' | 'tl';
}>();
function switchLang(l: 'en' | 'tl') {
    router.get('/privacy', { lang: l }, { preserveScroll: false });
}
</script>
<template>
    <article class="max-w-[680px] mx-auto py-12 lg:py-16 px-6 prose">
        <!-- lang toggle header + CDRRMO brand + v-html="content" -->
        <div v-html="content" />
    </article>
</template>
```
**Adaptations for Phase 22:**
- `v-html` is safe here because `PrivacyNoticeController` strips HTML input at compile time — untrusted HTML can never reach the prop.
- `PublicLayout.vue` NEW — minimal wrapper, no sidebar, no auth nav, CDRRMO logo top-center. Light-mode-only per UI-SPEC §Design System (citizen surface).
- Per UI-SPEC §Typography, `prose` container overrides the 4-size scale with document-scoped H1/H2/H3/body — authorized exception.

---

### `resources/privacy/privacy-notice.md` + `privacy-notice.tl.md`

- **Role:** doc
- **Data flow:** read
- **Closest analog:** none in repo (first `.md` content file ever committed as runtime-served content)
- **Adaptations for Phase 22:**
- Git-tracked (PR-reviewable) — content skeleton from UI-SPEC §Privacy Notice section headings (H1–H2 locked; body paragraphs planner drafts from CONTEXT D-31).
- `[CDRRMO_DPO_NAME]` / `[CDRRMO_DPO_EMAIL]` / etc. placeholders to be filled pre-go-live (Claude's Discretion whether to pre-fill with best guesses or leave explicit).
- Filipino version mirrors the heading skeleton 1:1 (UI-SPEC provides translated headings verbatim).

---

### `app/Console/Commands/FrasDpaExport.php`

- **Role:** console-command (file-I/O)
- **Data flow:** file-I/O
- **Closest analog:** `app/Console/Commands/PersonnelExpireSweepCommand.php` (for command scaffold) + `barryvdh/laravel-dompdf` PDF generation (already in composer.json per RESEARCH.md)
- **Pattern excerpt** (command scaffold from sweep + dompdf call):
```php
class FrasDpaExport extends Command
{
    protected $signature = 'fras:dpa:export {--doc=all : pia|signage|training|all} {--lang=en : en|tl}';
    protected $description = 'Export DPA documentation (PIA, signage, operator training) to PDF via dompdf';

    public function handle(): int
    {
        $doc = $this->option('doc');
        $lang = $this->option('lang');
        $docs = $doc === 'all' ? ['pia', 'signage', 'training'] : [$doc];
        $outDir = storage_path('app/dpa-exports/' . now()->format('Y-m-d'));
        if (!is_dir($outDir)) { mkdir($outDir, 0755, true); }

        foreach ($docs as $d) {
            $mdFile = match ($d) {
                'pia' => base_path('docs/dpa/PIA-template.md'),
                'signage' => base_path('docs/dpa/signage-template' . ($lang === 'tl' ? '.tl' : '') . '.md'),
                'training' => base_path('docs/dpa/operator-training.md'),
            };
            $md = file_get_contents($mdFile);
            $html = (new GithubFlavoredMarkdownConverter(['html_input' => 'strip']))->convert($md);
            $pdf = Pdf::loadView('dpa.export', ['content' => $html, 'title' => ucfirst($d)]);
            $out = "{$outDir}/{$d}-{$lang}.pdf";
            $pdf->save($out);
            $this->info($out);
        }
        return self::SUCCESS;
    }
}
```
**Adaptations for Phase 22:** CLI output (file paths to stdout) for the operator to hand off to CDRRMO legal.

---

### `resources/views/dpa/export.blade.php`

- **Role:** blade-template
- **Data flow:** read
- **Closest analog:** none in repo (first DPA Blade template); check if `resources/views/reports/` exists for any PDF-rendered Blade precedent.
- **Pattern excerpt** (minimal prose template — planner extends with CDRRMO brand):
```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; line-height: 1.7; max-width: 680px; margin: 0 auto; padding: 40px; }
        h1 { font-size: 24pt; margin-bottom: 0.5em; }
        h2 { font-size: 16pt; margin-top: 1.5em; }
        h3 { font-size: 12pt; margin-top: 1em; }
    </style>
</head>
<body>
    {!! $content !!}
</body>
</html>
```
**Adaptations for Phase 22:** `DejaVu Sans` is dompdf's safe default (handles Filipino diacritics). Content is pre-sanitized via `html_input: strip`.

---

### `docs/dpa/PIA-template.md` + `signage-template.md` + `signage-template.tl.md` + `operator-training.md`

- **Role:** doc
- **Data flow:** read
- **Closest analog:** none in repo; CONTEXT D-33 specifies the 10-section PIA skeleton, signage merge fields, and training note topics.
- **Adaptations for Phase 22:** Planner authors Markdown skeleton per CONTEXT D-33 content list. Signage files include `{CAMERA_LOCATION}`, `{CONTACT_DPO}`, `{CONTACT_OFFICE}`, `{RETENTION_WINDOW}` merge-field tokens.

---

### `tests/Feature/Fras/ResponderSceneTabTest.php` & `tests/Feature/Fras/PrivacyNoticeTest.php`

- **Role:** pest-feature-test
- **Data flow:** read + policy-gate
- **Closest analog** (SceneTab): `tests/Feature/Fras/BroadcastAuthorizationTest.php` + `tests/Feature/Responder/*`
- **Closest analog** (Privacy): `tests/Feature/DashboardTest.php` (public/guest page test shape)
- **Pattern excerpt — ResponderSceneTabTest:**
```php
it('returns person_of_interest prop when incident.timeline[0] is fras_recognition', function () { /* ... */ });
it('does NOT return scene_image_url in responder prop (layer-3 defense)', function () {
    $responder = User::factory()->create(['role' => UserRole::Responder]);
    // ... seed incident w/ fras_recognition timeline + scene image ...
    $this->actingAs($responder)->get(route('responder.station'))
        ->assertInertia(fn ($page) => $page
            ->where('incident.person_of_interest.face_image_url', fn ($v) => !empty($v))
            ->missing('incident.scene_image_url')
            ->missing('incident.person_of_interest.scene_image_url'));
});
it('responder receives 403 from /fras/events/{event}/scene (layer-1 defense)', function () { /* ... */ });
```

---

## Shared Patterns

These cross-cutting patterns apply across multiple waves and should be referenced from every plan rather than re-specified in each.

### Role Gate Enforcement (3-layer — D-29)
- **Source:** `routes/web.php:97` (role middleware) + `app/Providers/AppServiceProvider.php:122-124` (Gate::define) + `app/Http/Middleware/HandleInertiaRequests.php:52-68` (auth.can shared prop)
- **Apply to:** every `/fras/*` route, every Fras controller method, every FormRequest `authorize()`.
- **Three layers:** (1) route `role:operator,supervisor,admin` + `can:view-fras-alerts`, (2) controller `$this->authorize('view-fras-alerts')`, (3) FormRequest `Gate::allows('view-fras-alerts')`.

### Signed-URL Hydration at Prop-Build Time
- **Source:** `app/Http/Controllers/IntakeStationController.php:80-86`
```php
$faceImageUrl = $event->face_image_path
    ? URL::temporarySignedRoute('fras.event.face', now()->addMinutes(5), ['event' => $event->id])
    : null;
```
- **Apply to:** `FrasAlertFeedController::index`, `FrasEventHistoryController::index`, `FrasEventHistoryController::show`, `ResponderController::show` (for face crop only, never scene).
- **Route name:** use the Wayfinder-generated named routes (`fras.events.face.show`, `fras.events.scene.show`) — NOT the Phase 21 legacy name `fras.event.face` if Phase 22 renames the route.

### `fras_access_log` Sync Write Wrapper
- **Source:** Wave 2 FrasEventFaceController MOD (see above)
- **Apply to:** `FrasEventFaceController::show`, `FrasEventSceneController::show`. NOT to `FrasPhotoAccessController` (RESEARCH.md §Reconciliation #3 — camera-token-gated, not human).
- **Pattern:** `DB::transaction(fn () => FrasAccessLog::create([...]))` BEFORE the `$disk->response(...)` return.

### `ShouldBroadcast + ShouldDispatchAfterCommit` Event Shape
- **Source:** `app/Events/RecognitionAlertReceived.php` + `app/Events/AssignmentPushed.php`
- **Apply to:** `app/Events/FrasAlertAcknowledged.php`.
- **Pattern:** `final class X implements ShouldBroadcast, ShouldDispatchAfterCommit` + `Dispatchable, InteractsWithSockets, SerializesModels` traits + `broadcastOn()` returning `[new PrivateChannel('fras.alerts')]` + `broadcastAs()` named event + `broadcastWith()` returning flat scalar array.

### FormRequest `authorize() + rules()` Structure
- **Source:** `app/Http/Requests/TriageIncidentRequest.php`
- **Apply to:** all 4 new Fras FormRequests + UpdateFrasAudioMuteRequest.
- **Pattern:** `authorize(): bool => Gate::allows(...)`; `rules(): array` returns `['field' => ['required', Rule::in(...)]]`.

### Pest 4 Feature Test Shape
- **Source:** `tests/Feature/Fras/FrasIncidentFactoryTest.php:1-60`
- **Apply to:** all 8 new Pest test files.
- **Pattern:** `uses(RefreshDatabase::class); pest()->group('fras'); beforeEach(fn () => Storage::fake(...)); it('…', fn () => /* arrange, act, assert */);` — PostgreSQL via `fras` group per FRAMEWORK-05.

### Tailwind v4 + Sentinel Token Usage (UI-SPEC frozen set)
- **Source:** UI-SPEC §Color + `resources/css/app.css` (Sentinel tokens)
- **Apply to:** all new Vue components.
- **Constraint:** ZERO new CSS tokens (UI-SPEC locked). All colors via `--t-accent`, `--t-online`, `--t-p1`, `--t-unit-onscene`, `--t-unit-offline`, `--t-ch-fras`, `--t-bg`, `--t-surface`, `--t-surface-alt`, `--t-border-med`, `--t-text-faint`.

### Reka UI Primitive Composition
- **Source:** `resources/js/components/ui/dialog/`, `resources/js/components/ui/collapsible/`, `resources/js/components/ui/button/`
- **Apply to:** every new Vue component.
- **Constraint:** no new `components/ui/*` primitives introduced — UI-SPEC confirms existing set is sufficient. Accordion is composed from Collapsible, not via Reka Accordion.

### Wayfinder Auto-Regeneration
- **Source:** `resources/js/actions/` (auto-generated; excluded from ESLint)
- **Apply to:** every new route in `routes/web.php`.
- **Pattern:** after route save, Wayfinder regenerates `@/actions/App/Http/Controllers/…` functions. Vue components use `import { acknowledge } from '@/actions/App/Http/Controllers/FrasAlertFeedController'` and call `form.post(acknowledge(event.id).url)`.

---

## No Analog Found

Files with no close match in the codebase. Planner uses RESEARCH.md excerpts or UI-SPEC directly for these.

| Target file | Role | Data flow | Why no analog | Planner source |
|---|---|---|---|---|
| `resources/js/components/fras/EventHistoryTable.vue` | vue-component | read | First URL-driven paginated table surface in repo | UI-SPEC §Event History table columns + §EventHistoryTable.vue in UI-SPEC §Typography (micro-caps headers) |
| `resources/js/components/fras/EventHistoryFilters.vue` | vue-component | read | First debounced-URL-filter surface (useDebounceFn + router.get) | RESEARCH.md §2 snippet + UI-SPEC §Event History filter controls |
| `resources/privacy/privacy-notice.md` / `.tl.md` | doc | read | First runtime-served Markdown content file in repo | UI-SPEC §Privacy Notice — section headings skeleton (English + Filipino verbatim) |
| `resources/views/dpa/export.blade.php` | blade-template | read | First PDF-export Blade template in repo | RESEARCH.md §6 PDF dompdf pattern |
| `docs/dpa/PIA-template.md` + `signage-template.{md,tl.md}` + `operator-training.md` | doc | read | First compliance docs in repo | CONTEXT D-33 (10-section PIA; signage merge fields; training topics) |

For each of these, the planner references RESEARCH.md or UI-SPEC section numbers instead of an in-repo analog.

---

## Metadata

- **Analog search scope:** `app/Http/Controllers/`, `app/Services/`, `app/Events/`, `app/Models/`, `app/Enums/`, `app/Http/Requests/`, `app/Console/Commands/`, `app/Http/Middleware/`, `app/Providers/`, `database/migrations/`, `config/`, `routes/`, `resources/js/composables/`, `resources/js/pages/`, `resources/js/components/ui/`, `resources/js/components/fras/`, `resources/js/components/intake/`, `resources/js/components/responder/`, `resources/js/layouts/`, `tests/Feature/Fras/`, `tests/Feature/Responder/`
- **Files scanned / read for excerpts:** 23 primary analog files; 40+ directories inventoried via `ls`/`Bash`.
- **Pattern extraction date:** 2026-04-22
- **Phase:** 22-alert-feed-event-history-responder-context-dpa-compliance

---

## PATTERN MAPPING COMPLETE
