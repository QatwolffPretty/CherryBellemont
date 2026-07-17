<?php

namespace App\Services;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignDelivery;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NewsletterCampaignService
{
    /**
     * Snapshot eligible subscribers into immutable delivery records before any
     * message is dispatched. A unique campaign/email index makes retries safe.
     */
    public function start(NewsletterCampaign $campaign): NewsletterCampaign
    {
        return DB::transaction(function () use ($campaign): NewsletterCampaign {
            $campaign = NewsletterCampaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if (! in_array($campaign->status, [NewsletterCampaign::STATUS_DRAFT, NewsletterCampaign::STATUS_SCHEDULED], true)) {
                throw new RuntimeException('Only draft or scheduled campaigns can be sent.');
            }

            $campaign->update([
                'status' => NewsletterCampaign::STATUS_SENDING,
                'sending_started_at' => now(),
                'scheduled_at' => $campaign->scheduled_at,
                'sent_at' => null,
                'archived_at' => null,
                'recipient_count' => 0,
                'sent_count' => 0,
                'failed_count' => 0,
            ]);

            $this->eligibleSubscribers($campaign)
                ->orderBy('id')
                ->chunkById(250, function ($subscribers) use ($campaign): void {
                    $rows = [];
                    $now = now();

                    foreach ($subscribers as $subscriber) {
                        if (! $subscriber->verification_token) {
                            $subscriber->forceFill(['verification_token' => Str::random(64)])->save();
                        }

                        $rows[] = [
                            'newsletter_campaign_id' => $campaign->id,
                            'newsletter_subscriber_id' => $subscriber->id,
                            'email' => $subscriber->email,
                            'name' => $subscriber->name,
                            'status' => NewsletterCampaignDelivery::STATUS_PENDING,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($rows !== []) {
                        NewsletterCampaignDelivery::query()->insertOrIgnore($rows);
                    }
                });

            $campaign->update([
                'recipient_count' => $campaign->deliveries()->count(),
            ]);

            if ($campaign->recipient_count === 0) {
                $campaign->update([
                    'status' => NewsletterCampaign::STATUS_SENT,
                    'sent_at' => now(),
                ]);
            }

            return $campaign->fresh();
        }, 3);
    }

    public function dispatchPendingDeliveries(NewsletterCampaign $campaign): void
    {
        \App\Jobs\QueueNewsletterCampaignDeliveries::dispatch($campaign->id);
    }

    public function markDeliverySent(NewsletterCampaignDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery): void {
            $delivery = NewsletterCampaignDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if (in_array($delivery->status, [NewsletterCampaignDelivery::STATUS_SENT, NewsletterCampaignDelivery::STATUS_SKIPPED], true)) {
                return;
            }

            $delivery->update([
                'status' => NewsletterCampaignDelivery::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
            ]);

            $this->refreshCampaignProgress($delivery->newsletter_campaign_id);
        }, 3);
    }

    public function claimDelivery(int $deliveryId, bool $allowQueuedRetry = false): ?NewsletterCampaignDelivery
    {
        return DB::transaction(function () use ($deliveryId, $allowQueuedRetry): ?NewsletterCampaignDelivery {
            $delivery = NewsletterCampaignDelivery::query()->lockForUpdate()->find($deliveryId);
            $claimable = $delivery && (
                $delivery->status === NewsletterCampaignDelivery::STATUS_PENDING
                || ($allowQueuedRetry && $delivery->status === NewsletterCampaignDelivery::STATUS_QUEUED)
            );

            if (! $claimable) {
                return null;
            }

            $campaign = NewsletterCampaign::query()->lockForUpdate()->find($delivery->newsletter_campaign_id);
            $subscriber = $delivery->newsletter_subscriber_id
                ? NewsletterSubscriber::query()->lockForUpdate()->find($delivery->newsletter_subscriber_id)
                : null;

            if (! $campaign || $campaign->status !== NewsletterCampaign::STATUS_SENDING) {
                $delivery->update([
                    'status' => NewsletterCampaignDelivery::STATUS_SKIPPED,
                    'failure_reason' => 'Campaign is no longer accepting deliveries.',
                ]);
                if ($campaign) {
                    $this->refreshCampaignProgress($campaign->id);
                }

                return null;
            }

            if (! $subscriber || $subscriber->status !== 'subscribed') {
                $delivery->update([
                    'status' => NewsletterCampaignDelivery::STATUS_SKIPPED,
                    'failure_reason' => 'Subscriber is no longer subscribed.',
                ]);
                $this->refreshCampaignProgress($campaign->id);

                return null;
            }

            $delivery->update([
                'status' => NewsletterCampaignDelivery::STATUS_QUEUED,
                'queued_at' => $delivery->queued_at ?: now(),
            ]);

            return $delivery->fresh(['campaign', 'subscriber']);
        }, 3);
    }

    public function markDeliverySkipped(NewsletterCampaignDelivery $delivery, string $reason): void
    {
        DB::transaction(function () use ($delivery, $reason): void {
            $delivery = NewsletterCampaignDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if (in_array($delivery->status, [NewsletterCampaignDelivery::STATUS_SENT, NewsletterCampaignDelivery::STATUS_FAILED, NewsletterCampaignDelivery::STATUS_SKIPPED], true)) {
                return;
            }

            $delivery->update([
                'status' => NewsletterCampaignDelivery::STATUS_SKIPPED,
                'failure_reason' => Str::limit($reason, 1000),
            ]);

            $this->refreshCampaignProgress($delivery->newsletter_campaign_id);
        }, 3);
    }

    public function markDeliveryFailed(NewsletterCampaignDelivery $delivery, \Throwable $exception): void
    {
        DB::transaction(function () use ($delivery, $exception): void {
            $delivery = NewsletterCampaignDelivery::query()->lockForUpdate()->find($delivery->id);
            if (! $delivery || in_array($delivery->status, [NewsletterCampaignDelivery::STATUS_SENT, NewsletterCampaignDelivery::STATUS_SKIPPED, NewsletterCampaignDelivery::STATUS_FAILED], true)) {
                return;
            }

            $delivery->update([
                'status' => NewsletterCampaignDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => Str::limit($exception->getMessage(), 1000),
            ]);

            $this->refreshCampaignProgress($delivery->newsletter_campaign_id);
        }, 3);

        Log::warning('Newsletter campaign delivery failed.', [
            'delivery_id' => $delivery->id,
            'campaign_id' => $delivery->newsletter_campaign_id,
            'email' => $delivery->email,
            'reason' => Str::limit($exception->getMessage(), 300),
        ]);
    }

    public function schedule(NewsletterCampaign $campaign, \DateTimeInterface $scheduledAt): NewsletterCampaign
    {
        if (! in_array($campaign->status, [NewsletterCampaign::STATUS_DRAFT, NewsletterCampaign::STATUS_SCHEDULED], true)) {
            throw new RuntimeException('A campaign that has started sending cannot be scheduled.');
        }

        $campaign->update([
            'status' => NewsletterCampaign::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
            'archived_at' => null,
        ]);

        return $campaign->fresh();
    }

    /** @return Builder<NewsletterSubscriber> */
    public function eligibleSubscribers(NewsletterCampaign $campaign): Builder
    {
        $query = NewsletterSubscriber::query()->subscribed();

        return match ($campaign->audience_type) {
            NewsletterCampaign::AUDIENCE_LAST_30_DAYS => $query->where('subscribed_at', '>=', now()->subDays(30)),
            NewsletterCampaign::AUDIENCE_LAST_90_DAYS => $query->where('subscribed_at', '>=', now()->subDays(90)),
            default => $query,
        };
    }

    private function refreshCampaignProgress(int $campaignId): void
    {
        $campaign = NewsletterCampaign::query()->lockForUpdate()->findOrFail($campaignId);
        $counts = $campaign->deliveries()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $sent = (int) ($counts[NewsletterCampaignDelivery::STATUS_SENT] ?? 0);
        $failed = (int) ($counts[NewsletterCampaignDelivery::STATUS_FAILED] ?? 0);
        $pending = (int) ($counts[NewsletterCampaignDelivery::STATUS_PENDING] ?? 0)
            + (int) ($counts[NewsletterCampaignDelivery::STATUS_QUEUED] ?? 0);

        $attributes = ['sent_count' => $sent, 'failed_count' => $failed];
        if ($pending === 0 && $campaign->status === NewsletterCampaign::STATUS_SENDING) {
            $attributes['status'] = $sent > 0 ? NewsletterCampaign::STATUS_SENT : NewsletterCampaign::STATUS_FAILED;
            $attributes['sent_at'] = now();
        }

        $campaign->update($attributes);
    }
}
