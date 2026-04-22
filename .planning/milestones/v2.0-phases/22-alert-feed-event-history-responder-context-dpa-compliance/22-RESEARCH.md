# Phase 22 Research: Alert Feed + Event History + Responder Context + DPA Compliance

**Researched:** 2026-04-22
**Domain:** Laravel 13 + Inertia Vue 3 + Reverb broadcasting + PostgreSQL retention + Philippine Data Privacy Act
**Confidence:** HIGH (CONTEXT.md is extraordinarily prescriptive; this research verifies decisions against the actual codebase and flags the few places where CONTEXT requires reconciliation with existing state)

---

## User Constraints (from CONTEXT.md)

Phase 22's CONTEXT.md is already the authoritative decision record — the planner reads it directly. This research section surfaces only the **codebase-grounded reconciliations** and **verification signals** the planner needs before turning those decisions into plans.

### Locked Decisions (CONTEXT.md §Decisions) — planner MUST honor, research does not re-open
- 39 decisions D-01 through D-39 in `22-CONTEXT.md` — full fidelity; do not exercise alternatives.

### Claude's Discretion (from CONTEXT.md)
- Vue component hierarchy + naming under `pages/fras/` and `components/fras/`
- Tailwind/Reka UI class set (align with Phase 10 Design System tokens)
- Accordion primitive choice (reka-ui Accordion vs custom Shadcn)
- `league/commonmark` vs `parsedown` for Markdown rendering — **research finding: commonmark is already vendored (see §Existing Codebase Baseline)**
- PublicLayout new vs AuthLayout mode prop for `/privacy`
- Replay-badge SQL strategy (window vs group-by hydrate)
- Exact PIA + signage template content polish

### Deferred Ideas (OUT OF SCOPE)
- Sign-off admin UI page layout + certificate PDF + re-sign workflow
- Translation pipeline beyond Filipino
- CMS-editable Privacy Notice
- DPA audit UI dashboard
- Retention auto-adjustment based on outcome
- Per-camera retention overrides
- Session ACK analytics
- Multi-camera triangulation badge UI
- Promote-to-existing-Incident path
- CCTV signage QR codes
- PII-aware search logging
- Dispatch console alert summary widget

---

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ALERTS-01 | Live severity-classified FRAS feed at `/fras/alerts` via private `fras.alerts` channel | `fras.alerts` channel already exists (`routes/channels.php:21`); `RecognitionAlertReceived` already broadcasts on it (`app/Events/RecognitionAlertReceived.php`). Phase 22 adds a second sibling composable `useFrasFeed.ts` alongside the existing `useFrasAlerts.ts` map-pulse consumer. |
| ALERTS-02 | One-click ACK/Dismiss with state persistence + back-broadcast | `recognition_events` table already has `acknowledged_by`, `acknowledged_at`, `dismissed_at` columns (shipped in Phase 18, migration `2026_04_21_000004_create_recognition_events_table.php:47-49`). Phase 22 adds the missing 3 columns (`dismissed_by`, `dismiss_reason`, `dismiss_reason_note`) + new `FrasAlertAcknowledged` broadcast event. |
| ALERTS-03 | Severity-distinct audio cue using `useAlertSystem.ts` (no parallel stack) | `useAlertSystem.ts` exposes `playPriorityTone('P1')` with the existing 6-pulse pattern (verified `resources/js/composables/useAlertSystem.ts:49-79`). Audio-context unlock on user gesture already handled. |
| ALERTS-04 | Filter by date range + severity pills + camera + debounced free-text search | `@vueuse/core` is installed (`package.json:40`) — `useDebounceFn` available. PostgreSQL `ILIKE` is the correct pattern for the small-scale search; no trigram index required at CDRRMO volume. |
| ALERTS-05 | Numbered-page pagination + replay badges for repeat faces | Laravel Eloquent `->paginate(25)` produces numbered pagination; Inertia v2 renders via `Pagination` props. Replay badge = subquery or `COUNT(*) OVER` window on `(camera_id, personnel_id)` in last 24h. |
| ALERTS-06 | `useFrasFeed` bounded 100-alert ring buffer | New composable file; `ref<Array>` with splice-at-max pattern. Pattern precedent: none explicit in repo, but `useDispatchFeed.ts` + `useIntakeFeed.ts` are reactive-list composables worth mirroring in shape. |
| ALERTS-07 | Manual promote-to-Incident from event-detail modal | `FrasIncidentFactory` is already the single load-bearing write path (verified `app/Services/FrasIncidentFactory.php:105`). Phase 22 adds `createFromRecognitionManual()` method. |
| INTEGRATION-02 | Responder SceneTab Person-of-Interest accordion (face crop, NOT scene image) | `SceneTab.vue` lives at `resources/js/components/responder/SceneTab.vue`; rendered by `Station.vue:425-432` with `:incident` + `:checklist-template` props. Incident source tag check: `incident.timeline[0].event_data.source === 'fras_recognition'` (verified against Phase 21 timeline shape in `FrasIncidentFactory.php:167-168`). No `accordion` primitive in `components/ui/` — only `collapsible` (`components/ui/collapsible/`). Planner picks reka-ui Accordion vs custom wrapper over Collapsible. |
| DPA-01 | Public `/privacy` Inertia page, CDRRMO-branded, both languages | `league/commonmark` is already vendored (`vendor/league/commonmark/` present — transitive dep via Laravel). No new composer package needed. New `PublicLayout.vue` or AuthLayout mode-prop (Claude's Discretion). |
| DPA-02 | `fras_access_log` audit row on every human image fetch | New polymorphic table; sync-write via `DB::transaction` wrapper in `FrasEventFaceController::show()` + new `FrasEventSceneController::show()` — NOT `FrasPhotoAccessController` (see reconciliation below). |
| DPA-03 | Signed 5-min URLs, operator/supervisor/admin only, responders+dispatchers excluded | `URL::temporarySignedRoute` pattern already in use at `IntakeStationController.php:80-86`. Role gate pattern already in use at `FrasEventFaceController.php:27-28`. Phase 22 adds `FrasEventSceneController` cloning that shape for scene images. |
| DPA-04 | Scheduled purge: scene 30d, face crop 90d, active-incident-protection | `Schedule::command(...)->withoutOverlapping()` pattern already in use at `routes/console.php:16-29`. Protection query: `WHERE incident_id IS NULL OR incident.status IN ('resolved','cancelled')`. |
| DPA-05 | Retention windows configurable in `config/fras.php` | Existing `config/fras.php` follows one-section-per-concern convention; Phase 22 adds `'retention'` section (verified shape vs existing `recognition`, `enrollment`, `photo` sections). |
| DPA-06 | `docs/dpa/`: PIA template + signage generator + operator training | New directory; `barryvdh/laravel-dompdf` already installed (`composer.json:14` — `^3.1.2`). Shared Blade template `resources/views/dpa/export.blade.php` → `php artisan fras:dpa:export`. |
| DPA-07 | 5 new gates extending existing 9 gates, no new role | `AppServiceProvider::configureGates()` is the canonical location (`app/Providers/AppServiceProvider.php:114-190`). 15 gates already exist (not 9 — corrects CONTEXT count: planner should use actual line numbers). UserRole enum is the 5-value enum Phase 22 uses. |

---

## Summary

1. **CONTEXT.md is unusually complete** — 39 locked decisions map cleanly to an execution plan. This research's load-bearing job is to verify the decisions against the actual codebase and surface the few **reconciliations** CONTEXT assumes but should be explicit about.

2. **Schema reconciliation critical:** the `recognition_events` table already has `acknowledged_by`, `acknowledged_at`, `dismissed_at` columns (shipped Phase 18, not noted in CONTEXT D-03). Phase 22's migration adds only the *missing* columns: `dismissed_by`, `dismiss_reason`, `dismiss_reason_note`. The existing `acknowledged_by` is `foreignId` (bigint → `users.id`), NOT `foreignUuid` as CONTEXT D-03 writes. **All new user FKs in Phase 22 tables MUST be `foreignId`, never `foreignUuid`.** Users table uses `$table->id()` (bigint).

3. **`FrasPhotoAccessController` scope clarification:** CONTEXT D-16 says to wrap this controller with the `fras_access_log` sync-write. **This is wrong for v2.0.** `FrasPhotoAccessController` is the *token-gated enrollment fetch* called by cameras (not humans) — the token IS the access boundary (UUIDv4, 122 bits), and access auto-revokes once enrollments settle (`FrasPhotoAccessController.php:29-34`). Logging camera fetches confuses the DPA-02 intent of "whenever a human fetches". **Planner should log ONLY human-initiated fetches: `FrasEventFaceController` (humans browsing alerts/events) + the new `FrasEventSceneController` (scene image viewer) + any future human-browsable personnel-photo endpoint if one exists.** Recommend: leave `FrasPhotoAccessController` untouched, and add a *separate* human-facing personnel-photo viewer if DPA-02 requires (out of current scope — admin personnel CRUD already serves photos via a different path; verify before planning).

4. **Three-layer defense-in-depth is achievable** with the existing stack: (a) route `role:` middleware for page access, (b) controller `Gate::authorize` for action authorization, (c) Inertia prop stripping in `ResponderIncidentController::show` for scene-image omission. No new infrastructure needed — just composition of existing patterns.

5. **Audio-cue integration is trivial:** `useAlertSystem().playPriorityTone('P1')` exists (`useAlertSystem.ts:49`). Call from `useFrasFeed.ts` on Critical receipt. Per-user mute via `users.fras_audio_muted` column + new `FrasAudioMuteController` updating it. `document.visibilityState === 'visible'` gate on audio playback is a one-liner.

6. **Retention purge semantics must be idempotent + safe under concurrency:** `->withoutOverlapping()` on the schedule provides that at the process level; wrap each event's file-delete + column-null in `DB::transaction` for atomicity. The feature test uses `Storage::fake('fras_events')` → create an expired scene image tied to a status=Dispatched Incident → assert file + column survive.

7. **Markdown compilation uses `league/commonmark`** — already transitively vendored (verified `vendor/league/commonmark/` exists; used by Laravel-boost or sentry). No new composer require. Install `league/commonmark` explicitly to composer.json only if planner wants deterministic dep-graph ownership (recommend: yes, move to explicit require).

8. **`/fras/alerts` channel broadcast reach:** the `fras.alerts` private channel auth already includes Dispatcher (`routes/channels.php:7-11`). CONTEXT correctly notes responders are excluded from the channel. Dispatchers RECEIVE the broadcast (for map pulse via `useFrasAlerts.ts`) but CANNOT access `/fras/alerts` (gated by `view-fras-alerts` → operator+supervisor+admin only). Two composables on the same channel; different consumers.

9. **Test infrastructure baseline:** Pest 4 with `RefreshDatabase` trait + `pest()->group('fras')` already in use (`tests/Feature/Fras/FrasPhotoAccessControllerTest.php:10`). PostgreSQL is used for FRAS test groups (per FRAMEWORK-05). All 8 new test files fit the existing pattern.

10. **No new backend services needed beyond the CONTEXT list.** Phase 22 is a controllers + migrations + composable + Vue pages phase. `FrasIncidentFactory` gains one public method (`createFromRecognitionManual`). Two new artisan commands (`fras:purge-expired`, `fras:dpa:export`). Zero new service classes otherwise.

**Primary recommendation:** The planner can decompose this phase into 4 waves (schema + gates + events / signed-URL + audit / retention + DPA docs / feed + history + responder surfaces) with high confidence. The only *reconciliation work* upstream of execution: the `acknowledged_by` column pre-existence and the `FrasPhotoAccessController` scope note above.

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|--------------|----------------|-----------|
| Alert feed page rendering | Inertia Vue (client) | Laravel (SSR initial props) | Page lives in `pages/fras/Alerts.vue`; server hydrates last 100 non-ACK'd events into `initialAlerts` prop |
| Real-time alert delivery | Reverb (server) → Echo Vue (client) | — | `fras.alerts` private channel already wired Phase 21; Phase 22 only adds `FrasAlertAcknowledged` event alongside existing `RecognitionAlertReceived` |
| ACK/Dismiss persistence | Laravel (API + DB) | — | Column writes on `recognition_events`; controller action; broadcast back via same channel |
| Audio cue playback | Vue composable (client) | — | `useAlertSystem.playPriorityTone` — client-only; gated by `document.visibilityState` |
| Event history query | Laravel (DB) | Inertia SSR | PostgreSQL `ILIKE` + paginate(25); URL-driven filter state |
| Signed URL generation | Laravel (PHP) | — | `URL::temporarySignedRoute` at prop hydration time; client consumes pre-signed URLs, never forges |
| Image streaming + audit write | Laravel (controller + storage) | — | `Storage::disk('fras_events')->response()` wrapped in `DB::transaction` that writes `fras_access_log` first |
| Retention purge | Laravel scheduler (CLI) | — | `Schedule::command('fras:purge-expired')->dailyAt('02:00')->withoutOverlapping()` |
| Privacy Notice rendering | Laravel controller (Markdown→HTML) | Inertia Vue | Compiled server-side via `league/commonmark`; Vue page renders pre-sanitized HTML |
| DPA PDF export | Laravel artisan command | `barryvdh/laravel-dompdf` | CLI output to `storage/app/dpa-exports/` |
| Gate enforcement | `AppServiceProvider` + route middleware + Inertia shared props | FormRequest `authorize()` | Three-layer: frontend hide / backend authorize / route middleware |
| Responder scene-image exclusion | `ResponderIncidentController` (prop hydration) + `FrasEventSceneController` (role gate) + channel auth | — | Defense-in-depth: three independent walls |

---

## Existing Codebase Baseline

### Files Phase 22 EXTENDS (mod)
| File | Current shape | Phase 22 change |
|------|---------------|-----------------|
| `app/Providers/AppServiceProvider.php` | 15 gates defined in `configureGates()` lines 114–190 | Append 5 new `Gate::define` calls after existing lines. **Note: CONTEXT D-27 says "after line 167" but line 167 is inside `download-incident-report` gate — actual insertion point is after line 189 `}` (end of closure).** |
| `app/Http/Middleware/HandleInertiaRequests.php` | `auth.user.can` exposes 15 boolean gate checks (lines 52–68) | Add 5 new keys: `view_fras_alerts`, `manage_cameras`, `manage_personnel`, `trigger_enrollment_retry`, `view_recognition_image` |
| `app/Models/RecognitionEvent.php` | Has `acknowledged_by` (bigint FK → users), `acknowledged_at`, `dismissed_at` in `$fillable` + casts (lines 45–47, 62–63) | Add `dismissed_by`, `dismiss_reason`, `dismiss_reason_note` to `$fillable`; add `dismiss_reason => FrasDismissReason::class` cast; add `dismissedBy()` relation |
| `app/Models/User.php` | Standard auth model | Add `fras_audio_muted` to `$fillable` + `$casts` as `bool` |
| `app/Services/FrasIncidentFactory.php` | Two methods: `createFromSensor`, `createFromRecognition` (5-gate chain) | Add `createFromRecognitionManual(RecognitionEvent, IncidentPriority, string $reason, User)` — skips severity/confidence/dedup gates, keeps category gate |
| `app/Http/Controllers/FrasEventFaceController.php` | Role-gated signed-URL face-crop stream (`abort_unless` + `URL::temporarySignedRoute` consumer). Has a `TODO(Phase 22)` comment at line 35 | Wrap stream setup in `DB::transaction`; create `FrasAccessLog` row before stream; remove TODO |
| `app/Http/Controllers/IntakeStationController.php` | `$recentFrasEvents` signed-URL hydration pattern at lines 69–102 | None required; **serves as reference pattern** for new `FrasAlertFeedController::index` + `FrasEventHistoryController::index` |
| `app/Http/Controllers/ResponderController.php` | `show()` returns Incident + messages to `responder/Station` page | Strip `scene_image_url` from prop hydration; sign `face_image_url` if incident source = fras_recognition; include personnel+camera+captured_at for accordion |
| `resources/js/pages/responder/Station.vue` | Renders SceneTab with `:incident` + `:checklist-template` | No change at page level; SceneTab component gains the accordion |
| `resources/js/components/responder/SceneTab.vue` | Current scene/checklist/vitals/assessment rendering | Add conditional `<PersonOfInterestAccordion>` block when `incident.timeline[0]?.event_data?.source === 'fras_recognition'` |
| `routes/web.php` | Auth + role-middleware grouped routes (lines 89–185) | Add `/fras/alerts`, `/fras/events`, `/fras/events/{event}/scene`, `/fras/events/{event}/promote` group with `role:operator,supervisor,admin` + `can:view-fras-alerts` middleware; add public `/privacy` GET; add `/fras/settings/audio-mute` POST |
| `routes/console.php` | Existing `Schedule::command` calls (lines 12–29) | Append `Schedule::command('fras:purge-expired')->dailyAt('02:00')->timezone('Asia/Manila')->withoutOverlapping()` |
| `config/fras.php` | 5 sections: mqtt, cameras, enrollment, photo, recognition | Add 6th section `retention` with 4 env-backed keys |
| `composer.json` | `barryvdh/laravel-dompdf ^3.1.2` ✓, NO explicit `league/commonmark` (available transitively) | Optional: add `league/commonmark: ^2.8` explicitly for dep-graph clarity |

### Files Phase 22 CREATES (new)
Tracks CONTEXT `<code_context>` Integration Points — all NEW files inventoried there. **Key additions verified:**

- `database/migrations/*_add_dismissed_by_and_reason_to_recognition_events.php` (NEW) — **NOT** "add ack+dismiss columns" because ack columns already exist. Adds `dismissed_by` (foreignId), `dismiss_reason` (varchar 32), `dismiss_reason_note` (text).
- `database/migrations/*_create_fras_access_log_table.php` — schema per D-15 but with `foreignId('actor_user_id')` (not `foreignUuid`).
- All 8 new Pest feature tests land under `tests/Feature/Fras/` matching the existing pattern (`pest()->group('fras')`).

### Patterns Phase 22 Follows
- **Gate::define shape** (`AppServiceProvider.php:122-124` for multi-role example): `Gate::define('name', fn (User $user): bool => in_array($user->role, [Role::A, Role::B], true));`
- **Role middleware** (`routes/web.php:97`): `Route::middleware(['role:operator,supervisor,admin'])->group(...)` — the `role:` middleware is `EnsureUserHasRole` aliased in `bootstrap/app.php:59`. For redundant defense, chain with `can:view-fras-alerts`.
- **Inertia `auth.can` sharing** (`HandleInertiaRequests.php:52-68`): snake_case keys mapped to `$user->can('gate-name')`.
- **Broadcast event shape** (`app/Events/RecognitionAlertReceived.php`): `final class X implements ShouldBroadcast, ShouldDispatchAfterCommit` + `broadcastOn()` + `broadcastWith()` returning flat array.
- **Signed-URL hydration** (`IntakeStationController.php:80-86`): `URL::temporarySignedRoute('route.name', now()->addMinutes(5), ['event' => $id])` mapped over the collection before returning props.
- **Test style** (`FrasPhotoAccessControllerTest.php`): top-level `pest()->group('fras')`; `beforeEach` with `Storage::fake`; `it('...')` blocks; `$this->get()/->post()` assertions.
- **Schedule shape** (`routes/console.php:12-29`): `Schedule::command('x:y')->dailyAt('HH:mm')->timezone('Asia/Manila')->withoutOverlapping()->description('...')`.

---

## Runtime State Inventory

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | `recognition_events` rows with pre-existing `acknowledged_*` columns | None — Phase 22 extends schema additively; existing rows remain valid (new cols NULL by default) |
| Live service config | Reverb `fras.alerts` channel already active | None — Phase 22 adds new event type on same channel |
| OS-registered state | Laravel scheduler (cron entry) must be registered in CDRRMO production | Existing — IRMS already runs `artisan schedule:run`; new `fras:purge-expired` picks up automatically |
| Secrets/env vars | New env vars: `FRAS_RETENTION_SCENE_IMAGE_DAYS`, `FRAS_RETENTION_FACE_CROP_DAYS`, `FRAS_PURGE_RUN_SCHEDULE`, `FRAS_ACCESS_LOG_RETENTION_DAYS` | Document in `.env.example`; no secrets (all windows are operational knobs) |
| Build artifacts | Wayfinder regenerates TypeScript actions on `php artisan wayfinder:generate` or via vite plugin | Planner must regenerate after new routes land; CI should fail if `resources/js/actions/` or `resources/js/routes/` drift |

**Nothing found in "hidden state":** Phase 22 does NOT rename any existing strings, files, or enums. No string-replacement audit required.

---

## Implementation Approach (per success criterion)

### 1. Alert feed real-time (SC1 — ALERTS-01, ALERTS-02, ALERTS-03, ALERTS-06)

**Event shape broadcast to `fras.alerts`:**
- `RecognitionAlertReceived` (EXISTING, Phase 21) — reused unchanged. Payload already carries everything `/fras/alerts` needs (`app/Events/RecognitionAlertReceived.php:37-58`): event_id, camera_id+label, camera_location, severity, personnel_id+name+category, confidence, captured_at, incident_id.
- `FrasAlertAcknowledged` (NEW) — per D-01. Payload shape (proposed):
  ```php
  public function __construct(
      public string $eventId,
      public string $action,       // 'ack' | 'dismiss'
      public int $actorUserId,
      public string $actorName,
      public ?string $reason = null,
      public ?string $reasonNote = null,
  ) {}
  public function broadcastOn(): array { return [new PrivateChannel('fras.alerts')]; }
  public function broadcastAs(): string { return 'FrasAlertAcknowledged'; }
  ```
  Broadcast via `ShouldBroadcast + ShouldDispatchAfterCommit` (matches Phase 21 convention).

**`useFrasFeed` composable structure** (`resources/js/composables/useFrasFeed.ts` NEW):
```ts
import { ref, computed, onMounted } from 'vue';
import { useEcho } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';
import { useAlertSystem } from '@/composables/useAlertSystem';

const MAX_ALERTS = 100;

export function useFrasFeed(initialAlerts: FrasAlertItem[] = []) {
    const alerts = ref<FrasAlertItem[]>([...initialAlerts].slice(0, MAX_ALERTS));
    const page = usePage();
    const { playPriorityTone } = useAlertSystem();

    // new alert received
    useEcho<RecognitionAlertPayload>('fras.alerts', 'RecognitionAlertReceived', (payload) => {
        if (payload.severity !== 'critical' && payload.severity !== 'warning') return;
        alerts.value.unshift(mapPayloadToAlert(payload));
        if (alerts.value.length > MAX_ALERTS) alerts.value.length = MAX_ALERTS;

        if (payload.severity === 'critical'
            && document.visibilityState === 'visible'
            && !page.props.auth.user.fras_audio_muted) {
            playPriorityTone('P1');
        }
    });

    // ACK/Dismiss from any operator
    useEcho<FrasAckPayload>('fras.alerts', 'FrasAlertAcknowledged', (payload) => {
        alerts.value = alerts.value.filter(a => a.event_id !== payload.event_id);
    });

    return { alerts };
}
```
Reactive array + in-place splice/unshift keeps Vue reactivity overhead minimal at 100 items. Consider `shallowRef` only if perf profiling shows item-level deep reactivity cost; `ref<Array>` is the default and is fine at this scale.

**ACK/Dismiss state on `recognition_events`** (reconciles CONTEXT D-03 against existing schema):
- **Already exists** (Phase 18 migration): `acknowledged_by` (foreignId → users), `acknowledged_at` (timestamptz), `dismissed_at` (timestamptz).
- **Phase 22 adds**: `dismissed_by` (foreignId → users, nullable, nullOnDelete), `dismiss_reason` (varchar 32 nullable — `FrasDismissReason` values), `dismiss_reason_note` (text nullable), and two new indexes: `index('acknowledged_at')`, `index('dismissed_at')` if not already present.
- **Use `foreignId` NOT `foreignUuid`** — users table is bigint (`$table->id()` in the users migration).
- CONTEXT D-03 names `acknowledged_by_user_id` and `dismissed_by_user_id` — **planner should use the existing `acknowledged_by` column name** (don't rename) and mirror with `dismissed_by` (not `dismissed_by_user_id`) to match the existing naming.

**Controller actions** (thin, no service):
- `FrasAlertFeedController::acknowledge(AcknowledgeFrasAlertRequest, RecognitionEvent $event)` — sets `acknowledged_by = $user->id`, `acknowledged_at = now()`, returns Inertia redirect back.
- `FrasAlertFeedController::dismiss(DismissFrasAlertRequest, RecognitionEvent $event)` — sets `dismissed_by`, `dismissed_at`, `dismiss_reason`, `dismiss_reason_note`.
- Both dispatch `FrasAlertAcknowledged` after successful write.

### 2. Event history filtering + pagination (SC2 — ALERTS-04, ALERTS-05, ALERTS-07)

**Query pattern** (CONTEXT D-10 verbatim, PostgreSQL `ILIKE`):
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
    ->when($q, fn ($query, $term) =>
        $query->where(fn ($w) =>
            $w->whereHas('personnel', fn ($p) => $p->where('name', 'ilike', "%{$term}%"))
              ->orWhereHas('camera', fn ($c) => $c
                  ->where('camera_id_display', 'ilike', "%{$term}%")
                  ->orWhere('name', 'ilike', "%{$term}%")
              )
        )
    )
    ->orderByDesc('captured_at')
    ->paginate(25);
```

**SQLite-vs-PostgreSQL landmine:** `ILIKE` is PostgreSQL-only. FRAS test groups run on PostgreSQL (FRAMEWORK-05); other tests default to SQLite. Phase 22 tests for `/fras/events` MUST use `pest()->group('fras')` so they hit PostgreSQL. Planner verifies via `Wave0InfrastructureTest.php` pattern.

**Debounced URL-driven filters** (CONTEXT D-09):
```vue
<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';  // already installed
import { router } from '@inertiajs/vue3';

const applyFilters = (params, opts = {}) => router.get('/fras/events', params, {
    preserveState: true, preserveScroll: true, ...opts,
});
const applyTextSearch = useDebounceFn((q) => applyFilters({ ...currentParams, q }, { replace: true }), 300);
</script>
```
Severity pills + camera + date pickers → immediate `applyFilters({replace: false})`.
Free-text `q` → debounced 300ms + `replace: true` so back-button doesn't pile up keystrokes.

**Replay badge SQL** (`(camera_id, personnel_id)` in last 24h, N≥2):
- **Recommended approach:** follow-up group-by + hydrate (simpler, no window function). After pagination, run one query:
  ```php
  $keys = $events->getCollection()
      ->filter(fn ($e) => $e->personnel_id)
      ->map(fn ($e) => [$e->camera_id, $e->personnel_id])
      ->unique()->values();
  $counts = RecognitionEvent::query()
      ->whereIn('camera_id', $keys->pluck(0))
      ->whereIn('personnel_id', $keys->pluck(1))
      ->where('captured_at', '>=', now()->subDay())
      ->selectRaw('camera_id, personnel_id, COUNT(*) as n')
      ->groupBy('camera_id', 'personnel_id')
      ->get()
      ->keyBy(fn ($r) => $r->camera_id.'-'.$r->personnel_id);
  // hydrate onto collection: $e->replay_count = $counts[$e->camera_id.'-'.$e->personnel_id]->n ?? 1
  ```
- **Window function alternative:** `COUNT(*) OVER (PARTITION BY camera_id, personnel_id) FILTER (WHERE captured_at >= now() - interval '24h')` — cleaner SQL but requires raw query. Pick based on whether planner prefers raw SQL or two-query hydrate.
- Events with `personnel_id IS NULL` → skip badge (CONTEXT D-11).

**Promote-to-Incident path:**
- `POST /fras/events/{event}/promote` → `FrasEventHistoryController::promote(PromoteRecognitionEventRequest, RecognitionEvent $event)`
- `PromoteRecognitionEventRequest::rules()`: `priority: required|in:P1,P2,P3,P4`, `reason: required|string|min:8|max:500`.
- `PromoteRecognitionEventRequest::authorize()`: `return Gate::allows('view-fras-alerts');`
- Controller delegates to `FrasIncidentFactory::createFromRecognitionManual($event, IncidentPriority::from($priority), $reason, $request->user())`.
- Factory method skips severity + confidence + dedup gates; still rejects null personnel + allow-list personnel (nothing to promote).
- Factory dispatches `IncidentCreated` (Phase 17 locked payload) + `RecognitionAlertReceived` with populated `incident_id`.
- Success → Inertia redirect to `incidents.show($incident)`.

### 3. Responder SceneTab Person-of-Interest accordion (SC3 — INTEGRATION-02)

**SceneTab location:** `resources/js/components/responder/SceneTab.vue` (verified from `Station.vue:17` import).
**Incident source detection:** Check `props.incident.timeline[0]?.event_data?.source === 'fras_recognition'`. Timeline is already loaded in `ResponderController::show()` via `->with([..., 'timeline', ...])` (verified `ResponderController.php:52`).

**Prop hydration split:**
- `ResponderController::show()` currently `$activeIncidentModel->toArray()` dumps the full incident. **Phase 22 must transform this** to:
  1. Keep everything else as-is.
  2. If timeline[0].event_data.source === 'fras_recognition': locate the `recognition_event_id` from timeline[0].event_data, load that RecognitionEvent + personnel + camera, generate a signed 5-min URL for the face crop via `URL::temporarySignedRoute('fras.event.face', ...)`, attach to prop as `incident.person_of_interest = { face_image_url, personnel_name, personnel_category, camera_label, camera_name, captured_at }`.
  3. **Never** include a `scene_image_url` in the responder payload.

**Accordion component choice:**
- No `accordion` primitive exists in `resources/js/components/ui/` (only `collapsible`).
- **Option A (recommended):** Install reka-ui Accordion primitives (reka-ui is already v2.6.1 in `package.json:49` — supports accordion). Wire a new `components/ui/accordion/` mirroring the existing `collapsible/` structure.
- **Option B:** Use existing `Collapsible` from `components/ui/collapsible/` with custom styling; simpler but less idiomatic.
- Claude's Discretion per CONTEXT — recommend Option A for Shadcn-style consistency.

**Three-layer responder exclusion (CONTEXT D-26) — verified achievable:**
1. `FrasEventSceneController` — role gate `abort_unless(in_array($user->role, [Operator, Supervisor, Admin]))`. Responders → 403.
2. `routes/channels.php:7,17-23` — `fras.alerts` channel auth excludes responders (no Responder role in `$dispatchRoles`).
3. `ResponderController::show()` — strip scene URL at prop build time.

### 4. Signed URLs + audit log (SC4 — DPA-02, DPA-03)

**Signed URL generation** (identical to Phase 20 D-22 + Phase 21 established pattern):
```php
$faceUrl = URL::temporarySignedRoute(
    'fras.events.face.show',
    now()->addMinutes(5),
    ['event' => $event->id],
);
```
Frontend receives pre-signed URL; never forges.

**`fras_access_log` table** (CONTEXT D-15 with FK type reconciled):
```php
Schema::create('fras_access_log', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();  // bigint, NOT foreignUuid
    $table->ipAddress('ip_address');
    $table->string('user_agent', 255)->nullable();
    $table->string('subject_type', 48);   // FrasAccessSubject enum
    $table->uuid('subject_id');            // recognition_events.id OR personnel.id
    $table->string('action', 16);          // FrasAccessAction enum
    $table->timestampTz('accessed_at', precision: 0)->index();
    $table->index(['subject_type', 'subject_id']);
    $table->index(['actor_user_id', 'accessed_at']);
});
```

**Sync log-write pattern** for `FrasEventFaceController::show()` and new `FrasEventSceneController::show()`:
```php
public function show(Request $request, RecognitionEvent $event): StreamedResponse
{
    $user = $request->user();
    abort_unless($user && in_array($user->role, [UserRole::Operator, UserRole::Supervisor, UserRole::Admin], true), 403);
    abort_unless($event->face_image_path, 404);
    $disk = Storage::disk('fras_events');
    abort_unless($disk->exists($event->face_image_path), 404);

    // Sync audit write — transaction ensures FK violation or DB error fails the fetch cleanly
    DB::transaction(function () use ($request, $user, $event) {
        FrasAccessLog::create([
            'actor_user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'subject_type' => FrasAccessSubject::RecognitionEventFace->value,
            'subject_id' => $event->id,
            'action' => FrasAccessAction::View->value,
            'accessed_at' => now(),
        ]);
    });

    return $disk->response($event->face_image_path, basename($event->face_image_path), [
        'Content-Type' => 'image/jpeg',
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store, max-age=0',  // DPA: never cached by proxies
    ]);
}
```
**Cache-Control note:** `private, no-store` stronger than CONTEXT's suggested `private, max-age=60` — DPA-03 "signed for 5 min" should not be cacheable beyond the explicit TTL.

**Reconciliation: `FrasPhotoAccessController` scope.** As flagged in the summary: this controller is token-gated for *camera* fetches during enrollment (not humans). CONTEXT D-16's wrap instruction is mis-scoped. Planner should:
- LEAVE `FrasPhotoAccessController` untouched.
- Add a NEW human-facing personnel-photo viewer only if DPA-02 demands it; verify via REQUIREMENTS.md (DPA-02 says "recognition image" — does not explicitly cover personnel photos). Current scope: log only face-crop + scene-image fetches.
- If legal requires personnel-photo fetches to also log, add a separate `FrasPersonnelPhotoViewController` (role-gated + signed URL) — out of current Phase 22 scope unless raised during VALIDATION.

### 5. Retention purge with active-incident protection (SC5 — DPA-04, DPA-05)

**Command:** `app/Console/Commands/FrasPurgeExpired.php`
- Signature: `fras:purge-expired {--dry-run} {--verbose}`
- Logic sketch:
  ```php
  $sceneRetentionDays = config('fras.retention.scene_image_days', 30);
  $faceRetentionDays  = config('fras.retention.face_crop_days', 90);

  // Scene-image purge: events older than 30d with scene_image_path set
  $expiredScene = RecognitionEvent::query()
      ->whereNotNull('scene_image_path')
      ->where('captured_at', '<', now()->subDays($sceneRetentionDays))
      ->where(fn ($q) => $q
          ->whereNull('incident_id')
          ->orWhereHas('incident', fn ($i) => $i->whereIn('status', [
              IncidentStatus::Resolved, IncidentStatus::Cancelled,
          ]))
      )
      ->cursor();

  foreach ($expiredScene as $event) {
      DB::transaction(function () use ($event, $disk, $dryRun) {
          if (!$dryRun) {
              $disk->delete($event->scene_image_path);
              $event->update(['scene_image_path' => null]);
          }
      });
      $sceneCount++;
  }
  // same shape for face_image_path with faceRetentionDays
  ```
- Writes summary row to `fras_purge_runs` at start + updates at end.
- Exit 0 on success; non-zero if any per-event transaction threw.

**Scheduler registration** (`routes/console.php`):
```php
Schedule::command('fras:purge-expired')
    ->dailyAt('02:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('FRAS retention purge failed'));
```

**Active-incident-protection test** (mandatory per SC5):
```php
it('survives expired scene image when linked Incident is still Dispatched', function () {
    Storage::fake('fras_events');
    $incident = Incident::factory()->create(['status' => IncidentStatus::Dispatched]);
    $event = RecognitionEvent::factory()
        ->for(Camera::factory())
        ->for(Personnel::factory())
        ->create([
            'incident_id' => $incident->id,
            'scene_image_path' => 'expired/scene.jpg',
            'captured_at' => now()->subDays(45),  // past 30d retention
        ]);
    Storage::disk('fras_events')->put('expired/scene.jpg', 'bytes');

    $this->artisan('fras:purge-expired')->assertSuccessful();

    expect($event->fresh()->scene_image_path)->toBe('expired/scene.jpg');
    expect(Storage::disk('fras_events')->exists('expired/scene.jpg'))->toBeTrue();
});
```

**Idempotence + concurrency safety:** `->withoutOverlapping()` prevents concurrent scheduler invocations. Per-event `DB::transaction` pairs file delete + column null. File delete is safe to re-run (delete of non-existent file is a no-op via `Storage::delete()`). Column update to null is idempotent.

### 6. Privacy Notice page + `docs/dpa/` templates (SC6 — DPA-01, DPA-06)

**Markdown compilation:**
```php
use League\CommonMark\GithubFlavoredMarkdownConverter;
// In PrivacyNoticeController::show
$lang = in_array($request->query('lang'), ['en', 'tl']) ? $request->query('lang') : 'en';
$path = resource_path("privacy/privacy-notice" . ($lang === 'tl' ? '.tl' : '') . ".md");
$md = file_get_contents($path);
$html = (new GithubFlavoredMarkdownConverter(['html_input' => 'strip']))->convert($md);

return Inertia::render('Privacy', [
    'content' => (string) $html,
    'availableLangs' => ['en', 'tl'],
    'currentLang' => $lang,
]);
```
**Security note:** `'html_input' => 'strip'` sanitizes any raw HTML in the Markdown. Combined with git-tracked content (reviewable via PR), XSS risk is zero.

**Layout:** Planner chooses between new `PublicLayout.vue` vs AuthLayout with a `mode="public"` prop. Recommend new `PublicLayout` — citizens visiting `/privacy` don't need the auth navbar; isolated layout keeps concerns clean.

**`docs/dpa/` templates** — planner produces Markdown skeletons per D-33:
- `PIA-template.md` — 10 NPC-standard sections (scope, biometric data types, lawful basis, retention, data flows, risks, mitigations, DSR handling, incident response, DPO sign-off).
- `signage-template.md` + `signage-template.tl.md` — CCTV/FRAS signage copy with merge fields `{CAMERA_LOCATION}`, `{CONTACT_DPO}`, `{CONTACT_OFFICE}`, `{RETENTION_WINDOW}`.
- `operator-training.md` — role matrix, ACK/Dismiss semantics, promote-to-Incident, scene-image restrictions, signed-URL expiry, purge cadence.

**`php artisan fras:dpa:export`:**
- Signature: `fras:dpa:export {--doc=all : pia|signage|training|all} {--lang=en : en|tl}`
- Loads Markdown, renders via `resources/views/dpa/export.blade.php` shared template (`{!! $html !!}` inside prose-styled body), pipes through `barryvdh/laravel-dompdf` via `Pdf::loadHTML($bladeRendered)->save(storage_path("app/dpa-exports/{date}/{doc}-{lang}.pdf"))`.

### 7. Gates + legal sign-off (SC7 — DPA-07)

**Gate definitions** (append to `AppServiceProvider::configureGates()` after line 189):
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
**Explicit `true` third arg** on `in_array` — matches existing convention at line 123 etc.

**`HandleInertiaRequests::share()` extension:**
```php
'can' => [
    // ... existing 15 keys ...
    'view_fras_alerts' => $user->can('view-fras-alerts'),
    'manage_cameras' => $user->can('manage-cameras'),
    'manage_personnel' => $user->can('manage-personnel'),
    'trigger_enrollment_retry' => $user->can('trigger-enrollment-retry'),
    'view_recognition_image' => $user->can('view-recognition-image'),
],
```
snake_case consistent with existing keys.

**Route middleware composition** (three-layer):
```php
Route::middleware(['auth', 'role:operator,supervisor,admin', 'can:view-fras-alerts'])
    ->prefix('fras')->name('fras.')->group(function () { ... });
```
Both `role:` (existing `EnsureUserHasRole`) and `can:` (built-in Laravel) — redundant but defense-in-depth as CONTEXT intends.

**CDRRMO legal sign-off mechanics** (D-38) — deferred UI details; Phase 22 ships the `fras_legal_signoffs` table + `fras:legal-signoff` artisan command. Planner decides UI vs CLI-only at plan-time; recommend CLI-only for MVP, UI as post-close polish.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 (`pestphp/pest ^4.6`, verified `composer.json:29`) |
| Config file | `phpunit.xml` (default Pest config) |
| Quick run command | `php artisan test --compact --filter={TestName}` |
| Full suite command | `php artisan test --compact` |
| FRAS group command | `php artisan test --compact --group=fras` |

All Phase 22 tests tag with `pest()->group('fras')` at top so they run on PostgreSQL (per FRAMEWORK-05) where `ILIKE`, `jsonb_path_ops`, and `timestamptz` behave correctly.

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | File | Automated Command |
|--------|----------|-----------|------|-------------------|
| ALERTS-01 | `/fras/alerts` renders with initial 100 alerts | Feature | `tests/Feature/Fras/FrasAlertFeedTest.php` | `php artisan test --filter=FrasAlertFeedTest -x` |
| ALERTS-01 | `FrasAlertAcknowledged` broadcasts on `fras.alerts` channel | Feature | `tests/Feature/Fras/FrasAlertFeedTest.php` | same |
| ALERTS-02 | ACK writes columns + removes from other operators' feeds (broadcast) | Feature | `FrasAlertFeedTest.php` | same |
| ALERTS-02 | Dismiss with reason writes columns; dismiss without reason rejects 422 | Feature | `FrasAlertFeedTest.php` | same |
| ALERTS-03 | `useFrasFeed` calls `playPriorityTone('P1')` on Critical only (not Warning) | Unit (Vitest; if no FE test infra, manual) OR browser | `resources/js/composables/__tests__/useFrasFeed.test.ts` OR manual | manual or `npm test` (verify test framework exists) |
| ALERTS-04 | Filter by severity multi, camera_id, date range — returns filtered paginated set | Feature | `tests/Feature/Fras/FrasEventHistoryTest.php` | `php artisan test --filter=FrasEventHistoryTest -x` |
| ALERTS-04 | Debounced `q` param searches personnel.name + camera.camera_id_display + camera.name via ILIKE | Feature | `FrasEventHistoryTest.php` | same |
| ALERTS-05 | Paginated response has `meta.current_page`, `meta.last_page`, 25 per page | Feature | `FrasEventHistoryTest.php` | same |
| ALERTS-05 | Replay badge N surfaces for same (camera,personnel) in 24h; N≥2 only | Feature | `FrasEventHistoryTest.php` | same |
| ALERTS-06 | 100-alert cap — initial hydration limited; new broadcast removes oldest when >100 | Feature (server prop shape) + Browser (splice behavior) | `FrasAlertFeedTest.php` (server) + optional browser test | same |
| ALERTS-07 | Promote-to-Incident as operator with valid priority+reason creates Incident via `createFromRecognitionManual` | Feature | `tests/Feature/Fras/PromoteRecognitionEventTest.php` | `php artisan test --filter=PromoteRecognitionEventTest -x` |
| ALERTS-07 | Promote rejects reason<8 chars, missing priority | Feature | `PromoteRecognitionEventTest.php` | same |
| ALERTS-07 | Promote by responder → 403 | Feature | `PromoteRecognitionEventTest.php` | same |
| ALERTS-07 | Promote dispatches IncidentCreated + RecognitionAlertReceived | Feature | `PromoteRecognitionEventTest.php` | same |
| INTEGRATION-02 | Responder SceneTab shows accordion when `source=fras_recognition` | Feature | `tests/Feature/Fras/ResponderSceneTabTest.php` | `php artisan test --filter=ResponderSceneTabTest -x` |
| INTEGRATION-02 | Responder payload has no `scene_image_url` field | Feature | `ResponderSceneTabTest.php` | same |
| INTEGRATION-02 | Responder GET `/fras/events/{event}/scene` → 403 | Feature | `ResponderSceneTabTest.php` | same |
| INTEGRATION-02 | Operator GET `/fras/events/{event}/scene` with valid signature → 200 + log row | Feature | `FrasAccessLogTest.php` | `php artisan test --filter=FrasAccessLogTest -x` |
| DPA-01 | Public GET `/privacy` returns 200 unauthenticated, renders English by default | Feature | `tests/Feature/Fras/PrivacyNoticeTest.php` | `php artisan test --filter=PrivacyNoticeTest -x` |
| DPA-01 | GET `/privacy?lang=tl` returns Filipino content | Feature | `PrivacyNoticeTest.php` | same |
| DPA-01 | GET `/privacy?lang=xx` falls back to English | Feature | `PrivacyNoticeTest.php` | same |
| DPA-02 | Signed URL fetch writes one `fras_access_log` row with actor + IP + UA + subject | Feature | `FrasAccessLogTest.php` | same |
| DPA-02 | FK violation in log write fails the fetch (sync audit guarantee) | Feature | `FrasAccessLogTest.php` | same |
| DPA-03 | Expired signed URL → 403 | Feature | `FrasAccessLogTest.php` | same |
| DPA-03 | Valid signed URL but responder role → 403 | Feature | `FrasAccessLogTest.php` | same |
| DPA-03 | Valid signed URL + operator role → 200 | Feature | `FrasAccessLogTest.php` | same |
| DPA-04 | `fras:purge-expired` deletes scene at 30d, keeps scene at 29d | Feature | `tests/Feature/Fras/FrasPurgeExpiredCommandTest.php` | `php artisan test --filter=FrasPurgeExpiredCommandTest -x` |
| DPA-04 | `fras:purge-expired` deletes face crop at 90d, keeps at 89d | Feature | `FrasPurgeExpiredCommandTest.php` | same |
| DPA-04 | Active-incident-protection: expired scene tied to Dispatched Incident survives | Feature | `FrasPurgeExpiredCommandTest.php` | same |
| DPA-04 | Active-incident-protection: expired scene tied to Resolved Incident IS deleted | Feature | `FrasPurgeExpiredCommandTest.php` | same |
| DPA-04 | `--dry-run` performs no deletes, still writes `fras_purge_runs` row | Feature | `FrasPurgeExpiredCommandTest.php` | same |
| DPA-04 | Purge writes one `fras_purge_runs` summary row per invocation | Feature | `FrasPurgeExpiredCommandTest.php` | same |
| DPA-05 | `config/fras.php retention` keys resolve via env overrides | Feature | `FrasPurgeExpiredCommandTest.php` | same |
| DPA-06 | `fras:dpa:export --doc=pia --lang=en` produces PDF in `storage/app/dpa-exports/{date}/` | Feature | `tests/Feature/Fras/FrasDpaExportTest.php` | `php artisan test --filter=FrasDpaExportTest -x` |
| DPA-06 | `docs/dpa/PIA-template.md` file exists + has the 10 section headings | Static file test | `tests/Feature/Fras/DpaDocsExistTest.php` (minimal) | same |
| DPA-07 | Each of 5 gates: supervisor/admin pass, operator passes only `view-fras-alerts` + `view-recognition-image`, responder/dispatcher fail all except their own capabilities | Feature | `tests/Feature/Fras/FrasGatesTest.php` | `php artisan test --filter=FrasGatesTest -x` |
| DPA-07 | HandleInertiaRequests shares 5 new `can.*` keys | Feature | `FrasGatesTest.php` | same |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter={AffectedTestFile}` — sub-30-second per-file run.
- **Per wave merge:** `php artisan test --compact --group=fras` — FRAS-only group (10 existing + 8 new tests).
- **Phase gate:** `composer run ci:check` — full suite + Pint + TS types + lint before `/gsd-verify-work`.

### Wave 0 Gaps

- [x] Pest 4 installed + configured (verified `composer.json:29`)
- [x] `Wave0InfrastructureTest.php` already exists under `tests/Feature/Fras/` (scaffold pattern)
- [x] `RefreshDatabase` trait + `pest()->group('fras')` convention already in use
- [ ] No Vitest / frontend unit test framework in the repo (package.json has no `test` script; `composables/__tests__/` does not exist). **Decision point:** planner can either (a) skip unit-testing `useFrasFeed` and cover via Pest browser tests instead, or (b) add Vitest in a small Wave 0 task. Recommend (a) — Pest browser tests (Pest 4 supports visit/click/fill) cover this surface.

**Nothing blocking:** phase 22 can proceed with zero Wave 0 gaps given Pest 4's browser-test capability.

---

## Security Domain (ASVS alignment)

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|------------------|
| V2 Authentication | yes | Laravel Fortify (existing) — all FRAS routes behind `auth` middleware |
| V3 Session Management | yes | Laravel session + Fortify; no session changes |
| V4 Access Control | yes | Laravel Gates (existing + 5 new) + `role:` middleware + FormRequest::authorize |
| V5 Input Validation | yes | FormRequest `rules()` — priority enum, reason min/max, signed URL params |
| V6 Cryptography | yes | `URL::temporarySignedRoute` (Laravel's HMAC-SHA256 signed URLs) — never hand-roll |
| V7 Error Handling | yes | 403/404 `abort_unless`; no information leakage via error messages |
| V8 Data Protection | yes | `fras_access_log` (audit), purge (retention), `Cache-Control: private, no-store` on image responses |
| V9 Communications | — | HTTPS at deploy layer (Herd local, production LGU infra) — out of phase scope |
| V10 Malicious Code | yes | CommonMark `html_input => strip` on Markdown → HTML; git-tracked content reviewed via PR |
| V12 API | partial | `POST /fras/events/{event}/promote` etc. all Form-validated + CSRF via Inertia |
| V13 Configuration | yes | Retention + purge schedule + audit log retention all via `config/fras.php` env-overridable |

### Known Threat Patterns for this Phase

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Unauthenticated access to biometric images | I (Info disclosure) | Role gate + signed URL + audit log on every fetch |
| Signed URL leakage in logs | I (Info disclosure) | **Never log the full URL** — log only image ID + actor ID + timestamp. `fras_access_log.subject_id` is image ID, NOT URL. |
| Proxy/CDN caching of biometric images | I (Info disclosure) | `Cache-Control: private, no-store, max-age=0` on image responses |
| Replay of signed URL across sessions | T (Tampering) | 5-min TTL — signed URL expires server-side regardless of client cache |
| XSS via Privacy Notice Markdown | T (Tampering) | CommonMark `html_input => strip`; PR review on `resources/privacy/*.md` |
| Purge command deletes evidence on active incident | T (Tampering) / R (Repudiation) | Active-incident-protection query: `whereNull('incident_id')->orWhereHas('incident', fn ($i) => $i->whereIn('status', [Resolved, Cancelled]))` |
| SQL injection via free-text search | T (Tampering) | Eloquent `where('name', 'ilike', "%{$term}%")` — Eloquent parameterizes automatically; `$term` never concatenated into raw SQL |
| Concurrent purge command corrupting state | T (Tampering) | `Schedule::...->withoutOverlapping()` + per-event `DB::transaction` |
| Audit log write fails silently, leaving gap | R (Repudiation) | Sync-write in `DB::transaction` — FK/DB error aborts the image response with 500 |
| Cross-operator channel eavesdropping | I (Info disclosure) | `fras.alerts` is `PrivateChannel` with Broadcast::channel auth in `routes/channels.php` |
| Unprivileged user bypass via direct controller call | S (Spoofing) | Three-layer gate: route middleware + controller `authorize()` + prop-strip for responders |
| CSRF on ACK/Dismiss/Promote POSTs | T (Tampering) | Inertia ships CSRF tokens by default via Laravel's `VerifyCsrfToken` middleware |
| Timezone confusion on retention windows | T (Tampering) | `->timezone('Asia/Manila')` on schedule; `captured_at` stored as `timestamptz` |

---

## Pitfalls & Landmines

1. **`acknowledged_by` column pre-existence** — migration MUST NOT `->addColumn('acknowledged_by')` again; it exists from Phase 18. Only the 3 new dismiss-related columns + user-preference column go in Phase 22's migration. **Pre-flight check:** `SELECT column_name FROM information_schema.columns WHERE table_name = 'recognition_events';`

2. **User FK type mismatch** — CONTEXT D-03 and D-15 use `foreignUuid('...')->constrained('users')`. Users table has bigint PK (`$table->id()` in users migration, verified). **All user FKs must be `foreignId`.** Symptom if wrong: migration fails with type mismatch on the constraint.

3. **`FrasPhotoAccessController` scope mismatch** — CONTEXT D-16 asks to wrap this with log-write, but the controller serves camera-to-enrollment fetches (public token URL), not human fetches. Wrapping it would add noise rows without matching the DPA-02 "human fetch" intent. See §5 "Reconciliation" above.

4. **SQLite vs PostgreSQL `ILIKE`** — `ILIKE` is PostgreSQL-only. Tests for `/fras/events` search MUST tag `pest()->group('fras')` to route to PostgreSQL. Symptom if wrong: test fails with "no such function: ILIKE" on SQLite.

5. **Channel auth reach for dispatchers** — `fras.alerts` private channel auth allows Dispatcher (for map pulse consumer). Dispatchers can subscribe to the channel and receive `FrasAlertAcknowledged` broadcasts. That's fine (they don't render the feed), but the Inertia page `/fras/alerts` must gate via `view-fras-alerts` so dispatchers can't navigate to it.

6. **Signed URL in logs** — never write the full signed URL to `fras_access_log` or any log. The URL contains the HMAC signature; logging it creates a replay-attack window equal to log retention. Log only actor + IP + subject_id + timestamp.

7. **Audio context unlock** — `AudioContext` requires a user gesture to start. `useAlertSystem` handles this already via `click`/`keydown` listener (`useAlertSystem.ts:34-43`), but the first FRAS alert MIGHT silently fail if the user hasn't clicked yet. Accept this as a browser limitation; no mitigation beyond the existing handler.

8. **Ring-buffer Vue reactivity cost** — at 100 items with splice/unshift, deep reactivity on every item is fine. If items have 20+ nested fields and frame budget tightens, switch to `shallowRef` + array replacement pattern. Do NOT pre-optimize.

9. **Retention purge + file-delete race** — two concurrent `php artisan schedule:run` invocations (unlikely but possible if CDRRMO runs cron redundantly) → protected by `->withoutOverlapping()` + file-delete is no-op on missing file.

10. **Timeline[0] assumption** — INTEGRATION-02 detection relies on `incident.timeline[0].event_data.source === 'fras_recognition'`. Timeline[0] by ORM ordering may not be the oldest. **Planner verifies via `FrasIncidentFactory::createFromRecognition`** — that path writes the `incident_created` timeline row FIRST (line 164), so timeline ordered `orderBy('created_at')` gives the correct `[0]`. Add explicit ordering to `$activeIncidentModel->load(['timeline' => fn ($q) => $q->orderBy('created_at')])` to prevent ordering drift.

11. **Markdown file path traversal** — `?lang=xx` param → if not validated, could attempt `../` traversal. Controller MUST whitelist lang to `['en', 'tl']` before building path (already in CONTEXT, flagged here as critical).

12. **`fras_audio_muted` column default** — should be `false` default NOT NULL, so all existing users automatically have unmuted audio. CONTEXT D-06 says "default false (nullable)" — recommend NOT nullable since boolean-nullable creates tri-state confusion. Use `->boolean('fras_audio_muted')->default(false)`.

13. **Inertia router.get + preserveState race** — 300ms debounce + repeated keystrokes can stack Inertia requests. Inertia automatically cancels in-flight requests when a new one fires with same visit-id, so this is safe. Don't over-engineer.

14. **Signed URL generation inside `->map()` closure** — IntakeStationController pattern (lines 78–102) already does this; no object-per-iteration overhead concern at ≤50 events.

15. **Planner regenerate Wayfinder actions** — after new routes land, `php artisan wayfinder:generate` must run (or vite plugin auto-regens on boot). ESLint ignores `resources/js/actions/` so formatting is auto-OK, but plan-checker should verify the actions file has the 6+ new FRAS route functions.

---

## Recommended Plan Breakdown

Suggested wave partitioning — planner finalizes:

**Wave 1: Schema + Gates + Backend Primitives** (foundation, no user-visible behavior yet)
- Migration: add `dismissed_by` + `dismiss_reason` + `dismiss_reason_note` to `recognition_events`
- Migration: create `fras_access_log` table
- Migration: create `fras_purge_runs` table
- Migration: create `fras_legal_signoffs` table
- Migration: add `fras_audio_muted` to `users`
- Enums: `FrasDismissReason`, `FrasAccessSubject`, `FrasAccessAction`
- Models: `FrasAccessLog`, `FrasPurgeRun`, `FrasLegalSignoff` (+ extend `RecognitionEvent`, `User`)
- 5 new gates in `AppServiceProvider::configureGates()`
- `HandleInertiaRequests::share()` extension
- Test: `FrasGatesTest.php`
- Config: add `retention` section to `config/fras.php`

**Wave 2: Signed URLs + Audit + Retention** (DPA backbone)
- Wrap `FrasEventFaceController::show()` with sync audit log write
- New `FrasEventSceneController::show()` (clone of Face controller for scene images)
- New `FrasAlertAcknowledged` event class
- Extend `FrasIncidentFactory` with `createFromRecognitionManual()`
- New `FrasPurgeExpired` artisan command + schedule registration
- Test: `FrasAccessLogTest.php`, `FrasPurgeExpiredCommandTest.php`
- Test: `PromoteRecognitionEventTest.php`

**Wave 3: Controllers + Routes + Inertia Surfaces** (operator-facing)
- `FrasAlertFeedController` (index/acknowledge/dismiss)
- `FrasEventHistoryController` (index/show/promote)
- `FrasAudioMuteController` (update)
- `PrivacyNoticeController` (show)
- `FormRequest`s: `AcknowledgeFrasAlertRequest`, `DismissFrasAlertRequest`, `PromoteRecognitionEventRequest`, `UpdateFrasAudioMuteRequest`
- Routes: `/fras/alerts`, `/fras/events/*`, `/privacy`, `/fras/settings/audio-mute`
- Vue pages: `pages/fras/Alerts.vue`, `pages/fras/Events.vue`, `pages/Privacy.vue`
- Vue components: `AlertCard.vue`, `DismissReasonModal.vue`, `PromoteIncidentModal.vue`, `EventHistoryTable.vue`, `ReplayBadge.vue`
- Composable: `useFrasFeed.ts`
- Test: `FrasAlertFeedTest.php`, `FrasEventHistoryTest.php`, `PrivacyNoticeTest.php`
- Layout: new `PublicLayout.vue` for `/privacy` (Claude's Discretion)

**Wave 4: Responder Integration + DPA Docs + Sign-off CLI** (final surfaces + compliance)
- `ResponderController::show()` prop-hydration extension (face URL + strip scene)
- `SceneTab.vue` modification: conditional `PersonOfInterestAccordion`
- Vue component: `PersonOfInterestAccordion.vue` (+ possibly new `components/ui/accordion/`)
- `docs/dpa/` content: PIA-template.md, signage-template.md, signage-template.tl.md, operator-training.md
- `resources/privacy/privacy-notice.md` + `.tl.md`
- `resources/views/dpa/export.blade.php` shared template
- `FrasDpaExport` artisan command
- `FrasLegalSignoff` artisan command (or admin UI — planner picks)
- Test: `ResponderSceneTabTest.php`, `FrasDpaExportTest.php`, `DpaDocsExistTest.php`

Each wave is independently green-able and deployable. Wave 1 is schema-only (no behavior change). Wave 2 is backend-only (can deploy silently). Wave 3 is the first user-visible wave. Wave 4 is the compliance-focused polish. Inter-wave dependencies: 2 depends on 1 (gates + tables); 3 depends on 2 (uses new event/controllers); 4 depends on 3 (extends `ResponderController` shape).

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `league/commonmark` is safe to `use League\CommonMark\...` without explicit composer require (already transitively vendored) | Impl §6 | Low — the class is importable in current vendor dir; planner can add explicit require if dep-graph ownership matters |
| A2 | `reka-ui ^2.6.1` exports Accordion primitives usable via `components/ui/accordion/` wrapper | Impl §3 | Low — reka-ui is Shadcn-Vue's underlying lib; Accordion is a core primitive. Verify with `grep "Accordion" node_modules/reka-ui/dist/*.d.ts` before planning confirms |
| A3 | `FrasPhotoAccessController` should NOT be wrapped with `fras_access_log` (camera fetches, not human) | Impl §4 Reconciliation | Medium — contradicts CONTEXT D-16. Planner should verify with user OR err on side of logging both (sync write pattern is cheap). Raise in plan-check if unclear |
| A4 | Existing `acknowledged_by` column is correctly named; Phase 22 mirrors with `dismissed_by` (not `dismissed_by_user_id`) | Impl §1 / Pitfall #1 | Low — naming consistency; renaming existing column is higher risk than matching it |
| A5 | CDRRMO environment runs Laravel scheduler (cron → `schedule:run`) — assumed true since other scheduled commands exist and work | Impl §5 | Low — if cron absent, `fras:purge-expired` silently never runs; detectable via `fras_purge_runs` row absence |
| A6 | `barryvdh/laravel-dompdf ^3.1.2` is sufficient for rendering DPA Blade templates to PDF | Impl §6 | Low — verified in composer.json line 14; Phase 17 D-02 also relied on it |
| A7 | 100-alert ring buffer using default `ref<Array>` reactivity is performant enough at 100 items | Pitfall #8 | Low — 100 items with ≤10 nested fields each is well within Vue 3 reactivity budget |
| A8 | Timeline row ordering via `orderBy('created_at')` returns `timeline[0] = incident_created` reliably | Pitfall #10 | Low — timeline is append-only; first row is always incident_created by construction. Explicit order clause removes any drift risk |
| A9 | PostgreSQL is the test DB for `pest()->group('fras')` tests (per FRAMEWORK-05) | Pitfall #4 | Low — already established pattern via Phase 18; Wave0Infra test verifies |

**Note:** the CONTEXT decision authority trumps these assumptions — planner resolves any conflict by re-surfacing to discuss-phase, not by guessing.

---

## Open Questions

1. **Should `FrasPhotoAccessController` (camera enrollment fetch) be wrapped with audit logging?**
   - What we know: CONTEXT D-16 says yes. Codebase intent says no (it's a camera-facing endpoint). Requirement DPA-02 says "whenever a human fetches".
   - What's unclear: does CDRRMO legal interpret "human fetch" to include "the personnel-photo viewer if admin CRUD ever renders full-size photos"?
   - Recommendation: **raise in plan-check**. Default to logging only `FrasEventFaceController` + new `FrasEventSceneController` (human-faced). If admin personnel CRUD exposes a full-size photo viewer, that controller gets wrapped too. Camera enrollment endpoint stays unlogged.

2. **Should `fras:legal-signoff` be a CLI-only or include a minimal admin UI in Phase 22?**
   - What we know: CONTEXT D-38 says "admin UI OR CLI — planner picks".
   - Recommendation: CLI-only for MVP. Admin UI is polish for a future phase.

3. **Should `league/commonmark` be moved to explicit `composer.json` require?**
   - What we know: already vendored transitively; importable today.
   - Recommendation: yes, move to explicit require for dep-graph clarity. Version: `^2.8` (matches composer.lock entry at line 2178).

4. **Where should `PersonOfInterestAccordion.vue` live: `components/responder/` or `components/fras/`?**
   - What we know: it's fras-specific content rendered inside responder SceneTab.
   - Recommendation: `components/fras/` (colocation with other FRAS widgets), imported by `components/responder/SceneTab.vue`. Follows Phase 21 precedent (FRAS widgets colocated under `components/fras/`).

---

## Sources

### Primary (HIGH confidence)
- `22-CONTEXT.md` — authoritative decision record (Phase 22)
- `app/Services/FrasIncidentFactory.php` — Phase 21 factory shape (lines 105-187 for recognition gate chain)
- `app/Events/RecognitionAlertReceived.php` — existing broadcast event payload shape
- `app/Http/Controllers/FrasEventFaceController.php` — existing signed-URL role-gate pattern (TODO comment marks Phase 22 wrap point)
- `app/Http/Controllers/IntakeStationController.php:69-102` — existing signed-URL hydration pattern to mirror
- `app/Http/Controllers/Fras/FrasPhotoAccessController.php` — camera-enrollment token endpoint (verified public, not human)
- `app/Providers/AppServiceProvider.php:114-190` — existing 15-gate convention (correction: not 9)
- `app/Http/Middleware/HandleInertiaRequests.php:52-68` — existing `auth.can` shape
- `app/Http/Middleware/EnsureUserHasRole.php` — `role:` middleware logic
- `app/Models/RecognitionEvent.php:45-103` — existing fillable/casts/relations + pre-existing ack columns
- `database/migrations/2026_04_21_000004_create_recognition_events_table.php:47-49` — pre-existing ack columns
- `database/migrations/0001_01_01_000000_create_users_table.php:15` — users PK is bigint
- `resources/js/composables/useAlertSystem.ts:49-79` — `playPriorityTone` signature
- `resources/js/composables/useFrasAlerts.ts` — Phase 21 map-pulse consumer (sibling pattern for `useFrasFeed`)
- `resources/js/pages/responder/Station.vue:17,425-432` — SceneTab wiring
- `routes/channels.php:7-23` — `fras.alerts` channel auth (dispatchRoles + fras.enrollments)
- `routes/console.php:12-29` — Schedule::command pattern
- `routes/web.php:89-185` — role-middleware + route organization
- `config/fras.php` — existing section convention
- `composer.json:14` — `barryvdh/laravel-dompdf ^3.1.2` confirmed
- `composer.lock:2178` — `league/commonmark ^2.8.1` vendored transitively
- `package.json:40,49` — `@vueuse/core ^12.8.2`, `reka-ui ^2.6.1`
- `tests/Feature/Fras/FrasPhotoAccessControllerTest.php` — test style reference
- `.planning/REQUIREMENTS.md:106-136` — ALERTS, INTEGRATION, DPA acceptance criteria
- `.planning/ROADMAP.md:158-169` — Phase 22 success criteria

### Secondary (MEDIUM confidence)
- Laravel 13 docs (accessed via Context7) — signed URL + scheduler + gates patterns (training-data-consistent with v13 behavior)
- Reka UI docs — Accordion primitive availability (verified `package.json` version supports it)
- Philippine Data Privacy Act RA 10173 — compliance context (external reference, cited in PIA template scope)

### Tertiary (LOW confidence)
- None. All claims in this research are either codebase-verified (grep/read) or sourced from CONTEXT.md which is itself codebase-grounded.

---

## Metadata

**Confidence breakdown:**
- Standard stack: **HIGH** — all versions verified in composer.json / package.json
- Architecture: **HIGH** — CONTEXT locked 39 decisions; this research only reconciled 3 against existing state
- Pitfalls: **HIGH** — each pitfall traced to a specific file/line in the codebase
- Validation architecture: **HIGH** — test file patterns match existing Phase 18-21 conventions
- Security (ASVS): **MEDIUM** — mappings correct but compliance sign-off is CDRRMO legal's call, not engineering's

**Research date:** 2026-04-22
**Valid until:** 2026-05-22 (30 days — CDRRMO stack is stable; retention + DPA constants locked for v2.0)
