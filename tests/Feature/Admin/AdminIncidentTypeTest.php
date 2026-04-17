<?php

use App\Models\IncidentCategory;
use App\Models\IncidentType;
use App\Models\User;

it('allows admin to list incident types', function () {
    $admin = User::factory()->admin()->create();
    IncidentType::factory()->count(5)->create();

    $this->actingAs($admin)
        ->get(route('admin.incident-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/IncidentTypes')
            ->has('types')
            ->has('categories')
        );
});

it('allows admin to view create incident type form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.incident-types.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/IncidentTypeForm')
            ->has('priorities')
            ->has('categories')
        );
});

it('allows admin to create incident type', function () {
    $admin = User::factory()->admin()->create();
    $category = IncidentCategory::factory()->create(['name' => 'Medical']);

    $this->actingAs($admin)
        ->post(route('admin.incident-types.store'), [
            'incident_category_id' => $category->id,
            'name' => 'Cardiac Arrest',
            'code' => 'MED-CA',
            'default_priority' => 'P1',
            'description' => 'Cardiac arrest emergency',
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect(route('admin.incident-types.index'));

    $this->assertDatabaseHas('incident_types', [
        'code' => 'MED-CA',
        'name' => 'Cardiac Arrest',
        'incident_category_id' => $category->id,
        'category' => 'Medical',
    ]);
});

it('allows admin to update incident type', function () {
    $admin = User::factory()->admin()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.incident-types.update', $type), [
            'incident_category_id' => $type->incident_category_id,
            'name' => 'Updated Name',
            'code' => $type->code,
            'default_priority' => 'P2',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($type->fresh()->name)->toBe('Updated Name');
});

it('allows admin to disable incident type', function () {
    $admin = User::factory()->admin()->create();
    $type = IncidentType::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->put(route('admin.incident-types.update', $type), [
            'incident_category_id' => $type->incident_category_id,
            'name' => $type->name,
            'code' => $type->code,
            'default_priority' => $type->default_priority,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($type->fresh()->is_active)->toBeFalse();
});

it('soft-disables incident type on destroy instead of deleting', function () {
    $admin = User::factory()->admin()->create();
    $type = IncidentType::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->delete(route('admin.incident-types.destroy', $type))
        ->assertRedirect(route('admin.incident-types.index'));

    expect($type->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('incident_types', ['id' => $type->id]);
});

it('blocks non-admin from incident type routes', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('admin.incident-types.index'))
        ->assertStatus(403);
});

it('validates unique code on incident type create', function () {
    $admin = User::factory()->admin()->create();
    IncidentType::factory()->create(['code' => 'TAKEN']);

    $this->actingAs($admin)
        ->post(route('admin.incident-types.store'), [
            'incident_category_id' => IncidentCategory::factory()->create()->id,
            'name' => 'Test Type',
            'code' => 'TAKEN',
            'default_priority' => 'P2',
        ])
        ->assertSessionHasErrors('code');
});

it('allows keeping same code on incident type update', function () {
    $admin = User::factory()->admin()->create();
    $type = IncidentType::factory()->create(['code' => 'MYCODE']);

    $this->actingAs($admin)
        ->put(route('admin.incident-types.update', $type), [
            'incident_category_id' => $type->incident_category_id,
            'name' => $type->name,
            'code' => 'MYCODE',
            'default_priority' => $type->default_priority,
            'is_active' => true,
        ])
        ->assertSessionDoesntHaveErrors('code');
});
