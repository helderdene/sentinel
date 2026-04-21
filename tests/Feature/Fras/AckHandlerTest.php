<?php

use App\Enums\CameraEnrollmentStatus;
use App\Events\EnrollmentProgressed;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use App\Mqtt\Handlers\AckHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

pest()->group('fras');

beforeEach(function () {
    Cache::flush();
    Event::fake([EnrollmentProgressed::class]);
});

it('correlates ACK success to pending enrollment row and broadcasts progress', function () {
    $camera = Camera::factory()->create(['device_id' => 'cam-42']);
    $personnel = Personnel::factory()->create(['custom_id' => 'abc123']);
    $enrollment = CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);

    Cache::put("enrollment-ack:{$camera->id}:msg-42", [
        'camera_id' => $camera->id,
        'personnel_ids' => [$personnel->id],
        'photo_hashes' => ['abc123' => 'hashvalue'],
        'dispatched_at' => now()->toIso8601String(),
    ], 300);

    app(AckHandler::class)->handle(
        'mqtt/face/cam-42/Ack',
        json_encode(['messageId' => 'msg-42', 'info' => ['AddSucInfo' => [['customId' => 'abc123']]]])
    );

    $updated = $enrollment->fresh();
    expect($updated->status)->toBe(CameraEnrollmentStatus::Done);
    expect($updated->photo_hash)->toBe('hashvalue');
    expect($updated->enrolled_at)->not->toBeNull();

    expect(Cache::has("enrollment-ack:{$camera->id}:msg-42"))->toBeFalse(); // consumed

    Event::assertDispatched(EnrollmentProgressed::class);
});

it('correlates ACK failure with error code via translateErrorCode', function () {
    $camera = Camera::factory()->create(['device_id' => 'cam-42']);
    $personnel = Personnel::factory()->create(['custom_id' => 'abc123']);
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);

    Cache::put("enrollment-ack:{$camera->id}:msg-err", [
        'camera_id' => $camera->id,
        'personnel_ids' => [$personnel->id],
        'photo_hashes' => ['abc123' => 'hashvalue'],
        'dispatched_at' => now()->toIso8601String(),
    ], 300);

    app(AckHandler::class)->handle(
        'mqtt/face/cam-42/Ack',
        json_encode(['messageId' => 'msg-err', 'info' => ['AddErrInfo' => [['customId' => 'abc123', 'errorCode' => 467]]]])
    );

    $updated = CameraEnrollment::where('camera_id', $camera->id)->where('personnel_id', $personnel->id)->first();
    expect($updated->status)->toBe(CameraEnrollmentStatus::Failed);
    expect($updated->last_error)->toContain('face');
});

it('duplicate ACK delivery produces only ONE transition (idempotency via Cache::pull)', function () {
    $camera = Camera::factory()->create(['device_id' => 'cam-42']);
    $personnel = Personnel::factory()->create(['custom_id' => 'abc123']);
    CameraEnrollment::factory()->create([
        'camera_id' => $camera->id,
        'personnel_id' => $personnel->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);

    Cache::put("enrollment-ack:{$camera->id}:msg-dup", [
        'camera_id' => $camera->id,
        'personnel_ids' => [$personnel->id],
        'photo_hashes' => ['abc123' => 'h'],
        'dispatched_at' => now()->toIso8601String(),
    ], 300);

    $payload = json_encode(['messageId' => 'msg-dup', 'info' => ['AddSucInfo' => [['customId' => 'abc123']]]]);

    app(AckHandler::class)->handle('mqtt/face/cam-42/Ack', $payload);
    app(AckHandler::class)->handle('mqtt/face/cam-42/Ack', $payload); // duplicate

    Event::assertDispatched(EnrollmentProgressed::class, 1); // not 2
});

it('warn-logs when cache key missing (timeout) and does not modify rows', function () {
    $channelSpy = Mockery::spy(LoggerInterface::class);
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channelSpy);

    $camera = Camera::factory()->create(['device_id' => 'cam-42']);

    app(AckHandler::class)->handle(
        'mqtt/face/cam-42/Ack',
        json_encode(['messageId' => 'msg-gone', 'info' => ['AddSucInfo' => [['customId' => 'x']]]])
    );

    $channelSpy->shouldHaveReceived('warning')->with('ACK for unknown or expired messageId', Mockery::any());
});

it('warn-logs when camera device_id is unknown and does not throw', function () {
    $channelSpy = Mockery::spy(LoggerInterface::class);
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channelSpy);

    app(AckHandler::class)->handle(
        'mqtt/face/cam-unknown/Ack',
        json_encode(['messageId' => 'msg-x', 'info' => []])
    );

    $channelSpy->shouldHaveReceived('warning')->with('ACK for unknown camera', Mockery::any());
});
