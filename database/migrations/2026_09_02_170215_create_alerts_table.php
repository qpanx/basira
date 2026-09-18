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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->foreignId('blind_user_id')->constrained()->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('message_id');
            $table->string('type', 32);
            $table->string('severity', 32);
            $table->unsignedSmallInteger('distance_cm')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->dateTime('occurred_at', precision: 3)->nullable();
            $table->string('resolution', 32)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->dateTime('resolved_at', precision: 3)->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'message_id']);
            $table->index(['device_id', 'created_at', 'id']);
            $table->index(['blind_user_id', 'resolved_at', 'created_at']);
            $table->index(['blind_user_id', 'type', 'created_at']);
            $table->index(['type', 'severity', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
