# Phase 15: WebRTC Live Video Stream from Responder to Dispatch - Context

**Gathered:** 2026-03-15
**Status:** Ready for planning

<domain>
## Phase Boundary

One-way live video stream from the responder's device camera to the dispatch console using WebRTC for peer-to-peer video. Laravel Reverb handles signaling via client events (whisper) on a presence channel. This gives dispatch real-time visual situational awareness of active incidents. No recording, no audio — video-only, live-only.

</domain>

<decisions>
## Implementation Decisions

### Responder Camera UX
- Dedicated 5th "Stream" tab in responder tab bar (alongside assignment, nav, scene, chat)
- Stream tab shows full-size local camera preview so responder sees exactly what dispatch sees
- Rear camera by default (points at the scene); flip button to switch to front camera
- Video-only stream, no microphone audio (text chat already handles communication)
- "LIVE" indicator visible when streaming, with viewer count showing dispatch is watching

### Dispatch Video Viewer
- New collapsible "Live Feed" section in IncidentDetailPanel, placed between Assignees and Messages sections
- Section collapsed by default; header shows stream count badge (e.g., "LIVE FEED ● 2") when streams are active
- Multiple streams from multiple assigned units displayed as stacked vertical tiles (one per streaming unit)
- Each tile shows unit callsign, LIVE indicator, and video feed
- Expand button on each tile opens a fullscreen overlay covering the dispatch console for focused viewing
- Fullscreen overlay includes unit callsign, LIVE badge, stream duration, and close button

### Stream Lifecycle
- Responder-initiated only — responder taps "Start Stream" in Stream tab; dispatch cannot force/request a stream
- Stream persists through all status transitions (En Route → On Scene → Resolving); auto-stops only on incident resolution
- Responder can manually stop stream at any time via stop button
- Live-only, no recording — pure peer-to-peer WebRTC, no video data touches the server
- Queue card in dispatch shows camera/LIVE indicator when any assigned responder is streaming (consistent with unread message badge pattern)

### Network Resilience
- STUN + TURN server configuration for NAT traversal (TURN needed for mobile 4G carriers with strict NAT)
- TURN/STUN server URLs and credentials configurable via .env variables — works with any provider (Twilio, Metered, self-hosted coturn)
- Default to free Google STUN servers; TURN requires provider configuration
- Auto-degrade video quality on poor connections via WebRTC's built-in bandwidth estimation
- Visual quality indicator (green/yellow/red dot) on video tile so dispatch knows when feed quality is degraded
- Auto-reconnect on stream drop (while WebSocket still connected): new offer/answer exchange, "Reconnecting..." overlay, max 3 retries, then "Stream lost" with manual retry button

### Claude's Discretion
- Specific WebRTC configuration (codec preferences, resolution constraints, ICE gathering strategy)
- Presence channel naming and authorization structure
- Signaling message format and client event (whisper) payload design
- Stream tab UI layout details (button sizes, spacing, indicator animations)
- Video tile aspect ratio and sizing in dispatch panel
- Quality indicator thresholds and implementation approach

</decisions>

<specifics>
## Specific Ideas

- Signaling handled via client events (whisper) on a Reverb presence channel — no server-side signaling endpoints needed (peer-to-peer negotiation)
- Follows existing pattern: PrivateChannel for incident-level features, presence for participant awareness
- Stream tab pattern consistent with existing responder tabs — full-height content area with controls
- Dispatch Live Feed section follows IncidentDetailPanel collapsible section pattern (like Messages section: collapsed by default, badge indicator, auto-expand possible)
- Queue card LIVE badge follows existing unread message badge visual pattern

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `useResponderSession.ts`: Manages responder state, WebSocket subscriptions, status transitions — extend with video stream state
- `useDispatchFeed.ts`: Hub for all dispatch real-time events — extend with video stream signaling
- `useWebSocket.ts`: Connection status monitoring — video should respect connection state
- `useAckTimer.ts`: Pattern for interval-based UI updates — reference for stream duration timer
- `IncidentDetailPanel.vue`: Sectioned panel with collapsible areas — add Live Feed section
- `ResponderLayout.vue`: Provide/inject bridge for layout-page communication — inject video state
- `routes/channels.php`: Channel authorization patterns for dispatch and incident-level features
- `echo().private()` / `useEcho()`: Existing patterns for channel subscription and event listening

### Established Patterns
- Provide/inject bridge between ResponderLayout and Station.vue (no props/emits through Inertia layout)
- Collapsible sections with badge indicators in dispatch panel (Messages section pattern)
- Queue card badges for real-time status (unread message dots)
- Event-based composable hub pattern (useDispatchFeed consumes all broadcast events)
- Client-side reactive state with WebSocket mutations (no page reload)
- Fire-and-forget fetch for background operations

### Integration Points
- `Station.vue`: Add 'stream' to ResponderTab union type, add StreamTab component
- `ResponderLayout.vue`: Provide video streaming state via inject
- `ResponderTabbar.vue`: Add 5th tab icon for Stream
- `IncidentDetailPanel.vue`: Add LiveFeedSection component between Assignees and Messages
- `Console.vue`: Pass stream state to IncidentDetailPanel
- `QueueCard.vue`: Add LIVE indicator badge
- `routes/channels.php`: Add presence channel for video signaling
- `config/webrtc.php` or `.env`: STUN/TURN server configuration

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 15-webrtc-live-video-stream-from-responder-to-dispatch*
*Context gathered: 2026-03-15*
