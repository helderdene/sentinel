<?php

use App\Enums\RecognitionSeverity;
use App\Models\Camera;
use App\Models\RecognitionEvent;
use App\Mqtt\Handlers\RecognitionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

pest()->group('mqtt');

beforeEach(function () {
    Storage::fake('fras_events');
    $this->camera = Camera::factory()->create(['device_id' => 'CAM01']);
});

/**
 * Build a RecPush payload with sensible defaults for the IRMS RecognitionHandler.
 */
function recPushPayload(array $overrides = []): array
{
    return array_merge([
        'deviceId' => 'CAM01',
        'recordId' => 1001,
        'personName' => 'Juan Dela Cruz',
        'personType' => 0,
        'verifyStatus' => 1,
        'similarity' => 0.87,
        'capturedAt' => '2026-04-21T09:15:30+08:00',
        'faceImage' => base64_encode('tiny-face-bytes'),
        'sceneImage' => base64_encode('tiny-scene-bytes'),
    ], $overrides);
}

it('persists a recognition_events row for a RecPush with personName spelling', function () {
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['personName' => 'Alice Santos', 'recordId' => 1])),
    );

    expect(RecognitionEvent::count())->toBe(1);

    $event = RecognitionEvent::first();
    expect($event->camera_id)->toBe($this->camera->id);
    expect($event->name_from_camera)->toBe('Alice Santos');
    expect($event->raw_payload['personName'])->toBe('Alice Santos');
});

it('falls back to persionName firmware-typo key when personName is absent', function () {
    $payload = recPushPayload(['recordId' => 2]);
    unset($payload['personName']);
    $payload['persionName'] = 'Bob Typo';

    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode($payload),
    );

    expect(RecognitionEvent::count())->toBe(1);
    expect(RecognitionEvent::first()->name_from_camera)->toBe('Bob Typo');
});

it('is idempotent on duplicate (camera_id, record_id) RecPush', function () {
    $payload = json_encode(recPushPayload(['recordId' => 999]));

    app(RecognitionHandler::class)->handle('mqtt/face/CAM01/Rec', $payload);
    app(RecognitionHandler::class)->handle('mqtt/face/CAM01/Rec', $payload);

    expect(RecognitionEvent::where('record_id', 999)->count())->toBe(1);
});

it('drops RecPush for an unknown camera with a warning log and no DB/disk writes', function () {
    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('warning')
        ->once()
        ->with('RecPush for unknown camera', Mockery::on(fn ($ctx) => ($ctx['device_id'] ?? null) === 'CAM-UNKNOWN'));
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channel);

    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM-UNKNOWN/Rec',
        json_encode(recPushPayload(['deviceId' => 'CAM-UNKNOWN', 'recordId' => 77])),
    );

    expect(RecognitionEvent::count())->toBe(0);
    expect(Storage::disk('fras_events')->allFiles())->toBe([]);
});

it('persists face + scene images under date-partitioned paths on fras_events disk', function () {
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['recordId' => 5])),
    );

    $event = RecognitionEvent::first();
    $date = '2026-04-21';

    $expectedFace = "{$date}/faces/{$event->id}.jpg";
    $expectedScene = "{$date}/scenes/{$event->id}.jpg";

    Storage::disk('fras_events')->assertExists($expectedFace);
    Storage::disk('fras_events')->assertExists($expectedScene);
    expect($event->face_image_path)->toBe($expectedFace);
    expect($event->scene_image_path)->toBe($expectedScene);
});

it('keeps the recognition row but skips face image when face exceeds 1 MB cap', function () {
    $oversize = base64_encode(str_repeat('x', 1_100_000));

    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['recordId' => 6, 'faceImage' => $oversize])),
    );

    $event = RecognitionEvent::first();
    expect($event)->not->toBeNull();
    expect($event->face_image_path)->toBeNull();
    // Scene should still persist (2 MB cap, payload ~14 bytes)
    expect($event->scene_image_path)->not->toBeNull();
});

it('classifies severity via RecognitionSeverity::fromEvent on the stored row', function () {
    // personType=1 -> Critical per fromEvent()
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['recordId' => 7, 'personType' => 1, 'verifyStatus' => 0])),
    );

    expect(RecognitionEvent::first()->severity)->toBe(RecognitionSeverity::Critical);
});

it('never sets incident_id in Phase 19 (deferred to Phase 21)', function () {
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['recordId' => 8])),
    );

    expect(RecognitionEvent::first()->incident_id)->toBeNull();
});

it('parses the canonical recognition-person-name.json fixture end-to-end', function () {
    $json = file_get_contents(base_path('tests/fixtures/mqtt/recognition-person-name.json'));

    app(RecognitionHandler::class)->handle('mqtt/face/CAM01/Rec', $json);

    expect(RecognitionEvent::count())->toBe(1);
    $event = RecognitionEvent::first();
    expect($event->record_id)->toBe(42);
    expect($event->name_from_camera)->toBe('Juan Dela Cruz');
});

it('parses the recognition-persion-name.json firmware-typo fixture end-to-end', function () {
    $json = file_get_contents(base_path('tests/fixtures/mqtt/recognition-persion-name.json'));

    app(RecognitionHandler::class)->handle('mqtt/face/CAM01/Rec', $json);

    expect(RecognitionEvent::count())->toBe(1);
    $event = RecognitionEvent::first();
    expect($event->record_id)->toBe(43);
    expect($event->name_from_camera)->toBe('Juan Dela Cruz');
});
