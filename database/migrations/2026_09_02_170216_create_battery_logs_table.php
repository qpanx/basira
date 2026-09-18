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
        Schema::create('battery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->foreignId('blind_user_id')->constrained()->restrictOnDelete();
            $table->uuid('message_id');
            $table->unsignedTinyInteger('battery_level');
            $table->string('power_state', 32)->default('unknown');
            $table->dateTime('recorded_at', precision: 3);
            $table->dateTime('received_at', precision: 3);

            $table->unique(['device_id', 'message_id']);
            $table->index(['device_id', 'recorded_at', 'id']);
            $table->index(['blind_user_id', 'recorded_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('battery_logs');
    }
};
