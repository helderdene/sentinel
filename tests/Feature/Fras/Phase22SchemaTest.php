<?php

use Illuminate\Support\Facades\DB;

pest()->group('fras', 'phase22');

it('recognition_events has dismissed_by / dismiss_reason / dismiss_reason_note columns from Phase 22', function () {
    $cols = collect(DB::select("
        SELECT column_name, data_type, udt_name, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'recognition_events'
    "))->keyBy('column_name');

    expect($cols->has('dismissed_by'))->toBeTrue();
    expect($cols['dismissed_by']->data_type)->toBe('bigint');
    expect($cols['dismissed_by']->is_nullable)->toBe('YES');

    expect($cols->has('dismiss_reason'))->toBeTrue();
    expect($cols['dismiss_reason']->udt_name)->toBe('varchar');
    expect($cols['dismiss_reason']->is_nullable)->toBe('YES');

    expect($cols->has('dismiss_reason_note'))->toBeTrue();
    expect($cols['dismiss_reason_note']->data_type)->toBe('text');
});

it('recognition_events has dismiss_reason CHECK constraint with 4 enum values', function () {
    $constraint = DB::selectOne("
        SELECT pg_get_constraintdef(oid) AS def
        FROM pg_constraint
        WHERE conrelid = 'recognition_events'::regclass
          AND conname = 'recognition_events_dismiss_reason_check'
    ");

    expect($constraint)->not->toBeNull();
    expect($constraint->def)->toContain('false_match');
    expect($constraint->def)->toContain('test_event');
    expect($constraint->def)->toContain('duplicate');
    expect($constraint->def)->toContain('other');
});

it('recognition_events has indexes on acknowledged_at and dismissed_at', function () {
    $idxs = collect(DB::select("
        SELECT indexdef FROM pg_indexes
        WHERE tablename = 'recognition_events'
    "))->pluck('indexdef')->implode("\n");

    expect($idxs)->toMatch('/acknowledged_at/i');
    expect($idxs)->toMatch('/dismissed_at/i');
});

it('fras_access_log table exists with required columns', function () {
    $cols = collect(DB::select("
        SELECT column_name, data_type, udt_name
        FROM information_schema.columns
        WHERE table_name = 'fras_access_log'
    "))->keyBy('column_name');

    expect($cols->has('id'))->toBeTrue();
    expect($cols['id']->data_type)->toBe('uuid');

    expect($cols->has('actor_user_id'))->toBeTrue();
    expect($cols['actor_user_id']->data_type)->toBe('bigint');

    expect($cols->has('ip_address'))->toBeTrue();
    expect($cols->has('user_agent'))->toBeTrue();
    expect($cols->has('subject_type'))->toBeTrue();

    expect($cols->has('subject_id'))->toBeTrue();
    expect($cols['subject_id']->data_type)->toBe('uuid');

    expect($cols->has('action'))->toBeTrue();
    expect($cols->has('accessed_at'))->toBeTrue();
    expect($cols->has('created_at'))->toBeTrue();
    expect($cols->has('updated_at'))->toBeTrue();
});

it('fras_access_log has subject_type CHECK constraint with 3 enum values', function () {
    $constraint = DB::selectOne("
        SELECT pg_get_constraintdef(oid) AS def
        FROM pg_constraint
        WHERE conrelid = 'fras_access_log'::regclass
          AND conname = 'fras_access_log_subject_type_check'
    ");

    expect($constraint)->not->toBeNull();
    expect($constraint->def)->toContain('recognition_event_face');
    expect($constraint->def)->toContain('recognition_event_scene');
    expect($constraint->def)->toContain('personnel_photo');
});

it('fras_access_log has action CHECK constraint with 2 enum values', function () {
    $constraint = DB::selectOne("
        SELECT pg_get_constraintdef(oid) AS def
        FROM pg_constraint
        WHERE conrelid = 'fras_access_log'::regclass
          AND conname = 'fras_access_log_action_check'
    ");

    expect($constraint)->not->toBeNull();
    expect($constraint->def)->toContain('view');
    expect($constraint->def)->toContain('download');
});

it('fras_purge_runs table exists with expected columns', function () {
    $cols = collect(DB::select("
        SELECT column_name, data_type, udt_name
        FROM information_schema.columns
        WHERE table_name = 'fras_purge_runs'
    "))->keyBy('column_name');

    expect($cols->has('id'))->toBeTrue();
    expect($cols['id']->data_type)->toBe('uuid');

    expect($cols->has('started_at'))->toBeTrue();
    expect($cols->has('finished_at'))->toBeTrue();
    expect($cols->has('dry_run'))->toBeTrue();
    expect($cols->has('face_crops_purged'))->toBeTrue();
    expect($cols->has('scene_images_purged'))->toBeTrue();
    expect($cols->has('skipped_for_active_incident'))->toBeTrue();
    expect($cols->has('access_log_rows_purged'))->toBeTrue();
    expect($cols->has('error_summary'))->toBeTrue();
    expect($cols->has('created_at'))->toBeTrue();
});

it('fras_legal_signoffs table exists with expected columns', function () {
    $cols = collect(DB::select("
        SELECT column_name, data_type, udt_name
        FROM information_schema.columns
        WHERE table_name = 'fras_legal_signoffs'
    "))->keyBy('column_name');

    expect($cols->has('id'))->toBeTrue();
    expect($cols['id']->data_type)->toBe('uuid');

    expect($cols->has('signed_by_name'))->toBeTrue();
    expect($cols->has('contact'))->toBeTrue();
    expect($cols->has('signed_at'))->toBeTrue();
    expect($cols->has('notes'))->toBeTrue();
    expect($cols->has('created_at'))->toBeTrue();
});

it('users.fras_audio_muted boolean column exists defaulting to false', function () {
    $col = DB::selectOne("
        SELECT column_name, data_type, column_default, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'users' AND column_name = 'fras_audio_muted'
    ");

    expect($col)->not->toBeNull();
    expect($col->data_type)->toBe('boolean');
    expect($col->is_nullable)->toBe('NO');
    expect($col->column_default)->toContain('false');
});
