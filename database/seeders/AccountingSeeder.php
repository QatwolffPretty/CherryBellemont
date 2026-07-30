<?php

namespace Database\Seeders;

use App\Services\AccountingAccountService;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    /** Seed only missing default accounts, categories, and mappings. Existing bookkeeping choices remain untouched. */
    public function run(): void
    {
        app(AccountingAccountService::class)->ensureDefaults();
    }
}
