<?php

namespace App\Console\Commands;

use App\Models\PaymentReceipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SecurePaymentReceiptStorage extends Command
{
    protected $signature = 'receipts:secure-storage {--dry-run : Report legacy receipt files without moving them}';

    protected $description = 'Move legacy public payment receipts to Laravel private storage without changing receipt records.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $skipped = 0;

        PaymentReceipt::query()
            ->where(function ($query): void {
                $query->whereNull('storage_disk')->orWhere('storage_disk', 'public');
            })
            ->orderBy('id')
            ->chunkById(100, function ($receipts) use ($dryRun, &$moved, &$skipped): void {
                foreach ($receipts as $receipt) {
                    if (! Storage::disk('public')->exists($receipt->path)) {
                        $this->warn("Receipt #{$receipt->id} was skipped because its public file is missing.");
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("Would secure receipt #{$receipt->id}.");
                        $moved++;

                        continue;
                    }

                    try {
                        $this->move($receipt);
                        $moved++;
                    } catch (Throwable $exception) {
                        $skipped++;
                        Log::warning('Legacy payment receipt could not be moved to private storage.', [
                            'receipt_id' => $receipt->id,
                            'reason' => $exception->getMessage(),
                        ]);
                        $this->warn("Receipt #{$receipt->id} could not be secured; see the application log.");
                    }
                }
            });

        $this->info(($dryRun ? 'Would secure' : 'Secured')." {$moved} receipt(s); skipped {$skipped}.");

        return self::SUCCESS;
    }

    private function move(PaymentReceipt $receipt): void
    {
        $source = Storage::disk('public')->readStream($receipt->path);
        if (! is_resource($source)) {
            throw new RuntimeException('The public receipt file could not be read.');
        }

        try {
            if (! Storage::disk('local')->writeStream($receipt->path, $source)) {
                throw new RuntimeException('The private receipt file could not be written.');
            }
        } finally {
            fclose($source);
        }

        DB::transaction(function () use ($receipt): void {
            PaymentReceipt::query()
                ->lockForUpdate()
                ->findOrFail($receipt->id)
                ->update(['storage_disk' => 'local']);
        });

        if (! Storage::disk('public')->delete($receipt->path)) {
            PaymentReceipt::query()
                ->whereKey($receipt->id)
                ->update(['storage_disk' => 'public']);

            throw new RuntimeException('The public receipt copy could not be removed after the private copy was verified.');
        }
    }
}
