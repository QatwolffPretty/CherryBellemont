<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('reviews')) {
            return;
        }

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->string('customer_name', 160);
            $table->string('customer_email');
            $table->unsignedTinyInteger('rating');
            $table->string('title', 160);
            $table->text('review');
            $table->boolean('is_verified_purchase')->default(true);
            $table->boolean('is_approved')->default(false);
            $table->string('status', 20)->default('pending');
            $table->text('admin_reply')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();

            $table->unique('order_item_id');
            $table->index(['product_id', 'status', 'created_at']);
            $table->index(['customer_email', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
