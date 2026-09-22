<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->foreignId('blind_user_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('last_location_id')->constrained('locations')->restrictOnDelete();
            $table->string('destination_place_id');
            $table->json('destination');
            $table->string('language', 5);
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('distance_meters');
            $table->unsignedInteger('estimated_duration_seconds');
            $table->json('steps');
            $table->json('route_coordinates');
            $table->json('warnings');
            $table->unsignedInteger('current_step_index')->default(0);
            $table->boolean('off_route')->default(false);
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('last_reroute_at')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'status']);
            $table->index(['blind_user_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_sessions');
    }
};
