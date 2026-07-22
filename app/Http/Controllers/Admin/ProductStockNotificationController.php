<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminProductStockNotificationIndexRequest;
use App\Models\Product;
use App\Models\ProductStockNotification;
use Illuminate\View\View;

class ProductStockNotificationController extends Controller
{
    public function index(AdminProductStockNotificationIndexRequest $request): View
    {
        $filters = $request->validated();
        $notifications = ProductStockNotification::query()->with('product:id,name,stock,status')->latest('requested_at');

        if ($filters['status'] ?? null) {
            $notifications->where('status', $filters['status']);
        }
        if ($filters['product_id'] ?? null) {
            $notifications->where('product_id', $filters['product_id']);
        }

        $mostRequested = ProductStockNotification::query()
            ->waiting()
            ->selectRaw('product_id, COUNT(*) as request_count')
            ->groupBy('product_id')
            ->orderByDesc('request_count')
            ->first();

        return view('admin.products.stock-notifications.index', [
            'notifications' => $notifications->paginate(30)->withQueryString(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'waiting' => ProductStockNotification::query()->waiting()->count(),
                'products_with_waiting' => ProductStockNotification::query()->waiting()->distinct('product_id')->count('product_id'),
                'most_requested' => $mostRequested ? Product::query()->find($mostRequested->product_id) : null,
                'most_requested_count' => (int) ($mostRequested->request_count ?? 0),
            ],
        ]);
    }
}
