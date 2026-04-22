# Privacy Notice

The CDRRMO of Butuan City operates the Incident Response Management System
(IRMS), which includes a Face Recognition Alert System (FRAS). This notice
explains what personal and biometric data we collect through FRAS, why we
collect it, how long we retain it, how we protect it, and your rights under
Republic Act 10173 (the Data Privacy Act) and its implementing rules.

Last updated: 2026-04-22.

## What we collect

### Face images from CCTV cameras

FRAS cameras installed in designated public areas of Butuan City capture
images of faces that pass within the field of view. These face images are
processed to detect recognition matches against a controlled watchlist of
persons of interest (blocklist subjects, missing persons, and lost-child
alerts registered by authorised personnel).

### Recognition events

Every recognition match produces a recognition event record containing the
face image, the scene image (the wider frame the face was detected in),
the camera identifier and location, the timestamp of capture, the match
confidence score, and the category of the matched subject (block, missing,
or lost child).

### Access logs

Every time a CDRRMO operator, supervisor, or administrator views a face or
scene image stored by FRAS, the system records an access log entry with the
viewer's identity, their IP address, their user-agent, and the time of
access. These logs are append-only and tamper-evident.

## Why we collect it (lawful basis)

CDRRMO processes this data in the performance of a task carried out in the
public interest — specifically, emergency response and public safety
operations under the Philippine Disaster Risk Reduction and Management Act
(Republic Act 10121) and the city's mandate to detect and respond to
incidents affecting persons of interest. We rely on Section 12(e) and
Section 13(f) of RA 10173 as our lawful basis for processing personal and
sensitive personal information, respectively.

## How long we keep it (retention)

Face crops and scene images are retained only as long as necessary:

- **Scene images:** 30 days from capture.
- **Face crops:** 90 days from capture.
- **Exception:** if a face or scene image is tied to an active or resolved
  incident, it is retained until the incident is resolved or cancelled and
  for an additional period required for after-action review.
- **Access logs:** 2 years, as required for compliance audit.

After the retention period, images are deleted from primary storage by an
automated purge job that runs daily.

## Your rights as a data subject

Under RA 10173, you have the following rights with respect to your personal
data that we process:

1. **Right to be informed** — you are being informed by this notice.
2. **Right to access** — you may request a copy of the personal data we
   hold about you.
3. **Right to object** — you may object to the processing of your personal
   data, subject to the lawful basis on which we rely.
4. **Right to erasure or blocking** — you may request that we delete or
   block personal data that is no longer necessary, incomplete, outdated,
   or unlawfully obtained.
5. **Right to rectification** — you may request correction of inaccurate
   or incomplete personal data.
6. **Right to data portability** — where applicable, you may obtain and
   reuse your personal data in a structured, commonly-used electronic
   format.
7. **Right to damages** — you may be indemnified for damages sustained
   due to inaccurate, incomplete, outdated, false, unlawfully obtained, or
   unauthorised use of personal data.
8. **Right to file a complaint** — you may file a complaint with the
   National Privacy Commission (NPC).

To exercise any of these rights, contact our Data Protection Officer
using the details below.

## Who can access your data

Access to FRAS imagery and recognition data is role-restricted:

- **Operators** may view recognition events, scene images, and face images
  in the course of reviewing alerts and creating incidents.
- **Dispatchers** may view recognition events but NOT the underlying face
  or scene images.
- **Supervisors and Administrators** may view all of the above, plus audit
  logs of image-access events.
- **Responders** (field units) see personnel name, category, camera
  location, and a face thumbnail for operational context only — they do
  NOT see the wider scene image, and the face thumbnail is gated by the
  same role controls.

Recognition events linked to active incidents are additionally visible to
the unit assigned to respond, limited to the context described above.

## How we protect your data

- All FRAS image URLs are time-limited (5 minutes) and cryptographically
  signed — they cannot be shared, bookmarked, or reused.
- Every image-view request produces an append-only audit log entry written
  inside the same database transaction as the stream — a database failure
  aborts the stream, so we never serve an image without logging.
- Image storage uses a private disk; no public web path exposes FRAS
  imagery directly.
- Access is gated by role-based authorisation enforced at three layers
  (HTTP controller, broadcast channel, and page prop).
- Production data is encrypted at rest. Transport is encrypted with TLS.

## Contact our Data Protection Officer

For any questions, requests, or complaints relating to this notice or to
the personal data we hold about you, please contact:

- **Name:** [CDRRMO_DPO_NAME]
- **Email:** [CDRRMO_DPO_EMAIL]
- **Phone:** [CDRRMO_DPO_PHONE]
- **Office address:** [CDRRMO_DPO_OFFICE_ADDRESS]

## Filing a complaint with the NPC

If you believe your rights under RA 10173 have been violated, you may file
a complaint with the National Privacy Commission:

- **Website:** https://privacy.gov.ph
- **Email:** info@privacy.gov.ph
- **Address:** 5th Floor, Philippine International Convention Center
  (PICC), Pasay City, Metro Manila, Philippines
