---
status: partial
phase: 22-alert-feed-event-history-responder-context-dpa-compliance
source: [22-VERIFICATION.md]
started: 2026-04-22T00:00:00Z
updated: 2026-04-22T00:00:00Z
---

## Current Test

[awaiting human testing]

## Tests

### 1. Two-browser ACK propagation (ALERTS-01/02)
expected: Cross-operator ACK state propagates in real time via FrasAlertAcknowledged broadcast on fras.alerts channel
result: [pending]

Visit `/fras/alerts` in two browser tabs logged in as different operators. Trigger a Critical RecognitionAlertReceived via tinker. Confirm the alert appears in both tabs. ACK the alert in Tab 1. Confirm the card disappears from Tab 2 within ~1s.

### 2. Critical audio cue (ALERTS-03)
expected: Critical alert plays P1 tone once via shared useAlertSystem composable; tab must be visible and audio not muted
result: [pending]

Visit `/fras/alerts` as an operator. Trigger a Critical-severity RecognitionAlertReceived via tinker. Confirm an audible severity-distinct tone plays. Confirm no duplicate or overlapping audio (reuses useAlertSystem, not a parallel stack).

### 3. Event history filter UX (ALERTS-04/05/07)
expected: All four filter dimensions function; pagination shows numbered links (1 2 ... N); URL stays clean during typing
result: [pending]

Visit `/fras/events` as an operator. Filter by date range, severity pills, camera select, and free-text search (type 3+ chars). Confirm filters compose correctly. Confirm debounced search (300ms delay) updates URL with replace:true. Confirm numbered pagination appears.

### 4. Promote-to-Incident round-trip (ALERTS-05)
expected: PromoteIncidentModal submits reason (min 8 chars) + priority; redirect to incidents/{id}
result: [pending]

From `/fras/events` detail modal, manually promote a non-Critical recognition event to an Incident. Confirm the redirect lands on the new Incident's show page.

### 5. Responder POI accordion rendering (INTEGRATION-02)
expected: POI accordion renders; responder never sees the raw scene image; UserRound fallback appears if face crop returns 403
result: [pending]

As a responder, open an Incident created from a recognition event. Navigate to the SceneTab. Confirm the "Person of Interest" accordion is visible with face crop thumbnail (or UserRound fallback), personnel name, category chip, camera label, and event timestamp. Confirm no scene image is present anywhere on the page.

### 6. Privacy Notice legal content review (DPA-01/06)
expected: Public unauthenticated page renders CDRRMO header; bilingual toggle works; all required DPA sections present
result: [pending]

Visit `/privacy` as a non-authenticated user (incognito/logged-out). Confirm the CDRRMO-branded notice renders in English. Click the Filipino toggle. Confirm bilingual content switch. Verify the page contains biometric data collection, lawful basis, retention, and data-subject rights sections. DPO placeholders must be filled before go-live.

### 7. DPA PDF export visual inspection (DPA-06)
expected: 4 PDFs generated at storage/app/dpa-exports/{date}/; all render legibly on screen and on A4 print
result: [pending]

Run `php artisan fras:dpa:export --doc=pia --lang=en` on the production server. Open the generated PDF. Confirm it renders with DejaVu Sans font, 10 H2 sections, and readable formatting. Repeat for signage (EN + TL) and operator-training documents.

### 8. CDRRMO legal formal sign-off (DPA-07)
expected: fras_legal_signoffs row persisted; 22-VALIDATION.md sign-off line appended; milestone gate cleared
result: [pending]

CDRRMO legal team reviews `/privacy` page content and `docs/dpa/` artifacts (PIA-template.md, signage-template.md, signage-template.tl.md, operator-training.md) against RA 10173. Upon approval, run: `php artisan fras:legal-signoff --signed-by='...' --contact='...'` and confirm a fras_legal_signoffs row is written and the VALIDATION.md sign-off checkbox flips. This is the milestone-close gate.

## Summary

total: 8
passed: 0
issues: 0
pending: 8
skipped: 0
blocked: 0

## Gaps
