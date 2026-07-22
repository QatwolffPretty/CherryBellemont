<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewsletterCampaignMail extends Mailable
{
    use Queueable;

    /**
     * Campaign mail may wait in the queue while an administrator removes a
     * subscriber. Keep the rendering data as immutable scalar snapshots so a
     * later model lookup cannot make a queued message fail.
     */
    public readonly object $campaign;

    public readonly object $subscriber;

    public readonly ?string $unsubscribeToken;

    public function __construct(
        NewsletterCampaign $campaign,
        NewsletterSubscriber $subscriber,
        public readonly bool $isTest = false,
    ) {
        $this->campaign = (object) $campaign->only([
            'name', 'subject', 'preview_text', 'content', 'hero_image_path', 'cta_text', 'cta_url',
        ]);
        $this->subscriber = (object) [
            'name' => $subscriber->name,
            'email' => $subscriber->email,
        ];
        $this->unsubscribeToken = $subscriber->verification_token;
    }

    public function envelope(): Envelope
    {
        $prefix = $this->isTest ? '[TEST] ' : '';

        return new Envelope(subject: $prefix.$this->campaign->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.campaign',
            text: 'emails.newsletter.campaign-text',
            with: [
                'campaign' => $this->campaign,
                'subscriber' => $this->subscriber,
                'unsubscribeUrl' => route('newsletter.unsubscribe', ['token' => $this->unsubscribeToken]),
                'isTest' => $this->isTest,
            ],
        );
    }
}
