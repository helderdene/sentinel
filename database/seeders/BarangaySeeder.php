<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Illuminate\Database\Seeder;

class BarangaySeeder extends Seeder
{
    /**
     * Seed the 86 Butuan City barangays from GeoJSON data.
     */
    public function run(): void
    {
        $path = base_path('docs/brgy.json');
        $raw = file_get_contents($path);
        $features = json_decode("[{$raw}]", true);

        if ($features === null) {
            $this->command->error('Failed to parse brgy.json');

            return;
        }

        $riskLevels = $this->getRiskLevels();
        $districts = $this->getDistricts();

        foreach ($features as $feature) {
            $name = $feature['properties']['NAME_3'] ?? 'Unknown';
            $geometry = $feature['geometry'];

            $polygon = $this->convertMultiPolygonToPolygon($geometry);

            if ($polygon === null) {
                $this->command->warn("Skipping {$name}: could not parse geometry");

                continue;
            }

            Barangay::updateOrCreate(
                ['name' => $name],
                [
                    'district' => $districts[$name] ?? null,
                    'city' => 'Butuan City',
                    'boundary' => $polygon,
                    'population' => null,
                    'risk_level' => $riskLevels[$name] ?? 'moderate',
                ]
            );
        }
    }

    /**
     * Convert a MultiPolygon GeoJSON geometry to a Magellan Polygon.
     * Uses the first polygon (outer ring) of the MultiPolygon.
     *
     * @param  array<string, mixed>  $geometry
     */
    private function convertMultiPolygonToPolygon(array $geometry): ?Polygon
    {
        if ($geometry['type'] !== 'MultiPolygon' || empty($geometry['coordinates'])) {
            return null;
        }

        $firstPolygonCoords = $geometry['coordinates'][0];

        $rings = [];
        foreach ($firstPolygonCoords as $ringCoords) {
            $points = [];
            foreach ($ringCoords as $coord) {
                $points[] = Point::makeGeodetic($coord[1], $coord[0]);
            }
            $rings[] = LineString::make($points);
        }

        return Polygon::make($rings, 4326);
    }

    /**
     * Get risk level mappings for known barangays.
     *
     * @return array<string, string>
     */
    private function getRiskLevels(): array
    {
        return [
            'BaanKm3' => 'high',
            'BaanRiversidePoblacion' => 'high',
            'Banza' => 'high',
            'Libertad' => 'high',
            'Lumbocan' => 'high',
            'Masao' => 'high',
            'Pinamanculan' => 'high',
            'Taligaman' => 'high',
            'Dagatan' => 'high',
            'Ambago' => 'high',
            'Ampayon' => 'low',
            'Bonbon' => 'low',
            'Anticala' => 'low',
            'Tungao' => 'low',
            'Bancasi' => 'low',
            'Bit-Os' => 'low',
        ];
    }

    /**
     * Get district assignments for known barangays.
     *
     * @return array<string, string>
     */
    private function getDistricts(): array
    {
        return [
            'AgaoPoblacion' => 'District 1',
            'BaanRiversidePoblacion' => 'District 1',
            'BadingPoblacion' => 'District 1',
            'BayanihanPoblacion' => 'District 1',
            'BuhanginPoblacion' => 'District 1',
            'DagohoyPoblacion' => 'District 1',
            'DiegoSilangPoblacion' => 'District 1',
            'GoldenRibbonPoblacion' => 'District 1',
            'HolyRedeemerPoblacion' => 'District 1',
            'HumabonPoblacion' => 'District 1',
            'ImadejasPoblacion' => 'District 1',
            'JoseRizalPoblacion' => 'District 1',
            'Lapu-lapuPoblacion' => 'District 1',
            'LeonKilatPoblacion' => 'District 1',
            'LimahaPoblacion' => 'District 1',
            'MaonPoblacion' => 'District 1',
            'NewSocietyVillagePoblacion' => 'District 1',
            'ObreroPoblacion' => 'District 1',
            'OngYiuPoblacion' => 'District 1',
            'PortPoyohonPoblacion' => 'District 1',
            'RajahSolimanPoblacion' => 'District 1',
            'SanIgnacioPoblacion' => 'District 1',
            'SikatunaPoblacion' => 'District 1',
            'SilonganPoblacion' => 'District 1',
            'TandangSoraPoblacion' => 'District 1',
            'UrdujaPoblacion' => 'District 1',
            'Ampayon' => 'District 2',
            'Anticala' => 'District 2',
            'Bancasi' => 'District 2',
            'BaanKm3' => 'District 2',
            'Banza' => 'District 2',
            'Bit-Os' => 'District 2',
            'Bonbon' => 'District 2',
            'Doongan' => 'District 2',
            'Libertad' => 'District 2',
            'Lumbocan' => 'District 2',
            'Masao' => 'District 2',
            'Pinamanculan' => 'District 2',
            'Taligaman' => 'District 2',
            'Tiniwisan' => 'District 2',
            'Tungao' => 'District 2',
            'VillaKananga' => 'District 2',
        ];
    }
}
