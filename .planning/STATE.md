---
gsd_state_version: 1.0
milestone: v2.0
milestone_name: FRAS Integration
status: ready_to_plan
stopped_at: Completed 21-04
last_updated: "2026-04-22T04:46:49.338Z"
last_activity: 2026-04-22 -- Completed Phase 21 Plan 05 (FRAS UI components + human-verify APPROVED)
progress:
  total_phases: 6
  completed_phases: 6
  total_plans: 29
  completed_plans: 29
  percent: 100
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-21)

**Core value:** Dispatchers can receive an incident report, triage it, assign the nearest available unit, and track the response in real-time on a live map.
**Current focus:** Phase 21 COMPLETE — FRAS recognition bridge + dispatch map + IntakeStation 6th rail + human-verify APPROVED

## Current Position

Phase: 22
Plan: Not started
Status: Ready to plan
Last activity: 2026-04-22

Progress: [██████████] 100%

## v2.0 Phase Breakdown

| Phase | Name | Requirements | Status |
|-------|------|--------------|--------|
| 17 | Laravel 12 → 13 Upgrade | FRAMEWORK-01, 02, 03 | Not started |
| 18 | FRAS Schema Port to PostgreSQL | FRAMEWORK-04, 05, 06 | Not started |
| 19 | MQTT Pipeline + Listener Infrastructure | MQTT-01..06 | Not started |
| 20 | Camera + Personnel Admin + Enrollment | CAMERA-01..06, PERSONNEL-01..07 | Not started |
| 21 | Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail | RECOGNITION-01..08, INTEGRATION-01, 03, 04 | Not started |
| 22 | Alert Feed + Event History + Responder Context + DPA Compliance | ALERTS-01..07, INTEGRATION-02, DPA-01..07 | Not started (milestone gate) |

**Ordering (locked):** 17 → 18 → {19 ∥ 20} → 21 → 22. Phases 19 and 20 parallelise after 18. Phase 22 blocks on CDRRMO legal sign-off.

## Performance Metrics

**Velocity:**

- Total plans completed: 51
- Average duration: 14min
- Total execution time: 5.3 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 1. Foundation | 3/3 | 54min | 18min |
| 2. Intake | 3/3 | 48min | 16min |
| 3. Real-Time | 2/2 | 34min | 17min |
| 4. Dispatch Console | 4/4 | 37min | 9min |
| 8. Operator & Intake UI | 4/4 | 70min | 18min |
| 9. Citizen Reporting App | 3/3 | 57min | 19min |
| 15 | 2 | - | - |
| 16 | 3 | - | - |
| 17 | 4 | - | - |
| 18 | 6 | - | - |
| 20 | 8 | - | - |
| 21 | 5 | - | - |

**Recent Trend:**

- Last 5 plans: 09-02 (5min), 09-03 (45min), 05-01 (TDD), 05-02 (6min), 05-03 (5min)
- Trend: Normal

*Updated after each plan completion*
| Phase 08 P01 | 11min | 2 tasks | 27 files |
| Phase 08 P02 | 7min | 2 tasks | 23 files |
| Phase 08 P03 | 7min | 2 tasks | 8 files |
| Phase 08 P04 | 45min | 3 tasks | 20 files |
| Phase 04 P01 | 7min | 2 tasks | 26 files |
| Phase 04 P02 | 7min | 2 tasks | 10 files |
| Phase 04 P03 | 8min | 2 tasks | 12 files |
| Phase 04 P04 | 15min | 2 tasks | 13 files |
| Phase 09 P01 | 10min | 3 tasks | 19 files |
| Phase 09 P02 | 5min | 2 tasks | 33 files |
| Phase 09 P03 | 45min | 3 tasks | 24 files |
| Phase 05 P02 | 6min | 2 tasks | 9 files |
| Phase 05 P03 | 5min | 2 tasks | 7 files |
| Phase 05 P04 | 9min | 2 tasks | 8 files |
| Phase 05 P01 | 8min | 2 tasks | 32 files |
| Phase 06 P01 | 4min | 2 tasks | 11 files |
| Phase 06 P03 | 5min | 1 tasks | 11 files |
| Phase 06 P02 | 5min | 1 tasks | 8 files |
| Phase 07 P01 | 9min | 2 tasks | 17 files |
| Phase 07 P03 | 8min | 2 tasks | 15 files |
| Phase 10 P01 | 4min | 2 tasks | 8 files |
| Phase 10 P02 | 3min | 2 tasks | 10 files |
| Phase 10 P03 | 8min | 2 tasks | 4 files |
| Phase 10 P04 | 9min | 3 tasks | 10 files |
| Phase 10 P05 | 2min | 2 tasks | 5 files |
| Phase 11 P01 | 5min | 2 tasks | 14 files |
| Phase 11 P02 | 4min | 2 tasks | 2 files |
| Phase 12 P01 | 5min | 2 tasks | 9 files |
| Phase 12 P02 | 6min | 2 tasks | 11 files |
| Phase 12 P03 | 4min | 2 tasks | 3 files |
| Phase 12 P04 | 2min | 2 tasks | 2 files |
| Phase 13 P01 | 6min | 2 tasks | 12 files |
| Phase 13 P02 | 6min | 2 tasks | 18 files |
| Phase 13 P03 | 6min | 2 tasks | 8 files |
| Phase 14 P01 | 3min | 2 tasks | 5 files |
| Phase 14 P03 | 7min | 2 tasks | 21 files |
| Phase 14 P02 | 8min | 2 tasks | 25 files |
| Phase 16 P16-01 | 9min | 3 tasks | 3 files |
| Phase 16 P16-02 | 2 | 2 tasks | 1 files |
| Phase 16 P16-03 | 21min | 3 tasks | 2 files |
| Phase 17 P01 | 5min | 2 tasks | 12 files |
| Phase 17 P02 | 18min | 2 tasks | 37 files |
| Phase 17 P03 | 7min | 3 tasks | 1 files |
| Phase 18 P01 | 3min | 2 tasks | 4 files |
| Phase 18 P02 | 9min | 2 tasks | 4 files |
| Phase Phase 18 PP03 | 3min | 2 tasks tasks | 4 files files |
| Phase Phase 18 PP04 | 4min | 2 tasks tasks | 4 files files |
| Phase 18 P05 | 5min | 2 tasks | 3 files |
| Phase Phase 18 PP06 | 3min | 2 tasks tasks | 2 files files |
| Phase 20 P04 | 6min | 2 tasks | 7 files |
| Phase 20 P05 | 7min | 3 tasks | 16 files |
| Phase 20 P06 | 22min | 2 tasks | 7 files |
| Phase 20 P07 | 18min | 2 tasks | 8 files |
| Phase 20 P08 | 30min | 2 tasks tasks | 6 files files |
| Phase 21 P21-01 | 13min | 2 tasks | 10 files |
| Phase 21 P02 | 3 | 2 tasks | 4 files |
| Phase 21 P03 | 18min | 2 tasks | 6 files |
| Phase 21 P04 | 7min | 2 tasks | 9 files |
| Phase 21 P05 | 21min | 3 tasks | 8 files |

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

**v2.0 roadmap-level decisions (2026-04-21):**

- [17-02]: Wave 2 RESCOPED (2026-04-21) to absorb all aligned package bumps in a single atomic composer update. Original D-04 split framework+tinker (Commit 2) from aligned packages (Commit 3), but composer's resolver cannot land laravel/framework ^13.0 cleanly without simultaneously bumping magellan, dompdf, horizon, reverb, fortify, wayfinder, inertia-laravel, boost, pest, tinker together. Single commit preserves D-05 intent (explicit package list, no --with-all-dependencies) while acknowledging composer reality. Wave 3 is now narrowly scoped to Wayfinder TS regen + runbook + final verification.
- [17-02]: SC1 reinterpreted as family-classification gate rather than absolute-count gate. L12 baseline (50 failures) is a single sample from a non-deterministic distribution (fake()->unique() drift in IncidentCategoryFactory). L13 5-run distribution is 41-59 failures, fully overlapping baseline within variance. Zero new root-cause families (only Family A: incident_categories_name_unique, Family B: users_pkey) confirms no L13 regression introduced.
- [17-02]: nunomaduro/collision added to explicit composer update list as targeted transitive bump (pest ^4.6 requires collision ^8.9.3; was locked at v8.9.1). Collision's own root constraint ^8.6 already permits 8.9.3 so this is not scope expansion — it satisfies resolver without requiring -W (preserves D-05).
- [Roadmap v2.0]: 6 phases (17-22) with forced ordering 17 → 18 → {19 ∥ 20} → 21 → 22 — all 4 research agents aligned
- [Roadmap v2.0]: Phase 17 is feature-free Laravel 12 → 13 upgrade alone — bundling framework churn with FRAS features makes regression triage impossible
- [Roadmap v2.0]: Phase 18 is feature-free schema port alone — MySQL → Postgres type mapping (JSONB, TIMESTAMPTZ, Magellan geography) is leaf dependency for all downstream phases
- [Roadmap v2.0]: Phases 19 (MQTT infrastructure) and 20 (Camera/Personnel admin) parallelise after Phase 18 — different tables, different controllers, no shared seam
- [Roadmap v2.0]: ALERTS + DPA kept together in Phase 22 (not split) — access-log / signed-URL / retention surface is the same code path the alert feed queries, splitting would fragment the legal-sign-off gate
- [Roadmap v2.0]: Severity → priority mapping Critical→P2 default (one-click dispatcher escalation to P1), Warning→P4 notify-only, Info→history-only — all thresholds in `config/fras.php`
- [Roadmap v2.0]: MQTT listener under dedicated `irms-mqtt` Supervisor program, never under Horizon — Horizon restart must not interrupt camera ingestion
- [Roadmap v2.0]: UUID PKs on all 4 new FRAS tables (matches IRMS Incident/Message precedent); `mapbox-gl` rejected, CI bundle-check enforces MapLibre-only; Inertia v2 retained (v3 deferred to separate milestone)

**v1.0 phase-level decisions carried forward** (prior entries preserved below for continuity):

- [Roadmap]: 2D dispatch map (no pitch, no terrain) — simplifies Phase 4 MapLibre setup
- [Roadmap]: 7 phases following strict dependency chain — Foundation > Intake > Real-Time > Dispatch > Responder > Integration > Analytics
- [Roadmap]: Real-Time Infrastructure isolated as Phase 3 — WebSocket channel auth and reconnection strategy validated before dispatch console
- [01-01]: All tests on PostgreSQL (no SQLite split) for consistent behavior
- [01-01]: clickbar/laravel-magellan for PostGIS model casts instead of raw SQL
- [01-01]: Custom role enum + middleware + Gates instead of Spatie (4 fixed roles)
- [01-01]: Unit uses string primary key (AMB-01 style) for dispatch readability
- [01-03]: Unified /messages route for all 4 roles instead of separate per-role routes
- [01-03]: Removed index signature from User type for explicit TypeScript typing
- [01-03]: Computed Record<UserRole, NavItem[]> for per-role sidebar navigation
- [01-02]: Admin routes registered via withRouting(then:) callback -- keeps admin routes isolated from web.php
- [01-02]: IncidentType destroy soft-disables instead of deleting -- preserves foreign key references from incidents
- [01-02]: Barangay boundary column excluded from both select (performance) and validated input (security)
- [01-02]: Vue forms use useForm + Wayfinder actions instead of Inertia Form component -- matches existing settings pattern
- [02-01]: Service layer pattern: Contracts/ for interfaces, Services/ for implementations, bound in AppServiceProvider::register()
- [02-01]: Raw SQL for PostGIS ST_Contains in BarangayLookupService (proven Phase 1 pattern, Magellan ST::contains() unverified for geography columns)
- [02-01]: Multi-level priority escalation via floor(adjustment/threshold) -- extreme keyword combinations can jump multiple levels
- [02-01]: Unit tests extend TestCase in Pest.php for Laravel config/facade access
- [02-03]: SmsParserService as standalone service (not SmsServiceInterface) for single-responsibility keyword classification
- [02-03]: Webhook routes at top of routes/web.php before auth group with per-route CSRF exclusion
- [02-03]: Location extraction uses regex for Filipino (sa, dito sa) and English (at, near) prepositions
- [02-02]: Reka UI Combobox wrappers follow existing Shadcn-vue ui/ pattern for consistency
- [02-02]: Manual debounce + AbortController in composables instead of adding VueUse dependency
- [02-02]: Deferred props via HandleInertiaRequests for role-gated dashboard channel counts
- [02-02]: incident_created timeline entry with rich event_data for full audit trail on creation
- [03-01]: phpunit.xml uses BROADCAST_CONNECTION=reverb with test credentials for channel auth validation
- [03-01]: Magellan Point uses getLatitude()/getLongitude() methods not property access
- [03-01]: Existing intake tests use Event::fake([IncidentCreated]) to prevent broadcast errors in test env
- [03-01]: Presence channel returns user id, name, role for dispatch console user awareness
- [03-02]: Switched from phpredis to predis (pure PHP) -- avoids system extension requirement for local dev
- [03-02]: Echo useEcho event names without dot prefix -- @laravel/echo-vue auto-prepends namespace
- [03-02]: Reactive local copies of Inertia props for WebSocket mutation without full page reload
- [03-02]: ChannelMonitor realtime prop for self-subscribing mode (Dashboard) vs parent-managed mode (Queue)
- [08-02]: CSS custom properties with @theme inline indirection for design tokens -- dark mode via .dark selector
- [08-02]: color-mix() for opacity tints instead of rgba() -- works cleanly with CSS variable colors
- [08-02]: Separate script block for ChBadge exports -- Vue ESLint prohibits exports in script setup
- [08-02]: Dispatcher/responder roles mapped to operator display in intake context
- [Phase 08]: [08-01]: Custom Fortify LoginResponse binding for role-based operator redirect to /intake
- [Phase 08]: [08-01]: Intake routes use role:operator,supervisor,admin middleware separate from dispatcher routes
- [Phase 08]: [08-01]: Manual entry storeAndTriage creates dual timeline entries (created + triaged) for full audit trail
- [Phase 08]: [08-03]: useIntakeFeed manages pending/triaged in separate reactive arrays with WebSocket-driven mutations
- [Phase 08]: [08-03]: Dual-path triage form: same component routes to triage() or storeAndTriage() via Wayfinder actions
- [Phase 08]: [08-03]: Feed capped at 100 incidents to prevent memory growth in long operator sessions
- [Phase 08]: [08-03]: IntakePriorityPicker built as standalone grid with color-mix() backgrounds and suggestion labels
- [Phase 08]: [08-04]: Override and recall endpoints use existing gate infrastructure with timeline entries and WebSocket broadcast
- [Phase 08]: [08-04]: QueueRow inline priority picker expands on click without modal -- speed-optimized for ops context
- [Phase 08]: [08-04]: Queue.vue switched from PENDING to TRIAGED status filter to complete intake-to-dispatch handoff
- [Phase 08]: [08-04]: Session log hydrated from server-side timeline entries on page load for continuity across refreshes
- [04-01]: Forward-only status transitions enforced via explicit allowedTransitions map in DispatchConsoleController
- [04-01]: incident_unit pivot with unassigned_at null filter for active-only BelongsToMany queries
- [04-01]: Route renamed from dispatch.index to dispatch.console for semantic clarity
- [04-01]: ETA calculated at 30km/h urban speed for Butuan City context
- [Phase 04]: Forward-only status transitions enforced via allowedTransitions map
- [Phase 04]: incident_unit pivot with unassigned_at null filter for active-only queries
- [Phase 04]: Route renamed dispatch.index to dispatch.console for semantic clarity
- [04-02]: MapLibre Map type aliased as MaplibreMap to avoid native JS Map collision
- [04-02]: DispatchLayout uses provide/inject (matching IntakeLayout) since Inertia defineOptions layout does not receive page props
- [04-02]: Console.vue manages panel layout directly as flex siblings
- [04-02]: useDispatchMap stores GeoJSON in closure for style-switch re-application
- [04-03]: Local reactive copies of Inertia props with useDispatchSession for client-side metric computation
- [04-03]: averageHandleTime initialized from server value then recomputed client-side on incident resolution
- [04-03]: useAckTimer uses @vueuse/core useIntervalFn for automatic cleanup on unmount
- [04-03]: StatusPipeline maps TRIAGED to REPORTED display label (dispatch context)
- [04-03]: IncidentDetailPanel fetches nearby units via direct fetch() to Wayfinder URL (GET JSON endpoint)
- [04-04]: useDispatchFeed as single composable hub consuming all 5 broadcast events and mutating local reactive state
- [04-04]: Ticker events capped at 20 entries in ring buffer to prevent memory growth in long dispatch sessions
- [04-04]: MutualAidModal filters agencies by incident_type match for type-based suggestions with star highlight
- [04-04]: State-sync on WebSocket reconnection replaces full localIncidents and localUnits arrays from server
- [04-04]: Console.vue uses local reactive copies of Inertia props so WebSocket mutations are reflected immediately
- [09-01]: 30-char unambiguous alphabet (no O/I/L/0/1) for citizen tracking tokens
- [09-01]: incidentTypes() uses orWhere code=OTHER_EMERGENCY instead of scopePublic alone to always include catch-all type
- [09-01]: Citizen description stored in notes field for consistency with existing Incident model
- [09-01]: Rate limiters defined in AppServiceProvider::configureRateLimiters() following existing boot pattern
- [09-01]: API versioning: /api/v1/citizen/* route group with dedicated controller namespace Api\V1
- [09-02]: System-aware dark mode via prefers-color-scheme media query (not .dark class selector) for citizen app
- [09-02]: Module-scoped refs in useReportDraft for shared state across 3-step report flow without Pinia
- [09-02]: Vite dev server on port 5174 with proxy to irms.test for development API calls
- [09-03]: useReportDraft composable as sole state-sharing mechanism across report flow views (no route state or query params)
- [09-03]: GPS auto-detect on mount with manual barangay SearchableSelect fallback when denied
- [09-03]: Category-specific SVG icons in TypeCard for visual incident type identification
- [09-03]: SearchableSelect component with filter input replaces native select for barangay field
- [09-03]: Numeric priority values in API resources instead of string format (2 not P2)
- [09-03]: IncidentCreated broadcast includes caller_name, caller_contact, notes, incident_type_id for intake feed
- [05-02]: Provide/inject bridge pattern for ResponderLayout-Station.vue communication (Inertia layouts don't receive props or emit to children)
- [05-02]: GPS broadcast URL hardcoded as /responder/update-location pending Plan 01 backend route creation
- [05-02]: Separate ack timer in Station.vue using useIntervalFn (different API shape from dispatch useAckTimer)
- [05-02]: Event callback refs (onAdvance, onShowOutcomeSheet) for bidirectional layout-page event handling
- [Phase 05]: [05-01]: Task 2 (checklist_data migration) merged into Task 1 due to blocking dependency
- [Phase 05]: [05-01]: Responder advance-status excludes RESOLVED -- must use dedicated resolve endpoint with outcome
- [Phase 05]: [05-01]: Medical outcomes require vitals recorded before resolution via IncidentOutcome.isMedical() gate
- [Phase 05]: [05-01]: Backward-compatible route aliases kept for assignment.index and my-incidents.index
- [05-03]: Grid-template-rows accordion for smooth CSS-only expand/collapse without hardcoded max-height
- [05-03]: Fire-and-forget fetch() for checklist/tag toggles -- instant local state update, PATCH in background, revert on failure
- [05-03]: Checklist template selection via incident_type.code/category string matching (4 hardcoded templates for v1)
- [05-04]: Inline ack timer in AssignmentNotification using useIntervalFn (dispatch useAckTimer has different API shape)
- [05-04]: HospitalSelect as dedicated responder component rather than generic SearchableSelect
- [05-04]: Status advance POST via direct fetch to Wayfinder action URLs for non-blocking fire-and-forget
- [05-04]: ClosureSummary uses fixed overlay z-50 for full-screen takeover regardless of tab state
- [Phase 06]: StubMapboxDirectionsService uses Haversine at 30km/h urban speed matching existing nearbyUnits logic
- [Phase 06]: DispatchConsoleController wraps DirectionsServiceInterface::route() in try/catch with straight-line fallback
- [Phase 06]: config/integrations.php centralizes all 7 connector configs with simulate_errors flags defaulting to false
- [Phase 06]: [06-03]: SimpleXMLElement for NDRRMC SitRep XML -- native PHP, no external dependency
- [Phase 06]: [06-03]: BFP priority-to-alarm mapping: P1->5, P2->4, P3->3, P4->2, P5->1
- [Phase 06]: [06-03]: PNP 5W1H defaults: 'Unknown suspect' for who, 'Under investigation' for why
- [Phase 06]: FHIR Bundle uses urn:uuid: references for Patient-to-Encounter-to-Observation linkage
- [Phase 06]: LOINC codes for vitals: BP (85354-9), HR (8867-4), SpO2 (2708-6), GCS (9269-2)
- [Phase 06]: Hospital names resolved from config/hospitals.php dynamically, not hardcoded in stub
- [Phase 06]: Observation resources only emitted for non-null vitals to keep FHIR payload sparse
- [Phase 07]: PostgreSQL aggregation (EXTRACT EPOCH, DATE_TRUNC) for KPI computation instead of PHP loops
- [Phase 07]: Cache::rememberForever for barangay boundary GeoJSON (static data)
- [Phase 07]: Gate::authorize in controller constructor for analytics access control
- [Phase 07]: [07-03]: league/csv with SplTempFileObject for in-memory CSV generation
- [Phase 07]: [07-03]: P1 hook in ResponderController::resolve() dispatches GenerateNdrrmcSitRep after GenerateIncidentReport
- [Phase 07]: [07-03]: Timeline entry 'ndrrmc_sitrep_generated' for NDRRMC SitRep audit trail
- [Phase 07]: [07-03]: AnalyticsController::generateReport uses match expression for type-safe job dispatch
- [Phase 10]: [10-01]: One-direction CSS variable cascade: Shadcn vars reference --t-* tokens, never reverse
- [Phase 10]: [10-01]: DS-03 focus ring targets [data-slot] selector for Reka UI/Shadcn components
- [Phase 10]: [10-01]: Auth layout consolidated to single self-contained CDRRMO-branded card layout
- [Phase 10]: [10-02]: Inline SVG shield in AppLogo (consistent with AuthLayout, no AppLogoIcon import needed)
- [Phase 10]: [10-02]: Settings form content wrapped in card elevation container for visual consistency with dashboard
- [Phase 10]: [10-02]: DeleteUser red-* colors kept as-is (semantically meaningful danger styling not decorative)
- [Phase 10]: [10-03]: Status badges mapped to design system tokens: PENDING->t-p3, TRIAGED->t-accent, DISPATCHED->t-unit-dispatched, RESOLVED->t-online
- [Phase 10]: [10-04]: Chart.js colors kept as hardcoded hex (already match design system palette per research)
- [Phase 10]: [10-04]: Token-only alignment for dispatch/responder: color/font swaps in panel chrome, no layout or UX changes
- [Phase 10]: [10-05]: decoration-muted-foreground replaces neutral-300/dark:neutral-500 pair (CSS cascade handles dark mode)
- [Phase 10]: [10-05]: ReportRow type badges mapped: quarterly->t-accent, annual->t-role-supervisor, dilg->t-online, ndrrmc->t-p2
- [Phase 10]: [10-05]: PrioritySelector uses bg-t-p1..p4 active and color-mix() 40%/8% inactive pattern

- [11-01]: Auto-generated unit IDs use PostgreSQL regex SUBSTRING/CAST to extract max sequence from existing units of same type
- [11-01]: Decommission lifecycle (decommissioned_at timestamp) kept separate from operational status for clean domain separation
- [11-01]: scopeActive() pattern: whereNull('decommissioned_at') for excluding soft-disabled records without soft deletes
- [11-01]: Bidirectional crew sync via two-step User.unit_id update instead of pivot table
- [11-02]: Crew multi-select uses Reka UI Combobox with inline content position and manual toggleCrew for array management
- [11-02]: Agency selector uses preset dropdown (CDRRMO/BFP/PNP) with Other option revealing free-text input
- [11-02]: Decommissioned badge uses t-unit-offline token for visual consistency with offline status

- [12-01]: PrivateChannel for incident messages (not PresenceChannel) -- simpler auth, no online-user tracking needed per-channel
- [12-01]: Dispatcher senderUnitCallsign is null -- dispatchers operate without unit assignment
- [12-01]: broadcastWith includes messageId as 'id' for frontend deduplication and optimistic UI matching
- [12-02]: Reactive Map replacement (new Map(old)) instead of in-place mutation for Vue reactivity on unreadByIncident and messagesByIncident
- [12-02]: Messages are session-local: start empty, accumulate via WebSocket during session (no lazy-load from backend)
- [12-02]: Optimistic local push on send: message appears immediately via addLocalMessage, POST fires in background
- [12-03]: Manual watch + echo().private() for dynamic channel subscription -- useEcho deps only re-binds callbacks, not channel name
- [12-03]: Skip unread increment for own messages (sender_id === userId) to avoid self-notification
- [12-03]: Initial subscribe on composable setup if activeIncident already set (handles page reload with active incident)
- [12-04]: 11px header size matches TIMELINE section pattern for visual consistency
- [12-04]: 100px bottom padding rounds up from 96px StatusButton height for comfortable spacing
- [13-01]: sw.ts excluded from main tsconfig.json and ESLint -- vite-plugin-pwa compiles service workers independently with webworker lib
- [13-01]: PWA icons use dark navy (#0B1120) background matching design system brand color
- [13-01]: ReloadPrompt mounted as render array sibling to Inertia App for global availability without layout modifications
- [13-02]: Incident ID typed as string (not int) in CheckAckTimeout job since Incident model uses HasUuids
- [13-02]: Form Request classes for push subscription validation per project conventions (not inline validation)
- [13-02]: configureEventListeners() helper method in AppServiceProvider following existing boot pattern
- [13-03]: X-XSRF-TOKEN cookie pattern (matching project convention) instead of meta[name=csrf-token] for fetch CSRF in push composable
- [13-03]: VAPID test credentials added to phpunit.xml for test environment validation (matching Reverb test credential pattern)
- [13-03]: applicationServerKey uses .buffer as ArrayBuffer cast for TypeScript strict mode compatibility
- [Phase 14]: [14-01]: Channel tokens (--t-ch-sms, voice, iot) kept unchanged; --t-ch-app and --t-ch-walkin updated to Sentinel equivalents
- [Phase 14]: [14-01]: Report-app dark brand uses #378ADD (Signal Blue) matching pattern where dark brand is lighter for visibility
- [Phase 14]: [14-03]: Sentinel dark bg #05101E replaces #0f172a in all hardcoded dark mode overrides
- [Phase 14]: [14-03]: Badge style unification: PriBadge and ChBadge both use 15% bg / 40% border color-mix pattern
- [Phase 14]: [14-03]: StatusButton ACKNOWLEDGED uses same blue (#378ADD) as DISPATCHED for visual flow continuity
- [Phase 14]: CDRRMO kept as agency name in UnitForm presets and seeders -- real org name, not branding
- [Phase 14]: PWA icons generated via ImageMagick convert with SVG templates on #042C53 background
- [Phase 16]: [16-01]: D-07 literal-vs-skill resolved via named imports (SKILL.md:39 tree-shaking preference + useGpsTracking.ts structural analog)
- [Phase 16]: [16-01]: Pest convention guard in tests/Unit/Conventions/ uses Symfony Finder to scan resources/js/** (excluding Wayfinder-generated dirs)
- [Phase 16]: [16-02]: OP-10 marked Complete (not Partial) per 16-PATTERNS.md §4 status exception - gap resolved in commit 66b8a52 per 08-VERIFICATION frontmatter
- [Phase 16]: [16-02]: Traceability rows grouped by prefix (15 OP-* then 6 REBRAND-*) appended after MOBILE-02 per D-05, preserving phase-ordered reading inside the table
- [Phase 16]: [16-02]: Last-updated line rewritten to credit Phase 16 backfill while preserving Phase 15 gap-closure context (RSPDR-06 and RSPDR-10) inside a parenthetical so both audits read in a single line
- [Phase 16]: [16-03]: D-13 conflict resolved — used 'audited: 2026-04-17' (Phase 13 literal key) over CONTEXT.md's 'approved:' wording for precedent fidelity across VALIDATION files
- [Phase 16]: [16-03]: Human-verify handoff pattern — Task 2 (executor) prepares scaffold + checklist + .gitkeep; Task 3 (user offline) captures 6 screenshots + flips frontmatter + commits atomically; finalization agent records outcome in SUMMARY
- [17-01]: Carbon::setTestNow(fixed instant) beats freezeTime() for byte-identical fixtures — freezeTime pins to test-start Carbon::now() which still drifts run-to-run
- [17-01]: Fixture convention tests/Feature/Broadcasting/__snapshots__/{EventName}.json (Jest-style) with first-run write-through + markTestIncomplete, second-run byte-compare via expect->toBe(file_get_contents)
- [17-01]: 44 pre-existing full-suite failures on main exist prior to Wave 1 (UniqueConstraintViolationException in Feature tests) — Wave 2 needs diagnostic pass before L13 bump to distinguish new failures from baseline noise
- Phase 17 complete: FRAMEWORK-01/02/03 all met. Runbook shipped at docs/operations/laravel-13-upgrade.md; final regression gate clean (6/6 broadcast snapshots byte-identical; full suite failures confined to Family A/B baseline). Next: HUMAN-UAT dispatch flow spot-check per D-12.
- [18-01]: Migration filename 2026_04_21_000001_ reserves sequential slots 000002-000004 for personnel/camera_enrollments/recognition_events migrations in Plans 18-02..04
- [18-01]: CHECK constraint naming convention {table}_{column}_check established via explicit DB::statement — first DB-level CHECK in v1.0 schema; will be repeated in Plans 18-02..04
- [18-01]: FRAS enums use lowercase backed values (online/offline/degraded) — departure from Incident-family SCREAMING_CASE; matches DB CHECK literals and establishes convention for PersonnelCategory, CameraEnrollmentStatus, RecognitionSeverity
- [18-01]: Camera model omits toArray() {lat,lng} override per D-66 minimalist scope — Phase 20 can add when admin JSON responses need it
- [18-02]: protected $table = 'personnel' set explicitly on Personnel model — guards against Laravel pluralizer ambiguity ('personnel' is already plural); future inflection changes will not silently repoint
- [18-02]: PersonnelFactory.gender emits randomElement([0, 1, null]) — covers M/F/unknown trichotomy without presuming a gender-coding schema; Phase 20 UI will map 0/1/null to labels
- [18-02]: Shared Pattern B (raw DB CHECK constraint) confirmed stable on second application — personnel.category mirrors cameras.status idiom verbatim; Plans 18-03 and 18-04 can reuse
- [18-03]: Explicit constrained('personnel') table argument on foreignUuid FK — guards against Laravel pluralizer ambiguity (personnel is already plural); the Personnel model's $table override alone is insufficient at migration compile time
- [18-03]: CameraEnrollment extends Model (not Pivot) — row has its own UUID PK + timestamps; Pivot's composite-PK semantics are wrong for row-has-id pivots
- [18-03]: Shared Pattern B (raw DB::statement CHECK) confirmed stable on a third application (camera_enrollments.status); ready for verbatim reuse on recognition_events.severity in plan 18-04
- [18-04]: decimal(5,2) over float for similarity (D-40) — fixed-point 0.00–100.00 prevents float rounding drift in Phase 19 dedup comparisons; 'decimal:2' cast round-trips to 2-digit-scale string preserved through JSON
- [18-04]: foreignId(acknowledged_by)->constrained('users') coexists with foreignUuid FKs on recognition_events (D-50) — users.id is v1.0 bigint; Laravel Blueprint accepts mixed FK types in one migration without conflict
- [18-04]: GIN + jsonb_path_ops opclass via raw DB::statement for raw_payload (D-48) — 30% smaller than default jsonb_ops, same @> containment coverage; Blueprint does not expose opclass so raw DDL is the clean path
- [18-04]: TIMESTAMPTZ(6) microsecond precision reserved for recognition_events.captured_at/received_at — FRAS cameras emit multi-events-per-second on busy intakes; second precision would lose ordering under burst load
- [18-04]: RecognitionEventFactory preserves BOTH personName + persionName firmware typo in raw_payload (D-61) — Phase 19 handler parser must accept either spelling; factory output is test scaffolding for parser fallback coverage
- [18-05]: pest()->group('fras') introduced as first Pest group tag in repo — enables ./vendor/bin/pest --group=fras subsystem-scoped runs without phpunit.xml edits (D-58)
- [18-05]: FrasPlaceholderSeeder body left empty AND unregistered in DatabaseSeeder per D-62 — migrate:fresh --seed stays production-safe while SC4 'every table has a seeder' wording satisfied by file presence alone
- [18-05]: CameraSpatialQueryTest uses standalone Butuan plaza lat/lng literals instead of Incident FK reference — decouples spatial proof from Incident-family schema drift, avoids IncidentFactory's FK chain, keeps test to pure ST_DWithin semantics
- [18-05]: FRAMEWORK-05 verification is inspection-only (grep ^DB_CONNECTION=pgsql .env.testing) — .env.testing was already pgsql-configured pre-phase-18, so FRAMEWORK-05 is satisfied without reconfiguration
- [18-06]: extractCheckValues() helper uses regex /'([^']+)'/ on pg_get_constraintdef output — shape-agnostic across both Postgres CHECK output dialects (IN (...) and (col)::text = ANY (ARRAY[...::text])); one helper works across dialect rewrites without re-tokenization
- [18-06]: ILIKE (not LIKE) for all pg_indexes.indexdef string matches — PostGIS emits USING gist / USING GIST inconsistently across versions; case-insensitive matches survive extension upgrades without test-suite churn
- [18-06]: EnumCheckParityTest placed under tests/Feature/Fras/ (not Unit/Conventions/) — live pg_constraint introspection is Feature-shaped; placement inherits RefreshDatabase + pgsql binding automatically and colocates with fras-group tests
- [18-06]: Sorted-array equality (collect()->sort()->values()->all()) for enum-vs-CHECK parity — one expression, symmetric, fails loudly on either-side drift without two-direction array_diff comparison
- [20-03]: EnrollPersonnelBatch `$queue = 'fras'` set via constructor assignment instead of typed property — Queueable trait pre-declares `public $queue` (untyped) and a typed redeclaration causes a trait-composition fatal at class load; constructor assignment preserves the queue contract without the trait conflict
- [20-03]: AppServiceProvider observer registration uses `configureObservers()` helper method following existing configureDefaults/Gates/RateLimiters/EventListeners pattern — keeps boot() a single-responsibility dispatcher
- [20-03]: CameraEnrollmentService declared `final` (FRAS port norm) blocks Mockery doubling — EnrollPersonnelBatch delegation test uses MQTT::shouldReceive side-effect observation (topic + payload substring + row transition) instead of method-level mock; equivalent coverage without subclass workaround
- [20-03]: AckHandler warn-log tests use explicit `Mockery::spy(LoggerInterface::class)` bound via `Log::shouldReceive('channel')->andReturn($spy)` instead of `Log::spy()` — `Log::spy()`'s channel() returns null which fatals before assertion reach
- [20-03]: Cache::pull atomicity verified under ArrayStore (Laravel's .env.testing default) — idempotency test proves duplicate ACK delivery produces exactly 1 transition under array driver; production Redis gets true LUA atomicity automatically
- [20-04]: Additive migration 2026_04_22_000002 closes Phase 18 gap (barangay_id FK + notes + location_label nullable) without rewriting schema-freeze file — preserves D-20 schema-freeze contract while letting AdminCameraController.store persist BarangayLookupService results
- [20-04]: Placeholder admin/Cameras.vue + admin/CameraForm.vue shipped as 9-line stubs so Pest Inertia ::render assertions pass Vite manifest lookup — Plan 07 wholly replaces with Mapbox picker implementation; same pattern as AdminUnitTest which depends on pre-existing admin/Units.vue
- [20-04]: enrollAllToCamera test asserts Queue::assertNotPushed (not assertPushed) because CameraEnrollmentService gates on CameraStatus::Online and new cameras ship with status=offline — first heartbeat (Plan 06 watchdog) triggers first sync; controller→service handshake is what this test covers, not enrollment fan-out
- [20-05]: routes/fras.php houses admin.personnel.photo via a second withRouting->then group — shares prefix('admin') + name('admin.') with the admin.php group but broadens the role gate from role:admin to role:operator,supervisor,admin (D-22). URL surface `/admin/personnel/{id}/photo` + name `admin.personnel.photo` stable; only the middleware chain differs.
- [20-05]: Token rotation happens inside AdminPersonnelController (Str::uuid()->toString() in both store and update photo paths) — Form Request whitelists explicitly omit photo_access_token, blocking mass-assignment (T-20-05-01).
- [20-05]: update() guards delete() with `$oldPath !== $result['photo_path']` — FrasPhotoProcessor writes deterministically to `personnel/{id}.jpg` so old+new paths are typically identical on replace; unconditional delete would wipe the just-written file.
- [20-05]: destroy() calls `$service->deleteFromAllCameras($personnel)` explicitly — PersonnelObserver's `deleted` hook never fires on soft-decommission (the row is updated, not deleted), so DeletePersons MQTT must be published directly from the controller.
- [20-05]: photo_access_token is a PostgreSQL uuid column (not varchar) — test fixtures must use Str::uuid() not free-form strings; `'old-token-xxx'` from the plan was corrected to Str::uuid()->toString() during GREEN.
- [20-05]: Broadcast auth matrix dropped Citizen role entirely — UserRole enum has 5 cases (Admin/Supervisor/Operator/Dispatcher/Responder), no Citizen. fras.cameras allows 4 denies 1; fras.enrollments allows 2 denies 3; 10 assertions total.
- [20-06]: Pinned pgsql session timezone to UTC via config/database.php 'timezone' => env('DB_TIMEZONE', 'UTC') — closes pre-existing TIMESTAMPTZ round-trip skew where PG's Asia/Manila session reinterpreted Eloquent's no-TZ Y-m-d H:i:s strings, producing an 8hr offset between writes and reads. Documented as a config gap in HeartbeatHandlerTest L25-28 prior to this plan; CameraWatchdogCommand cannot function correctly without the fix.
- [20-06]: Transition-only CameraStatusChanged dispatch (if $camera->status !== $newStatus) prevents broadcast storm on steady-state ticks — T-20-06-01 mitigation. Test asserts 3 cameras at gap=10s produce 0 broadcasts.
- [20-06]: PersonnelExpireSweepCommand uses bulk CameraEnrollment::where(...)->update(['status' => Done]) — no per-row broadcast because FRAS has no delete ACK (D-14); the flip is bookkeeping, the personnel row is already decommissioned and scopeActive will hide it from admin UI.
- [20-06]: Carbon::setTestNow(Carbon::parse('...', 'UTC')) preferred over literal string form — robust to future database.php TZ config changes.
- [20-07]: CameraStatusBadge wraps Reka Badge with variant=secondary — primitive's default bg-primary overrides color-mix() tints via specificity; secondary is the neutral no-op variant (v1.0 Units convention)
- [20-07]: CameraLocationPicker @blur handler extracted to onBlurSearch() using window.setTimeout — vue-tsc rejects bare setTimeout in template expressions because Vue's resolved component-instance type does not expose DOM globals
- [20-07]: useEnrollmentProgress uses new Map(prev).set(id, row) reactive replacement (Phase 12 precedent) — Vue's reactivity proxy does not track in-place Map mutations on refs; replacing the ref value triggers the effect graph cleanly
- [20-07]: Photo dropzone built inline in PersonnelForm.vue (not extracted to component) — FRAS /resources/js/pages/personnel/Create.vue is citizen-app surface; inline keeps form self-contained with native drag/drop events and client-side MIME/size enforcement before useForm sees the File
- [20-07]: Collapsible Details+Contact sections default-open only when edit-mode AND underlying row has populated field values; create mode and empty-edit both default closed — reduces form height without hiding pre-existing data
- [20-08]: Camera layers registered at the TOP of addLayers() (first 3 calls) rather than via beforeId arguments — mapbox-gl renders in registration order, so cameras stack BENEATH incidents naturally without needing the incident-halo reference to pre-exist. Simpler + no coordination overhead.
- [20-08]: Popup innerHTML content escaped via custom escapeHtml helper (5-char replace for & < > " ') with encodeURIComponent on the id URL path segment — T-20-08-01 XSS mitigation; every dynamic string goes through one of the two escapes before reaching innerHTML.
- [20-08]: vite.config.ts PWA precache ceiling raised 2 MB -> 3 MB (pre-existing build failure baseline) — main bundle exceeded default after Phase 20 admin surfaces landed in Plan 07. Proper fix (bundle splitting) deferred to Phase 21+.
- [20-08]: Integration test installs a known-messageId ACK cache entry post-upsertBatch rather than scanning for the service's random messageId — Cache facade has no enumeration API under the array driver; this preserves the correlation contract test while keeping the test deterministic.
- [21-01]: person_of_interest IncidentType seeder routes via IncidentCategory name='Crime / Security' (exact shipped string); idempotent updateOrCreate keyed on code
- [21-01]: RecognitionAlertReceived payload locked to 11-key denorm shape (D-12) using toHaveKeys assertion — additive migrations safe without test churn
- [21-01]: Nyquist Wave 0 RED state = 17 failures across FrasIncidentFactory (11, Plan 02), EscalateToP1 trigger field (3, Plan 03), IntakeStation recentFrasEvents prop (3, Plan 03); all contract-locked before implementation
- [21-02]: Constructor injection for RecognitionHandler (not app() helper) — matches AckHandler precedent; single dep, test-friendly
- [21-02]: FrasIncidentFactory is the single load-bearing bridge — 5-gate chain (severity/confidence/category/dedup/write) observable via ordered it() blocks; Cache::add atomic dedup
- [21-02]: IoTWebhookController 99 -> 56 lines via delegation; raw_message now json_encode($validated) — invisible to v1.0 IoTWebhookTest which only checks sensor_type/sensor_id roundtrip
- [21-03]: routes/fras.php restructured to per-route URL/name prefixing — legacy admin.personnel.photo kept under /admin/ via inner Route::prefix('admin')->name('admin.') group, new fras.event.face lives at /fras/events/{event}/face (bootstrap no longer applies admin/ blanket); preserves existing route contract while honoring UI-SPEC URL shape for new route
- [21-03]: FrasEventFaceController enforces role gate in-controller via abort_unless(in_array($user->role, [Operator,Supervisor,Admin])) in addition to bootstrap middleware — defense-in-depth; 5-min signed URLs pre-computed server-side at prop boot (not lazy) so rail SSR-renders 50 events without client round-trip; TODO(Phase 22) comment marks fras_access_log insertion point
- [21-03]: overridePriority trigger field validated inline ('sometimes', 'in:manual_override,fras_escalate_button') per D-22 planner preference; default 'manual_override' written to event_data preserves v1.0 audit shape on legacy supervisor overrides while 'fras_escalate_button' differentiates rail-driven escalations for Phase 22 reporting
- [21-04]: useDispatchMap map ref is shallowRef<mapboxgl.Map | null> named 'map' — pulseCamera uses map.value (not mapInstance.value); severity-aware feature-state paint case expressions on camera-body icon-size (0.55→0.88) and camera-halo circle-radius/color/opacity drive visual pulse per D-14/D-15
- [21-04]: circle-color camera-halo uses nested case+match form [case pulse_severity=critical→#A32D2D, warning→#EF9F27, CAMERA_STATUS_COLORS] — Mapbox accepts via ExpressionSpecification cast; existing status-color halo preserved when no pulse active
- [21-04]: useFrasAlerts takes pulseCamera as function parameter (not import) — avoids circular dep between channel subscription composable and map composable; page-level orchestrator wires them together
- [21-04]: useFrasRail live events get face_image_path/url=null — broadcast payload omits signed URLs; SSR prop is sole source of face thumbnails until Phase 22 adds live signing
- [21-04]: useIntakeFeed.channelCounts record extended with FRAS:0 — Rule 3 auto-fix cascaded from ChannelKey union extension; required for types:check parity (baseline 15 pre-existing Wayfinder errors unchanged)
- [21-04]: --t-ch-fras light #0e7490 / dark #22d3ee with dark override in .dark block alongside surface tokens (not @theme inline) — follows Sentinel dark-mode convention from Phase 14
- [21-05] Wayfinder actions use single-file named-export shape (@/actions/.../IntakeStationController { overridePriority }, not subdirectory indirection) — Wayfinder v0 emits one .ts per controller
- [21-05] No backend edit to IncidentController::show — timeline was already eager-loaded via timeline.actor relation; plan anticipated possible 1-line add but it was unnecessary
- [21-05] EscalateToP1Button placed between h1 and priority Badge per UI-SPEC §4 reading order; self-gates on timeline source + priority so dispatchers never see it in DOM

### Roadmap Evolution

- Phase 8 added: Implement operator role and intake layer UI
- Phase 9 added: Create a public facing reporting app
- Phase 10 added: Update all pages design to match IRMS Intake Design System
- Phase 11 added: Implement Units CRUD
- Phase 12 added: Bi-directional dispatch-responder communication
- Phase 13 added: PWA setup
- Phase 14 added: Update design system to Sentinel branding and rename app
- Phase 15 added: WebRTC live video stream from responder to dispatch
- Phases 17-22 added: v2.0 FRAS Integration milestone (Laravel 13 upgrade, schema port, MQTT, camera/personnel admin, recognition bridge, alerts + DPA)

### Pending Todos

None yet.

### Blockers/Concerns

- [Phase 3]: @laravel/echo-vue installed successfully (blocker resolved)
- [Phase 4]: MapLibre v5 updateData() validated -- using direct maplibre-gl (no vue-maplibre-gl wrapper) for maximum control
- [Phase 6]: Semaphore SMS API docs need verification when phase begins — no maintained Laravel package
- [Phase 7]: NDRRMC SitRep XML schema and DILG monthly report format not publicly documented — need agency contact
- [Phase 17]: Horizon 6 + Magellan Laravel-13 compatibility to re-verify at upgrade time (research flag)
- [Phase 21]: Severity-mapping field validation with CDRRMO dispatchers; dedup/confidence defaults need field tuning (research flag)
- [Phase 22]: CDRRMO legal / Butuan LGU Data Privacy Officer engagement timeline — milestone gate blocks on their sign-off; owner on client side TBD (research flag)

## Deferred Items

Items acknowledged and deferred at v1.0 milestone close on 2026-04-17, carried over to v2.0 intake:

| Category | Item | Status |
|----------|------|--------|
| verification | 15-close-rspdr-real-time-dispatch-visibility | human_needed — 4/4 automated passed; 6-step D-16 two-browser checklist pending (live WebSocket + audio + XSS checks require dual-browser session) |
| human_uat | 15-HUMAN-UAT.md | partial — 6 pending human-test items (Scene Progress gate, live checklist update, resource request audio/ticker, state-sync reload, XSS) |
| validation | 15-VALIDATION.md | draft — nyquist_compliant false, wave_0_complete false (phase never ran Nyquist validator) |
| debug | chat-input-hidden-by-status-btn | diagnosed — hypothesis recorded (StatusButton position: fixed overlaps ChatTab slot content); fix never confirmed in browser |
| debug | dispatch-messages-not-visible | resolved — file still in .planning/debug/ (housekeeping only; not moved to resolved/) |

All 5 items remain open for v2 milestone decision (verify / fix / close-out).

## Session Continuity

Last session: 2026-04-22T04:01:51.886Z
Stopped at: Completed 21-04
Resume file: None

**Planned Phase:** 21 (Recognition → IoT-Intake Bridge + Dispatch Map + IntakeStation Rail) — 5 plans — 2026-04-22T03:23:52.812Z
**Plan 06 Wave 3 progress:** CameraWatchdogCommand (irms:camera-watchdog, everyMinute, transition-only CameraStatusChanged dispatch) + PersonnelExpireSweepCommand (irms:personnel-expire-sweep, hourly, calls CameraEnrollmentService::deleteFromAllCameras + soft-decommission + bulk enrollment→Done + mqtt audit log). Schedule::command registrations appended to routes/console.php; both ->withoutOverlapping(). Pre-existing pgsql session TZ gap fixed by setting 'timezone' => env('DB_TIMEZONE', 'UTC') on the pgsql connection — without the fix, last_seen_at round-trip drift made every camera flip to Offline. Full fras group: 104 passed (+5 from baseline 99), 306 assertions. No regressions.
