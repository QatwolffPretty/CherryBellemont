<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NewsletterCampaign $campaign,
        public readonly NewsletterSubscriber $subscriber,
        public readonly bool $isTest = false,
    ) {
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
                'unsubscribeUrl' => route('newsletter.unsubscribe', ['token' => $this->subscriber->verification_token]),
                'isTest' => $this->isTest,
            ],
        );
    }
}
