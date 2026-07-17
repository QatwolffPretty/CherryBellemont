<?php

namespace App\Jobs;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueNewsletterCampaignDeliveries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $campaignId)
    {
    }

    public function handle(): void
    {
        $campaign = NewsletterCampaign::query()->find($this->campaignId);
        if (! $campaign || $campaign->status !== NewsletterCampaign::STATUS_SENDING) {
            return;
        }

        NewsletterCampaignDelivery::query()
            ->where('newsletter_campaign_id', $campaign->id)
            ->pending()
            ->orderBy('id')
            ->chunkById(100, function ($deliveries): void {
                foreach ($deliveries as $delivery) {
                    SendNewsletterCampaignDelivery::dispatch($delivery->id);
                }
            });
    }
}
