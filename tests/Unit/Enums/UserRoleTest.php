<?php

use App\Enums\UserRole;

it('has an Operator case with value operator', function () {
    $operator = UserRole::Operator;

    expect($operator->value)->toBe('operator');
});

it('has exactly 5 cases', function () {
    expect(UserRole::cases())->toHaveCount(5);
});

it('has cases in alphabetical order', function () {
    $cases = array_map(fn (UserRole $role) => $role->name, UserRole::cases());

    expect($cases)->toBe(['Admin', 'Dispatcher', 'Operator', 'Responder', 'Supervisor']);
});
