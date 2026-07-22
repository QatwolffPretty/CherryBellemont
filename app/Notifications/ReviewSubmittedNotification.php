<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class ReviewSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public Review $review) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Review Waiting Approval — Cherry Bellemont')
            ->greeting('A new verified review is ready for moderation.')
            ->line($this->review->product->name.' received a '.$this->review->rating.'-star review from '.$this->review->customerFirstName().'.')
            ->action('Review submission', route('admin.reviews.show', $this->review));
    }
}
