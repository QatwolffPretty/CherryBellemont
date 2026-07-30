<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_accounts')) {
            Schema::table('accounting_accounts', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_accounts', 'is_cash_account')) {
                    $table->boolean('is_cash_account')->default(false)->index()->after('allow_manual_posting');
                }
                if (! Schema::hasColumn('accounting_accounts', 'is_cash_equivalent')) {
                    $table->boolean('is_cash_equivalent')->default(false)->index()->after('is_cash_account');
                }
                if (! Schema::hasColumn('accounting_accounts', 'is_clearing_account')) {
                    $table->boolean('is_clearing_account')->default(false)->index()->after('is_cash_equivalent');
                }
                if (! Schema::hasColumn('accounting_accounts', 'cash_flow_enabled')) {
                    $table->boolean('cash_flow_enabled')->default(false)->index()->after('is_clearing_account');
                }
            });

            DB::table('accounting_accounts')->whereIn('code', ['1000', '1010'])->update([
                'is_cash_account' => true,
                'cash_flow_enabled' => true,
            ]);
            DB::table('accounting_accounts')->whereIn('code', ['1020', '1030'])->update([
                'is_cash_equivalent' => true,
                'is_clearing_account' => true,
                'cash_flow_enabled' => true,
            ]);
        }

        if (! Schema::hasTable('cash_flow_account_mappings')) {
            Schema::create('cash_flow_account_mappings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('accounting_account_id')->unique()->constrained('accounting_accounts')->restrictOnDelete();
                $table->string('classification', 30)->index();
                $table->string('category_key', 60)->nullable()->index();
                $table->string('label')->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['classification', 'is_active'], 'cash_flow_mapping_classification_active_index');
            });
        }

        if (Schema::hasTable('journal_entries') && ! Schema::hasIndex('journal_entries', 'journal_entries_status_transaction_date_source_index')) {
            Schema::table('journal_entries', function (Blueprint $table): void {
                $table->index(['status', 'transaction_date', 'source_type'], 'journal_entries_status_transaction_date_source_index');
            });
        }
    }

    public function down(): void
    {
        // Cash-flow configuration is historical reporting metadata. It is intentionally retained on rollback.
    }
};
