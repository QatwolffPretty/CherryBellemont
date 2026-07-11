<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class Cart
{
    private const SESSION_KEY = 'cart';

    public function contents(): array
    {
        return collect(session(self::SESSION_KEY, []))
            ->mapWithKeys(fn ($quantity, $productId) => [(int) $productId => max(1, (int) $quantity)])
            ->all();
    }

    public function put(int $productId, int $quantity): void
    {
        $cart = $this->contents();
        $cart[$productId] = $quantity;
        session([self::SESSION_KEY => $cart]);
    }

    public function forget(int $productId): void
    {
        $cart = $this->contents();
        unset($cart[$productId]);
        session([self::SESSION_KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        return array_sum($this->contents());
    }

    public function lines(): Collection
    {
        $quantities = $this->contents();
        $products = Product::query()->whereIn('id', array_keys($quantities))->where('status', 'active')->get()->keyBy('id');

        return collect($quantities)->map(function (int $quantity, int $productId) use ($products) {
            $product = $products->get($productId);

            if (! $product) {
                return null;
            }

            $unitPrice = (int) round(((float) $product->price) * 100);

            return [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $quantity,
            ];
        })->filter()->values();
    }

    public function totals(Collection $lines): array
    {
        $subtotal = $lines->sum('line_total');

        return ['subtotal' => $subtotal, 'total' => $subtotal, 'count' => $lines->sum('quantity')];
    }
}
