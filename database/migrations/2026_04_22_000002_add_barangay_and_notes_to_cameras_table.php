<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 20 Plan 04 (D-14): AdminCameraController invokes
     * BarangayLookupService::findByCoordinates on store/update to derive
     * `barangay_id`. Phase 18's initial cameras migration omitted this
     * column + operator notes column; this additive migration closes the gap
     * without rewriting the schema-freeze 2026_04_21_000001 file.
     */
    public function up(): void
    {
        Schema::table('cameras', function (Blueprint $table) {
            $table->foreignId('barangay_id')
                ->nullable()
                ->after('location_label')
                ->constrained('barangays')
                ->nullOnDelete();

            $table->text('notes')->nullable()->after('decommissioned_at');

            // StoreCameraRequest marks location_label as `nullable` and the
            // Plan 07 Mapbox picker only emits it after reverseGeocode
            // resolves — which may fail or be skipped. The Phase 18 schema
            // shipped with NOT NULL; loosen here so the controller can
            // persist null without violating the constraint.
            $table->string('location_label', 150)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cameras', function (Blueprint $table) {
            $table->string('location_label', 150)->nullable(false)->change();
            $table->dropConstrainedForeignId('barangay_id');
            $table->dropColumn('notes');
        });
    }
};
