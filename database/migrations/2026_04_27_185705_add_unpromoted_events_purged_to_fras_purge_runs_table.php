<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track how many recognition_events rows were row-deleted by the daily
     * fras:purge-expired sweep (events that never promoted to an Incident,
     * past the unpromoted retention window). Distinct from the existing
     * face/scene file purges, which only null image paths and keep the row.
     */
    public function up(): void
    {
        Schema::table('fras_purge_runs', function (Blueprint $table) {
            $table->unsignedInteger('unpromoted_events_purged')->default(0)->after('access_log_rows_purged');
        });
    }

    public function down(): void
    {
        Schema::table('fras_purge_runs', function (Blueprint $table) {
            $table->dropColumn('unpromoted_events_purged');
        });
    }
};
