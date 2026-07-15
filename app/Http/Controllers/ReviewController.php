<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewSubmissionRequest;
use App\Models\Product;
use App\Models\Review;
use App\Services\ReviewEligibility;
use App\Services\ReviewNotifier;
use App\Services\ReviewSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function create(Request $request, Product $product, ReviewEligibility $eligibility): View
    {
        abort_unless($product->status === 'active', 404);
        $context = $eligibility->resolve($request, $product);

        return view('reviews.form', compact('product', 'context'));
    }

    public function store(ReviewSubmissionRequest $request, Product $product, ReviewEligibility $eligibility, ReviewSubmissionService $reviews, ReviewNotifier $notifier): RedirectResponse
    {
        abort_unless($product->status === 'active', 404);
        $context = $eligibility->resolve($request, $product);

        if ($context['review']) {
            return back()->withErrors(['review' => 'This purchased item already has a review. You may edit it instead.']);
        }

        $review = $reviews->create($product, $context, $request->validated());
        $notifier->notifyAdmins($review->load('product'));

        return $this->orderRedirect($request, $context['order'], 'Thank you. Your verified review is waiting for approval.');
    }

    public function update(ReviewSubmissionRequest $request, Product $product, Review $review, ReviewEligibility $eligibility, ReviewSubmissionService $reviews): RedirectResponse
    {
        abort_unless($product->status === 'active', 404);
        $context = $eligibility->resolve($request, $product);
        $eligibility->assertReviewMatches($review, $context);
        $reviews->update($review, $product, $context, $request->validated());

        return $this->orderRedirect($request, $context['order'], 'Your review has been updated and is waiting for approval.');
    }

    public function helpful(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->status === 'approved' && $review->is_approved, 404);
        $votes = collect(session('review_helpful_votes', []))->map(fn ($id) => (int) $id)->all();

        if (in_array($review->id, $votes, true)) {
            return back()->with('success', 'You have already marked this review as helpful.');
        }

        DB::transaction(function () use ($review): void {
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);
            abort_unless($review->status === 'approved' && $review->is_approved, 404);
            $review->increment('helpful_count');
        });

        session(['review_helpful_votes' => [...$votes, $review->id]]);

        return back()->with('success', 'Thank you for your feedback.');
    }

    private function orderRedirect(Request $request, \App\Models\Order $order, string $message): RedirectResponse
    {
        if ($order->guest_access_token) {
            return to_route('orders.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token])
                ->with('success', $message);
        }

        abort_unless($request->user() && $order->user_id === $request->user()->id, 403);

        return to_route('orders.show', $order)->with('success', $message);
    }
}
