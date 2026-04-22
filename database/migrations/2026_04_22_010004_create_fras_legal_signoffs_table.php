<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 22 Wave 1 (D-38): Legal / DPO sign-off records. One row per
     * approval stating who approved FRAS operation at CDRRMO and when.
     * Append-only; references are by name + contact (no FK to users — the
     * signer is often external counsel / DPO).
     */
    public function up(): void
    {
        Schema::create('fras_legal_signoffs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('signed_by_name', 150);
            $table->string('contact', 150);
            $table->timestampTz('signed_at', precision: 0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fras_legal_signoffs');
    }
};
