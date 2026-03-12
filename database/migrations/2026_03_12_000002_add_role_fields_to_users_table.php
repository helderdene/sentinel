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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('dispatcher')->after('email');
            $table->string('unit_id', 20)->nullable()->after('role');
            $table->string('badge_number', 50)->nullable()->after('unit_id');
            $table->string('phone', 30)->nullable()->after('badge_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'unit_id', 'badge_number', 'phone']);
        });
    }
};
