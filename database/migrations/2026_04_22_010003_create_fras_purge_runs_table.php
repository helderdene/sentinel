<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 22 Wave 1 (D-24): Retention sweep summary. Each scheduled purge
     * of expired face crops / scene images writes one row here with counts
     * per category plus any error summary. Append-only — rows live for
     * audit history.
     */
    public function up(): void
    {
        Schema::create('fras_purge_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('started_at', precision: 0);
            $table->timestampTz('finished_at', precision: 0)->nullable();
            $table->boolean('dry_run')->default(false);
            $table->unsignedInteger('face_crops_purged')->default(0);
            $table->unsignedInteger('scene_images_purged')->default(0);
            $table->unsignedInteger('skipped_for_active_incident')->default(0);
            $table->unsignedInteger('access_log_rows_purged')->default(0);
            $table->text('error_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fras_purge_runs');
    }
};
