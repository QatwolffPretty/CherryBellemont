<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_stock_notifications')) {
            return;
        }

        Schema::create('product_stock_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('email');
            // Only waiting requests populate this value, allowing a customer
            // to request another notification after an earlier one is sent.
            $table->string('waiting_email')->nullable();
            $table->string('name', 160)->nullable();
            $table->string('status', 20)->default('waiting');
            $table->string('notification_token', 64)->unique();
            $table->timestamp('requested_at');
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'waiting_email'], 'stock_notification_waiting_email_unique');
            $table->index(['product_id', 'status'], 'stock_notification_product_status_index');
            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_notifications');
    }
};
