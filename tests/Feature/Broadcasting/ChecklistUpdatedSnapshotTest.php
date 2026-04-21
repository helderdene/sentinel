<?php

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Events\ChecklistUpdated;
use App\Models\Barangay;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Pin clock to a fixed instant so `created_at` / `now()` are identical across runs.
    Carbon::setTestNow(Carbon::parse('2026-04-21T00:00:00Z'));
});

it('ChecklistUpdated payload matches golden fixture', function () {
    $user = User::factory()->create([
        'id' => 1,
        'email' => 'snapshot@irms.test',
        'name' => 'Snapshot Op',
    ]);
    $type = IncidentType::factory()->create([
        'id' => 1,
        'name' => 'Fire',
        'code' => 'FIRE',
    ]);
    $barangay = Barangay::factory()->create([
        'id' => 1,
        'name' => 'Libertad',
    ]);

    $incident = Incident::factory()
        ->for($user, 'createdBy')
        ->for($type, 'incidentType')
        ->for($barangay)
        ->create([
            'id' => '01929000-aaaa-bbbb-cccc-000000000005',
            'incident_no' => 'INC-2026-00005',
            'location_text' => 'J.C. Aquino Ave.',
            'caller_name' => 'Snapshot Tester',
            'caller_contact' => '09171234567',
            'notes' => 'Fixture data',
            'priority' => IncidentPriority::P2,
            'status' => IncidentStatus::OnScene,
            'channel' => IncidentChannel::Phone,
            'coordinates' => Point::makeGeodetic(8.9475, 125.5406),
            'checklist_pct' => 75,
        ]);

    $payload = (new ChecklistUpdated($incident))->broadcastWith();
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $fixturePath = __DIR__.'/__snapshots__/ChecklistUpdated.json';

    if (! file_exists($fixturePath)) {
        file_put_contents($fixturePath, $json);
        $this->markTestIncomplete('Golden fixture created; re-run to verify.');
    }

    expect($json)->toBe(file_get_contents($fixturePath));
});
