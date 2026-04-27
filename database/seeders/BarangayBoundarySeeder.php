<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Illuminate\Database\Seeder;

class BarangayBoundarySeeder extends Seeder
{
    /**
     * Re-seed barangay boundaries from PSA PSGC Q4 2023 (faeldon/philippines-json-maps).
     *
     * Source: docs/butuan-barangays-hires.geojson (10% simplification, all single Polygons).
     * Joined by psgc_code, which BarangayPsgcSeeder must populate first.
     */
    public function run(): void
    {
        $path = base_path('docs/butuan-barangays-hires.geojson');

        if (! is_file($path)) {
            $this->command->error("Missing GeoJSON: {$path}");

            return;
        }

        $features = json_decode((string) file_get_contents($path), true)['features'] ?? [];

        $updated = 0;
        $unmatched = [];

        foreach ($features as $feature) {
            $code = (string) ($feature['properties']['adm4_psgc'] ?? '');
            $polygon = $this->buildPolygon($feature['geometry'] ?? []);

            if ($code === '' || $polygon === null) {
                $unmatched[] = $feature['properties']['adm4_en'] ?? '(unknown)';

                continue;
            }

            $affected = Barangay::query()
                ->where('psgc_code', $code)
                ->update(['boundary' => $polygon]);

            if ($affected === 0) {
                $unmatched[] = $feature['properties']['adm4_en'].' (psgc='.$code.')';

                continue;
            }

            $updated += $affected;
        }

        $this->command->info("Updated boundary on {$updated} barangays.");

        if ($unmatched !== []) {
            $this->command->warn('Unmatched: '.implode(', ', $unmatched));
        }
    }

    /**
     * Convert a GeoJSON Polygon geometry to a Magellan Polygon (SRID 4326).
     *
     * @param  array<string, mixed>  $geometry
     */
    private function buildPolygon(array $geometry): ?Polygon
    {
        if (($geometry['type'] ?? null) !== 'Polygon' || empty($geometry['coordinates'])) {
            return null;
        }

        $rings = [];
        foreach ($geometry['coordinates'] as $ring) {
            $points = [];
            foreach ($ring as $coord) {
                $points[] = Point::makeGeodetic($coord[1], $coord[0]);
            }
            $rings[] = LineString::make($points);
        }

        return Polygon::make($rings, 4326);
    }
}
