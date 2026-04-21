<?php

use App\Models\Camera;
use App\Models\RecognitionEvent;
use Illuminate\Database\UniqueConstraintViolationException;

pest()->group('fras');

it('rejects duplicate (camera_id, record_id) via DB UNIQUE constraint', function () {
    $camera = Camera::factory()->create();

    // First insert succeeds
    RecognitionEvent::factory()
        ->for($camera)
        ->create(['record_id' => 123456]);

    // Duplicate (camera_id, record_id) must throw at DB layer
    expect(fn () => RecognitionEvent::factory()
        ->for($camera)
        ->create(['record_id' => 123456])
    )->toThrow(UniqueConstraintViolationException::class);
});

it('allows same record_id across different cameras', function () {
    $cameraA = Camera::factory()->create();
    $cameraB = Camera::factory()->create();

    RecognitionEvent::factory()->for($cameraA)->create(['record_id' => 999]);
    RecognitionEvent::factory()->for($cameraB)->create(['record_id' => 999]);

    expect(RecognitionEvent::where('record_id', 999)->count())->toBe(2);
});

it('allows different record_ids on the same camera', function () {
    $camera = Camera::factory()->create();

    RecognitionEvent::factory()->for($camera)->create(['record_id' => 100]);
    RecognitionEvent::factory()->for($camera)->create(['record_id' => 101]);

    expect(RecognitionEvent::where('camera_id', $camera->id)->count())->toBe(2);
});
