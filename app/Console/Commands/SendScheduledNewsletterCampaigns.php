<?php

namespace App\Console\Commands;

use App\Models\NewsletterCampaign;
use App\Services\NewsletterCampaignService;
use Illuminate\Console\Command;
use Throwable;

class SendScheduledNewsletterCampaigns extends Command
{
    protected $signature = 'newsletter:send-scheduled';

    protected $description = 'Queue newsletter campaigns whose scheduled time has arrived.';

    public function handle(NewsletterCampaignService $campaigns): int
    {
        $queued = 0;

        NewsletterCampaign::query()
            ->scheduled()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->each(function (NewsletterCampaign $campaign) use ($campaigns, &$queued): void {
                try {
                    $campaign = $campaigns->start($campaign);
                    if ($campaign->status === NewsletterCampaign::STATUS_SENDING) {
                        $campaigns->dispatchPendingDeliveries($campaign);
                    }
                    $queued++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->warn("Campaign [{$campaign->id}] could not be queued.");
                }
            });

        $this->info("Queued {$queued} scheduled campaign(s).");

        return self::SUCCESS;
    }
}
