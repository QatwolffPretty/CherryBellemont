<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductStockNotificationRequest;
use App\Models\Product;
use App\Services\ProductStockNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductStockNotificationController extends Controller
{
    public function store(StoreProductStockNotificationRequest $request, Product $product, ProductStockNotificationService $notifications): RedirectResponse
    {
        abort_unless($product->status === 'active', 404);

        $result = $notifications->request(
            $product,
            $request->validated('email'),
            $request->validated('name'),
        );

        return match ($result) {
            'created' => back()->with('stock_notification_success', 'We’ll notify you when this item is back in stock.'),
            'duplicate' => back()->with('stock_notification_success', 'You are already waiting for this item.'),
            'disabled' => back()->withErrors(['stock_notification' => 'Back-in-stock notifications are temporarily unavailable.']),
            default => back()->withErrors(['stock_notification' => 'This product is currently available.']),
        };
    }

    public function cancel(string $token, ProductStockNotificationService $notifications): View
    {
        $notification = $notifications->cancel($token);

        return view('stock-notifications.cancelled', compact('notification'));
    }
}
