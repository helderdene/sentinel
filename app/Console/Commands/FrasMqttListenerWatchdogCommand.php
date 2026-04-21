<?php

namespace App\Console\Commands;

use App\Events\MqttListenerHealthChanged;
use App\Models\Camera;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FrasMqttListenerWatchdogCommand extends Command
{
    private const SILENCE_THRESHOLD_SECONDS = 90;

    protected $signature = 'irms:mqtt-listener-watchdog';

    protected $description = 'Detect MQTT listener silence and broadcast health transitions';

    public function handle(): int
    {
        $activeCount = Camera::whereNull('decommissioned_at')->count();

        if ($activeCount === 0) {
            $this->transition('NO_ACTIVE_CAMERAS', null, $activeCount);

            return self::SUCCESS;
        }

        $lastMessageAt = Cache::get('mqtt:listener:last_message_received_at');
        $gapSeconds = $lastMessageAt
            ? now()->diffInSeconds(Carbon::parse($lastMessageAt), true)
            : PHP_INT_MAX;

        $state = $gapSeconds < self::SILENCE_THRESHOLD_SECONDS ? 'HEALTHY' : 'SILENT';

        $this->transition($state, $lastMessageAt, $activeCount);

        return self::SUCCESS;
    }

    private function transition(string $state, ?string $lastMessageAt, int $activeCount): void
    {
        $previous = Cache::get('mqtt:listener:last_known_state');

        if ($previous === $state) {
            return;
        }

        $since = now()->toIso8601String();

        Cache::put('mqtt:listener:last_known_state', $state);
        Cache::put('mqtt:listener:last_state_since', $since);

        MqttListenerHealthChanged::dispatch($state, $lastMessageAt, $since, $activeCount);
    }
}
