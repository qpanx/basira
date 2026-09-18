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
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('notification_id')->nullable()->index();
            $table->string('channel', 32);
            $table->string('provider', 64);
            $table->string('status', 32)->default('queued');
            $table->string('provider_reference', 191)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('destination_last_four', 4)->nullable();
            $table->dateTime('queued_at', precision: 3)->nullable();
            $table->dateTime('accepted_at', precision: 3)->nullable();
            $table->dateTime('delivered_at', precision: 3)->nullable();
            $table->dateTime('failed_at', precision: 3)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['alert_id', 'recipient_user_id', 'channel']);
            $table->unique(['provider', 'provider_reference']);
            $table->index(['alert_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
