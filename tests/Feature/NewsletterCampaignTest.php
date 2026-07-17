<?php

namespace Tests\Feature;

use App\Jobs\QueueNewsletterCampaignDeliveries;
use App\Jobs\SendNewsletterCampaignDelivery;
use App\Mail\NewsletterCampaignMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignDelivery;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\NewsletterCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class NewsletterCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_sanitized_draft_campaign(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.newsletter.campaigns.store'), $this->campaignData([
            'content' => '<h2>New collection</h2><p onclick="alert(1)">Welcome <strong>inside</strong>.</p><script>alert(2)</script>',
        ]));

        $campaign = NewsletterCampaign::query()->firstOrFail();
        $response->assertRedirect(route('admin.newsletter.campaigns.show', $campaign));
        $this->assertSame(NewsletterCampaign::STATUS_DRAFT, $campaign->status);
        $this->assertStringNotContainsString('<script', $campaign->content);
        $this->assertStringNotContainsString('onclick', $campaign->content);
        $this->assertStringContainsString('<strong>inside</strong>', $campaign->content);
    }

    public function test_a_non_admin_cannot_access_campaign_management(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer)->get(route('admin.newsletter.campaigns.index'))->assertForbidden();
    }

    public function test_campaign_validation_requires_core_fields_and_valid_cta_url(): void
    {
        $response = $this->actingAs($this->admin())->from(route('admin.newsletter.campaigns.create'))
            ->post(route('admin.newsletter.campaigns.store'), ['name' => '', 'subject' => '', 'content' => '', 'cta_url' => 'not-a-url']);

        $response->assertRedirect(route('admin.newsletter.campaigns.create'))
            ->assertSessionHasErrors(['name', 'subject', 'content', 'cta_url']);
    }

    public function test_a_test_email_is_queued_without_creating_a_delivery_record(): void
    {
        Mail::fake();
        $campaign = $this->campaign();

        $this->actingAs($this->admin())
            ->post(route('admin.newsletter.campaigns.test', $campaign), ['email' => 'test@example.test', 'name' => 'Test Guest'])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('newsletter_campaign_deliveries', 0);
        Mail::assertQueued(NewsletterCampaignMail::class, function (NewsletterCampaignMail $mail): bool {
            return $mail->isTest && $mail->envelope()->subject === '[TEST] A considered invitation';
        });
    }

    public function test_send_now_creates_one_delivery_per_eligible_active_subscriber_without_duplicates(): void
    {
        Queue::fake();
        $campaign = $this->campaign();
        $activeOne = $this->subscriber('one@example.test');
        $activeTwo = $this->subscriber('two@example.test');
        $this->subscriber('former@example.test', ['status' => 'unsubscribed', 'unsubscribed_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('admin.newsletter.campaigns.send', $campaign), ['confirm_send' => '1'])
            ->assertRedirect(route('admin.newsletter.campaigns.show', $campaign));

        $this->assertDatabaseCount('newsletter_campaign_deliveries', 2);
        $this->assertDatabaseHas('newsletter_campaign_deliveries', ['newsletter_subscriber_id' => $activeOne->id, 'status' => 'pending']);
        $this->assertDatabaseHas('newsletter_campaign_deliveries', ['newsletter_subscriber_id' => $activeTwo->id, 'status' => 'pending']);
        $this->assertDatabaseHas('newsletter_campaigns', ['id' => $campaign->id, 'status' => 'sending', 'recipient_count' => 2]);
        Queue::assertPushed(QueueNewsletterCampaignDeliveries::class, fn (QueueNewsletterCampaignDeliveries $job) => $job->campaignId === $campaign->id);

        $this->actingAs($this->admin())
            ->post(route('admin.newsletter.campaigns.send', $campaign), ['confirm_send' => '1'])
            ->assertSessionHasErrors('campaign');
        $this->assertDatabaseCount('newsletter_campaign_deliveries', 2);
    }

    public function test_unsubscribed_subscribers_are_skipped_before_delivery_and_campaign_counters_update(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $subscriber = $this->subscriber('former@example.test');
        app(NewsletterCampaignService::class)->start($campaign);
        $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        $delivery = NewsletterCampaignDelivery::query()->firstOrFail();

        (new SendNewsletterCampaignDelivery($delivery->id))->handle(app(NewsletterCampaignService::class));

        $this->assertDatabaseHas('newsletter_campaign_deliveries', ['id' => $delivery->id, 'status' => 'skipped']);
        $this->assertDatabaseHas('newsletter_campaigns', ['id' => $campaign->id, 'status' => 'failed', 'sent_count' => 0, 'failed_count' => 0]);
        Mail::assertNothingSent();
    }

    public function test_queued_delivery_sends_the_campaign_once_and_includes_a_secure_unsubscribe_link(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $subscriber = $this->subscriber('subscriber@example.test');
        app(NewsletterCampaignService::class)->start($campaign);
        $delivery = NewsletterCampaignDelivery::query()->firstOrFail();

        (new SendNewsletterCampaignDelivery($delivery->id))->handle(app(NewsletterCampaignService::class));
        (new SendNewsletterCampaignDelivery($delivery->id))->handle(app(NewsletterCampaignService::class));

        $this->assertDatabaseHas('newsletter_campaign_deliveries', ['id' => $delivery->id, 'status' => 'sent']);
        $this->assertDatabaseHas('newsletter_campaigns', ['id' => $campaign->id, 'status' => 'sent', 'sent_count' => 1]);
        Mail::assertSent(NewsletterCampaignMail::class, function (NewsletterCampaignMail $mail) use ($subscriber): bool {
            $this->assertStringContainsString($subscriber->verification_token, $mail->render());

            return true;
        });
        Mail::assertSent(NewsletterCampaignMail::class, 1);
    }

    public function test_failed_delivery_is_recorded_and_updates_campaign_counters(): void
    {
        $campaign = $this->campaign();
        $this->subscriber('failure@example.test');
        app(NewsletterCampaignService::class)->start($campaign);
        $delivery = NewsletterCampaignDelivery::query()->firstOrFail();

        app(NewsletterCampaignService::class)->markDeliveryFailed($delivery, new RuntimeException('Mailbox was unavailable.'));

        $this->assertDatabaseHas('newsletter_campaign_deliveries', ['id' => $delivery->id, 'status' => 'failed']);
        $this->assertDatabaseHas('newsletter_campaigns', ['id' => $campaign->id, 'status' => 'failed', 'failed_count' => 1]);
    }

    public function test_a_duplicate_first_attempt_cannot_send_an_already_queued_delivery(): void
    {
        Mail::fake();
        $campaign = $this->campaign();
        $this->subscriber('duplicate@example.test');
        $service = app(NewsletterCampaignService::class);
        $service->start($campaign);
        $delivery = NewsletterCampaignDelivery::query()->firstOrFail();
        $service->claimDelivery($delivery->id);

        (new SendNewsletterCampaignDelivery($delivery->id))->handle($service);

        $this->assertDatabaseHas('newsletter_campaign_deliveries', ['id' => $delivery->id, 'status' => 'queued']);
        Mail::assertNothingSent();
    }

    public function test_a_campaign_can_be_scheduled_and_the_due_command_queues_it_only_when_due(): void
    {
        Queue::fake();
        $campaign = $this->campaign();
        $this->subscriber('schedule@example.test');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.newsletter.campaigns.schedule', $campaign), ['scheduled_at' => now()->addHour()->format('Y-m-d H:i:s')])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('newsletter_campaigns', ['id' => $campaign->id, 'status' => 'scheduled']);

        $this->artisan('newsletter:send-scheduled')->assertSuccessful();
        Queue::assertNothingPushed();

        $campaign->update(['scheduled_at' => now()->subMinute()]);
        $this->artisan('newsletter:send-scheduled')->assertSuccessful();
        Queue::assertPushed(QueueNewsletterCampaignDeliveries::class, fn (QueueNewsletterCampaignDeliveries $job) => $job->campaignId === $campaign->id);
    }

    public function test_duplicate_campaign_creates_a_clean_draft_without_history(): void
    {
        $campaign = $this->campaign(['status' => NewsletterCampaign::STATUS_SENT, 'recipient_count' => 4, 'sent_count' => 4, 'sent_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('admin.newsletter.campaigns.duplicate', $campaign))
            ->assertRedirect();

        $copy = NewsletterCampaign::query()->whereKeyNot($campaign->id)->firstOrFail();
        $this->assertSame(NewsletterCampaign::STATUS_DRAFT, $copy->status);
        $this->assertStringEndsWith(' Copy', $copy->name);
        $this->assertSame(0, $copy->recipient_count);
        $this->assertNull($copy->scheduled_at);
        $this->assertDatabaseCount('newsletter_campaign_deliveries', 0);
    }

    public function test_archived_campaign_remains_viewable_but_sending_campaign_cannot_be_archived(): void
    {
        $campaign = $this->campaign();
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.newsletter.campaigns.archive', $campaign))->assertSessionHas('success');
        $this->actingAs($admin)->get(route('admin.newsletter.campaigns.show', $campaign))->assertOk();
        $this->assertDatabaseHas('newsletter_campaigns', ['id' => $campaign->id, 'status' => 'archived']);

        $sending = $this->campaign(['status' => NewsletterCampaign::STATUS_SENDING]);
        $this->actingAs($admin)->patch(route('admin.newsletter.campaigns.archive', $sending))->assertSessionHasErrors('campaign');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function campaign(array $attributes = []): NewsletterCampaign
    {
        return NewsletterCampaign::create(array_merge([
            'name' => 'Summer invitation '.Str::random(6),
            'subject' => 'A considered invitation',
            'content' => '<p>Discover the new Cherry Bellemont collection.</p>',
            'status' => NewsletterCampaign::STATUS_DRAFT,
            'audience_type' => NewsletterCampaign::AUDIENCE_ALL_ACTIVE,
        ], $attributes));
    }

    private function subscriber(string $email, array $attributes = []): NewsletterSubscriber
    {
        return NewsletterSubscriber::create(array_merge([
            'email' => $email,
            'name' => 'Newsletter Guest',
            'status' => 'subscribed',
            'subscribed_at' => now(),
            'verification_token' => Str::random(64),
        ], $attributes));
    }

    private function campaignData(array $attributes = []): array
    {
        return array_merge([
            'name' => 'A considered campaign',
            'subject' => 'A considered invitation',
            'content' => '<p>Discover our collection.</p>',
            'audience_type' => NewsletterCampaign::AUDIENCE_ALL_ACTIVE,
        ], $attributes);
    }
}
