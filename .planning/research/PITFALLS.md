# Pitfalls Research — v2.0 FRAS Integration

**Domain:** FRAS (Face Recognition + MQTT IP-camera ingestion) integration into IRMS v1.0 (Laravel 12, PostgreSQL/PostGIS, Reverb, Horizon, MapLibre) — CDRRMO Butuan City
**Researched:** 2026-04-21
**Confidence:** HIGH
**Supersedes:** v1.0 pitfalls research (was authored 2026-03-12, pre-dates FRAS scope)

Scope rule: every pitfall below is specific to *merging* FRAS capabilities into a **live LGU dispatch platform**. Generic Laravel / MQTT / Postgres warnings are excluded unless they have a FRAS+IRMS-specific edge.

Ownership is expressed against the v2.0 target phases implied by `PROJECT.md`:

- **Phase 17 — Framework Upgrade** (Laravel 12 → 13, package compatibility, no behaviour change)
- **Phase 18 — Schema Port** (FRAS MySQL schema → Postgres in the IRMS database, no business logic)
- **Phase 19 — MQTT Pipeline** (listener, handlers, image storage, Supervisor)
- **Phase 20 — Camera & Personnel Admin** (CRUD, enrollment sync, map layer)
- **Phase 21 — Recognition → Intake Bridge** (RecPush → IoT intake channel, severity mapping, dedup)
- **Phase 22 — Alert Feed + Retention + Privacy** (dispatch UI surface, retention, DPA compliance)

## Critical Pitfalls

### Pitfall 1: Doing the Laravel 12 → 13 upgrade in the same phase as MQTT/camera work

**What goes wrong:**
The upgrade mixes framework-level churn (job serialisation change, deprecation removals, PHP 8.3+ requirement, Fortify passkey defaults, Horizon supervisor changes) with brand-new domain code (MQTT handlers, RecPush parsing, photo pipeline). When dispatch regresses in staging, the team can't tell whether the cause is the framework upgrade or the new FRAS code, and rollback has to take *everything* back.

**Why it happens:**
Laravel 13 is marketed as "zero breaking changes / 10-minute upgrade," so teams bundle it with the feature milestone. In reality v13 **removes** previously-deprecated methods (`Route::controller()`, `$request->has()` with array syntax, `Model::unguard()`, `Str::slug()` with custom separator) and **changes job payload serialisation** — jobs queued by L12 workers fail on L13 workers. IRMS has 6 queued broadcast events plus Horizon and its own push-notification jobs; any in-flight job at the cutover is at risk.

**How to avoid:**
- Make Phase 17 a dedicated "upgrade + freeze" phase with **zero new feature code**. Success criterion = existing IRMS test suite green on L13 and all 6 v1.0 Reverb events still fire with identical payloads.
- Drain Horizon before deploy (`horizon:terminate` then wait for idle), then deploy L13 artifacts, then restart. Do not allow mixed-version workers.
- Grep the codebase for every removed API surface before starting (`Route::controller`, `->unguard(`, `Str::slug(`, `->has([`). Add a CI check.
- Require PHP 8.3+ on the Herd dev box *and* the DigitalOcean droplet before upgrade PR lands — IRMS is on 8.4.19 locally (good) but the droplet must be verified.
- Keep Fortify passkey defaults **off** in Phase 17. v2.0 must not silently start issuing WebAuthn challenges to operators who only know their TOTP flow. Fortify config must explicitly opt out until a separate phase adds passkeys with a UAT plan.

**Warning signs:**
- CI fails with `Method … deprecated` after upgrade — rollback, fix, retry.
- Any job serialization error in the Horizon dashboard during deploy — means a pre-upgrade job hit a post-upgrade worker.
- Dispatch map stops rendering incident markers on staging after upgrade — almost certainly an Inertia shared-prop shape regression, not a FRAS bug.

**Phase to address:** Phase 17 (own phase, blocks all others).

---

### Pitfall 2: Assuming Inertia v2 → v3 is transparent because FRAS is already on v3

**What goes wrong:**
FRAS Vue components use Inertia v3 conventions (e.g., `<Form>` component, v3 deferred-prop semantics, `usePage()` return shape). When those components are lifted into IRMS, they silently bind against v2's API surface. Symptoms: submit buttons that no-op, deferred props that never resolve, dark-mode flashes because v2 SSR context is read differently.

**Why it happens:**
"Inertia v2 → v3" is sold as an incremental upgrade but the `@inertiajs/vue3` package has type signature changes (`router.visit` options, `usePage<T>()` generic signature, `Deferred` slot prop names). IRMS currently depends on v2 patterns across `useDispatchFeed`, `useResponderSession`, `useReportDraft`, and the HandleInertiaRequests shared-prop bundle — the blast radius is every authenticated page.

**How to avoid:**
- Decide in Phase 17 whether IRMS stays on Inertia v2 or moves to v3. **Recommended: stay on v2** for v2.0. Port FRAS Vue components down to v2 semantics at copy-time rather than upgrade IRMS up. FRAS v3-only features used in the source (if any) are `<Form>` component + deferred prop API — both have clean v2 equivalents (`useForm` + Inertia v2 deferred props, already used in IRMS `12-03` dispatch messaging).
- Write a one-page "v3 → v2 shim" note in `.planning/research/` before Phase 20, listing each v3 API used by FRAS and its v2 translation.
- Do **not** `npm install @inertiajs/vue3@latest` blindly — pin to the version that matches the IRMS v1.0 lockfile.

**Warning signs:**
- `usePage().props` returns `undefined` for any v1.0 shared prop after porting a FRAS page.
- TypeScript errors on `router.visit(..., { … })` option keys.
- `<Link>` prefetch behaviour differs between FRAS pages and IRMS pages.

**Phase to address:** Phase 17 (decision) and Phase 20/22 (enforcement when porting Vue pages).

---

### Pitfall 3: Porting FRAS MySQL JSON columns to PostgreSQL as `JSON` instead of `JSONB`

**What goes wrong:**
FRAS uses MySQL `JSON` columns for `recognition_events.metadata`, `cameras.config`, enrollment `payload_snapshot`, etc. A literal `json('metadata')` in the Laravel migration creates a PostgreSQL `json` column (text-preserving, no indexing, slow containment ops). Event-history search then scans every row. Within a week of camera traffic the event-history page hits 2-3 second load times.

**Why it happens:**
Laravel's `$table->json()` maps to `json` in Postgres by default. Most devs don't know `jsonb` exists. MySQL has no distinction.

**How to avoid:**
- Use `$table->jsonb('column')` explicitly in Phase 18 migrations. IRMS v1.0 already uses JSONB for `incidents.vitals` — match that precedent.
- Add GIN indexes on the JSONB columns FRAS queries by keys (the `metadata` column on `recognition_events` is the high-volume one).
- Write a migration comment: `// JSONB, not JSON — required for index support; IRMS convention.`

**Warning signs:**
- `SELECT … WHERE metadata @> '{"camera_id": "…"}'` does a Seq Scan in `EXPLAIN`.
- Event history filters take >500ms on 10k rows.

**Phase to address:** Phase 18.

---

### Pitfall 4: Preserving MySQL AUTO_INCREMENT primary keys when IRMS uses UUIDs

**What goes wrong:**
FRAS tables use integer `AUTO_INCREMENT` IDs (cameras.id, personnel.id, recognition_events.id). IRMS v1.0 Phase 1 standardised on `HasUuids` for core domain tables (Incident uses UUID string PK, per decision log 13-02). A literal port creates a split-brain schema: `incidents.id` is UUID, `recognition_events.incident_id` (if/when linked) is BIGINT. Foreign key constraints break.

**Why it happens:**
Copy-paste migrations. MySQL → Postgres schema tools preserve integer PKs by default and just change `AUTO_INCREMENT` → Postgres `BIGSERIAL`/`GENERATED AS IDENTITY`.

**How to avoid:**
- Phase 18 decision point: match IRMS convention (UUID for all FRAS tables). Use `HasUuids` trait and `$table->uuid('id')->primary()`.
- Personnel `custom_id` (the camera-side identifier) is a **separate** field from primary key — preserve as `string` column with a unique index, not as PK.
- Camera `device_id` (the MQTT topic segment) is similarly a business key, not a PK. It stays as `string`.
- Document in Phase 18 plan: "All FRAS tables use UUID primary keys to match IRMS convention. Integer IDs from FRAS source are treated as legacy and not carried over."

**Warning signs:**
- A migration declares both `$table->id()` and `$table->uuid('uuid')->unique()` — pick one, and it should be UUID.
- Wayfinder types show `id: number` for FRAS models but `id: string` for IRMS models in TypeScript — immediate smell.

**Phase to address:** Phase 18.

---

### Pitfall 5: MySQL `TIMESTAMP` → PostgreSQL `TIMESTAMP WITHOUT TIME ZONE` on port

**What goes wrong:**
FRAS events carry `recognized_at` timestamps. The camera sends UTC epoch; Laravel casts to `datetime`; MySQL stores as `TIMESTAMP` (session-TZ-interpreted in MySQL but implicit-UTC in many configs). Port to Postgres and the default column is `TIMESTAMP WITHOUT TIME ZONE` — you lose tz awareness. Butuan is UTC+8, Reverb broadcasts timestamps as ISO strings, dispatchers see events "8 hours ago" that actually just arrived.

**Why it happens:**
Laravel `$table->timestamp()` maps to `timestamp without time zone` in Postgres. IRMS v1.0 dodged this because incident timestamps are treated as `config('app.timezone')`-aware at the application layer. FRAS has camera-origin timestamps that weren't under IRMS-side tz discipline.

**How to avoid:**
- Phase 18 uses `$table->timestampTz('recognized_at')` (TIMESTAMPTZ) for any column receiving wall-clock data from cameras.
- Keep existing IRMS `created_at`/`updated_at` columns as they are (don't retrofit existing tables — that's scope creep).
- Add one feature test: post a Rec event with a known UTC instant, assert the DB-stored value round-trips to the same UTC instant regardless of PHP tz.

**Warning signs:**
- Alert feed shows events in Asia/Manila but event-history filters use UTC → date-range off-by-one.
- Retention sweep deletes "old" images that were actually from today.

**Phase to address:** Phase 18.

---

### Pitfall 6: MQTT listener running under Horizon supervisor config

**What goes wrong:**
The natural instinct is "it's a long-running PHP process like a queue worker; Horizon already handles that." So the MQTT listener gets added as a Horizon supervisor block. Result: Horizon thinks it's a queue worker, restarts it on arbitrary SIGTERM, applies `--max-jobs`/`--max-time` semantics that don't apply, and fights with the MQTT client's own reconnect logic. Broker disconnects cause thundering-herd restarts.

**Why it happens:**
Both are "long-running PHP daemons" — the categories blur. FRAS's own architecture (Phase 1 of FRAS milestone) uses a **separate** Supervisor program for `fras:mqtt-listen`, not a Horizon supervisor. That precedent is correct and must carry over.

**How to avoid:**
- Phase 19 registers a **dedicated Supervisor program** (e.g., `irms-mqtt.conf`) for the listener artisan command. Separate from the Horizon supervisor block. Separate log file.
- The listener command itself handles reconnect: php-mqtt/laravel-client supports a retry loop but requires explicit `loop()` and `disconnect()` wiring; port the FRAS pattern verbatim.
- Use `--max-time=3600` on the listener command — exit cleanly every hour; Supervisor restarts; fresh PHP process avoids FD/memory leak. Pair with `--max-messages` if available.
- Listener must **never** do heavy work inline. It parses the topic, validates minimally, dispatches a Horizon job for the payload. The listener process stays lean; the job queue absorbs backpressure.

**Warning signs:**
- Horizon dashboard shows the listener as a "worker" with erratic job counts.
- Same listener PID never changes for days → it's leaking FDs or memory silently.
- `ps aux | grep mqtt-listen` shows multiple competing PIDs after a broker restart.

**Phase to address:** Phase 19.

---

### Pitfall 7: Deploying code without restarting the MQTT listener (stale-code-running-forever)

**What goes wrong:**
`git pull && php artisan optimize:clear` doesn't touch the MQTT daemon. The listener keeps running the *old* RecPush handler code. New deployments appear to ship but recognition events use yesterday's parsing logic. Severity rules drift. Bug fixes don't apply.

**Why it happens:**
`artisan queue:restart` signals Horizon workers, not arbitrary daemons. Supervisor doesn't know the PHP file changed. Unlike web requests (every FPM worker picks up new code on next request), a `while(true)` loop doesn't.

**How to avoid:**
- Deploy script must include `supervisorctl restart irms-mqtt:*` (or equivalent).
- Alternatively, the listener watches a "version marker" file and self-exits on change (FRAS Phase 1 used this pattern — port it).
- Add a post-deploy smoke test: publish a synthetic Rec event, confirm it lands in `recognition_events` with the build hash in the metadata.

**Warning signs:**
- Recognition event timestamps stop advancing shortly after deploy → listener is alive but disconnected, or alive and running stale code.
- Developer says "I fixed that yesterday, why is it still broken."

**Phase to address:** Phase 19 (deploy doc), and a plan-level task in the Phase 19 runbook.

---

### Pitfall 8: Camera recognition event rate swamps the intake pipeline

**What goes wrong:**
An AI IP camera fires RecPush on every face frame it sees. In a busy scene (e.g., Butuan market entrance) that's 10+ events/sec per camera × 8 cameras × 200 enrolled personnel = burst of hundreds of events/second. IRMS intake is built for human-scale volume (SMS + walk-in + citizen app = ~dozens/day). If every recognition event becomes an intake row, dispatchers drown, PostGIS row-count explodes, and the channel monitor counter goes exponential.

**Why it happens:**
The requirement says "recognition events ingested through existing IoT intake channel (no new channel)". The easy (wrong) interpretation is: one recognition event = one intake row. The correct interpretation is: a recognition **alert** (block-list / refused) may create an intake row, but ordinary recognition events are just logged in `recognition_events` for the history page.

**How to avoid:**
- Phase 21 design decision: only `AlertSeverity::CRITICAL` (block-list match) events create IoT-channel incident rows. `WARNING` (refused) optionally creates a dispatcher notification, not an incident. `INFO` (normal allowed) is history-only.
- Deduplication window: if the same personnel_id is recognised by the same camera_id within N seconds (start N=60), suppress duplicate intake creation. Store the active incident id on the recognition event so subsequent events append to it rather than multiply it.
- Confidence threshold gate: recognition events below a configurable `config('fras.min_confidence', 0.75)` never reach the IoT intake bridge at all.
- Load-test Phase 21 with a synthetic publisher at 50 events/second/camera and assert Horizon queue depth stays bounded.

**Warning signs:**
- Intake channel monitor shows "IOT: 2,400 today" and it's not even lunch.
- Dispatch queue has 30 open P3 incidents that are all the same person walking past the same camera.
- PostgreSQL `incidents` table row count doubles in a day.

**Phase to address:** Phase 21 (the core of this phase).

---

### Pitfall 9: Severity → priority mapping that treats every recognition as an emergency

**What goes wrong:**
Block-list match → P1. Refused/unknown → P2. Normal allow-list → P3. Dispatchers get paged at 2am because a janitor swiped at the back door. P1 audio tone fires on every staff member entering. Dispatcher desensitisation sets in within a week and real P1 incidents get muted.

**Why it happens:**
A naive one-to-one mapping from FRAS's `AlertSeverity` enum to IRMS's `P1..P4`. Both are severity ladders but they measure different things — FRAS severity is *about the recognition event* (who); IRMS priority is *about the emergency response* (how fast to roll a truck). They are not the same axis.

**How to avoid:**
- Phase 21 priority mapping rule:
  - `CRITICAL` (block-list) → **P2** default, configurable escalation to P1 only for specific block-list categories (e.g., "armed threat").
  - `WARNING` (refused entry) → **P4** notification-only, does not auto-create an incident unless dispatcher promotes.
  - `INFO` (normal allow) → no IRMS visibility beyond event history.
- Dispatcher must confirm before a P2 becomes P1. No auto-escalation paths from camera events to P1.
- Persist the rule in `config/fras.php` and expose to admin UI in a later milestone — do not hard-code in the handler.

**Warning signs:**
- P1 audio tone fires >5x/day with no corresponding real emergency.
- Dispatchers start muting the tab (check browser console logs for muted-tab hints).

**Phase to address:** Phase 21.

---

### Pitfall 10: Storing scene images on local disk with no retention ordering

**What goes wrong:**
Scene images are up to 2MB. At 5k events/day × 2MB = 10GB/day. Within a month the droplet fills up, Postgres can't write, dispatch console blanks. Worse: when retention cleanup kicks in, it deletes images that are *still referenced* by active incidents (recognition event attached to a P2 that's still OPEN) — dispatcher clicks the alert, gets a broken image, can't verify the subject.

**Why it happens:**
FRAS v1.0 retention policy (scenes 30d, faces 90d) was fine for a single-site facility. An LGU dispatch system routinely keeps incidents open for multiple days during disasters; an incident started day 29 might reference scene images about to be purged.

**How to avoid:**
- Phase 22 retention sweep must **exclude** images referenced by any incident that is not `RESOLVED` (or not resolved for N days).
  - Concretely: `DELETE FROM recognition_events WHERE created_at < NOW() - INTERVAL '30 days' AND NOT EXISTS (SELECT 1 FROM incidents WHERE incidents.reference_rec_event = recognition_events.id AND incidents.status != 'RESOLVED')`.
  - Delete the image file **after** the DB row, not before.
- Use the `fras` disk (not `local`), configure in `config/filesystems.php` with an explicit path. Makes the S3 migration path trivial later.
- Monitor disk usage: add a scheduled task that posts `df -h` to a log channel daily, alert if <20% free.
- Storage estimate + retention window are **configurable** (`config/fras.php` → `retention.scene_days`) — do not hard-code to 30.

**Warning signs:**
- `df -h` shows `/var/www` >80%.
- Dispatcher reports "clicked alert, image was missing" — check if retention sweep was the cause.

**Phase to address:** Phase 22.

---

### Pitfall 11: Facial biometric data with no Philippine DPA posture

**What goes wrong:**
FRAS v1.0 shipped at a single private facility — "internal deployment" posture was fine. IRMS is a **government agency processing citizens' biometric data**. Under RA 10173, biometric data is *sensitive personal information*; processing is generally prohibited absent a lawful basis. CDRRMO does not automatically inherit one. Without a Privacy Impact Assessment, retention schedule, published notice, and NPC registration of the processing system, the deployment is exposed to NPC complaint + administrative fines + individual civil claims.

**Why it happens:**
Engineering treats "LGU deployment" as equivalent to "internal deployment." Privacy review is assumed to happen "later" or by "someone else." The FRAS source does not carry a DPA compliance module because its source facility didn't need one.

**How to avoid:**
- Phase 22 **must** deliver a DPA compliance surface, not just the alert feed UI. Minimum:
  - Published Privacy Notice page accessible from citizen-app and main app footers, describing the FRAS processing (what data, what purpose — "public safety incident response," lawful basis, retention, data subject rights).
  - Consent/Notice signage text (generated from the app) that CDRRMO can post at camera locations.
  - Role-gated access: recognition images visible only to `operator`, `supervisor`, `admin` — never to `responder` (field responders don't need biometric access) and never to `dispatcher` unless the gate is granted explicitly.
  - Audit log of every image view + every enrollment add/remove — at minimum `viewer_id, image_id, viewed_at` rows. This was deferred in FRAS v1.0 ("Audit logs of admin actions — deferred to v1.1"); it cannot be deferred here.
  - Retention policy configurable and documented in the repo; default 30 days for images is a reasonable starting point for CDRRMO but must be signed off by the client.
  - Export/erasure endpoint: a data subject requesting erasure must be honourable via an admin action that removes their `personnel` row + cascades across `camera_enrollments` + anonymises matched `recognition_events`.
- Engage CDRRMO legal / Butuan City LGU data privacy officer **before** Phase 22 design-freeze. Block the phase on their sign-off.
- Phase 22 must produce a PIA template (Privacy Impact Assessment) the client can fill in and register with NPC.

**Warning signs:**
- "We'll handle privacy later" in any plan doc.
- Recognition event photos viewable by any authenticated user.
- No route-level or gate-level restriction on `GET /alerts/{id}/image`.

**Phase to address:** Phase 22. Block the milestone on client legal sign-off.

---

### Pitfall 12: Channel authorisation gap on FRAS Reverb channels

**What goes wrong:**
FRAS source broadcasts `RecognitionAlert` on a private channel `fras.alerts`. Porting that channel to IRMS without role discipline means any authenticated IRMS user (including a `responder` field user or a future `operator`) subscribes and receives biometric image URLs over WebSocket. This bypasses the role-based access control on the HTTP side entirely.

**Why it happens:**
The IRMS channel-auth matrix (Phase 3) was designed for 5 role-based private channels (`dispatcher.*`, `responder.*`, etc.). A new `fras.alerts` channel added as "a catchall for everyone" regresses the role model.

**How to avoid:**
- Phase 19/20 `routes/channels.php`: authorise `fras.alerts` only for `operator|supervisor|admin`. No `dispatcher`, no `responder`.
- If dispatcher needs to see some FRAS signal, create a separate lower-sensitivity channel (e.g., `fras.camera-status` for heartbeat only) that omits image URLs.
- Channel-naming convention: all FRAS Reverb events namespaced `fras.*` and listed in REQUIREMENTS.md alongside the 6 existing v1.0 events. No collisions with existing `dispatch.*`, `responder.*`, `intake.*`, `incident.{id}` names.
- Add a Pest test per channel that asserts only allowlisted roles return `true` from the auth callback (pattern already used by IRMS Phase 3 `phpunit.xml` BROADCAST_CONNECTION=reverb tests).

**Warning signs:**
- Grep finds `Broadcast::channel('fras.alerts', fn () => true)` — reject immediately.
- Responder page's DevTools → Network → WS frames include recognition image URLs.

**Phase to address:** Phase 19 (listener broadcasts) and Phase 20 (camera status broadcasts).

---

### Pitfall 13: Reverb broadcast flood crowds out v1.0 dispatch events

**What goes wrong:**
High-rate camera events saturate the Reverb server or the client WebSocket. Dispatch queue updates (IncidentCreated, UnitMoved) arrive seconds late. Dispatcher sees a stale map because the tab is busy processing RecognitionAlert payloads. Sub-500ms spec requirement violated.

**Why it happens:**
Reverb (and Pusher-compatible protocol) is single-threaded per connection. Heavy payloads (a RecognitionAlert with a 2MB scene image base64 inline would be catastrophic — but even 200-byte URLs at 50 msg/sec per tab starve the event loop) choke the channel.

**How to avoid:**
- **Never** inline image bytes in a broadcast. `RecognitionAlert` payload contains `scene_url`, `face_crop_url`, IDs, severity, timestamp — lazy-load the image via HTTP on click.
- Server-side throttle: the RecPush handler buffers events per (camera, personnel) pair and broadcasts at most 1/sec — subsequent events update the latest row, they don't fire new broadcasts.
- Per-page subscription discipline: the dispatch console subscribes to `fras.camera-status` (low-rate) and `fras.alerts-critical` only. The full alert feed page subscribes to `fras.alerts` (higher rate) because that's its job.
- Phase 22 load test: 20 msg/sec on fras.alerts for 60 seconds, measure dispatch console IncidentCreated round-trip latency p95 — must stay under 500ms.

**Warning signs:**
- `useDispatchFeed` IncidentCreated latency creeps above 1s in staging.
- Browser Task Manager shows the IRMS tab at 100% CPU.
- Reverb server log shows queued outbound messages.

**Phase to address:** Phase 19 (broadcast throttle) and Phase 22 (load test).

---

### Pitfall 14: Frontend `useEcho` subscription memory leak on high-rate channels

**What goes wrong:**
The FRAS alert feed page subscribes to `fras.alerts` via `useEcho` and accumulates every received event into a reactive array. Dispatcher leaves the tab open for an 8-hour shift. By end of shift the array has 50k+ entries, Vue reactivity tracks each, browser tab crashes at 3GB RAM.

**Why it happens:**
IRMS v1.0 already learned this lesson — `useDispatchFeed` caps ticker events at 20 (ring buffer), `useIntakeFeed` caps pending/triaged at 100. But a fresh FRAS page written by copying FRAS source won't have those caps; FRAS's source UI was for short operator sessions and never hit the limit.

**How to avoid:**
- Every new `useEcho` subscriber in Phase 20/22 has an explicit cap on its retained state (e.g., last 100 alerts in the feed, older events paginated via HTTP).
- Provide a reusable `useBoundedFeed<T>(max: number)` composable in Phase 19 so future pages don't re-invent.
- Phase 22 dev-test: open the alert feed page, leave it for 1 hour with a synthetic publisher, assert `performance.memory.usedJSHeapSize` stays flat.

**Warning signs:**
- Browser tab memory grows monotonically in staging.
- `Array.prototype.push` in a composable with no corresponding `shift` or `slice`.

**Phase to address:** Phase 22.

---

### Pitfall 15: Cameras stored without PostGIS geography and Magellan cast

**What goes wrong:**
FRAS source uses `lat decimal(10,8)`, `lng decimal(11,8)` as separate columns. A literal port preserves that. Now "show cameras on dispatch map" requires computing distances in PHP; "filter cameras inside barangay polygon" is a client-side loop or raw SQL; heatmap join with incidents requires casting on every query. The PostGIS advantage IRMS paid for in Phase 1 is bypassed.

**Why it happens:**
Decimal lat/lng is intuitive and was MySQL-native. PostGIS is not automatic in Laravel migrations — the Magellan cast (clickbar/laravel-magellan) must be opted into per model.

**How to avoid:**
- Phase 18 camera migration uses the Magellan `$table->geography('location', 'POINT', 4326)` helper — same pattern as IRMS `units.location`.
- `Camera` model uses Magellan `Point` cast, matching `Unit` model.
- `ST_Contains(barangay.boundary, camera.location)` becomes a one-line Eloquent scope — matches the IRMS `BarangayLookupService` pattern.
- Do **not** introduce a separate lat/lng pair alongside geography. Keep one source of truth.

**Warning signs:**
- Camera model has `$casts = ['lat' => 'float', 'lng' => 'float']` — wrong, replace with Magellan point.
- Admin UI sends `{lat, lng}` but backend recomposes into geography on every save — smell.

**Phase to address:** Phase 18 + 20.

---

### Pitfall 16: Enrollment sync rate-limiting / in-flight collision on redeploy

**What goes wrong:**
FRAS constraint: only one EditPersonsNew batch in-flight per camera at a time. IRMS has 8 cameras × ~200 personnel with periodic full-resyncs. If Horizon is drained during deploy (pitfall 1), in-flight enrollment jobs restart on the new worker. Without `WithoutOverlapping` applied correctly, two batches hit the same camera, the camera rejects one, the personnel row shows `sync_failed` for no real reason.

**Why it happens:**
`WithoutOverlapping($cameraId)` must be applied at the **camera** key level, not a global mutex. Easy to get wrong when copy-porting — the FRAS Phase 4 implementation is correct but fragile to refactoring.

**How to avoid:**
- Phase 20 enrollment job must use `public function middleware(): array { return [(new WithoutOverlapping($this->cameraId))->expireAfter(300)]; }`.
- Expire-after is essential — if a job dies mid-execution the lock is released, otherwise a dead job blocks future enrollments forever.
- Feature test: dispatch two enrollment jobs for the same camera concurrently; assert only one runs; the second retries.
- Retry policy: bounded exponential backoff with a max of 5 attempts, then mark `camera_enrollments.status = 'failed'` with a human-readable reason.

**Warning signs:**
- A camera's `camera_enrollments` rows are all `pending` forever — stuck lock.
- Redis shows a `laravel_unique_job:CameraEnroll:CAM-001` key with no TTL.

**Phase to address:** Phase 20.

---

### Pitfall 17: MQTT QoS 0 message loss on broker restart / subscriber restart

**What goes wrong:**
FRAS operates at MQTT QoS 0 (fire-and-forget) per the camera firmware spec. When the broker restarts, or the subscriber reconnects, any in-flight messages are lost silently. Block-list match at the moment of restart = missed critical alert. No retry, no warning.

**Why it happens:**
QoS 0 is the camera firmware default and cannot be raised unilaterally. The protocol itself doesn't guarantee delivery.

**How to avoid:**
- Phase 19 subscriber heartbeat: every camera sends `heartbeat` every 30s. If heartbeat is missed for 90s, mark camera offline. On reconnect, log the outage window so dispatchers *know* they might have missed events during that window.
- Add an `mqtt_listener_health` model row updated every 10s with `last_message_at` — expose to admin and run a scheduled alert if the gap exceeds threshold.
- Do not rely on Mosquitto persistence for missed messages — explicitly document "during MQTT broker/subscriber outage, recognition events are lost; cameras have onboard buffering that *may* replay some events if `ResumefromBreakpoint` is enabled, but FRAS v1.0 decided against that and this port inherits that decision."
- Operational SLA: document the expected MTTR for MQTT listener restart (< 30s via Supervisor) and surface listener-down state to the dispatcher (red banner).

**Warning signs:**
- Heartbeat gap with no corresponding broadcast event in dispatch timeline.
- Mosquitto log shows many `disconnected` events but no event-storm on reconnect.

**Phase to address:** Phase 19.

---

### Pitfall 18: Topic subscription wildcard mismatch

**What goes wrong:**
Camera publishes to `cameras/{cloudId}/rec`. Developer subscribes to `cameras/+/rec/#`. No messages received. Silent — no error, just an empty subscriber. Six hours of debugging.

**Why it happens:**
`+` matches one level; `#` matches multi-level but must be the last character. `cameras/+/rec/#` requires a fourth segment to be present, which it often isn't. MQTT is unforgiving here.

**How to avoid:**
- Phase 19 subscription constants in `config/fras.php`:

  ```php
  'topics' => [
      'rec' => 'cameras/+/rec',
      'ack' => 'cameras/+/ack/+',
      'heartbeat' => 'cameras/+/heartbeat',
      'online_offline' => 'cameras/+/basic/online',
  ],
  ```

- Test the `TopicRouter` with Pest: for each topic pattern, publish a known topic string and assert the right handler receives it. FRAS Phase 1 shipped this test pattern — port it.
- Log every received topic in debug mode for the first week of deployment so operators can verify pattern correctness.

**Warning signs:**
- Subscriber connects, never receives a message despite camera publishing normally (check Mosquitto log to confirm camera is publishing).
- `TopicRouter` default/unmatched branch logs with a topic that "should have" matched.

**Phase to address:** Phase 19.

---

### Pitfall 19: Inertia v2 shared-prop regression during Fortify upgrade

**What goes wrong:**
IRMS Phase 1 shared 9 permission flags via HandleInertiaRequests middleware. Fortify upgrade in Phase 17 changes the `$request->user()` resolution order or adds passkey fields to the User serialisation. Sidebar permission flags silently change shape; some menu items disappear for all roles.

**Why it happens:**
Fortify's user object is the source for `auth.user` shared prop. Any Fortify upgrade that adjusts user serialisation cascades to every Inertia page.

**How to avoid:**
- Phase 17 has a regression test: a Pest feature test that renders Dashboard for each of 5 roles and asserts the sidebar has the exact 9 permission flags with expected boolean values. This test runs **before and after** the upgrade; both must pass.
- Fortify config must explicitly pin the features enabled in v1.0 — do not inherit Fortify 13 defaults blindly.
- If Fortify 13 adds `passkeys` to the default user serialisation, exclude it via `User::$hidden` or a resource class.

**Warning signs:**
- Sidebar menu count changes between v12 and v13 in the regression test.
- `auth.user.id` changes type (UUID string ↔ integer) after upgrade.

**Phase to address:** Phase 17.

---

### Pitfall 20: Mapbox GL JS creeping into IRMS (should remain MapLibre-only)

**What goes wrong:**
FRAS source uses Mapbox GL JS v3 with HelderDene custom styles. Copy-porting FRAS dashboard components brings `import mapboxgl from 'mapbox-gl'` along with them. IRMS ends up with *both* MapLibre GL JS (dispatch, responder, analytics, citizen app) and Mapbox GL JS (FRAS camera dashboard). Two map libraries = double bundle size + conflicting styles + Mapbox access-token dependency where there was none.

**Why it happens:**
FRAS components are written against the Mapbox SDK. At copy time the path of least resistance is "keep Mapbox, add the token." The IRMS decision log (Phase 4 + Constraints) explicitly mandates MapLibre for WebGL markers.

**How to avoid:**
- Phase 20 port rule: **rewrite** every Mapbox-using FRAS Vue component against MapLibre GL JS before it lands in IRMS. MapLibre is API-compatible with Mapbox GL JS v1 and most v2/v3 features; the camera pulse animation is pure GeoJSON + expression-based paint properties which port cleanly.
- Bundle analyser check: `npm run build` then audit `dist/assets` — assert no `mapbox-gl` chunk present.
- Map style port: FRAS custom HelderDene styles are Mapbox Studio JSON — the style JSON itself is MapLibre-compatible as long as it doesn't use Mapbox-proprietary sources (`mapbox://` URIs). Rewrite those sources to MapTiler or OSM equivalents (same pattern IRMS Phase 4 used).

**Warning signs:**
- `package.json` diff shows a new `mapbox-gl` dependency.
- `.env.example` grows a `VITE_MAPBOX_ACCESS_TOKEN` key.

**Phase to address:** Phase 20.

---

### Pitfall 21: Sentinel design-token drift (FRAS glassmorphism overrides IRMS palette)

**What goes wrong:**
FRAS v1.0 Phase 8 shipped a slate/steel-blue SOC aesthetic with glassmorphism, glow effects, dense data grids. IRMS v1.0 Phase 14 shipped the Sentinel navy/blue palette with DM Mono + Bebas Neue. Both use CSS custom properties. A naïve copy imports FRAS `:root { --fras-… }` variables alongside IRMS `--t-…` tokens, and FRAS page components reference `--fras-glow-alert` which doesn't exist in IRMS context, so pages render colourless.

**Why it happens:**
Both products are HDSystem-authored, both use CSS variable indirection, both look "navy-ish". Token names don't collide but also don't align.

**How to avoid:**
- Phase 20 port rule: **no new CSS variables**. Every FRAS Vue component rewrites its `--fras-*` references to IRMS `--t-*` equivalents *at copy time*. Create a mapping table in `.planning/research/` before Phase 20.
- Glassmorphism / glow is additive styling — port selectively. Don't import FRAS's dashboard chrome wholesale; use existing IRMS Sentinel shell.
- Add a CI grep check: `grep -r "--fras-" resources/js/` returns empty.

**Warning signs:**
- A FRAS-derived page has `style="color: var(--fras-…)"` in the DOM.
- New Tailwind classes referring to undefined custom properties.

**Phase to address:** Phase 20/22.

---

### Pitfall 22: PostgreSQL migrations colliding with existing IRMS table names or sequences

**What goes wrong:**
FRAS has a `users` table (Fortify). IRMS has a `users` table (Fortify). Both have `password_reset_tokens`, `sessions`, `jobs`. A blind port of FRAS migrations tries to recreate these, the migration fails, and in the worst case (if someone uses `migrate:fresh`) blows away the IRMS prod users table.

**Why it happens:**
Migration copy from a separate Laravel starter kit. Someone runs `migrate:fresh` on staging thinking it's a safe env.

**How to avoid:**
- Phase 18 migration audit: compare every FRAS migration against existing IRMS migrations. Delete any duplicate (users, sessions, jobs, password_reset_tokens, personal_access_tokens). Keep only FRAS-specific (cameras, personnel, camera_enrollments, recognition_events).
- Forbid `migrate:fresh` on prod/staging: add a guard that refuses if `APP_ENV in [production, staging]`.
- All FRAS migrations include a conflict-check: `if (Schema::hasTable('cameras')) throw` rather than silent no-op.

**Warning signs:**
- `php artisan migrate --pretend` shows any `CREATE TABLE users` or `ALTER TABLE users` from a FRAS migration.

**Phase to address:** Phase 18.

---

### Pitfall 23: Camera "address" field assumed to be geocodable, but cameras don't geocode

**What goes wrong:**
FRAS stores camera location as lat/lng (set by admin at camera registration). IRMS dispatcher expects a human-readable address ("near Brgy. Libertad covered court, J.C. Aquino Ave."). A naive port shows "8.9500°N, 125.5400°E" in the alert feed — useless for dispatch.

**Why it happens:**
FRAS v1.0 was a facility deployment where admins knew the camera names by role ("south gate", "main entrance"). CDRRMO deployment is across a *city* with 86 barangays; "camera CAM-042" means nothing to the dispatcher.

**How to avoid:**
- Phase 20 camera create/edit form: require `label` (human name), `address` (geocoded via existing IRMS geocoding service from Phase 2), and `barangay_id` (auto-resolved via `ST_Contains`, editable).
- Alert feed displays: `{camera.label} — {camera.barangay.name}` — never raw coordinates.
- Optional: also store `fallback_description` free-text for landmark context ("beside 7-11 on the corner").
- Reuse the IRMS `GeocodingService` from Phase 2; do not introduce a new geocoder.

**Warning signs:**
- Any Vue component shows `{{ camera.lat }}, {{ camera.lng }}` as primary text.
- Camera create form has no address autocomplete.

**Phase to address:** Phase 20.

---

### Pitfall 24: Existing v1.0 Reverb event payloads silently break on L13 broadcast changes

**What goes wrong:**
Laravel 13 adjusts `broadcastWith()` / `broadcastAs()` serialisation in subtle ways (e.g., enum casting). The 6 v1.0 Reverb events (IncidentCreated, UnitAssigned, StatusChanged, ChecklistUpdated, ResourceRequested, MessageSent) emit the same *logical* payload but in a slightly different shape — e.g., `priority` becomes `"P2"` instead of `2`. Dispatch console's `useDispatchFeed` breaks because it switches on numeric priority (IRMS decision 09-03: "Numeric priority values in API resources instead of string format").

**Why it happens:**
Laravel 13 release notes emphasise "zero breaking changes" but enum serialisation in broadcasts has shifted in past releases. Any enum in a broadcast payload is a risk surface.

**How to avoid:**
- Phase 17 smoke test: for each of the 6 existing broadcast events, snapshot the payload shape **before** upgrade, upgrade, emit the same event, diff. Any diff is a regression that must be reverted to v1.0 shape via explicit `broadcastWith()` override.
- Long-term: declare every broadcast payload via a Resource class with explicit shape (don't rely on model serialisation). This is a Phase 17 refactor candidate.
- Add payload-shape Pest tests (IRMS already has some in `phpunit.xml` BROADCAST_CONNECTION=reverb pattern) as the acceptance criteria for Phase 17.

**Warning signs:**
- Dispatch console audio tone doesn't fire on P1 after upgrade.
- Frontend TypeScript error "Type 'string' is not assignable to type '1 | 2 | 3 | 4'" after upgrade on an Echo event payload.

**Phase to address:** Phase 17.

---

### Pitfall 25: Photo URL signing vs. camera-fetch requirement contradiction

**What goes wrong:**
IRMS best practice is signed/authenticated image URLs (especially for biometric data). FRAS constraint: the *camera itself* fetches enrollment photos over HTTP from the Laravel server, and the camera can't sign requests — it hits a plain URL. Instinct says "make all photos signed," which breaks camera enrollment.

**Why it happens:**
Two different consumers (human browser, IoT device) have different auth capabilities. One-size-fits-all URL rule picks one and breaks the other.

**How to avoid:**
- Phase 20 establishes **two** URL namespaces:
  - `/fras/enrollment-photos/{uuid}.jpg` — public, unguessable UUID, served only while `personnel.sync_status = pending`. After all cameras ACK, the public URL is revoked (or the file is moved to a private path). This is the narrowest possible exposure window.
  - `/fras/recognition-images/{id}` — auth-required, signed URL, 5-minute expiry, role-gated (`operator|supervisor|admin`). Used by the alert feed and event history.
- Log every access to recognition images (audit requirement from Pitfall 11).
- Do **not** put recognition images in a public disk. Ever.

**Warning signs:**
- `php artisan storage:link` exposes a `fras-recognition` symlink under `public/`.
- A recognition image is viewable from an incognito browser with no session.

**Phase to address:** Phase 20 (enrollment photos) + Phase 22 (recognition images).

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Skip Fortify passkey opt-out in Phase 17 and just pin Fortify version | Faster upgrade | When Fortify auto-upgrades later, passkey UI surface ships unexpectedly to operators with no UAT | Only if a Phase 17.5 ticket immediately follows to configure Fortify features explicitly |
| Hard-code severity→priority mapping in PHP | Ships faster in Phase 21 | Every client request to adjust ("P4 instead of P3 for refused") = code change + redeploy | Never — put in `config/fras.php` from day 1, the admin UI can come later |
| Treat all recognition events as IoT intake rows (no severity gate) | One codepath, simple | Pitfall 8 (queue flood, dispatcher desensitisation) | Never |
| Store scene images as base64 in DB | No file-disk config needed | Postgres bloat, backup size explodes, no CDN path | Never |
| Port FRAS Mapbox code as-is with a Mapbox token | Fastest Phase 20 | Two map SDKs in bundle, token cost, Mapbox vendor lock-in regressing Phase 4 decision | Never |
| Keep FRAS integer PKs alongside IRMS UUIDs | Fewer migration changes | Split-brain schema, FK contortions, Wayfinder type mismatches | Never |
| Skip audit log for image access in Phase 22 ("add it later") | Half-day saved | DPA non-compliance exposure, no incident forensics | Never for this deployment context |
| Single shared Supervisor program for MQTT + Horizon | One config file | Horizon restarts disrupt MQTT; MQTT crash doesn't auto-restart; logs interleaved | Never |
| Use `timestamp` (tz-naive) for `recognized_at` | Matches FRAS source | Off-by-8h bugs in Butuan; retention sweep misfires | Never |
| No confidence threshold gate (accept all) | No config needed | False positives flood intake | Acceptable only on dev; must be `>= 0.75` by staging |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| MQTT broker (Mosquitto) | Rely on broker persistence for QoS 0 messages | Add application-level heartbeat monitoring; log outage windows; document SLA |
| AI IP cameras (MQTT v3.1.1) | Assume firmware field names are stable (`personName` vs `persionName`) | Handle both spellings in RecPush handler; add a fixture test with both variants (FRAS Appendix C has the full list) |
| php-mqtt/laravel-client | Call `$client->loop()` once and expect it to reconnect | Wrap in outer loop with reconnect-on-exception + exponential backoff; use `--max-time` cutoff |
| Intervention Image | Use v3 API in a v4 context (FRAS v1.0 used v4) | Pick one version at Phase 19 — recommend v4 since FRAS Phase 3 validated it; pin explicitly |
| Laravel Reverb | Broadcast heavy payloads (image bytes, large arrays) inline | Broadcast IDs + URLs only; client HTTP-fetches details on demand |
| Existing IRMS IoT intake | Reuse the HMAC webhook endpoint for MQTT → intake (wrong layer) | RecPush handler calls the `IntakeService` directly (in-process), bypasses HTTP/HMAC entirely — it's internal trust |
| PostGIS + Magellan | Use `DB::raw('ST_Contains(...)')` everywhere | Use Magellan `Point` casts + model scopes; `BarangayLookupService` already exists, reuse |
| Horizon | Deploy without draining Horizon first (with new job payload format in L13) | `artisan horizon:terminate`, wait for idle, deploy, restart — see Pitfall 1 |
| Wayfinder | Assume route generation is automatic for new controllers | Run `npm run build` (or dev) after adding routes; commit generated actions per repo convention |
| Citizen Reporting SPA | Expose FRAS recognition data via public API | Citizen app has zero FRAS surface — firewall it at the route level |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Unbounded `useEcho` subscriber state | Tab memory grows over an 8-hour shift | Ring buffer cap (pattern already in `useDispatchFeed` at 20 entries) | >100 events retained per page |
| JSON column without JSONB | Event history filters >500ms | Use `jsonb` + GIN index | >5k recognition_events rows |
| Image retention with cascading FK blocks | Retention job deadlocks | Delete orphan images first, then expired events (reversed order of creation) | >10k events/day |
| RecPush handler does HTTP image download inline | Queue backs up during a burst | Queue the image-fetch as a separate job; handler just enqueues | >10 events/sec |
| No deduplication window | P1 audio fires 50x for one person walking past | Dedup key `(camera_id, personnel_id, trunc(created_at, 1min))` | First busy camera |
| Dispatcher subscribes to all FRAS channels | 100% tab CPU | Per-page channel discipline; dispatch console subscribes only to camera-status + critical alerts | 2+ cameras with busy scenes |
| Geocoding every camera on every list render | Slow admin page | Geocode once on create/edit, store; re-geocode only if address edited | 20+ cameras |
| No pagination on event history | Page load >3s | Server-side pagination + debounced filters (FRAS Phase 7 pattern) | >1k events |
| Synchronous enrollment sync in controller | HTTP request hangs on slow camera | Dispatch enrollment job, return 202 immediately | Any camera takes >2s to ACK |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Recognition image URLs unsigned / public | PII leak, DPA violation, NPC complaint | Auth-required signed URLs with 5-min expiry; role-gated (operator/supervisor/admin only) |
| Broadcast channel `fras.alerts` authorised for all authenticated users | Responder / dispatcher role sees biometric data in WebSocket frames | `Broadcast::channel('fras.alerts', fn($u) => in_array($u->role, ['operator','supervisor','admin']))` |
| No audit log for image/event access | Cannot answer "who viewed this person's recognition on X date" under DPA subject-access request | `fras_access_log` table: viewer_id, record_type, record_id, accessed_at, action — append on every image fetch |
| `migrate:fresh` allowed on staging/prod | Drops real user data, including citizen reports and incidents | Add environment guard in a custom command or AppServiceProvider boot |
| Camera enrollment photo URL is public forever | Personnel photos leak after employment ends | Make enrollment URL time-limited; revoke when sync_status = synced on all cameras |
| Personnel deletion doesn't cascade to camera_enrollments / recognition_events | Stale photos remain on cameras after HR removes employee | DeleteSync MQTT message fires on personnel delete; recognition_events anonymised (keep row, null PII fields) |
| MQTT broker on public internet without TLS | Anyone can publish fake recognition events | Broker bound to internal network only (matches FRAS Phase 1 constraint); firewall rule enforced |
| Camera `device_id` / MQTT topic is a guessable integer | Attacker on internal network can spoof camera events | Use UUID for device_id OR enforce mTLS on MQTT OR document the internal-trust model and firewall rule |
| HMAC secret shared across intake channels | If one leaks all leak | Separate secret for FRAS→intake internal bridge (unused — direct call recommended) vs. external IoT webhooks |
| Recognition image EXIF metadata retained | Camera firmware embeds model/serial/firmware version — info disclosure | Strip EXIF via Intervention Image on save |
| No Privacy Notice published to data subjects | DPA §16 violation (right to be informed) | Public privacy notice page linked from footers, signage text generator for camera locations |
| No Privacy Impact Assessment | NPC registration for high-risk processing is impossible | PIA template delivered in Phase 22 as part of the milestone |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Every recognition event fires the P1 audio tone | Alert fatigue within a day; real P1 emergencies muted | Only CRITICAL block-list events fire audio; WARNING has a softer tone or none; INFO silent |
| Alert feed shows raw camera IDs ("CAM-042") | Dispatchers can't geolocate mentally | Display `{camera.label} — {barangay.name}` always |
| Recognition alert card has no "who is this for" | Dispatcher unsure whether to dispatch or notify security | Alert card has explicit action chips: "Create Incident", "Notify Security", "Dismiss" |
| Alert detail modal shows face crop by default without consent banner | Dispatcher accidentally shows biometric data to someone looking over shoulder | Blur face crop by default; click-to-reveal with audit log entry |
| No indication that MQTT listener is down | Silent outage, dispatcher assumes zero events = quiet day | Persistent banner in dispatch/intake header when `mqtt_listener_health.last_message_at > 60s` ago |
| Camera marker on dispatch map uses same icon as unit | Visual confusion | Distinct icon (camera glyph) + distinct colour + legend entry |
| Retention cleanup happens silently | Dispatcher clicks an old alert → broken image, no explanation | UI message: "Image expired per retention policy on {date}"; link to policy |
| Block-list enrollment is an irreversible admin action with no confirmation | Accidental blocks of innocent personnel | Two-step: enroll as pending block → supervisor approves → sync fires |
| Dark mode implementation diverges (FRAS has its own) | Design rot, inconsistent UI | Sentinel design tokens only; port FRAS components onto IRMS tokens |
| No "last seen" context on personnel profile | Supervisor can't answer "when was X last here?" | Personnel detail page includes last-N recognition events timeline with filters |

## "Looks Done But Isn't" Checklist

- [ ] **Laravel 13 upgrade (Phase 17):** Often missing explicit regression tests for 6 v1.0 broadcast events — verify each fires with identical payload shape post-upgrade.
- [ ] **MQTT listener (Phase 19):** Often missing post-deploy restart hook — verify Supervisor reload is in the deploy script, not "someone remembers to run it."
- [ ] **Recognition → intake bridge (Phase 21):** Often missing the dedup window — verify two events within 60s of same (camera, person) produce only one incident.
- [ ] **Severity mapping (Phase 21):** Often missing dispatcher confirm-to-escalate — verify no codepath auto-creates P1 from a camera event without human action.
- [ ] **Retention (Phase 22):** Often missing the "skip active incidents" guard — verify a recognition event linked to an OPEN incident is NOT deleted at T+30d.
- [ ] **Privacy (Phase 22):** Often missing public Privacy Notice + signage template + PIA — verify routes exist (`/privacy`, `/fras/signage-template`) and PIA document is committed.
- [ ] **Audit log (Phase 22):** Often missing — verify every `GET /alerts/{id}/image` writes a `fras_access_log` row.
- [ ] **Channel auth (Phase 19/20):** Often missing role restriction on `fras.*` channels — verify responder and dispatcher roles return `false` from the auth callback.
- [ ] **Camera UI (Phase 20):** Often missing barangay auto-assign and human-readable label — verify alert feed shows no raw lat/lng.
- [ ] **Map library (Phase 20):** Often missing the Mapbox→MapLibre rewrite — verify `npm run build` output contains no `mapbox-gl` chunk.
- [ ] **JSONB columns (Phase 18):** Often missing GIN indexes — verify `EXPLAIN` on event-history filter uses the index.
- [ ] **Timestamps (Phase 18):** Often missing TIMESTAMPTZ — verify `recognized_at` column uses `timestamp with time zone`.
- [ ] **UUID PKs (Phase 18):** Often missing on FRAS tables — verify `cameras.id`, `personnel.id`, `recognition_events.id` are all UUID.
- [ ] **Enrollment mutex (Phase 20):** Often missing `expireAfter()` on `WithoutOverlapping` — verify a killed job releases the lock within 5 minutes.
- [ ] **MQTT listener health (Phase 19):** Often missing the last_message_at heartbeat row — verify it updates every 10s and alerts at 60s gap.
- [ ] **Photo URL scoping (Phase 20):** Often missing the "revoke public URL after sync" step — verify personnel with `sync_status=synced` on all cameras have no public URL surface.
- [ ] **Deploy rollback plan (Phase 17):** Often missing — verify there's a documented rollback for the L12→L13 upgrade that includes Horizon payload migration back to v12 format.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Horizon job format mismatch after L13 deploy | MEDIUM | `horizon:terminate`, drain queue manually via `queue:retry` after rollback, redeploy with drain protocol correctly |
| Recognition event flood (Pitfall 8) in prod | MEDIUM | Emergency: raise `fras.min_confidence` to 0.9 via config + deploy; long-term: enforce severity gate + dedup |
| DPA complaint / NPC inquiry after Phase 22 ships without audit log | HIGH | Implement audit log retroactively; cannot recover missing historical log; may require public disclosure |
| Retention sweep deleted active-incident images | HIGH | If backup exists, restore specific files; if not, incident closure requires "image unavailable" note — consult legal |
| Storage disk fills, Postgres stops | HIGH | Emergency: delete oldest scene images regardless of retention (log what was deleted); resize droplet; tune retention down |
| Channel auth bypass discovered (responder saw biometric data) | HIGH | Revoke Reverb auth tokens; patch channel auth; run audit of who subscribed when; DPA breach notification within 72h |
| Mapbox token exposed in committed code | LOW-MEDIUM | Rotate token in Mapbox; git history rewrite not strictly required if only rate-limit exposure; audit Mapbox billing |
| MySQL-style integer PK snuck into a FRAS table | MEDIUM | New migration: add UUID column, backfill, swap to PK, drop integer column — do not rewrite history |
| Mapbox library crept into bundle | LOW | Remove dep, rewrite affected components to MapLibre; bundle-size CI check prevents recurrence |
| JSON (not JSONB) column in production with data | MEDIUM | Migration: `ALTER TABLE … ALTER COLUMN metadata TYPE jsonb USING metadata::jsonb;` — verify on staging first |
| Timezone-naive timestamp with data | MEDIUM | Migration: add new `timestamptz`, backfill assuming original was UTC, drop old column — document interpretation choice |
| MQTT listener running stale code | LOW | `supervisorctl restart irms-mqtt:*` — but add this to deploy script afterwards |
| Block-list enrollment hit the wrong person | LOW-MEDIUM | DeleteSync MQTT fire, add audit entry, review approval flow — implement two-step approval (UX pitfall above) |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| 1. Bundled upgrade + feature work | Phase 17 | No FRAS code in Phase 17 PR; 6 broadcast-payload regression tests pass |
| 2. Inertia v2/v3 divergence | Phase 17 (decision) + 20/22 (enforcement) | Shim doc exists; TypeScript build passes; sidebar menu unchanged across roles |
| 3. JSON vs JSONB | Phase 18 | `\d recognition_events` shows `jsonb`; EXPLAIN shows GIN index use |
| 4. Integer PKs | Phase 18 | All FRAS tables: `id uuid PRIMARY KEY` |
| 5. Tz-naive timestamps | Phase 18 | `\d recognition_events` shows `timestamp with time zone`; round-trip test passes |
| 6. MQTT under Horizon supervisor | Phase 19 | Separate `supervisorctl status` entry for `irms-mqtt:*`; Horizon dashboard does not list it |
| 7. Stale MQTT code post-deploy | Phase 19 | Deploy script contains supervisor restart; post-deploy smoke publishes a test event |
| 8. Recognition event flood | Phase 21 | Load test: 50 events/sec for 60s, intake rows created ≤ configured severity threshold count |
| 9. Severity → P1 auto-escalation | Phase 21 | Unit test: WARNING never becomes P1; CRITICAL is P2 default; config-driven |
| 10. Retention deletes active-incident images | Phase 22 | Feature test: rec event attached to OPEN incident, advance clock 31 days, run retention, row+file still present |
| 11. DPA non-compliance | Phase 22 + legal sign-off | Privacy Notice route live; signage generator; audit log table exists; PIA committed |
| 12. `fras.alerts` channel too permissive | Phase 19/20 | Pest: responder and dispatcher roles return `false`; operator/supervisor/admin return `true` |
| 13. Reverb broadcast flood | Phase 19 + 22 | Throttle in RecPush handler; load test shows dispatch console p95 < 500ms under 20 msg/s |
| 14. useEcho memory leak | Phase 22 | Reusable `useBoundedFeed`; 1-hour soak test shows flat heap |
| 15. Missing PostGIS cast | Phase 18/20 | Camera model has Magellan Point cast; `ST_Contains` scope works |
| 16. Enrollment mutex missing expireAfter | Phase 20 | Feature test: kill job mid-run, assert lock releases within 5min |
| 17. QoS 0 message loss blindness | Phase 19 | `mqtt_listener_health` row updated every 10s; alert on 60s gap |
| 18. Topic wildcard mismatch | Phase 19 | TopicRouter Pest test with every topic pattern + off-by-one variants |
| 19. Fortify upgrade shared-prop regression | Phase 17 | Sidebar snapshot Pest test per role; passes before and after |
| 20. Mapbox dep sneaking in | Phase 20 | CI bundle check: no `mapbox-gl` chunk in `dist/` |
| 21. Design token drift | Phase 20/22 | CI grep: `--fras-` returns empty in `resources/js/` |
| 22. Migration collisions | Phase 18 | `migrate --pretend` clean; duplicate migrations deleted from port |
| 23. Raw lat/lng in UI | Phase 20 | No Vue component displays `camera.lat` / `camera.lng` as primary text |
| 24. v1.0 broadcast payload regression | Phase 17 | Payload-shape snapshot tests per event |
| 25. Photo URL signing contradiction | Phase 20 + 22 | Two URL namespaces documented; recognition image anon-access returns 401 |

## Sources

- [Laravel 12 to 13 Upgrade Guide: Zero Breaking Changes Doesn't Mean Zero Work (hafiz.dev, Mar 2026)](https://hafiz.dev/blog/laravel-12-to-13-upgrade-guide) — HIGH confidence; verified: removed deprecations, PHP 8.3 min, job payload format change
- [Laravel 13.x Upgrade Guide (official)](https://laravel.com/docs/13.x/upgrade) — HIGH confidence; authoritative
- [Laravel 13.x Release Notes (official)](https://laravel.com/docs/13.x/releases) — HIGH confidence; Fortify passkey integration in new installs
- [php-mqtt/laravel-client GitHub](https://github.com/php-mqtt/laravel-client) — HIGH confidence; Supervisor pattern, long-running command lifecycle
- [Avoiding Memory Leaks in Laravel Queue Workers (Diving Laravel)](https://divinglaravel.com/avoiding-memory-leaks-when-running-laravel-queue-workers) — HIGH confidence; `--max-time` + `--max-jobs` pattern applicable to any long-running PHP daemon
- [Why Laravel Queue Workers Die (pola5h, 2026)](https://pola5h.github.io/blog/laravel-queues-jobs-redis-horizon/) — MEDIUM confidence; corroborates Supervisor patterns
- [CCTV Privacy Compliance under Philippine DPA (Respicio & Co.)](https://www.respicio.ph/commentaries/cctv-privacy-compliance-under-philippine-data-privacy-act-1) — HIGH confidence; biometric processing as sensitive personal info, PIA requirement
- [Philippines Data Privacy Laws Compliance Guide (2026)](https://www.recordinglaw.com/world-laws/world-data-privacy-laws/philippines-data-privacy-laws/) — MEDIUM confidence
- [Privacy Rights and CCTV Surveillance (Philippines)](https://www.lawyer-philippines.com/articles/privacy-rights-and-cctv-surveillance) — MEDIUM confidence; LGU ordinance harmonisation with DPA
- [Republic Act 10173 (National Privacy Commission)](https://privacy.gov.ph/data-privacy-act/) — HIGH confidence; authoritative; data subject rights, NPC registration, breach notification
- FRAS source at `/Users/helderdene/fras` — `.planning/PROJECT.md`, `.planning/MILESTONES.md` (HIGH confidence; v1.0 validated patterns for MQTT, enrollment, retention)
- IRMS v1.0 decision log (`STATE.md` Accumulated Context) — HIGH confidence; JSONB precedent (vitals), role-gated channels (03-01), Magellan point casts (01-01), numeric priority convention (09-03)

---
*Pitfalls research for: FRAS integration into IRMS v2.0*
*Researched: 2026-04-21*
*Previous v1.0 pitfalls research superseded.*
