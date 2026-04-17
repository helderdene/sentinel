# Phase 10: Update All Pages Design to Match IRMS Intake Design System - Research

**Researched:** 2026-03-14
**Domain:** CSS design system migration, Tailwind CSS v4 token alignment, Vue component restyling
**Confidence:** HIGH

## Summary

Phase 10 is a visual restyling phase with no backend changes. The goal is to bring all existing pages (auth, admin, settings, dashboard, incidents, analytics) into alignment with the IRMS Intake Design System documented at `docs/IRMS-Intake-Design-System.md`. The Intake Station (Phase 8), Citizen Reporting App (Phase 9), Dispatch Console, and Responder Station already use the design system tokens. This phase closes the visual gap.

The primary implementation strategy is a **CSS-first, cascade-down approach**: update the Shadcn/Reka UI CSS variables in `app.css` to map to design system tokens, then restyle layouts, then individual pages. Because Shadcn components consume CSS variables (`--background`, `--foreground`, `--border`, etc.), remapping these variables to design system values provides app-wide impact from a single file change. Page-level work focuses on replacing hardcoded `neutral-*`, `zinc-*`, and HSL values with `t-*` token classes.

**Primary recommendation:** Start with CSS variable remapping in `app.css` (highest leverage), then consolidate auth layouts, then restyle the sidebar layout, then sweep individual page groups. Dispatch and Responder get token alignment only -- no layout changes.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **Full design system treatment:** Auth pages (7), Settings pages (4), Admin pages (6), Dashboard (1), Incidents pages (4), Analytics pages (3) -- full restyle with typography, spacing, elevation, shadow scale, and component patterns
- **Token alignment only:** Dispatch Console (1) and Responder Station (1) -- swap colors/fonts to design system tokens but keep existing layout structure, custom UX, and purpose-built interfaces intact
- **Shadcn/Reka UI:** Restyle existing components in `components/ui/` to use design system tokens -- NOT replace with custom components. Keeps Reka UI accessibility + behavior, changes the visual layer
- **Focus/hover states:** Override Shadcn ring utility with design system's combined `border-color: #2563eb + box-shadow: 0 0 0 3px rgba(37,99,235,.10)` focus style; hover states use t-border-med
- **Shadow scale:** Adopt full 5-level shadow scale with border+shadow pairing (see CONTEXT.md for exact values)
- **Icons:** Keep Lucide icons for non-intake pages. Custom IntakeIcon* SVGs remain intake-specific only
- **Auth pages:** Single unified layout with centered card, CDRRMO branding (icon 52x52, "CDRRMO Butuan City" DM Sans 600, subtitle). Remove unused auth layout variants (AuthSimpleLayout, AuthSplitLayout, AuthCardLayout). Level 4 shadow, 14px border-radius, fadeUp entrance animation
- **Clean login, no role display** -- role-based UI appears only after authentication
- **Sidebar layout:** Restyle AppSidebarLayout with design system tokens. Sidebar background t-surface, borders t-border, nav items t-text/t-text-mid, active state t-accent with t-accentLo background. Section labels Space Mono uppercase 9px. CDRRMO icon + "IRMS" text at top, user chip at bottom. Content area background t-bg, content cards use t-surface
- **Data tables:** t-surface background, Level 1 shadow, t-border borders, 7px border-radius. Column headers Space Mono uppercase 9px. Priority/status badges matching design system colors. Border-left colored indicators where appropriate
- Total pages affected: ~30 Vue files across 5 layout types

### Claude's Discretion
- Exact CSS variable mapping between Shadcn HSL tokens and design system tokens
- Dark mode token values for pages beyond what's already defined in app.css
- Specific border-radius values per component (within design system scale: 3-16px)
- Animation timing for auth page transitions
- Sidebar collapse/expand behavior styling
- Table row border-radius implementation approach (border-collapse vs separate)
- Settings page form layout within design system patterns
- Analytics chart styling (colors from design system palette)
- Page transition animations between routes
- Breadcrumb component styling

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Tailwind CSS | v4 | Utility-first CSS with `@theme inline` | Already installed; design tokens use CSS custom properties with Tailwind indirection |
| Reka UI | current | Headless component primitives | Already installed; Shadcn components wrap Reka UI for accessibility |
| class-variance-authority | current | Variant-based component styling | Already installed; Button, Badge use CVA for variant management |
| DM Sans | Google Font | Primary content font | Already imported in app.css |
| Space Mono | Google Font | Data/code/labels font | Already imported in app.css |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Lucide Vue Next | current | Icon library | All non-intake pages (per locked decision) |
| color-mix() | CSS native | Opacity tints from CSS variables | Established Phase 8 pattern for priority/channel/accent backgrounds |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| CSS variable remapping | Rewriting each component | Remapping is higher leverage; one file change cascades to all Shadcn components |
| border-separate for table rows | border-collapse | border-separate allows per-row border-radius; requires explicit border handling |

**Installation:**
No new packages needed. All libraries already installed.

## Architecture Patterns

### Cascade Strategy (Critical)

The restyling has three layers of impact, ordered by leverage:

```
Layer 1: CSS Variables (app.css)           -- Highest leverage
   Maps Shadcn HSL tokens to design system hex values
   Affects ALL Shadcn components automatically

Layer 2: Layout Components                 -- Medium leverage
   AuthLayout, AppSidebarLayout, AppSidebarHeader
   Affects all pages using that layout

Layer 3: Individual Pages                  -- Lowest leverage
   Dashboard.vue, Users.vue, Index.vue, etc.
   Page-specific styling adjustments
```

### CSS Variable Remapping Pattern

**Current state:** Shadcn uses HSL variables (`--background: hsl(0 0% 100%)`) that are independent of design system tokens (`--t-bg: #f4f6f9`). Two parallel systems exist.

**Target state:** Shadcn variables point to design system token values so all Shadcn components inherit the design system look automatically.

```css
/* BEFORE (current) */
:root {
    --background: hsl(0 0% 100%);
    --foreground: hsl(0 0% 3.9%);
    --border: hsl(0 0% 92.8%);
    --card: hsl(0 0% 100%);
    --t-bg: #f4f6f9;
    --t-surface: #ffffff;
    --t-border: #e2e8f0;
}

/* AFTER (target) */
:root {
    --background: var(--t-bg);          /* #f4f6f9 -- page background */
    --foreground: var(--t-text);        /* #0f172a -- primary text */
    --border: var(--t-border);          /* #e2e8f0 -- default border */
    --input: var(--t-border);           /* #e2e8f0 -- input border */
    --ring: var(--t-accent);            /* #2563eb -- focus ring */
    --card: var(--t-surface);           /* #ffffff -- card background */
    --card-foreground: var(--t-text);
    --popover: var(--t-surface);
    --popover-foreground: var(--t-text);
    --primary: var(--t-brand);          /* #1e3a6e -- primary buttons */
    --primary-foreground: #ffffff;
    --secondary: var(--t-surface-alt);  /* #f8fafc -- secondary bg */
    --secondary-foreground: var(--t-text-mid);
    --muted: var(--t-surface-alt);
    --muted-foreground: var(--t-text-dim);
    --accent: var(--t-surface-alt);
    --accent-foreground: var(--t-text);
    --destructive: var(--t-p1);         /* #dc2626 -- red */
    --sidebar-background: var(--t-surface);
    --sidebar-foreground: var(--t-text-mid);
    --sidebar-primary: var(--t-brand);
    --sidebar-primary-foreground: #ffffff;
    --sidebar-accent: color-mix(in srgb, var(--t-accent) 8%, transparent);
    --sidebar-accent-foreground: var(--t-accent);
    --sidebar-border: var(--t-border);
}
```

The same pattern applies to `.dark` block using the existing dark token values.

### Auth Layout Consolidation Pattern

**Current:** 3 auth layout variants (AuthSimpleLayout, AuthCardLayout, AuthSplitLayout) with AuthLayout.vue pointing to AuthSimpleLayout.

**Target:** Single auth layout with CDRRMO branding, centered card, design system tokens.

```
resources/js/layouts/
  AuthLayout.vue          -- Rebuilt: centered card with CDRRMO branding
  auth/
    AuthSimpleLayout.vue  -- DELETE (unused after consolidation)
    AuthCardLayout.vue    -- DELETE (unused after consolidation)
    AuthSplitLayout.vue   -- DELETE (unused after consolidation)
```

Auth page structure:
```html
<div class="bg-t-bg min-h-svh flex items-center justify-center">
  <!-- Centered card -->
  <div class="bg-t-surface rounded-[14px] border border-t-border
              shadow-[0_4px_24px_rgba(0,0,0,.08)] w-full max-w-md p-10
              animate-[fadeUp_400ms_ease]">
    <!-- CDRRMO branding -->
    <div class="flex flex-col items-center gap-2 mb-8">
      <div class="size-[52px] ..."><!-- Brand icon --></div>
      <span class="font-sans text-base font-semibold text-t-brand">
        CDRRMO Butuan City
      </span>
      <span class="font-sans text-xs text-t-text-dim">
        IRMS -- Incident Response Management System
      </span>
    </div>
    <!-- Form slot -->
    <slot />
  </div>
</div>
```

### Sidebar Layout Restyling Pattern

**Current:** AppSidebar uses default Shadcn sidebar styling with `sidebar-*` CSS variables. AppLogo shows "Laravel Starter Kit" text.

**Target:** Sidebar uses design system tokens. AppLogo shows CDRRMO icon + "IRMS" text. Content area has t-bg background.

Key changes:
1. **AppLogo.vue:** Replace "Laravel Starter Kit" with CDRRMO icon + "IRMS" branding
2. **Sidebar CSS variables:** Already remapped in Layer 1 (sidebar-background -> t-surface, sidebar-border -> t-border, etc.)
3. **NavMain section labels:** Add Space Mono uppercase styling for group labels
4. **Content area:** AppSidebarLayout wraps `<slot />` in a container with `bg-t-bg` background
5. **Page-level header:** AppSidebarHeader gets design system border and typography tokens

### Data Table Restyling Pattern

**Current:** Tables use `bg-muted/50`, `border-neutral-*`, `hover:bg-muted/30` classes.

**Target:** Design system table with Level 1 shadows, Space Mono headers, colored border-left indicators.

```html
<!-- Table wrapper -->
<div class="overflow-hidden rounded-[7px] border border-t-border bg-t-surface
            shadow-[0_1px_3px_rgba(0,0,0,.04)]">
  <table class="w-full text-sm">
    <thead>
      <tr class="border-b border-t-border">
        <th class="px-4 py-3 font-mono text-[9px] font-bold tracking-[2px]
                   text-t-text-faint uppercase">
          COLUMN
        </th>
      </tr>
    </thead>
    <tbody>
      <tr class="border-b border-t-border hover:bg-t-surface-alt transition-colors">
        <td class="px-4 py-3 font-sans text-sm text-t-text">Data</td>
      </tr>
    </tbody>
  </table>
</div>
```

### Anti-Patterns to Avoid
- **Hardcoded neutral-* colors:** Replace ALL `text-neutral-*`, `bg-neutral-*`, `border-neutral-*`, `bg-zinc-*` with `t-*` token equivalents
- **HSL values in component code:** Use design system tokens, not raw HSL/hex
- **Modifying Reka UI behavior:** Only change visual styling; never alter accessibility props, keyboard handling, or ARIA attributes
- **Breaking existing functionality:** This is purely visual; no prop interface changes, no route changes, no backend changes
- **shadow-sm/shadow-md generic Tailwind shadows:** Use the 5-level shadow scale from the design system

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Focus ring styling | Custom focus CSS per component | Override `--ring` CSS variable + update base `focus-visible` styles | One change propagates to all Shadcn components |
| Dark mode tokens | Per-component dark: overrides | `.dark` CSS variable block with design system dark tokens | Already established pattern from Phase 8 |
| Badge variants | New badge component | Restyle existing `Badge.vue` CVA variants with design system colors | Preserves Reka UI accessibility |
| Table border-radius | Nested div wrappers | `border-separate border-spacing-0` + `first:rounded-tl-[7px]` approach | CSS-only, no markup changes |
| Opacity tints | rgba() with hardcoded values | `color-mix(in srgb, var(--t-accent) 8%, transparent)` | Works with CSS variables, established Phase 8 pattern |

**Key insight:** The highest-leverage approach is remapping Shadcn CSS variables. Fighting component-level styles when the variable layer can do the work is wasted effort.

## Common Pitfalls

### Pitfall 1: CSS Variable Circular References
**What goes wrong:** Mapping `--background: var(--t-bg)` when `--color-background: var(--background)` AND `--color-t-bg: var(--t-bg)` creates indirection but NOT circularity in this project's setup. However, if someone maps `--t-bg: var(--background)`, it creates a circular reference and the browser silently falls back to initial value (transparent).
**Why it happens:** The `@theme inline` block creates `--color-*` aliases, and `:root` defines the actual values. Mixing the indirection levels can cause silent failures.
**How to avoid:** Only remap in ONE direction: Shadcn variables point TO design system tokens. Never the reverse. Design system tokens (`--t-*`) remain as authoritative hex values.
**Warning signs:** Elements rendering with transparent/missing backgrounds in dev tools.

### Pitfall 2: Dark Mode Token Completeness
**What goes wrong:** Light mode looks correct but dark mode has missing or mismatched colors because not all Shadcn variables were remapped in the `.dark` block.
**Why it happens:** The `.dark` block must mirror every variable from `:root`. Easy to forget sidebar-*, chart-*, or destructive-* variables.
**How to avoid:** Update `:root` and `.dark` blocks together. After remapping, verify dark mode manually for every page group.
**Warning signs:** Elements appearing invisible or unreadable in dark mode.

### Pitfall 3: Shadcn Component Class Overrides Not Applying
**What goes wrong:** Adding Tailwind classes to Shadcn components like `<Button class="bg-t-brand">` doesn't override the default CVA styles because of CSS specificity.
**Why it happens:** CVA-generated classes and Tailwind utility classes may have equal specificity; order in the stylesheet determines which wins.
**How to avoid:** Prefer CSS variable changes (Layer 1) over class-level overrides. When class overrides are needed, use `!important` sparingly or modify the CVA variant definition directly.
**Warning signs:** Styles not visually applying despite being present in the DOM class list.

### Pitfall 4: border-collapse Breaks Row Border-Radius
**What goes wrong:** Applying `border-radius` to `<tr>` elements has no effect when the table uses `border-collapse: collapse` (Tailwind's default).
**Why it happens:** The CSS spec ignores border-radius on internal table elements in collapsed border mode.
**How to avoid:** Use `border-separate border-spacing-0` on the `<table>`, then apply border-radius to `<td>` elements at row boundaries (first-child/last-child).
**Warning signs:** Table rows appearing with square corners despite border-radius classes.

### Pitfall 5: Removing Auth Layouts Breaks Existing Page References
**What goes wrong:** Deleting AuthSimpleLayout.vue, AuthCardLayout.vue, AuthSplitLayout.vue causes build errors because some auth pages import them directly.
**Why it happens:** AuthLayout.vue currently delegates to AuthSimpleLayout.vue. If other pages import variants directly (not through AuthLayout.vue), deleting them breaks the build.
**How to avoid:** Search the entire codebase for ALL imports of the auth layout variants before deleting. Update every reference to use the new consolidated AuthLayout.vue.
**Warning signs:** Vite build errors about missing modules.

### Pitfall 6: Sidebar "inset" Variant Styling Conflict
**What goes wrong:** The current sidebar uses `variant="inset"` which applies specific Shadcn CSS that may conflict with design system styling.
**Why it happens:** Shadcn sidebar variants have opinionated CSS including background, border, and spacing rules that may override design system tokens.
**How to avoid:** Review the SidebarInset and SidebarProvider CSS carefully. The CSS variable remapping (Layer 1) should handle most of it, but verify that `variant="inset"` behavior is compatible with t-bg content background.
**Warning signs:** Unexpected padding, border, or background differences between sidebar and content area.

## Code Examples

### CSS Variable Remapping (app.css :root block)
```css
/* Source: IRMS Design System docs/IRMS-Intake-Design-System.md */
:root {
    /* Map Shadcn to design system */
    --background: var(--t-bg);
    --foreground: var(--t-text);
    --card: var(--t-surface);
    --card-foreground: var(--t-text);
    --popover: var(--t-surface);
    --popover-foreground: var(--t-text);
    --primary: var(--t-brand);
    --primary-foreground: #ffffff;
    --secondary: var(--t-surface-alt);
    --secondary-foreground: var(--t-text-mid);
    --muted: var(--t-surface-alt);
    --muted-foreground: var(--t-text-dim);
    --accent: var(--t-surface-alt);
    --accent-foreground: var(--t-text);
    --destructive: var(--t-p1);
    --destructive-foreground: #ffffff;
    --border: var(--t-border);
    --input: var(--t-border);
    --ring: var(--t-accent);

    /* Sidebar tokens */
    --sidebar-background: var(--t-surface);
    --sidebar-foreground: var(--t-text-mid);
    --sidebar-primary: var(--t-brand);
    --sidebar-primary-foreground: #ffffff;
    --sidebar-accent: color-mix(in srgb, var(--t-accent) 8%, transparent);
    --sidebar-accent-foreground: var(--t-accent);
    --sidebar-border: var(--t-border);
    --sidebar-ring: var(--t-accent);

    /* Chart tokens from design system palette */
    --chart-1: var(--t-accent);
    --chart-2: var(--t-online);
    --chart-3: var(--t-p2);
    --chart-4: var(--t-p3);
    --chart-5: var(--t-ch-iot);

    --radius: 0.4375rem; /* 7px -- design system default card radius */

    /* Design system tokens remain unchanged */
    --t-bg: #f4f6f9;
    /* ... all existing --t-* tokens ... */
}
```

### Focus Ring Override (app.css @layer base)
```css
/* Source: IRMS Design System Section 5 - Focus Ring */
@layer base {
    * {
        @apply border-border;
    }
    body {
        @apply bg-background text-foreground;
    }

    /* Design system focus ring: border-color change + box-shadow */
    [data-slot] {
        --tw-ring-color: color-mix(in srgb, var(--t-accent) 10%, transparent);
    }
}
```

### Auth Layout with CDRRMO Branding
```vue
<!-- Source: CONTEXT.md Auth Pages decisions -->
<template>
    <div class="flex min-h-svh items-center justify-center bg-t-bg p-6">
        <div
            class="w-full max-w-md rounded-[14px] border border-t-border
                   bg-t-surface p-10
                   shadow-[0_4px_24px_rgba(0,0,0,.08)]"
            style="animation: fadeUp 400ms ease"
        >
            <!-- CDRRMO branding block -->
            <div class="mb-8 flex flex-col items-center gap-2">
                <div
                    class="flex size-[52px] items-center justify-center
                           rounded-[10px] bg-t-brand/10"
                >
                    <!-- brand icon SVG here -->
                </div>
                <span class="text-base font-semibold text-t-brand">
                    CDRRMO Butuan City
                </span>
                <span class="text-xs text-t-text-dim">
                    IRMS -- Incident Response Management System
                </span>
            </div>

            <!-- Page title -->
            <div class="mb-6 text-center">
                <h1 class="text-lg font-semibold text-t-text">{{ title }}</h1>
                <p v-if="description" class="mt-1 text-sm text-t-text-dim">
                    {{ description }}
                </p>
            </div>

            <slot />
        </div>
    </div>
</template>
```

### Space Mono Section Label Pattern
```html
<!-- Source: IRMS Design System Section 2 - Label Convention -->
<span class="font-mono text-[9px] font-bold tracking-[2px] text-t-text-faint uppercase">
    SECTION LABEL
</span>
```

### Design System Table Row with Priority Indicator
```html
<!-- Source: CONTEXT.md Data Tables decisions -->
<tr class="group border-b border-t-border transition-colors hover:bg-t-surface-alt">
    <td class="relative px-4 py-3">
        <!-- Priority color border-left indicator -->
        <div class="absolute left-0 top-2 bottom-2 w-[3px] rounded-r-full bg-t-p1" />
        <span class="pl-2 font-mono text-[10px] text-t-text-faint">INC-2026-00042</span>
    </td>
    <td class="px-4 py-3 text-sm text-t-text">Structure Fire</td>
    <!-- ... -->
</tr>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Shadcn default HSL variables | Design system hex tokens | Phase 8 (2026-03-13) | Two parallel color systems currently coexist; this phase unifies them |
| Multiple auth layout variants | Single branded auth layout | This phase | Reduces layout code, consistent branding |
| Generic neutral-* Tailwind colors | Semantic t-* tokens | Phase 8 | Colors carry meaning (priority, channel, role, status) |
| shadow-sm / shadow-md generic | 5-level shadow scale | Phase 8 | Precise elevation hierarchy from design system |

**Deprecated/outdated after this phase:**
- `AuthSimpleLayout.vue`, `AuthCardLayout.vue`, `AuthSplitLayout.vue`: Replaced by consolidated AuthLayout.vue
- `AppLogo.vue` "Laravel Starter Kit" branding: Replaced by CDRRMO branding
- All `text-neutral-*`, `bg-neutral-*`, `border-neutral-*`, `bg-zinc-*` classes in affected pages: Replaced by `t-*` tokens
- Shadcn default HSL color values in `:root`: Replaced by design system token references

## Recommended Project Structure

No new directories needed. Changes happen in existing files:

```
resources/
├── css/
│   └── app.css                          # Layer 1: CSS variable remapping
├── js/
│   ├── layouts/
│   │   ├── AuthLayout.vue               # Rebuilt: CDRRMO branded centered card
│   │   ├── auth/                        # DELETE contents after consolidation
│   │   ├── app/
│   │   │   ├── AppSidebarLayout.vue     # Restyled with t-* tokens
│   │   │   └── AppHeaderLayout.vue      # Restyled with t-* tokens
│   │   └── settings/
│   │       └── Layout.vue               # Restyled with t-* tokens
│   ├── components/
│   │   ├── AppLogo.vue                  # CDRRMO icon + "IRMS" text
│   │   ├── AppSidebarHeader.vue         # Design system header bar
│   │   ├── Heading.vue                  # Design system typography
│   │   └── ui/                          # Layer 1 handles most; some need class updates
│   └── pages/
│       ├── auth/*.vue                   # 7 pages: design system treatment
│       ├── settings/*.vue               # 4 pages: design system treatment
│       ├── admin/*.vue                  # 6 pages: design system treatment
│       ├── Dashboard.vue                # Design system treatment
│       ├── incidents/*.vue              # 4 pages: design system treatment
│       ├── analytics/*.vue              # 3 pages: design system treatment
│       ├── dispatch/Console.vue         # Token alignment ONLY
│       └── responder/Station.vue        # Token alignment ONLY
```

## File Inventory (Scope)

### Files to Modify (estimated ~40 files)

**Layer 1 - CSS Foundation (1 file):**
- `resources/css/app.css` -- Remap Shadcn variables to design system tokens

**Layer 2 - Layouts (6 files):**
- `resources/js/layouts/AuthLayout.vue` -- Rebuild with CDRRMO branding
- `resources/js/layouts/app/AppSidebarLayout.vue` -- Add bg-t-bg content wrapper
- `resources/js/layouts/app/AppHeaderLayout.vue` -- Update if used
- `resources/js/layouts/settings/Layout.vue` -- Design system tokens
- `resources/js/components/AppSidebar.vue` -- No structural change; CSS variables cascade
- `resources/js/components/AppSidebarHeader.vue` -- Design system header bar
- `resources/js/components/AppLogo.vue` -- CDRRMO branding

**Layer 3 - Shared Components (3 files):**
- `resources/js/components/Heading.vue` -- Design system typography
- `resources/js/components/NavMain.vue` -- Section label styling
- `resources/js/components/NavUser.vue` -- User chip styling

**Layer 3 - Auth Pages (7 files):**
- Login.vue, Register.vue, ForgotPassword.vue, ResetPassword.vue
- TwoFactorChallenge.vue, VerifyEmail.vue, ConfirmPassword.vue

**Layer 3 - Settings Pages (4 files):**
- Profile.vue, Password.vue, TwoFactor.vue, Appearance.vue

**Layer 3 - Admin Pages (6 files):**
- Users.vue, UserForm.vue, Barangays.vue, BarangayForm.vue
- IncidentTypes.vue, IncidentTypeForm.vue

**Layer 3 - Other Pages (8 files):**
- Dashboard.vue
- incidents/Index.vue, Create.vue, Queue.vue, Show.vue
- analytics/Dashboard.vue, Heatmap.vue, Reports.vue

**Files to Delete (3 files):**
- `resources/js/layouts/auth/AuthSimpleLayout.vue`
- `resources/js/layouts/auth/AuthCardLayout.vue`
- `resources/js/layouts/auth/AuthSplitLayout.vue`

**Token alignment only (2 pages, already mostly using tokens):**
- `resources/js/pages/dispatch/Console.vue` -- Verify token usage
- `resources/js/pages/responder/Station.vue` -- Verify token usage

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=testName` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
This phase is purely visual (CSS/HTML changes). There are no new behaviors or backend changes to test programmatically. Existing tests must continue passing to verify nothing was broken.

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| N/A | Auth pages render correctly | Feature | `php artisan test --compact tests/Feature/Auth/` | Existing |
| N/A | Settings pages render correctly | Feature | `php artisan test --compact tests/Feature/Settings/` | Existing |
| N/A | Admin pages render correctly | Feature | `php artisan test --compact tests/Feature/Admin/` | Existing |
| N/A | TypeScript compiles cleanly | Build | `npm run types:check` | N/A |
| N/A | ESLint passes | Build | `npm run lint` | N/A |
| N/A | Frontend builds | Build | `npm run build` | N/A |

### Sampling Rate
- **Per task commit:** `php artisan test --compact` + `npm run build`
- **Per wave merge:** Full suite + `npm run types:check` + `npm run lint`
- **Phase gate:** Full suite green + successful build before `/gsd:verify-work`

### Wave 0 Gaps
None -- existing test infrastructure covers all phase requirements. No new test files needed since this is a visual-only change. The verification is: existing tests still pass + frontend builds successfully + visual inspection.

## Open Questions

1. **Table row border-radius implementation**
   - What we know: `border-collapse: collapse` prevents border-radius on tr/td. The design system specifies 7px border-radius for table rows.
   - What's unclear: Whether to use `border-separate border-spacing-0` (pure CSS) or wrap each row group in a div (markup change).
   - Recommendation: Use `border-separate border-spacing-0` on the table with radius on first/last td elements. This is CSS-only and avoids semantic HTML changes.

2. **Analytics chart colors**
   - What we know: Chart.js datasets currently use hardcoded hex colors matching the design system palette (`#2563eb`, `#7c3aed`, `#16a34a`, etc.)
   - What's unclear: Whether to switch chart colors to CSS variable references or keep hardcoded (Chart.js doesn't natively read CSS variables).
   - Recommendation: Keep hardcoded hex values in JS for chart datasets since they already match the design system palette. Update `--chart-*` CSS variables for any Shadcn chart components.

3. **Sidebar collapse behavior styling**
   - What we know: The sidebar uses `collapsible="icon"` with Shadcn's built-in collapse behavior.
   - What's unclear: Whether the collapsed state needs special design system treatment beyond what CSS variable remapping provides.
   - Recommendation: CSS variable remapping should handle it. Verify visually after Layer 1 changes and address only if needed.

## Sources

### Primary (HIGH confidence)
- `docs/IRMS-Intake-Design-System.md` -- Authoritative design system specification
- `resources/css/app.css` -- Current CSS variable definitions, both Shadcn and design system
- `.planning/phases/10-update-all-pages-design-to-match-irms-intake-design-system/10-CONTEXT.md` -- User decisions
- Phase 8 implementation (`resources/js/layouts/IntakeLayout.vue`, `components/intake/*`) -- Reference implementation of design system in code

### Secondary (MEDIUM confidence)
- Shadcn Vue component source files (`components/ui/*.vue`) -- Current styling patterns
- Existing page components -- Current class usage patterns to be replaced

### Tertiary (LOW confidence)
- None -- all findings verified against codebase

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all libraries already installed and in use
- Architecture: HIGH -- cascade strategy verified against actual codebase structure
- Pitfalls: HIGH -- identified from direct codebase inspection of CSS variable chains and Shadcn component internals
- File inventory: HIGH -- enumerated from actual file system

**Research date:** 2026-03-14
**Valid until:** 2026-04-14 (stable -- no external dependency changes expected)
