# Phase 14: Update Design System to Sentinel Branding and Rename App - Research

**Researched:** 2026-03-15
**Domain:** CSS design tokens, SVG assets, font loading, PWA manifest, app renaming
**Confidence:** HIGH

## Summary

Phase 14 is a pure visual rebrand with no feature changes. The existing CSS token architecture (established in Phase 10) provides a single-point-of-change cascade: `--t-*` tokens in `:root`/`.dark` blocks flow through Shadcn variable remapping to all components. This means the color migration is primarily a token-value swap in `resources/css/app.css` and `report-app/src/assets/tokens.css`. However, there are significant secondary touchpoints: 60+ components use `font-mono` (currently Space Mono, changing to DM Mono), 11 files contain hardcoded priority hex colors for MapLibre layers and Chart.js, and 15+ files reference "IRMS" or "CDRRMO" strings.

The brand guide at `docs/hd-sentinel-brand_1.html` is a complete HTML reference containing: extractable SVG shields (simplified 26x30 nav icon and full 600x680 hero shield with animations), exact color values for both dark and light themes, badge styling with pill+dot+border pattern, and typography specs. The dark theme uses rgba surfaces that must be pre-computed to solid hex to avoid Shadcn component stacking issues (as the user decided).

**Primary recommendation:** Execute in three waves: (1) tokens + fonts + focus ring, (2) SVG assets + logo + auth + sidebar + naming sweep, (3) hardcoded color sweep (maps, charts, responder) + report-app + PWA.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- App is called "Sentinel" (not "HD Sentinel") everywhere
- Auth page shows: shield icon + "SENTINEL" (Bebas Neue) + subtitle "Incident Response Management System"
- Sidebar shows: shield icon + "SENTINEL" (DM Sans 500, single color, no accent)
- All CDRRMO Butuan City references removed entirely -- app becomes a generic IRMS product
- PWA manifest: name "Sentinel", short_name "Sentinel"
- Page title tags: "Sentinel" replacing "IRMS"
- Citizen reporting app (report-app/) also rebranded to Sentinel
- Bebas Neue: Auth page "SENTINEL" title only -- not used elsewhere in the app UI
- DM Sans: Remains body/heading font (already in use, no change)
- DM Mono: Replaces Space Mono everywhere -- incident codes, timestamps, section labels, badges, all monospace UI elements
- Nav section labels: DM Mono, 10px, 2-3px letter-spacing (matching brand guide spec, slightly larger than current 9px)
- Load Bebas Neue and DM Mono via Google Fonts (alongside existing DM Sans)
- Full migration from Tailwind slate palette to Sentinel navy/blue palette
- All --t-* design tokens remapped to Sentinel values
- Dark theme surfaces use solid hex equivalents (not rgba) to avoid stacking transparency issues
- Dark: bg #05101E, surface solid blend, text #FFFFFF, accent #378ADD
- Light: bg #EFF3FA, surface #FFFFFF, text #042C53, accent #185FA5
- Priority colors changed: P1 #A32D2D/#E24B4A, P2 #854F0B/#EF9F27, P3 #0F6E56/#1D9E75, P4 #185FA5/#378ADD
- No grid background or noise overlay textures
- Badge style updated: pill shape with dot indicator, colored border, and tinted background
- Brand color: #042C53 (Command Blue / navy)
- Focus ring: #378ADD (blue-mid)
- Borders: rgba(55,138,221,0.12) dark / rgba(24,95,165,0.14) light
- Auth page: Full detailed shield SVG with radar rings, crosshairs, signal arcs, and eye -- animated with pulse ring and rotating sweep line
- Sidebar: Simplified shield icon (shield outline + inner circles + dot) at 26x30px
- Favicon: Simplified shield icon at 32x32
- PWA icons: Regenerated with simplified shield on Command Blue (#042C53) background
- Wordmark "SENTINEL" in single color (no accent letter) alongside shield icon
- Brand guide HTML at docs/hd-sentinel-brand_1.html is authoritative reference
- SVG shields should be extracted and used directly (not recreated)

### Claude's Discretion
- Exact solid hex values for dark theme surface (blending rgba on page bg)
- Shadow scale adjustments if needed for the new palette
- Border token adjustments for the new blue-based borders
- How to handle dispatch map marker colors (may need adjustment for new P3/P4)
- Transition approach: whether to update tokens first then sweep components, or section by section

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Tailwind CSS | v4 | Utility-first CSS with @theme inline | Already in use; token cascade via CSS variables |
| DM Sans | Google Fonts | Body/heading font | Already loaded, no change needed |
| DM Mono | Google Fonts | Monospace: codes, timestamps, labels | Replaces Space Mono per brand guide |
| Bebas Neue | Google Fonts | Display: auth page "SENTINEL" title | Brand guide display typeface |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| vite-plugin-pwa | current | PWA manifest regeneration | Update name, theme_color, icon references |

### No New Dependencies
This phase requires zero new npm or composer packages. All changes are CSS token values, SVG assets, font URLs, and string replacements.

**Font Loading (updated Google Fonts URL):**
```html
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:ital,wght@0,300;0,400;0,500;1,300&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
```

## Architecture Patterns

### Token Cascade (Established Pattern)
The Phase 10 token architecture is the key enabler for this rebrand:
```
--t-* tokens (authoritative hex values)
  -> Shadcn variables (--background, --foreground, etc.)
    -> @theme inline (--color-* for Tailwind utilities)
      -> Component classes (bg-t-bg, text-t-text, etc.)
```

**Changing `--t-bg: #f4f6f9` to `--t-bg: #EFF3FA` in `:root` automatically updates:**
- `--background` (Shadcn)
- `--color-background` / `--color-t-bg` (Tailwind)
- Every component using `bg-background` or `bg-t-bg`

### Files That Change (Complete Inventory)

**Tier 1: Token files (cascade propagation)**
| File | Changes |
|------|---------|
| `resources/css/app.css` | All `--t-*` values in `:root` and `.dark`, `--font-mono`, focus ring, shadow scale, p1-flash color |
| `report-app/src/assets/tokens.css` | All `--t-*` values in `:root` and `@media (prefers-color-scheme: dark)` |
| `report-app/src/assets/app.css` | `--font-mono` value |

**Tier 2: Font loading**
| File | Changes |
|------|---------|
| `resources/views/app.blade.php` | Google Fonts URL (add Bebas Neue, replace Space Mono with DM Mono), HTML background-color for dark mode, title default |
| `report-app/index.html` | Google Fonts URL, title text, theme-color meta |

**Tier 3: SVG/Logo assets**
| File | Changes |
|------|---------|
| `resources/js/components/AppLogo.vue` | Replace shield SVG + change "IRMS" to "SENTINEL" |
| `resources/js/layouts/AuthLayout.vue` | Replace CDRRMO branding with Sentinel animated shield + Bebas Neue title |
| `public/favicon.svg` | Replace with simplified shield SVG |
| `public/favicon.ico` | Regenerate from new favicon.svg |
| `public/pwa-192x192.png` | Regenerate with new shield on #042C53 |
| `public/pwa-512x512.png` | Regenerate with new shield on #042C53 |
| `public/maskable-icon-512x512.png` | Regenerate with new shield on #042C53 |
| `public/apple-touch-icon.png` | Regenerate with new shield on #042C53 |

**Tier 4: String replacements ("IRMS"/"CDRRMO" -> "Sentinel")**
| File | What Changes |
|------|-------------|
| `.env` | `APP_NAME="Sentinel"` |
| `vite.config.ts` | PWA manifest name, short_name, description, theme_color |
| `resources/js/sw.ts` | Default notification title, tag prefix |
| `resources/js/components/PushPermissionPrompt.vue` | "IRMS" -> "Sentinel" in prompt text |
| `resources/js/components/dispatch/DispatchTopbar.vue` | "IRMS" -> "SENTINEL" |
| `resources/js/components/dispatch/DispatchStatusbar.vue` | "CDRRMO BUTUAN CITY" -> "SENTINEL" |
| `resources/js/components/intake/IntakeTopbar.vue` | "IRMS" -> "SENTINEL" |
| `resources/js/components/intake/IntakeStatusbar.vue` | "CDRRMO - BUTUAN CITY" -> "SENTINEL" |
| `report-app/src/views/HomeView.vue` | CDRRMO references -> Sentinel |
| `report-app/src/views/AboutView.vue` | CDRRMO/IRMS references -> Sentinel |
| `report-app/src/views/ReportConfirmView.vue` | CDRRMO references -> Sentinel |
| `report-app/src/assets/tokens.css` | Comment header |

**Tier 5: Hardcoded priority/status colors**
| File | What Changes |
|------|-------------|
| `resources/js/composables/useDispatchMap.ts` | PRIORITY_COLORS, STATUS_COLORS, ICON_COLORS arrays |
| `resources/js/composables/useAnalyticsMap.ts` | Priority color map in popup rendering |
| `resources/js/pages/analytics/Dashboard.vue` | KPI chart colors |
| `resources/js/components/analytics/KpiCard.vue` | KPI indicator colors |
| `resources/js/components/analytics/KpiLineChart.vue` | Chart line colors |
| `resources/js/components/responder/NavTab.vue` | Priority color map |
| `resources/js/components/responder/OutcomeSheet.vue` | Outcome icon colors |
| `resources/js/components/responder/StandbyScreen.vue` | Hardcoded colors |
| `resources/js/components/responder/StatusButton.vue` | Status colors |
| `resources/js/components/intake/QueueRow.vue` | Override/recall icon colors |
| `resources/js/composables/useAlertSystem.ts` | P1 flash color |

**Tier 6: Nav section label typography**
| File | What Changes |
|------|-------------|
| `resources/js/components/NavMain.vue` | Section label: `text-[9px]` -> `text-[10px]`, `tracking-[2px]` -> `tracking-[2.5px]` |

### Computed Dark Theme Solid Hex Values

The brand guide specifies rgba surfaces for dark mode. Per user decision, these must be pre-computed to solid hex blended on the page background (#05101E):

| Token | Brand Guide RGBA | Solid Hex (on #05101E) | Purpose |
|-------|-----------------|----------------------|---------|
| `--t-bg` | N/A | `#05101E` | Page background |
| `--t-surface` | `rgba(4,44,83,0.5)` | `#041E38` | Card/panel surface |
| `--t-surface-alt` | `rgba(4,44,83,0.3)` | `#04182D` | Alternate surface |
| (input bg) | `rgba(4,44,83,0.6)` | `#04203D` | Input backgrounds (if needed) |

### Complete Token Mapping

**Light Theme (:root)**
| Token | Current | Sentinel | Source |
|-------|---------|----------|--------|
| `--t-bg` | `#f4f6f9` | `#EFF3FA` | Brand guide `body.light --bg-page` |
| `--t-surface` | `#ffffff` | `#FFFFFF` | Brand guide `body.light --bg-surface` |
| `--t-surface-alt` | `#f8fafc` | `#F0F4FA` | Brand guide `body.light --bg-surface2` |
| `--t-text` | `#0f172a` | `#042C53` | Brand guide `body.light --text-primary` |
| `--t-text-mid` | `#334155` | `#185FA5` | Brand guide `body.light --text-secondary` |
| `--t-text-dim` | `#64748b` | `rgba(4,44,83,0.4)` as hex | Brand guide `body.light --text-muted` |
| `--t-text-faint` | `#94a3b8` | `rgba(24,95,165,0.4)` as hex | Derived from brand |
| `--t-border` | `#e2e8f0` | `rgba(24,95,165,0.14)` as hex | Brand guide `body.light --border-soft` |
| `--t-border-med` | `#cbd5e1` | `rgba(24,95,165,0.3)` as hex | Brand guide `body.light --border-mid` |
| `--t-border-foc` | `#2563eb` | `#185FA5` | Brand guide `body.light --border-focus` |
| `--t-brand` | `#1e3a6e` | `#042C53` | Command Blue |
| `--t-accent` | `#2563eb` | `#185FA5` | Signal Blue |
| `--t-online` | `#16a34a` | `#1D9E75` | Response Teal |
| `--t-queued` | `#2563eb` | `#185FA5` | Signal Blue |
| `--t-p1` | `#dc2626` | `#A32D2D` | Critical Red (light variant) |
| `--t-p2` | `#ea580c` | `#854F0B` | Dispatch Amber (light variant) |
| `--t-p3` | `#ca8a04` | `#0F6E56` | Response Teal (light variant) |
| `--t-p4` | `#16a34a` | `#185FA5` | Signal Blue (light variant) |

**Dark Theme (.dark)**
| Token | Current | Sentinel | Source |
|-------|---------|----------|--------|
| `--t-bg` | `#0f172a` | `#05101E` | Brand guide `--bg-page` |
| `--t-surface` | `#1e293b` | `#041E38` | Computed solid |
| `--t-surface-alt` | `#334155` | `#04182D` | Computed solid |
| `--t-text` | `#f8fafc` | `#FFFFFF` | Brand guide `--text-primary` |
| `--t-text-mid` | `#cbd5e1` | `#B5D4F4` | Brand guide `--text-secondary` |
| `--t-text-dim` | `#94a3b8` | `rgba(181,212,244,0.5)` as hex | Brand guide `--text-muted` |
| `--t-text-faint` | `#64748b` | `rgba(55,138,221,0.4)` as hex | Derived from brand |
| `--t-border` | `#334155` | `rgba(55,138,221,0.12)` as hex | Brand guide `--border-soft` |
| `--t-border-med` | `#475569` | `rgba(55,138,221,0.25)` as hex | Brand guide `--border-mid` |
| `--t-border-foc` | `#3b82f6` | `#378ADD` | Brand guide `--border-focus` |

**Note on rgba-to-hex conversion for non-surface tokens:** For border and text-dim/faint tokens, the rgba values should also be computed to solid hex on their likely backgrounds. The implementer should blend:
- Dark borders: `rgba(55,138,221,0.12)` on `#05101E` -> approximately `#0B1A2D`
- Dark text-muted: `rgba(181,212,244,0.5)` -> this is text color, use directly as `#5A7B9E` (blend on page bg) or keep as-is since text doesn't stack

**Priority Colors (for dark theme / MapLibre / hardcoded contexts):**
| Priority | Light (token) | Dark (display) | Map marker |
|----------|--------------|----------------|------------|
| P1 | `#A32D2D` | `#E24B4A` | `#E24B4A` |
| P2 | `#854F0B` | `#EF9F27` | `#EF9F27` |
| P3 | `#0F6E56` | `#1D9E75` | `#1D9E75` |
| P4 | `#185FA5` | `#378ADD` | `#378ADD` |

**Important:** The dispatch map always uses a dark base style. MapLibre hardcoded colors should use the bright (dark-theme) variants: `#E24B4A`, `#EF9F27`, `#1D9E75`, `#378ADD`.

### SVG Assets to Extract

**1. Simplified Shield (sidebar icon, favicon) - from brand guide nav:**
```html
<!-- 26x30 simplified shield - extract from brand guide line 1212 -->
<svg width="26" height="30" viewBox="0 0 26 30" fill="none">
  <path d="M13 1L25 6.5V16C25 22.5 13 29 13 29C13 29 1 22.5 1 16V6.5L13 1Z"
    fill="#0C447C" stroke="#378ADD" stroke-width="1.2"/>
  <path d="M13 3L23 7.5V15.5C23 21 13 27 13 27C13 27 3 21 3 15.5V7.5L13 3Z"
    fill="none" stroke="rgba(55,138,221,0.25)" stroke-width="0.5"/>
  <circle cx="13" cy="17" r="5.5" fill="none" stroke="#378ADD" stroke-width="0.8" opacity="0.4"/>
  <circle cx="13" cy="17" r="3.2" fill="#378ADD" opacity="0.9"/>
  <circle cx="13" cy="17" r="1.3" fill="#E6F1FB"/>
  <line x1="9" y1="9" x2="17" y2="9" stroke="#378ADD" stroke-width="0.7" opacity="0.5"/>
  <line x1="7.5" y1="11.5" x2="18.5" y2="11.5" stroke="#378ADD" stroke-width="0.5" opacity="0.3"/>
</svg>
```

**2. Full Animated Shield (auth page) - from brand guide hero:**
The hero shield SVG at line 1245-1330 of the brand guide contains:
- Shield outline with radial gradient glow
- 6 concentric radar rings with increasing opacity
- Crosshair lines with tick marks
- Signal arcs at shield top
- Eye with iris, pupil, and highlight
- CSS animated pulse ring (`pulseRing 2.4s ease-out infinite`)
- CSS animated sweep line (`sweep 4s linear infinite`)

This is a large SVG (~85 lines) that should be extracted directly. The CSS animations (`pulseRing` and `sweep` keyframes) are already defined in the brand guide and need to be added to `app.css`.

### Badge Style Update

The brand guide defines a new badge pattern:

**Current (PriBadge.vue):**
```css
background: color-mix(in srgb, ${color} 8%, transparent);
/* No border */
```

**Sentinel (from brand guide CSS):**
```css
/* Example for P1 dark: */
background: rgba(163,45,45,0.15);
color: #F09595;
border: 1px solid rgba(163,45,45,0.4);
/* Dot: background #E24B4A */
```

The PriBadge.vue already uses the dot indicator pattern. The main changes are:
1. Add a `border` with `color-mix(in srgb, ${color} 40%, transparent)`
2. Adjust background opacity from 8% to 15%
3. Badge text color uses a lighter tint of the priority color

**Important:** The existing PriBadge and ChBadge components already use CSS variables and `color-mix()` for dynamic color computation. The pattern is sound -- just update the opacity percentages and add border.

### PWA Theme Color

Current: `#0B1120` (dark navy from old design system)
Sentinel: `#042C53` (Command Blue)

Update locations:
- `vite.config.ts` manifest `theme_color` and `background_color`
- `resources/views/app.blade.php` meta `theme-color`
- `report-app/index.html` meta `theme-color`

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| rgba-to-hex conversion | Runtime JS blending | Pre-computed hex values | Stacking transparency is the exact problem user identified |
| Font hosting | Self-hosted font files | Google Fonts CDN | Already using Google Fonts for DM Sans; keep consistent |
| PWA icon generation | Manual SVG-to-PNG conversion | SVG favicon + inline SVG for PWA | SVG favicons are well-supported; PNG icons can be generated once |
| Badge color variants | Per-component color logic | CSS variable + color-mix() pattern | Already established in PriBadge/ChBadge |

## Common Pitfalls

### Pitfall 1: Missing Hardcoded Colors
**What goes wrong:** Tokens update but MapLibre map markers, Chart.js charts, and inline style colors remain the old palette.
**Why it happens:** MapLibre expressions and Chart.js configs use raw hex strings, not CSS variables.
**How to avoid:** Search for every occurrence of old hex colors: `#dc2626`, `#ea580c`, `#ca8a04`, `#16a34a`, `#2563eb` (where used as accent), `#0f172a`, `#1e293b`, `#334155`. The grep analysis found 11 files with hardcoded priority colors.
**Warning signs:** Map markers or charts showing old colors after token update.

### Pitfall 2: Dark Mode Surface Stacking
**What goes wrong:** Shadcn dropdown/popover components render over card surfaces, creating double-transparency artifacts.
**Why it happens:** If `--t-surface` uses rgba, a popover (surface) on a card (surface) gets 2x the tint.
**How to avoid:** Use pre-computed solid hex for ALL dark surface tokens (already decided by user). The computed values are: surface `#041E38`, surface-alt `#04182D`.

### Pitfall 3: Report App Token Drift
**What goes wrong:** Main app rebrands correctly but citizen reporting app keeps old colors.
**Why it happens:** Report app has its own `tokens.css` and `app.css` with duplicated token values.
**How to avoid:** Update both `report-app/src/assets/tokens.css` AND `report-app/src/assets/app.css` in same wave as main CSS.

### Pitfall 4: Font Loading Flash
**What goes wrong:** Bebas Neue loads late, causing "SENTINEL" to flash in fallback font.
**Why it happens:** Google Fonts loaded via `<link>` can be render-blocking or flash.
**How to avoid:** Add `font-display: swap` (already in Google Fonts URL), and Bebas Neue is only used on auth page so flash is acceptable. Could add `preload` hint for Bebas Neue if desired.

### Pitfall 5: P1 Flash Animation Color
**What goes wrong:** The P1 screen flash still uses old red color.
**Why it happens:** `p1-flash` keyframe in `app.css` has hardcoded `rgba(220, 38, 38, 0.3)` (old P1 red).
**How to avoid:** Update to new P1: `rgba(163, 45, 45, 0.3)` or `rgba(226, 75, 74, 0.3)` for the dark variant.

### Pitfall 6: Inline HTML Background Color
**What goes wrong:** Browser shows wrong color during page load before CSS loads.
**Why it happens:** `app.blade.php` has inline `<style>` with `oklch(0.145 0 0)` for dark mode pre-flash.
**How to avoid:** Update inline style to match new dark bg `#05101E`.

### Pitfall 7: Dispatch Map Status Colors
**What goes wrong:** Unit status colors (AVAILABLE green, ON_SCENE yellow) conflict with new P3/P4 colors.
**Why it happens:** Old P3 was yellow (#ca8a04) matching ON_SCENE, and P4 was green (#16a34a) matching AVAILABLE. New palette changes these semantics.
**How to avoid:** Review STATUS_COLORS in useDispatchMap.ts. AVAILABLE should stay green-ish (could use `#1D9E75` teal), ON_SCENE could use `#EF9F27` amber. The unit status colors are independent from priority colors -- update them to use Sentinel palette equivalents that maintain visual distinction.

### Pitfall 8: Focus Ring Color Mismatch
**What goes wrong:** DS-03 focus ring still uses old blue.
**Why it happens:** Focus ring in `app.css` has hardcoded `#2563eb` and `rgba(37, 99, 235, 0.1)`.
**How to avoid:** Update to Sentinel focus: `#378ADD` and `rgba(55, 138, 221, 0.1)`.

## Code Examples

### Token Update Pattern (app.css :root)
```css
/* Source: brand guide docs/hd-sentinel-brand_1.html */
:root {
    --t-bg: #EFF3FA;
    --t-surface: #FFFFFF;
    --t-surface-alt: #F0F4FA;
    --t-text: #042C53;
    --t-text-mid: #185FA5;
    /* ... */
    --t-brand: #042C53;
    --t-accent: #185FA5;
    --t-p1: #A32D2D;
    --t-p2: #854F0B;
    --t-p3: #0F6E56;
    --t-p4: #185FA5;
}

.dark {
    --t-bg: #05101E;
    --t-surface: #041E38;
    --t-surface-alt: #04182D;
    --t-text: #FFFFFF;
    --t-text-mid: #B5D4F4;
    /* ... */
}
```

### Font Stack Update (app.css @theme inline)
```css
@theme inline {
    --font-sans: 'DM Sans', ui-sans-serif, system-ui, sans-serif, ...;
    --font-mono: 'DM Mono', ui-monospace, monospace;
    --font-display: 'Bebas Neue', sans-serif;
    /* ... */
}
```

### Auth Shield Animation Keyframes
```css
/* Source: brand guide hero shield animations */
@keyframes pulseRing {
    0%   { transform: scale(1);   opacity: 0.7; }
    100% { transform: scale(2.2); opacity: 0; }
}

@keyframes sweep {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
```

### AppLogo.vue Sidebar Pattern
```vue
<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-[8px] bg-t-brand">
        <!-- Simplified Sentinel shield SVG (26x30 from brand guide nav) -->
        <svg class="size-5" viewBox="0 0 26 30" fill="none">
            <!-- ... extracted SVG ... -->
        </svg>
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-medium">SENTINEL</span>
    </div>
</template>
```

### MapLibre Priority Color Update
```typescript
// Source: brand guide priority encoding section
const PRIORITY_COLORS: ExpressionSpecification = [
    'match',
    ['get', 'priority'],
    'P1', '#E24B4A',  // was #dc2626
    'P2', '#EF9F27',  // was #ea580c
    'P3', '#1D9E75',  // was #ca8a04
    'P4', '#378ADD',   // was #16a34a
    '#888888',
];
```

### PriBadge Sentinel Style
```vue
<!-- Updated badge with border per brand guide -->
<span
    class="inline-flex items-center gap-1 rounded-full font-mono font-bold whitespace-nowrap"
    :style="{
        backgroundColor: `color-mix(in srgb, ${color} 15%, transparent)`,
        color: color,
        borderWidth: '1px',
        borderStyle: 'solid',
        borderColor: `color-mix(in srgb, ${color} 40%, transparent)`,
    }"
>
    <span class="shrink-0 rounded-full size-1.5" :style="{ backgroundColor: color }" />
    P{{ p }}
</span>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Slate palette (#0f172a, #1e293b) | Sentinel navy/blue (#05101E, #042C53) | Phase 14 | All surface/text tokens |
| Space Mono monospace | DM Mono monospace | Phase 14 | 60+ components using font-mono |
| CDRRMO/IRMS branding | "Sentinel" branding | Phase 14 | All user-facing text |
| Simple shield outline SVG | Radar/eye shield with animations | Phase 14 | Auth page, sidebar, favicon, PWA |
| Flat color-mix() badges | Pill + dot + colored border badges | Phase 14 | PriBadge and similar components |

## Open Questions

1. **Light theme text-dim/faint exact hex values**
   - What we know: Brand guide uses `rgba(4,44,83,0.4)` for text-muted in light mode
   - What's unclear: Should we compute this on white (#FFFFFF) background or #EFF3FA page bg?
   - Recommendation: Compute on #FFFFFF since most text appears on white card surfaces. `rgba(4,44,83,0.4) on #FFF` = approximately `#9AA9BA`. Test visually and adjust.

2. **Unit status colors after rebrand**
   - What we know: Current AVAILABLE=#16a34a (green), ON_SCENE=#ca8a04 (yellow) match old P4 and P3
   - What's unclear: With P3 now teal and P4 now blue, should unit status colors also change?
   - Recommendation: Keep unit status colors functionally distinct from priorities. Use AVAILABLE=#1D9E75 (teal), ON_SCENE=#EF9F27 (amber). The key is that map markers for units and incidents use different shapes/layers so color overlap with priorities is acceptable.

3. **Shadow scale for dark theme**
   - What we know: Current shadows use `rgba(0,0,0,...)` which works on any background
   - What's unclear: Whether the very dark #05101E background makes shadows invisible
   - Recommendation: May need to shift to slightly brighter shadows like `rgba(0,0,0,0.3)` or add a subtle `rgba(55,138,221,0.05)` tint. Test visually.

4. **Chart.js / analytics colors**
   - What we know: Chart colors in Dashboard.vue and KpiLineChart.vue use hardcoded hex
   - What's unclear: Whether chart colors should follow the exact new priority palette or keep separate
   - Recommendation: Update chart colors to use Sentinel palette. The chart-* CSS variables should be updated in app.css too.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map

This phase has no formal requirement IDs (it is a rebrand phase). Validation is primarily visual with some automated checks:

| Behavior | Test Type | Automated Command | Notes |
|----------|-----------|-------------------|-------|
| APP_NAME config returns "Sentinel" | unit | `php artisan test --compact --filter=test_app_name` | New test or tinker |
| Existing tests pass with renamed app | regression | `php artisan test --compact` | Full suite |
| PWA manifest has correct name | manual | Check vite.config.ts manifest object | Code review |
| Auth page renders | smoke | `php artisan test --compact tests/Feature/Auth/` | Existing auth tests |
| TypeScript compiles | build | `npm run types:check` | No type changes expected |
| ESLint passes | lint | `npm run lint` | Vue template changes |
| Frontend builds | build | `npm run build` | CSS + font changes |

### Sampling Rate
- **Per task commit:** `php artisan test --compact` + `npm run build`
- **Per wave merge:** Full test suite + visual check of auth, sidebar, dispatch, responder
- **Phase gate:** Full suite green + `npm run build` + `npm run types:check`

### Wave 0 Gaps
None -- existing test infrastructure covers regression. No new test files needed for a visual rebrand phase. The main validation is visual (colors, fonts, SVGs render correctly) supplemented by existing test suite passing.

## Sources

### Primary (HIGH confidence)
- `docs/hd-sentinel-brand_1.html` -- Brand guide with all color values, SVGs, typography specs, badge styles
- `resources/css/app.css` -- Current token system, cascade architecture
- `report-app/src/assets/tokens.css` + `app.css` -- Report app token system
- `resources/js/composables/useDispatchMap.ts` -- Hardcoded MapLibre colors
- Codebase grep for IRMS/CDRRMO/Space Mono/hardcoded colors

### Secondary (MEDIUM confidence)
- Computed rgba-to-hex blending values (mathematical, verified with Python)

### Tertiary (LOW confidence)
- Shadow scale adjustments -- needs visual testing (flagged in Open Questions)
- Light theme text-dim/faint exact values -- needs visual testing

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- no new libraries, all existing patterns
- Architecture: HIGH -- token cascade fully mapped, all files inventoried
- Pitfalls: HIGH -- comprehensive grep analysis of hardcoded values, brand guide thoroughly read
- Color mapping: HIGH -- all values extracted from brand guide HTML source
- SVG assets: HIGH -- exact SVG markup located in brand guide with line numbers

**Research date:** 2026-03-15
**Valid until:** Indefinite (brand guide is a static document, no external dependencies)
