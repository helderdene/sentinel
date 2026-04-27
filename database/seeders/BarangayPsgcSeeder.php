<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;

class BarangayPsgcSeeder extends Seeder
{
    /**
     * Backfill PSGC codes onto existing Butuan City barangay rows by name match.
     *
     * Source: faeldon/philippines-json-maps (PSA PSGC Q4 2023).
     * The shipped GeoJSON at public/maps/butuan-barangays.geojson uses adm4_en
     * names like "Agao Pob.", while DB rows use concatenated names like
     * "AgaoPoblacion". Both sides are normalized to a common key for matching.
     */
    public function run(): void
    {
        $path = public_path('maps/butuan-barangays.geojson');

        if (! is_file($path)) {
            $this->command->error("Missing GeoJSON: {$path}");

            return;
        }

        $features = json_decode((string) file_get_contents($path), true)['features'] ?? [];

        $byKey = [];
        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            $byKey[$this->normalize((string) ($props['adm4_en'] ?? ''))] = (string) ($props['adm4_psgc'] ?? '');
        }

        $matched = 0;
        $unmatched = [];

        Barangay::query()->orderBy('name')->each(function (Barangay $barangay) use ($byKey, &$matched, &$unmatched): void {
            $key = $this->normalize($barangay->name);
            $code = $byKey[$key] ?? null;

            if ($code === null || $code === '') {
                $unmatched[] = $barangay->name;

                return;
            }

            $barangay->psgc_code = $code;
            $barangay->save();
            $matched++;
        });

        $this->command->info("Backfilled psgc_code on {$matched} barangays.");

        if ($unmatched !== []) {
            $this->command->warn('Unmatched: '.implode(', ', $unmatched));
        }
    }

    /**
     * Collapse name variations to a comparable key.
     */
    private function normalize(string $name): string
    {
        $name = mb_strtolower($name);
        $name = str_replace('ñ', 'n', $name);
        $name = str_replace('poblacion', 'pob', $name);

        return (string) preg_replace('/[^a-z0-9]/', '', $name);
    }
}
