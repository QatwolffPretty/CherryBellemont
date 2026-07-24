<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('couriers')) {
            Schema::create('couriers', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('code', 60)->unique();
                $table->string('tracking_url_template', 2048)->nullable();
                $table->string('website_url', 2048)->nullable();
                $table->string('logo_path')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->boolean('supports_api')->default(false);
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('shipments')) {
            Schema::create('shipments', function (Blueprint $table): void {
                $table->id();
                $table->string('shipment_number', 80)->unique();
                $table->foreignId('order_id')->constrained()->restrictOnDelete();
                $table->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
                $table->string('courier_name_snapshot', 120)->nullable();
                $table->string('service_name', 120)->nullable();
                $table->string('tracking_number', 160)->nullable()->index();
                $table->string('tracking_url', 2048)->nullable();
                $table->string('shipment_status', 40)->default('draft')->index();
                $table->string('shipment_type', 30)->default('outbound')->index();
                $table->string('label_path')->nullable();
                $table->text('admin_note')->nullable();
                $table->string('provider_reference', 160)->nullable()->index();
                $table->string('api_provider', 80)->nullable();
                $table->timestamp('shipped_at')->nullable()->index();
                $table->timestamp('estimated_delivery_at')->nullable();
                $table->timestamp('delivered_at')->nullable()->index();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['order_id', 'shipment_status']);
            });
        }

        if (! Schema::hasTable('shipment_events')) {
            Schema::create('shipment_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
                $table->string('status', 40);
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->string('location', 160)->nullable();
                $table->timestamp('event_time')->index();
                $table->string('source', 20)->default('admin');
                $table->string('provider_event_id', 160)->nullable();
                $table->timestamps();
                $table->index(['shipment_id', 'event_time']);
                $table->unique(['shipment_id', 'provider_event_id']);
            });
        }

        if (! Schema::hasTable('shipment_audit_logs')) {
            Schema::create('shipment_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
                $table->string('action', 80);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['shipment_id', 'created_at']);
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'tracking_url')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('tracking_url', 2048)->nullable()->after('tracking_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'tracking_url')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('tracking_url'));
        }
        Schema::dropIfExists('shipment_audit_logs');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('couriers');
    }
};
