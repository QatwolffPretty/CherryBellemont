<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestOrderLookupRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class GuestOrderLookupController extends Controller
{
    public function create(): View
    {
        return view('orders.lookup');
    }

    public function store(GuestOrderLookupRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $order = Order::query()
            ->where(fn ($query) => $query->where('order_number', $data['order_number'])->orWhere('number', $data['order_number']))
            ->where(function ($query) use ($data): void {
                $query->whereRaw('LOWER(customer_email) = ?', [$data['email']])
                    ->orWhereRaw('LOWER(email) = ?', [$data['email']]);
            })
            ->first();

        if (! $order?->guest_access_token) {
            Log::notice('Guest order lookup did not produce a secure order link.', [
                'order_number' => $data['order_number'],
                'ip_hash' => hash('sha256', (string) $request->ip()),
            ]);

            return back()->withErrors(['lookup' => 'We could not find an order matching those details.'])->withInput($request->except('email'));
        }

        Log::info('Guest order lookup succeeded.', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        return to_route('orders.guest.show', ['order' => $order->order_number ?? $order->number, 'token' => $order->guest_access_token]);
    }
}
