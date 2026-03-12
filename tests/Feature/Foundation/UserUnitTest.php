<?php

use App\Enums\UserRole;
use App\Models\Unit;
use App\Models\User;

it('creates users with each of the four roles via factory states', function () {
    $admin = User::factory()->admin()->create();
    $dispatcher = User::factory()->dispatcher()->create();
    $responder = User::factory()->responder()->create();
    $supervisor = User::factory()->supervisor()->create();

    expect($admin->role)->toBe(UserRole::Admin);
    expect($dispatcher->role)->toBe(UserRole::Dispatcher);
    expect($responder->role)->toBe(UserRole::Responder);
    expect($supervisor->role)->toBe(UserRole::Supervisor);
});

it('has responder linked to a unit via belongsTo relationship', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);

    expect($responder->unit)->toBeInstanceOf(Unit::class);
    expect($responder->unit->id)->toBe($unit->id);
});

it('has non-responder roles with null unit_id by default', function () {
    $admin = User::factory()->admin()->create();
    $dispatcher = User::factory()->dispatcher()->create();

    expect($admin->unit_id)->toBeNull();
    expect($dispatcher->unit_id)->toBeNull();
});

it('has role helper methods on User model', function () {
    $admin = User::factory()->admin()->create();
    $dispatcher = User::factory()->dispatcher()->create();
    $responder = User::factory()->responder()->create();
    $supervisor = User::factory()->supervisor()->create();

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isDispatcher())->toBeFalse();
    expect($dispatcher->isDispatcher())->toBeTrue();
    expect($responder->isResponder())->toBeTrue();
    expect($supervisor->isSupervisor())->toBeTrue();
});
