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
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('district', 50)->nullable();
            $table->string('city', 50)->default('Butuan City');
            $table->geography('boundary', subtype: 'polygon', srid: 4326)->nullable();
            $table->integer('population')->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->timestamps();

            $table->spatialIndex('boundary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};
