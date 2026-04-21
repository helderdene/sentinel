<?php

use App\Enums\CameraEnrollmentStatus;
use App\Enums\CameraStatus;
use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use Illuminate\Support\Facades\DB;

pest()->group('fras');

/**
 * Asserts each PHP backed-enum's string values match the DB CHECK IN clause
 * on the corresponding table column, 1:1.
 *
 * Catches drift scenarios:
 *   - Phase 21 developer adds `RecognitionSeverity::Urgent` case but forgets
 *     to update the CHECK — PHP cast succeeds, DB rejects at insert time.
 *   - DB migration adds a value to the CHECK but no PHP enum case for it.
 */
function extractCheckValues(string $table, string $column): array
{
    $constraint = DB::selectOne('
        SELECT pg_get_constraintdef(oid) AS def
        FROM pg_constraint
        WHERE conrelid = ?::regclass
          AND contype = ?
          AND conname = ?
    ', [$table, 'c', "{$table}_{$column}_check"]);

    if (! $constraint) {
        return [];
    }

    // Extract values from CHECK ((status)::text = ANY (ARRAY['online'::text, ...]))
    // or CHECK (status IN ('online', 'offline', 'degraded'))
    preg_match_all("/'([^']+)'/", $constraint->def, $matches);

    return $matches[1] ?? [];
}

it('CameraStatus enum matches cameras.status CHECK clause', function () {
    $enumValues = array_map(fn ($c) => $c->value, CameraStatus::cases());
    $checkValues = extractCheckValues('cameras', 'status');

    expect(collect($checkValues)->sort()->values()->all())
        ->toEqual(collect($enumValues)->sort()->values()->all());
});

it('PersonnelCategory enum matches personnel.category CHECK clause', function () {
    $enumValues = array_map(fn ($c) => $c->value, PersonnelCategory::cases());
    $checkValues = extractCheckValues('personnel', 'category');

    expect(collect($checkValues)->sort()->values()->all())
        ->toEqual(collect($enumValues)->sort()->values()->all());
});

it('CameraEnrollmentStatus enum matches camera_enrollments.status CHECK clause', function () {
    $enumValues = array_map(fn ($c) => $c->value, CameraEnrollmentStatus::cases());
    $checkValues = extractCheckValues('camera_enrollments', 'status');

    expect(collect($checkValues)->sort()->values()->all())
        ->toEqual(collect($enumValues)->sort()->values()->all());
});

it('RecognitionSeverity enum matches recognition_events.severity CHECK clause', function () {
    $enumValues = array_map(fn ($c) => $c->value, RecognitionSeverity::cases());
    $checkValues = extractCheckValues('recognition_events', 'severity');

    expect(collect($checkValues)->sort()->values()->all())
        ->toEqual(collect($enumValues)->sort()->values()->all());
});
