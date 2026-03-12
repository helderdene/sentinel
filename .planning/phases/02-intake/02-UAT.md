---
status: complete
phase: 02-intake
source: [02-01-SUMMARY.md, 02-02-SUMMARY.md, 02-03-SUMMARY.md]
started: 2026-03-13T02:10:00Z
updated: 2026-03-13T02:40:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Triage Form - Incident Type Combobox
expected: Navigate to /incidents/create. Click the "Incident Type" combobox. Type a keyword like "fire". Grouped results appear filtered by the search term. Select an item — the input shows the type code + name (e.g., "FIR-001 Structure Fire"), not a raw ID.
result: pass

### 2. Triage Form - Priority Auto-Suggestion
expected: Select an incident type — priority buttons auto-select based on the type's default. Then type escalation keywords in Notes (e.g., "trapped children") — priority updates after ~500ms with a confidence percentage shown next to the buttons.
result: pass

### 3. Triage Form - Priority Override
expected: After auto-suggestion selects a priority, click a different priority button (e.g., P2 instead of P1). The override sticks. Submit the incident — on the detail page, timeline shows a "Priority Override" entry noting the suggested vs selected priority.
result: pass

### 4. Triage Form - Geocoding Autocomplete
expected: Type an address in the Location field (e.g., "villa kanga butuan"). A dropdown of location suggestions appears. Select one — coordinates auto-populate below, and barangay is shown as "Will be assigned on submit".
result: issue
reported: "the coordinate given is so far from the exact location"
severity: cosmetic

### 5. Triage Form - Full Submit Flow
expected: Fill all fields (channel, caller name/contact, incident type, location, notes). Click "+ Create Incident". Redirected to the dispatch queue at /incidents/queue. The new incident appears in the queue with correct priority badge and colored border.
result: pass

### 6. Dispatch Queue - Priority Ordering
expected: Create incidents with different priorities (P1, P3). On /incidents/queue, P1 incidents appear above P3. Same-priority incidents are ordered by creation time (FIFO). Each row has a colored left border matching its priority.
result: pass

### 7. Dispatch Queue - Polling Refresh
expected: Open /incidents/queue. Create a new incident in a separate tab/window. Within ~10 seconds, the new incident appears in the queue without a manual page refresh and without visible page flicker.
result: pass

### 8. Incidents List - Status Filtering
expected: Navigate to /incidents. All incidents shown by default. Click a status filter button (e.g., "PENDING") — list filters to only that status. Pagination works with cursor-based navigation.
result: pass

### 9. Incident Detail - Two-Column Layout with Timeline
expected: Click an incident from the queue or list. Detail page shows two columns: left has incident details (type, channel, location, barangay, caller, notes), right has Timeline with "Incident Created" entry showing type, priority, channel, location, caller, timestamp, and actor name.
result: pass

### 10. Dashboard - Channel Monitor Widget
expected: Navigate to Dashboard as a dispatcher/supervisor/admin. A "Channel Monitor" widget appears showing 5 cards (Phone, SMS, App, IoT, Radio) each with a pending incident count badge.
result: pass

### 11. Sidebar Navigation
expected: As a dispatcher/supervisor/admin, sidebar shows "Incident Queue", "Incidents", and "+ New Incident" links. Each link navigates to the correct page. The current page is highlighted in the sidebar.
result: pass

### 12. IoT Webhook Endpoint
expected: Send a POST to /webhooks/iot-sensor with valid HMAC signature, X-Timestamp header, and a sensor payload (e.g., sensor_type: "flood_gauge", value: 85, threshold: 50). Returns 201 with incident_no. The incident appears in the queue with channel "IoT Sensor" and correct type.
result: pass

### 13. SMS Webhook Endpoint
expected: Send a POST to /webhooks/sms-inbound with a body containing sender number and message text (e.g., "sunog sa villa kanga"). Returns 200 with auto-reply text. Incident created with channel "SMS", classified type based on keyword, and location extracted from message.
result: pass

## Summary

total: 13
passed: 12
issues: 1
pending: 0
skipped: 0

## Gaps

- truth: "Geocoding returns coordinates close to the actual queried location"
  status: failed
  reason: "User reported: the coordinate given is so far from the exact location"
  severity: cosmetic
  test: 4
  root_cause: "Stub geocoding service generates deterministic coordinates from query hash, not real geocoding. This is by-design — real Mapbox integration comes in Phase 6. Stub was improved to constrain coordinates within ~3km of Butuan center."
  artifacts:
    - path: "app/Services/StubMapboxGeocodingService.php"
      issue: "Hash-based coordinate offsets don't correspond to real locations"
  missing:
    - "Real Mapbox geocoding API integration (Phase 6)"
  debug_session: ""
