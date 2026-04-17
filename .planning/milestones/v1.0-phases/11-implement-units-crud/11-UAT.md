---
status: complete
phase: 11-implement-units-crud
source: [11-01-SUMMARY.md, 11-02-SUMMARY.md]
started: 2026-03-14T00:00:00Z
updated: 2026-03-14T05:42:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Navigate to Units via Sidebar
expected: Clicking "Units" in the admin sidebar navigates to /admin/units. The page loads with a table showing columns for ID, callsign, type, status, crew count, and agency.
result: pass

### 2. Create a New Unit
expected: Clicking "Add Unit" opens the create form. Select a type (e.g., Ambulance), fill in agency (choose from CDRRMO/BFP/PNP or select "Other" for free-text), set crew capacity, shift, and status. On submit, the unit appears in the index table with an auto-generated ID like "AMB-01". The type field should only be available during creation.
result: issue
reported: "console error ReferenceError: Cannot access 'form' before initialization at UnitForm.vue:64; then TypeError: Cannot read properties of undefined (reading 'charAt') at UnitForm.vue:188; then SelectItem empty value error for Shift"
severity: blocker
fix: Moved useForm() before computed that references it; changed enum prop types from Array<{value:string}> to string[]; changed Shift empty SelectItem value to 'none' with transform

### 3. Crew Assignment Multi-Select
expected: On the create or edit form, the crew assignment section shows a searchable multi-select. Typing filters available members. Selecting a member adds them as a removable chip/badge. If crew count exceeds capacity, an over-capacity warning badge appears (but does not block save).
result: pass

### 4. Edit an Existing Unit
expected: Clicking a unit row or edit button opens the edit form pre-filled with current values. The type field is visible but disabled (greyed out). Changing callsign, agency, status, crew, or notes and saving updates the unit. Changes reflect in the index table.
result: pass

### 5. Decommission a Unit
expected: Clicking decommission on an active unit shows a confirmation dialog. After confirming, the unit row dims (reduced opacity), shows a "Decommissioned" badge, and crew members are unassigned from the unit. The decommission button is replaced with a recommission button.
result: issue
reported: "SQLSTATE[42703]: Undefined column: 7 ERROR: column decommissioned_at of relation units does not exist"
severity: blocker
fix: Ran pending migration (2026_03_13_210232_add_decommissioned_at_to_units_table)

### 6. Recommission a Decommissioned Unit
expected: Clicking "Recommission" on a decommissioned unit restores it to active status. The row returns to full opacity, the "Decommissioned" badge is removed, and status returns to "Available".
result: pass

### 7. Type and Status Badges
expected: In the index table, unit types (Ambulance, Fire Truck, etc.) display with distinct color badges. Status values (Available, Offline, Dispatched) also have distinct color badges using the design system color-mix pattern.
result: pass

### 8. Agency "Other" Free-Text
expected: On the create/edit form, the agency dropdown lists CDRRMO, BFP, PNP. Selecting "Other" reveals a free-text input where a custom agency name can be typed. Saving preserves the custom agency name.
result: issue
reported: "no text input is showing; then cant type in the field; then validation error The selected shift is invalid"
severity: major
fix: Added showCustomAgency ref flag instead of deriving from form.agency; switched to v-model with watcher; changed shift SelectItem values from Day/Night to day/night to match backend validation

## Summary

total: 8
passed: 5
issues: 3
pending: 0
skipped: 0

## Gaps

[none - all issues were fixed inline during testing]
