<?php

use App\Enums\CameraStatus;
use App\Enums\PersonnelCategory;
use App\Jobs\EnrollPersonnelBatch;
use App\Models\Camera;
use App\Models\Personnel;
use Illuminate\Support\Facades\Queue;
use PhpMqtt\Client\Facades\MQTT;

pest()->group('fras');

beforeEach(function () {
    Queue::fake();
    // Ensure at least two online cameras so enrollPersonnel has targets.
    Camera::factory()->count(2)->create([
        'status' => CameraStatus::Online,
        'decommissioned_at' => null,
    ]);
});

it('does NOT dispatch EnrollPersonnelBatch when a non-gated field changes (name)', function () {
    $p = Personnel::factory()->create([
        'name' => 'Jane Doe',
        'photo_hash' => 'hashA',
        'category' => PersonnelCategory::Block,
    ]);
    Queue::fake(); // reset — factory create may have fired observer if photo_hash attribute dirty

    $p->update(['name' => 'Jane Smith']);

    Queue::assertNotPushed(EnrollPersonnelBatch::class);
});

it('dispatches EnrollPersonnelBatch per active camera when photo_hash changes', function () {
    $p = Personnel::factory()->create([
        'photo_hash' => 'hashA',
        'category' => PersonnelCategory::Block,
    ]);
    Queue::fake();

    $p->update(['photo_hash' => 'hashB']);

    Queue::assertPushed(EnrollPersonnelBatch::class, 2); // 2 online cameras from beforeEach
});

it('dispatches EnrollPersonnelBatch when category changes', function () {
    $p = Personnel::factory()->create([
        'photo_hash' => 'hashA',
        'category' => PersonnelCategory::Block,
    ]);
    Queue::fake();

    $p->update(['category' => PersonnelCategory::Missing]);

    Queue::assertPushed(EnrollPersonnelBatch::class, 2);
});

it('publishes DeletePersons MQTT per active camera when Personnel is deleted', function () {
    $p = Personnel::factory()->create(['photo_hash' => 'hashA', 'custom_id' => 'abc123']);

    MQTT::shouldReceive('connection->publish')->times(2); // 2 online cameras

    $p->delete();
});

it('does NOT dispatch on pure hydration (Personnel::find) — observer skips saved-not-changed path', function () {
    $p = Personnel::factory()->create(['photo_hash' => 'hashA']);
    Queue::fake();

    $rehydrated = Personnel::find($p->id);
    expect($rehydrated->photo_hash)->toBe('hashA');

    Queue::assertNotPushed(EnrollPersonnelBatch::class);
});
