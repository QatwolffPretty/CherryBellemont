<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterSubscribeRequest;
use App\Models\NewsletterSubscriber;
use App\Services\AdminNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    public function subscribe(NewsletterSubscribeRequest $request, AdminNotificationService $adminNotifier): RedirectResponse
    {
        $data = $request->validated();
        [$message, $newSubscriber] = DB::transaction(function () use ($data): array {
            $subscriber = NewsletterSubscriber::query()
                ->where('email', $data['email'])
                ->lockForUpdate()
                ->first();

            if ($subscriber?->status === 'subscribed') {
                return ['You are already subscribed to the Cherry Bellemont List.', null];
            }

            $attributes = [
                'name' => $data['name'] ?? null,
                'status' => 'subscribed',
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'source' => $data['source'] ?? 'footer',
                'verification_token' => Str::random(64),
            ];

            if ($subscriber) {
                $subscriber->update($attributes);

                return ['Welcome back to the Cherry Bellemont List.', null];
            }

            $subscriber = NewsletterSubscriber::create(['email' => $data['email'], ...$attributes]);

            return ['Thank you for joining the Cherry Bellemont List.', $subscriber];
        });

        if ($newSubscriber) {
            $adminNotifier->send('new_newsletter_subscriber', ['subscriber' => $newSubscriber]);
        }

        return back()->with('newsletter_success', $message);
    }

    public function unsubscribe(string $token): View
    {
        $subscriber = NewsletterSubscriber::query()->where('verification_token', $token)->firstOrFail();

        if ($subscriber->status !== 'unsubscribed') {
            $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        }

        return view('newsletter.unsubscribed');
    }
}
