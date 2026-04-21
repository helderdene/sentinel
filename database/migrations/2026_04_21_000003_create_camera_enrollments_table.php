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
        Schema::create('camera_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('camera_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('personnel_id')->constrained('personnel')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('enrolled_at', precision: 0)->nullable();
            $table->string('photo_hash', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['camera_id', 'personnel_id']);
            $table->index(['camera_id', 'status']);
            $table->index(['personnel_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE camera_enrollments ADD CONSTRAINT camera_enrollments_status_check '
            ."CHECK (status IN ('pending','syncing','done','failed'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camera_enrollments');
    }
};
