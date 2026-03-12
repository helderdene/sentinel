<?php

use App\Models\Barangay;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Database\Seeders\BarangaySeeder;
use Illuminate\Support\Facades\DB;

it('seeds 86 barangays from GeoJSON', function () {
    $this->seed(BarangaySeeder::class);

    expect(Barangay::count())->toBe(86);
});

it('identifies correct barangay from point-in-polygon query', function () {
    $this->seed(BarangaySeeder::class);

    $result = DB::select('
        SELECT name FROM barangays
        WHERE ST_Contains(boundary::geometry, ST_SetSRID(ST_MakePoint(125.5599, 8.9607), 4326)::geometry)
        LIMIT 1
    ');

    expect($result)->not->toBeEmpty();
    expect($result[0]->name)->toBe('AgaoPoblacion');
});

it('stores boundary as geography polygon retrievable via Magellan cast', function () {
    $this->seed(BarangaySeeder::class);

    $barangay = Barangay::where('name', 'AgaoPoblacion')->first();

    expect($barangay)->not->toBeNull();
    expect($barangay->boundary)->toBeInstanceOf(Polygon::class);
});
