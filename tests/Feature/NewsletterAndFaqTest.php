<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Notifications\AdminOperationalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewsletterAndFaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_subscribe_to_the_newsletter(): void
    {
        Notification::fake();
        $response = $this->from('/')
            ->post(route('newsletter.subscribe'), [
                'email' => '  Guest@Example.test ',
                'name' => 'Cherry Guest',
                'source' => 'footer',
            ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('newsletter_success');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'guest@example.test',
            'name' => 'Cherry Guest',
            'status' => 'subscribed',
            'source' => 'footer',
        ]);
        Notification::assertSentOnDemand(AdminOperationalNotification::class, fn (AdminOperationalNotification $notification) => $notification->event === 'new_newsletter_subscriber');
    }

    public function test_a_duplicate_subscription_does_not_create_another_record(): void
    {
        Notification::fake();
        NewsletterSubscriber::create([
            'email' => 'guest@example.test',
            'status' => 'subscribed',
            'subscribed_at' => now(),
            'verification_token' => Str::random(64),
        ]);

        $response = $this->from('/')
            ->post(route('newsletter.subscribe'), [
                'email' => 'GUEST@EXAMPLE.TEST',
            ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('newsletter_success');
        $this->assertDatabaseCount('newsletter_subscribers', 1);
        Notification::assertNothingSent();
    }

    public function test_an_invalid_newsletter_email_is_rejected(): void
    {
        $response = $this->from('/')
            ->post(route('newsletter.subscribe'), ['email' => 'not-an-email']);

        $response->assertRedirect('/');
        $response->assertSessionHasErrorsIn('newsletter', 'email');
    }

    public function test_a_subscriber_can_unsubscribe_with_a_secure_token(): void
    {
        $subscriber = NewsletterSubscriber::create([
            'email' => 'guest@example.test',
            'status' => 'subscribed',
            'subscribed_at' => now(),
            'verification_token' => Str::random(64),
        ]);

        $this->get(route('newsletter.unsubscribe', $subscriber->verification_token))
            ->assertOk()
            ->assertSee('You have been unsubscribed from Cherry Bellemont updates.');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'id' => $subscriber->id,
            'status' => 'unsubscribed',
        ]);
    }

    public function test_an_invalid_unsubscribe_token_fails_safely(): void
    {
        $this->get(route('newsletter.unsubscribe', Str::random(64)))->assertNotFound();
    }

    public function test_non_admins_cannot_access_newsletter_administration(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer)
            ->get(route('admin.newsletter.index'))
            ->assertForbidden();
    }

    public function test_the_public_faq_page_only_displays_active_faqs_and_sanitizes_output(): void
    {
        Faq::create([
            'question' => 'How should I care for my piece?',
            'answer' => '<p onmouseover="alert(1)">Care for it gently.</p><script>alert(2)</script>',
            'category' => 'Care Instructions',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Faq::create([
            'question' => 'This question is inactive',
            'answer' => 'This answer must not appear.',
            'sort_order' => 2,
            'is_active' => false,
        ]);

        $this->get(route('faq.index'))
            ->assertOk()
            ->assertSee('How should I care for my piece?')
            ->assertSee('Care for it gently.')
            ->assertDontSee('This question is inactive')
            ->assertDontSee('onmouseover')
            ->assertDontSee('<script>alert(2)</script>', false)
            ->assertDontSee('alert(2)');
    }

    public function test_an_admin_can_create_and_edit_an_faq(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.newsletter.index'))
            ->assertOk()
            ->assertSee('Newsletter subscribers');

        $this->actingAs($admin)
            ->get(route('admin.faqs.index'))
            ->assertOk()
            ->assertSee('Manage the editable FAQ guidance');

        $create = $this->actingAs($admin)->post(route('admin.faqs.store'), [
            'question' => 'Can I update my delivery details?',
            'answer' => '<strong>Please contact us promptly.</strong><script>alert(1)</script>',
            'category' => 'Orders',
            'sort_order' => 10,
            'is_active' => '1',
        ]);

        $create->assertRedirect(route('admin.faqs.index'));

        $faq = Faq::query()->where('question', 'Can I update my delivery details?')->firstOrFail();
        $this->assertStringNotContainsString('<script', $faq->answer);

        $update = $this->actingAs($admin)->patch(route('admin.faqs.update', $faq), [
            'question' => 'Can I update my delivery address?',
            'answer' => '<p>Contact us as soon as possible.</p>',
            'category' => 'Orders',
            'sort_order' => 11,
            'is_active' => '0',
        ]);

        $update->assertRedirect(route('admin.faqs.index'));
        $this->assertDatabaseHas('faqs', [
            'id' => $faq->id,
            'question' => 'Can I update my delivery address?',
            'is_active' => false,
        ]);
    }
}
