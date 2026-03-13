<?php

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Unit;
use App\Models\User;

it('allows admin to list units (UNIT-01)', function () {
    $admin = User::factory()->admin()->create();
    Unit::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.units.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Units')
            ->has('units', 3)
            ->has('types')
            ->has('statuses')
            ->has('responders')
        );
});

it('allows admin to create unit with auto-generated ID (UNIT-02)', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.units.store'), [
            'type' => 'ambulance',
            'agency' => 'CDRRMO',
            'crew_capacity' => 4,
            'status' => 'AVAILABLE',
        ])
        ->assertRedirect(route('admin.units.index'));

    $this->assertDatabaseHas('units', [
        'id' => 'AMB-01',
        'type' => 'ambulance',
        'agency' => 'CDRRMO',
    ]);
});

it('allows admin to update unit (UNIT-03)', function () {
    $admin = User::factory()->admin()->create();
    $unit = Unit::factory()->create(['id' => 'AMB-01', 'type' => UnitType::Ambulance]);

    $this->actingAs($admin)
        ->put(route('admin.units.update', $unit), [
            'callsign' => 'Alpha One',
            'agency' => 'BFP',
            'crew_capacity' => 6,
            'status' => 'OFFLINE',
            'shift' => 'night',
        ])
        ->assertRedirect();

    $unit->refresh();
    expect($unit->callsign)->toBe('Alpha One');
    expect($unit->agency)->toBe('BFP');
    expect($unit->crew_capacity)->toBe(6);
    expect($unit->status)->toBe(UnitStatus::Offline);
    expect($unit->shift)->toBe('night');
});

it('allows admin to decommission unit (UNIT-04)', function () {
    $admin = User::factory()->admin()->create();
    $unit = Unit::factory()->create(['id' => 'AMB-01', 'type' => UnitType::Ambulance]);
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);

    $this->actingAs($admin)
        ->delete(route('admin.units.destroy', $unit))
        ->assertRedirect();

    $unit->refresh();
    expect($unit->decommissioned_at)->not->toBeNull();
    expect($responder->fresh()->unit_id)->toBeNull();
});

it('allows admin to recommission unit (UNIT-05)', function () {
    $admin = User::factory()->admin()->create();
    $unit = Unit::factory()->create([
        'id' => 'AMB-01',
        'type' => UnitType::Ambulance,
        'status' => UnitStatus::Offline,
        'decommissioned_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.units.recommission', $unit))
        ->assertRedirect();

    $unit->refresh();
    expect($unit->decommissioned_at)->toBeNull();
    expect($unit->status)->toBe(UnitStatus::Available);
});

it('syncs crew_ids bidirectionally on store and update (UNIT-06)', function () {
    $admin = User::factory()->admin()->create();
    $r1 = User::factory()->responder()->create();
    $r2 = User::factory()->responder()->create();
    $r3 = User::factory()->responder()->create();

    // Create unit with crew
    $this->actingAs($admin)
        ->post(route('admin.units.store'), [
            'type' => 'fire',
            'agency' => 'BFP',
            'crew_capacity' => 4,
            'status' => 'AVAILABLE',
            'crew_ids' => [$r1->id, $r2->id],
        ])
        ->assertRedirect();

    $unit = Unit::where('id', 'FIRE-01')->first();
    expect($r1->fresh()->unit_id)->toBe($unit->id);
    expect($r2->fresh()->unit_id)->toBe($unit->id);

    // Update crew: remove r1, add r3
    $this->actingAs($admin)
        ->put(route('admin.units.update', $unit), [
            'agency' => 'BFP',
            'crew_capacity' => 4,
            'status' => 'AVAILABLE',
            'crew_ids' => [$r2->id, $r3->id],
        ])
        ->assertRedirect();

    expect($r1->fresh()->unit_id)->toBeNull();
    expect($r2->fresh()->unit_id)->toBe($unit->id);
    expect($r3->fresh()->unit_id)->toBe($unit->id);
});

it('blocks non-admin users from admin unit routes (UNIT-07)', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('admin.units.index'))
        ->assertForbidden();
});

it('generates sequential IDs for same type (UNIT-08)', function () {
    $admin = User::factory()->admin()->create();

    // Create first ambulance
    $this->actingAs($admin)
        ->post(route('admin.units.store'), [
            'type' => 'ambulance',
            'agency' => 'CDRRMO',
            'crew_capacity' => 4,
            'status' => 'AVAILABLE',
        ]);

    // Create second ambulance
    $this->actingAs($admin)
        ->post(route('admin.units.store'), [
            'type' => 'ambulance',
            'agency' => 'CDRRMO',
            'crew_capacity' => 4,
            'status' => 'AVAILABLE',
        ]);

    $this->assertDatabaseHas('units', ['id' => 'AMB-01']);
    $this->assertDatabaseHas('units', ['id' => 'AMB-02']);
});

it('rejects invalid status values for admin operations (UNIT-09)', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.units.store'), [
            'type' => 'ambulance',
            'agency' => 'CDRRMO',
            'crew_capacity' => 4,
            'status' => 'EN_ROUTE',
        ])
        ->assertSessionHasErrors('status');
});

it('prevents decommissioning unit with active incidents', function () {
    $admin = User::factory()->admin()->create();
    $unit = Unit::factory()->create(['id' => 'AMB-01', 'type' => UnitType::Ambulance]);

    // Create an active incident assigned to this unit
    $incident = \App\Models\Incident::factory()->create([
        'status' => \App\Enums\IncidentStatus::Dispatched,
    ]);
    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.units.destroy', $unit))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($unit->fresh()->decommissioned_at)->toBeNull();
});
