<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('type', 30)->index();
            $table->string('subtype', 60)->nullable();
            $table->text('description')->nullable();
            $table->string('normal_balance', 6);
            $table->foreignId('parent_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });

        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_number', 40)->unique();
            $table->date('transaction_date')->index();
            $table->timestamp('posting_date')->nullable()->index();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event', 60)->nullable();
            $table->string('reference')->nullable()->index();
            $table->text('description');
            $table->string('status', 20)->default('draft')->index();
            $table->string('currency', 3)->default('MYR');
            $table->decimal('total_debit', 14, 2)->default(0);
            $table->decimal('total_credit', 14, 2)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'source_event'], 'journal_entries_source_event_unique');
            $table->index(['status', 'transaction_date']);
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('default_account_code', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('expense_number', 40)->unique();
            $table->date('expense_date')->index();
            $table->date('accounting_date')->index();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('debit_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->string('supplier')->nullable()->index();
            $table->text('description');
            $table->decimal('amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('receipt_path')->nullable();
            $table->string('reference_number')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'expense_date']);
        });

        Schema::create('owner_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_number', 40)->unique();
            $table->date('transaction_date')->index();
            $table->string('transaction_type', 50)->index();
            $table->decimal('amount', 14, 2);
            $table->foreignId('payment_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('destination_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->text('description');
            $table->string('payment_method', 40)->nullable();
            $table->string('reference_number')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'transaction_date']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['order_id', 'expense_id']);
        });

        Schema::create('accounting_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('record_type', 80)->index();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['record_type', 'record_id']);
        });

        if (! Schema::hasColumn('products', 'cost_price')) {
            Schema::table('products', fn (Blueprint $table) => $table->decimal('cost_price', 14, 2)->nullable()->after('price'));
        }
        if (! Schema::hasColumn('order_items', 'unit_cost')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->decimal('unit_cost', 14, 2)->nullable()->after('unit_price'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audit_logs');
        Schema::dropIfExists('accounting_settings');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('owner_transactions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_accounts');

        if (Schema::hasColumn('order_items', 'unit_cost')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('unit_cost'));
        }
        if (Schema::hasColumn('products', 'cost_price')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('cost_price'));
        }
    }
};
