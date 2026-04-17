---
status: diagnosed
trigger: "Investigate why the responder Chat tab's free text input is hidden behind the RESOLVING > status button"
created: 2026-03-14T00:00:00Z
updated: 2026-03-14T00:00:00Z
---

## Current Focus

hypothesis: StatusButton uses `position: fixed` with `bottom: 80px`, overlapping the bottom of the slot content area. The ChatTab content sits in a flex column that fills all available space but has no bottom padding to account for the fixed-position StatusButton overlay.
test: Trace the layout hierarchy and CSS positioning
expecting: Confirm that ChatTab's bottom content (quick-reply chips + text input) has no clearance for the ~95px-tall fixed StatusButton
next_action: Return diagnosis

## Symptoms

expected: Chat tab shows quick-reply chips AND a free text input field, all fully visible and not overlapping with the status transition button
actual: The free text input area is not visible -- it is covered by the "RESOLVING >" status button
errors: None (layout/CSS issue)
reproduction: Open responder station with active incident in ON_SCENE status, switch to Chat tab
started: Since StatusButton was implemented with fixed positioning

## Eliminated

(none needed -- root cause found on first investigation)

## Evidence

- timestamp: 2026-03-14T00:01:00Z
  checked: ResponderLayout.vue template structure (lines 67-93)
  found: |
    Layout is a flex column with h-dvh and overflow-hidden.
    Structure top-to-bottom:
      1. ResponderTopbar
      2. <div class="flex flex-1 flex-col overflow-hidden"> wrapping <slot />
      3. StatusButton (rendered IN document flow position but uses `fixed` positioning)
      4. ResponderTabbar (h-20, shrink-0)

    The StatusButton is placed in the DOM between the slot wrapper and the tabbar,
    but because it uses `position: fixed`, it is removed from the flex flow entirely.
    This means the flex layout calculates available space as if StatusButton does not exist.
  implication: The slot content area (flex-1) fills all space between topbar and tabbar. StatusButton floats over the bottom of this content area.

- timestamp: 2026-03-14T00:02:00Z
  checked: StatusButton.vue positioning (line 82)
  found: |
    The wrapper div uses: `fixed inset-x-0 bottom-[80px] shrink-0 px-3 pt-8 pb-3`
    - `fixed` removes it from document flow
    - `bottom-[80px]` positions it 80px from viewport bottom (to sit above the tabbar which is h-20 = 80px)
    - `pt-8 pb-3` = 32px + 12px padding
    - The button itself is `min-h-[52px]`
    - Total visual height of the overlay: ~32px (pt-8) + 52px (button) + 12px (pb-3) = ~96px
    - It also has a gradient background from transparent to var(--t-bg), meaning it visually covers content beneath it
  implication: The StatusButton overlay covers approximately 96px of the bottom of the content area

- timestamp: 2026-03-14T00:03:00Z
  checked: ChatTab.vue layout (lines 113-221)
  found: |
    ChatTab is `flex flex-1 flex-col overflow-hidden` containing:
      1. Message history div (flex-1, overflow-y-auto) -- scrollable
      2. Quick-reply chips row (border-t, px-4 py-2) -- static height
      3. Free text input row (border-t, px-4 py-2, min-h-[44px] input + 44px button) -- static height

    There is NO bottom padding or margin on the ChatTab to account for the StatusButton overlay.
    The free text input sits at the very bottom of the ChatTab.
  implication: The free text input (and possibly part of the quick-reply chips) is directly behind the StatusButton

- timestamp: 2026-03-14T00:04:00Z
  checked: Station.vue template (line 365)
  found: |
    The Station page wraps all tab content in `<div class="flex flex-1 flex-col overflow-y-auto">`.
    This div fills the slot in ResponderLayout.
    There is no bottom padding on this wrapper either.
  implication: Neither the page wrapper nor the tab component accounts for the fixed StatusButton height

## Resolution

root_cause: |
  The StatusButton component uses `position: fixed` with `bottom: 80px` (to sit above the 80px tabbar).
  Because it is fixed-positioned, it is removed from the normal document/flex flow entirely.
  The flex layout in ResponderLayout calculates available space for the content slot as:
    viewport height - topbar height - tabbar height (80px)

  But the StatusButton visually overlaps ~96px of the bottom of that content area.

  ChatTab places its free text input at the very bottom of its container with no bottom padding.
  The input sits exactly where the StatusButton renders, making it invisible/untappable.

  The same issue likely affects other tabs but is most noticeable on Chat because it has
  interactive content (input field) at the bottom, whereas other tabs scroll.

fix: (not applied -- diagnosis only)
verification: (not applicable)
files_changed: []
