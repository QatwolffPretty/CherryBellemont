<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_receipts') && ! Schema::hasColumn('payment_receipts', 'storage_disk')) {
            Schema::table('payment_receipts', function (Blueprint $table): void {
                $table->string('storage_disk', 32)->nullable()->after('path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_receipts') && Schema::hasColumn('payment_receipts', 'storage_disk')) {
            Schema::table('payment_receipts', function (Blueprint $table): void {
                $table->dropColumn('storage_disk');
            });
        }
    }
};
