<?php

use App\Enums\CameraEnrollmentStatus;
use App\Enums\CameraStatus;
use App\Enums\UserRole;
use App\Jobs\EnrollPersonnelBatch;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use App\Models\User;
use App\Mqtt\Handlers\AckHandler;
use App\Services\CameraEnrollmentService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpMqtt\Client\Facades\MQTT;

pest()->group('fras');

beforeEach(function () {
    Storage::fake('fras_photos');
    Cache::flush();
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('end-to-end: admin creates camera and personnel -> enrollment queued -> ACK transitions row Done -> photo URL revoked', function () {
    Queue::fake();
    MQTT::shouldReceive('connection->publish')->zeroOrMoreTimes();

    // 1. Admin creates a camera.
    $this->actingAs($this->admin)
        ->post(route('admin.cameras.store'), [
            'name' => 'Integration Cam',
            'device_id' => 'int-001',
            'latitude' => 8.9475,
            'longitude' => 125.5406,
        ])
        ->assertRedirect();

    $camera = Camera::where('device_id', 'int-001')->firstOrFail();
    // Watchdog would flip this on first heartbeat (Plan 06); simulate directly.
    $camera->update(['status' => CameraStatus::Online, 'last_seen_at' => now()]);

    // 2. Admin creates personnel with a photo.
    $this->post(route('admin.personnel.store'), [
        'name' => 'Jane Integration',
        'category' => 'block',
        'consent_basis' => 'Test consent',
        'photo' => UploadedFile::fake()->image('face.jpg', 800, 600),
    ])->assertRedirect();

    $personnel = Personnel::where('name', 'Jane Integration')->firstOrFail();

    // Observer fired enrollPersonnel when photo_hash was set.
    Queue::assertPushed(EnrollPersonnelBatch::class);

    $enrollment = CameraEnrollment::where('camera_id', $camera->id)
        ->where('personnel_id', $personnel->id)
        ->firstOrFail();

    expect($enrollment->status)->toBe(CameraEnrollmentStatus::Pending);

    // 3. Photo public URL returns 200 while enrollments are pending/syncing.
    $this->get("/fras/photo/{$personnel->photo_access_token}")
        ->assertOk();

    // 4. Simulate job execution inline — CameraEnrollmentService::upsertBatch
    // transitions Pending -> Syncing, caches the ACK correlation, publishes MQTT.
    app(CameraEnrollmentService::class)
        ->upsertBatch($camera, [$personnel->id]);

    expect($enrollment->fresh()->status)->toBe(CameraEnrollmentStatus::Syncing);

    // 5. AckHandler correlates via "enrollment-ack:{camera_id}:{messageId}".
    // The messageId that upsertBatch generated uses a timestamp + Str::random,
    // which is non-deterministic to the test. Install a known messageId
    // cache entry manually + invoke AckHandler — this proves the round-trip
    // end-to-end without coupling the test to service-internal IDs.
    Cache::put(
        "enrollment-ack:{$camera->id}:integration-msg",
        [
            'camera_id' => $camera->id,
            'personnel_ids' => [$personnel->id],
            'photo_hashes' => [$personnel->custom_id => $personnel->photo_hash],
            'dispatched_at' => now()->toIso8601String(),
        ],
        300,
    );

    app(AckHandler::class)->handle(
        "mqtt/face/{$camera->device_id}/Ack",
        json_encode([
            'messageId' => 'integration-msg',
            'info' => ['AddSucInfo' => [['customId' => $personnel->custom_id]]],
        ]),
    );

    expect($enrollment->fresh()->status)->toBe(CameraEnrollmentStatus::Done);

    // 6. Once every enrollment row has settled, photo URL returns 404.
    $this->get("/fras/photo/{$personnel->photo_access_token}")
        ->assertNotFound();
});

it('camera deletion is blocked while enrollments are in-flight (CAMERA-06 integration)', function () {
    $camera = Camera::factory()->create([
        'decommissioned_at' => null,
        'location' => Point::makeGeodetic(8.9475, 125.5406),
    ]);
    $personnel = Personnel::factory()->create();
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.cameras.destroy', $camera))
        ->assertSessionHasErrors('camera');

    expect($camera->fresh()->decommissioned_at)->toBeNull();
});

it('personnel removal triggers DeletePersons MQTT and soft-decommissions the row (PERSONNEL-01 integration)', function () {
    Camera::factory()->create([
        'status' => CameraStatus::Online,
        'decommissioned_at' => null,
        'location' => Point::makeGeodetic(8.9475, 125.5406),
    ]);
    $personnel = Personnel::factory()->create(['decommissioned_at' => null]);

    MQTT::shouldReceive('connection->publish')->atLeast()->once();

    $this->actingAs($this->admin)
        ->delete(route('admin.personnel.destroy', $personnel))
        ->assertRedirect();

    // D-33: row preserved, only decommissioned_at set.
    expect($personnel->fresh()->decommissioned_at)->not->toBeNull();
    expect(Personnel::find($personnel->id))->not->toBeNull();
});
