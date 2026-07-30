<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductGalleryService
{
    /** @param array<int, UploadedFile> $files @param array<int, string|null> $altTexts */
    public function addUploads(Product $product, array $files, array $altTexts = []): Collection
    {
        $this->preserveLegacyImage($product);

        if ($files === []) {
            return collect();
        }

        $existingCount = $product->productImages()->count();
        if (($existingCount + count($files)) > 10) {
            throw ValidationException::withMessages(['images' => 'A product can have up to 10 images.']);
        }

        $firstIsPrimary = $existingCount === 0;
        $nextSort = (int) ($product->productImages()->max('sort_order') ?? -1) + 1;

        return collect($files)->values()->map(function (UploadedFile $file, int $index) use ($product, $altTexts, &$nextSort, &$firstIsPrimary): ProductImage {
            $image = $product->productImages()->create([
                'image_path' => $file->store('products/'.$product->id, 'public'),
                'alt_text' => filled($altTexts[$index] ?? null) ? trim((string) $altTexts[$index]) : $product->name,
                'sort_order' => $nextSort++,
                'is_primary' => $firstIsPrimary,
            ]);
            $firstIsPrimary = false;

            return $image;
        })->tap(fn () => $this->syncLegacyPrimaryPath($product));
    }

    public function setPrimary(Product $product, ProductImage $image): void
    {
        $this->assertOwned($product, $image);
        $product->productImages()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
        $this->syncLegacyPrimaryPath($product);
    }

    public function sort(Product $product, array $positions): void
    {
        $images = $product->productImages()->whereIn('id', array_keys($positions))->get()->keyBy('id');
        foreach ($positions as $imageId => $position) {
            if ($image = $images->get((int) $imageId)) {
                $image->update(['sort_order' => max(0, (int) $position)]);
            }
        }
    }

    public function delete(Product $product, ProductImage $image): void
    {
        $this->assertOwned($product, $image);
        $wasPrimary = $image->is_primary;
        $path = $image->image_path;
        $image->delete();

        if ($wasPrimary && ($replacement = $product->productImages()->orderBy('sort_order')->orderBy('id')->first())) {
            $this->setPrimary($product, $replacement);
        } else {
            $this->syncLegacyPrimaryPath($product);
        }

        $stillReferenced = ProductImage::query()->where('image_path', $path)->exists()
            || Product::query()->where('image_path', $path)->exists();
        if (! $stillReferenced) {
            Storage::disk('public')->delete($path);
        }
    }

    public function deleteAll(Product $product): void
    {
        $images = $product->productImages()->get();
        foreach ($images as $image) {
            $path = $image->image_path;
            $image->delete();
            if (! ProductImage::query()->where('image_path', $path)->exists()) {
                Storage::disk('public')->delete($path);
            }
        }

        if ($product->image_path
            && ! ProductImage::query()->where('image_path', $product->image_path)->exists()
            && ! Product::query()->where('image_path', $product->image_path)->whereKeyNot($product->id)->exists()) {
            Storage::disk('public')->delete($product->image_path);
        }
    }

    private function assertOwned(Product $product, ProductImage $image): void
    {
        abort_unless($image->product_id === $product->id, 404);
    }

    private function syncLegacyPrimaryPath(Product $product): void
    {
        $primary = $product->productImages()->where('is_primary', true)->orderBy('sort_order')->first()
            ?? $product->productImages()->orderBy('sort_order')->first();

        $product->update(['image_path' => $primary?->image_path]);
    }

    private function preserveLegacyImage(Product $product): void
    {
        if (! $product->image_path || $product->productImages()->where('image_path', $product->image_path)->exists()) {
            return;
        }

        $product->productImages()->create([
            'image_path' => $product->image_path,
            'alt_text' => $product->name,
            'sort_order' => (int) ($product->productImages()->max('sort_order') ?? -1) + 1,
            'is_primary' => ! $product->productImages()->exists(),
        ]);
    }
}
