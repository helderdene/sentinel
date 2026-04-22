<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 22 Wave 1 (D-06): Per-user toggle muting the FRAS alert sound.
     * Defaults to false (sound on). Surfaced via HandleInertiaRequests to
     * auth.user.fras_audio_muted for the useFrasFeed composable.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('fras_audio_muted')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fras_audio_muted');
        });
    }
};
