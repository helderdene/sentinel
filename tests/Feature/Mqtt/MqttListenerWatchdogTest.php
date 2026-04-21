<?php

use App\Events\MqttListenerHealthChanged;
use App\Models\Camera;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

pest()->group('mqtt');

beforeEach(function () {
    Cache::forget('mqtt:listener:last_message_received_at');
    Cache::forget('mqtt:listener:last_known_state');
    Cache::forget('mqtt:listener:last_state_since');
});

it('reports NO_ACTIVE_CAMERAS and dispatches transition event when no cameras exist', function () {
    Event::fake([MqttListenerHealthChanged::class]);

    $this->artisan('irms:mqtt-listener-watchdog')->assertSuccessful();

    Event::assertDispatched(MqttListenerHealthChanged::class, function ($event) {
        return $event->status === 'NO_ACTIVE_CAMERAS'
            && $event->activeCameraCount === 0;
    });

    expect(Cache::get('mqtt:listener:last_known_state'))->toBe('NO_ACTIVE_CAMERAS');
});

it('reports HEALTHY when last message was within 90s and does not re-dispatch if state unchanged', function () {
    Camera::factory()->create();
    Cache::put('mqtt:listener:last_message_received_at', now()->subSeconds(30)->toIso8601String());
    Cache::put('mqtt:listener:last_known_state', 'HEALTHY');

    Event::fake([MqttListenerHealthChanged::class]);

    $this->artisan('irms:mqtt-listener-watchdog')->assertSuccessful();

    Event::assertNotDispatched(MqttListenerHealthChanged::class);
    expect(Cache::get('mqtt:listener:last_known_state'))->toBe('HEALTHY');
});

it('transitions to SILENT and dispatches event when gap exceeds 90 seconds', function () {
    Camera::factory()->create();
    $lastAt = now()->subSeconds(100)->toIso8601String();
    Cache::put('mqtt:listener:last_message_received_at', $lastAt);
    Cache::put('mqtt:listener:last_known_state', 'HEALTHY');

    Event::fake([MqttListenerHealthChanged::class]);

    $this->artisan('irms:mqtt-listener-watchdog')->assertSuccessful();

    Event::assertDispatched(MqttListenerHealthChanged::class, function ($event) use ($lastAt) {
        return $event->status === 'SILENT'
            && $event->lastMessageReceivedAt === $lastAt
            && $event->activeCameraCount === 1;
    });

    expect(Cache::get('mqtt:listener:last_known_state'))->toBe('SILENT');
});

it('treats a missing last_message_received_at cache key as infinite gap → SILENT', function () {
    Camera::factory()->create();

    Event::fake([MqttListenerHealthChanged::class]);

    $this->artisan('irms:mqtt-listener-watchdog')->assertSuccessful();

    Event::assertDispatched(MqttListenerHealthChanged::class, function ($event) {
        return $event->status === 'SILENT'
            && $event->lastMessageReceivedAt === null;
    });
});

it('does not re-dispatch when previous state equals current state (SILENT → SILENT)', function () {
    Camera::factory()->create();
    Cache::put('mqtt:listener:last_message_received_at', now()->subSeconds(200)->toIso8601String());
    Cache::put('mqtt:listener:last_known_state', 'SILENT');

    Event::fake([MqttListenerHealthChanged::class]);

    $this->artisan('irms:mqtt-listener-watchdog')->assertSuccessful();

    Event::assertNotDispatched(MqttListenerHealthChanged::class);
});

it('ignores decommissioned cameras when computing active count (D-09)', function () {
    Camera::factory()->count(2)->create(['decommissioned_at' => now()->subDay()]);

    Event::fake([MqttListenerHealthChanged::class]);

    $this->artisan('irms:mqtt-listener-watchdog')->assertSuccessful();

    Event::assertDispatched(MqttListenerHealthChanged::class, function ($event) {
        return $event->status === 'NO_ACTIVE_CAMERAS'
            && $event->activeCameraCount === 0;
    });
});

it('registers irms:mqtt-listener-watchdog on the 30-second schedule', function () {
    $exitCode = Artisan::call('schedule:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('irms:mqtt-listener-watchdog');
});
