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
        Schema::create('incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('incident_no', 20)->unique();
            $table->foreignId('incident_type_id')->constrained();
            $table->string('priority', 2);
            $table->string('status', 30)->default('PENDING');
            $table->string('channel', 20);
            $table->text('location_text')->nullable();
            $table->geography('coordinates', subtype: 'point', srid: 4326)->nullable();
            $table->foreignId('barangay_id')->nullable()->constrained();
            $table->string('caller_name', 100)->nullable();
            $table->string('caller_contact', 30)->nullable();
            $table->text('raw_message')->nullable();
            $table->text('notes')->nullable();
            $table->string('assigned_unit', 20)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('on_scene_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('outcome', 50)->nullable();
            $table->string('hospital', 100)->nullable();
            $table->integer('scene_time_sec')->nullable();
            $table->smallInteger('checklist_pct')->nullable();
            $table->jsonb('vitals')->nullable();
            $table->text('assessment_tags')->nullable();
            $table->text('closure_notes')->nullable();
            $table->string('report_pdf_url', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->spatialIndex('coordinates');
            $table->index('priority');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
