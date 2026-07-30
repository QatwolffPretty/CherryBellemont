<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_transactions', function (Blueprint $table): void {
            // Reserve allocations are equity reclassifications and do not require a cash movement.
            $table->foreignId('payment_account_id')->nullable()->change();
            $table->foreignId('debit_account_id')->nullable()->after('destination_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('credit_account_id')->nullable()->after('debit_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('attachment_path')->nullable()->after('reference_number');
            $table->foreignId('posted_by')->nullable()->after('journal_entry_id')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->timestamp('reversed_at')->nullable()->after('posted_at');
            $table->foreignId('reversal_transaction_id')->nullable()->after('reversed_at')->constrained('owner_transactions')->nullOnDelete();
            $table->index(['transaction_type', 'status', 'transaction_date'], 'owner_transactions_type_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('owner_transactions', function (Blueprint $table): void {
            $table->dropIndex('owner_transactions_type_status_date_index');
            $table->dropConstrainedForeignId('reversal_transaction_id');
            $table->dropColumn(['reversed_at', 'posted_at']);
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn('attachment_path');
            $table->dropConstrainedForeignId('credit_account_id');
            $table->dropConstrainedForeignId('debit_account_id');
        });
    }
};
