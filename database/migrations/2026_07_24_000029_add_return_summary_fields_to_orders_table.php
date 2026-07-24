<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'refunded_amount')) $table->decimal('refunded_amount', 10, 2)->default(0);
            if (! Schema::hasColumn('orders', 'refund_status')) $table->string('refund_status', 30)->nullable()->index();
            if (! Schema::hasColumn('orders', 'return_status')) $table->string('return_status', 30)->nullable()->index();
            if (! Schema::hasColumn('orders', 'last_return_requested_at')) $table->timestamp('last_return_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            foreach (['last_return_requested_at', 'return_status', 'refund_status', 'refunded_amount'] as $column) {
                if (Schema::hasColumn('orders', $column)) $table->dropColumn($column);
            }
        });
    }
};
