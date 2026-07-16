<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $subscribers = NewsletterSubscriber::query()->latest('subscribed_at');

        if ($search = $request->string('search')->trim()->value()) {
            $subscribers->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if (in_array($request->input('status'), ['subscribed', 'unsubscribed', 'pending'], true)) {
            $subscribers->where('status', $request->input('status'));
        }

        return view('admin.newsletter.index', [
            'subscribers' => $subscribers->paginate(25)->withQueryString(),
            'stats' => [
                'active' => NewsletterSubscriber::query()->subscribed()->count(),
                'new_this_month' => NewsletterSubscriber::query()->subscribed()->where('subscribed_at', '>=', now()->startOfMonth())->count(),
                'unsubscribed' => NewsletterSubscriber::query()->where('status', 'unsubscribed')->count(),
                'total' => NewsletterSubscriber::query()->count(),
            ],
        ]);
    }

    public function unsubscribe(NewsletterSubscriber $newsletterSubscriber): RedirectResponse
    {
        if ($newsletterSubscriber->status !== 'unsubscribed') {
            $newsletterSubscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
        }

        return back()->with('success', 'Subscriber has been unsubscribed.');
    }

    public function export()
    {
        $filename = 'cherry-bellemont-subscribers-'.now()->format('Y-m-d').'.csv';

        return Response::streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Name', 'Email', 'Subscribed At', 'Source']);

            NewsletterSubscriber::query()->subscribed()->orderBy('email')->each(function (NewsletterSubscriber $subscriber) use ($stream): void {
                fputcsv($stream, [
                    $this->csvValue($subscriber->name),
                    $this->csvValue($subscriber->email),
                    $this->csvValue($subscriber->subscribed_at?->toDateTimeString()),
                    $this->csvValue($subscriber->source),
                ]);
            });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(NewsletterSubscriber $newsletterSubscriber): RedirectResponse
    {
        $newsletterSubscriber->delete();

        return back()->with('success', 'Subscriber record deleted.');
    }

    private function csvValue(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
