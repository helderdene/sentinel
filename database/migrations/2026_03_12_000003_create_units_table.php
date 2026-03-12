<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('callsign', 50);
            $table->string('type', 20);
            $table->string('agency', 50);
            $table->integer('crew_capacity');
            $table->string('status', 20)->default('AVAILABLE');
            $table->geography('coordinates', subtype: 'point', srid: 4326)->nullable();
            $table->string('shift', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->spatialIndex('coordinates');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
        });

        Schema::dropIfExists('units');
    }
};
