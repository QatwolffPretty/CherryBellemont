<?php

namespace App\Services;

use App\Models\Review;
class ReviewNotifier
{
    public function __construct(private readonly AdminNotificationService $adminNotifier) {}

    public function notifyAdmins(Review $review): void
    {
        $this->adminNotifier->send('new_review', ['review' => $review->loadMissing('product')]);
    }
}
