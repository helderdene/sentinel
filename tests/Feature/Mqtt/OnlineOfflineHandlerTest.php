<?php

use App\Enums\CameraStatus;
use App\Models\Camera;
use App\Mqtt\Handlers\OnlineOfflineHandler;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

pest()->group('mqtt');

beforeEach(function () {
    $this->camera = Camera::factory()->create([
        'device_id' => 'CAM01',
        'status' => CameraStatus::Offline,
    ]);
});

it('flips camera status to Online on operator=Online', function () {
    app(OnlineOfflineHandler::class)->handle(
        'mqtt/face/basic',
        json_encode(['facesluiceId' => 'CAM01', 'operator' => 'Online']),
    );

    $this->camera->refresh();
    expect($this->camera->status)->toBe(CameraStatus::Online);
});

it('flips camera status to Offline on operator=Offline', function () {
    $this->camera->update(['status' => CameraStatus::Online]);

    app(OnlineOfflineHandler::class)->handle(
        'mqtt/face/basic',
        json_encode(['facesluiceId' => 'CAM01', 'operator' => 'Offline']),
    );

    $this->camera->refresh();
    expect($this->camera->status)->toBe(CameraStatus::Offline);
});

it('logs a warning and makes no DB change for an unknown device', function () {
    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('warning')->once();
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channel);

    app(OnlineOfflineHandler::class)->handle(
        'mqtt/face/basic',
        json_encode(['facesluiceId' => 'CAM-UNKNOWN', 'operator' => 'Online']),
    );

    $this->camera->refresh();
    expect($this->camera->status)->toBe(CameraStatus::Offline);
});

it('never writes CameraStatus::Degraded during Phase 19 (D-08 regression guard)', function () {
    foreach (['Online', 'Offline', 'Degraded', 'weird'] as $operator) {
        app(OnlineOfflineHandler::class)->handle(
            'mqtt/face/basic',
            json_encode(['facesluiceId' => 'CAM01', 'operator' => $operator]),
        );
    }

    $this->camera->refresh();
    expect($this->camera->status)->not->toBe(CameraStatus::Degraded);
});

it('drives expected transitions when fed online.json + offline.json fixtures', function () {
    $online = file_get_contents(base_path('tests/fixtures/mqtt/online.json'));
    $offline = file_get_contents(base_path('tests/fixtures/mqtt/offline.json'));

    app(OnlineOfflineHandler::class)->handle('mqtt/face/basic', $online);
    $this->camera->refresh();
    expect($this->camera->status)->toBe(CameraStatus::Online);

    app(OnlineOfflineHandler::class)->handle('mqtt/face/basic', $offline);
    $this->camera->refresh();
    expect($this->camera->status)->toBe(CameraStatus::Offline);
});
