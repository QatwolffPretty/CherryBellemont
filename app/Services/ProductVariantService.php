<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductVariantService
{
    /** @param array<int|string, array<string, mixed>> $rows */
    public function sync(Product $product, array $rows): void
    {
        foreach ($rows as $row) {
            $sizeId = filled($row['size_id'] ?? null) ? (int) $row['size_id'] : null;
            $colourId = filled($row['colour_id'] ?? null) ? (int) $row['colour_id'] : null;
            if ($sizeId === null && $colourId === null) {
                continue;
            }

            $variant = filled($row['id'] ?? null)
                ? $product->variants()->findOrFail((int) $row['id'])
                : null;

            $duplicate = $product->variants()
                ->where('product_size_id', $sizeId)
                ->where('product_colour_id', $colourId)
                ->when($variant, fn ($query) => $query->whereKeyNot($variant->id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['variants' => 'Each size and colour combination may only be created once.']);
            }

            $data = [
                'product_size_id' => $sizeId,
                'product_colour_id' => $colourId,
                'sku' => filled($row['sku'] ?? null) ? Str::upper(trim((string) $row['sku'])) : $this->sku($product, $sizeId, $colourId),
                'price_override' => filled($row['price_override'] ?? null) ? $row['price_override'] : null,
                'stock' => max(0, (int) ($row['stock'] ?? 0)),
                'is_active' => (bool) ($row['is_active'] ?? false),
            ];

            if ($variant) {
                $variant->update($data);
            } else {
                $product->variants()->create($data);
            }
        }

        $this->syncProductStock($product);
    }

    /** @param array<int, int> $sizeIds @param array<int, int> $colourIds */
    public function generate(Product $product, array $sizeIds, array $colourIds): int
    {
        $sizeIds = array_values(array_unique(array_filter(array_map('intval', $sizeIds))));
        $colourIds = array_values(array_unique(array_filter(array_map('intval', $colourIds))));
        $sizes = $sizeIds === [] ? [null] : $sizeIds;
        $colours = $colourIds === [] ? [null] : $colourIds;
        $created = 0;

        foreach ($sizes as $sizeId) {
            foreach ($colours as $colourId) {
                if ($sizeId === null && $colourId === null) {
                    continue;
                }

                $exists = $product->variants()
                    ->where('product_size_id', $sizeId)
                    ->where('product_colour_id', $colourId)
                    ->exists();
                if (! $exists) {
                    $product->variants()->create([
                        'product_size_id' => $sizeId,
                        'product_colour_id' => $colourId,
                        'sku' => $this->sku($product, $sizeId, $colourId),
                        'stock' => 0,
                        'is_active' => true,
                    ]);
                    $created++;
                }
            }
        }

        $this->syncProductStock($product);

        return $created;
    }

    public function syncProductStock(Product $product): void
    {
        if (! $product->variants()->exists()) {
            return;
        }

        $product->update(['stock' => (int) $product->variants()->active()->sum('stock')]);
    }

    private function sku(Product $product, ?int $sizeId, ?int $colourId): string
    {
        $base = 'CB-'.$product->id.'-'.($colourId ?? 'ONE').'-'.($sizeId ?? 'ONE');
        $sku = Str::upper($base);
        $suffix = 1;

        while (ProductVariant::query()->where('sku', $sku)->exists()) {
            $sku = Str::upper($base.'-'.$suffix++);
        }

        return $sku;
    }
}
