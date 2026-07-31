<?php

namespace App\Console\Commands;

use App\Services\AccountingBackfillService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class BackfillAccountingTransactions extends Command
{
    protected $signature = 'accounting:backfill
        {--from= : Include completed events on or after this YYYY-MM-DD date}
        {--to= : Include completed events on or before this YYYY-MM-DD date}
        {--dry-run : Report work without writing journal or audit records}
        {--chunk=100 : Number of records to process per database chunk}';

    protected $description = 'Idempotently post historical paid orders and completed refunds to the General Ledger';

    public function handle(AccountingBackfillService $backfill): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $chunk = max(1, min(1000, (int) $this->option('chunk')));

        try {
            if ($from) {
                CarbonImmutable::parse($from);
            }
            if ($to) {
                CarbonImmutable::parse($to);
            }
            if ($from && $to && CarbonImmutable::parse($from)->greaterThan(CarbonImmutable::parse($to))) {
                $this->error('The --from date cannot be later than --to.');

                return self::FAILURE;
            }
        } catch (\Throwable) {
            $this->error('Use valid YYYY-MM-DD dates for --from and --to.');

            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Dry run: no journal or audit records will be written.' : 'Posting historical accounting records.');
        $summary = $backfill->run($from ?: null, $to ?: null, (bool) $this->option('dry-run'), $chunk, function (string $type, string $status, string $reference): void {
            $this->line(strtoupper($type).' '.$reference.': '.$status);
        });

        $this->table(['Metric', 'Count'], collect($summary)->map(fn (int $count, string $metric) => [str_replace('_', ' ', ucfirst($metric)), $count])->all());

        return $summary['failures'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
