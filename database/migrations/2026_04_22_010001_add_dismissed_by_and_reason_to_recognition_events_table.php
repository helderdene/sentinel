<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 22 Wave 1 (D-03): Extend recognition_events with the missing
     * dismiss metadata columns. Phase 18 already shipped the acknowledge /
     * dismiss timestamp columns plus the acknowledging-user FK; this
     * migration only adds dismissed_by + dismiss_reason + dismiss_reason_note
     * plus query indexes for the Phase 22 event-history feed.
     *
     * Does NOT re-declare any Phase 18 columns; see
     * 2026_04_21_000004_create_recognition_events_table for the baseline.
     */
    public function up(): void
    {
        Schema::table('recognition_events', function (Blueprint $table) {
            $table->foreignId('dismissed_by')
                ->nullable()
                ->after('dismissed_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('dismiss_reason', 32)->nullable()->after('dismissed_by');
            $table->text('dismiss_reason_note')->nullable()->after('dismiss_reason');

            $table->index('acknowledged_at');
            $table->index('dismissed_at');
        });

        DB::statement(
            'ALTER TABLE recognition_events ADD CONSTRAINT recognition_events_dismiss_reason_check '
            ."CHECK (dismiss_reason IS NULL OR dismiss_reason IN ('false_match','test_event','duplicate','other'))"
        );
    }

    /**
     * Reverse the migrations — drop the CHECK constraint first, then the
     * indexes, then the columns (reverse order of creation).
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE recognition_events DROP CONSTRAINT IF EXISTS recognition_events_dismiss_reason_check');

        Schema::table('recognition_events', function (Blueprint $table) {
            $table->dropIndex(['acknowledged_at']);
            $table->dropIndex(['dismissed_at']);
            $table->dropColumn('dismiss_reason_note');
            $table->dropColumn('dismiss_reason');
            $table->dropConstrainedForeignId('dismissed_by');
        });
    }
};
