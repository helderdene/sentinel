<?php

use App\Events\CameraStatusChanged;
use App\Events\EnrollmentProgressed;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

pest()->group('fras');

it('has Intervention Image v4 Laravel facade resolvable', function () {
    expect(class_exists(Image::class))->toBeTrue();
    expect(class_exists(JpegEncoder::class))->toBeTrue();
});

it('exposes fras.cameras and fras.photo config keys with expected defaults', function () {
    expect(config('fras.cameras.degraded_gap_s'))->toBe(30);
    expect(config('fras.cameras.offline_gap_s'))->toBe(90);
    expect(config('fras.enrollment.batch_size'))->toBe(10);
    expect(config('fras.enrollment.ack_timeout_minutes'))->toBe(5);
    expect(config('fras.photo.max_dimension'))->toBe(1080);
    expect(config('fras.photo.jpeg_quality'))->toBe(85);
    expect(config('fras.photo.max_size_bytes'))->toBe(1_048_576);
});

it('registers the fras_photos private disk', function () {
    expect(config('filesystems.disks.fras_photos.driver'))->toBe('local');
    expect(config('filesystems.disks.fras_photos.visibility'))->toBe('private');
    expect(config('filesystems.disks.fras_photos.root'))->toEndWith('fras_photos');
});

it('adds photo_access_token uuid column to personnel', function () {
    expect(Schema::hasColumn('personnel', 'photo_access_token'))->toBeTrue();
});

it('CameraStatusChanged is a ShouldBroadcast + ShouldDispatchAfterCommit event on fras.cameras', function () {
    $camera = Camera::factory()->make();
    $event = new CameraStatusChanged($camera);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
    expect($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class);
    expect($event->broadcastOn())->toEqual([new PrivateChannel('fras.cameras')]);
    expect($event->broadcastWith())
        ->toHaveKeys(['camera_id', 'camera_id_display', 'status', 'last_seen_at', 'location']);
});

it('EnrollmentProgressed is a ShouldBroadcast + ShouldDispatchAfterCommit event on fras.enrollments', function () {
    $camera = Camera::factory()->create();
    $personnel = Personnel::factory()->create();
    $enrollment = CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
    ]);
    $event = new EnrollmentProgressed($enrollment);

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
    expect($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class);
    expect($event->broadcastOn())->toEqual([new PrivateChannel('fras.enrollments')]);
    expect($event->broadcastWith())
        ->toHaveKeys(['personnel_id', 'camera_id', 'camera_id_display', 'status', 'last_error']);
});

it('Personnel and Camera expose enrollments HasMany relations', function () {
    expect((new Personnel)->enrollments())->toBeInstanceOf(HasMany::class);
    expect((new Camera)->enrollments())->toBeInstanceOf(HasMany::class);
});

it('Personnel has photo_access_token in fillable', function () {
    expect((new Personnel)->getFillable())->toContain('photo_access_token');
});
