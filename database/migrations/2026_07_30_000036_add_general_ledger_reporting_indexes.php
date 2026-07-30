<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journal_entries') && ! Schema::hasIndex('journal_entries', 'journal_entries_status_posting_date_index')) {
            Schema::table('journal_entries', function (Blueprint $table): void {
                $table->index(['status', 'posting_date'], 'journal_entries_status_posting_date_index');
            });
        }

        // The source event unique key already supports source_type/source_id
        // lookups. This index supports the account activity join/order path.
        if (Schema::hasTable('journal_entry_lines') && ! Schema::hasIndex('journal_entry_lines', 'journal_entry_lines_account_created_at_index')) {
            Schema::table('journal_entry_lines', function (Blueprint $table): void {
                $table->index(['account_id', 'created_at'], 'journal_entry_lines_account_created_at_index');
            });
        }
    }

    public function down(): void
    {
        // The ledger reports historical accounting data. Index removal is not
        // needed for an application rollback and this migration stays safe.
    }
};
