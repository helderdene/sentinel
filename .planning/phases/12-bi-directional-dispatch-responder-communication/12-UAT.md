---
status: resolved
phase: 12-bi-directional-dispatch-responder-communication
source: 12-01-SUMMARY.md, 12-02-SUMMARY.md, 12-03-SUMMARY.md
started: 2026-03-14T00:00:00Z
updated: 2026-03-14T06:15:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Dispatch sends message to responder
expected: Open the dispatch console with an active incident that has a unit assigned. Select the incident. Expand the Messages section in the incident detail panel. Type a message and send it. The message should appear immediately in the dispatch Messages section. On the responder Chat tab, the same message should appear.
result: pass (re-test after 12-04 fix)

### 2. Responder message appears in dispatch
expected: From a responder's Chat tab, send a message (quick reply or free text) on an active incident. On the dispatch console, the message should appear in the Messages section for that incident. The sender should be identified with unit callsign and name (e.g., "FIRE-01 · J. Cruz").
result: pass

### 3. Collapsible Messages section placement
expected: Select an incident in the dispatch console. The right panel should show the incident detail with a Messages section positioned above the Timeline section. The Messages section should be collapsed by default with a header showing the message count. Clicking the header expands it to reveal the message thread, input area, and quick-reply chips. The expanded area should have a max height (~200px) with scrolling for overflow.
result: pass

### 4. Auto-expand on unread messages
expected: While viewing a different incident (or no incident selected) in the dispatch console, have a responder send a message. Then click on that incident's queue row. The Messages section should auto-expand (overriding the default collapsed state) since there are unread messages. The unread indicator should clear once expanded.
result: pass

### 5. Dispatcher quick-reply chips
expected: Expand the Messages section on an incident. Below the message thread, 7 dispatcher-specific quick-reply chips should be visible in a wrap grid layout (2 rows): "Copy", "Stand by", "Proceed", "Return to station", "Backup en route", "Update status", "Acknowledged". Clicking a chip should send the message immediately (no confirmation needed).
result: pass

### 6. Unread badge on queue card and topbar MSGS count
expected: Have a responder send a message on an incident you are NOT currently viewing. The incident's queue card in the left panel should show an unread message count badge (dot or number). The topbar should show a MSGS stat with the total unread count across all incidents. Both should update in real-time.
result: pass

### 7. Audio cue on incoming message
expected: While viewing a different incident (or no incident), have a responder send a message on another incident. A subtle, soft audio chime should play (distinct from the priority alert tones). No audio should play if the message is for the currently selected incident or if you sent the message yourself.
result: pass

### 8. Responder group chat visibility
expected: On an incident with multiple units assigned (e.g., FIRE-01 and AMB-02), send a message from FIRE-01's Chat tab. The message should appear on AMB-02's Chat tab as well (not just dispatch). All participants (dispatch + all assigned units) see all messages in a single unified thread.
result: pass (re-test after 12-04 fix)

### 9. Responder sender identification
expected: In the responder's Chat tab, messages from other units should show "UNIT-CALLSIGN · Name" format (e.g., "FIRE-01 · Juan Cruz"). Messages from dispatch should show the dispatcher's name with a role badge. Own messages should appear on the right side of the chat bubble.
result: pass

## Summary

total: 9
passed: 9
issues: 0
pending: 0
skipped: 0

## Gaps

- truth: "Dispatch Messages section visible in incident detail panel for sending messages"
  status: resolved
  reason: "User reported: There is no message section in the incident detail panel"
  severity: major
  test: 1
  root_cause: "No code-level bug found. The component is correctly wired (IncidentDetailPanel imports and renders DispatchMessagesSection unconditionally). The section starts collapsed with a very small 9px MESSAGES header label that may be overlooked between Available Units and Timeline sections. Possible stale build assets if npm run build/dev not re-run."
  artifacts:
    - path: "resources/js/components/dispatch/IncidentDetailPanel.vue"
      issue: "DispatchMessagesSection rendered at lines 372-380, collapsed header may be too small to notice"
    - path: "resources/js/components/dispatch/DispatchMessagesSection.vue"
      issue: "Header text is text-[9px] — very small, easy to miss"
  missing:
    - "Make the Messages header more visually prominent (larger text, icon, or visual separator)"
  debug_session: ".planning/debug/dispatch-messages-not-visible.md"

- truth: "Responder Chat tab free text input accessible (not hidden behind status button)"
  status: resolved
  reason: "User reported: there seems to be no place to type in the responder chat box — free text input hidden behind the RESOLVING status button"
  severity: major
  test: 8
  root_cause: "StatusButton uses position:fixed with bottom-[80px], overlapping ~96px of the content area. ChatTab places free text input flush at the bottom with no padding. The layout slot wrapper does not reserve space for the fixed overlay."
  artifacts:
    - path: "resources/js/components/responder/StatusButton.vue"
      issue: "Fixed positioning at bottom-[80px] overlaps content area by ~96px"
    - path: "resources/js/components/responder/ChatTab.vue"
      issue: "No bottom padding to push content above StatusButton overlay"
    - path: "resources/js/layouts/ResponderLayout.vue"
      issue: "Slot wrapper does not reserve space for StatusButton"
  missing:
    - "Add bottom padding (~100px) to ChatTab or Station page wrapper when StatusButton is visible"
  debug_session: ".planning/debug/chat-input-hidden-by-status-btn.md"
