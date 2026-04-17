---
status: partial
phase: 15-close-rspdr-real-time-dispatch-visibility
source: [15-VERIFICATION.md]
started: 2026-04-17T00:00:00Z
updated: 2026-04-17T00:00:00Z
---

## Current Test

[awaiting human testing — user replied `continue` to close phase; D-16 6-step checklist deferred for live two-browser session]

## Tests

### 1. Scene Progress visibility gate (D-02)
expected: Selecting a TRIAGED incident shows no SCENE PROGRESS section. After responder advances to ON_SCENE, the bar appears with the current `checklist_pct` value.
result: [pending]

### 2. Live checklist update (RSPDR-06, D-01/D-03/D-04)
expected: Responder ticks a checklist item; dispatcher Scene Progress bar animates to the new percentage within ~1 second. No audio cue plays. No ticker entry is added.
result: [pending]

### 3. Resource request flow (RSPDR-10, D-05/D-06/D-07/D-09)
expected: Responder submits a Medevac resource request with notes. Dispatcher observes simultaneously: (a) triangle-wave arpeggio audio, acoustically distinct from priority/message/ack/mutual-aid tones, (b) new live ticker entry `Resource: Medevac — <responder name> — <notes>`, (c) new top row in RESOURCE REQUESTS section of IncidentDetailPanel with resource label, timestamp, requester, notes.
result: [pending]

### 4. State-sync reload (D-08)
expected: With a resource request on screen, hard-reload the dispatch console (Cmd+R). After reload, RESOURCE REQUESTS section still shows the historical request (hydrated from state-sync `incident.resource_requests[]`).
result: [pending]

### 5. Audio distinctiveness (D-09)
expected: Trigger new P2 incident, new message, resource request, and mutual aid in quick succession. Each of the four tones is subjectively distinguishable.
result: [pending]

### 6. XSS spot-check (T-15-02)
expected: Responder submits notes `<script>alert(1)</script>`. Dispatch detail panel renders the literal string (no alert dialog fires). Vue `{{ }}` auto-escaping is the mitigation.
result: [pending]

## Summary

total: 6
passed: 0
issues: 0
pending: 6
skipped: 0
blocked: 0

## Gaps

_No gaps reported yet — all 6 tests pending live two-browser verification._
