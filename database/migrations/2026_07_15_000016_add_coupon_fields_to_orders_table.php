<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = [
            'coupon_id' => fn (Blueprint $table) => $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete(),
            'coupon_code' => fn (Blueprint $table) => $table->string('coupon_code')->nullable(),
            'discount_amount' => fn (Blueprint $table) => $table->decimal('discount_amount', 10, 2)->default(0),
            'original_shipping_fee' => fn (Blueprint $table) => $table->decimal('original_shipping_fee', 10, 2)->nullable(),
            'free_shipping_discount' => fn (Blueprint $table) => $table->decimal('free_shipping_discount', 10, 2)->default(0),
        ];

        foreach ($columns as $column => $addColumn) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', $addColumn);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'coupon_id')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('coupon_id'));
        }

        $columns = array_values(array_filter([
            'coupon_code',
            'discount_amount',
            'original_shipping_fee',
            'free_shipping_discount',
        ], fn (string $column): bool => Schema::hasColumn('orders', $column)));

        if ($columns !== []) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
