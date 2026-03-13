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
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('tracking_token', 8)->nullable()->unique()->after('incident_no');
            $table->index('tracking_token');
        });

        Schema::table('incident_types', function (Blueprint $table) {
            $table->boolean('show_in_public_app')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['tracking_token']);
            $table->dropColumn('tracking_token');
        });

        Schema::table('incident_types', function (Blueprint $table) {
            $table->dropColumn('show_in_public_app');
        });
    }
};
