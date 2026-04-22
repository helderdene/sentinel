# FRAS Operator Training — IRMS DPA Compliance

> **Audience:** CDRRMO operators, supervisors, dispatchers, responders, and admins.
> **Purpose:** Ensure every human who interacts with FRAS-sourced personal data understands their scope of access, the semantics of each action they can take, and the DPA obligations that bind their role.
> **Prerequisite:** Read `docs/dpa/PIA-template.md` and the CDRRMO Operator Manual § FRAS chapter.

---

## Role Matrix

The table below enumerates which surfaces each IRMS role can access. A check (✓) means the role can reach the surface through the UI; a cross (✗) means the backend refuses the request regardless of UI state.

| Role | /fras/alerts | /fras/events | Manage Cameras | Manage Personnel | Promote Event | View Scene Image | View Face Image |
|------|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Admin | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Supervisor | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Operator | ✓ | ✓ | ✗ | ✗ | ✓ | ✓ | ✓ |
| Dispatcher | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Responder | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✓ (face only, on assigned incident) |

**Why responders cannot see scene images:** the scene crop captures bystanders who are not the subject of the Person-of-Interest match. Responders receive only the cropped face of the identified personnel plus metadata (category, last seen timestamp, BOLO expiry) on incidents to which they are assigned. The backend never serves the `scene_image_url` field to responder sessions (Plan 22-08 D-27).

---

## ACK vs Dismiss

Every FRAS alert that arrives on `/fras/alerts` must be resolved by exactly one of the following actions:

- **Acknowledge** — "I am handling this event." No reason required. Removes the card from every operator's feed in real time via `FrasAlertAcknowledged` broadcast. The event row persists; `acknowledged_by` and `acknowledged_at` are set.
- **Dismiss** — "Not actionable, or a false positive." Reason is **required** and must be one of four enum values:
  - `FalseMatch` — the recognition is incorrect (the person in frame is not the enrolled personnel).
  - `TestEvent` — the event was generated during testing or calibration.
  - `Duplicate` — a higher-severity alert already covers this situation.
  - `Other` — free-text elaboration required in `dismiss_reason_note`.

Dismissed events cannot be un-dismissed. If a dismiss was in error, promote the original event (see next section) or create a new Incident manually via `/intake`.

---

## When to Promote to Incident

The **Promote** action is available on `/fras/events` detail modal for non-Critical severity events (i.e. Warning severity, or Critical events that were previously dedup'd). Requirements:

- The operator picks a priority (P1–P4) based on the situation at time of promotion.
- The operator writes a **reason** of 8–500 characters explaining why the recognition warrants full incident treatment.
- On submit, an Incident is created and linked to the original recognition event. A `fras_operator_promote` trigger row is written to `incident_timeline.event_data` for full audit trace.

Events that are already linked to an Incident cannot be promoted twice; the UI shows a "Created Incident" pill instead of the Promote button.

---

## Scene Image Access Restrictions

- **Operators / Supervisors / Admins** may view the scene image via a signed 5-minute URL issued from `/fras/events/{id}/scene`. Every view generates a `fras_access_log` row with `action = View` and `subject_type = RecognitionEventScene`.
- **Responders** do **not** receive scene image URLs. The `SceneTab` on `/responder/station` omits the scene field for FRAS-linked incidents and renders only the person-of-interest accordion with the face crop.
- **Citizens** (DPA data subjects) have no direct UI access; they exercise rights via the DPO contact in the signage / privacy notice.

> Signed URLs do not prevent a human operator from screen-recording the page. This is a **DPA reviewer concern**, not a technical control. Operators are bound by the CDRRMO acceptable-use policy and by the `fras_access_log` audit trail, which is reviewed weekly by the DPO.

---

## Signed URL Expiry

Every face and scene image URL expires server-side after **5 minutes**. If an operator closes and reopens the event, the page regenerates a fresh signed URL and writes a new `fras_access_log` row. Reloading does **not** bypass the audit log — every hydration counts as one access.

---

## Retention Purge Cadence

The daily purge command runs at **02:00 Asia/Manila** via the Laravel scheduler:

- **Scene images** — deleted 30 days after `captured_at` (config: `fras.retention.scene_image_days`).
- **Face crops** — deleted 90 days after `captured_at` (config: `fras.retention.face_crop_days`).
- **Access log rows** — deleted 2 years (730 days) after `accessed_at` (config: `fras.retention.access_log_retention_days`).
- **Exception (DPA legal wall):** events linked to a non-terminal Incident (status NOT IN Resolved/Cancelled) are protected — the purge skips them and increments `skipped_for_active_incident` on the `fras_purge_runs` summary row.

Commands for operators and DPO:

```bash
# Review what the next run will delete without actually deleting
php artisan fras:purge-expired --dry-run

# Inspect the most recent purge run summary
php artisan tinker --execute "\App\Models\FrasPurgeRun::latest('started_at')->first();"

# Legal pre-go-live review (before first real purge runs)
php artisan fras:dpa:export --doc=all --lang=en
```

---

## DPA Contact

For questions about this training, data-subject requests, or breach reports, contact the CDRRMO Data Protection Officer at the address posted on-premise via the CCTV signage (`docs/dpa/signage-template.md`) and on the public Privacy Notice page (`/privacy`).
