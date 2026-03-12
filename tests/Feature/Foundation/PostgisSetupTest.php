<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('has PostGIS extension enabled', function () {
    $result = DB::select('SELECT PostGIS_Version()');

    expect($result)->not->toBeEmpty();
    expect($result[0]->postgis_version)->toBeString();
});

it('has all required tables', function () {
    $tables = [
        'users',
        'units',
        'barangays',
        'incident_types',
        'incidents',
        'incident_timeline',
        'incident_messages',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} should exist");
    }
});

it('has geography columns on spatial tables', function () {
    expect(Schema::hasColumn('incidents', 'coordinates'))->toBeTrue();
    expect(Schema::hasColumn('units', 'coordinates'))->toBeTrue();
    expect(Schema::hasColumn('barangays', 'boundary'))->toBeTrue();
});

it('has role fields on users table', function () {
    expect(Schema::hasColumn('users', 'role'))->toBeTrue();
    expect(Schema::hasColumn('users', 'unit_id'))->toBeTrue();
    expect(Schema::hasColumn('users', 'badge_number'))->toBeTrue();
    expect(Schema::hasColumn('users', 'phone'))->toBeTrue();
});

it('has Fortify registration disabled', function () {
    $response = $this->get('/register');

    $response->assertStatus(404);
});
