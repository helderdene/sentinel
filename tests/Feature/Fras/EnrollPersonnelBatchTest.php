<?php

use App\Enums\CameraEnrollmentStatus;
use App\Enums\CameraStatus;
use App\Events\EnrollmentProgressed;
use App\Jobs\EnrollPersonnelBatch;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use App\Services\CameraEnrollmentService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use PhpMqtt\Client\Facades\MQTT;

pest()->group('fras');

it('has $tries = 3, queue = fras, and a WithoutOverlapping middleware keyed by camera-id', function () {
    $camera = Camera::factory()->create();
    $job = new EnrollPersonnelBatch($camera, ['uuid-1', 'uuid-2']);

    expect($job->tries)->toBe(3);
    expect($job->queue)->toBe('fras');

    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);

    $r = new ReflectionObject($middleware[0]);
    $keyProp = $r->getProperty('key');
    $keyProp->setAccessible(true);
    expect($keyProp->getValue($middleware[0]))->toBe('enrollment-camera-'.$camera->id);
});

it('handle() delegates to CameraEnrollmentService::upsertBatch with camera + ids', function () {
    // CameraEnrollmentService is `final`, which blocks Mockery + anonymous
    // subclass doubling. Verify delegation by observing the downstream side
    // effect — upsertBatch transitions rows Pending -> Syncing and publishes
    // to MQTT. Fake the broadcast event so we do not contact Pusher.
    Event::fake([EnrollmentProgressed::class]);

    $camera = Camera::factory()->create(['device_id' => 'cam-handle']);
    $p = Personnel::factory()->create(['photo_hash' => 'h', 'custom_id' => 'cid-1']);
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $p->id,
        'status' => CameraEnrollmentStatus::Pending,
    ]);

    MQTT::shouldReceive('connection->publish')
        ->once()
        ->withArgs(function (string $topic, string $payload) use ($camera) {
            return str_contains($topic, $camera->device_id)
                && str_contains($payload, 'EditPersonsNew');
        });

    $job = new EnrollPersonnelBatch($camera, [$p->id]);
    $job->handle(app(CameraEnrollmentService::class));

    // Row transitioned pending -> syncing as upsertBatch side-effect.
    expect(CameraEnrollment::where('personnel_id', $p->id)->first())
        ->status->toBe(CameraEnrollmentStatus::Syncing);
});

it('failed() marks each enrollment row status=failed with last_error and dispatches EnrollmentProgressed per row', function () {
    Event::fake([EnrollmentProgressed::class]);
    $camera = Camera::factory()->create(['status' => CameraStatus::Online]);
    $p1 = Personnel::factory()->create();
    $p2 = Personnel::factory()->create();
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $p1->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $p2->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);

    $job = new EnrollPersonnelBatch($camera, [$p1->id, $p2->id]);
    $job->failed(new RuntimeException('broker unreachable'));

    expect(CameraEnrollment::where('personnel_id', $p1->id)->first())
        ->status->toBe(CameraEnrollmentStatus::Failed)
        ->last_error->toBe('broker unreachable');
    expect(CameraEnrollment::where('personnel_id', $p2->id)->first())
        ->status->toBe(CameraEnrollmentStatus::Failed);

    Event::assertDispatched(EnrollmentProgressed::class, 2);
});
