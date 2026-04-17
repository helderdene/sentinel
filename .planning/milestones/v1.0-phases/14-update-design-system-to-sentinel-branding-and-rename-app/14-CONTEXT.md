# Phase 14: Update design system to Sentinel branding and rename app - Context

**Gathered:** 2026-03-15
**Status:** Ready for planning

<domain>
## Phase Boundary

Rebrand the entire IRMS application from "IRMS / CDRRMO Butuan City" to "Sentinel" using the HD Sentinel brand identity guide (`docs/hd-sentinel-brand_1.html`). This covers: new color palette (navy/blue), typography changes (Bebas Neue display, DM Mono monospace), new shield logo with radar/eye motif, app rename in all surfaces (sidebar, auth, PWA manifest, page titles, meta tags), and the citizen reporting app. No new features or functionality — pure visual rebrand.

</domain>

<decisions>
## Implementation Decisions

### App naming
- App is called "Sentinel" (not "HD Sentinel") everywhere
- Auth page shows: shield icon + "SENTINEL" (Bebas Neue) + subtitle "Incident Response Management System"
- Sidebar shows: shield icon + "SENTINEL" (DM Sans 500, single color, no accent)
- All CDRRMO Butuan City references removed entirely — app becomes a generic IRMS product
- PWA manifest: name "Sentinel", short_name "Sentinel"
- Page `<title>` tags: "Sentinel" replacing "IRMS"
- Citizen reporting app (report-app/) also rebranded to Sentinel

### Typography
- **Bebas Neue**: Auth page "SENTINEL" title only — not used elsewhere in the app UI
- **DM Sans**: Remains body/heading font (already in use, no change)
- **DM Mono**: Replaces Space Mono everywhere — incident codes, timestamps, section labels, badges, all monospace UI elements
- Nav section labels: DM Mono, 10px, 2-3px letter-spacing (matching brand guide spec, slightly larger than current 9px)
- Report app also gets DM Mono replacing Space Mono
- Load Bebas Neue and DM Mono via Google Fonts (alongside existing DM Sans)

### Color palette
- Full migration from Tailwind slate palette to Sentinel navy/blue palette
- All `--t-*` design tokens remapped to Sentinel values
- **Dark theme surfaces use solid hex equivalents** (not rgba from brand guide) to avoid stacking transparency issues with nested Shadcn components
- Dark: bg #05101E, surface solid blend of rgba(4,44,83,0.5) on #05101E, text #FFFFFF, accent #378ADD
- Light: bg #EFF3FA, surface #FFFFFF, text #042C53, accent #185FA5
- **Priority colors changed:**
  - P1: #A32D2D / #E24B4A (Critical Red) — was #dc2626
  - P2: #854F0B / #EF9F27 (Dispatch Amber) — was #ea580c
  - P3: #0F6E56 / #1D9E75 (Response Teal) — was #ca8a04 (yellow)
  - P4: #185FA5 / #378ADD (Signal Blue) — was #16a34a (green)
- No grid background or noise overlay textures — clean functional surfaces
- **Badge style updated**: pill shape with dot indicator, colored border, and tinted background per brand guide (replaces current color-mix() flat badges)
- Brand color: #042C53 (Command Blue / navy)
- Focus ring: #378ADD (blue-mid)
- Borders: rgba(55,138,221,0.12) dark / rgba(24,95,165,0.14) light

### Logo & shield icon
- **Auth page**: Full detailed shield SVG with radar rings, crosshairs, signal arcs, and eye — animated with pulse ring and rotating sweep line (CSS animations from brand guide)
- **Sidebar**: Simplified shield icon (shield outline + inner circles + dot) at 26x30px
- **Favicon**: Simplified shield icon at 32x32
- **PWA icons**: Regenerated with simplified shield on Command Blue (#042C53) background
- Wordmark "SENTINEL" in single color (no accent letter) alongside shield icon

### Claude's Discretion
- Exact solid hex values for dark theme surface (blending rgba on page bg)
- Shadow scale adjustments if needed for the new palette
- Border token adjustments for the new blue-based borders
- How to handle dispatch map marker colors (may need adjustment for new P3/P4)
- Transition approach: whether to update tokens first then sweep components, or section by section

</decisions>

<specifics>
## Specific Ideas

- Brand guide HTML at `docs/hd-sentinel-brand_1.html` is the authoritative reference — all color values, font specs, badge styles, and SVG assets come from there
- The brand guide's SVG shields should be extracted and used directly (not recreated) — both the full detailed version and simplified icon
- The brand voice pillars (Authority, Precision, Urgency) inform the overall feel but don't need explicit implementation beyond visual design
- Primary tagline "ALWAYS READY. ALWAYS WATCHING." could appear on the auth page but not required

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/css/app.css`: Central `--t-*` token system with `:root` (light) and `.dark` blocks — single point of change for color migration
- Shadcn variables already remap to `--t-*` tokens — cascade means updating tokens automatically updates all Shadcn components
- `resources/js/components/AppLogo.vue`: Sidebar logo component — swap SVG and text here
- `resources/js/layouts/AuthLayout.vue`: Auth page layout with shield icon — swap to full animated shield
- `report-app/src/`: Separate Vue SPA with its own CSS tokens and font loading
- PWA icons in `public/` directory — regenerate with new shield

### Established Patterns
- Phase 10 established the one-direction CSS variable cascade: Shadcn vars reference `--t-*` tokens, never reverse
- DS-03 focus ring targets `[data-slot]` selector for Reka UI/Shadcn components
- color-mix() pattern used extensively for opacity tints — will need updates for new badge style
- `@theme inline` block in app.css registers Tailwind utility colors from CSS vars

### Integration Points
- `config/app.php`: APP_NAME environment variable — rename to "Sentinel"
- `resources/views/app.blade.php`: Page title, font loading <link> tags
- `vite-plugin-pwa` config in `vite.config.ts`: PWA manifest (name, short_name, theme_color, icons)
- `report-app/index.html`: Citizen app HTML shell with font loading
- `report-app/vite.config.ts`: Report app build config

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 14-update-design-system-to-sentinel-branding-and-rename-app*
*Context gathered: 2026-03-15*
