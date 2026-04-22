<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 22 Wave 1 (D-15): Append-only audit log for DPA-grade biometric
     * image access (recognition face crops, scene images, personnel photos).
     * UUID PK, timestamptz, composite indexes on (subject_type, subject_id)
     * and (actor_user_id, accessed_at). Polymorphic subject_id has NO FK —
     * integrity lives in the CHECK + enum layer.
     */
    public function up(): void
    {
        Schema::create('fras_access_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->ipAddress('ip_address');
            $table->string('user_agent', 255)->nullable();
            $table->string('subject_type', 48);
            $table->uuid('subject_id');
            $table->string('action', 16);
            $table->timestampTz('accessed_at', precision: 0)->index();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_user_id', 'accessed_at']);
        });

        DB::statement(
            'ALTER TABLE fras_access_log ADD CONSTRAINT fras_access_log_subject_type_check '
            ."CHECK (subject_type IN ('recognition_event_face','recognition_event_scene','personnel_photo'))"
        );
        DB::statement(
            'ALTER TABLE fras_access_log ADD CONSTRAINT fras_access_log_action_check '
            ."CHECK (action IN ('view','download'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('fras_access_log');
    }
};
