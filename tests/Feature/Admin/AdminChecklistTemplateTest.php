<?php

use App\Models\ChecklistTemplate;
use App\Models\IncidentType;
use App\Models\User;

it('allows admin to list checklist templates', function () {
    $admin = User::factory()->admin()->create();
    ChecklistTemplate::query()->create([
        'name' => 'Example',
        'slug' => 'example',
        'description' => null,
        'items' => [['key' => 'a', 'label' => 'A']],
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.checklist-templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/ChecklistTemplates')
            ->has('templates', 1)
        );
});

it('allows admin to view create form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.checklist-templates.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/ChecklistTemplateForm')
        );
});

it('allows admin to create a checklist template', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.checklist-templates.store'), [
            'name' => 'Water Rescue',
            'slug' => 'water_rescue',
            'description' => 'Protocol for water rescues',
            'is_default' => false,
            'is_active' => true,
            'items' => [
                ['key' => 'scene_secured', 'label' => 'Scene secured'],
                ['key' => 'pfd_deployed', 'label' => 'PFD deployed'],
            ],
        ])
        ->assertRedirect(route('admin.checklist-templates.index'));

    $this->assertDatabaseHas('checklist_templates', [
        'slug' => 'water_rescue',
        'name' => 'Water Rescue',
    ]);
});

it('allows admin to update a checklist template', function () {
    $admin = User::factory()->admin()->create();
    $template = ChecklistTemplate::query()->create([
        'name' => 'Old Name',
        'slug' => 'old',
        'description' => null,
        'items' => [['key' => 'x', 'label' => 'X']],
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.checklist-templates.update', $template), [
            'name' => 'New Name',
            'slug' => 'old',
            'is_default' => false,
            'is_active' => true,
            'items' => [
                ['key' => 'y', 'label' => 'Y'],
                ['key' => 'z', 'label' => 'Z'],
            ],
        ])
        ->assertRedirect(route('admin.checklist-templates.index'));

    $fresh = $template->fresh();
    expect($fresh->name)->toBe('New Name');
    expect($fresh->items)->toHaveCount(2);
});

it('enforces single default template on create', function () {
    $admin = User::factory()->admin()->create();
    $existing = ChecklistTemplate::query()->create([
        'name' => 'Old Default',
        'slug' => 'old_default',
        'items' => [['key' => 'a', 'label' => 'A']],
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.checklist-templates.store'), [
            'name' => 'New Default',
            'slug' => 'new_default',
            'is_default' => true,
            'is_active' => true,
            'items' => [['key' => 'a', 'label' => 'A']],
        ])
        ->assertRedirect();

    expect($existing->fresh()->is_default)->toBeFalse();
    expect(ChecklistTemplate::where('is_default', true)->count())->toBe(1);
});

it('soft-disables non-default template on destroy', function () {
    $admin = User::factory()->admin()->create();
    $template = ChecklistTemplate::query()->create([
        'name' => 'Disposable',
        'slug' => 'disposable',
        'items' => [['key' => 'a', 'label' => 'A']],
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.checklist-templates.destroy', $template))
        ->assertRedirect(route('admin.checklist-templates.index'));

    expect($template->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('checklist_templates', ['id' => $template->id]);
});

it('refuses to disable the default template', function () {
    $admin = User::factory()->admin()->create();
    $template = ChecklistTemplate::query()->create([
        'name' => 'Fallback',
        'slug' => 'fallback',
        'items' => [['key' => 'a', 'label' => 'A']],
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.checklist-templates.destroy', $template))
        ->assertRedirect();

    expect($template->fresh()->is_active)->toBeTrue();
});

it('validates required fields on create', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.checklist-templates.store'), [
            'name' => '',
            'slug' => '',
            'items' => [],
        ])
        ->assertSessionHasErrors(['name', 'slug', 'items']);
});

it('validates unique slug on create', function () {
    $admin = User::factory()->admin()->create();
    ChecklistTemplate::query()->create([
        'name' => 'Existing',
        'slug' => 'taken',
        'items' => [['key' => 'a', 'label' => 'A']],
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.checklist-templates.store'), [
            'name' => 'New',
            'slug' => 'taken',
            'is_default' => false,
            'is_active' => true,
            'items' => [['key' => 'a', 'label' => 'A']],
        ])
        ->assertSessionHasErrors('slug');
});

it('blocks non-admin from checklist template routes', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('admin.checklist-templates.index'))
        ->assertStatus(403);
});

it('exposes checklist template via incident type relation', function () {
    $template = ChecklistTemplate::query()->create([
        'name' => 'Cardiac',
        'slug' => 'cardiac_test',
        'items' => [['key' => 'abc', 'label' => 'ABC']],
        'is_default' => false,
        'is_active' => true,
    ]);

    $type = IncidentType::factory()->create([
        'checklist_template_id' => $template->id,
    ]);

    expect($type->checklistTemplate->slug)->toBe('cardiac_test');
});
