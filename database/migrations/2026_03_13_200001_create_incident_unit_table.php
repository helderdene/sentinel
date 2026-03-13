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
        Schema::create('incident_unit', function (Blueprint $table) {
            $table->uuid('incident_id');
            $table->string('unit_id', 20);
            $table->timestamp('assigned_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();

            $table->primary(['incident_id', 'unit_id']);

            $table->foreign('incident_id')
                ->references('id')
                ->on('incidents')
                ->cascadeOnDelete();

            $table->foreign('unit_id')
                ->references('id')
                ->on('units')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_unit');
    }
};
