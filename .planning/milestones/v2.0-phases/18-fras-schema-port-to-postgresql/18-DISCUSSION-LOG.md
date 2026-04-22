# Phase 18: FRAS Schema Port to PostgreSQL - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-21
**Phase:** 18-fras-schema-port-to-postgresql
**Areas discussed:** Schema forward-compat scope, Cameras identifier triad, FK cascade + timestamp precision, Factory/seeder data scope

---

## Area Selection

**Gray areas presented:** Schema forward-compat scope, Cameras identifier triad, FK cascade + timestamp precision, Factory/seeder data scope
**User selected:** All four

---

## Schema forward-compat scope

### Q1: Personnel columns to port

| Option | Description | Selected |
|--------|-------------|----------|
| Full FRAS + Phase 20 columns | Port all FRAS personnel fields AS-IS; REPLACE person_type(tinyint) with category(string enum); ADD expires_at + consent_basis | ✓ |
| Minimal port + Phase 20 columns only | Keep name + photo_path + photo_hash only. Add category + expires_at + consent_basis. Drop FRAS demographic fields. | |
| Literal FRAS port | Port FRAS verbatim. Phase 20 migrates later. | |

**User's choice:** Full FRAS + Phase 20 columns (Recommended)
**Notes:** Preserves firmware-reported fields (id_card/phone/address/gender/birthday) so Phase 19 MQTT handler can snapshot them into recognition_events without losing fidelity; replaces 0/1 tinyint with expressive enum upfront.

### Q2: Cameras CAM-XX display ID

| Option | Description | Selected |
|--------|-------------|----------|
| Add CAM-XX column now | camera_id_display varchar unique nullable; Phase 20 adds sequencing | ✓ |
| Defer to Phase 20 | Phase 18 ships UUID + device_id only | |

**User's choice:** Add CAM-XX column now (Recommended)
**Notes:** Reserves the column so Phase 20 doesn't need a new migration; sequencing logic still belongs to Phase 20 where the admin form exists.

### Q3: Recognition events incident_id + ack columns

| Option | Description | Selected |
|--------|-------------|----------|
| All now | incident_id + severity + acknowledged_by/at + dismissed_at in Phase 18 | ✓ |
| incident_id only, defer ack | Phase 22 adds ack columns | |
| Defer all | Phase 21 + Phase 22 each add migrations | |

**User's choice:** All now (Recommended)
**Notes:** Honors "no schema churn" goal — Phase 21 and Phase 22 only write data, never migrate.

### Q4: camera_enrollments.status values

| Option | Description | Selected |
|--------|-------------|----------|
| All 4 values + DB CHECK | PHP enum + DB-level CHECK constraint | ✓ |
| All 4 values PHP-only | PHP enum, no DB constraint | |
| Pending only | FRAS literal; Phase 20 adds rest | |

**User's choice:** All 4 values + DB CHECK (Recommended)
**Notes:** First introduction of DB-level CHECK constraints in IRMS schema. Belt-and-suspenders integrity against Phase 19/20 jobs writing garbage.

---

## Cameras identifier triad

### Q1: device_id format and constraints

| Option | Description | Selected |
|--------|-------------|----------|
| Unique + length cap + immutable | varchar(64) unique NOT NULL, app-level immutable | ✓ |
| Unique + nullable | Pre-provision cameras before device binding | |
| Unique + length cap (mutable) | Admin can swap device_id if firmware replaced | |

**User's choice:** Unique + length cap + immutable (Recommended)
**Notes:** Phase 20 form exposes create-only; no update route for device_id. Matches FRAS firmware reality (device_id is the MQTT client auth credential).

### Q2: camera_id_display column type

| Option | Description | Selected |
|--------|-------------|----------|
| varchar(10) unique nullable | Matches CAM-9999 width cap | ✓ |
| varchar(20) unique nullable | Matches Incident.incident_no width; room for barangay prefix | |
| varchar(10) unique not null | Placeholder CAM-S001 seeded | |

**User's choice:** varchar(10) unique nullable (Recommended)
**Notes:** Nullable matches "Phase 20 owns the sequencing"; unique prevents dup accidents.

### Q3: name and location_label required-ness

| Option | Description | Selected |
|--------|-------------|----------|
| Both required | name(100) NOT NULL + location_label(150) NOT NULL | ✓ |
| name required, location_label nullable | Coords-only fallback for map marker | |
| Both nullable | FRAS literal | |

**User's choice:** Both required (Recommended)
**Notes:** Prevents unlabeled cameras polluting the dispatch map.

### Q4: cameras.is_online vs status enum

| Option | Description | Selected |
|--------|-------------|----------|
| status enum + last_seen_at | online/offline/degraded + CHECK constraint | ✓ |
| is_online bool + last_seen_at | FRAS literal | |
| Both (redundant) | Rejected by minimal-surface | |

**User's choice:** status enum + last_seen_at (Recommended)
**Notes:** Direct match to Phase 20 SC2 three-state broadcast. Eliminates future is_online → status migration.

---

## FK cascade + timestamp precision

### Q1: Camera FK cascade policy

| Option | Description | Selected |
|--------|-------------|----------|
| Restrict + decommissioned_at | recognition_events.camera_id RESTRICT, camera_enrollments.camera_id CASCADE, + cameras.decommissioned_at | ✓ |
| Cascade everywhere + decommissioned_at | All FKs CASCADE, soft-delete app-only | |
| Restrict everywhere, no decommissioned_at | Never delete — always decommission manually | |

**User's choice:** Restrict + decommissioned_at (Recommended)
**Notes:** Preserves recognition history (can't accidentally lose it via admin delete); enrollments safely cascade as they're sync state.

### Q2: Personnel FK cascade policy

| Option | Description | Selected |
|--------|-------------|----------|
| nullOnDelete + decommissioned_at | recognition_events.personnel_id nullOnDelete, camera_enrollments.personnel_id CASCADE, + personnel.decommissioned_at | ✓ |
| nullOnDelete everywhere, no decommissioned_at | Hard-delete only | |
| Restrict everywhere + decommissioned_at | Never hard-delete personnel | |

**User's choice:** nullOnDelete + decommissioned_at (Recommended)
**Notes:** Phase 22 DPA right-to-erasure may need hard delete — nullOnDelete preserves the recognition history record (camera_id + captured_at retained) even when the person is purged. Decommissioned_at column exists for admin soft-deactivate UX.

### Q3: Timestamp precision for MQTT cols

| Option | Description | Selected |
|--------|-------------|----------|
| Precision 6 on MQTT cols, default elsewhere | captured_at + received_at TIMESTAMPTZ(6); all else default | ✓ |
| Precision 0 everywhere | v1.0 convention; rely on record_id for dedup | |
| Precision 6 everywhere | Uniform but deviates from v1.0 | |

**User's choice:** Precision 6 on MQTT cols, default elsewhere (Recommended)
**Notes:** Matches research recommendation. Sub-second MQTT ordering matters when a single camera emits multiple recognitions in the same second; record_id alone doesn't help ordering.

### Q4: Camera clock vs server clock

| Option | Description | Selected |
|--------|-------------|----------|
| captured_at + received_at, no skew column | Skew derivable | ✓ |
| captured_at + received_at + camera_clock_skew_ms | Research's full recommendation | |
| captured_at only (FRAS literal) | Single column | |

**User's choice:** captured_at + received_at, no skew column (Recommended)
**Notes:** Skew is `received_at - captured_at` — no reason to persist a derivable value. Preserves the camera-vs-server-clock distinction that FRAS lacked.

---

## Factory/seeder data scope

### Q1: What does migrate:fresh --seed produce

| Option | Description | Selected |
|--------|-------------|----------|
| Factories only, empty seeders | FrasPlaceholderSeeder exists but not wired into DatabaseSeeder | ✓ |
| Factories + demo seeder | 8 cameras + 20 block-list personnel auto-seeded in local | |
| Factories + FrasDemoSeeder opt-in | Manual db:seed --class=FrasDemoSeeder | |

**User's choice:** Factories only, empty seeders (Recommended)
**Notes:** Matches v1.0 pattern (UnitFactory exists; no UnitSeeder wired). Prevents CDRRMO prod from accidentally loading demo personnel.

### Q2: RecognitionEventFactory baseline shape

| Option | Description | Selected |
|--------|-------------|----------|
| Real RecPush shape with sensible defaults | raw_payload contains both firmware spellings; realistic target_bbox | ✓ |
| Minimal scalar-only factory | raw_payload = ['test' => true] | |
| Factory with state() variants only | Minimal baseline + state() methods | |

**User's choice:** Real RecPush shape with sensible defaults (Recommended)
**Notes:** Phase 19 MQTT handler tests get a production-shape payload out of the box. Adds state() variants as a natural extension (not exclusive with baseline).

### Q3: Test location for mandated feature tests

| Option | Description | Selected |
|--------|-------------|----------|
| tests/Feature/Fras/ group with 'fras' Pest group | CameraSpatialQueryTest + RecognitionEventIdempotencyTest | ✓ |
| tests/Feature/Cameras/ + tests/Feature/Personnel/ | Split by table domain | |
| tests/Feature/Schema/Fras/ single dir | Schema emphasis | |

**User's choice:** tests/Feature/Fras/ group with 'fras' Pest group (Recommended)
**Notes:** Enables `./vendor/bin/pest --group=fras` for FRAMEWORK-05 CI gate. Phase 20 tests may later move to tests/Feature/Cameras/ but keep the fras group.

### Q4: UUID PK pattern

| Option | Description | Selected |
|--------|-------------|----------|
| HasUuids trait | Matches Incident model | ✓ |
| Manual UUID generation in factories | No trait | |

**User's choice:** HasUuids trait (Recommended)
**Notes:** Unambiguous v1.0 precedent.

---

## Claude's Discretion

Items where the user did not explicitly choose; planner decides:
- Exact migration filenames (timestamp prefix): follow 2026_03_12 v1.0 numbering style
- Enum class location (`app/Enums/` assumed from IncidentOutcome precedent)
- Whether `camera_id_display` is `char(10)` vs `varchar(10)`
- GIN index DDL: Blueprint helper vs raw `jsonb_path_ops` statement
- `personnel.custom_id` width: varchar(48) (FRAS) vs varchar(36) (UUID-width)
- Factory Butuan City coordinate jitter exact bounding box

## Deferred Ideas

Captured in 18-CONTEXT.md `<deferred>` section:
- camera_id_display auto-sequencing logic → Phase 20
- MapLibre camera picker → Phase 20
- Heartbeat watchdog → Phase 20
- EnrollPersonnelBatch jobs → Phase 20
- FRAS photo disks → Phase 19
- DPA audit log, signed URLs, retention → Phase 22
- Critical-recognition notifications → Phase 22
- GIN index on raw_payload->>'cameraDeviceId' path → optimize if hot
- citext extension for ILIKE → Phase 20 if UX friction surfaces
- Photo thumbnail generation → Phase 20
- Retention-purge soft-delete column → Phase 22
