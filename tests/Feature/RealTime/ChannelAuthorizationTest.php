<?php

use App\Models\User;

it('dispatcher can subscribe to dispatch.incidents channel', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-dispatch.incidents',
        ])
        ->assertSuccessful();
});

it('supervisor can subscribe to dispatch.incidents channel', function () {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-dispatch.incidents',
        ])
        ->assertSuccessful();
});

it('admin can subscribe to dispatch.incidents channel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-dispatch.incidents',
        ])
        ->assertSuccessful();
});

it('responder cannot subscribe to dispatch.incidents channel', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-dispatch.incidents',
        ])
        ->assertForbidden();
});

it('dispatcher can subscribe to dispatch.units channel', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $this->actingAs($dispatcher)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-dispatch.units',
        ])
        ->assertSuccessful();
});

it('responder cannot subscribe to dispatch.units channel', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-dispatch.units',
        ])
        ->assertForbidden();
});

it('user can subscribe to their own user.{id} channel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-user.{$user->id}",
        ])
        ->assertSuccessful();
});

it('user cannot subscribe to another user channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-user.{$other->id}",
        ])
        ->assertForbidden();
});

it('dispatcher can join presence-dispatch channel', function () {
    $dispatcher = User::factory()->dispatcher()->create();

    $response = $this->actingAs($dispatcher)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-dispatch',
        ])
        ->assertSuccessful();

    $data = $response->json();
    expect($data)->toHaveKey('channel_data');

    $channelData = json_decode($data['channel_data'], true);
    expect($channelData['user_info']['id'])->toBe($dispatcher->id);
    expect($channelData['user_info']['name'])->toBe($dispatcher->name);
    expect($channelData['user_info']['role'])->toBe('dispatcher');
});

it('responder cannot join presence-dispatch channel', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-dispatch',
        ])
        ->assertForbidden();
});
