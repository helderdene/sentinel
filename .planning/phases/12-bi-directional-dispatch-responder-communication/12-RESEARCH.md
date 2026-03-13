# Phase 12: Bi-directional Dispatch-Responder Communication - Research

**Researched:** 2026-03-14
**Domain:** Real-time WebSocket messaging (Laravel Broadcasting + Echo Vue), dispatch console UI, channel architecture
**Confidence:** HIGH

## Summary

This phase upgrades the existing responder-only messaging system to a true bi-directional group chat. The core change is architectural: shifting from a user-level channel (`user.{id}`) to an incident-level channel (`incident.{id}.messages`) so all participants on an incident see all messages. The dispatch side needs a new Messages section in the IncidentDetailPanel, a send endpoint in DispatchConsoleController, notification indicators (queue badge, topbar count, audio cue), and the responder side needs its channel subscription migrated.

All building blocks exist in the codebase. The `IncidentMessage` model, `SendMessageRequest` validation, `ChatTab.vue` UI, `useAlertSystem.ts` audio infrastructure, and Echo composable patterns (`useEcho` from `@laravel/echo-vue`) are all proven. The work is primarily wiring -- refactoring the `MessageSent` event's `broadcastOn()` to target incident channels, adding channel authorization, building the dispatch-side message section, and threading unread counts through the dispatch composable chain.

**Primary recommendation:** Refactor the `MessageSent` event to broadcast on both `incident.{incidentId}.messages` (for participants) and `dispatch.incidents` (for all dispatchers), add a `sendMessage()` endpoint to `DispatchConsoleController`, build a collapsible Messages section in `IncidentDetailPanel`, and track unread counts client-side via a Map keyed by incident ID in `useDispatchFeed`.

<user_constraints>

## User Constraints (from CONTEXT.md)

### Locked Decisions
- Messages section added to right-panel incident detail view, above Timeline section, below Assignees/Dispatch chips
- Collapsible section, always collapsed by default; auto-expands when incident with unread messages is selected
- Section header shows unread count badge ("MESSAGES (3 new)")
- Fixed max height (~200px) with overflow scroll
- Input area (quick-reply chips + text input) only visible when expanded
- Channel architecture: shift from `user.{id}` to `incident.{id}.messages`
- All messages broadcast to incident channel; true group chat model
- Additionally broadcast to `dispatch.incidents` so all online dispatchers see messages for any active incident
- Dispatcher sends message -> all assigned responders receive via incident channel
- Responder sends message -> dispatch + all other assigned responders receive
- Update responder ChatTab to subscribe to `incident.{id}.messages` instead of `user.{id}`
- Unread message dot/count badge on incident queue row in left panel
- Global unread messages count in topbar ("MSGS" stat)
- Clicking queue row with unread auto-opens incident detail with Messages auto-expanded
- Subtle audio cue via Web Audio API (distinct from priority tones); only plays for messages on non-selected incidents
- No typing indicator
- 7 dispatcher quick-reply chips: "Copy", "Stand by", "Proceed", "Return to station", "Backup en route", "Update status", "Acknowledged"
- Wrap grid layout (2 rows) within 360px right panel
- Free text input below chips
- Unified thread with messages tagged by sender name + unit callsign (e.g., "FIRE-01 . J. Cruz")
- Radio-channel mental model; dispatch messages styled differently from responder messages

### Claude's Discretion
- Exact channel authorization logic for incident-level channels
- Message sender display format (unit callsign + name)
- Audio cue frequency/duration for message notification
- Unread count tracking mechanism (client-side vs server-side read receipts)
- Channel subscription lifecycle (subscribe on assignment, unsubscribe on unassign/resolve)
- Backend endpoint design for dispatch-side message sending
- Whether to create a shared message component or keep dispatch/responder message rendering separate

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope

</user_constraints>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Broadcasting | v12 | Server-side event broadcasting | Already configured with Reverb; MessageSent event exists |
| @laravel/echo-vue | v2 | Vue composables for WebSocket subscriptions | `useEcho` already used in useDispatchFeed.ts and useResponderSession.ts |
| Laravel Reverb | v1 | WebSocket server | Already running; channel auth configured in routes/channels.php |
| Web Audio API | native | Message notification sound | useAlertSystem.ts already wraps AudioContext for priority tones |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Vue 3 Composition API | v3 | Reactive state management for unread counts | Map<string, number> for per-incident tracking |
| Wayfinder | v0 | TypeScript route generation for dispatch sendMessage | Auto-generates action imports for new endpoint |
| Tailwind CSS | v4 | Styling dispatch message section | Design token system already established |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Client-side unread tracking | Server-side read receipts | Client-side is simpler and sufficient; server-side adds DB writes on every message view -- unnecessary for v1 ops context |
| Shared message component | Separate dispatch/responder renderers | **Recommend separate** -- dispatch has 200px constrained space with dense info layout vs responder has full-screen mobile chat; shared component would over-abstract |

**Installation:** No new packages needed. All dependencies are already installed.

## Architecture Patterns

### Backend Changes

```
app/Events/MessageSent.php           # Refactor broadcastOn() to incident + dispatch channels
app/Http/Controllers/
    DispatchConsoleController.php     # Add sendMessage() method
    ResponderController.php           # Update sendMessage() dispatch call
routes/web.php                       # Add dispatch/{incident}/message route
routes/channels.php                  # Add incident.{id}.messages authorization
```

### Frontend Changes

```
resources/js/
    composables/
        useDispatchFeed.ts           # Add MessageSent listener on dispatch.incidents
    components/dispatch/
        IncidentDetailPanel.vue      # Add collapsible Messages section
        DispatchMessagesSection.vue   # NEW: messages + input for dispatch context
        QueueCard.vue                # Add unread badge indicator
    components/dispatch/
        DispatchTopbar.vue           # Add MSGS stat pill
    pages/dispatch/Console.vue       # Thread unread state, auto-expand logic
    composables/
        useResponderSession.ts       # Migrate channel from user.{id} to incident.{id}.messages
    types/dispatch.ts                # Add message-related types
```

### Pattern 1: Dual-Channel Broadcasting (Incident + Dispatch)
**What:** MessageSent broadcasts on two channels: `incident.{id}.messages` (for participants) and `dispatch.incidents` (for all dispatchers)
**When to use:** Every message send (from both responder and dispatcher)
**Example:**
```php
// MessageSent::broadcastOn()
public function broadcastOn(): array
{
    return [
        new PrivateChannel('incident.'.$this->incidentId.'.messages'),
        new PrivateChannel('dispatch.incidents'),
    ];
}
```
**Why dual channels:**
- `incident.{id}.messages` -- only participants on that specific incident subscribe (responders + dispatcher viewing that incident). Channel auth checks user is assigned or is dispatch role.
- `dispatch.incidents` -- all online dispatchers get the message. Already subscribed to this channel (5 events consumed). Enables unread badge on any queue row, not just the currently selected incident.

### Pattern 2: Client-Side Unread Tracking via Reactive Map
**What:** A `Map<string, number>` (keyed by incident ID) tracks unread counts per incident, stored in the dispatch feed composable
**When to use:** For dispatch-side unread badges on queue rows and global count
**Example:**
```typescript
// In useDispatchFeed.ts
const unreadByIncident = ref<Map<string, number>>(new Map());

// On MessageSent from dispatch.incidents channel:
useEcho<MessagePayload>('dispatch.incidents', 'MessageSent', (m) => {
    if (m.sender_id !== currentUserId) {
        const current = unreadByIncident.value.get(m.incident_id) ?? 0;
        unreadByIncident.value.set(m.incident_id, current + 1);
    }
});

// Computed global count:
const totalUnreadMessages = computed(() => {
    let total = 0;
    for (const count of unreadByIncident.value.values()) {
        total += count;
    }
    return total;
});
```

### Pattern 3: Collapsible Section with Grid-Template-Rows Accordion
**What:** Smooth expand/collapse animation for the Messages section in IncidentDetailPanel
**When to use:** This is the established project pattern (ChatTab/SceneTab use it)
**Example:**
```vue
<div
    class="grid transition-[grid-template-rows] duration-200"
    :style="{ gridTemplateRows: isExpanded ? '1fr' : '0fr' }"
>
    <div class="overflow-hidden">
        <!-- Messages content -->
    </div>
</div>
```
**Source:** STATE.md decision [05-03]: Grid-template-rows accordion for smooth CSS-only expand/collapse

### Pattern 4: Fire-and-Forget fetch() for Message Sending
**What:** Use direct fetch() with XSRF token for non-blocking sends
**When to use:** Both dispatch and responder message sending
**Source:** Established in ChatTab.vue, StatusPipeline, AssignmentChip -- consistent across the codebase
```typescript
async function send(body: string, isQuickReply: boolean): Promise<void> {
    await fetch(sendMessage.url({ incident: String(incidentId) }), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
        body: JSON.stringify({ body, is_quick_reply: isQuickReply }),
    });
}
```

### Anti-Patterns to Avoid
- **Subscribing to incident channel from within IncidentDetailPanel:** The panel mounts/unmounts as incidents are selected. Subscribing/unsubscribing on each selection adds WebSocket churn. Instead, listen on `dispatch.incidents` (always subscribed) for notifications, and only subscribe to `incident.{id}.messages` for full message history when the Messages section is expanded.
- **Storing messages in Inertia props:** Messages are ephemeral during a dispatch session. Use local reactive state, not Inertia page props, to avoid full-page reloads destroying message state.
- **Building a shared message component between dispatch and responder:** The dispatch context (200px height, 360px width, alongside timeline/assignees) is fundamentally different from responder (full-screen mobile chat). Over-abstracting hurts both.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| WebSocket channel subscription lifecycle | Manual Echo.join/leave | `useEcho` composable from @laravel/echo-vue | Auto-subscribes on mount, auto-unsubscribes on unmount; handles reconnection |
| Audio notification | Custom audio file loading | `useAlertSystem.ts` with Web Audio API oscillator | Already has AudioContext management, user-gesture unlock, gain envelopes |
| XSRF token extraction | New utility function | Copy existing `getXsrfToken()` pattern from ChatTab/Console | Proven pattern, 4+ existing usages |
| Expand/collapse animation | JavaScript height animation | CSS grid-template-rows pattern | GPU-accelerated, 0 JavaScript, established project pattern |
| Message validation | Inline controller validation | `SendMessageRequest` FormRequest | Already exists and is tested |

**Key insight:** Every technical building block for this phase already exists in the codebase. The work is integration and wiring, not greenfield development.

## Common Pitfalls

### Pitfall 1: MessageSent Event Constructor Change Breaks Responder
**What goes wrong:** The current `MessageSent` constructor takes `recipientId` as first parameter. Changing the constructor signature without updating all dispatch call sites causes runtime errors.
**Why it happens:** `ResponderController::sendMessage()` currently passes `0` as recipientId (hardcoded, since dispatch.incidents was the implicit target). The constructor needs to change to accept `incidentId` as the primary identifier.
**How to avoid:** Update the event constructor first, then update both `ResponderController::sendMessage()` and the new `DispatchConsoleController::sendMessage()` simultaneously. The `broadcastWith()` payload should include sender unit info.
**Warning signs:** Messages stop arriving on either dispatch or responder side after the refactor.

### Pitfall 2: Duplicate Messages on Dispatch Side
**What goes wrong:** If a dispatcher sends a message, their own message arrives twice -- once from the optimistic local push and once from the WebSocket echo back via `dispatch.incidents`.
**Why it happens:** The dispatcher is subscribed to `dispatch.incidents` which receives ALL messages including their own.
**How to avoid:** Filter by `sender_id !== currentUserId` in the `dispatch.incidents` MessageSent listener. The sender already has the message from the optimistic local push after the fire-and-forget fetch returns.
**Warning signs:** Every sent message appears twice in the dispatch message list.

### Pitfall 3: Responder Channel Migration Timing
**What goes wrong:** If the responder's `useResponderSession` subscribes to `incident.{id}.messages` but the incident ID isn't available yet (e.g., on standby screen before assignment), the channel name is `incident.null.messages`.
**Why it happens:** `useEcho` is called at composable setup time. If there's no active incident, the channel name is invalid.
**How to avoid:** Only subscribe to the incident message channel when `activeIncident` is non-null. Use a `watch` to dynamically subscribe/unsubscribe as the incident changes, or conditionally call `useEcho` inside a `watchEffect`.
**Warning signs:** Responder receives no messages after assignment, or receives messages from previous incidents.

### Pitfall 4: Unread Count State Desync on Incident Selection
**What goes wrong:** User selects incident with 3 unread -> Messages section auto-expands -> unread cleared. But if messages arrive between page load and click, the count shown may not match.
**Why it happens:** Client-side counts start at 0 on page load but messages may have been sent while the dispatcher was offline.
**How to avoid:** Accept that unread counts are session-local (start at 0 on page load, only track messages received during this session). This matches the radio-dispatch mental model where you don't "catch up" on missed radio traffic.
**Warning signs:** Users expect to see historical unread counts across sessions. Set expectations that counts are live-session only.

### Pitfall 5: Audio Cue Firing for Own Messages
**What goes wrong:** Dispatcher sends a message, hears the notification cue for their own message on other incidents' queue rows.
**Why it happens:** The `dispatch.incidents` channel receives ALL messages. The audio cue logic needs to exclude the sender and only fire for non-selected incidents.
**How to avoid:** Audio cue conditions: `sender_id !== currentUserId && incidentId !== selectedIncidentId`.
**Warning signs:** Notification sound plays every time you send a message.

## Code Examples

### 1. Refactored MessageSent Event

```php
// app/Events/MessageSent.php
class MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $incidentId,
        public int $senderId,
        public string $senderName,
        public string $senderRole,
        public ?string $senderUnitCallsign,
        public string $body,
        public bool $isQuickReply,
        public int $messageId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('incident.'.$this->incidentId.'.messages'),
            new PrivateChannel('dispatch.incidents'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'incident_id' => $this->incidentId,
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'sender_role' => $this->senderRole,
            'sender_unit_callsign' => $this->senderUnitCallsign,
            'body' => $this->body,
            'is_quick_reply' => $this->isQuickReply,
            'sent_at' => now()->toISOString(),
        ];
    }
}
```

### 2. Channel Authorization

```php
// routes/channels.php -- add incident.{id}.messages channel
Broadcast::channel('incident.{incidentId}.messages', function (User $user, string $incidentId) use ($dispatchRoles): bool {
    // Dispatchers/operators/supervisors/admins can access any incident's messages
    if (in_array($user->role, $dispatchRoles)) {
        return true;
    }

    // Responders can access only if their unit is assigned to this incident
    if ($user->role === UserRole::Responder && $user->unit) {
        return $user->unit->activeIncidents()
            ->where('incidents.id', $incidentId)
            ->exists();
    }

    return false;
});
```

### 3. Dispatch sendMessage Endpoint

```php
// DispatchConsoleController::sendMessage()
public function sendMessage(SendMessageRequest $request, Incident $incident): JsonResponse
{
    /** @var User $user */
    $user = $request->user();

    $message = $incident->messages()->create([
        'sender_type' => User::class,
        'sender_id' => $user->id,
        'body' => $request->validated('body'),
        'message_type' => 'text',
        'is_quick_reply' => $request->validated('is_quick_reply', false),
    ]);

    MessageSent::dispatch(
        $incident->id,
        $user->id,
        $user->name,
        $user->role->value,
        null, // dispatchers have no unit callsign
        $message->body,
        $message->is_quick_reply,
        $message->id,
    );

    return response()->json(['message' => 'Message sent.']);
}
```

### 4. Subtle Message Notification Tone

```typescript
// Addition to useAlertSystem.ts
function playMessageTone(): void {
    const ctx = ensureAudioContext();
    if (!ctx || ctx.state !== 'running') return;

    // Soft two-note chime: 523Hz (C5) then 659Hz (E5), low volume
    const notes = [523, 659];
    const duration = 0.1;

    notes.forEach((freq, i) => {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine'; // Softer waveform than default
        osc.frequency.value = freq;
        gain.gain.value = 0.12; // Much quieter than priority tones (0.3)
        osc.start(ctx.currentTime + i * 0.12);
        gain.gain.exponentialRampToValueAtTime(
            0.01,
            ctx.currentTime + i * 0.12 + duration,
        );
        osc.stop(ctx.currentTime + i * 0.12 + duration);
    });
}
```

### 5. Message Payload TypeScript Interface

```typescript
// Addition to dispatch.ts types
export interface DispatchMessagePayload {
    id: number;
    incident_id: string;
    sender_id: number;
    sender_name: string;
    sender_role: string;
    sender_unit_callsign: string | null;
    body: string;
    is_quick_reply: boolean;
    sent_at: string;
}

export interface DispatchMessageItem {
    id: number;
    body: string;
    is_quick_reply: boolean;
    sender_id: number;
    sender_name: string;
    sender_role: string;
    sender_unit_callsign: string | null;
    sent_at: string;
}
```

### 6. Responder Channel Migration

```typescript
// useResponderSession.ts -- migrate from user.{id} to incident.{id}.messages
// OLD:
// useEcho<MessagePayload>(`user.${userId}`, 'MessageSent', (m) => { ... });

// NEW: Use watch to dynamically subscribe based on active incident
watch(
    () => activeIncident.value?.id,
    (incidentId, oldId) => {
        // Dynamic subscription handled by useEcho with deps
    },
);

// Subscribe to incident-level channel when incident is active
// The useEcho composable auto-unsubscribes on unmount
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `user.{id}` channel for messages | `incident.{id}.messages` channel | This phase | True group chat; all participants see all messages |
| Responder-only messaging | Bi-directional dispatch+responder | This phase | Dispatchers can send commands/coordination messages |
| No dispatch message notifications | Queue badge + topbar count + audio | This phase | Dispatchers aware of incoming field communications |
| `MessageSent(recipientId, ...)` | `MessageSent(incidentId, ...)` | This phase | Breaking change to event constructor -- update all callers |

**Deprecated/outdated after this phase:**
- `user.{id}` channel for MessageSent events (replaced by incident-level)
- `recipientId` parameter in MessageSent constructor (removed)
- Direct push to single recipient pattern (replaced by broadcast to channel)

## Open Questions

1. **Loading historical messages when expanding dispatch Messages section**
   - What we know: Messages are stored in `incident_messages` table. Responder gets them via `Incident::messages()` on page load.
   - What's unclear: Should we lazy-load messages when the Messages section is first expanded, or include them in the initial Inertia page load?
   - Recommendation: **Lazy-load via fetch** when Messages section is first expanded for an incident. Avoid bloating the initial Inertia payload with messages for ALL queue incidents. Cache loaded messages in a client-side Map keyed by incident ID.

2. **Responder `useEcho` dynamic channel subscription**
   - What we know: `useEcho` subscribes at composable setup time. The incident may not be available yet (standby screen).
   - What's unclear: Whether `useEcho`'s `deps` parameter supports reactive channel names that change.
   - Recommendation: Based on the SKILL.md documentation, `useEcho` has a `deps` parameter for reactive state. Pass `[() => activeIncident.value?.id]` as deps so it re-subscribes when the incident changes. If this doesn't work, use manual `leaveChannel()`/`listen()` control returned from `useEcho`.

3. **Message count for resolved incidents**
   - What we know: When an incident is resolved, it's removed from `localIncidents` in useDispatchFeed.
   - What's unclear: Should unread counts for resolved incidents be cleared proactively?
   - Recommendation: When incident is removed from localIncidents (on RESOLVED status), delete its entry from `unreadByIncident` Map. No cleanup needed for channel subscriptions since dispatch.incidents is the listener, not per-incident channels.

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
| COMM-01 | MessageSent event broadcasts on incident channel + dispatch channel | unit | `php artisan test --compact tests/Feature/Communication/MessageSentEventTest.php -x` | No - Wave 0 |
| COMM-02 | Incident channel authorization (dispatch roles + assigned responders) | feature | `php artisan test --compact tests/Feature/Communication/IncidentMessageChannelTest.php -x` | No - Wave 0 |
| COMM-03 | Dispatch sendMessage endpoint creates message + dispatches event | feature | `php artisan test --compact tests/Feature/Communication/DispatchSendMessageTest.php -x` | No - Wave 0 |
| COMM-04 | Responder sendMessage uses updated MessageSent constructor | feature | `php artisan test --compact tests/Feature/Communication/ResponderSendMessageTest.php -x` | No - Wave 0 |
| COMM-05 | Unauthorized users cannot access incident message channel | feature | `php artisan test --compact tests/Feature/Communication/IncidentMessageChannelTest.php -x` | No - Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact tests/Feature/Communication/ -x`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Communication/MessageSentEventTest.php` -- covers COMM-01
- [ ] `tests/Feature/Communication/IncidentMessageChannelTest.php` -- covers COMM-02, COMM-05
- [ ] `tests/Feature/Communication/DispatchSendMessageTest.php` -- covers COMM-03
- [ ] `tests/Feature/Communication/ResponderSendMessageTest.php` -- covers COMM-04

## Sources

### Primary (HIGH confidence)
- **Codebase inspection** -- Read all referenced files from CONTEXT.md code_context section
  - `app/Events/MessageSent.php` -- current event structure (user.{id} channel)
  - `routes/channels.php` -- current channel auth (dispatch.incidents, dispatch.units, user.{id}, dispatch presence)
  - `resources/js/composables/useResponderSession.ts` -- current message listener (user.{userId})
  - `resources/js/composables/useDispatchFeed.ts` -- current 5-event listener pattern on dispatch channels
  - `resources/js/components/responder/ChatTab.vue` -- full responder chat UI reference
  - `resources/js/components/dispatch/IncidentDetailPanel.vue` -- current right-panel structure
  - `resources/js/components/dispatch/DispatchQueuePanel.vue` -- queue card structure for badge placement
  - `resources/js/components/dispatch/DispatchTopbar.vue` -- stat pill layout for MSGS counter
  - `resources/js/composables/useAlertSystem.ts` -- Web Audio API patterns
  - `app/Http/Controllers/ResponderController.php` -- current sendMessage implementation
  - `app/Http/Controllers/DispatchConsoleController.php` -- dispatch controller for new endpoint

- **@laravel/echo-vue SKILL.md** -- `useEcho` composable API, channel types, type-safe event listening, manual control (leaveChannel/listen/stopListening), deps parameter for reactive state

- **echo-development SKILL.md** -- Broadcasting interfaces (ShouldBroadcast, ShouldDispatchAfterCommit), channel authorization patterns, broadcastOn() with multiple channels

### Secondary (MEDIUM confidence)
- **STATE.md accumulated decisions** -- Established patterns referenced:
  - [05-03]: Grid-template-rows accordion for expand/collapse
  - [05-03]: Fire-and-forget fetch() for non-blocking sends
  - [04-04]: useDispatchFeed as single composable hub consuming broadcast events
  - [04-04]: Ticker events ring buffer for memory management
  - [03-02]: Echo useEcho event names without dot prefix

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- all libraries already installed and used in the codebase
- Architecture: HIGH -- dual-channel broadcast pattern is standard Laravel; all existing patterns directly applicable
- Pitfalls: HIGH -- identified from direct code inspection of current MessageSent event structure and Echo subscription patterns
- Validation: HIGH -- testing patterns well-established with Pest 4; existing IncidentMessageTest.php as reference

**Research date:** 2026-03-14
**Valid until:** 2026-04-14 (stable -- no dependency changes expected)
