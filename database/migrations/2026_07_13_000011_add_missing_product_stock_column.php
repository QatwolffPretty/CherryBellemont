<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const STOCK_INDEX = 'products_stock_inventory_index';

    public function up(): void
    {
        if (Schema::hasColumn('products', 'stock')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('stock')->default(0)->index(self::STOCK_INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'stock') || ! Schema::hasIndex('products', self::STOCK_INDEX)) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(self::STOCK_INDEX);
            $table->dropColumn('stock');
        });
    }
};
