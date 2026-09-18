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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blind_user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('serial_number', 100)->unique();
            $table->string('firmware_version', 50)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->string('preferred_language', 5)->default('en');
            $table->string('status', 32)->default('offline');
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->unsignedInteger('configuration_version')->default(1);
            $table->dateTime('last_seen_at', precision: 3)->nullable();
            $table->dateTime('last_location_recorded_at', precision: 3)->nullable();
            $table->dateTime('geofence_evaluated_at', precision: 3)->nullable();
            $table->timestamps();

            $table->index(['blind_user_id', 'status']);
            $table->index(['status', 'last_seen_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
