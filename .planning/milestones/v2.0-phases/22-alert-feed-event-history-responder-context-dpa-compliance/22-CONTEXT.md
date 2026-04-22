# Phase 22: Alert Feed + Event History + Responder Context + DPA Compliance - Context

**Gathered:** 2026-04-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 22 delivers the **final FRAS operator surface** plus the **RA 10173 DPA compliance stack** that gates v2.0 milestone close. Concretely:

- **`/fras/alerts`** live feed page: real-time `fras.alerts` subscription, one-click ACK/Dismiss (two distinct actions), severity-distinct audio cue on Critical (reusing `useAlertSystem.ts`), 100-alert ring buffer via `useFrasFeed.ts` — role-gated to operator+supervisor+admin
- **`/fras/events`** searchable event history: date-range + severity pills + camera select + debounced free-text search on personnel name + camera label, URL-driven filters (shareable), 25/page numbered pagination, `(camera, personnel)`-last-24h replay badges, event-detail modal with manual promote-to-Incident
- **Responder SceneTab Person-of-Interest accordion** on `pages/responder/Station.vue`: conditional on `incident.timeline[0].event_data.source === 'fras_recognition'`, collapsed by default, shows face crop (signed URL) + personnel name + category + camera label + captured_at — backend strips scene image from prop entirely
- **`fras_access_log`** polymorphic audit table — sync-write row on every recognition image + personnel photo fetch, before the stream starts; DPA-grade append guarantee
- **Signed 5-minute URLs** retrofitted across `FrasEventFaceController` (face crops) and `FrasPhotoAccessController` (personnel photos) — role gate + signed-URL check + audit append, all sync
- **`fras:purge-expired`** artisan command scheduled daily 02:00 with `--dry-run` flag — deletes expired files from disk, NULLs `face_image_path`/`scene_image_path` on `recognition_events`, skips any event whose linked Incident status is not in [Resolved, Cancelled], writes summary row to new `fras_purge_runs` table
- **Public `/privacy` route**: Inertia Vue page compiled from git-tracked Markdown (`resources/privacy/privacy-notice.md` + `privacy-notice.tl.md` Filipino sibling), language toggle, CDRRMO-branded
- **`docs/dpa/`** package: `PIA-template.md`, `signage-template.md` (with `{CAMERA_LOCATION}` / `{CONTACT_DPO}` merge fields), `operator-training.md`, plus `php artisan fras:dpa:export` command that generates PDFs via `dompdf`
- **Five new gates** in `AppServiceProvider::boot()`: `view-fras-alerts` (operator+supervisor+admin, ACK/Dismiss allowed), `manage-cameras` (supervisor+admin), `manage-personnel` (supervisor+admin), `trigger-enrollment-retry` (supervisor+admin), `view-recognition-image` (operator+supervisor+admin, responder explicitly excluded)
- **Global ACK broadcast**: new `FrasAlertAcknowledged` event on `fras.alerts` so a clear from one operator removes the card from every other operator's feed
- **CDRRMO legal sign-off** recorded in the Phase 22 VALIDATION before the milestone closes — mechanism is Claude's Discretion (see Deferred)

**Out of scope (guardrails):**
- Strangers / `Snap` topic handling — REQUIREMENTS.md out-of-scope
- `allow`-category recognition events creating Incidents — explicitly excluded (Phase 21 D-01)
- Changes to `useFrasAlerts.ts` (Phase 21 map pulse consumer) — Phase 22 adds sibling `useFrasFeed.ts`; map pulse wiring unchanged
- `IncidentChannel` enum new case — v2.0 locked
- New role — v2.0 locked; all gating adds to the existing 5 roles
- v1.0 broadcast payload changes — Phase 17 snapshot locked
- CMS-editable Privacy Notice — static Markdown is the scope; DB-driven content is a future phase
- "Session log" of historical ACK actions (who-ACKed-what-when list) — covered transitively by `acknowledged_by_user_id`/`dismissed_by_user_id` columns + timeline audit; no separate audit UI

The deliverable gate: an operator at `/fras/alerts` sees a live Critical alert (audio cue fires once, ACK + Dismiss both available), clicks ACK, and every other operator's feed removes that card within 1s via broadcast. At `/fras/events` the same operator filters to the last 24h on a given camera, searches "juan de la cruz", sees a 25-row paginated result with a "×3 today" replay badge on repeat sightings, opens the event-detail modal on a Warning-severity false-negative, clicks "Promote to Incident" with P2 + reason "visible weapon", and the resulting Incident appears on the dispatch map with `timeline.event_data.trigger = 'fras_operator_promote'`. A responder opening the Incident sees a "Person of Interest" accordion with the face crop (signed URL hit logs a row in `fras_access_log`) but never the scene image. A citizen visiting `/privacy` sees the CDRRMO-branded notice in English or Filipino. Overnight the `fras:purge-expired` command runs at 02:00, deleting a scene image at 30 days and a face crop at 90 days, while skipping any image whose linked Incident is still Dispatched/OnScene. CDRRMO legal signs off, and v2.0 closes.

</domain>

<decisions>
## Implementation Decisions

### Alert feed + ACK/Dismiss model

- **D-01: Global ACK scope.** ACK/Dismiss on `/fras/alerts` is a shared-operator-queue action — one operator's ACK clears the card from every operator's feed. Broadcast via new `App\Events\FrasAlertAcknowledged` event on the existing `fras.alerts` private channel (ShouldBroadcast + ShouldDispatchAfterCommit). Payload: `event_id`, `action` (`ack`|`dismiss`), `actor_user_id`, `actor_name`, `reason` (nullable, dismiss-only), `acted_at`. `useFrasFeed.ts` listens and removes matching entries from the ring buffer on receipt. Matches CDRRMO's single-shift collaborative operator model.
- **D-02: Two distinct actions (ACK vs Dismiss).** "Acknowledge" = "I saw this / I'm handling it" — one-click green action, no reason required. "Dismiss" = "not actionable / false positive" — requires a reason from an enum (`App\Enums\FrasDismissReason`: `false_match`, `test_event`, `duplicate`, `other`). UI shows Dismiss as a secondary action behind a reason-picker modal. Both actions surface on `/fras/events` as badges (green ✓ ACK / gray ✕ Dismiss + reason chip) so history investigators can distinguish. Honors ROADMAP SC1 "acknowledge/dismiss" as distinct semantics.
- **D-03: ACK/Dismiss state as columns on `recognition_events`.** New migration adds six nullable columns:
  ```php
  $table->timestampTz('acknowledged_at')->nullable()->index();
  $table->foreignUuid('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
  $table->timestampTz('dismissed_at')->nullable()->index();
  $table->foreignUuid('dismissed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
  $table->string('dismiss_reason', 32)->nullable(); // enum-backed (FrasDismissReason values)
  $table->text('dismiss_reason_note')->nullable(); // free-text addition when reason = 'other'
  ```
  Single source of truth, queryable for both /fras/alerts (WHERE acknowledged_at IS NULL AND dismissed_at IS NULL) and /fras/events (full history). Aligns with Phase 18's "columns on recognition_events" convention for bridge state (matches `incident_id` nullable FK pattern).
- **D-04: 100-alert ring buffer implemented in `useFrasFeed.ts`.** New composable `resources/js/composables/useFrasFeed.ts`, distinct from Phase 21's `useFrasAlerts.ts` (map pulse consumer — stays unchanged). Buffer behavior: Critical + Warning alerts pushed in LIFO, truncated to the newest 100, ACK'd/Dismissed entries removed on `FrasAlertAcknowledged` receipt (not just marked read — removed from buffer so long sessions don't leak). Initial hydration from Inertia prop `initialAlerts` = last 100 non-acknowledged non-dismissed recognition_events with severity in [Critical, Warning], ordered by captured_at desc.

### Audio cues (ALERTS-03)

- **D-05: Audio plays only when `/fras/alerts` is mounted.** `useFrasFeed.ts` subscribes to the Echo channel. On Critical receipt, calls `useAlertSystem().playPriorityTone('P1')` (reusing existing P1 pattern: `[880, 660, 880, 660, 880, 660]` frequencies, 0.25s durations). No app-wide injection — only when `pages/fras/Alerts.vue` is mounted. Tab-backgrounded check via `document.visibilityState === 'visible'` gate so a minimized tab stays silent. Scoped audio footprint.
- **D-06: Per-user mute preference persisted.** New column on `users`: `fras_audio_muted` boolean default false (nullable); migrated with the Phase 22 user-preference pass. Toggle button in `/fras/alerts` header (🔔/🔕 icon). Update via a new `/fras/settings/audio-mute` POST endpoint (Wayfinder-generated action, Fortify-style scoped to current user). Mute setting loaded into `usePage().props.auth.user.fras_audio_muted`; `useFrasFeed.ts` checks before playing. Rejected localStorage-only because multi-workstation operators expect mute to follow them.
- **D-07: Severity tones.** Critical reuses `useAlertSystem.playPriorityTone('P1')` (the existing 6-pulse urgent pattern). Warning severity alerts do NOT play audio (operator-awareness-only per RECOGNITION-05 intent; audio reserved for action-requiring Critical). Info never reaches the feed (Phase 21 D-16). "Severity-distinct" requirement (ALERTS-03) is honored via Critical-only audio with the priority-tone set already carrying severity semantics.

### Event history page (`/fras/events`)

- **D-08: `/fras/events` page shape.** New Inertia page `resources/js/pages/fras/Events.vue`. Server-side: new `App\Http\Controllers\FrasEventHistoryController::index(Request)`. Filters accepted as URL query params: `severity[]` (multi, `critical`|`warning`|`info`), `camera_id` (uuid), `q` (free text, max 64 chars), `from` (ISO date), `to` (ISO date), `page` (int). Wayfinder action generated for use in the Vue page.
- **D-09: URL-driven filters + debounced `q`.** Filter state syncs to URL via Inertia `router.get(url, params, { preserveState: true, preserveScroll: true, replace: true })`. Free-text input debounced 300ms client-side (`@vueuse/core` `useDebounceFn` already in project) before the `router.get` fires. Severity pills + camera select + date pickers fire immediately (no debounce). `replace: true` on the debounced path so back-button doesn't pile up every keystroke; severity/camera/date changes use `replace: false` so back-button works for deliberate filter changes.
- **D-10: Search backend = PostgreSQL `ILIKE` on `personnel.name` + `camera.camera_id_display` + `camera.name`.** Query shape:
  ```php
  RecognitionEvent::query()
      ->with(['camera:id,camera_id_display,name', 'personnel:id,name,category', 'incident:id,incident_no,priority'])
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
  25/page. No new indexes — CDRRMO expected volume (≤10k events/month) fits ILIKE comfortably. If volume grows, pg_trgm / tsvector is a clean upgrade path (deferred).
- **D-11: Replay badge = `(camera_id, personnel_id)` within last 24h.** Pre-compute in the controller query via a subquery or window function — planner picks between `WITH COUNT(*) OVER (PARTITION BY camera_id, personnel_id WHERE captured_at >= now() - interval '24h')` and a follow-up `Event::groupBy` pass hydrated onto the paginated rows. Badge label: `"×N today"` where N = total matching events in the last 24h rolling window (inclusive of the current row). Badge only renders when N >= 2. Ignores events with `personnel_id IS NULL` (stranger/unmatched). Matches operator shift-based mental model.

### Promote-to-Incident (ALERTS-07)

- **D-12: Operator+ gate; priority picker + reason required.** Event-detail modal (new `resources/js/components/fras/FrasEventDetailModal.vue`, distinct from Phase 21's `FrasEventDetailModal.vue` if one exists — planner reconciles or relocates) exposes a "Promote to Incident" button visible when: `incident_id IS NULL` AND user has `view-fras-alerts` gate AND event severity is NOT Critical (Critical already auto-created per Phase 21 D-07 or was dedup-suppressed). Button opens a priority picker (P1-P4 radio group) + required reason textarea (min 8 chars, max 500). Submit hits new `POST /fras/events/{event}/promote` route.
- **D-13: Backend path: `FrasIncidentFactory::createFromRecognitionManual()`.** Phase 21's factory gains a new public method:
  ```php
  public function createFromRecognitionManual(
      RecognitionEvent $event,
      IncidentPriority $priority,
      string $reason,
      User $actor,
  ): Incident
  ```
  Skips the severity gate and the confidence gate (manual path is an explicit override of automatic classification). Still applies personnel-category gate (rejects `allow`/null personnel — nothing to promote). Skips dedup check (operator knows what they're doing). Writes Incident with:
  - `priority` = picked value
  - `channel` = `IncidentChannel::IoT` (v2.0 lock)
  - `incidentType` = `person_of_interest` (Phase 21 D-02)
  - `notes` = Phase 21 D-03 format PLUS appended: `" — Manually promoted by {actor.name}: {reason}"`
  - `IncidentTimeline.event_data` = Phase 21 D-03 shape with `trigger = 'fras_operator_promote'`, `promoted_by_user_id = actor.id`, `promotion_reason = reason`, `promoted_priority = priority.value`
  - Dispatches `IncidentCreated` (Phase 17 locked payload)
  - Broadcasts `RecognitionAlertReceived` with now-populated `incident_id` (Phase 21 D-12 shape)
- **D-14: New route + controller method.** `POST /fras/events/{event}/promote` → `FrasEventHistoryController::promote(PromoteRecognitionEventRequest, RecognitionEvent $event)`. Form request validates: `priority in P1,P2,P3,P4`, `reason string min:8 max:500`. Authorize via `$this->authorize('view-fras-alerts')`. Returns Inertia redirect to `incidents.show($incident)` on success. Wayfinder regenerates action automatically.

### DPA audit log (`fras_access_log`)

- **D-15: Polymorphic `fras_access_log` table.** New migration:
  ```php
  Schema::create('fras_access_log', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->foreignUuid('actor_user_id')->constrained('users')->cascadeOnDelete();
      $table->ipAddress('ip_address');
      $table->string('user_agent', 255)->nullable();
      $table->string('subject_type', 48); // FrasAccessSubject enum: 'recognition_event_face', 'recognition_event_scene', 'personnel_photo'
      $table->uuid('subject_id'); // recognition_events.id OR personnel.id depending on subject_type
      $table->string('action', 16); // FrasAccessAction enum: 'view', 'download'
      $table->timestampTz('accessed_at')->index();
      $table->index(['subject_type', 'subject_id']);
      $table->index(['actor_user_id', 'accessed_at']);
  });
  ```
  Two supporting enums: `App\Enums\FrasAccessSubject`, `App\Enums\FrasAccessAction`. Polymorphic design means one log covers both recognition imagery + personnel photos; compliance export queries by `subject_type` filter.
- **D-16: Sync log write before stream.** In `FrasEventFaceController::show()` and `FrasPhotoAccessController::show()`, wrap the stream setup in `DB::transaction(function () use (...) { $logRow = FrasAccessLog::create([...]); ... return $response; })` — log row writes before the StreamedResponse is returned to the client. If the log insert fails (DB down, FK violation), the fetch fails with 500 — no silent audit gaps. Adds ~5ms per fetch; acceptable for DPA-grade "audit on every view" guarantee.
- **D-17: Log scope — face crops + scene images + personnel photos only.** Three subject types: `recognition_event_face` (FrasEventFaceController hit), `recognition_event_scene` (new FrasEventSceneController hit — see D-18), `personnel_photo` (FrasPhotoAccessController hit). Nothing else is logged — no /fras/alerts page-load rows, no /fras/events list-view rows. The audit anchor is actual biometric imagery retrieval, matching DPA-02 "whenever a human fetches a recognition image".

### Signed URLs (DPA-03)

- **D-18: Scene-image controller is NEW + operator/supervisor/admin-only.** Phase 21 only serves face crops via `FrasEventFaceController`. Phase 22 adds `FrasEventSceneController::show(RecognitionEvent $event)` for the full scene image with the same role gate (operator+supervisor+admin; responder 403). Signed-URL convention matches `FrasEventFaceController` exactly (5-min TTL via `URL::temporarySignedRoute`). Responders cannot fetch scene images at all — enforced in controller `abort_unless` on role AND in the responder SceneTab backend prop hydration (scene image URL simply not populated; D-19 below).
- **D-19: Signed URL generation at prop-hydration time.** `/fras/alerts` and `/fras/events` Inertia controllers generate 5-min signed URLs in PHP (via `URL::temporarySignedRoute('fras.events.face.show', now()->addMinutes(5), ['event' => $e->id])`) and hydrate them onto the response prop. Frontend consumes pre-signed URLs — no client-side URL forging. Pattern already established by Phase 20 D-22 (personnel photo signed-URL hydration). Responder SceneTab controller passes ONLY the face signed URL; scene URL is omitted from the prop entirely.

### Retention purge (DPA-04, DPA-05)

- **D-20: `fras:purge-expired` artisan command, scheduled daily at 02:00 Asia/Manila.** New `App\Console\Commands\FrasPurgeExpired` with:
  - `--dry-run` flag: scans + logs counts but performs no deletes (for CDRRMO legal pre-go-live verification)
  - `--verbose` flag: streams per-event decisions to stdout
  - Exit code 0 on success, non-zero on partial failure
  Scheduled in `routes/console.php`:
  ```php
  Schedule::command('fras:purge-expired')->dailyAt('02:00')->timezone('Asia/Manila')->withoutOverlapping()->onFailure(fn () => Log::error('FRAS retention purge failed'));
  ```
  Aligns with Laravel scheduler pattern used in v1.0 (check `routes/console.php` for existing scheduled commands as shape reference).
- **D-21: Purge semantics — delete files, NULL columns, keep row.** For each expired event:
  - Scene images: `face_image_path` set to NULL, file deleted from `fras_events` disk → retention `config('fras.retention.scene_image_days', 30)` days after `captured_at`
  - Face crops: same treatment → retention `config('fras.retention.face_crop_days', 90)` days after `captured_at`
  - The `recognition_events` row itself is NEVER deleted — history survives for `/fras/events` (shows "Image purged — retention policy" chip instead of the signed URL)
  - Wrapped in `DB::transaction` per event so a file-delete + column-update pair either both succeed or both fail
- **D-22: Active-incident-protection = `incident.status NOT IN (Resolved, Cancelled)`.** Purge query skips events where:
  ```php
  WHERE recognition_events.incident_id IS NOT NULL
    AND EXISTS (
      SELECT 1 FROM incidents
      WHERE incidents.id = recognition_events.incident_id
        AND incidents.status NOT IN ('resolved', 'cancelled')
    )
  ```
  Protected events survive past their retention date until the linked Incident closes; a follow-up purge run processes them. Resolution-linked events that cross the retention date AFTER the Incident resolves are handled by the next daily run. Required SC5 test: seed an expired event linked to a status=Dispatched Incident, assert command survives it on dry-run + survives it on live run.
- **D-23: `config/fras.php` gains `retention` section.** Phase 21's config layout extended:
  ```php
  'retention' => [
      'scene_image_days' => (int) env('FRAS_RETENTION_SCENE_IMAGE_DAYS', 30),
      'face_crop_days' => (int) env('FRAS_RETENTION_FACE_CROP_DAYS', 90),
      'purge_run_schedule' => env('FRAS_PURGE_RUN_SCHEDULE', '02:00'), // HH:mm local
      'access_log_retention_days' => (int) env('FRAS_ACCESS_LOG_RETENTION_DAYS', 730), // 2yr for compliance export
  ],
  ```
  Admin can tighten/loosen without deploy (DPA-05). Access log self-retention 2-year default — long enough for any CDRRMO compliance audit window; Phase 22 purge command also cleans `fras_access_log` rows older than this.
- **D-24: `fras_purge_runs` summary table.** Each command run writes one row:
  ```php
  Schema::create('fras_purge_runs', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->timestampTz('started_at');
      $table->timestampTz('finished_at')->nullable();
      $table->boolean('dry_run')->default(false);
      $table->unsignedInteger('face_crops_purged')->default(0);
      $table->unsignedInteger('scene_images_purged')->default(0);
      $table->unsignedInteger('skipped_for_active_incident')->default(0);
      $table->unsignedInteger('access_log_rows_purged')->default(0);
      $table->text('error_summary')->nullable();
  });
  ```
  Supports operational visibility + trend reporting (future admin UI can surface purge history). Compliance artifact for DPA audits.

### SceneTab Person-of-Interest accordion (INTEGRATION-02)

- **D-25: Extend `resources/js/pages/responder/Station.vue` SceneTab.** New accordion block injected when `props.incident.timeline[0]?.event_data?.source === 'fras_recognition'`. Accordion component: reuse existing accordion primitive if `resources/js/components/ui/accordion/*` exists; otherwise planner picks (check Reka UI primitives first). Header: "Person of Interest" + category-colored chip (block=red, missing=amber, lost_child=red). Collapsed by default — responder expands when ready for context. Contents: face thumbnail (signed URL), personnel name (bold), category chip, camera label + camera name, `captured_at` relative time. Zero scene image hydration — `ResponderIncidentController::show` backend strips it from the prop entirely (only face URL signed + passed).
- **D-26: Responder role exclusion from scene imagery enforced at three layers:**
  1. `FrasEventSceneController` role gate (D-18) — 403 on responder attempt
  2. `fras.alerts` channel auth already excludes responders (Phase 21 D-11) — no realtime scene pushes
  3. `ResponderIncidentController::show` backend prop hydration — `scene_image_url` never included in the Inertia payload for responder role
  Defense-in-depth. A DPA reviewer tracing any code path from responder UI back to scene bytes hits a wall at every layer.

### Five new gates (DPA-07)

- **D-27: Gate definitions in `AppServiceProvider::boot()`, after existing line 167.** Five new `Gate::define()` calls:
  ```php
  Gate::define('view-fras-alerts', fn (User $user): bool => in_array($user->role, [
      UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
  ]));

  Gate::define('manage-cameras', fn (User $user): bool => in_array($user->role, [
      UserRole::Supervisor, UserRole::Admin,
  ]));

  Gate::define('manage-personnel', fn (User $user): bool => in_array($user->role, [
      UserRole::Supervisor, UserRole::Admin,
  ]));

  Gate::define('trigger-enrollment-retry', fn (User $user): bool => in_array($user->role, [
      UserRole::Supervisor, UserRole::Admin,
  ]));

  Gate::define('view-recognition-image', fn (User $user): bool => in_array($user->role, [
      UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
  ]));
  ```
  Explicit role arrays (not inheritance chains) — matches v1.0 Gate convention. All role constants come from existing `App\Enums\UserRole`. No new role.
- **D-28: Operator "view-only on alerts" = can ACK+Dismiss but CANNOT Promote-to-Incident or manage cameras/personnel.** `view-fras-alerts` gate authorizes viewing + ACK + Dismiss (all three — because ACK/Dismiss is the operator's core feed-clearing workflow; SC1 says "one-click acknowledge/dismiss"). Promote-to-Incident is gated by `view-fras-alerts` too, BUT the frontend only renders the promote button when the feature-bearing UI chooses to — Phase 22 renders Promote for operator+supervisor+admin (aligning ALERTS-07 with operator-plus access). "View-only" in ROADMAP SC7 honored as "no camera/personnel/enrollment-retry management" — the DPA-relevant restriction.
- **D-29: Gate enforcement points.**
  - Frontend: all `resources/js/pages/fras/*.vue` + `pages/responder/Station.vue` accordion check `usePage().props.auth.can.viewFrasAlerts` etc. — `HandleInertiaRequests::share()` exposes them as `can: { viewFrasAlerts, manageCameras, managePersonnel, triggerEnrollmentRetry, viewRecognitionImage }`.
  - Backend: each controller method calls `$this->authorize('{gate-name}')` at the top; FormRequest `authorize()` method returns `Gate::allows(...)` for write endpoints.
  - Route middleware: `->middleware('can:{gate-name}')` on the route chain — redundant but defensive; three-layer check.

### Privacy Notice (`/privacy`)

- **D-30: Public Inertia Vue page backed by compiled Markdown.** Route `GET /privacy` (no auth middleware — citizen-accessible). Controller `App\Http\Controllers\PrivacyNoticeController::show(Request)` reads the appropriate `.md` file based on `?lang=en|tl` query param (default `en`), compiles to HTML via a Markdown parser (add `league/commonmark` if not already installed — planner confirms via composer.json). Inertia render: `Inertia::render('Privacy', ['content' => $html, 'availableLangs' => ['en', 'tl'], 'currentLang' => $lang])`.
- **D-31: Content location + localization.**
  - `resources/privacy/privacy-notice.md` (English — default)
  - `resources/privacy/privacy-notice.tl.md` (Filipino sibling)
  Both files git-tracked; version history = git log. Content template covers: biometric data collection scope, lawful basis (RA 10173 + CDRRMO authority), retention periods (30/90 days with active-incident exception), data subject rights (access/correction/deletion/objection), DPO contact (CDRRMO contact block — planner inserts CDRRMO-provided text or placeholder like `[CDRRMO_DPO_CONTACT_TO_BE_FILLED]` for post-deploy edit).
- **D-32: Page UX.** `resources/js/pages/Privacy.vue` renders the compiled HTML inside a prose-styled container (Tailwind `prose` plugin if available, else custom typography utilities). Language toggle in header: two buttons (English / Filipino) that `router.get('/privacy', { lang: 'tl' })` — state reflected in URL. CDRRMO branding via existing AuthLayout header components if suitable; planner confirms layout choice (likely a new minimal `PublicLayout` or reuse AuthLayout's header).

### DPA docs package (`docs/dpa/`)

- **D-33: Markdown-first, PDF-on-demand via `fras:dpa:export`.** New directory `docs/dpa/` with three Markdown files:
  - `PIA-template.md` — Privacy Impact Assessment template covering 10 NPC-standard sections (scope, biometric data types, lawful basis, retention, data flows, risks, mitigations, DSR handling, incident response, DPO sign-off)
  - `signage-template.md` — CCTV/FRAS signage copy with merge fields `{CAMERA_LOCATION}`, `{CONTACT_DPO}`, `{CONTACT_OFFICE}`, `{RETENTION_WINDOW}` for per-camera localization; planner adds multilingual sibling `signage-template.tl.md`
  - `operator-training.md` — training notes covering DPA role matrix, ACK/Dismiss semantics, when to promote-to-Incident, scene image access restrictions, signed-URL expiry behavior, purge command cadence
- **D-34: `php artisan fras:dpa:export` command.** New `App\Console\Commands\FrasDpaExport` generates PDFs via `barryvdh/laravel-dompdf` (present in composer.json per Phase 17 D-02 "dompdf upgrade" verifying availability). Renders each Markdown file through a Blade template `resources/views/dpa/export.blade.php` (single shared template, content injected), produces PDFs into `storage/app/dpa-exports/{yyyy-mm-dd}/`. CLI accepts `--doc=pia|signage|training|all` and optional `--lang=en|tl`. Output: file paths listed on stdout for the operator to hand off to CDRRMO legal.

### Controller + route topology

- **D-35: New controllers.** (planner generates via `php artisan make:controller`):
  - `App\Http\Controllers\FrasAlertFeedController` (`index`, `acknowledge`, `dismiss`)
  - `App\Http\Controllers\FrasEventHistoryController` (`index`, `show`, `promote`)
  - `App\Http\Controllers\FrasEventSceneController` (`show` — signed-URL scene image)
  - `App\Http\Controllers\PrivacyNoticeController` (`show`)
- **D-36: New routes (`routes/web.php`).**
  ```php
  // Public
  Route::get('/privacy', [PrivacyNoticeController::class, 'show'])->name('privacy');

  // Authenticated FRAS feed + history (can:view-fras-alerts)
  Route::middleware(['auth', 'can:view-fras-alerts'])->prefix('fras')->name('fras.')->group(function () {
      Route::get('/alerts', [FrasAlertFeedController::class, 'index'])->name('alerts.index');
      Route::post('/alerts/{event}/ack', [FrasAlertFeedController::class, 'acknowledge'])->name('alerts.ack');
      Route::post('/alerts/{event}/dismiss', [FrasAlertFeedController::class, 'dismiss'])->name('alerts.dismiss');
      Route::get('/events', [FrasEventHistoryController::class, 'index'])->name('events.index');
      Route::get('/events/{event}', [FrasEventHistoryController::class, 'show'])->name('events.show');
      Route::post('/events/{event}/promote', [FrasEventHistoryController::class, 'promote'])->name('events.promote');
      Route::get('/events/{event}/scene', [FrasEventSceneController::class, 'show'])
          ->middleware('signed')
          ->name('events.scene.show');
  });

  // User audio-mute preference (auth only)
  Route::middleware('auth')->post('/fras/settings/audio-mute', [FrasAudioMuteController::class, 'update'])
      ->name('fras.settings.audio-mute.update');
  ```
  Wayfinder regenerates TypeScript actions on save.
- **D-37: `FrasAudioMuteController` minimal.** Single `update(Request $request)` method: validates `muted:boolean`, updates `$request->user()->fras_audio_muted`, returns Inertia redirect back. Tiny controller; keeps the gesture out of a shared settings namespace that might conflict with Phase 10's design-system settings.

### Sign-off milestone mechanics

- **D-38: CDRRMO legal sign-off captured in `22-VALIDATION.md` + Admin config flag.** Admin sets `fras.legal_signoff_recorded_at` via a new admin UI field OR via a CLI (`php artisan fras:legal-signoff --signed-by="Atty. [Name]" --contact="[email]"`) — planner picks between UI + CLI after reviewing existing admin settings pages. Writes to a new table `fras_legal_signoffs` (id, signed_by_name, contact, signed_at, notes). Phase 22 VALIDATION step includes this as a required pre-close verification. Non-blocking for code delivery but blocking for milestone close. (See Deferred for extended discussion.)

### Planning-time amendments required

- **D-39: REQUIREMENTS.md RECOGNITION-02** already amended in Phase 21 (D-26) — no Phase 22 action. Verify the amendment landed before Phase 22 planning.

### Claude's Discretion

- Exact Vue component hierarchy for `/fras/alerts` and `/fras/events` pages (how many sub-components, naming, colocation under `pages/fras/` vs `components/fras/`) — planner picks consistent with v1.0 layout conventions.
- Precise Tailwind/Reka UI class set for the alert feed card, event-history table, and SceneTab accordion — planner picks consistent with Phase 10 (Design System) tokens.
- Whether the 100-alert ring buffer also applies to the `/fras/events` first-page fetch (probably no — events is paginated) or strictly to `useFrasFeed.ts` state.
- Whether the scene-image "Image purged" placeholder uses an icon, an image, or a text chip.
- Accordion primitive choice (Reka UI Accordion vs Shadcn-style custom) for the Person-of-Interest block on Station.vue.
- `league/commonmark` vs `erusev/parsedown` for Markdown compilation — planner picks based on security (HTML sanitization support) and existing dep graph.
- Whether `PublicLayout.vue` is a new minimal layout for `/privacy` or AuthLayout gets a `mode="public"` prop — planner picks.
- Exact PIA template section wording (10-section skeleton is locked; content polish is planner's + CDRRMO legal's joint call via PR review).
- DPO contact block content in privacy-notice.md — leave as `[CDRRMO_DPO_CONTACT]` placeholder? pre-fill with best-guess? planner picks based on whether CDRRMO has provided contact info.
- Replay-badge SQL strategy (window function vs follow-up group-by + hydrate) — planner picks based on query plan inspection with realistic volume.
- Test file naming + coverage strategy across the 4 major surfaces (alerts feed, events history, DPA audit, retention purge) — planner produces 4+ feature tests following Phase 21 convention.
- Whether `/fras/settings/audio-mute` uses Fortify-style scoped update or a bespoke endpoint — planner picks; Fortify integration is likely overkill for a single boolean.
- Whether ACK/Dismiss timestamps get exposed in any Unit/Operator analytics dashboards — out of scope for Phase 22; analytics surfaces deferred.

### Folded Todos

*None — no pending todos matched Phase 22 scope.*

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Phase 22 goal, requirements, success criteria
- `.planning/ROADMAP.md` §Phase 22 — goal, depends-on (Phase 21), 7 success criteria, requirements list (ALERTS-01..07, INTEGRATION-02, DPA-01..07)
- `.planning/REQUIREMENTS.md` §ALERTS — ALERTS-01..07 acceptance criteria (lines 106–112)
- `.planning/REQUIREMENTS.md` §INTEGRATION — INTEGRATION-02 (line 120)
- `.planning/REQUIREMENTS.md` §DPA — DPA-01..07 acceptance criteria (lines 130–136)

### Phase 18 schema (Phase 22 adds columns + new tables)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` — schema freeze; Phase 22 adds 6 columns to `recognition_events` (D-03) + 3 new tables: `fras_access_log` (D-15), `fras_purge_runs` (D-24), `fras_legal_signoffs` (D-38)
- `.planning/phases/18-fras-schema-port-to-postgresql/18-CONTEXT.md` — `recognition_events.face_image_path` + `scene_image_path` columns (NULL on purge per D-21)

### Phase 19 (RecognitionHandler + storage disk)
- `.planning/phases/19-mqtt-pipeline-listener-infrastructure/19-CONTEXT.md` — MQTT listener + RecognitionHandler shape; Phase 22 does NOT modify handler
- `app/Mqtt/Handlers/RecognitionHandler.php` — handler writes `face_image_path` + `scene_image_path` on the `fras_events` disk; Phase 22 purge deletes from same disk

### Phase 20 (cameras + personnel + signed URL pattern)
- `.planning/phases/20-camera-personnel-admin-enrollment/20-CONTEXT.md` D-22 — signed-URL photo pattern (Phase 22 `FrasEventSceneController` mirrors)
- `app/Http/Controllers/FrasPhotoAccessController.php` — personnel photo signed-URL controller; Phase 22 wraps with `fras_access_log` sync write per D-16
- `.planning/phases/20-camera-personnel-admin-enrollment/20-CONTEXT.md` D-36/D-38 — `fras.cameras` + `fras.enrollments` channel auth pattern

### Phase 21 (factory + fras.alerts + face controller + map consumers)
- `.planning/phases/21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai/21-CONTEXT.md` — full Phase 21 shape
- `.planning/phases/21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai/21-CONTEXT.md` D-06/D-07 — `FrasIncidentFactory` + 5 gates; Phase 22 adds `createFromRecognitionManual()` per D-13 here
- `.planning/phases/21-recognition-iot-intake-bridge-dispatch-map-intakestation-rai/21-CONTEXT.md` D-11/D-12 — `fras.alerts` channel + `RecognitionAlertReceived` payload; Phase 22 adds `FrasAlertAcknowledged` event on same channel per D-01 here
- `app/Events/RecognitionAlertReceived.php` — broadcast shape Phase 22 consumers read; promote-to-Incident broadcasts it with populated `incident_id` per D-13
- `app/Http/Controllers/FrasEventFaceController.php` — already role-gated + signed-URL; Phase 22 wraps with `fras_access_log` sync write per D-16 + D-17 (removes the `TODO(Phase 22)` comment)
- `resources/js/composables/useFrasAlerts.ts` — Phase 21 map-pulse consumer; Phase 22 adds SIBLING `useFrasFeed.ts` per D-04; do NOT modify Phase 21's composable
- `resources/js/pages/responder/Station.vue` — Phase 22 adds Person-of-Interest accordion per D-25

### IRMS v1.0 conventions + reference code
- `app/Providers/AppServiceProvider.php` lines 116–167 — existing 9 gates; Phase 22 adds 5 more after line 167 per D-27
- `app/Enums/UserRole.php` — role enum Phase 22 gates use (Operator, Supervisor, Admin, Dispatcher, Responder)
- `app/Enums/IncidentPriority.php` — P1-P4 enum Phase 22 promote picker validates against
- `app/Enums/IncidentStatus.php` — `Resolved` + `Cancelled` used in retention protection query per D-22
- `app/Http/Controllers/IntakeStationController.php::show()` — signed-URL hydration pattern (lines handling `recentFrasEvents` prop) — Phase 22 mirrors in FrasAlertFeedController + FrasEventHistoryController
- `app/Composables::useAlertSystem` — `playPriorityTone('P1')` already available; Phase 22 reuses without modification
- `resources/js/composables/useAlertSystem.ts` lines 1–80 — tone definitions; Phase 22 calls `.playPriorityTone('P1')` for Critical FRAS alerts per D-05/D-07
- `routes/web.php` — existing route organization pattern (auth middleware group); Phase 22 adds `/fras/*` authenticated prefix group + public `/privacy` per D-36
- `routes/channels.php` — existing private channel auth; Phase 22 does NOT add channels (reuses `fras.alerts`)
- `routes/console.php` — existing scheduled commands; Phase 22 appends `fras:purge-expired` schedule per D-20
- `config/fras.php` — Phase 22 adds `retention` section per D-23; existing sections (mqtt/cameras/enrollment/photo/recognition) unchanged
- `composer.json` — verify `league/commonmark` or `erusev/parsedown` available for Markdown; verify `barryvdh/laravel-dompdf` for PDF export
- `package.json` — `@vueuse/core` available for `useDebounceFn` (D-09)
- `app/Http/Middleware/HandleInertiaRequests.php` — `share()` method; Phase 22 extends with `can: { viewFrasAlerts, ... }` props per D-29

### Migration references (conventions)
- `database/migrations/2025_*_create_recognition_events_table.php` (Phase 18) — UUID PK + TIMESTAMPTZ + JSONB conventions; Phase 22 migrations follow same style
- `database/migrations/*create_incidents_table*.php` — status enum reference for D-22 active-incident query

### Test file references (convention)
- `tests/Feature/Fras/FrasIncidentFactoryTest.php` (Phase 21) — Pest feature test pattern for FRAS; Phase 22 adds:
  - `tests/Feature/Fras/FrasAlertFeedTest.php` — ACK/Dismiss/broadcast + ring buffer
  - `tests/Feature/Fras/FrasEventHistoryTest.php` — filter/search/pagination/replay badge
  - `tests/Feature/Fras/PromoteRecognitionEventTest.php` — promote-to-Incident + `createFromRecognitionManual`
  - `tests/Feature/Fras/FrasAccessLogTest.php` — sync write on face/scene/personnel-photo fetches
  - `tests/Feature/Fras/FrasPurgeExpiredCommandTest.php` — retention + dry-run + active-incident-protection
  - `tests/Feature/Fras/ResponderSceneTabTest.php` — accordion + scene-image absence + 403 on scene URL
  - `tests/Feature/Fras/PrivacyNoticeTest.php` — public route + both langs
  - `tests/Feature/Fras/FrasGatesTest.php` — all 5 new gates per role matrix

### v2.0 milestone locked decisions
- `.planning/REQUIREMENTS.md` §Scope Decisions — `IncidentChannel::IoT` reuse (no new channel), no new role — **all honored**
- `.planning/STATE.md §Accumulated Context` — Inertia v2, UUID PKs, Postgres+PostGIS — **all honored**

### External specs / compliance references
- Republic Act 10173 (Philippines Data Privacy Act) — lawful basis, data-subject rights, security requirements — **Privacy Notice content must align**
- NPC (National Privacy Commission) Memorandum Circular 2022-01 (Biometric Data) — biometric handling standards — **PIA template must align**
- *Note: no internal ADR files exist for DPA; Phase 22 IS the DPA decision record.*

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`useAlertSystem.ts`** (192 LOC) — `playPriorityTone(priority: IncidentPriority)` ready for reuse. Handles audio-context unlock on user gesture. Phase 22 calls `.playPriorityTone('P1')` on Critical FRAS alerts; no parallel Web Audio stack needed.
- **`FrasEventFaceController`** — role gate + signed URL + `abort_unless` pattern; Phase 22 adds one line (the `fras_access_log` sync write) + removes the `TODO(Phase 22)` comment. Clone shape for `FrasEventSceneController`.
- **`FrasPhotoAccessController`** (Phase 20 D-22) — signed-URL personnel photo controller; Phase 22 wraps with same log-write wrapper.
- **`IntakeStationController::show()`** signed-URL hydration pattern — `temporarySignedRoute(..., now()->addMinutes(5), ...)` over mapped collections. Phase 22 `FrasAlertFeedController::index` + `FrasEventHistoryController::index` mirror.
- **`useFrasAlerts.ts`** (Phase 21) — `useEcho('private:fras.alerts', 'RecognitionAlertReceived', handler)` pattern. Phase 22 `useFrasFeed.ts` clones with different handler + adds `FrasAlertAcknowledged` listener.
- **AppServiceProvider gate convention** (lines 116–167) — `Gate::define('{name}', fn (User $user): bool => in_array($user->role, [...]))`. Phase 22 appends 5 gates following identical shape per D-27.
- **`RecognitionAlertReceived`** broadcast event — Phase 21 D-12; Phase 22 dispatches it (not re-defines) from `createFromRecognitionManual()` path per D-13.
- **`FrasIncidentFactory`** — Phase 21 service class; Phase 22 adds one new public method (`createFromRecognitionManual`) without altering existing methods.
- **Inertia URL-driven filter pattern** — check existing paginated pages (likely incidents index or units index) for the `router.get(url, params, { preserveState, replace })` convention; Phase 22 `/fras/events` follows.
- **`RefreshDatabase` Pest trait + Phase 18/19/20/21 factory chain** — Phase 22 tests seed via existing `RecognitionEventFactory`, `CameraFactory`, `PersonnelFactory`, `IncidentFactory`.
- **`config/fras.php` convention** — one `'section_name' => [...]` block per concern; Phase 22 adds `retention` section per D-23.
- **Laravel `Schedule::command('...')->dailyAt('HH:mm')->timezone('Asia/Manila')`** — existing scheduled commands in `routes/console.php` as shape reference for Phase 22 `fras:purge-expired` schedule.
- **Wayfinder auto-generation** — all new routes automatically produce TypeScript actions in `resources/js/actions/`; Phase 22 touches many routes but never hand-writes TS imports.

### Established Patterns
- **Thin controller → service** — Phase 22 `FrasAlertFeedController::acknowledge` + `dismiss` are thin; column updates stay in the controller (no new service class needed since state is simple). `promote` delegates to `FrasIncidentFactory::createFromRecognitionManual`.
- **Nullable FK pattern** — `acknowledged_by_user_id` + `dismissed_by_user_id` follow `recognition_events.incident_id` + `recognition_events.personnel_id` nullable-FK convention (all set with `nullOnDelete` or `cascadeOnDelete` consistent with parent).
- **Enum-backed string column** — `dismiss_reason` stored as varchar, PHP casts to `FrasDismissReason` enum via model `$casts`.
- **Private channel auth via role gate** (`routes/channels.php`) — Phase 22 does NOT add new channels (reuses Phase 21's `fras.alerts`).
- **FormRequest + `authorize()` + `rules()` pattern** — `PromoteRecognitionEventRequest` follows v1.0 convention; validates priority + reason.
- **Inertia `auth.can.*` prop sharing** — Phase 22 extends `HandleInertiaRequests::share()` with 5 new gate checks per D-29.
- **DB::transaction for paired writes** — Phase 22 retention purge + fras_access_log sync-write wrap file I/O + column update for atomicity.
- **`php artisan make:command` + scheduled in `routes/console.php`** — v1.0 scheduled-task pattern; `FrasPurgeExpired` follows.
- **`ShouldBroadcast + ShouldDispatchAfterCommit`** — Phase 22 `FrasAlertAcknowledged` event follows IRMS convention.

### Integration Points (files that change or get created)
- `database/migrations/2026_*_add_ack_dismiss_columns_to_recognition_events.php` (NEW)
- `database/migrations/2026_*_create_fras_access_log_table.php` (NEW)
- `database/migrations/2026_*_create_fras_purge_runs_table.php` (NEW)
- `database/migrations/2026_*_create_fras_legal_signoffs_table.php` (NEW)
- `database/migrations/2026_*_add_fras_audio_muted_to_users.php` (NEW)
- `app/Enums/FrasDismissReason.php` (NEW)
- `app/Enums/FrasAccessSubject.php` (NEW)
- `app/Enums/FrasAccessAction.php` (NEW)
- `app/Models/FrasAccessLog.php` (NEW)
- `app/Models/FrasPurgeRun.php` (NEW)
- `app/Models/FrasLegalSignoff.php` (NEW)
- `app/Models/RecognitionEvent.php` (MOD) — add `acknowledged_by`/`dismissed_by` relations + casts
- `app/Models/User.php` (MOD) — add `fras_audio_muted` to `$casts` + fillable
- `app/Events/FrasAlertAcknowledged.php` (NEW) — per D-01
- `app/Services/FrasIncidentFactory.php` (MOD) — add `createFromRecognitionManual()` per D-13
- `app/Http/Controllers/FrasAlertFeedController.php` (NEW)
- `app/Http/Controllers/FrasEventHistoryController.php` (NEW)
- `app/Http/Controllers/FrasEventSceneController.php` (NEW)
- `app/Http/Controllers/PrivacyNoticeController.php` (NEW)
- `app/Http/Controllers/FrasAudioMuteController.php` (NEW)
- `app/Http/Controllers/FrasEventFaceController.php` (MOD) — wrap with log-write per D-16
- `app/Http/Controllers/FrasPhotoAccessController.php` (MOD) — wrap with log-write per D-16
- `app/Http/Controllers/Responder/IncidentController.php` (MOD, if exists; else `ResponderIncidentController`) — strip `scene_image_url` from prop, sign `face_image_url` per D-25/D-26
- `app/Http/Requests/Fras/AcknowledgeFrasAlertRequest.php` (NEW)
- `app/Http/Requests/Fras/DismissFrasAlertRequest.php` (NEW)
- `app/Http/Requests/Fras/PromoteRecognitionEventRequest.php` (NEW)
- `app/Http/Requests/Fras/UpdateFrasAudioMuteRequest.php` (NEW)
- `app/Http/Middleware/HandleInertiaRequests.php` (MOD) — extend `share.auth.can` per D-29
- `app/Providers/AppServiceProvider.php` (MOD) — add 5 `Gate::define` calls per D-27
- `app/Console/Commands/FrasPurgeExpired.php` (NEW) — per D-20/D-21/D-22
- `app/Console/Commands/FrasDpaExport.php` (NEW) — per D-34
- `routes/web.php` (MOD) — add route group per D-36
- `routes/console.php` (MOD) — schedule `fras:purge-expired` per D-20
- `config/fras.php` (MOD) — add `retention` section per D-23
- `resources/js/pages/fras/Alerts.vue` (NEW)
- `resources/js/pages/fras/Events.vue` (NEW)
- `resources/js/pages/fras/EventDetail.vue` (NEW — may be a modal under `components/fras/` instead; planner picks)
- `resources/js/pages/Privacy.vue` (NEW)
- `resources/js/pages/responder/Station.vue` (MOD) — add Person-of-Interest accordion per D-25
- `resources/js/components/fras/AlertCard.vue` (NEW)
- `resources/js/components/fras/DismissReasonModal.vue` (NEW)
- `resources/js/components/fras/PromoteIncidentModal.vue` (NEW)
- `resources/js/components/fras/EventHistoryTable.vue` (NEW)
- `resources/js/components/fras/ReplayBadge.vue` (NEW)
- `resources/js/components/fras/PersonOfInterestAccordion.vue` (NEW)
- `resources/js/composables/useFrasFeed.ts` (NEW) — per D-04
- `resources/privacy/privacy-notice.md` (NEW)
- `resources/privacy/privacy-notice.tl.md` (NEW)
- `resources/views/dpa/export.blade.php` (NEW) — shared PDF template per D-34
- `docs/dpa/PIA-template.md` (NEW)
- `docs/dpa/signage-template.md` (NEW)
- `docs/dpa/signage-template.tl.md` (NEW)
- `docs/dpa/operator-training.md` (NEW)
- `tests/Feature/Fras/FrasAlertFeedTest.php` (NEW)
- `tests/Feature/Fras/FrasEventHistoryTest.php` (NEW)
- `tests/Feature/Fras/PromoteRecognitionEventTest.php` (NEW)
- `tests/Feature/Fras/FrasAccessLogTest.php` (NEW)
- `tests/Feature/Fras/FrasPurgeExpiredCommandTest.php` (NEW)
- `tests/Feature/Fras/ResponderSceneTabTest.php` (NEW)
- `tests/Feature/Fras/PrivacyNoticeTest.php` (NEW)
- `tests/Feature/Fras/FrasGatesTest.php` (NEW)

### Known touchpoints that DO NOT change in Phase 22
- `useFrasAlerts.ts` — Phase 21 map-pulse consumer; Phase 22 adds sibling `useFrasFeed.ts`
- `FrasIncidentFactory::createFromSensor` + `createFromRecognition` — Phase 21 methods preserved
- `RecognitionAlertReceived` payload — Phase 21 D-12 shape preserved (promote path dispatches same event)
- `fras.alerts` channel auth in `routes/channels.php` — Phase 21 D-11 preserved
- `IncidentChannel` enum — no new case
- `IncidentCreated` broadcast payload — Phase 17 byte-identical snapshot preserved (promote-to-Incident dispatches same event)
- Phase 17 broadcast snapshot set (6 events) — `FrasAlertAcknowledged` is NEW but is NOT part of the snapshot set
- `RecognitionHandler` body — Phase 19/21 shape preserved
- `UserRole` enum — no new role
- `config/services.php` — unchanged

</code_context>

<specifics>
## Specific Ideas

- **"ACK is global, Dismiss is different from ACK."** The operator queue is shared — one clear for all. But "I handled it" and "false positive, ignore" are operationally distinct, so the history page can tell apart "42 legitimate alerts ACK'd today" from "8 false matches dismissed today" for CDRRMO quality reporting.
- **"Columns on recognition_events, not a new table."** Matches how Phase 21 added `incident_id` nullable FK on the same table. The history page already joins to this row; adding state columns avoids a second query.
- **"Audio scoped to /fras/alerts only."** Respects operators doing intake work elsewhere in the app. Per-user mute via a persisted column follows them across workstations — matches the mobile operator pattern where a supervisor on their phone shouldn't get a 6-pulse tone during meetings.
- **"URL-driven filters for /fras/events."** Operators pass investigations across shifts. A shareable URL like `/fras/events?from=2026-04-20&camera=CAM-03&q=juan` is the operational handoff artifact.
- **"Replay badge rolls over at 24h."** Shift-based. An operator coming in at 06:00 doesn't care that a face appeared 40 times across the last 30 days — they care about the last shift.
- **"Promote-to-Incident reuses FrasIncidentFactory."** Single write path for "recognition → Incident" keeps the audit story clean. The difference is which gates run; `createFromRecognitionManual` documents the skipped gates as explicit (severity, confidence, dedup bypassed; category still enforced).
- **"Polymorphic fras_access_log."** Covers face crops, scene images, personnel photos with one table. DPA export is `SELECT * WHERE subject_type = 'recognition_event_face' AND accessed_at BETWEEN ...`. Polymorphic was the obvious call for three image types that all need the same audit treatment.
- **"Sync log write, not queued."** DPA-02 says "records ... whenever a human fetches". Queued defers that guarantee to a queue that can fail. Sync is ~5ms and the guarantee is unconditional.
- **"Daily 02:00 retention purge with --dry-run."** 02:00 Asia/Manila is CDRRMO's lowest-activity window. Dry-run is the legal-pre-go-live artifact — "here's what would be purged if we ran this live today".
- **"Active-incident-protection = status not Resolved/Cancelled."** Investigation lifecycle aligns with incident lifecycle. A purge that kills evidence on an open case is the DPA failure-mode-zero; the status check is the natural guard.
- **"Five Gate::define calls, all in AppServiceProvider."** The v1.0 convention is 9 gates in one file; 14 is still manageable. Policy classes add abstraction when the v1.0 project doesn't use them.
- **"Three-layer gate enforcement."** Frontend hides UI, backend authorizes action, route-middleware denies at the door. A code reviewer tracing any path finds a `can` check at every boundary.
- **"Privacy notice is Markdown + public Inertia page."** Markdown is editable by non-devs via PR; Inertia keeps the page consistent with the rest of the app; public route means no auth friction for citizens reading.
- **"SceneTab accordion collapsed by default."** Responders need the context sometimes, not always. Collapsed-default + clear labeling means no surprise-context-overload; expand-on-demand.
- **"Scene image never reaches responder."** Enforced at three layers. DPA reviewer tracing the code can see the guard at every layer.
- **"dompdf for PDF export, Markdown for authoring."** Using an existing dep (confirmed in Phase 17) + git-friendly authoring format. A CDRRMO staff member can edit .md via GitHub web UI with zero dev-tools knowledge.

</specifics>

<deferred>
## Deferred Ideas

- **Sign-off mechanics (D-38) — UI + CLI + table design — open details.** Phase 22 writes the table + CLI command; the admin UI page design is Claude's Discretion. Specifically: (a) Does sign-off live under an existing admin settings section or a new `/admin/compliance` page? (b) Does sign-off produce a downloadable PDF certificate? (c) Is there a re-sign workflow when retention policy changes? — all Phase 22+ polish unless CDRRMO legal raises early.
- **Milestone-close ceremony mechanics** — after Phase 22 code + sign-off both land, does `/gsd-complete-milestone` auto-fire or does an operator run a manual review? Currently `/gsd-complete-milestone` is already available — Phase 22 doesn't reach into GSD workflow tooling.
- **Translation pipeline for DPA docs beyond Filipino** — if Butuan LGU wants Cebuano (Bisaya) + English + Filipino, the `*.{lang}.md` convention extends naturally, but content authoring is a CDRRMO linguistic call. Phase 22 ships English + Filipino only; other langs are additive later.
- **CMS-editable Privacy Notice** — if CDRRMO wants legal to edit without git access, a future phase adds a `privacy_notices` table + admin UI + versioning. Out of Phase 22 scope.
- **DPA audit UI** — a dashboard showing "who accessed what images when" — future admin tool. Phase 22 produces the log (the data contract); rendering is deferred.
- **Expand `fras_access_log` to cover non-image actions** — if DPA compliance later requires logging /fras/alerts page-view or /fras/events search events, the polymorphic schema already supports new `subject_type` values. Phase 22 keeps scope to image fetches.
- **Retention auto-adjustment based on incident outcome** — e.g., keep images for 1yr after a "criminal case opened" outcome. Future compliance feature; Phase 22 ships the 30/90 baseline.
- **Session-based ACK ownership analytics** — "operator X acknowledges 3× the average"; may surface training opportunities. Out of Phase 22 scope.
- **Replay badge extended to multi-camera triangulation UI** — map view of "this face has appeared on these N cameras in the last 24h". Useful for missing-person search; not in Phase 22.
- **Promote-to-Incident with "attach to existing Incident" option** — if the recognition event should augment an open incident (e.g., add context to a kidnapping-in-progress) rather than create a new one. Phase 22 only supports new-incident creation.
- **CCTV signage PDF with QR-code linking to /privacy** — signage generator could bake a QR code. Phase 22 ships merge-field templates; QR-code integration is a small addition later.
- **PII-aware search** — if CDRRMO legal requires that operator free-text searches over `personnel.name` produce a log entry (because they're PII queries), wire after Phase 22. Phase 22 logs image fetches only.
- **Dispatch console FRAS alert summary widget** — a small badge on the dispatch console showing "3 unACKed Critical alerts" — nice-to-have, not in Phase 22 scope (dispatchers have the map pulse; operators have the feed).
- **Retention policy per-camera override** — if some cameras are flagged "critical infrastructure" and need longer retention. Phase 22 ships the app-wide config; per-camera overrides are a future admin feature.

### Reviewed Todos (not folded)
*None reviewed — no pending todos matched Phase 22 scope.*

</deferred>

---

*Phase: 22-alert-feed-event-history-responder-context-dpa-compliance*
*Context gathered: 2026-04-22*
