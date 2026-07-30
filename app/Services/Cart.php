<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class Cart
{
    private const SESSION_KEY = 'cart';
    private const COUPON_SESSION_KEY = 'coupon_code';

    /**
     * Cart lines are keyed by product plus variant. This method also converts
     * the legacy [product_id => quantity] session shape at read time.
     *
     * @return array<string, array{product_id:int, variant_id:?int, quantity:int}>
     */
    public function contents(): array
    {
        return collect(session(self::SESSION_KEY, []))
            ->map(function (mixed $line, mixed $key): ?array {
                if (is_array($line)) {
                    $productId = (int) ($line['product_id'] ?? 0);
                    $variantId = filled($line['variant_id'] ?? null) ? (int) $line['variant_id'] : null;
                    $quantity = max(1, (int) ($line['quantity'] ?? 0));
                } else {
                    $productId = (int) $key;
                    $variantId = null;
                    $quantity = max(1, (int) $line);
                }

                if ($productId < 1) {
                    return null;
                }

                return compact('productId', 'variantId', 'quantity');
            })
            ->filter()
            ->mapWithKeys(function (array $line): array {
                $key = $this->key($line['productId'], $line['variantId']);

                return [$key => [
                    'product_id' => $line['productId'],
                    'variant_id' => $line['variantId'],
                    'quantity' => $line['quantity'],
                ]];
            })
            ->all();
    }

    public function key(int $productId, ?int $variantId = null): string
    {
        return $variantId ? 'product-'.$productId.'-variant-'.$variantId : (string) $productId;
    }

    public function put(int $productId, int $quantity, ?int $variantId = null): void
    {
        $cart = $this->contents();
        $cart[$this->key($productId, $variantId)] = $variantId
            ? ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => max(1, $quantity)]
            : max(1, $quantity);
        session([self::SESSION_KEY => $cart]);
    }

    public function putByKey(string $key, int $quantity): void
    {
        $cart = $this->contents();
        if (! isset($cart[$key])) {
            return;
        }
        if ($cart[$key]['variant_id']) {
            $cart[$key]['quantity'] = max(1, $quantity);
        } else {
            $cart[$key] = max(1, $quantity);
        }
        session([self::SESSION_KEY => $cart]);
    }

    public function line(string $key): ?array
    {
        return $this->contents()[$key] ?? null;
    }

    public function forget(int $productId, ?int $variantId = null): void
    {
        $this->forgetByKey($this->key($productId, $variantId));
    }

    public function forgetByKey(string $key): void
    {
        $cart = $this->contents();
        unset($cart[$key]);
        session([self::SESSION_KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget([self::SESSION_KEY, self::COUPON_SESSION_KEY]);
    }

    public function couponCode(): ?string
    {
        $code = trim((string) session(self::COUPON_SESSION_KEY, ''));

        return $code === '' ? null : strtoupper($code);
    }

    public function applyCoupon(string $code): void
    {
        session([self::COUPON_SESSION_KEY => strtoupper(trim($code))]);
    }

    public function removeCoupon(): void
    {
        session()->forget(self::COUPON_SESSION_KEY);
    }

    public function count(): int
    {
        return collect($this->contents())->sum('quantity');
    }

    public function lines(): Collection
    {
        $contents = $this->contents();
        $productIds = collect($contents)->pluck('product_id')->unique()->values()->all();
        $variantIds = collect($contents)->pluck('variant_id')->filter()->unique()->values()->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('status', 'active')
            ->with(['primaryImage'])
            ->get()
            ->keyBy('id');
        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->with(['size:id,name,code', 'colour:id,name,slug,hex_code'])
            ->get()
            ->keyBy('id');

        return collect($contents)->map(function (array $entry, string $key) use ($products, $variants): ?array {
            $product = $products->get($entry['product_id']);
            if (! $product) {
                return null;
            }

            $variant = $entry['variant_id'] ? $variants->get($entry['variant_id']) : null;
            if ($entry['variant_id'] && (! $variant || $variant->product_id !== $product->id)) {
                return null;
            }

            $unitPrice = (int) round(((float) ($variant?->price_override ?? $product->price)) * 100);
            $availableStock = $variant ? (int) $variant->stock : (int) $product->stock;

            return [
                'key' => $key,
                'product' => $product,
                'variant' => $variant,
                'quantity' => $entry['quantity'],
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $entry['quantity'],
                'available_stock' => $availableStock,
                'sku' => $variant?->sku,
                'size_name' => $variant?->size?->name,
                'colour_name' => $variant?->colour?->name,
                'variant_description' => $variant?->displayDescription(),
                'image_path' => $product->primaryImagePath(),
            ];
        })->filter()->values();
    }

    public function totals(Collection $lines): array
    {
        $subtotal = $lines->sum('line_total');

        return ['subtotal' => $subtotal, 'total' => $subtotal, 'count' => $lines->sum('quantity')];
    }
}
