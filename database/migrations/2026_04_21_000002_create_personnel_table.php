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
        Schema::create('personnel', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('custom_id', 48)->unique()->nullable();
            $table->string('name', 100);
            $table->smallInteger('gender')->nullable();
            $table->date('birthday')->nullable();
            $table->string('id_card', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('photo_hash', 32)->nullable();
            $table->string('category', 20)->default('allow');
            $table->timestampTz('expires_at', precision: 0)->nullable();
            $table->text('consent_basis')->nullable();
            $table->timestampTz('decommissioned_at', precision: 0)->nullable();
            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE personnel ADD CONSTRAINT personnel_category_check '
            ."CHECK (category IN ('allow','block','missing','lost_child'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personnel');
    }
};
