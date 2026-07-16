<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->addIndex('orders', 'orders_payment_status_order_status_created_at_index', ['payment_status', 'order_status', 'created_at']);
        $this->addIndex('orders', 'orders_payment_provider_payment_status_created_at_index', ['payment_provider', 'payment_status', 'created_at']);
        $this->addIndex('payment_receipts', 'payment_receipts_status_submitted_at_index', ['status', 'submitted_at']);
        $this->addIndex('products', 'products_stock_index', ['stock']);
    }

    public function down(): void
    {
        $this->dropIndex('orders', 'orders_payment_status_order_status_created_at_index');
        $this->dropIndex('orders', 'orders_payment_provider_payment_status_created_at_index');
        $this->dropIndex('payment_receipts', 'payment_receipts_status_submitted_at_index');
        $this->dropIndex('products', 'products_stock_index');
    }

    private function addIndex(string $table, string $name, array $columns): void
    {
        if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
        }
    }
};
