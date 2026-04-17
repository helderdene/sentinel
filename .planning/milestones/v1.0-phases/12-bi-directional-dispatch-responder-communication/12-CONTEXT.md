# Phase 12: Bi-directional Dispatch-Responder Communication - Context

**Gathered:** 2026-03-14
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire up the dispatch side of incident messaging so dispatchers can see and reply to responder messages in the incident detail panel, upgrade the channel architecture from user-level to incident-level for true group chat, add dispatch notification indicators (queue badge, topbar count, audio cue), and update the responder ChatTab to consume the new incident-level channel. The responder ChatTab UI structure stays intact — only the channel subscription and message visibility change.

</domain>

<decisions>
## Implementation Decisions

### Dispatch Chat Placement
- Messages section added to the right-panel incident detail view, positioned above the Timeline section (below Assignees/Dispatch chips)
- Collapsible section — always collapsed by default
- Section header shows unread count badge ("MESSAGES (3 new)")
- Auto-expands when an incident with unread messages is selected; clears unread badge on expand
- Fixed max height (~200px) with overflow scroll to prevent pushing other sections out of view
- Input area (quick-reply chips + text input) only visible when Messages section is expanded

### Message Targeting & Channel Architecture
- Shift from `user.{id}` to incident-level channel: `incident.{id}.messages`
- All messages broadcast to incident channel — every participant (dispatch + all assigned units) sees all messages
- True group chat model: responder messages visible to dispatch AND all other assigned units on the same incident
- Additionally broadcast to `dispatch.incidents` so all online dispatchers see messages for any active incident (not just the one currently selected)
- Dispatcher sends message → all assigned responders receive it via incident channel
- Responder sends message → dispatch + all other assigned responders receive it
- Update existing responder ChatTab to subscribe to `incident.{id}.messages` instead of `user.{id}` for MessageSent events

### Dispatch Notifications
- Unread message dot/count badge on incident's queue row in the left panel
- Global unread messages count in topbar next to existing stats (ACTIVE, CRITICAL, TOTAL, MSGS)
- Clicking queue row with unread messages opens incident detail with Messages section auto-expanded
- Subtle audio cue via Web Audio API (distinct from priority alert tones) — only plays when message is for an incident NOT currently selected
- No typing indicator — messages appear when sent, no ephemeral composing awareness

### Dispatch Quick Replies
- 7 dispatcher-specific command/coordination chips: "Copy", "Stand by", "Proceed", "Return to station", "Backup en route", "Update status", "Acknowledged"
- Different tone from responder's 8 field-update chips (On scene, Need backup, Patient stable, etc.)
- Wrap grid layout (2 rows) within the 360px right panel — all chips visible without scrolling
- Free text input below chips, same as responder pattern

### Claude's Discretion
- Exact channel authorization logic for incident-level channels
- Message sender display format (unit callsign + name)
- Audio cue frequency/duration for message notification
- Unread count tracking mechanism (client-side vs server-side read receipts)
- How to handle channel subscription lifecycle (subscribe on assignment, unsubscribe on unassign/resolve)
- Backend endpoint design for dispatch-side message sending
- Whether to create a shared message component or keep dispatch/responder message rendering separate

</decisions>

<specifics>
## Specific Ideas

- Unified thread with messages tagged by sender name + unit callsign (e.g., "FIRE-01 · J. Cruz") for multi-unit incidents
- Matches the radio-channel mental model — one frequency per incident, everyone hears everything
- Quick-reply chips for dispatch have a coordination/command tone vs responder's field-status tone
- "Color carries meaning" continues — dispatch messages styled differently from responder messages (own vs incoming bubble colors)
- Message notification audio should be distinctly softer than priority alert tones to avoid alert fatigue

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `ChatTab.vue`: Full responder chat implementation with quick-reply chips + free text — reference for dispatch-side message component
- `MessageSent` event (`app/Events/MessageSent.php`): Currently broadcasts on `user.{recipientId}` — needs refactoring to incident-level channel
- `IncidentMessage` model: Polymorphic sender, quick_reply flag, read_at tracking — backend ready
- `SendMessageRequest`: Validation already exists for body + is_quick_reply
- `useResponderSession.ts`: Listens for `MessageSent` on `user.{userId}` — needs channel migration
- `useDispatchFeed.ts`: Consumes 5 broadcast events — needs MessageSent added
- `useAlertSystem.ts`: Web Audio API tones — extend with subtle message notification sound
- `IncidentDetailPanel` (in Console.vue right panel): Where Messages section will be inserted

### Established Patterns
- Collapsible accordion sections: `ChatTab`/`SceneTab` use grid-template-rows for smooth expand/collapse
- Echo composables with `useEcho()` from `@laravel/echo-vue` for WebSocket subscriptions
- Fire-and-forget `fetch()` for non-blocking sends (ChatTab pattern)
- Reactive local state for WebSocket mutations without page reload
- `color-mix()` for opacity tints on badges/indicators

### Integration Points
- `routes/channels.php`: Add `incident.{id}.messages` channel authorization
- `app/Http/Controllers/ResponderController.php`: `sendMessage()` method — needs channel refactoring
- `app/Http/Controllers/DispatchConsoleController.php`: Add dispatch-side `sendMessage()` endpoint
- `resources/js/pages/dispatch/Console.vue`: Right panel incident detail — add Messages section
- `resources/js/composables/useDispatchFeed.ts`: Add MessageSent event listener
- `resources/js/composables/useResponderSession.ts`: Migrate MessageSent listener from user channel to incident channel

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 12-bi-directional-dispatch-responder-communication*
*Context gathered: 2026-03-14*
