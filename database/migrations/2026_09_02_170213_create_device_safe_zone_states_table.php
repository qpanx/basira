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
        Schema::create('device_safe_zone_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('safe_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->boolean('is_inside')->nullable();
            $table->dateTime('evaluated_at', precision: 3)->nullable();
            $table->dateTime('entered_at', precision: 3)->nullable();
            $table->dateTime('exited_at', precision: 3)->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'safe_zone_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_safe_zone_states');
    }
};
