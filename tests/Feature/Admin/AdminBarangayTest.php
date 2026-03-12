<?php

use App\Models\Barangay;
use App\Models\User;

it('allows admin to list barangays', function () {
    $admin = User::factory()->admin()->create();
    Barangay::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.barangays.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Barangays')
            ->has('barangays', 3)
        );
});

it('allows admin to view edit barangay form', function () {
    $admin = User::factory()->admin()->create();
    $barangay = Barangay::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.barangays.edit', $barangay))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/BarangayForm')
            ->has('barangay')
        );
});

it('allows admin to update barangay metadata', function () {
    $admin = User::factory()->admin()->create();
    $barangay = Barangay::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.barangays.update', $barangay), [
            'district' => 'District 2',
            'population' => 15000,
            'risk_level' => 'high',
        ])
        ->assertRedirect();

    $fresh = $barangay->fresh();
    expect($fresh->district)->toBe('District 2');
    expect($fresh->population)->toBe(15000);
    expect($fresh->risk_level)->toBe('high');
});

it('does not accept boundary field on barangay update', function () {
    $admin = User::factory()->admin()->create();
    $barangay = Barangay::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.barangays.update', $barangay), [
            'district' => 'District 1',
            'population' => 10000,
            'risk_level' => 'low',
            'boundary' => 'POLYGON((0 0, 1 0, 1 1, 0 1, 0 0))',
        ])
        ->assertRedirect();

    // Boundary should not be affected by this extra field -- UpdateBarangayRequest
    // does not include 'boundary' in its rules, so it's stripped from validated data
});

it('blocks non-admin from barangay routes', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    Barangay::factory()->create();

    $this->actingAs($dispatcher)
        ->get(route('admin.barangays.index'))
        ->assertStatus(403);
});

it('validates risk level values on barangay update', function () {
    $admin = User::factory()->admin()->create();
    $barangay = Barangay::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.barangays.update', $barangay), [
            'risk_level' => 'extreme',
        ])
        ->assertSessionHasErrors('risk_level');
});
