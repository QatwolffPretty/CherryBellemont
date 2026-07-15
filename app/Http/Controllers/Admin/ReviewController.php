<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewBulkModerationRequest;
use App\Http\Requests\ReviewModerationRequest;
use App\Http\Requests\ReviewReplyRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = Review::query()->with(['product', 'order', 'images'])->latest();

        if ($search = $request->string('search')->trim()->value()) {
            $reviews->where(function ($query) use ($search): void {
                $query->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('review', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"));
            });
        }

        if (in_array($request->input('status'), ['pending', 'approved', 'rejected', 'hidden'], true)) {
            $reviews->where('status', $request->input('status'));
        }
        if (in_array((int) $request->input('rating'), [1, 2, 3, 4, 5], true)) {
            $reviews->where('rating', (int) $request->input('rating'));
        }
        if ($request->boolean('with_images')) {
            $reviews->has('images');
        }

        return view('admin.reviews.index', ['reviews' => $reviews->paginate(20)->withQueryString()]);
    }

    public function show(Review $review): View
    {
        return view('admin.reviews.show', ['review' => $review->load(['product', 'order.items.product', 'orderItem', 'images'])]);
    }

    public function approve(Review $review): RedirectResponse
    {
        $this->moderate($review, 'approved');

        return back()->with('success', 'Review approved.');
    }

    public function reject(Review $review): RedirectResponse
    {
        $this->moderate($review, 'rejected');

        return back()->with('success', 'Review rejected.');
    }

    public function hide(Review $review): RedirectResponse
    {
        $this->moderate($review, 'hidden');

        return back()->with('success', 'Review hidden from the storefront.');
    }

    public function reply(ReviewReplyRequest $request, Review $review): RedirectResponse
    {
        DB::transaction(function () use ($request, $review): void {
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);

            if ($review->admin_reply) {
                throw ValidationException::withMessages(['admin_reply' => 'An official reply has already been added to this review.']);
            }

            $review->update(['admin_reply' => $request->validated('admin_reply')]);
        });

        return back()->with('success', 'Official response published.');
    }

    public function bulk(ReviewBulkModerationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $status = $data['status'];

        DB::transaction(function () use ($data, $status): void {
            $reviews = Review::query()->whereIn('id', $data['review_ids'])->lockForUpdate()->get();

            foreach ($reviews as $review) {
                $review->update([
                    'status' => $status,
                    'is_approved' => $status === 'approved',
                    'approved_at' => $status === 'approved' ? ($review->approved_at ?? now()) : null,
                ]);
            }
        });

        return back()->with('success', 'Selected reviews were '.$status.'.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $paths = $review->images()->pluck('image_path')->all();

        DB::transaction(function () use ($review): void {
            Review::query()->lockForUpdate()->findOrFail($review->id)->delete();
        });
        Storage::disk('public')->delete($paths);

        return to_route('admin.reviews.index')->with('success', 'Review deleted.');
    }

    private function moderate(Review $review, string $status): void
    {
        DB::transaction(function () use ($review, $status): void {
            $review = Review::query()->lockForUpdate()->findOrFail($review->id);
            $review->update([
                'status' => $status,
                'is_approved' => $status === 'approved',
                'approved_at' => $status === 'approved' ? ($review->approved_at ?? now()) : null,
            ]);
        });
    }
}
