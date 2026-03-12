<?php

use App\Models\User;

it('grants triage-incidents to operator, supervisor, admin', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('triage-incidents'))->toBeTrue();
})->with(['operator', 'supervisor', 'admin']);

it('denies triage-incidents to dispatcher, responder', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('triage-incidents'))->toBeFalse();
})->with(['dispatcher', 'responder']);

it('grants manual-entry to operator, supervisor, admin', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('manual-entry'))->toBeTrue();
})->with(['operator', 'supervisor', 'admin']);

it('denies manual-entry to dispatcher, responder', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('manual-entry'))->toBeFalse();
})->with(['dispatcher', 'responder']);

it('grants submit-dispatch to operator, supervisor, admin', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('submit-dispatch'))->toBeTrue();
})->with(['operator', 'supervisor', 'admin']);

it('denies submit-dispatch to dispatcher, responder', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('submit-dispatch'))->toBeFalse();
})->with(['dispatcher', 'responder']);

it('grants override-priority to supervisor, admin only', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('override-priority'))->toBeTrue();
})->with(['supervisor', 'admin']);

it('denies override-priority to operator, dispatcher, responder', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('override-priority'))->toBeFalse();
})->with(['operator', 'dispatcher', 'responder']);

it('grants recall-incident to supervisor, admin only', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('recall-incident'))->toBeTrue();
})->with(['supervisor', 'admin']);

it('denies recall-incident to operator, dispatcher, responder', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('recall-incident'))->toBeFalse();
})->with(['operator', 'dispatcher', 'responder']);

it('grants view-session-log to supervisor, admin only', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('view-session-log'))->toBeTrue();
})->with(['supervisor', 'admin']);

it('denies view-session-log to operator, dispatcher, responder', function (string $factoryState) {
    $user = User::factory()->{$factoryState}()->create();

    expect($user->can('view-session-log'))->toBeFalse();
})->with(['operator', 'dispatcher', 'responder']);

it('grants create-incidents to operator', function () {
    $user = User::factory()->operator()->create();

    expect($user->can('create-incidents'))->toBeTrue();
});

it('allows operator to subscribe to dispatch.incidents channel', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-dispatch.incidents',
        ])
        ->assertSuccessful();
});
