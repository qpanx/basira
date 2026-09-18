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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('ngo_id')->nullable()->constrained('ngos')->restrictOnDelete();
            $table->string('phone', 32)->nullable();
            $table->string('role', 32)->default('family');
            $table->string('preferred_language', 5)->default('en');
            $table->boolean('is_active')->default(true);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('sms_opt_in_at')->nullable();

            $table->index(['role', 'ngo_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'ngo_id', 'is_active']);
            $table->dropConstrainedForeignId('ngo_id');
            $table->dropColumn([
                'phone',
                'role',
                'preferred_language',
                'is_active',
                'phone_verified_at',
                'sms_opt_in_at',
            ]);
        });
    }
};
