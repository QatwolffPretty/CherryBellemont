<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_notification_logs')) {
            return;
        }

        Schema::create('order_notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('return_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_type', 80);
            $table->string('recipient', 254);
            $table->string('subject')->nullable();
            $table->string('event_key', 191)->unique();
            $table->string('status', 20)->default('queued');
            $table->boolean('is_manual_resend')->default(false);
            $table->foreignId('resent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['return_request_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['notification_type', 'created_at']);
            $table->index('recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notification_logs');
    }
};
