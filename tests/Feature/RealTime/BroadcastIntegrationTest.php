<?php

use App\Events\IncidentCreated;
use App\Models\IncidentType;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('creating incident via store endpoint dispatches IncidentCreated event', function () {
    Event::fake([IncidentCreated::class]);

    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'channel' => 'phone',
            'location_text' => 'City Hall, Butuan City',
        ])
        ->assertRedirect();

    Event::assertDispatched(IncidentCreated::class);
});

it('IncidentCreated event is dispatched with correct incident data', function () {
    Event::fake([IncidentCreated::class]);

    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($dispatcher)
        ->post(route('incidents.store'), [
            'incident_type_id' => $type->id,
            'priority' => 'P1',
            'channel' => 'radio',
            'location_text' => 'Agusan del Norte',
        ]);

    Event::assertDispatched(IncidentCreated::class, function (IncidentCreated $event) use ($type) {
        return $event->incident->incident_type_id === $type->id
            && $event->incident->priority->value === 'P1'
            && $event->incident->location_text === 'Agusan del Norte';
    });
});
