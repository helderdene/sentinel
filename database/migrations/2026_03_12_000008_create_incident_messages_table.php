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
        Schema::create('incident_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('sender_type', 50);
            $table->unsignedBigInteger('sender_id');
            $table->text('body');
            $table->string('message_type', 20)->default('text');
            $table->boolean('is_quick_reply')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_messages');
    }
};
