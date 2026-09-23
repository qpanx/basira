<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_logs', function (Blueprint $table): void {
            $table->decimal('voltage', 5, 3)->nullable();
        });

        Schema::table('alerts', function (Blueprint $table): void {
            $table->decimal('sensor_value', 12, 3)->nullable();
            $table->string('sensor_unit', 8)->nullable();
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->dateTime('last_status_recorded_at', precision: 3)->nullable();
            $table->dateTime('last_status_received_at', precision: 3)->nullable();
            $table->boolean('last_status_online')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn(['last_status_recorded_at', 'last_status_received_at', 'last_status_online']);
        });

        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropColumn(['sensor_value', 'sensor_unit']);
        });

        Schema::table('battery_logs', function (Blueprint $table): void {
            $table->dropColumn('voltage');
        });
    }
};
