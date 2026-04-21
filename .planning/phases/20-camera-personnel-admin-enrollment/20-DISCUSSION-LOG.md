# Phase 20: Camera + Personnel Admin + Enrollment - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-21
**Phase:** 20-camera-personnel-admin-enrollment
**Areas discussed:** Map migration scope, Admin UI + route conventions, Enrollment flow + photo lifecycle, Camera watchdog + BOLO expiry

---

## Gray area selection

| Option | Description | Selected |
|--------|-------------|----------|
| Map migration scope | mapbox-gl → maplibre-gl scope + tile source + geocoding replacement | ✓ |
| Admin UI + route conventions | IRMS vs FRAS page shape, enrollment progress UI location, list rendering | ✓ |
| Enrollment flow + photo lifecycle | Service port, ACK correlation, re-enroll triggers, photo URL revocation, processor pipeline, disk choice, filename | ✓ |
| Camera watchdog + BOLO expiry | Column name reconciliation, degraded thresholds, deletion rule, auto-unenroll cadence, custom_id format, channel auth, broadcast granularity | ✓ |

**User's choice:** All four areas selected for discussion.

---

## Map migration scope

### Migration reach

| Option | Description | Selected |
|--------|-------------|----------|
| Full swap now | Migrate all IRMS map code (useDispatchMap, useAnalyticsMap, LocationMapPicker, useGeocodingSearch, StandbyScreen, NavTab) + uninstall mapbox-gl | |
| FRAS picker only, defer rest | Add new MapLibre camera picker, keep mapbox-gl for existing v1.0 code, weaken SC3 CI check | |
| FRAS picker + dispatch map only | Migrate just useDispatchMap + build new picker in MapLibre, defer intake/analytics | |
| **[Other — Revert v2.0 decision, keep mapbox-gl]** | User clarified: keep mapbox-gl npm package installed; Phase 20 camera picker uses mapbox-gl; drop SC3 CI bundle-check; update REQUIREMENTS.md | ✓ |

**User's choice:** Revert the v2.0 milestone scope decision. Keep mapbox-gl across the IRMS codebase. Drop SC3 CI bundle-check. Amend REQUIREMENTS.md + ROADMAP.md text during planning.

**Notes:** Initial user answer ("use Mapbox to all") was ambiguous between "keep the mapbox-gl library" and "use Mapbox tiles with MapLibre rendering." After clarification, user confirmed the full reversion.

### Tile/basemap source (pre-clarification)

| Option | Description | Selected |
|--------|-------------|----------|
| Mapbox via MapLibre | Keep Mapbox Studio styles, use MapLibre renderer | |
| MapTiler | Commercial, free tier 100K req/mo, vector tiles + geocoding | |
| OpenFreeMap + Nominatim | Fully free/self-hostable | |
| Decide in research | Flag for gsd-phase-researcher | |
| **[Other — "we will be using mapbox"]** | Post-clarification: moot because reversion kept everything Mapbox | ✓ |

### Geocoding strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Keep Mapbox geocoding API via fetch | HTTP calls, no client SDK, same as LocationMapPicker.reverseGeocode | ✓ |
| Swap to MapTiler/Nominatim | Full vendor independence | |
| Photon (OSM) | Self-hostable, Butuan coverage varies | |

**User's choice:** Keep Mapbox geocoding API via fetch.

---

## Admin UI + route conventions

### Admin page structure

| Option | Description | Selected |
|--------|-------------|----------|
| Mirror IRMS Units pattern | Index.vue + Form.vue, 2 files per resource, Route::resource + recommission POST | ✓ |
| FRAS split (Index/Create/Edit/Show) | 4 Vue pages per resource, more code | |
| Hybrid | Cameras Units-style, Personnel with Show for enrollment progress | |

**User's choice:** Mirror IRMS Units pattern verbatim.

### Enrollment progress UI location

| Option | Description | Selected |
|--------|-------------|----------|
| Inline panel on Personnel edit/show page | Echo-subscribed to fras.enrollments | ✓ |
| Dedicated /admin/enrollments dashboard | Fleet-wide observability, extra nav item | |
| Both | Inline panel + dashboard | |

**User's choice:** Inline panel on Personnel edit page.

### List rendering at CDRRMO scale

| Option | Description | Selected |
|--------|-------------|----------|
| Table for both, matches Units.vue | Tabular, filters, search header | ✓ |
| Cards for cameras, table for personnel | Card grid with mini-map thumbnail per camera | |
| Table + mini-map sidebar on cameras page | Single-page view, map secondary | |

**User's choice:** Table for both resources.

---

## Enrollment flow + photo lifecycle

### CameraEnrollmentService shape

| Option | Description | Selected |
|--------|-------------|----------|
| Port FRAS service verbatim + IRMS tweaks | UUID FKs + config/fras.* + broadcast + fras queue | ✓ |
| Port + extract messageId builder | Separate MessageIdGenerator service for reuse | |
| Rewrite as event-driven (Personnel observed) | Auto-enqueue via observer, removes explicit service calls | |

**User's choice:** Port FRAS service verbatim with IRMS tweaks.

### ACK correlation mechanism

| Option | Description | Selected |
|--------|-------------|----------|
| Redis cache request-ID → enrollment-row | TTL-bound, matches FRAS pattern exactly | ✓ |
| Database request_id column + table | Durable, survives Redis flush, breaks Phase 18 freeze | |

**User's choice:** Redis cache correlation.

### Re-enrollment trigger

| Option | Description | Selected |
|--------|-------------|----------|
| Only photo change or category change | wasChanged(['photo_hash', 'category']) gate | ✓ |
| Any edit triggers re-enroll | Simpler, higher MQTT traffic | |
| Claude's discretion | Planner picks based on FRAS inspection | |

**User's choice:** Only photo_hash or category changes trigger re-enroll.

### Photo URL revocation mechanism

| Option | Description | Selected |
|--------|-------------|----------|
| Route-level check: enrollment still pending | Controller verifies pending/syncing exists; rotates token on re-upload | ✓ |
| Filesystem move on ACK (public → private) | I/O-based, risk of torn state under crash | |
| Short-TTL signed URL, no explicit revocation | 10-min TTL, weaker DPA posture | |

**User's choice:** Route-level enrollment-state check; token rotation on re-upload.

### FrasPhotoProcessor pipeline

| Option | Description | Selected |
|--------|-------------|----------|
| Port FRAS verbatim with v4 API adjustments | decode→orient→scaleDown→encode→quality-degradation-loop→hash | ✓ |
| Port + fail fast (no quality loop) | Reject oversized upload; force operator to re-upload | |
| Port + WebP encoding | Smaller files; FRAS firmware rejects WebP | |

**User's choice:** Port FRAS verbatim with Intervention v4 API.

### Photo storage disk

| Option | Description | Selected |
|--------|-------------|----------|
| New 'fras_photos' private disk | Mirrors Phase 19 fras_events pattern, clean retention boundary | ✓ |
| Reuse Phase 19 fras_events disk | One disk, messier retention semantics | |
| Laravel default public disk | Matches FRAS, weaker DPA posture | |

**User's choice:** New `fras_photos` private disk.

### Photo filename strategy

| Option | Description | Selected |
|--------|-------------|----------|
| UUID filename + separate token column | Stored as personnel/{uuid}.jpg, photo_access_token column for URL rotation | ✓ |
| Random UUID filename, path is the token | FRAS-style, no extra column, revocation requires filesystem move | |

**User's choice:** UUID filename + separate `photo_access_token` column. Requires Phase 20 migration (crosses Phase 18 freeze — flagged in CONTEXT.md D-20).

---

## Camera watchdog + BOLO expiry

### Column name reconciliation

| Option | Description | Selected |
|--------|-------------|----------|
| Keep `last_seen_at` | Matches Phase 18 D-10; update SC5 text during planning | ✓ |
| Rename to `last_heartbeat_at` | Matches FRAS + SC5, requires migration | |

**User's choice:** Keep `last_seen_at`; amend SC5 text in planning (D-43).

### Degraded-state threshold

| Option | Description | Selected |
|--------|-------------|----------|
| gap > 30s AND gap ≤ 90s | Online/degraded/offline at 30/90 boundaries | ✓ |
| Skip degraded for now | Only write online/offline, defer degraded to later phase | |
| Configurable in config/fras.php | Same 30/90 defaults, tunable | |

**User's choice:** gap > 30s AND gap ≤ 90s → degraded (thresholds made configurable in D-39 during writeup).

### Camera deletion rule

| Option | Description | Selected |
|--------|-------------|----------|
| App-level check + 422 response | Controller blocks before decommission, FK CASCADE remains unreachable | ✓ |
| App-level check + cascade banned | App check + ON DELETE CASCADE → RESTRICT, changes Phase 18 D-22 | |
| Decommission-only, no hard-delete | No delete button in UI; Phase 22 handles hard-delete | |

**User's choice:** App-level check + 422 response. Hard-delete UI path dropped (D-29).

### BOLO auto-unenroll cadence + action

| Option | Description | Selected |
|--------|-------------|----------|
| Hourly schedule + unenroll-from-cameras-only | Personnel row preserved, Expired badge in admin list | ✓ |
| Daily schedule, same action | Less Horizon traffic, up to 24h lag | |
| Hourly + hard-delete personnel row | Stronger DPA, loses audit trail | |

**User's choice:** Hourly schedule + unenroll-only (preserve personnel row).

### Personnel custom_id format

| Option | Description | Selected |
|--------|-------------|----------|
| Personnel UUID truncated to 32 chars | Deterministic, globally unique, fits varchar(48) | ✓ |
| CDRRMO prefix + sequence (CDRRMO-00001) | Human-readable, requires regex MAX+1 lookup | |
| Random 12-char alphanumeric | Shorter payload, unguessable | |

**User's choice:** Personnel UUID without dashes → 32-char hex.

### Broadcast channel authorization

| Option | Description | Selected |
|--------|-------------|----------|
| fras.cameras → operator+dispatcher+supervisor+admin; fras.enrollments → supervisor+admin | Matches dispatch.units auth pattern; enrollments admin-only | ✓ |
| Both on fras.admin — admin only | Tighter, dispatchers lose live camera status | |
| Both on dispatch.incidents (reuse) | No new channels, pollutes existing channel | |

**User's choice:** Two-channel split with role-appropriate auth.

### EnrollmentProgressed broadcast granularity

| Option | Description | Selected |
|--------|-------------|----------|
| Per-row state change | ~1,600 events per full resync at CDRRMO scale, within Reverb throttle | ✓ |
| Debounced batching (500ms aggregate) | Fewer WS frames, extra complexity | |
| Poll-instead-of-broadcast | 2s polling, simpler backend, less live | |

**User's choice:** Per-row state change.

---

## Claude's Discretion

- Camera marker SVG shape + color tokens (online/degraded/offline)
- Form field ordering + sections in CameraForm.vue / PersonnelForm.vue
- Retry attempt count for transient ACK errors (default 3)
- Batch size for pre-existing-personnel sync to newly added camera
- 422 error copy on blocked camera deletion
- EnrollmentProgressPanel component location (components/fras/ vs components/admin/)
- Whether PersonnelExpireSweep sweeps all cameras per-command-run or batches by camera

## Deferred Ideas

(Captured in CONTEXT.md `<deferred>` section — 14 items ranging from full MapLibre migration to hard-delete DPA rules to marker clustering.)
