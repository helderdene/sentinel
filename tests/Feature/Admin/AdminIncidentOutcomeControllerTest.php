<?php

use App\Models\IncidentOutcome;
use App\Models\User;
use Database\Seeders\IncidentOutcomeSeeder;

beforeEach(function () {
    $this->seed(IncidentOutcomeSeeder::class);
});

it('allows admin to list incident outcomes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.incident-outcomes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/IncidentOutcomes')
            ->has('outcomes', 11)
            ->has('categories')
        );
});

it('allows admin to view the create form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.incident-outcomes.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/IncidentOutcomeForm')
            ->has('categories')
            ->missing('outcome')
        );
});

it('allows admin to create a new incident outcome', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.incident-outcomes.store'), [
            'code' => 'EVACUATED',
            'label' => 'Evacuated to Shelter',
            'description' => 'Civilians evacuated to a designated shelter.',
            'applicable_categories' => ['Natural Disaster'],
            'is_universal' => false,
            'requires_vitals' => false,
            'requires_hospital' => false,
            'sort_order' => 200,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.incident-outcomes.index'));

    $this->assertDatabaseHas('incident_outcomes', [
        'code' => 'EVACUATED',
        'label' => 'Evacuated to Shelter',
    ]);
});

it('rejects lowercase or invalid codes on store', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.incident-outcomes.store'), [
            'code' => 'evacuated',
            'label' => 'X',
        ])
        ->assertSessionHasErrors('code');
});

it('rejects duplicate codes on store', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.incident-outcomes.store'), [
            'code' => 'FALSE_ALARM',
            'label' => 'Dup',
        ])
        ->assertSessionHasErrors('code');
});

it('allows admin to update an incident outcome', function () {
    $admin = User::factory()->admin()->create();
    $outcome = IncidentOutcome::query()->where('code', 'SUBJECT_FLED')->first();

    $this->actingAs($admin)
        ->put(route('admin.incident-outcomes.update', $outcome->id), [
            'code' => 'SUBJECT_FLED',
            'label' => 'Subject Fled the Scene',
            'description' => $outcome->description,
            'applicable_categories' => ['Crime / Security'],
            'is_universal' => false,
            'requires_vitals' => false,
            'requires_hospital' => false,
            'sort_order' => $outcome->sort_order,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.incident-outcomes.index'));

    expect($outcome->fresh()->label)->toBe('Subject Fled the Scene');
});

it('soft-disables an outcome via destroy without removing the row', function () {
    $admin = User::factory()->admin()->create();
    $outcome = IncidentOutcome::query()->where('code', 'MISMATCH')->first();

    $this->actingAs($admin)
        ->delete(route('admin.incident-outcomes.destroy', $outcome->id))
        ->assertRedirect(route('admin.incident-outcomes.index'));

    expect($outcome->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('incident_outcomes', ['id' => $outcome->id]);
});

it('blocks responder role from accessing the admin index', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->get(route('admin.incident-outcomes.index'))
        ->assertForbidden();
});
