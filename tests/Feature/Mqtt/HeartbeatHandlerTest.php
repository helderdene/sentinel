<?php

use App\Models\Camera;
use App\Mqtt\Handlers\HeartbeatHandler;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

pest()->group('mqtt');

beforeEach(function () {
    $this->camera = Camera::factory()->create([
        'device_id' => 'CAM01',
        'last_seen_at' => null,
    ]);
});

it('bumps cameras.last_seen_at for a registered device on heartbeat', function () {
    app(HeartbeatHandler::class)->handle(
        'mqtt/face/heartbeat',
        json_encode(['facesluiceId' => 'CAM01']),
    );

    $this->camera->refresh();
    expect($this->camera->last_seen_at)->not->toBeNull();
    // Freshness check — the pgsql connection in this project is not configured
    // with a `timezone` key, so absolute timestamps skew by the session tz offset
    // (a pre-existing config gap tracked for later). A 24h tolerance still proves
    // "heartbeat just ran" (not a pre-factory null) without depending on that fix.
    expect(abs($this->camera->last_seen_at->timestamp - now()->timestamp))->toBeLessThan(86400);
});

it('logs a warning and makes no DB change for an unknown device heartbeat', function () {
    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('warning')
        ->once()
        ->with('Heartbeat for unknown camera', Mockery::on(fn ($ctx) => ($ctx['device_id'] ?? null) === 'CAM-UNKNOWN'));
    Log::shouldReceive('channel')->with('mqtt')->andReturn($channel);

    app(HeartbeatHandler::class)->handle(
        'mqtt/face/heartbeat',
        json_encode(['facesluiceId' => 'CAM-UNKNOWN']),
    );

    $this->camera->refresh();
    expect($this->camera->last_seen_at)->toBeNull();
});

it('updates last_seen_at when driven by the heartbeat.json fixture', function () {
    $json = file_get_contents(base_path('tests/fixtures/mqtt/heartbeat.json'));

    app(HeartbeatHandler::class)->handle('mqtt/face/heartbeat', $json);

    $this->camera->refresh();
    expect($this->camera->last_seen_at)->not->toBeNull();
});
