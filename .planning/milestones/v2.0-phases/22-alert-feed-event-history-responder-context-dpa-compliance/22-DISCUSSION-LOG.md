# Phase 22: Alert Feed + Event History + Responder Context + DPA Compliance - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in 22-CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-22
**Phase:** 22-alert-feed-event-history-responder-context-dpa-compliance
**Areas discussed:** Alert feed + ACK/Dismiss + audio; Event history + replay + promote; DPA audit + signed URLs + retention; Privacy + SceneTab + gates + DPA docs
**Mode:** interactive (recommended defaults surfaced for each question)

---

## Area Selection

| Option | Description | Selected |
|--------|-------------|----------|
| Alert feed + ACK/Dismiss + audio | /fras/alerts architecture, ACK/dismiss storage + scope, dismiss vs ack semantics, ACK state broadcast-back, audio cue trigger rules, 100-alert ring buffer | ✓ |
| Event history + replay + promote | /fras/events filter UX, debounce, replay badge definition, pagination, promote-to-Incident action | ✓ |
| DPA audit + signed URLs + retention | fras_access_log schema + write path, signed-URL scope, retention purge command, active-incident-protection, config tunables | ✓ |
| Privacy + SceneTab + gates + docs | /privacy route + content, SceneTab Person-of-Interest accordion, 5 new gates + operator view-only semantics, DPA docs package format | ✓ |

**User's choice:** All 4 areas selected.

---

## Alert feed + ACK/Dismiss + audio

### Q: When one operator acknowledges a FRAS alert, should it clear for everyone or just them?

| Option | Description | Selected |
|--------|-------------|----------|
| Global ACK — one clear for all | Broadcast on ACK; all operators' feeds update. Shared queue model. (Recommended) | ✓ |
| Per-user ACK | Each operator has their own state; parallel shifts only. | |
| Hybrid (ACK global, Dismiss per-user) | Two-tier semantics; more state to manage. | |

**User's choice:** Global ACK — one clear for all
**Notes:** Matches CDRRMO single-shift collaborative operator model. Rationale carried into D-01.

### Q: Where should ACK/Dismiss state live?

| Option | Description | Selected |
|--------|-------------|----------|
| Columns on recognition_events | `acknowledged_at`, `acknowledged_by_user_id`, `dismissed_at`, `dismissed_by_user_id`, `dismiss_reason`. Single source, queryable. (Recommended) | ✓ |
| New fras_alert_acknowledgments table | Separate audit table; needs joins. | |
| Cache-only (ephemeral) | Redis-only; lies to history page; rejected for DPA. | |

**User's choice:** Columns on recognition_events
**Notes:** Aligns with Phase 18 "bridge state on recognition_events" convention (matches Phase 21 D-07 step 5 `incident_id` pattern). Captured in D-03 with six columns (added `dismiss_reason_note` for "other" reason free-text).

### Q: Are Acknowledge and Dismiss different actions, or just one "clear" with optional reason?

| Option | Description | Selected |
|--------|-------------|----------|
| Two distinct actions | ACK positive, Dismiss negative with required reason enum. (Recommended) | ✓ |
| Unified clear with reason | One action + dropdown; loses quick-ACK. | |
| ACK only | No dismiss; doesn't honor SC1. | |

**User's choice:** Two distinct actions
**Notes:** ROADMAP SC1 explicitly lists "acknowledge/dismiss" — treating as distinct semantics is spec-faithful. D-02 captures FrasDismissReason enum with values: false_match, test_event, duplicate, other.

### Q: When should the Critical audio cue play, and what's the mute control?

| Option | Description | Selected |
|--------|-------------|----------|
| Plays only on /fras/alerts, per-user mute | Scoped audio; persisted mute column; tab-visibility gated. (Recommended) | ✓ |
| App-wide for operators, session mute | Louder coverage; noisy for intake work. | |
| /fras/alerts + dispatch console, per-workstation mute | Balanced coverage; localStorage mute. | |

**User's choice:** Plays only on /fras/alerts, per-user mute
**Notes:** Scoped audio footprint. D-05 adds `document.visibilityState === 'visible'` gate. D-06 persists mute via `users.fras_audio_muted` boolean column (follows operator across workstations).

---

## Event history + replay + promote-to-Incident

### Q: When should the "replay badge" fire on an event row in /fras/events?

| Option | Description | Selected |
|--------|-------------|----------|
| Same (camera, personnel) in last 24h | Shift-based rolling window; "N× today". (Recommended) | ✓ |
| Same personnel across any camera, last 7d | Broader scope; useful for missing-person movement. | |
| Same personnel lifetime | Too broad; loses meaning. | |

**User's choice:** Same (camera, personnel) in last 24h
**Notes:** Matches operator shift-based mental model. D-11 ignores events with `personnel_id IS NULL`; badge renders only when N ≥ 2.

### Q: How should /fras/events filters behave in the URL?

| Option | Description | Selected |
|--------|-------------|----------|
| URL-driven (shareable, back-button-safe) | Query params; Inertia router.get with preserveState/replace. (Recommended) | ✓ |
| Local state only | Cleaner URLs; loses shareability. | |
| Stored per-user preset | localStorage recall; overkill for forensic search. | |

**User's choice:** URL-driven (shareable, back-button-safe)
**Notes:** Captured in D-08/D-09. 300ms debounce on `q`, replace:true for debounced, replace:false for severity/camera/date changes.

### Q: Page size and free-text search backend?

| Option | Description | Selected |
|--------|-------------|----------|
| 25/page, PostgreSQL ILIKE | Simple, no new indexes; CDRRMO volume fits. (Recommended) | ✓ |
| 50/page, tsvector GIN | Future-proof; more infrastructure. | |
| 20/page, pg_trgm fuzzy | Typo-tolerant; not justified. | |

**User's choice:** 25/page, PostgreSQL ILIKE on name+camera_label
**Notes:** D-10 captures full query shape: ILIKE on `personnel.name` + `camera.camera_id_display` + `camera.name`. Upgrade path to tsvector/pg_trgm deferred.

### Q: Promote-to-Incident action from event-detail modal: who can promote and how?

| Option | Description | Selected |
|--------|-------------|----------|
| Operator+, priority picker, reuses factory override path | Priority picker (P1-P4) + reason textarea; `createFromRecognitionManual`. (Recommended) | ✓ |
| Supervisor+ only, auto-priority P2 | Tighter control; leaves operator unable to react. | |
| Operator+, no picker (severity → priority map) | Fast action; less control. | |

**User's choice:** Operator+, priority picker, reuses factory override path
**Notes:** D-12/D-13 capture form contract (reason min 8 / max 500 chars) + new factory method skipping severity+confidence+dedup gates but keeping category gate. Audit: `event_data.trigger = 'fras_operator_promote'`.

---

## DPA audit + signed URLs + retention

### Q: What fields should `fras_access_log` capture?

| Option | Description | Selected |
|--------|-------------|----------|
| Polymorphic (subject_type + subject_id) + actor/ip/ua/ts | Single table covers face/scene/personnel photos; compliance-export ready. (Recommended) | ✓ |
| Narrow recognition_event_id FK only | Simpler; misses personnel photos. | |
| Append raw route + params | Flexible; compliance-export painful. | |

**User's choice:** Polymorphic (subject_type + subject_id) + actor/ip/ua/ts
**Notes:** D-15 captures full schema + two supporting enums (FrasAccessSubject, FrasAccessAction). Indexes: `(subject_type, subject_id)` + `(actor_user_id, accessed_at)`.

### Q: How should the log write happen — inline or async?

| Option | Description | Selected |
|--------|-------------|----------|
| Sync write in controller before stream | DB::transaction; fail-closed audit. (Recommended) | ✓ |
| Dispatched event + queued listener | Faster; risks missed rows under queue failure. | |
| Sync via observer on StreamedResponse | More magic; same cost, less control. | |

**User's choice:** Sync write in controller before stream
**Notes:** D-16 captures transaction wrap + ~5ms cost acceptance. DPA-grade "audit on every view" guarantee requires unconditional sync path.

### Q: Retention purge command design — how does it run?

| Option | Description | Selected |
|--------|-------------|----------|
| `fras:purge-expired` scheduled daily 02:00, dry-run flag | Standard Laravel scheduler; dry-run for legal pre-verify. (Recommended) | ✓ |
| Hourly job, hard-delete rows after 90d | Too aggressive; breaks history page. | |
| Manual admin button only | Fails DPA-04 "scheduled"; rejected. | |

**User's choice:** `fras:purge-expired` scheduled daily 02:00, dry-run flag
**Notes:** D-20 through D-24 capture full shape: daily 02:00 Asia/Manila, `--dry-run` + `--verbose` flags, file delete + column NULL (keep row), `fras_purge_runs` summary table, `withoutOverlapping()` + failure logging.

### Q: How should "active-incident-protection" be defined?

| Option | Description | Selected |
|--------|-------------|----------|
| Protect if linked Incident.status != Resolved/Cancelled | Natural close signal; images survive investigation. (Recommended) | ✓ |
| Protect for N days after incident close | Grace-period tail; config tension. | |
| Protect if linked to ANY incident ever | Too broad; contradicts baseline retention. | |

**User's choice:** Protect if linked Incident.status != Resolved/Cancelled
**Notes:** D-22 captures the EXISTS subquery. SC5 test: seed expired event + status=Dispatched Incident → purge skips.

---

## Privacy + SceneTab + gates + DPA docs

### Q: How should the `/privacy` route be built?

| Option | Description | Selected |
|--------|-------------|----------|
| Public Inertia Vue page, content in .md → compiled | Git-tracked Markdown; language toggle; Inertia-consistent. (Recommended) | ✓ |
| Public Blade view, hardcoded | No build step; no localization. | |
| DB-driven CMS | Overkill; scope creep. | |

**User's choice:** Public Inertia Vue page, content in .md → compiled
**Notes:** D-30/D-31/D-32 capture controller + Markdown compile path + `?lang=en|tl` switching. English + Filipino sibling files git-tracked. DPO contact block left with placeholder for post-deploy edit.

### Q: Where and how does the Person-of-Interest accordion render on the responder view?

| Option | Description | Selected |
|--------|-------------|----------|
| New accordion block in responder/Station.vue SceneTab, collapsed by default | Conditional render; face-only; collapsed-default. (Recommended) | ✓ |
| New dedicated "Person of Interest" tab | More discoverable; tab-bar real estate costly on mobile. | |
| Inline above SceneTab, always expanded | More immediate; may clutter. | |

**User's choice:** New accordion block in responder/Station.vue SceneTab section, collapsed by default
**Notes:** D-25/D-26 capture conditional render (`event_data.source === 'fras_recognition'`) + three-layer scene-image exclusion enforcement.

### Q: How should the 5 new gates be defined + what does "operator view-only" mean?

| Option | Description | Selected |
|--------|-------------|----------|
| Defined in AppServiceProvider; operator = read-only ACK allowed | Five Gate::define() calls; three-layer enforcement. (Recommended) | ✓ |
| Policy classes + Gates | Inconsistent with v1.0 Gate-only convention. | |
| Operator view-only = no ACK | Conflicts with SC1 "one-click acknowledge/dismiss". | |

**User's choice:** Defined in AppServiceProvider alongside v1.0 gates; operator = read-only ACK allowed
**Notes:** D-27/D-28/D-29 capture all 5 gate definitions + role matrices + three-layer enforcement (frontend hide / backend authorize / route middleware). "View-only" interpreted as "no camera/personnel/enrollment-retry management".

### Q: Format for the DPA docs package in `docs/dpa/`?

| Option | Description | Selected |
|--------|-------------|----------|
| Markdown-first, PDF generated on demand | dompdf (existing dep) + Blade template; git-friendly. (Recommended) | ✓ |
| PDF-first, Word-authored | Binary versioning pain. | |
| Static HTML + signage UI | Mini-feature scope creep. | |

**User's choice:** Markdown-first, PDF generated on demand
**Notes:** D-33/D-34 capture three Markdown files (PIA/signage/operator-training) + `fras:dpa:export` CLI with `--doc` + `--lang` flags + shared Blade template + `storage/app/dpa-exports/{yyyy-mm-dd}/` output directory.

---

## Final check

### Q: We've covered 4 areas. Ready to write CONTEXT.md or explore more?

| Option | Description | Selected |
|--------|-------------|----------|
| Write CONTEXT.md now | 16 decisions captured; recommended defaults; remaining details planner-scope. (Recommended) | ✓ |
| One more: CDRRMO legal sign-off mechanics | UI + CLI + table design. | |
| One more: Milestone-close ceremony | Auto-archive trigger. | |
| Revisit an earlier area | — | |

**User's choice:** Write CONTEXT.md now
**Notes:** Legal sign-off mechanics captured as D-38 + Deferred; milestone-close ceremony captured in Deferred.

---

## Claude's Discretion

- Vue component hierarchy + colocation (pages/fras/ vs components/fras/)
- Tailwind/Reka UI class sets for feed cards + history table + accordion
- Scene-image "Image purged" placeholder treatment
- Accordion primitive selection (Reka UI vs custom)
- Markdown parser selection (`league/commonmark` vs `erusev/parsedown`)
- `PublicLayout` design for `/privacy` (new layout vs AuthLayout prop mode)
- PIA template section wording polish
- DPO contact block content vs placeholder
- Replay-badge SQL strategy (window function vs group-by hydration)
- Test file naming + coverage strategy across 8 new Pest feature tests
- `/fras/settings/audio-mute` shape (Fortify scope vs bespoke)
- ACK/Dismiss analytics exposure

## Deferred Ideas

- Sign-off UI/CLI/PDF-cert design details
- Milestone-close ceremony mechanics
- Translation beyond English + Filipino (Cebuano, etc.)
- CMS-editable Privacy Notice
- DPA audit dashboard UI
- Expanded `fras_access_log` for non-image actions
- Retention auto-adjustment by incident outcome
- ACK ownership analytics
- Replay badge multi-camera triangulation UI
- Promote with "attach to existing Incident"
- Signage PDF with QR code to /privacy
- PII-aware search logging
- Dispatch console FRAS summary widget
- Per-camera retention overrides
