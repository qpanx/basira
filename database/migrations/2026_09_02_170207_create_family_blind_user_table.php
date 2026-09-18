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
        Schema::create('family_blind_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blind_user_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 64);
            $table->json('permissions');
            $table->timestamps();

            $table->unique(['user_id', 'blind_user_id']);
            $table->index(['blind_user_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_blind_user');
    }
};
