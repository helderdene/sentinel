# Phase 8: Implement Operator Role and Intake Layer UI - Research

**Researched:** 2026-03-13
**Domain:** Role-based access control, custom layout architecture, real-time intake station UI, design system adoption
**Confidence:** HIGH

## Summary

Phase 8 introduces a 5th user role (Operator) and builds a full-screen intake station UI that replaces the sidebar-based incident pages for operators. This phase touches backend authorization (enum extension, gates, middleware, routing), a new Inertia layout, and a complete three-column UI built from a detailed design system specification. The design system document (`docs/IRMS-Intake-Design-System.md`) is comprehensive and prescriptive -- it defines exact pixel values, color tokens, typography, spacing, and component specs.

The existing codebase has strong patterns to follow: `UserRole` enum, gate definitions in `AppServiceProvider`, `EnsureUserHasRole` middleware, `UserFactory` with role states, and the `AppSidebarLayout.vue` as a reference for the new `IntakeLayout.vue`. The `useWebSocket` composable, `useEcho` composables, `usePrioritySuggestion`, and `useGeocodingSearch` are all directly reusable. The `IncidentController` store logic and `StoreIncidentRequest` validation handle the backend workflow -- the intake station's triage form will call the same endpoint but with a TRIAGED status instead of PENDING.

**Primary recommendation:** Structure as 3-4 plans: (1) Backend -- role, enum, gates, migration, seeder, routing, controller; (2) Design system tokens + IntakeLayout + topbar/statusbar shell; (3) Left panel (channel feed) + center panel (triage form); (4) Right panel (dispatch queue + session metrics) + supervisor features.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Add "operator" as a 5th role alongside admin, dispatcher, responder, supervisor -- one role per user
- Operator permissions (intake only): triage, manual entry, submit to dispatch queue
- Dispatcher keeps: dispatch console, unit assignment, map (Phase 4 scope)
- New intake-specific Laravel gates alongside existing ones: `triage-incidents`, `manual-entry`, `submit-dispatch`, `override-priority`, `recall-incident`, `view-session-log`
- Operator granted: triage-incidents, manual-entry, submit-dispatch
- Supervisor/admin granted: all intake gates (including override-priority, recall-incident, view-session-log)
- Existing dispatcher users are NOT migrated -- both roles coexist, admin creates operator users separately
- Operator's default landing page: intake station directly (no dashboard step)
- Intake station is operator's entire world -- no sidebar, no other pages, no escape to Index.vue or Show.vue
- New `IntakeLayout.vue` -- full-screen three-column layout with custom topbar (56px) and statusbar (24px)
- Other roles (dispatcher, responder, supervisor dashboard) continue using the existing `AppSidebarLayout`
- Supervisor/admin see the same intake station layout as operator but with extra controls (Override Priority button, Recall button, Session Log section)
- Fixed panel widths: left 296px, center flex, right 304px -- no resize, no collapse
- Single Inertia page (`IntakeStation.vue`) composed from panel components
- Light AND dark mode support
- Topbar stat pills update in real-time via WebSocket
- Live ticker scrolls real incident events via WebSocket
- Left panel feed shows real incidents (PENDING status) as cards -- arriving via WebSocket (IncidentCreated events)
- Filter tabs: All / Pending / Triaged -- triaged cards show at 55% opacity
- Clicking a feed card opens it in the center triage form with pre-filled data
- "+ Manual Entry" button opens a blank triage form
- New TRIAGED status added between PENDING and DISPATCHED in IncidentStatus enum
- Submitting the triage form sets status to TRIAGED and moves incident to right panel dispatch queue
- Dispatch queue (right panel) shows triaged incidents ordered by priority (P1 first) then FIFO
- Triage form rebuilt from scratch following the design system (not adapted from existing Create.vue)
- Existing Create.vue, Queue.vue, Index.vue replaced by the station for operators -- other roles keep their existing pages
- Session metrics are per-session (reset on login)
- Priority breakdown bar chart shows distribution of incidents by priority
- Supervisor/admin see an additional session log section at the bottom of the right panel
- Fonts: DM Sans + Space Mono adopted app-wide (replace current font stack)
- Color tokens: Replace existing Tailwind color tokens with design system values app-wide
- Icons: Custom inline SVG Vue components for intake station only -- other pages continue using Lucide icons
- Styling: Tailwind utilities where possible, custom CSS only for values Tailwind can't express

### Claude's Discretion
- Exact WebSocket event handling for topbar stat updates and live ticker
- Component file structure within intake station
- Dark mode token derivation from the light theme values
- Triage form field ordering and validation behavior
- Feed card animation timing and details
- Session metrics computation approach (in-memory vs backend)
- Priority breakdown chart implementation (CSS bars vs chart library)
- Session log entry format and display

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

This phase does not map to specific pre-defined requirement IDs from REQUIREMENTS.md. However, it addresses the following derived requirements based on CONTEXT.md:

| ID | Description | Research Support |
|----|-------------|-----------------|
| OP-01 | Add Operator as 5th role in UserRole enum with migration | UserRole enum extension pattern, factory state, seeder pattern |
| OP-02 | Add TRIAGED status between PENDING and DISPATCHED in IncidentStatus enum | Enum extension pattern, migration for existing data compatibility |
| OP-03 | Define 6 new intake gates and grant per role matrix | Gate definition pattern in AppServiceProvider, HandleInertiaRequests sharing |
| OP-04 | Operator default landing page is intake station (not dashboard) | Fortify home path role-based redirect pattern |
| OP-05 | IntakeLayout.vue full-screen three-column layout with topbar/statusbar | Layout architecture pattern, Tailwind CSS v4 theme tokens |
| OP-06 | IntakeStation.vue Inertia page with controller and route | Controller/route/Wayfinder pattern |
| OP-07 | Channel feed (left panel) with WebSocket real-time updates | useEcho composable, IncidentCreated event |
| OP-08 | Triage form (center panel) with priority suggestion and geocoding | usePrioritySuggestion, useGeocodingSearch composables, StoreIncidentRequest |
| OP-09 | Dispatch queue (right panel) with priority ordering | Queue ordering pattern from existing Queue.vue |
| OP-10 | Session metrics (per-session, in-memory) | Vue reactive state, composable pattern |
| OP-11 | Supervisor/admin extra controls (override, recall, session log) | Gate-based conditional rendering, timeline entry pattern |
| OP-12 | Design system tokens adopted app-wide (fonts, colors) | Tailwind CSS v4 @theme directive, CSS custom properties |
| OP-13 | Custom SVG icon components for intake station | Vue SFC pattern for inline SVG |
| OP-14 | WebSocket-driven topbar stats and live ticker | useEcho composable, aggregation queries |
| OP-15 | Intake station channel authorization for operators | channels.php authorization pattern |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | v12 | Backend role/gate/routing/controller | Already in use, proven patterns |
| Vue 3 | v3 | Frontend SPA components | Already in use |
| Inertia.js | v2 | Server-driven SPA routing | Already in use, `useForm` for triage |
| Tailwind CSS | v4 | Utility-first styling + design tokens | Already in use, `@theme` for token system |
| @laravel/echo-vue | v2 | WebSocket composables | Already in use, `useEcho` / `useConnectionStatus` |
| Laravel Reverb | v1 | WebSocket server | Already configured and running |
| Wayfinder | v0 | TypeScript route generation | Already in use for form actions |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Google Fonts (DM Sans, Space Mono) | N/A | Design system typography | Import in app.blade.php or CSS, used app-wide |
| Pest 4 | v4 | Testing operator role/gates/routing | All backend changes |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| CSS bars for priority breakdown | Chart.js / vue-chartjs | CSS bars are simpler, no dependency -- use CSS since it's just 4 horizontal bars |
| In-memory session metrics | Backend session tracking | In-memory is simpler and matches "reset on login" requirement -- no DB persistence needed |
| Custom SVG components | Lucide icons | Design system requires custom icons with specific strokes/sizes -- must hand-build for intake station |

**Installation:**
No new packages required. DM Sans and Space Mono fonts loaded via Google Fonts link in `resources/views/app.blade.php` or `@import url()` in `resources/css/app.css`.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Enums/
│   ├── UserRole.php              # Add Operator case
│   └── IncidentStatus.php        # Add Triaged case
├── Http/
│   ├── Controllers/
│   │   └── IntakeStationController.php  # New controller for intake station
│   ├── Requests/
│   │   └── TriageIncidentRequest.php    # New FormRequest for triage action
│   └── Middleware/
│       └── EnsureUserHasRole.php        # Existing -- no changes needed
├── Providers/
│   └── AppServiceProvider.php    # Add 6 new gates
database/
├── migrations/
│   └── xxxx_add_operator_role_and_triaged_status.php  # Enum column updates
├── seeders/
│   └── OperatorUserSeeder.php    # Demo operator users from design system
resources/
├── css/
│   └── app.css                   # Design system tokens in @theme
├── js/
│   ├── layouts/
│   │   └── IntakeLayout.vue      # New full-screen layout (no sidebar)
│   ├── pages/
│   │   └── intake/
│   │       └── IntakeStation.vue # Single Inertia page component
│   ├── components/
│   │   └── intake/               # All intake station sub-components
│   │       ├── IntakeTopbar.vue
│   │       ├── IntakeStatusbar.vue
│   │       ├── ChannelFeed.vue       # Left panel
│   │       ├── FeedCard.vue
│   │       ├── TriagePanel.vue       # Center panel
│   │       ├── TriageForm.vue
│   │       ├── DispatchQueuePanel.vue # Right panel
│   │       ├── QueueRow.vue
│   │       ├── SessionMetrics.vue
│   │       ├── PriorityBreakdown.vue
│   │       ├── SessionLog.vue        # Supervisor/admin only
│   │       ├── IntakePriorityPicker.vue
│   │       ├── PriBadge.vue
│   │       ├── ChBadge.vue
│   │       ├── RoleBadge.vue
│   │       ├── UserChip.vue
│   │       └── icons/               # Custom SVG icon components
│   │           ├── IntakeIconSms.vue
│   │           ├── IntakeIconApp.vue
│   │           ├── IntakeIconVoice.vue
│   │           ├── IntakeIconIot.vue
│   │           ├── IntakeIconWalkin.vue
│   │           ├── IntakeIconPin.vue
│   │           ├── IntakeIconUser.vue
│   │           ├── IntakeIconCheck.vue
│   │           ├── IntakeIconIntake.vue
│   │           ├── IntakeIconLogout.vue
│   │           ├── IntakeIconShield.vue
│   │           ├── IntakeIconRecall.vue
│   │           ├── IntakeIconOverride.vue
│   │           └── IntakeIconActivity.vue
│   ├── composables/
│   │   ├── useIntakeSession.ts   # Session metrics (in-memory)
│   │   └── useIntakeFeed.ts      # Feed state + WebSocket subscription
│   └── types/
│       ├── auth.ts               # Add 'operator' to UserRole union
│       └── incident.ts           # Add 'TRIAGED' to IncidentStatus union
routes/
├── web.php                       # Add intake station route
└── channels.php                  # Authorize operator for dispatch.incidents
```

### Pattern 1: Role-Based Post-Login Redirect
**What:** Operator users redirect to intake station instead of dashboard after login.
**When to use:** When different roles have fundamentally different landing pages.
**Example:**
```php
// app/Providers/FortifyServiceProvider.php
use Laravel\Fortify\Fortify;

Fortify::authenticatedRedirectUsing(function (Request $request) {
    return $request->user()->role === UserRole::Operator
        ? route('intake.station')
        : config('fortify.home');
});
```
**Source:** Fortify v1 documentation -- `authenticatedRedirectUsing` callback.

### Pattern 2: IntakeLayout.vue (Independent Layout)
**What:** A layout that has NO sidebar, NO breadcrumbs -- just topbar, content area, statusbar.
**When to use:** When a role needs a completely different shell from the main app.
**Example:**
```vue
<template>
    <div class="flex h-screen flex-col overflow-hidden bg-[var(--t-bg)]">
        <IntakeTopbar />
        <div class="flex flex-1 overflow-hidden">
            <slot />
        </div>
        <IntakeStatusbar />
    </div>
</template>
```
**Source:** Existing `AppSidebarLayout.vue` as reference for layout shell pattern.

### Pattern 3: Triage Action (Status Transition)
**What:** Triage form submits to a dedicated controller method that transitions PENDING -> TRIAGED.
**When to use:** When the triage action differs from the original incident creation.
**Example:**
```php
// IntakeStationController.php
public function triage(TriageIncidentRequest $request, Incident $incident): RedirectResponse
{
    $oldStatus = $incident->status;
    $incident->update([
        'status' => IncidentStatus::Triaged,
        'priority' => $request->validated('priority'),
        'incident_type_id' => $request->validated('incident_type_id'),
        // ... other triage fields
    ]);

    IncidentStatusChanged::dispatch($incident, $oldStatus);

    return back();
}
```

### Pattern 4: Design System Tokens via Tailwind CSS v4 @theme
**What:** Map the design system's `T.*` tokens to CSS custom properties available as Tailwind utilities.
**When to use:** For all design system color/spacing values.
**Example:**
```css
/* resources/css/app.css */
@theme inline {
    --font-sans: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'Space Mono', ui-monospace, monospace;

    --color-t-bg: #f4f6f9;
    --color-t-surface: #ffffff;
    --color-t-surface-alt: #f8fafc;
    --color-t-text: #0f172a;
    --color-t-text-mid: #334155;
    --color-t-text-dim: #64748b;
    --color-t-text-faint: #94a3b8;
    --color-t-border: #e2e8f0;
    --color-t-border-med: #cbd5e1;
    --color-t-border-foc: #2563eb;
    --color-t-brand: #1e3a6e;
    --color-t-accent: #2563eb;
    --color-t-online: #16a34a;
    --color-t-p1: #dc2626;
    --color-t-p2: #ea580c;
    --color-t-p3: #ca8a04;
    --color-t-p4: #16a34a;
}
```
Usage: `bg-t-bg`, `text-t-text-mid`, `border-t-border`, etc.

### Anti-Patterns to Avoid
- **Modifying Create.vue for the triage form:** The context explicitly says "rebuilt from scratch following the design system." Do NOT adapt Create.vue. Build `TriageForm.vue` as a new component.
- **Putting operator in the sidebar nav system:** Operators have NO sidebar. The intake station is their entire world. Do not add operator entries to `AppSidebar.vue`'s nav items -- operator bypasses it entirely.
- **Sharing a layout between intake and sidebar pages:** `IntakeLayout.vue` must be completely independent from `AppSidebarLayout.vue`. No shared shell.
- **Hard-coding colors instead of using tokens:** Every color in the intake station must reference a CSS custom property / Tailwind token, not a raw hex value. This enables dark mode.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| WebSocket connection state | Custom WebSocket manager | `useWebSocket` composable + `useConnectionStatus` | Already handles reconnection, state sync, banner levels |
| Priority suggestion | Custom classification | `usePrioritySuggestion` composable | Already debounced, abort-controller managed |
| Geocoding search | Custom location lookup | `useGeocodingSearch` composable | Already debounced, abort-controller managed |
| Form validation | Inline controller validation | `TriageIncidentRequest` FormRequest | Project convention, reusable |
| Route generation | Manual URL construction | Wayfinder actions/routes | Auto-generates TypeScript functions |
| Role middleware | Custom auth checks | `EnsureUserHasRole` middleware | Already works with variadic roles |
| Channel authorization | Custom WebSocket auth | `routes/channels.php` closures | Already established pattern |

**Key insight:** The existing intake infrastructure (Phase 2) and WebSocket infrastructure (Phase 3) provide the entire backend. Phase 8 is primarily a UI/layout phase with role extension -- the heavy lifting is in Vue components, not backend logic.

## Common Pitfalls

### Pitfall 1: Channel Enum Mismatch Between Design System and Codebase
**What goes wrong:** The design system uses channel keys `SMS`, `APP`, `VOICE`, `IOT`, `WALKIN` but the existing `IncidentChannel` enum uses `phone`, `sms`, `app`, `iot`, `radio`.
**Why it happens:** The design system was created independently from the Phase 2 enum values.
**How to avoid:** Create a mapping in the intake station components that translates existing enum values to design system display. The `phone` channel maps to `VOICE`, `radio` could map to `WALKIN` or remain separate. The design system is a UI-level concern -- do NOT rename the PHP enum values as that would break existing data.
**Warning signs:** Feed cards showing wrong channel labels or icons.

### Pitfall 2: Fortify Home Path is Static
**What goes wrong:** Fortify's `config/fortify.php` has `'home' => '/dashboard'` which redirects ALL users to dashboard after login.
**Why it happens:** Fortify doesn't natively support role-based redirects.
**How to avoid:** Use `Fortify::authenticatedRedirectUsing()` in `FortifyServiceProvider::boot()` to check the user's role and return the appropriate URL. This is a documented Fortify feature.
**Warning signs:** Operators landing on the dashboard instead of intake station.

### Pitfall 3: Existing Gate Names Overlap
**What goes wrong:** The `create-incidents` gate currently grants dispatcher/supervisor/admin access to incident creation. Operators also need to create (triage) incidents but through a different flow.
**Why it happens:** The existing gate was designed for dispatcher-centric workflow.
**How to avoid:** Add operator to the `create-incidents` gate (they do create/update incidents during triage) AND add the new intake-specific gates (`triage-incidents`, `manual-entry`, `submit-dispatch`) for fine-grained intake permissions. The triage endpoint should use `triage-incidents` gate, not `create-incidents`.
**Warning signs:** Operators getting 403 on triage submission, or operators being able to access dispatch pages.

### Pitfall 4: Design System Font Change Breaks Existing Pages
**What goes wrong:** Replacing `Instrument Sans` with `DM Sans` app-wide changes the appearance of all existing pages (dashboard, settings, auth, admin).
**Why it happens:** The `--font-sans` variable in `@theme` affects every page.
**How to avoid:** This is an intentional change per the locked decision "Fonts: DM Sans + Space Mono adopted app-wide." Accept that existing pages will shift to DM Sans. Do a visual check on key pages (Login, Dashboard, Settings) after the change. The design is similar enough (both are geometric sans-serifs) that it should not break layouts.
**Warning signs:** Text wrapping differently in existing components due to different character widths.

### Pitfall 5: TRIAGED Status Breaks Existing Queue Logic
**What goes wrong:** The existing `Queue.vue` and `IncidentController::queue()` query `where('status', IncidentStatus::Pending)`. Adding TRIAGED means the dispatch queue needs to show TRIAGED incidents, not PENDING.
**Why it happens:** Phase 2 conflated "in the queue" with "PENDING status." Phase 8 separates triage from dispatch.
**How to avoid:** The intake station's right panel (dispatch queue) should query for TRIAGED incidents. The existing `Queue.vue` (used by dispatchers) should also be updated to show TRIAGED incidents. The left panel feed shows PENDING incidents. This is a clear status-based separation.
**Warning signs:** Triaged incidents disappearing from all views, or PENDING incidents showing in the dispatch queue.

### Pitfall 6: Dark Mode Token Derivation
**What goes wrong:** The design system only specifies light mode tokens. Dark mode needs manual derivation.
**Why it happens:** The design system document focuses on light theme.
**How to avoid:** Derive dark mode tokens by inverting the surface/text hierarchy: `T.bg` becomes a dark gray (~`#0f172a`), `T.surface` becomes slightly lighter (~`#1e293b`), `T.text` becomes light (~`#f8fafc`). Priority colors remain the same in dark mode (they're already vivid). Define dark tokens in `.dark { }` CSS block alongside existing dark mode variables.
**Warning signs:** White text on white backgrounds, invisible borders, priority colors washing out.

### Pitfall 7: WebSocket Channel Auth for Operators
**What goes wrong:** The `dispatch.incidents` channel in `channels.php` only authorizes `Dispatcher`, `Supervisor`, `Admin`. Operators cannot subscribe.
**Why it happens:** Operators didn't exist when Phase 3 was implemented.
**How to avoid:** Add `UserRole::Operator` to the `$dispatchRoles` array in `channels.php`. Operators need to receive `IncidentCreated` and `IncidentStatusChanged` events for the live feed.
**Warning signs:** Intake station feed never updating, WebSocket auth failures in console.

### Pitfall 8: PostgreSQL Enum Column Migration
**What goes wrong:** Adding a new value to a PostgreSQL enum column requires `ALTER TYPE ... ADD VALUE`, not a standard Laravel migration.
**Why it happens:** PostgreSQL enums are strict -- you can't just add values like MySQL.
**How to avoid:** The `role` column is stored as a string (VARCHAR) with the enum handled at the PHP level via `UserRole::class` cast. The `status` column is also a string. Verify this by checking the migration. If they're truly string columns (not DB-level enums), no special migration is needed -- just update the PHP enum. If they ARE DB-level enums, use `DB::statement("ALTER TYPE ...")`.
**Warning signs:** Migration fails with "invalid input value for enum" error.

## Code Examples

### Adding Operator to UserRole Enum
```php
// app/Enums/UserRole.php
enum UserRole: string
{
    case Admin = 'admin';
    case Dispatcher = 'dispatcher';
    case Operator = 'operator';
    case Responder = 'responder';
    case Supervisor = 'supervisor';
}
```

### Adding TRIAGED to IncidentStatus Enum
```php
// app/Enums/IncidentStatus.php
enum IncidentStatus: string
{
    case Pending = 'PENDING';
    case Triaged = 'TRIAGED';
    case Dispatched = 'DISPATCHED';
    case Acknowledged = 'ACKNOWLEDGED';
    case EnRoute = 'EN_ROUTE';
    case OnScene = 'ON_SCENE';
    case Resolving = 'RESOLVING';
    case Resolved = 'RESOLVED';
}
```

### New Intake Gates in AppServiceProvider
```php
// In configureGates() method
Gate::define('triage-incidents', fn (User $user): bool => in_array($user->role, [
    UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
], true));

Gate::define('manual-entry', fn (User $user): bool => in_array($user->role, [
    UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
], true));

Gate::define('submit-dispatch', fn (User $user): bool => in_array($user->role, [
    UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
], true));

Gate::define('override-priority', fn (User $user): bool => in_array($user->role, [
    UserRole::Supervisor, UserRole::Admin,
], true));

Gate::define('recall-incident', fn (User $user): bool => in_array($user->role, [
    UserRole::Supervisor, UserRole::Admin,
], true));

Gate::define('view-session-log', fn (User $user): bool => in_array($user->role, [
    UserRole::Supervisor, UserRole::Admin,
], true));
```

### Role-Based Login Redirect
```php
// app/Providers/FortifyServiceProvider.php in boot()
Fortify::authenticatedRedirectUsing(function (Request $request) {
    if ($request->user()->role === UserRole::Operator) {
        return route('intake.station');
    }
    return config('fortify.home');
});
```

### Channel Authorization Update
```php
// routes/channels.php
$dispatchRoles = [UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin];
```

### Design System Token Integration (Tailwind CSS v4)
```css
/* resources/css/app.css -- replace existing @theme inline block */
@theme inline {
    --font-sans: 'DM Sans', ui-sans-serif, system-ui, sans-serif,
        'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
    --font-mono: 'Space Mono', ui-monospace, monospace;

    /* Design System Surface Tokens */
    --color-t-bg: #f4f6f9;
    --color-t-surface: #ffffff;
    --color-t-surface-alt: #f8fafc;

    /* Design System Text Tokens */
    --color-t-text: #0f172a;
    --color-t-text-mid: #334155;
    --color-t-text-dim: #64748b;
    --color-t-text-faint: #94a3b8;

    /* Design System Border Tokens */
    --color-t-border: #e2e8f0;
    --color-t-border-med: #cbd5e1;
    --color-t-border-foc: #2563eb;

    /* Design System Brand & Accent */
    --color-t-brand: #1e3a6e;
    --color-t-accent: #2563eb;
    --color-t-online: #16a34a;
    --color-t-queued: #2563eb;

    /* Priority Colors */
    --color-t-p1: #dc2626;
    --color-t-p2: #ea580c;
    --color-t-p3: #ca8a04;
    --color-t-p4: #16a34a;

    /* Channel Colors */
    --color-t-ch-sms: #059669;
    --color-t-ch-app: #2563eb;
    --color-t-ch-voice: #d97706;
    --color-t-ch-iot: #7c3aed;
    --color-t-ch-walkin: #dc2626;

    /* Role Colors */
    --color-t-role-operator: #2563eb;
    --color-t-role-supervisor: #7c3aed;
    --color-t-role-admin: #0f766e;

    /* Keep existing token variables for backward compatibility */
    --radius-lg: var(--radius);
    --radius-md: calc(var(--radius) - 2px);
    --radius-sm: calc(var(--radius) - 4px);
    /* ... existing shadcn color variables stay ... */
}
```

### Custom SVG Icon Component Pattern
```vue
<!-- resources/js/components/intake/icons/IntakeIconSms.vue -->
<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: number;
        color?: string;
    }>(),
    {
        size: 16,
        color: 'currentColor',
    },
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="`0 0 ${size} ${size}`"
        fill="none"
        :stroke="color"
        stroke-width="1.3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <!-- Message bubble with text lines + tail -->
        <path d="M2 3h12a1 1 0 011 1v7a1 1 0 01-1 1H5l-3 3V4a1 1 0 011-1z" />
        <line x1="5" y1="6" x2="11" y2="6" />
        <line x1="5" y1="9" x2="9" y2="9" />
    </svg>
</template>
```

### IntakeLayout Shell
```vue
<!-- resources/js/layouts/IntakeLayout.vue -->
<script setup lang="ts">
import { useWebSocket } from '@/composables/useWebSocket';
import IntakeStatusbar from '@/components/intake/IntakeStatusbar.vue';
import IntakeTopbar from '@/components/intake/IntakeTopbar.vue';

const { bannerLevel, isSyncing, status } = useWebSocket();
</script>

<template>
    <div class="flex h-screen flex-col overflow-hidden bg-t-bg dark:bg-[#0f172a]">
        <IntakeTopbar />
        <div class="flex flex-1 overflow-hidden">
            <slot />
        </div>
        <IntakeStatusbar :connection-status="status" />
    </div>
</template>
```

### useIntakeSession Composable (In-Memory Metrics)
```typescript
// resources/js/composables/useIntakeSession.ts
import { computed, ref } from 'vue';

export function useIntakeSession() {
    const received = ref(0);
    const triaged = ref(0);
    const handleTimes = ref<number[]>([]);

    function recordReceived() {
        received.value++;
    }

    function recordTriaged(handleTimeMs: number) {
        triaged.value++;
        handleTimes.value.push(handleTimeMs);
    }

    const pending = computed(() => received.value - triaged.value);

    const avgHandleTime = computed(() => {
        if (handleTimes.value.length === 0) return 0;
        const sum = handleTimes.value.reduce((a, b) => a + b, 0);
        return Math.round(sum / handleTimes.value.length / 1000); // seconds
    });

    return { received, triaged, pending, avgHandleTime, recordReceived, recordTriaged };
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Tailwind config.js | Tailwind CSS v4 @theme directive | v4.0 | All token definitions in CSS, no JS config file |
| Individual CSS imports | `@import "tailwindcss"` | v4.0 | Single import statement |
| bg-opacity-* | bg-color/* | v4.0 | Opacity modifier syntax |
| Fortify static home | Fortify::authenticatedRedirectUsing() | Fortify v1 | Programmatic role-based redirect |

**Deprecated/outdated:**
- `Instrument Sans` font: Being replaced by `DM Sans` per design system decision
- `phone` and `radio` channel labels: The intake UI uses `VOICE` and `WALKIN` display labels (enum values unchanged)

## Open Questions

1. **Column type for role/status in database**
   - What we know: PHP enums cast string values. The columns are likely VARCHAR.
   - What's unclear: Whether original migration used `->enum()` (DB-level constraint) or `->string()`.
   - Recommendation: Check the migration files before writing the enum extension migration. If `->string()`, no migration needed for enum values. If `->enum()`, need `ALTER TYPE` for PostgreSQL or recreate column.

2. **Channel value mapping (phone -> VOICE, radio -> WALKIN)**
   - What we know: Design system uses different channel names than the PHP enum.
   - What's unclear: Whether to rename the PHP enum values or just map at the UI level.
   - Recommendation: Map at the UI level only. The PHP enum values are stored in the database and used across the codebase. Create a `channelDisplayMap` in the intake components.

3. **Existing page styling after font change**
   - What we know: DM Sans replaces Instrument Sans app-wide.
   - What's unclear: Visual regression impact on existing pages.
   - Recommendation: Test Login, Dashboard, Settings, and Queue pages after font change. DM Sans has similar metrics to Instrument Sans, so impact should be minimal.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=testName` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| OP-01 | Operator role exists in enum and factory | unit | `php artisan test --compact tests/Unit/Enums/UserRoleTest.php -x` | Wave 0 |
| OP-02 | TRIAGED status exists in enum | unit | `php artisan test --compact tests/Unit/Enums/IncidentStatusTest.php -x` | Wave 0 |
| OP-03 | Intake gates grant correct roles | feature | `php artisan test --compact tests/Feature/Intake/IntakeGatesTest.php -x` | Wave 0 |
| OP-04 | Operator redirected to intake station after login | feature | `php artisan test --compact tests/Feature/Auth/OperatorRedirectTest.php -x` | Wave 0 |
| OP-05 | Intake station page renders for operator | feature | `php artisan test --compact tests/Feature/Intake/IntakeStationTest.php -x` | Wave 0 |
| OP-06 | Intake station forbidden for responder/dispatcher | feature | `php artisan test --compact tests/Feature/Intake/IntakeStationTest.php -x` | Wave 0 |
| OP-07 | Triage action transitions PENDING to TRIAGED | feature | `php artisan test --compact tests/Feature/Intake/TriageIncidentTest.php -x` | Wave 0 |
| OP-08 | Triage form validation rules enforced | feature | `php artisan test --compact tests/Feature/Intake/TriageIncidentTest.php -x` | Wave 0 |
| OP-11 | Override priority requires supervisor/admin gate | feature | `php artisan test --compact tests/Feature/Intake/IntakeGatesTest.php -x` | Wave 0 |
| OP-11 | Recall incident requires supervisor/admin gate | feature | `php artisan test --compact tests/Feature/Intake/IntakeGatesTest.php -x` | Wave 0 |
| OP-15 | Operator authorized on dispatch.incidents channel | feature | `php artisan test --compact tests/Feature/Broadcasting/ChannelAuthTest.php -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=Intake`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Intake/IntakeGatesTest.php` -- covers OP-03, OP-11
- [ ] `tests/Feature/Intake/IntakeStationTest.php` -- covers OP-05, OP-06
- [ ] `tests/Feature/Intake/TriageIncidentTest.php` -- covers OP-07, OP-08
- [ ] `tests/Feature/Auth/OperatorRedirectTest.php` -- covers OP-04
- [ ] `tests/Feature/Broadcasting/ChannelAuthTest.php` -- covers OP-15
- [ ] `database/factories/UserFactory.php` needs `operator()` state method

## Sources

### Primary (HIGH confidence)
- Codebase inspection: `app/Enums/UserRole.php`, `app/Enums/IncidentStatus.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/EnsureUserHasRole.php`
- Codebase inspection: `routes/web.php`, `routes/channels.php`, `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`
- Codebase inspection: `resources/css/app.css`, `resources/js/layouts/app/AppSidebarLayout.vue`, `resources/js/components/AppSidebar.vue`
- Codebase inspection: `resources/js/composables/useWebSocket.ts`, `resources/js/composables/usePrioritySuggestion.ts`, `resources/js/composables/useGeocodingSearch.ts`
- Codebase inspection: `resources/js/pages/incidents/Queue.vue`, `resources/js/pages/incidents/Create.vue`, `resources/js/components/incidents/ChannelMonitor.vue`
- Codebase inspection: `app/Http/Controllers/IncidentController.php`, `app/Http/Requests/StoreIncidentRequest.php`
- Codebase inspection: `app/Events/IncidentCreated.php`, `app/Events/IncidentStatusChanged.php`
- Codebase inspection: `resources/js/types/auth.ts`, `resources/js/types/incident.ts`
- Design system: `docs/IRMS-Intake-Design-System.md` -- complete specification
- Project skills: `echo-vue-development`, `inertia-vue-development`, `tailwindcss-development`, `pest-testing`

### Secondary (MEDIUM confidence)
- Tailwind CSS v4 `@theme` directive -- verified via existing `app.css` usage and skill documentation
- Fortify `authenticatedRedirectUsing` -- documented feature in Fortify v1, consistent with project's FortifyServiceProvider pattern
- `@laravel/echo-vue` composables (`useEcho`, `useConnectionStatus`) -- verified via existing usage in Queue.vue and useWebSocket.ts

### Tertiary (LOW confidence)
- Dark mode token derivation: No design system spec for dark mode -- must be manually derived by inverting surface/text hierarchy. Needs visual validation.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all libraries already in use, no new dependencies
- Architecture: HIGH -- patterns directly extrapolated from existing codebase, design system is prescriptive
- Pitfalls: HIGH -- identified from direct codebase inspection (channel enum mismatch, Fortify home, gate overlap, channel auth)
- Design system adoption: MEDIUM -- light mode fully specified, dark mode requires derivation
- PostgreSQL enum migration: MEDIUM -- need to verify column types from original migrations

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable -- no external dependency changes expected)
