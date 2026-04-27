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
        Schema::create('incident_outcomes', function (Blueprint $table) {
            $table->id();
            // Code stored on incidents.outcome — kept stable so historical
            // rows survive the enum-to-DB swap. Matches the value previously
            // produced by IncidentOutcome::Foo->value.
            $table->string('code', 50)->unique();
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            // List of incident_types.category strings this outcome applies
            // to (e.g. ["Medical","Fire"]). Empty + is_universal=true means
            // it applies to every category. Filtering happens in
            // IncidentOutcome::forCategory() at the model layer.
            $table->json('applicable_categories')->nullable();
            $table->boolean('is_universal')->default(false);
            $table->boolean('requires_vitals')->default(false);
            $table->boolean('requires_hospital')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_outcomes');
    }
};
