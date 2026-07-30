<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_accounts')) {
            return;
        }

        Schema::table('accounting_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_accounts', 'opening_balance_date')) {
                $table->date('opening_balance_date')->nullable()->after('opening_balance');
            }

            if (! Schema::hasColumn('accounting_accounts', 'allow_manual_posting')) {
                $table->boolean('allow_manual_posting')->default(true)->after('is_system');
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: accounting history must be preserved.
    }
};
