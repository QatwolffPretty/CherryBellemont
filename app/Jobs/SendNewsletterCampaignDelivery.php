<?php

namespace App\Jobs;

use App\Mail\NewsletterCampaignMail;
use App\Services\NewsletterCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNewsletterCampaignDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $deliveryId)
    {
    }

    public function handle(NewsletterCampaignService $campaigns): void
    {
        $delivery = $campaigns->claimDelivery($this->deliveryId, $this->attempts() > 1);
        if (! $delivery) {
            return;
        }

        Mail::to($delivery->email, $delivery->name)->send(
            new NewsletterCampaignMail($delivery->campaign, $delivery->subscriber)
        );

        $campaigns->markDeliverySent($delivery);
    }

    public function failed(Throwable $exception): void
    {
        app(NewsletterCampaignService::class)->markDeliveryFailed(
            new \App\Models\NewsletterCampaignDelivery(['id' => $this->deliveryId]),
            $exception,
        );
    }
}
