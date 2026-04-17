<?php

use App\Models\IncidentCategory;
use App\Models\IncidentType;
use App\Models\User;

it('allows admin to list incident categories', function () {
    $admin = User::factory()->admin()->create();
    IncidentCategory::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.incident-categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/IncidentCategories')
            ->has('categories', 3)
        );
});

it('allows admin to view create category form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.incident-categories.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/IncidentCategoryForm')
        );
});

it('allows admin to create incident category', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.incident-categories.store'), [
            'name' => 'Medical',
            'icon' => 'Heart',
            'description' => 'Medical emergencies',
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect(route('admin.incident-categories.index'));

    $this->assertDatabaseHas('incident_categories', [
        'name' => 'Medical',
        'icon' => 'Heart',
    ]);
});

it('allows admin to view edit category form', function () {
    $admin = User::factory()->admin()->create();
    $category = IncidentCategory::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.incident-categories.edit', $category))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/IncidentCategoryForm')
            ->has('category')
        );
});

it('allows admin to update incident category', function () {
    $admin = User::factory()->admin()->create();
    $category = IncidentCategory::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.incident-categories.update', $category), [
            'name' => 'Updated Name',
            'icon' => 'Flame',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($category->fresh())
        ->name->toBe('Updated Name')
        ->icon->toBe('Flame');
});

it('allows admin to disable incident category', function () {
    $admin = User::factory()->admin()->create();
    $category = IncidentCategory::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->delete(route('admin.incident-categories.destroy', $category))
        ->assertRedirect(route('admin.incident-categories.index'));

    expect($category->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('incident_categories', ['id' => $category->id]);
});

it('blocks non-admin from incident category routes', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('admin.incident-categories.index'))
        ->assertStatus(403);
});

it('validates unique name on category create', function () {
    $admin = User::factory()->admin()->create();
    IncidentCategory::factory()->create(['name' => 'Medical']);

    $this->actingAs($admin)
        ->post(route('admin.incident-categories.store'), [
            'name' => 'Medical',
            'icon' => 'Heart',
        ])
        ->assertSessionHasErrors('name');
});

it('allows keeping same name on category update', function () {
    $admin = User::factory()->admin()->create();
    $category = IncidentCategory::factory()->create(['name' => 'Medical']);

    $this->actingAs($admin)
        ->put(route('admin.incident-categories.update', $category), [
            'name' => 'Medical',
            'icon' => 'Flame',
            'is_active' => true,
        ])
        ->assertSessionDoesntHaveErrors('name');
});

it('includes incident types count in index', function () {
    $admin = User::factory()->admin()->create();
    $category = IncidentCategory::factory()->create();
    IncidentType::factory()->count(3)->create([
        'incident_category_id' => $category->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.incident-categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/IncidentCategories')
            ->where('categories.0.incident_types_count', 3)
        );
});
