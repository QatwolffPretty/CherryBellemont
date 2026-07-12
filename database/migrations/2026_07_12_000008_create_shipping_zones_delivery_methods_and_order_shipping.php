<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('state'); $table->string('city_or_area')->nullable(); $table->string('postcode_from', 20)->nullable(); $table->string('postcode_to', 20)->nullable(); $table->decimal('base_fee', 10, 2); $table->boolean('is_active')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::create('delivery_methods', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('code')->unique(); $table->text('description')->nullable(); $table->decimal('additional_fee', 10, 2)->default(0); $table->unsignedInteger('estimated_days')->nullable(); $table->boolean('is_pickup')->default(false); $table->boolean('is_active')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shipping_method_name')->nullable();
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->string('pickup_location')->nullable();
            $table->text('delivery_instructions')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void { $table->dropConstrainedForeignId('shipping_zone_id'); $table->dropConstrainedForeignId('delivery_method_id'); $table->dropColumn(['shipping_method_name', 'shipping_fee', 'pickup_location', 'delivery_instructions']); });
        Schema::dropIfExists('delivery_methods'); Schema::dropIfExists('shipping_zones');
    }
};
