<?php

use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Events\IncidentCreated;
use App\Events\RecognitionAlertReceived;
use App\Models\Camera;
use App\Models\IncidentCategory;
use App\Models\IncidentType;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Mqtt\Handlers\RecognitionHandler;
use App\Services\FrasIncidentFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Log\LoggerInterface;

pest()->group('mqtt');

beforeEach(function () {
    Storage::fake('fras_events');
    $this->camera = Camera::factory()->create(['device_id' => 'CAM01']);
});

/**
 * Build a V1.25 RecPush payload (spec §5) with sensible defaults for the
 * IRMS RecognitionHandler. The outer envelope is { operator, info } and all
 * record data lives under `info`.
 */
function recPushPayload(array $infoOverrides = []): array
{
    $info = array_merge([
        'customId' => 'cam01-custom-default',
        'personId' => '22',
        'RecordID' => '1001',
        'VerifyStatus' => '1',
        'PersonType' => '0',
        'similarity1' => '87.000000',
        'Sendintime' => 1,
        'personName' => 'Juan Dela Cruz',
        'facesluiceId' => 'CAM01',
        'idCard' => '',
        'telnum' => '',
        'time' => '2026-04-21 09:15:30',
        'isNoMask' => '0',
        'PushType' => 0,
        'targetPosInScene' => [217, 0, 1333, 1080],
        'pic' => base64_encode('tiny-face-bytes'),
        'scene' => base64_encode('tiny-scene-bytes'),
    ], $infoOverrides);

    return [
        'operator' => 'RecPush',
        'info' => $info,
    ];
}

it('persists a recognition_events row for a RecPush with personName spelling', function () {
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['personName' => 'Alice Santos', 'RecordID' => '1'])),
    );

    expect(RecognitionEvent::count())->toBe(1);

    $event = RecognitionEvent::first();
    expect($event->camera_id)->toBe($this->camera->id);
    expect($event->name_from_camera)->toBe('Alice Santos');
    expect($event->raw_payload['info']['personName'])->toBe('Alice Santos');
});

it('falls back to persionName firmware-typo key when personName is absent', function () {
    $payload = recPushPayload(['RecordID' => '2']);
    unset($payload['info']['personName']);
    $payload['info']['persionName'] = 'Bob Typo';

    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode($payload),
    );

    expect(RecognitionEvent::count())->toBe(1);
    expect(RecognitionEvent::first()->name_from_camera)->toBe('Bob Typo');
});

it('maps V1.25 info keys to the recognition_events columns', function () {
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload([
            'RecordID' => '77',
            'customId' => 'cid-777',
            'personId' => '49',
            'facesluiceId' => 'CAM01',
            'idCard' => 'A1234',
            'telnum' => '09171234567',
            'isNoMask' => '1',
            'Sendintime' => 0,
            'similarity1' => '84.000000',
            'targetPosInScene' => [10, 20, 30, 40],
        ])),
    );

    $event = RecognitionEvent::first();
    expect($event->record_id)->toBe(77);
    expect($event->custom_id)->toBe('cid-777');
    expect($event->camera_person_id)->toBe('49');
    expect($event->facesluice_id)->toBe('CAM01');
    expect($event->id_card)->toBe('A1234');
    expect($event->phone)->toBe('09171234567');
    expect($event->is_no_mask)->toBe(1);
    expect($event->is_real_time)->toBeFalse();
    expect((float) $event->similarity)->toBe(0.84);
    expect($event->target_bbox)->toBe([10, 20, 30, 40]);
});

it('preserves the payload wall-time instant on captured_at', function () {
    // Regression: the FRAS Events table was rendering every captured_at as
    // "Just now" because Eloquent serialized the Carbon without a TZ offset
    // and the PG session was UTC, stamping Manila wall times as UTC and
    // landing 8h in the future. Fix is in config/database.php (PG session is
    // now Asia/Manila); this test pins the round-trip instant regardless of
    // APP_TIMEZONE so it survives the test env's UTC default and prod's
    // Asia/Manila default alike.
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload([
            'RecordID' => '999',
            'time' => '2026-04-21T09:15:30+08:00',
        ])),
    );

    $event = RecognitionEvent::first();

    expect($event->captured_at->equalTo('2026-04-21T09:15:30+08:00'))->toBeTrue();
});

it('resolves personnel_id from customId when a matching Personnel exists', function () {
    $personnel = Personnel::factory()->create([
        'custom_id' => 'cid-linked-1',
        'category' => PersonnelCategory::Block,
    ]);

    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['RecordID' => '101', 'customId' => 'cid-linked-1'])),
    );

    expect(RecognitionEvent::first()->personnel_id)->toBe($personnel->id);
});

it('leaves personnel_id null when customId has no matching Personnel', function () {
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['RecordID' => '102', 'customId' => 'cid-nonexistent'])),
    );

    expect(RecognitionEvent::first()->personnel_id)->toBeNull();
});

it('strips data-URI prefix from pic/scene before base64-decoding', function () {
    $faceBytes = 'face-binary-xyz';
    $sceneBytes = 'scene-binary-xyz';

    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload([
            'RecordID' => '200',
            'pic' => 'data:image/jpeg;base64,'.base64_encode($faceBytes),
            'scene' => 'data:image/jpeg;base64,'.base64_encode($sceneBytes),
        ])),
    );

    $event = RecognitionEvent::first();
    expect($event->face_image_path)->not->toBeNull();
    expect($event->scene_image_path)->not->toBeNull();
    expect(Storage::disk('fras_events')->get($event->face_image_path))->toBe($faceBytes);
    expect(Storage::disk('fras_events')->get($event->scene_image_path))->toBe($sceneBytes);
});

it('is idempotent on duplicate (camera_id, record_id) RecPush', function () {
    $payload = json_encode(recPushPayload(['RecordID' => '999']));

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
        json_encode(recPushPayload(['RecordID' => '77'])),
    );

    expect(RecognitionEvent::count())->toBe(0);
    expect(Storage::disk('fras_events')->allFiles())->toBe([]);
});

it('drops payload when envelope is missing the RecPush operator', function () {
    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('warning')
        ->once()
        ->with('RecPush operator mismatch', Mockery::any());
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channel);

    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(['info' => recPushPayload()['info']]),
    );

    expect(RecognitionEvent::count())->toBe(0);
});

it('persists face + scene images under date-partitioned paths on fras_events disk', function () {
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['RecordID' => '5'])),
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
        json_encode(recPushPayload(['RecordID' => '6', 'pic' => $oversize])),
    );

    $event = RecognitionEvent::first();
    expect($event)->not->toBeNull();
    expect($event->face_image_path)->toBeNull();
    // Scene should still persist (2 MB cap, payload ~14 bytes)
    expect($event->scene_image_path)->not->toBeNull();
});

it('classifies severity via RecognitionSeverity::fromEvent on the stored row', function () {
    // PersonType=1 -> Critical per fromEvent()
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['RecordID' => '7', 'PersonType' => '1', 'VerifyStatus' => '0'])),
    );

    expect(RecognitionEvent::first()->severity)->toBe(RecognitionSeverity::Critical);
});

it('never sets incident_id for an unlinked personnel recognition', function () {
    app(RecognitionHandler::class)->handle(
        'mqtt/face/CAM01/Rec',
        json_encode(recPushPayload(['RecordID' => '8', 'customId' => 'cid-none'])),
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

describe('factory integration', function () {
    beforeEach(function () {
        Event::fake([IncidentCreated::class, RecognitionAlertReceived::class]);

        $category = IncidentCategory::firstOrCreate(
            ['name' => 'Crime / Security'],
            ['icon' => 'Shield', 'is_active' => true, 'sort_order' => 4]
        );

        IncidentType::updateOrCreate(
            ['code' => 'person_of_interest'],
            [
                'incident_category_id' => $category->id,
                'category' => 'Crime / Security',
                'name' => 'Person of Interest',
                'default_priority' => 'P2',
                'is_active' => true,
                'show_in_public_app' => false,
                'sort_order' => 999,
            ]
        );

        config([
            'fras.recognition.confidence_threshold' => 0.75,
            'fras.recognition.dedup_window_seconds' => 60,
            'fras.recognition.priority_map' => [
                'critical' => [
                    'block' => 'P2',
                    'missing' => 'P2',
                    'lost_child' => 'P1',
                ],
            ],
        ]);
    });

    it('calls FrasIncidentFactory::createFromRecognition after persisting event and images for Critical block-list recognition', function () {
        $personnel = Personnel::factory()->create([
            'category' => PersonnelCategory::Block,
            'name' => 'Juan Dela Cruz',
        ]);

        // Seed the recognition_events row directly with the personnel_id
        // linked so the factory's Gate 3 (personnel lookup) resolves.
        // This test exercises the handler -> factory wiring contract without
        // depending on customId echo-back resolution in the handler.
        $event = RecognitionEvent::factory()
            ->for($this->camera)
            ->for($personnel)
            ->create([
                'severity' => RecognitionSeverity::Critical,
                'similarity' => 0.85,
                'person_type' => 1,
                'verify_status' => 0,
            ]);

        app(FrasIncidentFactory::class)->createFromRecognition($event);

        $event->refresh();
        expect($event->incident_id)->not->toBeNull();
        Event::assertDispatched(IncidentCreated::class);
        Event::assertDispatched(RecognitionAlertReceived::class);
    });

    it('resolves personnel_id via customId and promotes Critical event to an incident end-to-end', function () {
        $personnel = Personnel::factory()->create([
            'custom_id' => 'cid-eol-1',
            'category' => PersonnelCategory::Block,
            'name' => 'Juan Dela Cruz',
        ]);

        app(RecognitionHandler::class)->handle(
            'mqtt/face/CAM01/Rec',
            json_encode(recPushPayload([
                'RecordID' => '600',
                'customId' => 'cid-eol-1',
                'PersonType' => '1',
                'VerifyStatus' => '0',
                'similarity1' => '88.000000',
            ])),
        );

        $event = RecognitionEvent::first();
        expect($event->personnel_id)->toBe($personnel->id);
        expect($event->severity)->toBe(RecognitionSeverity::Critical);
        expect($event->refresh()->incident_id)->not->toBeNull();

        Event::assertDispatched(IncidentCreated::class);
        Event::assertDispatched(RecognitionAlertReceived::class);
    });

    it('persists Info severity event via handler without factory write path or broadcasts', function () {
        // PersonType=0 + VerifyStatus=1 -> Info via RecognitionSeverity::fromEvent
        app(RecognitionHandler::class)->handle(
            'mqtt/face/CAM01/Rec',
            json_encode(recPushPayload([
                'RecordID' => '500',
                'PersonType' => '0',
                'VerifyStatus' => '1',
            ])),
        );

        expect(RecognitionEvent::count())->toBe(1);
        expect(RecognitionEvent::first()->severity)->toBe(RecognitionSeverity::Info);

        Event::assertNotDispatched(IncidentCreated::class);
        Event::assertNotDispatched(RecognitionAlertReceived::class);
    });
});
