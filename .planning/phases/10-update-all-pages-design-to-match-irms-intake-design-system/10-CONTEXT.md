# Phase 10: Update All Pages Design to Match IRMS Intake Design System - Context

**Gathered:** 2026-03-14
**Status:** Ready for planning

<domain>
## Phase Boundary

Restyle all existing pages (auth, admin, settings, dashboard, dispatch, responder, analytics, incidents) to follow the typography, color tokens, spacing, elevation, and component styles defined in the IRMS Intake Design System (`docs/IRMS-Intake-Design-System.md`). The intake station (Phase 8) and citizen reporting app (Phase 9) already follow the design system. This phase brings every remaining page into visual alignment.

</domain>

<decisions>
## Implementation Decisions

### Scope & Depth Per Page Group
- **Full design system treatment:** Auth pages (7), Settings pages (4), Admin pages (6), Dashboard (1), Incidents pages (4), Analytics pages (3) — all get full restyle with design system typography, spacing, elevation, shadow scale, and component patterns
- **Token alignment only:** Dispatch Console (1) and Responder Station (1) — swap colors/fonts to design system tokens but keep existing layout structure, custom UX, and purpose-built interfaces intact
- Total pages affected: ~30 Vue files across 5 layout types

### Shadcn/Reka UI Components
- Restyle existing Shadcn components in `components/ui/` to use design system tokens — NOT replace with custom components
- Keeps Reka UI accessibility + behavior, changes the visual layer
- All pages using these components automatically inherit the updated look
- **Focus/hover states:** Override Shadcn's ring utility with design system's combined `border-color: #2563eb + box-shadow: 0 0 0 3px rgba(37,99,235,.10)` focus style; hover states use t-border-med
- **Shadow scale:** Adopt full 5-level shadow scale with border+shadow pairing:
  - Level 1 (cards/rows): `0 1px 3px rgba(0,0,0,.04)`
  - Level 2 (topbar): `0 1px 4px rgba(0,0,0,.06)`
  - Level 3 (empty states): `0 2px 8px rgba(0,0,0,.06)`
  - Level 4 (login card, dialogs): `0 4px 24px rgba(0,0,0,.08)`
  - Level 5 (dropdowns, popovers): `0 8px 24px rgba(0,0,0,.12)`
- **Icons:** Keep Lucide icons for non-intake pages. Custom IntakeIcon* SVGs remain intake-specific only

### Auth Pages
- Match intake design system login screen: centered card with CDRRMO branding
- **Single unified layout:** All auth pages (login, register, forgot password, reset, 2FA challenge, verify email, confirm password) use one centered card layout. Remove unused auth layout variants (AuthSimpleLayout, AuthSplitLayout, AuthCardLayout)
- **Full CDRRMO branding:** Brand icon (52x52px), "CDRRMO Butuan City" in DM Sans 600, "IRMS — Incident Response Management System" subtitle
- Login card: Level 4 shadow, 14px border-radius, fadeUp entrance animation
- Lock icon (20x20px padlock SVG) for login screen
- **Clean login, no role display** — role-based UI appears only after authentication (topbar user chip, sidebar)
- Apply same aesthetic to all auth pages: t-bg background, t-surface card, design system inputs/buttons

### Sidebar Layout Treatment
- Restyle AppSidebarLayout with design system tokens:
  - Sidebar background: t-surface, borders: t-border
  - Nav items: t-text/t-text-mid colors, active state uses t-accent with t-accentLo background
  - Section labels: Space Mono uppercase (9px, t-text-faint, letter-spacing 2)
- **Sidebar header:** CDRRMO icon + "IRMS" text at top, user chip at bottom. No separate full-width topbar — sidebar IS the navigation
- **Page-level header:** Main content area gets a breadcrumb + page title header bar
- **Content area background:** t-bg (#f4f6f9 light / #0f172a dark). Content cards/panels use t-surface. Creates visual depth matching intake station
- Sidebar stays t-surface for contrast against t-bg content area

### Data Tables
- Table rows: t-surface background, Level 1 shadow, t-border borders, 7px border-radius
- Active/selected rows: t-accentLo background
- Column headers: Space Mono uppercase (9px, t-text-faint, letter-spacing 2)
- Data cells: DM Sans
- Priority/status columns: colored badges matching design system priority/status colors
- Border-left colored indicators where semantically appropriate (priority, status)

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

</decisions>

<specifics>
## Specific Ideas

- The design system document at `docs/IRMS-Intake-Design-System.md` is the authoritative reference for all visual implementation
- "Refined Government Ops" aesthetic — light, professional, high-clarity, information density without cognitive overload
- "Color carries meaning" — every color is functional (priority, channel, role, status), no decorative color
- "Monospace for data, sans-serif for content" — Space Mono for timestamps, IDs, codes, labels, metrics, section headers, table column headers; DM Sans for human-readable content, body text, form inputs
- 4px base unit spacing system throughout
- Border + shadow combinations for elevation (not shadow alone)
- The dispatch console and responder station should still feel like their own specialized environments, just with consistent tokens

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/css/app.css`: Design system tokens already registered (t-bg, t-surface, t-text, t-border, t-accent, t-p1-p4, t-ch-*, t-role-*, t-unit-*) with light AND dark values
- `components/ui/`: ~30 Shadcn/Reka UI primitives (Button, Input, Select, Dialog, Table, Card, Badge, etc.) — restyle these centrally for app-wide effect
- Intake station components (`components/intake/`): Reference implementation for design system patterns (IntakeTopbar, FeedCard, TriageForm, ChBadge, PriBadge)
- `IntakeLayout.vue`: Reference for design system layout patterns
- `DispatchLayout.vue`: Custom dispatch layout — update tokens only
- `ResponderLayout.vue`: Custom responder layout — update tokens only

### Established Patterns
- CSS custom properties with `@theme inline` indirection for Tailwind CSS v4
- `color-mix()` for opacity tints (Phase 8 pattern)
- DM Sans + Space Mono fonts already globally applied via app.css
- `.dark` selector for dark mode variants
- Shadcn component styles defined via CSS variables (--background, --foreground, --border, etc.)

### Integration Points
- `resources/css/app.css`: Update `:root` and `.dark` CSS variables to align Shadcn tokens with design system values
- `components/ui/*.vue`: Update ~30 Shadcn components with design system styling
- `layouts/AppSidebarLayout.vue`: Restyle sidebar with design system tokens
- `layouts/AuthLayout.vue`: Replace with single centered card layout
- `layouts/auth/Auth*.vue`: Remove unused variants after consolidation
- `layouts/app/AppHeaderLayout.vue`: Update with design system patterns
- All page components in `pages/`: Update page-level styling (backgrounds, cards, headers)

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 10-update-all-pages-design-to-match-irms-intake-design-system*
*Context gathered: 2026-03-14*
