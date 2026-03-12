<?php

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;

it('returns only PENDING incidents in queue', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Incident::factory()->count(3)->create(['status' => IncidentStatus::Pending]);
    Incident::factory()->create(['status' => IncidentStatus::Dispatched]);
    Incident::factory()->create(['status' => IncidentStatus::Resolved]);

    $this->actingAs($dispatcher)
        ->get(route('incidents.queue'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('incidents/Queue')
            ->has('incidents', 3)
            ->has('channelCounts')
        );
});

it('orders queue by priority P1 first then FIFO', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $p3 = Incident::factory()->create([
        'priority' => 'P3',
        'status' => IncidentStatus::Pending,
        'created_at' => now()->subMinutes(10),
    ]);

    $p1 = Incident::factory()->create([
        'priority' => 'P1',
        'status' => IncidentStatus::Pending,
        'created_at' => now()->subMinutes(5),
    ]);

    $p1_older = Incident::factory()->create([
        'priority' => 'P1',
        'status' => IncidentStatus::Pending,
        'created_at' => now()->subMinutes(8),
    ]);

    $p2 = Incident::factory()->create([
        'priority' => 'P2',
        'status' => IncidentStatus::Pending,
        'created_at' => now(),
    ]);

    $this->actingAs($dispatcher)
        ->get(route('incidents.queue'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('incidents/Queue')
            ->has('incidents', 4)
            ->where('incidents.0.id', $p1_older->id)
            ->where('incidents.1.id', $p1->id)
            ->where('incidents.2.id', $p2->id)
            ->where('incidents.3.id', $p3->id)
        );
});

it('returns channel counts for pending incidents', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Incident::factory()->count(2)->create([
        'status' => IncidentStatus::Pending,
        'channel' => 'phone',
    ]);
    Incident::factory()->create([
        'status' => IncidentStatus::Pending,
        'channel' => 'sms',
    ]);

    $this->actingAs($dispatcher)
        ->get(route('incidents.queue'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('channelCounts')
        );
});

it('returns all incidents with status filter on index page', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    Incident::factory()->count(3)->create(['status' => IncidentStatus::Pending]);
    Incident::factory()->count(2)->create(['status' => IncidentStatus::Resolved]);

    $this->actingAs($dispatcher)
        ->get(route('incidents.index', ['status' => 'PENDING']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('incidents/Index')
            ->has('incidents.data', 3)
        );
});

it('returns incident detail with relations on show page', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $incident = Incident::factory()->create(['created_by' => $dispatcher->id]);

    $this->actingAs($dispatcher)
        ->get(route('incidents.show', $incident))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('incidents/Show')
            ->has('incident')
        );
});

it('blocks responder from accessing queue', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->get(route('incidents.queue'))
        ->assertForbidden();
});

it('returns priority suggestion as JSON', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create(['default_priority' => 'P3']);

    $this->actingAs($dispatcher)
        ->get(route('api.priority.suggest', [
            'incident_type_id' => $type->id,
            'notes' => 'trapped and unconscious',
        ]))
        ->assertOk()
        ->assertJsonStructure(['priority', 'confidence']);
});

it('returns geocoding results as JSON', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('api.geocoding.search', [
            'query' => 'Butuan City Hall',
        ]))
        ->assertOk()
        ->assertJsonStructure([
            '*' => ['lat', 'lng', 'display_name'],
        ]);
});
