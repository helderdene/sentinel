<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Testing\TestResponse;

pest()->group('fras');

/**
 * Issue a POST /broadcasting/auth attempt as a user of the given role against
 * the given channel name, returning the raw TestResponse so individual it()
 * blocks can assert on the status code.
 *
 * No `Citizen` role exists in UserRole — matrix is 5 cases (Admin, Supervisor,
 * Operator, Dispatcher, Responder) split across the two channels.
 */
function authAttempt(UserRole $role, string $channelName): TestResponse
{
    $user = User::factory()->create(['role' => $role]);

    return test()
        ->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => $channelName,
            'socket_id' => '1234.5678',
        ]);
}

describe('fras.cameras channel', function () {
    foreach ([UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin] as $allowedRole) {
        it("authorizes {$allowedRole->value} to subscribe to private-fras.cameras", function () use ($allowedRole) {
            $response = authAttempt($allowedRole, 'private-fras.cameras');
            expect($response->getStatusCode())->toBeIn([200, 201]);
        });
    }

    it('denies responder subscription to private-fras.cameras', function () {
        $response = authAttempt(UserRole::Responder, 'private-fras.cameras');
        expect($response->getStatusCode())->toBe(403);
    });
});

describe('fras.enrollments channel', function () {
    foreach ([UserRole::Supervisor, UserRole::Admin] as $allowedRole) {
        it("authorizes {$allowedRole->value} to subscribe to private-fras.enrollments", function () use ($allowedRole) {
            $response = authAttempt($allowedRole, 'private-fras.enrollments');
            expect($response->getStatusCode())->toBeIn([200, 201]);
        });
    }

    foreach ([UserRole::Operator, UserRole::Dispatcher, UserRole::Responder] as $deniedRole) {
        it("denies {$deniedRole->value} subscription to private-fras.enrollments", function () use ($deniedRole) {
            $response = authAttempt($deniedRole, 'private-fras.enrollments');
            expect($response->getStatusCode())->toBe(403);
        });
    }
});
