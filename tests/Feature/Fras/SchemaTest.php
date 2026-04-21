<?php

use Illuminate\Support\Facades\DB;

pest()->group('fras');

it('cameras table has UUID PK and geography location column', function () {
    $cols = collect(DB::select("
        SELECT column_name, data_type, udt_name
        FROM information_schema.columns
        WHERE table_name = 'cameras'
    "))->keyBy('column_name');

    expect($cols->has('id'))->toBeTrue();
    expect($cols['id']->data_type)->toBe('uuid');

    expect($cols->has('location'))->toBeTrue();
    expect($cols['location']->udt_name)->toBe('geography');

    expect($cols->has('device_id'))->toBeTrue();
    expect($cols->has('camera_id_display'))->toBeTrue();
    expect($cols->has('decommissioned_at'))->toBeTrue();
});

it('cameras.location has a spatial GIST index', function () {
    $idxs = DB::select("
        SELECT indexname, indexdef FROM pg_indexes
        WHERE tablename = 'cameras' AND indexdef ILIKE '%gist%location%'
    ");
    expect($idxs)->not->toBeEmpty();
});

it('personnel table has all FRAS fields reserved', function () {
    $cols = collect(DB::select("
        SELECT column_name FROM information_schema.columns
        WHERE table_name = 'personnel'
    "))->pluck('column_name')->all();

    expect($cols)->toContain('id', 'custom_id', 'name', 'gender', 'birthday',
        'id_card', 'phone', 'address', 'photo_path', 'photo_hash',
        'category', 'expires_at', 'consent_basis', 'decommissioned_at');
});

it('recognition_events has jsonb columns and microsecond timestamps', function () {
    $cols = collect(DB::select("
        SELECT column_name, udt_name, datetime_precision
        FROM information_schema.columns
        WHERE table_name = 'recognition_events'
    "))->keyBy('column_name');

    expect($cols['raw_payload']->udt_name)->toBe('jsonb');
    expect($cols['target_bbox']->udt_name)->toBe('jsonb');
    expect((int) $cols['captured_at']->datetime_precision)->toBe(6);
    expect((int) $cols['received_at']->datetime_precision)->toBe(6);
});

it('recognition_events has GIN index on raw_payload with jsonb_path_ops', function () {
    $idxs = DB::select("
        SELECT indexname, indexdef FROM pg_indexes
        WHERE tablename = 'recognition_events'
          AND indexdef ILIKE '%gin%raw_payload%'
    ");
    expect($idxs)->not->toBeEmpty();
    expect(strtolower($idxs[0]->indexdef))->toContain('jsonb_path_ops');
});

it('recognition_events has UNIQUE (camera_id, record_id) — FRAMEWORK-06', function () {
    $constraints = DB::select("
        SELECT conname, pg_get_constraintdef(oid) AS def
        FROM pg_constraint
        WHERE conrelid = 'recognition_events'::regclass
          AND contype = 'u'
    ");

    $match = collect($constraints)->first(
        fn ($c) => str_contains($c->def, 'camera_id')
            && str_contains($c->def, 'record_id')
    );

    expect($match)->not->toBeNull();
});

it('camera_enrollments has UNIQUE (camera_id, personnel_id) + per-status indexes', function () {
    $constraints = DB::select("
        SELECT pg_get_constraintdef(oid) AS def
        FROM pg_constraint
        WHERE conrelid = 'camera_enrollments'::regclass
          AND contype = 'u'
    ");

    $hasUnique = collect($constraints)->contains(
        fn ($c) => str_contains($c->def, 'camera_id')
            && str_contains($c->def, 'personnel_id')
    );
    expect($hasUnique)->toBeTrue();

    $idxs = collect(DB::select("
        SELECT indexdef FROM pg_indexes
        WHERE tablename = 'camera_enrollments'
    "))->pluck('indexdef')->implode("\n");

    expect($idxs)->toContain('camera_id, status');
    expect($idxs)->toContain('personnel_id, status');
});
