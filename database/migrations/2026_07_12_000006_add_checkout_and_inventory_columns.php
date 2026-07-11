<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });

        $columns = [
            'order_number' => fn (Blueprint $table) => $table->string('order_number')->nullable()->unique(),
            'guest_access_token' => fn (Blueprint $table) => $table->string('guest_access_token', 64)->nullable()->unique(),
            'full_name' => fn (Blueprint $table) => $table->string('full_name')->nullable(),
            'email' => fn (Blueprint $table) => $table->string('email')->nullable(),
            'phone' => fn (Blueprint $table) => $table->string('phone', 40)->nullable(),
            'customer_name' => fn (Blueprint $table) => $table->string('customer_name')->nullable(),
            'customer_email' => fn (Blueprint $table) => $table->string('customer_email')->nullable(),
            'customer_phone' => fn (Blueprint $table) => $table->string('customer_phone', 40)->nullable(),
            'address_line_1' => fn (Blueprint $table) => $table->string('address_line_1')->nullable(),
            'address_line_2' => fn (Blueprint $table) => $table->string('address_line_2')->nullable(),
            'city' => fn (Blueprint $table) => $table->string('city')->nullable(),
            'state' => fn (Blueprint $table) => $table->string('state')->nullable(),
            'postcode' => fn (Blueprint $table) => $table->string('postcode', 30)->nullable(),
            'country' => fn (Blueprint $table) => $table->string('country')->nullable(),
            'payment_status' => fn (Blueprint $table) => $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending'),
            'order_status' => fn (Blueprint $table) => $table->enum('order_status', ['pending', 'processing', 'shipped', 'cancelled'])->default('pending'),
            'customer_notes' => fn (Blueprint $table) => $table->text('customer_notes')->nullable(),
        ];

        foreach ($columns as $column => $addColumn) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', $addColumn);
            }
        }

        $itemColumns = [
            'product_name' => fn (Blueprint $table) => $table->string('product_name')->nullable(),
            'line_total' => fn (Blueprint $table) => $table->decimal('line_total', 10, 2)->nullable(),
        ];

        foreach ($itemColumns as $column => $addColumn) {
            if (! Schema::hasColumn('order_items', $column)) {
                Schema::table('order_items', $addColumn);
            }
        }
    }

    public function down(): void
    {
        foreach (['product_name', 'line_total'] as $column) {
            if (Schema::hasColumn('order_items', $column)) {
                Schema::table('order_items', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        foreach (['guest_access_token', 'order_number'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $table) use ($column): void {
                    $table->dropUnique('orders_'.$column.'_unique');
                    $table->dropColumn($column);
                });
            }
        }

        foreach (['full_name', 'email', 'phone', 'customer_name', 'customer_email', 'customer_phone', 'address_line_1', 'address_line_2', 'city', 'state', 'postcode', 'country', 'payment_status', 'order_status', 'customer_notes'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
