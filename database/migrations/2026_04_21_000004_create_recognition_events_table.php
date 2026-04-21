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
        Schema::create('recognition_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('camera_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('personnel_id')->nullable()->constrained('personnel')->nullOnDelete();
            $table->foreignUuid('incident_id')->nullable()->constrained()->nullOnDelete();

            $table->bigInteger('record_id');
            $table->string('custom_id', 100)->nullable();
            $table->string('camera_person_id', 100)->nullable();
            $table->smallInteger('verify_status');
            $table->smallInteger('person_type');
            $table->decimal('similarity', 5, 2);
            $table->boolean('is_real_time');

            $table->string('name_from_camera', 100)->nullable();
            $table->string('facesluice_id', 100)->nullable();
            $table->string('id_card', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->smallInteger('is_no_mask');

            $table->jsonb('target_bbox')->nullable();

            $table->timestampTz('captured_at', precision: 6);
            $table->timestampTz('received_at', precision: 6);

            $table->string('face_image_path', 255)->nullable();
            $table->string('scene_image_path', 255)->nullable();

            $table->jsonb('raw_payload');

            $table->string('severity', 10)->default('info');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('acknowledged_at', precision: 0)->nullable();
            $table->timestampTz('dismissed_at', precision: 0)->nullable();

            $table->timestamps();

            $table->unique(['camera_id', 'record_id']);

            $table->index(['camera_id', 'captured_at']);
            $table->index(['person_type', 'verify_status']);
            $table->index('severity');
            $table->index(['is_real_time', 'severity']);
            $table->index('incident_id');
        });

        DB::statement(
            'ALTER TABLE recognition_events ADD CONSTRAINT recognition_events_severity_check '
            ."CHECK (severity IN ('info','warning','critical'))"
        );

        DB::statement(
            'CREATE INDEX recognition_events_raw_payload_gin_idx '
            .'ON recognition_events USING GIN (raw_payload jsonb_path_ops)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recognition_events');
    }
};
