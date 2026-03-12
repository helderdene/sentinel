<?php

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Unit;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;

it('creates unit with string primary key', function () {
    $unit = Unit::factory()->create(['id' => 'AMB-TEST-01']);

    expect($unit->id)->toBe('AMB-TEST-01');
});

it('stores geography point coordinates', function () {
    $unit = Unit::factory()->create();

    $fresh = Unit::find($unit->id);
    expect($fresh->coordinates)->toBeInstanceOf(Point::class);
});

it('has many users (responders) relationship', function () {
    $unit = Unit::factory()->create();
    User::factory()->responder()->count(2)->create(['unit_id' => $unit->id]);

    expect($unit->users)->toHaveCount(2);
});

it('casts status to UnitStatus enum', function () {
    $unit = Unit::factory()->create();

    expect($unit->status)->toBeInstanceOf(UnitStatus::class);
});

it('casts type to UnitType enum', function () {
    $unit = Unit::factory()->create();

    expect($unit->type)->toBeInstanceOf(UnitType::class);
});
