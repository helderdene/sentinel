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
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('title');
            $table->string('period', 30);
            $table->string('file_path');
            $table->string('csv_path')->nullable();
            $table->string('status', 20)->default('generating');
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['type', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
