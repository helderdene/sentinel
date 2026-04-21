<?php

use App\Enums\CameraEnrollmentStatus;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

pest()->group('fras');

beforeEach(function () {
    Storage::fake('fras_photos');
});

it('serves photo with 200 when personnel has at least one pending enrollment', function () {
    $token = Str::uuid()->toString();
    $personnel = Personnel::factory()->create(['photo_access_token' => $token]);
    $camera = Camera::factory()->create();
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Pending,
    ]);
    Storage::disk('fras_photos')->put("personnel/{$personnel->id}.jpg", 'fake-jpeg-bytes');

    $response = $this->get("/fras/photo/{$token}");

    $response->assertOk();
});

it('serves photo with 200 when personnel has syncing enrollment', function () {
    $token = Str::uuid()->toString();
    $personnel = Personnel::factory()->create(['photo_access_token' => $token]);
    $camera = Camera::factory()->create();
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);
    Storage::disk('fras_photos')->put("personnel/{$personnel->id}.jpg", 'fake');

    $this->get("/fras/photo/{$token}")->assertOk();
});

it('returns 404 when personnel has no pending/syncing enrollments (all done — revocation)', function () {
    $token = Str::uuid()->toString();
    $personnel = Personnel::factory()->create(['photo_access_token' => $token]);
    $camera = Camera::factory()->create();
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Done,
    ]);
    Storage::disk('fras_photos')->put("personnel/{$personnel->id}.jpg", 'fake');

    $this->get("/fras/photo/{$token}")->assertNotFound();
});

it('returns 404 for unknown token', function () {
    $this->get('/fras/photo/'.Str::uuid()->toString())->assertNotFound();
});

it('returns 404 when token was rotated (old token invalidated)', function () {
    $oldToken = Str::uuid()->toString();
    $newToken = Str::uuid()->toString();
    $personnel = Personnel::factory()->create(['photo_access_token' => $oldToken]);
    $camera = Camera::factory()->create();
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Pending,
    ]);

    // Rotate
    $personnel->update(['photo_access_token' => $newToken]);

    $this->get("/fras/photo/{$oldToken}")->assertNotFound();
    Storage::disk('fras_photos')->put("personnel/{$personnel->id}.jpg", 'fake');
    $this->get("/fras/photo/{$newToken}")->assertOk();
});
