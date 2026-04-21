<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_id', 64)->unique();
            $table->string('camera_id_display', 10)->unique()->nullable();
            $table->string('name', 100);
            $table->string('location_label', 150);
            $table->geography('location', subtype: 'point', srid: 4326)->nullable();
            $table->string('status', 20)->default('offline');
            $table->timestampTz('last_seen_at', precision: 0)->nullable();
            $table->timestampTz('decommissioned_at', precision: 0)->nullable();
            $table->timestamps();

            $table->spatialIndex('location');
        });

        DB::statement(
            'ALTER TABLE cameras ADD CONSTRAINT cameras_status_check '
            ."CHECK (status IN ('online','offline','degraded'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cameras');
    }
};
