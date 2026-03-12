<?php

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;

it('allows admin to list users', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/Users')
            ->has('users', 4)
            ->has('roles')
            ->has('units')
        );
});

it('allows admin to view create user form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/UserForm')
            ->has('roles')
            ->has('units')
        );
});

it('allows admin to create user with role in one request', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'dispatcher',
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'role' => 'dispatcher',
    ]);
});

it('allows creating responder with unit assignment', function () {
    $admin = User::factory()->admin()->create();
    $unit = Unit::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Responder User',
            'email' => 'responder@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'responder',
            'unit_id' => $unit->id,
        ])
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'responder@example.com',
        'role' => 'responder',
        'unit_id' => $unit->id,
    ]);
});

it('blocks non-admin users from admin routes', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->get(route('admin.users.index'))
        ->assertStatus(403);
});

it('allows admin to view edit user form', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/UserForm')
            ->has('user')
            ->has('roles')
            ->has('units')
        );
});

it('allows admin to update user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->dispatcher()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'supervisor',
        ])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('Updated Name');
    expect($user->fresh()->role)->toBe(UserRole::Supervisor);
});

it('allows admin to update user without changing password', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $originalPassword = $user->password;

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'dispatcher',
        ])
        ->assertRedirect();

    expect($user->fresh()->password)->toBe($originalPassword);
});

it('allows admin to delete user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('prevents admin from deleting themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('validates email uniqueness on user create', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'dispatcher',
        ])
        ->assertSessionHasErrors('email');
});

it('validates email uniqueness on user update allows own email', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['email' => 'myemail@example.com']);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Same Email',
            'email' => 'myemail@example.com',
            'role' => 'dispatcher',
        ])
        ->assertSessionDoesntHaveErrors('email');
});

it('requires password on user create', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'No Password',
            'email' => 'nopass@example.com',
            'role' => 'dispatcher',
        ])
        ->assertSessionHasErrors('password');
});

it('makes password optional on user update', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Updated',
            'email' => $user->email,
            'role' => 'dispatcher',
        ])
        ->assertSessionDoesntHaveErrors('password');
});
