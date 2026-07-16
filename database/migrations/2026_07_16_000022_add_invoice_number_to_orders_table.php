<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'orders_invoice_number_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'invoice_number')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('invoice_number')->nullable()->unique(self::UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'invoice_number') && Schema::hasIndex('orders', self::UNIQUE_INDEX)) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE_INDEX);
                $table->dropColumn('invoice_number');
            });
        }
    }
};
