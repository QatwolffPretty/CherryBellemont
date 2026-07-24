<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'gift_wrapping')) {
                $table->boolean('gift_wrapping')->default(false);
            }

            if (! Schema::hasColumn('orders', 'gift_wrapping_fee')) {
                $table->decimal('gift_wrapping_fee', 10, 2)->default(0);
            }

            if (! Schema::hasColumn('orders', 'gift_message')) {
                $table->text('gift_message')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            foreach (['gift_message', 'gift_wrapping_fee', 'gift_wrapping'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
