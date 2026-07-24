<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReturnRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestImage;
use App\Services\OrderDocumentService;
use App\Services\ReturnEligibilityService;
use App\Services\ReturnNotifier;
use App\Services\ReturnWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReturnRequestController extends Controller
{
    public function create(Request $request, Order $order, ReturnEligibilityService $eligibility): View
    {
        $this->authorizeAuthenticated($request, $order);

        return $this->createFor($order, null, $eligibility);
    }

    public function guestCreate(Order $order, string $token, ReturnEligibilityService $eligibility): View
    {
        $this->authorizeGuest($order, $token);

        return $this->createFor($order, $token, $eligibility);
    }

    public function store(StoreReturnRequest $request, Order $order, ReturnEligibilityService $eligibility, ReturnWorkflowService $workflow, ReturnNotifier $notifier): RedirectResponse
    {
        $this->authorizeAuthenticated($request, $order);

        return $this->storeFor($request, $order, null, $eligibility, $workflow, $notifier);
    }

    public function guestStore(StoreReturnRequest $request, Order $order, string $token, ReturnEligibilityService $eligibility, ReturnWorkflowService $workflow, ReturnNotifier $notifier): RedirectResponse
    {
        $this->authorizeGuest($order, $token);

        return $this->storeFor($request, $order, $token, $eligibility, $workflow, $notifier);
    }

    public function show(Request $request, Order $order, ReturnRequest $returnRequest): View
    {
        $this->authorizeAuthenticated($request, $order);

        return $this->showFor($order, $returnRequest, null);
    }

    public function guestShow(Order $order, string $token, ReturnRequest $returnRequest): View
    {
        $this->authorizeGuest($order, $token);

        return $this->showFor($order, $returnRequest, $token);
    }

    public function creditNote(Request $request, Order $order, ReturnRequest $returnRequest, Refund $refund, OrderDocumentService $documents): Response
    {
        $this->authorizeAuthenticated($request, $order);

        return $this->creditNoteFor($order, $returnRequest, $refund, $documents);
    }

    public function guestCreditNote(Order $order, string $token, ReturnRequest $returnRequest, Refund $refund, OrderDocumentService $documents): Response
    {
        $this->authorizeGuest($order, $token);

        return $this->creditNoteFor($order, $returnRequest, $refund, $documents);
    }

    public function downloadImage(Request $request, Order $order, ReturnRequest $returnRequest, ReturnRequestImage $image): Response
    {
        $this->authorizeAuthenticated($request, $order);

        return $this->downloadImageFor($order, $returnRequest, $image);
    }

    public function guestDownloadImage(Order $order, string $token, ReturnRequest $returnRequest, ReturnRequestImage $image): Response
    {
        $this->authorizeGuest($order, $token);

        return $this->downloadImageFor($order, $returnRequest, $image);
    }

    private function createFor(Order $order, ?string $token, ReturnEligibilityService $eligibility): View
    {
        $order->load('items');
        abort_unless($eligibility->canRequest($order), 403);

        return view('returns.create', ['order' => $order, 'token' => $token, 'items' => $eligibility->eligibleItems($order)]);
    }

    private function storeFor(StoreReturnRequest $request, Order $order, ?string $token, ReturnEligibilityService $eligibility, ReturnWorkflowService $workflow, ReturnNotifier $notifier): RedirectResponse
    {
        $order->load('items');
        if (! $eligibility->canRequest($order)) {
            throw ValidationException::withMessages(['return' => 'This order is not eligible for a return request.']);
        }

        $data = $request->validated();
        $firstItem = collect($data['items'])->first();
        $return = DB::transaction(function () use ($order, $data, $firstItem, $request, $eligibility, $workflow): ReturnRequest {
            foreach ($data['items'] as $selected) {
                if (ReturnRequest::query()->where('order_id', $order->id)->whereNotIn('status', ['rejected', 'closed'])->whereHas('items', fn ($query) => $query->where('order_item_id', $selected['order_item_id']))->exists()) {
                    throw ValidationException::withMessages(['items' => 'A return request for one of these items is already in progress.']);
                }
            }

            $return = ReturnRequest::create([
                'return_number' => $this->number(),
                'order_id' => $order->id,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'request_type' => $data['request_type'],
                'status' => 'requested',
                'customer_reason' => $firstItem['reason'],
                'customer_details' => $data['customer_details'] ?? null,
                'preferred_resolution' => $data['preferred_resolution'] ?? null,
                'requested_at' => now(),
            ]);

            foreach ($data['items'] as $selected) {
                $item = $eligibility->validateItem($order, (int) $selected['order_item_id'], (int) $selected['quantity']);
                $return->items()->create([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name ?: $item->name,
                    'requested_quantity' => $selected['quantity'],
                    'unit_price' => $item->unit_price,
                    'line_paid_amount' => $item->line_total ?? $item->total,
                    'reason' => $selected['reason'],
                    'stock_disposition' => 'pending',
                ]);
            }
            foreach ($request->file('images', []) as $index => $image) {
                $return->images()->create(['image_path' => $image->store('return-evidence', 'local'), 'sort_order' => $index]);
            }
            $order->update(['return_status' => 'requested', 'last_return_requested_at' => now()]);
            $workflow->event($return, null, 'request_submitted', null, 'requested', 'Customer submitted a return request.');

            return $return;
        }, 3);

        $notifier->customer($return, 'requested');
        $notifier->admin($return, 'new_request');

        return $this->redirectToShow($return, $token)->with('success', 'Return request '.$return->return_number.' has been submitted for review.');
    }

    private function showFor(Order $order, ReturnRequest $returnRequest, ?string $token): View
    {
        abort_unless($returnRequest->order_id === $order->id, 404);

        return view('returns.show', ['order' => $order, 'returnRequest' => $returnRequest->load('items.product', 'images', 'refunds', 'events'), 'token' => $token]);
    }

    private function creditNoteFor(Order $order, ReturnRequest $returnRequest, Refund $refund, OrderDocumentService $documents): Response
    {
        abort_unless($returnRequest->order_id === $order->id && $refund->return_request_id === $returnRequest->id && $refund->status === 'succeeded', 404);

        return $documents->creditNote($refund)->download('credit-note-'.$refund->refund_number.'.pdf');
    }

    private function downloadImageFor(Order $order, ReturnRequest $returnRequest, ReturnRequestImage $image): Response
    {
        abort_unless($returnRequest->order_id === $order->id && $image->return_request_id === $returnRequest->id, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($image->image_path), 404);

        return $disk->download($image->image_path, 'return-evidence-'.$image->id.'.'.pathinfo($image->image_path, PATHINFO_EXTENSION));
    }

    private function authorizeAuthenticated(Request $request, Order $order): void
    {
        abort_unless($request->user() && $order->user_id === $request->user()->id, 403);
    }

    private function authorizeGuest(Order $order, string $token): void
    {
        abort_unless($order->guest_access_token && hash_equals($order->guest_access_token, $token), 403);
    }

    private function redirectToShow(ReturnRequest $return, ?string $token): RedirectResponse
    {
        return $token
            ? to_route('returns.guest.show', ['order' => $return->order->order_number, 'token' => $token, 'returnRequest' => $return])
            : to_route('returns.show', ['order' => $return->order_id, 'returnRequest' => $return]);
    }

    private function number(): string
    {
        do {
            $number = 'RET-CB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (ReturnRequest::query()->where('return_number', $number)->exists());

        return $number;
    }
}
