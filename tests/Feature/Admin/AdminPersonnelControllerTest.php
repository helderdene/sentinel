<?php

use App\Enums\CameraEnrollmentStatus;
use App\Enums\CameraStatus;
use App\Enums\UserRole;
use App\Jobs\EnrollPersonnelBatch;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

pest()->group('fras');

beforeEach(function () {
    Queue::fake();
    Storage::fake('fras_photos');
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

it('lists all personnel on index for admin', function () {
    Personnel::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.personnel.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/Personnel')->has('personnel', 3));
});

it('denies non-admin users on index', function () {
    foreach ([UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Responder] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)->get(route('admin.personnel.index'))->assertForbidden();
    }
});

it('stores personnel with photo, sets custom_id from UUID (no dashes), and sets photo_access_token', function () {
    $photo = UploadedFile::fake()->image('face.jpg', 800, 600);

    $this->actingAs($this->admin)
        ->post(route('admin.personnel.store'), [
            'name' => 'Jane Doe',
            'category' => 'block',
            'consent_basis' => 'Police blotter 2026-041',
            'photo' => $photo,
        ])
        ->assertRedirect();

    $personnel = Personnel::first();
    expect($personnel->name)->toBe('Jane Doe');
    expect($personnel->custom_id)->toBe(str_replace('-', '', $personnel->id));
    expect($personnel->custom_id)->toHaveLength(32);
    expect($personnel->photo_access_token)->not->toBeNull();
    expect($personnel->photo_hash)->not->toBeNull();
    Storage::disk('fras_photos')->assertExists("personnel/{$personnel->id}.jpg");
});

it('rotates photo_access_token on photo replace during update', function () {
    $oldToken = Str::uuid()->toString();
    $personnel = Personnel::factory()->create([
        'photo_access_token' => $oldToken,
        'photo_path' => 'personnel/old.jpg',
        'photo_hash' => 'oldhash',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.personnel.update', $personnel), [
            'name' => $personnel->name,
            'category' => $personnel->category->value,
            'consent_basis' => $personnel->consent_basis ?? 'reason',
            'photo' => UploadedFile::fake()->image('new.jpg', 600, 400),
        ])
        ->assertRedirect();

    $personnel->refresh();
    expect($personnel->photo_access_token)->not->toBe($oldToken);
});

it('rejects photo over 1MB', function () {
    $oversize = UploadedFile::fake()->image('big.jpg')->size(2048); // 2MB

    $this->actingAs($this->admin)
        ->post(route('admin.personnel.store'), [
            'name' => 'Jane',
            'category' => 'block',
            'consent_basis' => 'x',
            'photo' => $oversize,
        ])
        ->assertSessionHasErrors('photo');
});

it('destroy soft-decommissions personnel (not hard-delete) and publishes DeletePersons MQTT', function () {
    $personnel = Personnel::factory()->create(['decommissioned_at' => null]);
    Camera::factory()->create(['status' => CameraStatus::Online, 'decommissioned_at' => null]);

    MQTT::shouldReceive('connection->publish')->atLeast()->once();

    $this->actingAs($this->admin)
        ->delete(route('admin.personnel.destroy', $personnel))
        ->assertRedirect();

    expect($personnel->fresh()->decommissioned_at)->not->toBeNull();
    // Row still present (not hard-deleted)
    expect(Personnel::find($personnel->id))->not->toBeNull();
});

it('recommission clears decommissioned_at', function () {
    $personnel = Personnel::factory()->create(['decommissioned_at' => now()]);

    $this->actingAs($this->admin)
        ->post(route('admin.personnel.recommission', $personnel))
        ->assertRedirect();

    expect($personnel->fresh()->decommissioned_at)->toBeNull();
});

it('retry endpoint marks the specified enrollment pending and dispatches EnrollPersonnelBatch', function () {
    $personnel = Personnel::factory()->create();
    $camera = Camera::factory()->create(['status' => CameraStatus::Online]);
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Failed,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.personnel.enrollment.retry', ['personnel' => $personnel, 'camera' => $camera]))
        ->assertRedirect();

    expect(CameraEnrollment::where('camera_id', $camera->id)->first()->status)
        ->toBe(CameraEnrollmentStatus::Pending);

    Queue::assertPushed(EnrollPersonnelBatch::class, function ($job) use ($camera) {
        return $job->camera->id === $camera->id;
    });
});

it('resync endpoint calls enrollPersonnel (dispatches per active camera)', function () {
    $personnel = Personnel::factory()->create(['photo_hash' => 'h']);
    Camera::factory()->count(3)->create(['status' => CameraStatus::Online, 'decommissioned_at' => null]);

    $this->actingAs($this->admin)
        ->post(route('admin.personnel.enrollment.resync', $personnel))
        ->assertRedirect();

    Queue::assertPushed(EnrollPersonnelBatch::class, 3);
});
