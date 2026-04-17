# IRMS Intake Layer — Design System

**Project:** Incident Response Management System (IRMS)  
**Layer:** Intake Triage Station  
**Client:** CDRRMO Butuan City  
**Built by:** HDSystem  
**Version:** 1.0  

---

## Table of Contents

1. [Design Principles](#1-design-principles)
2. [Typography](#2-typography)
3. [Color Tokens](#3-color-tokens)
4. [Spacing & Layout](#4-spacing--layout)
5. [Elevation & Borders](#5-elevation--borders)
6. [Priority System](#6-priority-system)
7. [Channel System](#7-channel-system)
8. [Role System](#8-role-system)
9. [Icon Library](#9-icon-library)
10. [Components](#10-components)
11. [Animations](#11-animations)
12. [Layout Architecture](#12-layout-architecture)
13. [Interaction Patterns](#13-interaction-patterns)

---

## 1. Design Principles

### Refined Government Ops
The Intake Layer follows a **light, professional, high-clarity** aesthetic. The design priority is *information density without cognitive overload* — operators work under pressure and the UI must surface critical information instantly.

**Guiding rules:**
- **Color carries meaning.** Every color in the UI is functional, not decorative. Priority, channel, role, and status each have a dedicated hue. No decorative color is used.
- **Status is always visible.** Priority level, channel source, and triage state are legible at a glance on every card — never buried in a detail view.
- **Hierarchy through weight, not noise.** The type scale and font weight system distinguishes primary content from supporting metadata without relying on color alone.
- **Monospace for data, sans-serif for content.** Timestamps, IDs, codes, labels, and metrics use `Space Mono`. Human-readable content — names, addresses, descriptions — uses `DM Sans`.
- **No emojis.** All iconography is custom flat SVG, rendered inline at precise sizes and colors.

---

## 2. Typography

### Font Stack

| Role | Font | Weights Used |
|------|------|-------------|
| Primary (content) | `DM Sans` | 300, 400, 500, 600, 700 |
| Data / Code / Labels | `Space Mono` | 400, 700 |

### Google Fonts Import
```
DM Sans: ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400
Space Mono: wght@400;700
```

### Type Scale

| Usage | Size | Weight | Font | Color |
|-------|------|--------|------|-------|
| Section title | 14px | 600 | DM Sans | `text` `#0f172a` |
| Card title | 13–15px | 600–700 | DM Sans | `text` `#0f172a` |
| Body / form labels | 12–13px | 400–500 | DM Sans | `textMid` `#334155` |
| Supporting metadata | 10–12px | 400 | DM Sans | `textDim` `#64748b` |
| Timestamps, IDs, codes | 9.5–11px | 400 | Space Mono | `textFaint` `#94a3b8` |
| Section labels (uppercase) | 9–10px | 700 | Space Mono | `textFaint` `#94a3b8` |
| Stat numbers | 20–21px | 700 | Space Mono | varies by context |

### Label Convention
Section labels (e.g. `CHANNEL ACTIVITY`, `SESSION METRICS`) are:
- `Space Mono`, 9–10px
- `textFaint` `#94a3b8`
- `letterSpacing: 2`
- `textTransform: uppercase`

### Field Labels
Form field labels are:
- `DM Sans`, 11px, weight 600
- `textDim` `#64748b`
- `letterSpacing: 0.3`
- `display: block`, `marginBottom: 5px`

---

## 3. Color Tokens

All colors are defined in the `T` token object and referenced by name throughout the codebase. Never use raw hex values in component code — always reference a token.

### Surfaces

| Token | Value | Usage |
|-------|-------|-------|
| `T.bg` | `#f4f6f9` | Page background |
| `T.surface` | `#ffffff` | Cards, panels, topbar, statusbar |
| `T.surfaceAlt` | `#f8fafc` | Input backgrounds, inset blocks, alt rows |
| `T.overlay` | `rgba(255,255,255,0.96)` | Floating overlays, dropdowns |

### Text

| Token | Value | Usage |
|-------|-------|-------|
| `T.text` | `#0f172a` | Primary text, headings |
| `T.textMid` | `#334155` | Secondary content, body text |
| `T.textDim` | `#64748b` | Subdued labels, metadata |
| `T.textFaint` | `#94a3b8` | Placeholder text, timestamps, faint labels |

### Borders

| Token | Value | Usage |
|-------|-------|-------|
| `T.border` | `#e2e8f0` | Default border on cards, inputs, dividers |
| `T.borderMed` | `#cbd5e1` | Stronger borders, separators |
| `T.borderFoc` | `#2563eb` | Focus ring color (same as accent) |

### Brand & Accent

| Token | Value | Usage |
|-------|-------|-------|
| `T.brand` | `#1e3a6e` | Logo, topbar brand text, primary action backgrounds |
| `T.accent` | `#2563eb` | Active states, focus rings, links, LIVE badge |
| `T.accentLo` | `rgba(37,99,235,.08)` | Subtle accent backgrounds |
| `T.accentMid` | `rgba(37,99,235,.14)` | Active filter tab backgrounds |

### Status Colors

| Token | Value | Usage |
|-------|-------|-------|
| `T.online` | `#16a34a` | Online indicator, triaged badge, success states |
| `T.queued` | `#2563eb` | Queued for dispatch indicator |

---

## 4. Spacing & Layout

### Base Unit
The spatial system uses **4px as a base unit**. Common values:

| Multiplier | Value | Usage |
|-----------|-------|-------|
| 1× | 4px | Tight gaps between inline elements |
| 1.5× | 6px | Badge internal padding, small gaps |
| 2× | 8px | Card internal gaps, form row gaps |
| 2.5× | 10px | Panel padding, list item padding |
| 3× | 12px | Card padding, section padding |
| 4× | 16px | Section padding, topbar horizontal padding |
| 5× | 20px | Center panel padding |
| 5.5× | 22px | Center panel horizontal padding |

### Fixed Dimensions

| Element | Value |
|---------|-------|
| Topbar height | 56px |
| Statusbar height | 24px |
| Left column width | 296px |
| Right column width | 304px |
| Center column | `flex: 1` (fills remaining space) |
| Icon container (channel stats) | 26×26px |
| Avatar / initials chip | 26×26px (topbar), 40×40px (login, dropdown) |
| Brand icon container | 34×34px (topbar), 52×52px (login) |

### Border Radius Scale

| Value | Usage |
|-------|-------|
| 3–4px | Small badges, status tags |
| 5–6px | Buttons (secondary), filter tabs, small inputs |
| 7px | Cards, rows, primary buttons |
| 8px | Panels, dropdown menus, larger cards |
| 9px | Login card buttons, login panel |
| 10px | Avatar containers (login screen) |
| 14px | Login card |
| 16px | Empty state icon container |

---

## 5. Elevation & Borders

The design uses **border + shadow** combinations rather than shadow alone for elevation. Shadows are subtle and directional.

### Shadow Scale

| Level | CSS | Usage |
|-------|-----|-------|
| 0 | none | Flat surface elements |
| 1 | `0 1px 3px rgba(0,0,0,.04)` | Default cards, rows |
| 2 | `0 1px 4px rgba(0,0,0,.06)` | Topbar |
| 3 | `0 2px 8px rgba(0,0,0,.06)` | Empty state icon |
| 4 | `0 4px 24px rgba(0,0,0,.08)` | Login card |
| 5 | `0 8px 24px rgba(0,0,0,.12)` | Dropdowns, popovers |

### Focus Ring
Focused inputs show a combined border color change + box shadow:
```css
border-color: #2563eb;
box-shadow: 0 0 0 3px rgba(37,99,235,.10);
```

### Active Selection (e.g. incoming card)
```css
border-color: rgba(37,99,235,.50);
box-shadow: 0 0 0 3px rgba(37,99,235,.10);
```

---

## 6. Priority System

Four priority levels, each with a full color set: a solid ink tone for text/borders, a low-opacity background, and a slightly stronger background for selected states.

| Level | Label | Token | Hex | `lo` (8%) | `bg` (6%) |
|-------|-------|-------|-----|-----------|-----------|
| P1 | CRITICAL | `T.p1` | `#dc2626` | `rgba(220,38,38,.08)` | `rgba(220,38,38,.06)` |
| P2 | HIGH | `T.p2` | `#ea580c` | `rgba(234,88,12,.08)` | `rgba(234,88,12,.06)` |
| P3 | MEDIUM | `T.p3` | `#ca8a04` | `rgba(202,138,4,.08)` | `rgba(202,138,4,.06)` |
| P4 | LOW | `T.p4` | `#16a34a` | `rgba(22,163,74,.08)` | `rgba(22,163,74,.06)` |

### Auto-Classification Rules

Priority is automatically assigned from incident type. Operators can override before submission.

| P1 — Critical | P2 — High | P3 — Medium | P4 — Low |
|---------------|-----------|-------------|----------|
| Cardiac Arrest | Road Accident | Domestic Violence | All others |
| Structure Fire | Assault | Seizure | |
| Drowning | Stabbing | Chest Pain | |
| Electrocution | Gas Leak | Missing Person | |
| Building Collapse | Flood | | |

### Priority Picker Component
4-column grid. Each cell shows:
- Dot indicator (filled when selected, border-only when idle)
- `P{n}` label in `Space Mono` bold
- Level label (CRITICAL / HIGH / MEDIUM / LOW) at 9px

Selected state: `bg` background fill + solid `p{n}` color border.  
Idle state: `surfaceAlt` background + `border` color border.

### Priority Badge (`PriBadge`)
Inline pill used in queue rows and feed cards.

| Prop | Values |
|------|--------|
| `p` | 1–4 |
| `size` | `'sm'` (default) or `'lg'` |

- Small: 10px mono, `2px 7px` padding, 6×6px dot
- Large: 11px mono, `4px 10px` padding, 8×8px dot

---

## 7. Channel System

Five intake channels, each with a dedicated color. Channel identity is expressed via colored badges and icon containers throughout the feed and triage form.

| Key | Label | Color | Icon |
|-----|-------|-------|------|
| `SMS` | SMS / Text | `#059669` (green) | Message bubble with lines |
| `APP` | Mobile App | `#2563eb` (blue) | Mobile phone outline |
| `VOICE` | Voice / 911 | `#d97706` (amber) | Phone handset |
| `IOT` | IoT Sensor | `#7c3aed` (violet) | Signal tower with arcs |
| `WALKIN` | Walk-in | `#dc2626` (red) | Walking figure |

### Channel Badge (`ChBadge`)
Inline pill showing channel icon + abbreviated name.

| Prop | Values |
|------|--------|
| `ch` | `'SMS'` `'APP'` `'VOICE'` `'IOT'` `'WALKIN'` |
| `small` | `true` / `false` |

- Background: `${color}12` (7% tint)
- Border: `${color}35` (21% opacity)
- Text: channel color, bold, `Space Mono`
- Icon: channel color, rendered at 10px (small) or 12px (default)

---

## 8. Role System

### Role Definitions

| Role | Color | Hex |
|------|-------|-----|
| Operator | Blue | `#2563eb` |
| Supervisor | Violet | `#7c3aed` |
| Admin | Teal | `#0f766e` |

Each role has `bgColor` (10% opacity tint) and `borderColor` (30% opacity) for badge backgrounds and selection states.

### Permission Matrix

| Permission | Operator | Supervisor | Admin |
|------------|:--------:|:----------:|:-----:|
| `triage` | ✓ | ✓ | ✓ |
| `submitDispatch` | ✓ | ✓ | ✓ |
| `manualEntry` | ✓ | ✓ | ✓ |
| `overridePriority` | — | ✓ | ✓ |
| `recallIncident` | — | ✓ | ✓ |
| `viewSessionLog` | — | ✓ | ✓ |

### Role Badge (`RoleBadge`)
Shield icon + uppercase role label pill. Uses role `color`, `bgColor`, `borderColor`.

| Prop | Values |
|------|--------|
| `roleKey` | `'operator'` `'supervisor'` `'admin'` |
| `small` | `true` / `false` |

### User Chip (Topbar)
Shows initials avatar + name + role label. Clicking opens a dropdown with:
- Full initials avatar (40×40px)
- Name, role badge, shift label
- Permissions list (key / YES or NO)
- Sign out button

### Demo Users

| ID | Name | Role | Initials | Shift |
|----|------|------|----------|-------|
| u1 | Santos, M.L. | Operator | MS | Day Shift |
| u2 | Reyes, J.A. | Supervisor | JR | Day Shift |
| u3 | Admin | Admin | AD | System |

---

## 9. Icon Library

All icons are custom inline SVG functions with signature `(sz, col) => <svg>`. No external icon library.

### Usage Pattern
```jsx
// Render at 16px in accent color
{ICONS.sms(16, T.accent)}

// Render at 12px in a channel's own color
{ICONS[CH[m.ch].iconKey](12, CH[m.ch].color)}
```

### Icon Catalog

| Key | Default Size | ViewBox | Description |
|-----|-------------|---------|-------------|
| `sms` | 16 | 16×16 | Message bubble with text lines + tail |
| `app` | 16 | 16×16 | Mobile phone outline with dot |
| `voice` | 16 | 16×16 | Phone handset silhouette |
| `iot` | 16 | 16×16 | Signal tower with two arcs + dot |
| `walkin` | 16 | 16×16 | Walking figure, circle head |
| `pin` | 14 | 14×14 | Teardrop location pin with inner circle |
| `user` | 14 | 14×14 | Person with head + shoulders arc |
| `check` | 10 | 10×10 | Checkmark stroke |
| `intake` | 18 | 18×18 | Inbox tray with down-arrow + baseline |
| `clipboard` | 32 | 32×32 | Clipboard with ruled lines (empty state) |
| `lock` | 20 | 20×20 | Padlock (login screen) |
| `logout` | 16 | 16×16 | Arrow exiting door |
| `shield` | 12 | 12×12 | Shield with checkmark (role badge) |
| `userCircle` | 28 | 28×28 | Circle avatar with head + shoulders |
| `recall` | 13 | 13×13 | Circular arrow (undo / recall) |
| `override` | 13 | 13×13 | Up-arrow with two base lines |
| `activity` | 14 | 14×14 | EKG / pulse waveform |

### SVG Style Conventions
- `fill="none"` on all SVGs
- `stroke` carries the color (`col` param)
- `strokeWidth`: 1.2–1.5 depending on icon size
- `strokeLinecap="round"` throughout
- `strokeLinejoin="round"` on path junctions

---

## 10. Components

### Inputs

All text inputs, selects, and textareas share a base style:

```
background:    #ffffff (T.surface)
border:        1px solid #e2e8f0 (T.border)
border-radius: 6px
padding:       8px 11px
font:          13px DM Sans
color:         #0f172a (T.text)
transition:    border-color .15s, box-shadow .15s
```

**Focus state:**
```
border-color:  #2563eb
box-shadow:    0 0 0 3px rgba(37,99,235,.10)
```

**Hover state:**
```
border-color:  #b0bcd0
```

**Placeholder:**
```
color: #94a3b8
```

### Buttons

**Primary action (Submit):**
- Background: priority color (`PCOLOR[form.priority]`)
- Border: 1.5px same color
- Text: `#ffffff`, 13px DM Sans, weight 600
- Border-radius: 7px
- Box-shadow: `0 2px 8px ${color}40`
- Disabled: `surfaceAlt` bg, `border` border, `textFaint` text

**Secondary / Neutral:**
- Background: `T.surface`
- Border: `1px solid T.border`
- Text: `T.textMid`, 12px DM Sans weight 500
- Border-radius: 7px

**Supervisor action (Recall, Override):**
- Recall: `rgba(220,38,38,.06)` bg, `rgba(220,38,38,.2)` border, `#dc2626` text
- Override: `rgba(124,58,237,.07)` bg, `rgba(124,58,237,.25)` border, `#7c3aed` text

**Filter tabs:**
- Active: `T.accentMid` bg, `T.accent+'50'` border, `T.accent` text, weight 600
- Idle: transparent bg, `T.border` border, `T.textDim` text, weight 400
- Border-radius: 5px

### Cards

**Incoming Message Card:**
- Background: `T.surface` (idle) / `T.accentLo` (active)
- Border-left: 3px solid priority color
- Border-radius: 8px
- Padding: 11px 12px
- Idle shadow: `0 1px 3px rgba(0,0,0,.04)`
- Active shadow: `0 0 0 3px rgba(37,99,235,.1)`
- Triaged: `opacity: 0.55`

**Queued Incident Row (right panel):**
- Background: `T.surface`
- Border-left: 3px solid priority color
- Border-radius: 7px
- Shadow: `0 1px 3px rgba(0,0,0,.04)`

**Original Message Block (triage form):**
- Background: `T.surfaceAlt`
- Border: `1px solid T.border`
- Border-left: `3px solid T.borderMed`
- Border-radius: 7px

**Source Indicator (triage form):**
- Background: `${channel.color}08`
- Border: `1px solid ${channel.color}30`
- Border-radius: 8px

### Stat Pills (topbar)
- Layout: column flex, centered
- Number: 21px, `Space Mono`, bold, colored
- Label: 9px, `Space Mono`, `T.textFaint`, `letterSpacing: 1.2`, uppercase
- Divided by `1px solid T.border` on right

### Status Indicators

**Online pulse dot (statusbar):**
- 6×6px circle, `T.online` `#16a34a`
- `pulse` animation, 3s infinite

**TRIAGED badge:**
- Background: `rgba(22,163,74,.1)`
- Border: `1px solid rgba(22,163,74,.25)`
- Color: `T.online`, `Space Mono`, 9.5px, bold
- Check icon + "TRIAGED" text

**LIVE badge (topbar ticker):**
- Background: `T.accentLo`
- Border: `1px solid ${T.accent}25`
- Color: `T.accent`, `Space Mono`, 9px, `letterSpacing: 2`

---

## 11. Animations

All animations are CSS keyframes defined once in a `<style>` block inside the root component.

| Name | Definition | Usage |
|------|-----------|-------|
| `slideIn` | `translateY(-8px) → none`, `opacity 0→1` | New cards entering the feed |
| `fadeUp` | `translateY(5px) → none`, `opacity 0→1` | Login screen entrance |
| `pulse` | `opacity 1→0.5→1` | Online status dot |
| `blink` | `opacity 1→0.25→1` | Not in active use (legacy) |
| `spin` | `rotate(0→360deg)` | Login button loading spinner |

### Duration Conventions

| Type | Duration | Easing |
|------|----------|--------|
| Card enter | 300–350ms | `ease` |
| Login screen | 400ms | `ease` |
| Dropdown open | 150ms | `ease` |
| Button/border transitions | 120–150ms | linear |
| Progress bar fill | 400–500ms | linear |

---

## 12. Layout Architecture

### Shell Structure
```
App (root — login state only)
  └── LoginScreen           (unauthenticated)
  └── IntakeApp             (authenticated)
        ├── Topbar (56px)
        ├── Three-column body (flex: 1)
        │     ├── Left panel (296px)   — Channel monitor + feed
        │     ├── Center panel (flex)  — Triage form
        │     └── Right panel (304px)  — Dispatch queue + metrics
        └── Statusbar (24px)
```

### Left Panel Sections
1. Channel Activity (fixed height) — stats bars per channel
2. Filter tabs (fixed height) — All / Pending / Triaged
3. Message feed (scrollable)

### Center Panel Sections
1. Section header (fixed) — title, subtitle, Manual Entry button
2. Triage form or empty state (scrollable)

### Right Panel Sections
1. Queue header (fixed) — title, incident count badge
2. Queue list (scrollable) — triaged incident rows
3. Session metrics (fixed) — 2×2 stat grid
4. Priority breakdown (conditional) — bar chart per priority level
5. Session log (conditional, Supervisor/Admin only) — activity entries

### Topbar Composition (left → right)
Brand icon → Brand text → Divider → Stat pills × 4 → Live ticker (flex) → Clock → User chip

---

## 13. Interaction Patterns

### Card Selection
Clicking an incoming message card:
- Sets it as `activeMsg`
- Highlights with `T.accentLo` bg + accent border + focus ring
- Pre-fills the triage form with channel, caller, type, barangay, and message

### Triage Form Submission
1. Operator fills required fields: type, barangay, caller
2. Submit button activates with priority color
3. 600ms simulated loading (spinner state)
4. Message marked as `triaged: true` in feed (opacity 0.55)
5. Incident added to queue (right panel) with `slideIn` animation
6. Activity log entry created (Supervisor/Admin visible)
7. Form clears, center panel returns to empty state

### Priority Override (Supervisor / Admin)
Available on each queued incident row via the "Override Priority" button:
- Opens an inline 4-cell picker
- Current priority shown as disabled (40% opacity)
- Selecting a new level updates the incident immediately
- Activity log entry records the change with old/new values

### Recall (Supervisor / Admin)
Removes a queued incident from the dispatch queue:
- Instant removal with no confirmation dialog (speed-optimized for ops context)
- Activity log records the recall

### Sign Out
User chip dropdown → Sign out button → Resets `currentUser` to `null` → Returns to login screen. All session state (messages, queue, log) is preserved in memory until page reload.

### Simulated Incoming Messages
A recursive `setTimeout` fires every 8–22 seconds (randomized), appending a new message from `MSG_TEMPLATES` to the feed with `fresh: true`, triggering `slideIn` animation. The feed is capped at 40 messages.

---

*This document covers the Intake Layer prototype only. The Dispatch Console and Responder App have separate design implementations that share the priority color system and icon conventions.*

*HDSystem IRMS · CDRRMO Butuan City · v1.0*
