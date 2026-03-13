# Phase 5: Responder Workflow - Research

**Researched:** 2026-03-13
**Domain:** Mobile-first responder UI, real-time WebSocket communication, PDF generation, Browser Geolocation API, MapLibre GL JS
**Confidence:** HIGH

## Summary

Phase 5 builds the responder-facing mobile web application within the existing IRMS Vue 3 + Inertia.js + Laravel 12 stack. The responder workflow spans receiving assignments via WebSocket, navigating to scenes with MapLibre mini-maps and Google Maps deep-links, documenting scene data (checklists, vitals, assessment tags), messaging dispatch, selecting outcomes, and auto-generating PDF incident reports via a queued Laravel job.

The existing codebase provides extensive reusable infrastructure: `useAlertSystem` (priority audio tones), `useAckTimer` (90-second countdown), `useWebSocket` (connection management and state sync), Echo composables from `@laravel/echo-vue`, the `AssignmentPushed` and `MessageSent` broadcast events, the `IncidentMessage` model with polymorphic sender, the `IncidentUnit` pivot with `acknowledged_at`, and `maplibre-gl` already installed. The `DispatchConsoleController::advanceStatus()` method already enforces forward-only transitions. All database columns needed (vitals JSONB, assessment_tags TEXT, outcome, hospital, checklist_pct, scene_time_sec, report_pdf_url) already exist on the incidents table.

**Primary recommendation:** Create a dedicated `ResponderController` with its own status advancement endpoint (separate from dispatch's), a `ResponderLayout.vue` with bottom tab bar following the IntakeLayout/DispatchLayout pattern, and compose the responder UI from tab-specific components that share state via a `useResponderSession` composable. Install `barryvdh/laravel-dompdf` v3.1+ for PDF generation via a queued `GenerateIncidentReport` job.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Dedicated `ResponderLayout.vue` -- full-screen mobile-first layout with compact topbar (44px) + bottom tab bar. No sidebar. Follows IntakeLayout/DispatchLayout pattern
- Compact topbar shows: unit callsign (AMB-01), current incident number + priority badge, current status chip
- Bottom tab bar with 3 contextual tabs: Assignment + (Nav OR Scene) + Chat
  - Before arrival: Assignment | Nav | Chat
  - After arrival: Assignment | Nav swaps to Scene | Chat
- Chat tab shows unread message count badge (red dot with number)
- Status transition button fixed above tab bar on every tab
- Standby screen when no active assignment
- Full-screen takeover for assignment notification with ACKNOWLEDGE button, 90-second countdown, priority-colored border
- Priority-matched audio tones reused from Phase 4 (useAlertSystem)
- One assignment at a time
- After acknowledging: auto-transition to Nav tab
- Assignment tab shows full incident summary card with resource request button at bottom
- Resource request modal with 6 resource types as large touch-friendly buttons
- Scene tab: accordion sections (Checklist, Vitals, Assessment Tags), one section open at a time, progress indicators
- Checklist completion encouraged not required (warning on incomplete)
- 4 hardcoded checklist templates: cardiac, road accident, structure fire, default
- Assessment tags auto-save on each toggle
- Single vitals reading per incident for v1
- Vitals required only for medical outcomes
- Nav tab: embedded MapLibre mini-map with route polyline, responder position, incident location, live ETA countdown, large Google Maps button
- GPS auto-broadcast via Browser Geolocation API: 10s en route, 60s on scene
- Quick-reply chips: 8 presets, single-tap = immediate send
- Free text input below quick-reply chips
- In-app slide-down banner for incoming messages on other tabs
- Outcome selection via bottom sheet: 5 outcomes as large cards
- "Transported to Hospital" expands searchable dropdown with pre-seeded Butuan City hospitals
- Post-closure summary screen with "Done" button returning to standby
- PDF auto-generated server-side on closure as queued job (barryvdh/laravel-dompdf)
- PDF stored as file path on incident record

### Claude's Discretion
- Exact tab bar icon design and animation
- Standby screen waiting animation
- Bottom sheet implementation approach (CSS transition vs library)
- MapLibre mini-map zoom level and route polyline styling
- Accordion section animation timing
- Checklist item content for each of 4 templates
- Quick-reply chip styling and layout
- PDF Blade template layout and typography
- Hospital seeder data (specific Butuan City hospitals)
- Exact summary screen layout after closure
- Assignment card layout details
- Resource request modal design

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| RSPDR-01 | Responder receives assignment via WebSocket with toast notification and audio cue | Reuse `AssignmentPushed` event on `user.{userId}` channel + `useAlertSystem` composable + `useEcho` from `@laravel/echo-vue` |
| RSPDR-02 | Responder can acknowledge assignment with single tap; timestamp captured and dispatch timer closed | `IncidentUnit` pivot has `acknowledged_at`; new `ResponderController::acknowledge()` endpoint; broadcast `IncidentStatusChanged` |
| RSPDR-03 | Status transitions (Acknowledged > En Route > On Scene > Resolving > Resolved) with 44px touch targets | Forward-only transitions via `allowedTransitions` map pattern from `DispatchConsoleController`; separate responder endpoint |
| RSPDR-04 | Navigation deep-link + MapLibre mini-map + animated route + ETA countdown | `maplibre-gl` v5.20+ already installed; Browser Geolocation API for GPS; GeoJSON line source + layer for route |
| RSPDR-05 | Bi-directional messaging with dispatch; 8 quick-reply chips + free text | `IncidentMessage` model with `is_quick_reply` flag; `MessageSent` broadcast event on `user.{recipientId}` channel |
| RSPDR-06 | Contextual arrival checklists per incident type with progress | Hardcoded JSON checklist templates; `checklist_pct` column on incidents table; broadcast progress via `IncidentStatusChanged` or new event |
| RSPDR-07 | Patient vitals form (BP, HR, SpO2, GCS) with validation ranges | `vitals` JSONB column on incidents; form request validation for ranges |
| RSPDR-08 | Quick assessment tags as toggle chips (11 tags) | `assessment_tags` TEXT column (cast as array in model); auto-save on toggle |
| RSPDR-09 | Outcome selection required before closure with hospital picker | `outcome` and `hospital` string columns exist; `IncidentOutcome` enum to create; hospitals seeder |
| RSPDR-10 | Resource request from field (6 types) | New `ResourceRequested` broadcast event; timeline entry; dispatch notification |
| RSPDR-11 | Auto-generated incident report PDF on closure | `barryvdh/laravel-dompdf` v3.1+; queued `GenerateIncidentReport` job; Blade template; `report_pdf_url` column exists |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vue 3 | ^3.5 | Component framework | Already in use across project |
| Inertia.js v2 | ^2.3 | SPA routing with server-side rendering | Project standard; Wayfinder integration |
| @laravel/echo-vue | ^2.3 | WebSocket composables | Project standard for real-time; `useEcho`, `useConnectionStatus` |
| maplibre-gl | ^5.20 | Mini-map for navigation tab | Already installed from Phase 4 |
| @vueuse/core | ^12.8 | `useIntervalFn` for timers, `useGeolocation` optional | Already installed; used by `useAckTimer` |
| Tailwind CSS v4 | ^4.1 | Styling with design system tokens | Project standard with `@theme inline` |
| barryvdh/laravel-dompdf | ^3.1 | Server-side PDF generation | De facto Laravel PDF library; supports Laravel 12 (`illuminate/support: ^9|^10|^11|^12`) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel Horizon | v5 | Queue processing for PDF job | Already configured; PDF job runs on `default` queue |
| lucide-vue-next | ^0.468 | Icons for tab bar and UI elements | Already installed in project |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| barryvdh/laravel-dompdf | spatie/laravel-pdf (Browsershot) | Browsershot requires Chrome/Puppeteer binary on server; DomPDF is pure PHP, simpler deployment |
| Raw CSS for bottom sheet | @vueuse/core `useSwipe` | Over-engineering for a simple show/hide slide-up; CSS transform + transition is sufficient |
| Custom geolocation | @vueuse/core `useGeolocation` | VueUse wrapper is convenient but adds abstraction; raw `navigator.geolocation.watchPosition` gives more control for interval-based broadcasting |

**Installation:**
```bash
composer require barryvdh/laravel-dompdf
```

No new npm packages needed -- all frontend dependencies already installed.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Http/Controllers/
│   └── ResponderController.php          # Main responder controller
├── Http/Requests/
│   ├── AcknowledgeAssignmentRequest.php # Ack validation
│   ├── UpdateVitalsRequest.php          # Vitals validation with ranges
│   ├── SendMessageRequest.php           # Message validation
│   ├── RequestResourceRequest.php       # Resource type validation
│   └── ResolveIncidentRequest.php       # Outcome + hospital + closure validation
├── Events/
│   ├── ChecklistUpdated.php             # Checklist progress broadcast (NEW)
│   └── ResourceRequested.php            # Resource request broadcast (NEW)
├── Jobs/
│   └── GenerateIncidentReport.php       # Queued PDF generation (NEW)
├── Enums/
│   └── IncidentOutcome.php              # 5 outcome values (NEW)

resources/js/
├── layouts/
│   └── ResponderLayout.vue              # Mobile-first layout with bottom tabs
├── pages/
│   └── responder/
│       └── Station.vue                  # Main responder page
├── composables/
│   ├── useResponderSession.ts           # Central state management
│   └── useGpsTracking.ts               # Geolocation + broadcast
├── components/
│   └── responder/
│       ├── ResponderTopbar.vue          # Compact 44px topbar
│       ├── ResponderTabbar.vue          # Bottom tab bar
│       ├── StandbyScreen.vue            # No active assignment
│       ├── AssignmentNotification.vue   # Full-screen takeover
│       ├── AssignmentTab.vue            # Incident summary card
│       ├── NavTab.vue                   # MapLibre mini-map + Google Maps link
│       ├── SceneTab.vue                 # Accordion: Checklist + Vitals + Tags
│       ├── ChatTab.vue                  # Quick-reply chips + free text
│       ├── StatusButton.vue             # Fixed status transition button
│       ├── OutcomeSheet.vue             # Bottom sheet for outcome selection
│       ├── ResourceRequestModal.vue     # 6 resource type buttons
│       ├── ClosureSummary.vue           # Post-closure summary
│       ├── ChecklistSection.vue         # Animated checkboxes
│       ├── VitalsForm.vue              # BP, HR, SpO2, GCS inputs
│       ├── AssessmentTags.vue          # Toggle chip grid
│       └── MessageBanner.vue           # Slide-down incoming message
├── types/
│   └── responder.ts                     # Responder-specific types

resources/views/
└── pdf/
    └── incident-report.blade.php        # DomPDF template
```

### Pattern 1: Responder Session Composable (Central State Hub)
**What:** A single composable manages all responder state -- active incident, current status, checklist progress, vitals, tags, messages, unread count. WebSocket events mutate this state reactively.
**When to use:** Always -- the responder page is a single Inertia page with tab-based navigation. State must persist across tab switches.
**Example:**
```typescript
// Pattern from existing useDispatchSession / useDispatchFeed
export function useResponderSession(
    initialIncident: ResponderIncident | null,
    initialUnit: ResponderUnit,
) {
    const activeIncident = ref<ResponderIncident | null>(initialIncident);
    const currentStatus = computed(() => activeIncident.value?.status ?? 'STANDBY');
    const messages = ref<IncidentMessage[]>([]);
    const unreadCount = ref(0);
    const checklistState = ref<Record<string, boolean>>({});
    const vitals = ref<VitalsData | null>(null);
    const assessmentTags = ref<string[]>([]);

    // Subscribe to user.{userId} for AssignmentPushed, MessageSent
    useEcho<AssignmentPayload>(`user.${userId}`, 'AssignmentPushed', (e) => {
        activeIncident.value = mapToResponderIncident(e);
    });

    return { activeIncident, currentStatus, messages, unreadCount, /* ... */ };
}
```

### Pattern 2: GPS Tracking Composable
**What:** Wraps `navigator.geolocation.watchPosition` with interval-based broadcasting. 10s en route, 60s on scene.
**When to use:** When responder has an active assignment and is in Acknowledged/EnRoute/OnScene status.
**Example:**
```typescript
export function useGpsTracking(unitId: string, status: Ref<string>) {
    let watchId: number | null = null;
    const position = ref<{ lat: number; lng: number } | null>(null);
    let lastBroadcast = 0;

    const broadcastInterval = computed(() =>
        ['ACKNOWLEDGED', 'EN_ROUTE'].includes(status.value) ? 10_000 : 60_000
    );

    function start(): void {
        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                position.value = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                const now = Date.now();
                if (now - lastBroadcast >= broadcastInterval.value) {
                    broadcastLocation(unitId, position.value);
                    lastBroadcast = now;
                }
            },
            (err) => console.warn('GPS error:', err),
            { enableHighAccuracy: true, maximumAge: 5000 }
        );
    }

    // POST to responder/location endpoint which fires UnitLocationUpdated
    async function broadcastLocation(unitId: string, coords: { lat: number; lng: number }) {
        await fetch(updateLocation.url({ unit: unitId }), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(coords),
        });
    }

    onUnmounted(() => { if (watchId) navigator.geolocation.clearWatch(watchId); });

    return { position, start, stop };
}
```

### Pattern 3: Bottom Sheet via CSS Transform
**What:** Outcome selection as a slide-up sheet using CSS `transform: translateY()` + transition. No library needed.
**When to use:** Outcome selection when advancing to Resolving status.
**Example:**
```vue
<div
    class="fixed inset-x-0 bottom-0 z-50 rounded-t-2xl bg-t-surface shadow-2xl transition-transform duration-300"
    :class="isOpen ? 'translate-y-0' : 'translate-y-full'"
>
    <!-- Sheet content -->
</div>
<div
    v-if="isOpen"
    class="fixed inset-0 z-40 bg-black/40"
    @click="isOpen = false"
/>
```

### Pattern 4: Tab-Based Navigation (No Router)
**What:** Tab switching via reactive `activeTab` ref, not Inertia router navigation. All tabs render within a single Inertia page.
**When to use:** The responder station is a single page with in-page tab switching.
**Example:**
```vue
<script setup>
const activeTab = ref<'assignment' | 'nav' | 'scene' | 'chat'>('assignment');
const isOnScene = computed(() => ['ON_SCENE', 'RESOLVING'].includes(currentStatus.value));
const middleTab = computed(() => isOnScene.value ? 'scene' : 'nav');
</script>
```

### Anti-Patterns to Avoid
- **Separate Inertia pages per tab:** Each tab is NOT a separate page. Responder station is a single Inertia page with reactive tab switching. Navigating away would lose WebSocket subscriptions and local state.
- **Reusing DispatchConsoleController for responder actions:** Responder needs its own controller with responder-specific authorization, validation, and logic (e.g., acknowledging closes the ack timer, advancing to Resolved requires outcome).
- **Polling for messages:** Messages arrive via `MessageSent` WebSocket event. Do not poll.
- **Saving checklist/tags with a form submit:** Assessment tags auto-save on each toggle (individual PATCH requests). Checklist items save individually too. No "save all" button.
- **Using Inertia form for real-time mutations:** Status transitions, tag toggles, and checklist ticks should use direct fetch/Wayfinder calls, not `useForm` (which triggers full page visit).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PDF generation | Custom HTML-to-PDF pipeline | `barryvdh/laravel-dompdf` with Blade template | DomPDF handles font embedding, page layout, CSS support; Blade template is standard Laravel |
| Audio alerts | New oscillator logic | `useAlertSystem` composable from Phase 4 | Already has all 4 priority tones and P1 flash |
| Ack countdown timer | Custom setInterval | `useAckTimer` composable from Phase 4 | Already handles calculation, display formatting, expiry callback |
| WebSocket connection management | Custom reconnection logic | `useWebSocket` composable from Phase 3 | Already handles reconnection, state sync, banner levels |
| Map rendering | Custom canvas/SVG map | `maplibre-gl` with GeoJSON sources | Already in use in Phase 4; mini-map is a simplified version |
| Outcome enum | Magic strings | `IncidentOutcome` PHP enum | Type safety, validation, consistent across backend |
| Searchable hospital dropdown | Custom autocomplete | Follow `SearchableSelect` pattern from Phase 9 | Proven pattern in codebase; keyboard navigation, filter, clear |

**Key insight:** Phase 5's value is in the mobile-first responder UX, not in infrastructure. Nearly all backend patterns (events, channels, service layer, status transitions) exist from prior phases.

## Common Pitfalls

### Pitfall 1: iOS Safari Audio Context Restriction
**What goes wrong:** Audio won't play on iOS Safari without user gesture first
**Why it happens:** iOS requires `AudioContext.resume()` to be triggered from a user interaction event
**How to avoid:** The existing `useAlertSystem` already handles this with click/keydown event listeners that call `resume()`. The responder must interact with the app (tap "ACKNOWLEDGE") before audio can loop, so this is naturally satisfied.
**Warning signs:** Audio plays on desktop but not on mobile Safari

### Pitfall 2: Geolocation Permission on iOS
**What goes wrong:** `watchPosition()` silently fails or returns low-accuracy position
**Why it happens:** iOS Safari requires HTTPS for geolocation; user may deny permission; the permission prompt only appears once
**How to avoid:** Always check `navigator.geolocation` exists; handle `PositionError` with graceful fallback; show clear UI when GPS is unavailable; the app runs on HTTPS via Herd (irms.test). Use `enableHighAccuracy: true` for GPS.
**Warning signs:** GPS dot doesn't appear on dispatch map; location updates stop

### Pitfall 3: WebSocket Event Name Mismatch
**What goes wrong:** Events fire on the server but the client never receives them
**Why it happens:** `@laravel/echo-vue` auto-prepends the namespace. If the event uses `broadcastAs()`, the client must listen with the exact name (no auto-prefix).
**How to avoid:** Follow existing pattern: none of the current events use `broadcastAs()`, so the class name works directly. Keep it consistent.
**Warning signs:** Server logs show event dispatched but client callback never fires

### Pitfall 4: Dual Status Advancement Endpoints
**What goes wrong:** Dispatch and responder both advance status, causing race conditions
**Why it happens:** Both `DispatchConsoleController::advanceStatus()` and `ResponderController::advanceStatus()` can update the same incident
**How to avoid:** Both endpoints must reload `$incident->fresh()` before checking current status. The `allowedTransitions` map prevents invalid transitions. The responder endpoint should additionally enforce that only the assigned responder's user can advance (gate check: `respond-incidents`).
**Warning signs:** 422 errors on valid status transitions; dispatch shows stale status

### Pitfall 5: Assessment Tags Column Type
**What goes wrong:** Tags won't save or load correctly
**Why it happens:** The `assessment_tags` column is defined as `TEXT` in the migration (not `JSONB` or array type), but cast as `'array'` in the model. For PostgreSQL, Eloquent's array cast stores/loads JSON to/from TEXT columns, which works. But for SQLite (test env), this might behave differently.
**How to avoid:** The existing cast as `'array'` handles JSON encode/decode transparently. In tests, use PostgreSQL (project already uses `RefreshDatabase` on PostgreSQL per decision `[01-01]`). When saving, pass a PHP array; it auto-JSON-encodes.
**Warning signs:** Tags appear as `null` or stringified JSON instead of array

### Pitfall 6: PDF Generation Timeout in Queue
**What goes wrong:** PDF job fails or times out
**Why it happens:** DomPDF rendering can be slow for complex HTML; default queue timeout may be too short
**How to avoid:** Keep the PDF template simple (single page target). Set `$timeout = 60` on the job class. Use `public $tries = 3` for retry on failure. The PDF is generated asynchronously (queued), so the user doesn't wait.
**Warning signs:** Failed jobs in Horizon dashboard; `report_pdf_url` stays null

### Pitfall 7: Mobile Touch Target Sizes
**What goes wrong:** Buttons are too small to tap reliably on mobile
**Why it happens:** Desktop-first CSS with small padding/margins
**How to avoid:** Enforce 44px minimum touch targets per spec. Use `min-h-[44px] min-w-[44px]` on all interactive elements. Bottom tab bar buttons, status transition button, quick-reply chips, checklist items, and assessment tags must all meet this minimum.
**Warning signs:** Users report misclicks; buttons are hard to tap with gloves

## Code Examples

### Responder Controller - Acknowledge Assignment
```php
// Pattern follows existing DispatchConsoleController structure
public function acknowledge(Request $request, Incident $incident): JsonResponse
{
    $user = $request->user();
    $unit = $user->unit;

    if (!$unit) {
        return response()->json(['message' => 'User is not assigned to a unit.'], 422);
    }

    // Update pivot acknowledged_at
    $incident->assignedUnits()->updateExistingPivot($unit->id, [
        'acknowledged_at' => now(),
    ]);

    $oldStatus = $incident->status;
    $incident->update([
        'status' => IncidentStatus::Acknowledged,
        'acknowledged_at' => now(),
    ]);

    // Timeline entry
    $incident->timeline()->create([
        'event_type' => 'status_changed',
        'event_data' => [
            'old_status' => $oldStatus->value,
            'new_status' => IncidentStatus::Acknowledged->value,
        ],
        'actor_type' => get_class($user),
        'actor_id' => $user->id,
    ]);

    IncidentStatusChanged::dispatch($incident->fresh(), $oldStatus);

    return response()->json(['message' => 'Assignment acknowledged.']);
}
```

### Responder Controller - Resolve with Outcome
```php
public function resolve(ResolveIncidentRequest $request, Incident $incident): JsonResponse
{
    $validated = $request->validated();
    $oldStatus = $incident->status;

    $updateData = [
        'status' => IncidentStatus::Resolved,
        'resolved_at' => now(),
        'outcome' => $validated['outcome'],
        'hospital' => $validated['hospital'] ?? null,
        'closure_notes' => $validated['closure_notes'] ?? null,
        'scene_time_sec' => $incident->on_scene_at
            ? now()->diffInSeconds($incident->on_scene_at)
            : null,
    ];

    $incident->update($updateData);

    // Release unit back to AVAILABLE
    $unit = $request->user()->unit;
    if ($unit) {
        $oldUnitStatus = $unit->status;
        $unit->update(['status' => UnitStatus::Available]);
        UnitStatusChanged::dispatch($unit->fresh(), $oldUnitStatus);
    }

    // Timeline
    $incident->timeline()->create([
        'event_type' => 'status_changed',
        'event_data' => [
            'old_status' => $oldStatus->value,
            'new_status' => 'RESOLVED',
            'outcome' => $validated['outcome'],
        ],
        'actor_type' => get_class($request->user()),
        'actor_id' => $request->user()->id,
    ]);

    IncidentStatusChanged::dispatch($incident->fresh(), $oldStatus);

    // Queue PDF generation
    GenerateIncidentReport::dispatch($incident->fresh());

    return response()->json(['message' => 'Incident resolved.']);
}
```

### MapLibre Mini-Map Route Polyline
```typescript
// Source: MapLibre GL JS docs + existing useDispatchMap pattern
function addRoutePolyline(
    map: MaplibreMap,
    coordinates: [number, number][],
): void {
    map.addSource('route', {
        type: 'geojson',
        data: {
            type: 'Feature',
            properties: {},
            geometry: {
                type: 'LineString',
                coordinates,
            },
        },
    });

    map.addLayer({
        id: 'route-line',
        type: 'line',
        source: 'route',
        layout: {
            'line-join': 'round',
            'line-cap': 'round',
        },
        paint: {
            'line-color': '#2563eb',
            'line-width': 4,
            'line-opacity': 0.8,
        },
    });
}
```

### Google Maps Deep-Link
```typescript
// Source: Google Maps URL scheme documentation
function getGoogleMapsUrl(lat: number, lng: number): string {
    return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;
}
```

### Queued PDF Generation Job
```php
// Source: barryvdh/laravel-dompdf documentation
class GenerateIncidentReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public Incident $incident) {}

    public function handle(): void
    {
        $incident = $this->incident->load([
            'incidentType', 'barangay', 'assignedUnits', 'timeline',
        ]);

        $pdf = Pdf::loadView('pdf.incident-report', [
            'incident' => $incident,
        ]);

        $filename = "incident-reports/{$incident->incident_no}.pdf";
        Storage::put($filename, $pdf->output());

        $incident->update(['report_pdf_url' => $filename]);
    }
}
```

### IncidentOutcome Enum
```php
enum IncidentOutcome: string
{
    case TreatedOnScene = 'TREATED_ON_SCENE';
    case TransportedToHospital = 'TRANSPORTED_TO_HOSPITAL';
    case RefusedTreatment = 'REFUSED_TREATMENT';
    case DeclaredDOA = 'DECLARED_DOA';
    case FalseAlarm = 'FALSE_ALARM';
}
```

### Assessment Tags Auto-Save Pattern
```typescript
// Each toggle sends immediate PATCH request
async function toggleTag(tag: string): void {
    const current = assessmentTags.value;
    const updated = current.includes(tag)
        ? current.filter((t) => t !== tag)
        : [...current, tag];

    assessmentTags.value = updated;

    // Fire-and-forget PATCH
    await fetch(updateTags.url({ incident: incidentId }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ assessment_tags: updated }),
    });
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Separate route per view | Single Inertia page with tab switching | Project convention from Phase 4+ | Maintains WebSocket subscriptions across tabs |
| Manual Echo setup | `@laravel/echo-vue` composables | Echo Vue v2 (project standard) | Auto-cleanup on unmount, type-safe callbacks |
| Custom interval timers | `@vueuse/core` `useIntervalFn` | Phase 4 | Automatic cleanup, pause/resume |
| `bg-opacity-*` | `bg-black/*` or `color-mix()` | Tailwind v4 | Deprecated utilities removed |
| `$casts` property | `casts()` method | Laravel 11+ | Method-based casting, project convention |

**Deprecated/outdated:**
- `BROADCAST_DRIVER` env key: Now `BROADCAST_CONNECTION` in Laravel 11+
- `@tailwind` directives: Now `@import "tailwindcss"` in Tailwind v4
- `bg-opacity-*` utilities: Use `bg-color/*` opacity modifier or `color-mix()`

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 with pestphp/pest-plugin-laravel |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=Responder` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| RSPDR-01 | Assignment WebSocket notification | unit | `php artisan test --compact tests/Feature/Responder/AssignmentNotificationTest.php -x` | Wave 0 |
| RSPDR-02 | Acknowledge assignment updates pivot + incident status | feature | `php artisan test --compact tests/Feature/Responder/AcknowledgeAssignmentTest.php -x` | Wave 0 |
| RSPDR-03 | Status transitions with forward-only enforcement | feature | `php artisan test --compact tests/Feature/Responder/StatusTransitionTest.php -x` | Wave 0 |
| RSPDR-04 | GPS location update endpoint | feature | `php artisan test --compact tests/Feature/Responder/LocationUpdateTest.php -x` | Wave 0 |
| RSPDR-05 | Send message (quick-reply + free text) | feature | `php artisan test --compact tests/Feature/Responder/MessagingTest.php -x` | Wave 0 |
| RSPDR-06 | Update checklist progress | feature | `php artisan test --compact tests/Feature/Responder/ChecklistTest.php -x` | Wave 0 |
| RSPDR-07 | Save vitals with validation ranges | feature | `php artisan test --compact tests/Feature/Responder/VitalsTest.php -x` | Wave 0 |
| RSPDR-08 | Toggle assessment tags auto-save | feature | `php artisan test --compact tests/Feature/Responder/AssessmentTagsTest.php -x` | Wave 0 |
| RSPDR-09 | Resolve with outcome (required), hospital (conditional) | feature | `php artisan test --compact tests/Feature/Responder/ResolutionTest.php -x` | Wave 0 |
| RSPDR-10 | Request resource creates timeline entry | feature | `php artisan test --compact tests/Feature/Responder/ResourceRequestTest.php -x` | Wave 0 |
| RSPDR-11 | PDF generation job produces valid file | feature | `php artisan test --compact tests/Feature/Responder/PdfGenerationTest.php -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=Responder`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Responder/AcknowledgeAssignmentTest.php` -- covers RSPDR-02
- [ ] `tests/Feature/Responder/StatusTransitionTest.php` -- covers RSPDR-03
- [ ] `tests/Feature/Responder/LocationUpdateTest.php` -- covers RSPDR-04
- [ ] `tests/Feature/Responder/MessagingTest.php` -- covers RSPDR-05
- [ ] `tests/Feature/Responder/ChecklistTest.php` -- covers RSPDR-06
- [ ] `tests/Feature/Responder/VitalsTest.php` -- covers RSPDR-07
- [ ] `tests/Feature/Responder/AssessmentTagsTest.php` -- covers RSPDR-08
- [ ] `tests/Feature/Responder/ResolutionTest.php` -- covers RSPDR-09
- [ ] `tests/Feature/Responder/ResourceRequestTest.php` -- covers RSPDR-10
- [ ] `tests/Feature/Responder/PdfGenerationTest.php` -- covers RSPDR-11
- [ ] Framework install: `composer require barryvdh/laravel-dompdf` -- DomPDF not yet in composer.json
- [ ] Hospital seeder: `database/seeders/HospitalSeeder.php` -- Butuan City hospitals for outcome picker

## Open Questions

1. **Straight-line route vs actual road route on mini-map**
   - What we know: CONTEXT.md mentions "route polyline" on mini-map. Mapbox Directions API is Phase 6 (stubbed). No real routing API available yet.
   - What's unclear: Should the mini-map show a straight line between unit and incident, or attempt to show an actual road route?
   - Recommendation: Show straight line (great circle) between responder GPS position and incident coordinates. The ETA countdown can use the 30km/h urban speed estimate (matching existing `nearbyUnits` calculation). Real road routing is Phase 6 scope.

2. **Message recipient identification for dispatch**
   - What we know: `MessageSent` event targets `user.{recipientId}`. But dispatch might have multiple dispatchers online.
   - What's unclear: Does the responder message go to a specific dispatcher or to all dispatchers?
   - Recommendation: Messages should broadcast to the `dispatch.incidents` channel (all dispatchers see it) AND create an `IncidentMessage` record. The `MessageSent` event already works for 1:1, but for dispatch-side visibility, also broadcast on the shared dispatch channel.

3. **Checklist persistence approach**
   - What we know: Checklist state needs to persist and broadcast progress to dispatch. The `checklist_pct` column stores completion percentage.
   - What's unclear: Should individual checklist items be stored, or just the percentage?
   - Recommendation: Store checklist state as JSONB on the incident (e.g., `{ "scene_secured": true, "patient_assessed": false }`) in a new endpoint, and compute `checklist_pct` from it. This allows dispatch to see which items are done, not just the percentage.

## Sources

### Primary (HIGH confidence)
- Project codebase: `app/Events/AssignmentPushed.php`, `app/Events/MessageSent.php`, `app/Events/IncidentStatusChanged.php`, `app/Events/UnitLocationUpdated.php` -- existing broadcast event patterns
- Project codebase: `app/Http/Controllers/DispatchConsoleController.php` -- status advancement, unit assignment patterns
- Project codebase: `app/Models/Incident.php`, `app/Models/IncidentUnit.php`, `app/Models/IncidentMessage.php` -- data model with all needed columns
- Project codebase: `resources/js/composables/useAlertSystem.ts`, `useAckTimer.ts`, `useWebSocket.ts`, `useDispatchFeed.ts` -- reusable composable patterns
- Project codebase: `resources/js/layouts/DispatchLayout.vue`, `IntakeLayout.vue` -- layout patterns with provide/inject
- Project codebase: `routes/channels.php` -- existing channel authorization
- `.claude/skills/echo-vue-development/SKILL.md` -- `useEcho`, `useConnectionStatus` composable APIs
- `.claude/skills/echo-development/SKILL.md` -- broadcast event patterns, channel types

### Secondary (MEDIUM confidence)
- [barryvdh/laravel-dompdf GitHub](https://github.com/barryvdh/laravel-dompdf) -- v3.1+ supports Laravel 12 via `illuminate/support: ^9|^10|^11|^12`
- [Packagist barryvdh/laravel-dompdf](https://packagist.org/packages/barryvdh/laravel-dompdf) -- confirmed Laravel 12 compatibility
- [MDN Geolocation API](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation_API) -- `watchPosition()` browser API reference
- [Can I Use watchPosition](https://caniuse.com/mdn-api_geolocation_watchposition) -- browser support including Safari iOS 16.4+
- [Geoapify MapLibre route tutorial](https://www.geoapify.com/tutorial/draw-route-on-the-maplibre-mapbox-map/) -- GeoJSON line source + layer pattern for route polylines

### Tertiary (LOW confidence)
- None -- all findings verified with primary or secondary sources

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in project or verified compatible (DomPDF confirmed Laravel 12 via Packagist)
- Architecture: HIGH - All patterns directly derived from existing codebase (DispatchLayout, useDispatchFeed, DispatchConsoleController)
- Pitfalls: HIGH - Based on direct code inspection of existing implementations and known iOS Safari behavior
- PDF generation: MEDIUM - DomPDF well-documented but PDF template design is discretionary; queued job pattern is standard Laravel

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable stack, no fast-moving dependencies)
