# Privacy Impact Assessment — CDRRMO FRAS

**Controller:** CDRRMO, City Government of Butuan
**System:** IRMS Face Recognition Alert System (FRAS)
**Last updated:** [DATE]
**Status:** DRAFT — pending DPO sign-off
**Regulatory basis:** Republic Act 10173 (Data Privacy Act of 2012), NPC Memorandum Circular 2022-01 (Biometric Data)

---

## 1. Scope

This Privacy Impact Assessment (PIA) covers the FRAS subsystem of the IRMS platform operated by the City Disaster Risk Reduction and Management Office (CDRRMO) of Butuan City. Scope includes all CCTV cameras enrolled in FRAS, all personnel records registered for facial recognition, the MQTT ingestion pipeline, the recognition-events data store, the operator console (`/fras/alerts` and `/fras/events`), and the responder Person-of-Interest context shown on active incidents. Scope does **not** include non-FRAS CCTV feeds, third-party law-enforcement biometric databases, or any facial recognition service operated outside the CDRRMO.

[CDRRMO_SPECIFIC_FILLIN — enumerate physical camera locations, number of enrolled personnel, and any LGU-specific process variations]

## 2. Biometric Data Types

FRAS processes four distinct categories of personal and biometric data:

1. **Face images (scene crops)** — JPEG/PNG frames captured from CCTV at the moment of a recognition event. Retained in `storage/app/fras_events/scene/`.
2. **Face embeddings** — 512-dimensional floating-point vectors derived from enrolled personnel photos, stored on cameras as part of the enrollment payload. Not reversible to the original photograph.
3. **Recognition events** — structured rows in `recognition_events` recording which personnel matched which camera at which time, with similarity score and severity.
4. **Access logs** — append-only rows in `fras_access_log` recording every operator view or download of a face/scene image.

[CDRRMO_SPECIFIC_FILLIN — confirm that no voice, fingerprint, or iris data is processed; confirm that face embeddings are stored on-camera only and not copied to long-term LGU storage]

## 3. Lawful Basis

Processing is grounded in:

- **RA 10173 § 12(e)** — necessary for the performance of a task carried out in the exercise of functions and authority vested upon the LGU by law (disaster risk reduction and emergency response under RA 10121).
- **RA 10173 § 13(b) and § 13(f)** — processing of sensitive personal information for public order and safety, and for the protection of life and property.
- **RA 10121 §§ 5, 12, 15** — CDRRMO mandate for incident response, early-warning, and hazard monitoring.

Legitimate interest has been balanced against data-subject rights per the mitigations enumerated in § 7 of this document.

[CDRRMO_SPECIFIC_FILLIN — reference the specific City Council resolution or ordinance authorizing CCTV deployment and FRAS, if any]

## 4. Retention

Retention windows are enforced programmatically by `php artisan fras:purge-expired` (daily 02:00 Asia/Manila):

| Data Class | Retention | Storage Location |
|------------|-----------|------------------|
| Scene images | 30 days after captured_at | `storage/app/fras_events/scene/` |
| Face image crops | 90 days after captured_at | `storage/app/fras_events/face/` |
| Recognition-event row (metadata) | Indefinite (operational audit) | `recognition_events` table |
| `fras_access_log` rows | 2 years (730 days) | `fras_access_log` table |
| Enrolled personnel records | Until revocation or BOLO expiry | `personnel` table |

**Exception (DPA legal wall):** recognition events linked to an active, non-terminal Incident (status NOT IN Resolved/Cancelled) are exempted from the purge until the incident closes. This prevents evidence destruction during an open case.

[CDRRMO_SPECIFIC_FILLIN — confirm the LGU retention schedule matches or shortens these windows; never lengthen without a formal amendment]

## 5. Data Flows

```
CCTV Camera  --MQTT-->  RecognitionHandler  -->  recognition_events table
                                                       |
                                                       v
                                           ShouldBroadcast (fras.alerts)
                                                       |
                                                       v
                                        Operator console / fras/alerts
                                                       |
                                          acknowledge or dismiss or promote
                                                       |
                                                       v
                                           Incident (if promoted)
                                                       |
                                                       v
                                     Responder assignment / scene-tab
                                                       |
                                                       v
                                  signed-URL face image (5-min expiry)
                                                       |
                                                       v
                                  fras_access_log (append-only audit)
```

[CDRRMO_SPECIFIC_FILLIN — attach network topology diagram showing which subnets cameras, MQTT broker, IRMS app, and operator workstations sit on]

## 6. Risks

Identified privacy and security risks prior to mitigations:

1. Unauthorized operator access to face or scene images.
2. Retention-window drift (images retained past 30d/90d in error).
3. Operator error: incorrect dismiss-reason or mis-attribution during promote-to-incident.
4. Cross-site scripting via Markdown render in the Privacy Notice page.
5. SQL injection via event-history search input.
6. Evidence destruction by the retention purge during an open case.
7. Public exposure of DPA export PDFs via webroot.
8. Unauthorized face-embedding exfiltration from an enrolled camera.

## 7. Mitigations

Each risk in § 6 is mitigated by one or more Phase 22 security controls:

| Risk | Mitigation |
|------|------------|
| Unauthorized access | Role-gated routes (operator/supervisor/admin) + 3-layer defense (route middleware + can gate + FormRequest authorize) + append-only `fras_access_log` audit |
| Retention drift | Daily `fras:purge-expired` + `fras_purge_runs` summary row (reviewed weekly) |
| Operator error | Enum-whitelist dismiss reason (4 values) + min:8 promote reason + 7-field audit trail on timeline |
| XSS in Markdown | `GithubFlavoredMarkdownConverter` configured with `html_input: 'strip'`; raw HTML is stripped at compile |
| SQL injection | Parameterized ILIKE search; no raw SQL concatenation in controllers |
| Evidence destruction | `whereNull(incident_id) OR terminal-status` protection query in the purge command |
| Webroot exposure | `storage/app/dpa-exports/` is NOT symlinked to `public/` (PDFs are server-local only) |
| Embedding exfiltration | Cameras isolate embeddings on-device; IRMS stores only the `camera_enrollments` metadata, not the vector |

## 8. DSR Handling (Data Subject Rights)

Under RA 10173 §§ 16–19, data subjects may exercise the following rights against CDRRMO:

- **Right to be informed** — fulfilled by public-facing `/privacy` page (English + Filipino) and on-premise CCTV signage (see `signage-template.md`).
- **Right to access** — data subject submits a written request to the DPO; CDRRMO responds within 15 working days with a summary of processing.
- **Right to rectification** — as above; corrections logged in a DSR register maintained by the DPO.
- **Right to object / erase** — evaluated case-by-case; erasure of enrolled personnel is done via `personnel.decommissioned_at` and `camera_enrollments.status = Deleted`. Erasure of a recognition-event linked to an open case is deferred until case closure (§ 4 legal wall).
- **Right to data portability** — fulfilled by exporting the subject's recognition-event rows as CSV on DPO request.
- **Right to lodge a complaint** — subjects may file directly with the National Privacy Commission at privacy.gov.ph.

[CDRRMO_SPECIFIC_FILLIN — name the DPO, their contact, and the DSR intake email/phone]

## 9. Incident Response

Per RA 10173 § Sec 38 and NPC Circular 16-03:

- Personal data breaches affecting ≥ 100 data subjects (or sensitive personal information) must be notified to the NPC within **72 hours** of discovery.
- Internal IR procedure:
  1. CDRRMO IT lead confirms the breach and its scope.
  2. DPO drafts the NPC notification using the template at [CDRRMO_SPECIFIC_FILLIN — link to internal breach notification template].
  3. Affected data subjects are notified in parallel.
  4. Post-incident review produces a written corrective action plan.
- IRMS-specific detection: anomalous `fras_access_log` patterns (e.g., a single user viewing > 50 scene images in an hour) should raise an alert to the DPO.

## 10. DPO Sign-off

By signing below, the CDRRMO Data Protection Officer attests that this Privacy Impact Assessment accurately describes the FRAS subsystem's processing activities, that the risks in § 6 have been adequately mitigated by the controls in § 7, and that the retention schedule in § 4 complies with RA 10173 and applicable NPC circulars.

```
Signed by (name):    ________________________________

Position:            Data Protection Officer

Date:                ____________________

Signature:           ________________________________

Contact:             ____________________
```

[CDRRMO_SPECIFIC_FILLIN — attach annex: list of enrolled camera IDs and locations, list of personnel categories (allow/watch/deny), incident-response contact tree]
