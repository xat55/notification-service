<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subscriber_id');
            $table->enum('channel', ['sms', 'email']);
            $table->text('message');
            $table->enum('priority', ['high', 'low'])->default('low');
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued');
            $table->string('idempotency_key')->unique();
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['subscriber_id', 'created_at']);
            $table->index('status');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
