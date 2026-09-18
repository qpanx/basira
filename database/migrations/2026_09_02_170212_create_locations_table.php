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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->foreignId('blind_user_id')->constrained()->restrictOnDelete();
            $table->uuid('message_id');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
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
        Schema::dropIfExists('locations');
    }
};
