<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewSubmittedNotification;
use Illuminate\Support\Facades\Notification;

class ReviewNotifier
{
    public function notifyAdmins(Review $review): void
    {
        $admins = User::query()->where('is_admin', true)->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ReviewSubmittedNotification($review));
        }
    }
}
