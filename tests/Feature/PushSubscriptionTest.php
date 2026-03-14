<?php

use App\Models\User;

it('stores a push subscription for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('push-subscriptions.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-store-endpoint',
            'public_key' => 'BNg4M6K5gW4e5aYtZ0VwN_jKj3hQz3NXWCF7mE8kPl1OJWjS3U4XE_IjFxL5oQPCxGfDmH7GrjAjy4bDWzFNHw',
            'auth_token' => 'dGVzdC1hdXRoLXRva2Vu',
            'content_encoding' => 'aesgcm',
        ])
        ->assertCreated()
        ->assertJson(['message' => 'Subscription saved.']);

    $this->assertDatabaseHas('push_subscriptions', [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-store-endpoint',
    ]);
});

it('rejects push subscription store without authentication', function () {
    $this->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-unauth',
        'public_key' => 'test-key',
        'auth_token' => 'test-token',
    ])
        ->assertUnauthorized();
});

it('deletes a push subscription for authenticated user', function () {
    $user = User::factory()->create();
    $endpoint = 'https://fcm.googleapis.com/fcm/send/test-delete-endpoint';

    $user->updatePushSubscription($endpoint, 'key', 'token', 'aesgcm');

    $this->actingAs($user)
        ->deleteJson(route('push-subscriptions.destroy'), [
            'endpoint' => $endpoint,
        ])
        ->assertNoContent();

    $this->assertDatabaseMissing('push_subscriptions', [
        'endpoint' => $endpoint,
    ]);
});

it('validates required fields for push subscription store', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('push-subscriptions.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint']);
});

it('validates endpoint must be a url for push subscription store', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('push-subscriptions.store'), [
            'endpoint' => 'not-a-url',
            'public_key' => 'key',
            'auth_token' => 'token',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint']);
});
