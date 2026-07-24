<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('return_requests')) {
            Schema::create('return_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('return_number')->unique();
                $table->foreignId('order_id')->constrained()->restrictOnDelete();
                $table->string('customer_name');
                $table->string('customer_email')->index();
                $table->string('request_type', 20)->index();
                $table->string('status', 30)->default('requested')->index();
                $table->string('customer_reason', 100);
                $table->text('customer_details')->nullable();
                $table->string('preferred_resolution', 40)->nullable();
                $table->text('admin_decision_reason')->nullable();
                $table->text('return_instructions')->nullable();
                $table->json('exchange_details')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('item_received_at')->nullable();
                $table->timestamp('inspected_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['order_id', 'status']);
            });
        }

        if (! Schema::hasTable('return_request_items')) {
            Schema::create('return_request_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('product_name');
                $table->unsignedInteger('requested_quantity');
                $table->unsignedInteger('approved_quantity')->nullable();
                $table->decimal('unit_price', 10, 2);
                $table->decimal('line_paid_amount', 10, 2);
                $table->string('reason', 100);
                $table->string('condition_received', 100)->nullable();
                $table->text('inspection_notes')->nullable();
                $table->string('stock_disposition', 30)->nullable();
                $table->timestamp('restocked_at')->nullable();
                $table->timestamps();
                $table->unique(['return_request_id', 'order_item_id']);
            });
        }

        if (! Schema::hasTable('return_request_images')) {
            Schema::create('return_request_images', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
                $table->string('image_path');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table): void {
                $table->id();
                $table->string('refund_number')->unique();
                $table->foreignId('return_request_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('order_id')->constrained()->restrictOnDelete();
                $table->string('payment_provider', 20)->index();
                $table->string('refund_type', 20);
                $table->string('status', 20)->default('pending')->index();
                $table->decimal('amount', 10, 2);
                $table->decimal('shipping_refund_amount', 10, 2)->default(0);
                $table->decimal('gift_wrap_refund_amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('MYR');
                $table->text('reason');
                $table->string('stripe_refund_id')->nullable()->unique();
                $table->string('stripe_payment_intent_id')->nullable()->index();
                $table->string('manual_reference')->nullable()->unique();
                $table->string('manual_proof_path')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['order_id', 'status']);
            });
        }

        if (! Schema::hasTable('return_request_events')) {
            Schema::create('return_request_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
                $table->string('actor_type', 30);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('event_type', 60);
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30)->nullable();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['return_request_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_events');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('return_request_images');
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
    }
};
