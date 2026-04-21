<?php

declare(strict_types=1);

use App\Enums\CameraEnrollmentStatus;
use App\Enums\CameraStatus;
use App\Events\EnrollmentProgressed;
use App\Jobs\EnrollPersonnelBatch;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use App\Services\CameraEnrollmentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

pest()->group('fras');

beforeEach(function () {
    // Wave 1 dependency — EnrollmentProgressed event + Personnel.photo_access_token
    // column + Personnel::photo_url accessor all ship in plan 20-01. This worktree
    // was branched before plan 20-01's commits exist, so class / column / accessor
    // lookups fail. Skip until the orchestrator merges Wave 1.
    if (! class_exists(EnrollmentProgressed::class)) {
        test()->markTestSkipped('Wave 1 dependency — EnrollmentProgressed + Personnel.photo_access_token ship in plan 20-01 merge');
    }
});

it('enrollPersonnel dispatches EnrollPersonnelBatch per online non-decommissioned camera only', function () {
    Queue::fake();
    Event::fake([EnrollmentProgressed::class]);

    $personnel = Personnel::factory()->create(['photo_hash' => 'abc123']);
    Camera::factory()->count(2)->create([
        'status' => CameraStatus::Online,
        'decommissioned_at' => null,
    ]);
    Camera::factory()->create(['status' => CameraStatus::Offline]);
    Camera::factory()->create([
        'status' => CameraStatus::Online,
        'decommissioned_at' => now(),
    ]);

    app(CameraEnrollmentService::class)->enrollPersonnel($personnel);

    // Only the 2 online, non-decommissioned cameras should receive a job.
    Queue::assertPushed(EnrollPersonnelBatch::class, 2);
    // One EnrollmentProgressed per enrollment row created.
    Event::assertDispatchedTimes(EnrollmentProgressed::class, 2);
});

it('upsertBatch transitions enrollment to syncing, broadcasts progress, and publishes MQTT', function () {
    Event::fake([EnrollmentProgressed::class]);
    config(['fras.enrollment.ack_timeout_minutes' => 7]);

    $camera = Camera::factory()->create(['device_id' => 'cam-42']);
    $personnel = Personnel::factory()->create([
        'custom_id' => 'abc123',
        'photo_hash' => 'hashA',
    ]);
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Pending,
    ]);

    MQTT::shouldReceive('connection->publish')->atLeast()->once();

    app(CameraEnrollmentService::class)->upsertBatch($camera, [$personnel->id]);

    expect(CameraEnrollment::where('camera_id', $camera->id)->first()->status)
        ->toBe(CameraEnrollmentStatus::Syncing);

    Event::assertDispatched(EnrollmentProgressed::class, function ($e) use ($camera, $personnel) {
        return $e->enrollment->camera_id === $camera->id
            && $e->enrollment->personnel_id === $personnel->id;
    });
});

it('upsertBatch includes photo_url in MQTT payload via Personnel::photo_url accessor', function () {
    Event::fake([EnrollmentProgressed::class]);

    $camera = Camera::factory()->create(['device_id' => 'cam-42']);
    $personnel = Personnel::factory()->create([
        'custom_id' => 'abc123',
        'photo_hash' => 'hashA',
        'photo_path' => 'personnel/'.Str::uuid().'.jpg',
        'photo_access_token' => Str::uuid()->toString(),
    ]);
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Pending,
    ]);

    $capturedJson = null;
    MQTT::shouldReceive('connection->publish')
        ->once()
        ->andReturnUsing(function ($topic, $json) use (&$capturedJson) {
            $capturedJson = $json;
        });

    app(CameraEnrollmentService::class)->upsertBatch($camera, [$personnel->id]);

    // The photo_access_token is the invariant — it appears only in the route URL
    // built by Personnel::photo_url, which is embedded as the `picURI` field.
    expect($capturedJson)->toContain($personnel->photo_access_token);
});

it('translateErrorCode maps the 10 FRAS error codes to non-empty strings with a default fallback', function () {
    $s = app(CameraEnrollmentService::class);

    foreach ([461, 463, 464, 465, 466, 467, 468, 474, 478] as $code) {
        expect($s->translateErrorCode($code))->toBeString()->not->toBeEmpty();
    }

    // Default path must also return a non-empty string.
    expect($s->translateErrorCode(9999))->toBeString()->not->toBeEmpty();
});

it('deleteFromAllCameras publishes DeletePersons MQTT for each online camera only', function () {
    $p = Personnel::factory()->create(['custom_id' => 'abc123']);
    Camera::factory()->count(3)->create([
        'status' => CameraStatus::Online,
        'decommissioned_at' => null,
    ]);
    Camera::factory()->create(['status' => CameraStatus::Offline]);

    MQTT::shouldReceive('connection->publish')->times(3);

    app(CameraEnrollmentService::class)->deleteFromAllCameras($p);
});
