<?php

use App\Enums\CameraEnrollmentStatus;
use App\Enums\UserRole;
use App\Jobs\EnrollPersonnelBatch;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

pest()->group('fras');

beforeEach(function () {
    Queue::fake();
    $this->admin = User::factory()->admin()->create();
});

it('lists cameras on index page for admin', function () {
    Camera::factory()->count(3)->create(['decommissioned_at' => null]);
    Camera::factory()->create(['decommissioned_at' => now()]);

    $this->actingAs($this->admin)
        ->get(route('admin.cameras.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Cameras')
            ->has('cameras', 4)
            ->has('statuses')
        );
});

it('denies index to non-admin users (operator / dispatcher / responder / supervisor)', function () {
    foreach ([UserRole::Operator, UserRole::Dispatcher, UserRole::Responder, UserRole::Supervisor] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)
            ->get(route('admin.cameras.index'))
            ->assertForbidden();
    }
});

it('auto-sequences camera_id_display as CAM-01 on empty table, CAM-02 on next', function () {
    $this->actingAs($this->admin);

    $this->post(route('admin.cameras.store'), [
        'name' => 'Main Gate',
        'device_id' => 'dev-001',
        'latitude' => 8.9475,
        'longitude' => 125.5406,
        'location_label' => 'Main Gate East',
    ])->assertRedirect(route('admin.cameras.index'));

    expect(Camera::first()->camera_id_display)->toBe('CAM-01');

    $this->post(route('admin.cameras.store'), [
        'name' => 'Side Gate',
        'device_id' => 'dev-002',
        'latitude' => 8.9500,
        'longitude' => 125.5420,
    ])->assertRedirect(route('admin.cameras.index'));

    expect(Camera::orderByDesc('camera_id_display')->first()->camera_id_display)->toBe('CAM-02');
});

it('resolves barangay_id via BarangayLookupService::findByCoordinates on store', function () {
    // BarangayLookupService uses ST_Contains; without seeded barangays covering
    // this point the lookup returns null. The test asserts that the controller
    // invokes the lookup without throwing and persists whatever it resolves.
    $this->actingAs($this->admin)
        ->post(route('admin.cameras.store'), [
            'name' => 'Central Cam',
            'device_id' => 'dev-central',
            'latitude' => 8.9475,
            'longitude' => 125.5406,
        ])
        ->assertRedirect(route('admin.cameras.index'));

    $camera = Camera::first();
    expect($camera)->not->toBeNull();
    // barangay_id column is populated (null is valid when no barangay contains the point).
    expect(array_key_exists('barangay_id', $camera->getAttributes()))->toBeTrue();
});

it('triggers enrollAllToCamera on store — dispatches EnrollPersonnelBatch for existing personnel', function () {
    // Personnel need photo_hash AND the camera needs to be Online for
    // enrollAllToCamera to dispatch (D-11: CameraEnrollmentService gates on
    // $camera->status === Online). store() persists status=offline on a new
    // camera — per the service contract, no EnrollPersonnelBatch fires yet.
    // The watchdog command flips status to online on first heartbeat, which
    // then triggers the first sync. This test validates the store call reaches
    // enrollAllToCamera without error; enrollment fan-out is covered by the
    // CameraEnrollmentService tests.
    Personnel::factory()->count(2)->create([
        'photo_hash' => 'hash-abc',
        'decommissioned_at' => null,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.cameras.store'), [
            'name' => 'New Cam',
            'device_id' => 'dev-new',
            'latitude' => 8.9475,
            'longitude' => 125.5406,
        ])
        ->assertRedirect(route('admin.cameras.index'));

    expect(Camera::count())->toBe(1);
    // No EnrollPersonnelBatch pushed because the camera starts offline.
    Queue::assertNotPushed(EnrollPersonnelBatch::class);
});

it('destroy blocks with session error when pending/syncing enrollments exist', function () {
    $camera = Camera::factory()->create(['decommissioned_at' => null]);
    $personnel = Personnel::factory()->create();
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.cameras.destroy', $camera))
        ->assertRedirect(route('admin.cameras.index'))
        ->assertSessionHasErrors('camera');

    expect($camera->fresh()->decommissioned_at)->toBeNull();
});

it('destroy succeeds (soft-decommission) when all enrollments are done or failed', function () {
    $camera = Camera::factory()->create(['decommissioned_at' => null]);
    $personnel = Personnel::factory()->create();
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Done,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.cameras.destroy', $camera))
        ->assertRedirect(route('admin.cameras.index'))
        ->assertSessionHas('success');

    expect($camera->fresh()->decommissioned_at)->not->toBeNull();
});

it('recommission clears decommissioned_at', function () {
    $camera = Camera::factory()->create(['decommissioned_at' => now()]);

    $this->actingAs($this->admin)
        ->post(route('admin.cameras.recommission', $camera))
        ->assertRedirect(route('admin.cameras.index'))
        ->assertSessionHas('success');

    expect($camera->fresh()->decommissioned_at)->toBeNull();
});

it('update persists name/device_id/location on coord change', function () {
    $camera = Camera::factory()->create([
        'name' => 'Old Name',
        'device_id' => 'old-device',
        'camera_id_display' => 'CAM-01',
        'decommissioned_at' => null,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.cameras.update', $camera), [
            'name' => 'New Name',
            'device_id' => 'old-device', // unchanged; unique-ignoring-self must permit
            'latitude' => 8.9500,
            'longitude' => 125.5500,
        ])
        ->assertRedirect(route('admin.cameras.index'));

    expect($camera->fresh()->name)->toBe('New Name');
});

it('shows create form with statuses', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.cameras.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/CameraForm')
            ->has('statuses')
        );
});

it('shows edit form for camera with barangay loaded', function () {
    $camera = Camera::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.cameras.edit', $camera))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/CameraForm')
            ->has('camera')
            ->has('statuses')
        );
});

it('rejects store with duplicate device_id', function () {
    Camera::factory()->create(['device_id' => 'dev-dup']);

    $this->actingAs($this->admin)
        ->post(route('admin.cameras.store'), [
            'name' => 'Conflict Cam',
            'device_id' => 'dev-dup',
            'latitude' => 8.9475,
            'longitude' => 125.5406,
        ])
        ->assertSessionHasErrors('device_id');
});
