<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_sessions', function (Blueprint $table): void {
            $table->string('routing_provider', 20)->default('google');
        });
    }

    public function down(): void
    {
        Schema::table('navigation_sessions', function (Blueprint $table): void {
            $table->dropColumn('routing_provider');
        });
    }
};
