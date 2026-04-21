# Project Research Summary — v2.0 FRAS Integration

**Project:** IRMS v2.0 — FRAS Integration
**Domain:** Emergency dispatch CAD + MQTT face-recognition event ingestion (brownfield port into a shipped Laravel 12 / Vue 3 platform)
**Researched:** 2026-04-21
**Supersedes:** v1.0 SUMMARY (archived under `.planning/milestones/v1.0-phases/` references — consult STACK/FEATURES/ARCHITECTURE/PITFALLS for v2.0-specific findings)
**Confidence:** HIGH

## Executive Summary

v2.0 embeds HDSystem's shipped FRAS v1.0 capability set (MQTT IP-camera ingestion, personnel enrollment, recognition alerting) into the live IRMS CDRRMO dispatch platform while keeping FRAS running standalone at `/Users/helderdene/fras`. Roughly 80% of FRAS's service/handler/job layer is a direct PHP port, ~60% of the Vue page layer needs rework (Sentinel design-token remap, Mapbox GL JS → MapLibre GL JS rewrite), and ~100% of the IRMS integration surface (dispatch-map cameras layer, IoT-intake bridge, BOLO/DPA features) is net-new work. The load-bearing integration seam is a single `FrasIncidentFactory` service that bridges MQTT recognition events into IRMS's existing IoT intake channel — no new `IncidentChannel` enum value, no duplicated pipeline.

All four researchers independently surfaced the same forced ordering: the **Laravel 12 → 13 upgrade must be a dedicated, feature-free phase that lands before any FRAS code**. Bundling framework churn (`PreventRequestForgery` rename, `'serializable_classes' => false` default, Horizon/Fortify/Inertia version bumps, job payload serialisation changes) with simultaneous MQTT + MySQL→Postgres + new-feature work makes regression triage impossible and rollback catastrophic. The schema port (Phase 18) is similarly isolated: FRAS's MySQL `JSON` + `TINYINT(1)` + `DATETIME` + `AUTO_INCREMENT` choices must become PostgreSQL `JSONB` + `BOOLEAN` + `TIMESTAMPTZ` + aligned-with-IRMS PK type on port, with cameras migrated to Magellan `geography(POINT, 4326)` so PostGIS can answer "cameras near this incident" in one query.

The dominant risk profile is operational, not technical: (1) **severity → priority mapping** — only `AlertSeverity::CRITICAL` (block-list) may create IoT-channel incidents, and those default to **P2 not auto-P1**, because FRAS severity measures *who* while IRMS priority measures *response urgency*; (2) **DPA/RA 10173 exposure** — IRMS is a government system processing citizens' biometric data, so Phase 22 (milestone-completion gate) must ship a Privacy Notice route, role-gated signed recognition-image URLs, `fras_access_log` audit trail, PIA template, and CDRRMO legal sign-off; deferring any of these regresses the milestone; (3) **event flood / desensitisation** — recognition events can burst at 10+ events/sec/camera × 8 cameras, so severity gating + dedup windows + confidence thresholds + Reverb throttling are non-negotiable from day one. These three risks, with the Laravel upgrade, shape the phase ordering.

## Key Findings

### Recommended Stack

Add the two FRAS packages (`php-mqtt/laravel-client ^1.8`, `intervention/image-laravel ^4.0`) plus external Mosquitto 2.0.x + Supervisor 4.2.x; bump Laravel 12 → 13 with aligned Horizon/Reverb/Fortify/Wayfinder/Tinker versions. Keep everything else in IRMS v1.0 as-is: PostgreSQL + PostGIS via Magellan, **Inertia v2** (defer v3 to a separate milestone), **MapLibre GL JS** (explicitly reject `mapbox-gl`), Reverb, Horizon, Pest 4, `vite-plugin-pwa`. No net-new frontend package is required.

**Core additions:**
- `php-mqtt/laravel-client ^1.8.0` — v1.8.0 declares `illuminate/*: ^10|11|12|13`, so it works on both sides of the Laravel upgrade.
- `intervention/image-laravel ^4.0` — personnel photo resize to ≤1080p, ≤1 MB JPEG, MD5 hash. FRAS v1.0 shipped and validated v4.
- Mosquitto 2.0.x + Supervisor 4.2.x — single-Droplet deploy; cameras are LAN-only, no cloud-managed broker possible.
- Laravel 13 — zero-breaking-changes posture, but `PreventRequestForgery` rename, `serializable_classes` default, PHP 8.3 floor require attention.

### Expected Features

Five feature categories: (1) **camera management** — CRUD, heartbeat, offline detection, map pins on dispatch MapLibre; (2) **personnel/BOLO** — CRUD, photo preprocessing, per-camera enrollment sync with `WithoutOverlapping`, ACK correlation via cache, error-code translation; (3) **recognition pipeline** — MQTT listener + TopicRouter + 4 handlers, RecPush parsing (firmware quirks: `personName` vs `persionName`), base64 image save, severity classification, retention cleanup; (4) **alert feed + map overlays** — feed, filters, detail modal, acknowledge/dismiss, event history, dispatch-map recognition-pulse animation; (5) **recognition → IoT-intake bridge** — the whole point: only CRITICAL recognitions become Incidents, via `FrasIncidentFactory::createFromRecognition()` that reuses `channel=IoT` + `IncidentCreated`.

**Must have (table stakes):**
- Camera + Personnel CRUD + enrollment sync with per-camera mutex
- MQTT listener + RecognitionHandler + severity classification + raw-payload JSONB persistence
- Alert feed + acknowledge/dismiss + auth-gated image serving
- Cameras layer on dispatch MapLibre map (WebGL symbol layer, no HTML overlays)
- Recognition → IoT-intake bridge (Critical severity only, dedup-windowed)
- Laravel 12 → 13 upgrade + MySQL→Postgres schema port

**Should have (CDRRMO differentiators):**
- BOLO categories `missing`, `lost_child` with expiry + SMS on match
- Consent basis + `fras_access_log` audit + Privacy Notice (DPA)
- Responder SceneTab recognition-context accordion
- Manual promote-to-incident on non-Critical alerts
- IntakeStation FRAS rail integration

**Defer (post-v2.0):**
- Inertia v2 → v3 (its own milestone)
- Stranger-detection (Snap topic), bulk CSV import, video streaming, additional biometric modalities, behavioral-analytics events, multi-tenancy

### Architecture Approach

FRAS bolts on as a new ingress surface but reuses the existing `IncidentChannel::IoT` enum value. New `app/Mqtt/` directory (sibling to `app/Http/`) hosts the listener, TopicRouter, 4 handlers; new `app/Services/` gains `CameraEnrollmentService`, `FrasPhotoProcessor`, and the critical `FrasIncidentFactory`; new `app/Events/` gains 3 broadcast events on `fras.alerts` / `fras.cameras` / `fras.enrollments` private channels gated to operator/supervisor/admin only. New `useFrasFeed.ts` composable mirrors `useDispatchFeed.ts`. Dispatch MapLibre gains a cameras WebGL source + symbol layer. Processes: dedicated Supervisor program `irms-mqtt` for the listener (**separate from Horizon, never under it**), plus a new Horizon supervisor block for the `fras` queue isolating enrollment jobs.

**Major components:**
1. **`FrasIncidentFactory` (load-bearing integration seam)** — single service both `IoTWebhookController` and `RecognitionHandler` call. Factors the existing IoT controller's body into `createFromSensor()` + `createFromRecognition()`. Idempotent on `recognition_events.(camera_id, record_id)`. Only Critical creates Incidents.
2. **MQTT listener + TopicRouter + handlers** — `irms:mqtt-listen` artisan command; thin handlers persist + dispatch to queue, never broadcast inline; Supervisor-managed, `--max-time=3600` rotation.
3. **`CameraEnrollmentService` + `EnrollPersonnelBatch` job** — cache-backed ACK correlation; `WithoutOverlapping('enrollment-camera-{id}')->expireAfter(300)` mandatory per FRAS firmware constraint.
4. **`useFrasFeed` composable** — subscribes to 3 new channels, bounded ring buffer (100 alerts) to prevent useEcho memory leak, merges with Inertia-prop `localCameras` ref.
5. **Dispatch MapLibre cameras layer** — GeoJSON source + symbol layer + pulse animation driven by `RecognitionAlert` echoes. Reuses commit `ea52f22` custom dark style.

### Critical Pitfalls

The 25 domain pitfalls collapse into four structural risks:

1. **Bundling the Laravel upgrade with FRAS feature work** — Phase 17 must be feature-free. Pre/post upgrade Pest regression for all 6 v1.0 broadcast events. Drain Horizon before deploy (mixed-version workers corrupt queued payloads). Grep for removed deprecations. Pin Fortify features to avoid a surprise passkey surface.
2. **Schema port shortcuts** — `JSON` → `JSONB` (not `JSON`), `TINYINT(1)` → `BOOLEAN` (test raw `DB::select`, not just Eloquent casts), `DATETIME(6)` → `TIMESTAMPTZ(6)` (Butuan UTC+8; naive timestamps break retention), cameras as Magellan geography not decimal pair, delete duplicate migrations. **STACK and PITFALLS diverge on UUID vs `bigIncrements` PKs — REQUIREMENTS must resolve.**
3. **Severity → priority mapping** — Critical → P2 default (dispatcher-confirm to P1); Warning → P4 notification-only, never auto-Incident; Info never surfaces beyond history. Dedup `(camera_id, personnel_id, 60s)`. Confidence threshold `>= 0.75`. Rules in `config/fras.php`, not PHP.
4. **DPA gate on Phase 22** — Privacy Notice route, signage template, `fras_access_log` with append-on-every-image-view, role-gated signed recognition URLs (5-min, operator/supervisor/admin only — responders and dispatchers **excluded**), two-namespace photo URL scheme, configurable retention with active-incident-protection clause, PIA committed, CDRRMO legal sign-off blocks milestone completion.

## Implications for Roadmap

**Forced ordering (all 4 researchers agree):** `17 → 18 → {19 ∥ 20} → 21 → 22`. Phases 17 and 18 strictly sequential and feature-free. Phases 19 and 20 parallelise after 18. Phase 21 depends on both tracks converging. Phase 22 is the milestone gate with the DPA legal blocker.

### Phase 17: Laravel 12 → 13 Upgrade (feature-free, alone)
**Rationale:** Every downstream phase depends on framework churn being absorbed. Mixing upgrade with FRAS makes triage impossible.
**Delivers:** IRMS v1.0 green on Laravel 13 with zero behaviour change; 6 v1.0 broadcast events emit identical payloads; Horizon drain-and-deploy protocol documented; Inertia v2 pinned, Fortify features explicitly pinned.

### Phase 18: FRAS Schema Port to PostgreSQL (feature-free)
**Rationale:** Models + migrations are leaves of the dependency graph; unblock everything downstream.
**Delivers:** Empty `cameras` (Magellan geography), `personnel`, `camera_enrollments`, `recognition_events` with JSONB + GIN indexes + TIMESTAMPTZ + aligned PK type; factories + seeders; Pest switched to Postgres for FRAS test groups; `(camera_id, record_id)` unique for factory idempotency.

### Phase 19: MQTT Pipeline + Listener Infrastructure
**Rationale:** Listener + TopicRouter must land before feature code can accept events. Separate Supervisor program (not Horizon) is architectural.
**Delivers:** `irms:mqtt-listen` command, TopicRouter + 4 handlers, `config/mqtt-client.php` (subscriber + publisher connections), `composer run dev` 6th process, production `irms-mqtt.conf`, `mqtt_listener_health` heartbeat + 60s-gap banner, 3 Reverb channels with **explicit exclusion of responder + dispatcher from `fras.alerts`**, `ShouldDispatchAfterCommit` on all FRAS events.

### Phase 20: Camera + Personnel Admin + Dispatch Map Integration (parallel with 19)
**Rationale:** Admin surface touches different tables/controllers than the listener; parallelises cleanly.
**Delivers:** Admin CRUD reusing `AdminUnitController` pattern; MapLibre camera picker (rewritten from FRAS Mapbox); `CameraEnrollmentService` + `EnrollPersonnelBatch` with `WithoutOverlapping(...)->expireAfter(300)`; offline-detection watchdog; cameras as WebGL symbol layer (no HTML overlays, no `mapbox-gl` import — CI bundle check); camera `label` + geocoded `address` + PostGIS `barangay_id` (never raw lat/lng in UI); new gates extending the existing 9; Horizon `cameras` queue supervisor.

### Phase 21: Recognition → IoT-Intake Bridge
**Rationale:** Highest-risk phase for dispatcher alert-fatigue regression. Needs real Camera + Personnel + MQTT wiring in place.
**Delivers:** Factor `IoTWebhookController` body into `FrasIncidentFactory::createFromSensor()`; add `createFromRecognition()`; `FrasIncidentFactoryInterface` bound in `AppServiceProvider`; `recognition_events.incident_id` nullable FK; idempotency guard; **severity → priority in `config/fras.php` (Critical → P2 default, Warning → P4 notify-only, Info silent; P1 requires dispatcher action)**; dedup `(camera_id, personnel_id, 60s)`; confidence `>= 0.75` gate; `IncidentTimeline.event_data.source = 'fras_recognition'`; manual promote-to-incident action; Reverb throttle; load test 50 events/sec/camera.

### Phase 22: Alert Feed + Retention + Privacy/DPA (milestone-completion gate)
**Rationale:** Ships operator UI + retention + DPA compliance. Cannot ship without CDRRMO legal sign-off.
**Delivers:** Alert feed + event history with numbered pagination + debounced search + severity pills + camera filter; acknowledge/dismiss; audio via existing `useAlertSystem.ts` (shared Web Audio); recognition-pulse animation; BOLO `missing` + `lost_child` + expiry + auto-unenroll; `personnel.consent_basis` + `personnel.expires_at`; **`fras_access_log` with append-on-every-image-view**; retention cleanup with active-incident-protection + configurable; two-namespace photo URL (public unguessable UUID revoked post-sync + auth-signed 5-min for operators); **published `/privacy` route + signage-template generator + PIA committed + CDRRMO legal sign-off**; `useBoundedFeed<T>(max)` composable; 1-hour soak test.

### Research Flags

**Needs research (`/gsd-research-phase`):**
- **Phase 17:** Horizon 6 + Magellan L13-compat at upgrade time; Inertia v2 shim audit; Fortify feature-flag configuration.
- **Phase 21:** Severity-mapping field validation with CDRRMO dispatchers; dedup/confidence defaults; load-test methodology.
- **Phase 22:** NPC registration process, PIA format, retention-policy sign-off workflow with Butuan LGU data privacy officer (external, outside HDSystem expertise).

**Standard patterns (skip research):**
- **Phase 18** — type-mapping table in STACK.md is exhaustive, verified against L13 docs.
- **Phase 19** — FRAS v1.0 source is the validated reference; direct port.
- **Phase 20** — reuses IRMS `AdminUnitController` pattern verbatim.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Both codebases inspected; package versions verified on Packagist 2026-04-21; Laravel 13 cross-referenced with official docs + Context7 + hafiz.dev. Flag: Horizon 6 + Magellan L13-compat to re-verify at upgrade time. |
| Features | HIGH | FRAS v1.0 codebase authoritative for port; IRMS v1.0 composables/events authoritative for integration surface. |
| Architecture | HIGH | Both codebases inspected; patterns mirror IRMS conventions (Service + Contracts DI, Magellan, WebGL-only markers, ShouldDispatchAfterCommit). |
| Pitfalls | HIGH | 25 pitfalls mapped to phases with verification criteria; sources include L13 upgrade guide, NPC/RA 10173 primary source, FRAS v1.0 validated constraints. |

**Overall confidence:** HIGH

### Gaps — REQUIREMENTS.md Author Must Resolve

- **UUID vs `bigIncrements` primary keys.** STACK recommends `bigIncrements` (matches IRMS v1.0 `Unit`/`Barangay`); PITFALLS says UUIDs (decision 13-02 on Incident). Split-brain breaks FKs + Wayfinder types. **Decision required before Phase 18 migrations are written.**
- **Inertia v2 vs v3.** Confirm v2 (STACK + PITFALLS both recommend). FRAS pages port down to v2 at copy-time.
- **Severity → priority mapping defaults.** Proposed Critical→P2 / Warning→P4 / Info silent are research conclusions, not field-validated. Escalation-to-P1 UX (confirm button vs chip vs modal) undefined.
- **Dedup window + confidence threshold.** 60 s + 0.75 are starting points. Global, per-camera, or per-category? v2.0 ship defaults need a decision.
- **IntakeStation FRAS surface.** 4th rail, channel-monitor column, or separate page only?
- **Camera auto-ID vs device_id.** Follow v1.0 `AMB-01`/`FIRE-02` precedent with `CAM-01`, or use raw MQTT `device_id`?
- **Lost-child SMS broadcast.** Un-stub the SMS integration or defer to v2.1?
- **Retention policy numerics.** FRAS shipped 30d scenes / 90d faces; PITFALLS flagged aggressive for government. CDRRMO legal must approve; `config/fras.php` default needed before Phase 22.
- **Camera photo URL auth model.** Confirm two-namespace scheme (public unguessable UUID revoked post-sync + auth-signed for humans) — affects Phase 20 controller design.
- **CDRRMO legal / Butuan LGU data privacy officer engagement timeline.** Phase 22 cannot ship without sign-off; who owns this on the client side, and when does engagement start?

## Sources

### Primary (HIGH)
- FRAS v1.0 codebase at `/Users/helderdene/fras` — composer, PROJECT.md, MILESTONES.md, `app/Mqtt/*`, `app/Services/CameraEnrollmentService.php`, `app/Enums/AlertSeverity.php`, `app/Jobs/EnrollPersonnelBatch.php`, `config/hds.php`.
- IRMS v1.0 codebase at `/Users/helderdene/IRMS` — composer, PROJECT.md, CLAUDE.md, `IoTWebhookController.php`, `IncidentCreated.php`, `UserRole`/`IncidentChannel` enums, `useDispatchFeed`/`useDispatchMap`/`useAlertSystem`, `routes/channels.php`, `routes/web.php`.
- Context7 `/websites/laravel_13_x`, `/php-mqtt/laravel-client`, `/intervention/image-laravel`, `/websites/inertiajs_v3`.
- Packagist — `php-mqtt/laravel-client` v1.8.0 verified 2026-04-21.
- Laravel 13.x official docs (upgrade, filesystem, migrations, queues, Horizon).
- RA 10173 / National Privacy Commission — biometric-data legal basis + PIA + NPC registration.

### Secondary (MEDIUM)
- hafiz.dev Laravel 12→13 upgrade guide; pola5h.github.io L13 features.
- EMQX + manubes MQTT broker comparisons.
- Respicio & Co. CCTV Privacy Compliance under Philippine DPA; Lawyer-Philippines.com.
- Diving Laravel — Memory Leaks in Queue Workers.
- Laravel Cloud — Migrate MySQL to PostgreSQL knowledge base.

### Tertiary (LOW)
- codegive Laravel MQTT production patterns (corroborating only).
