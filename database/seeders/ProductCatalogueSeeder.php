<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ProductColour;
use App\Models\ProductSize;
use App\Models\ProductTag;
use Illuminate\Database\Seeder;

class ProductCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $women = Category::query()->firstOrCreate(
            ['slug' => 'women'],
            ['name' => 'Women', 'sort_order' => 0, 'is_active' => true]
        );

        foreach ([
            ['name' => 'Pilates', 'slug' => 'pilates', 'description' => 'Refined movement wear designed for studio sessions, low-impact training and effortless everyday comfort.', 'sort_order' => 10],
            ['name' => 'Sportswear', 'slug' => 'sportswear', 'description' => 'Performance-inspired pieces balancing support, flexibility and modern feminine style.', 'sort_order' => 20],
            ['name' => 'Bathrobes', 'slug' => 'bathrobes', 'description' => 'Soft, elegant robes created for slow mornings, post-shower comfort and relaxed luxury.', 'sort_order' => 30],
            ['name' => 'Streetwear', 'slug' => 'streetwear', 'description' => 'Confident everyday silhouettes combining comfort, edge and contemporary women’s style.', 'sort_order' => 40],
        ] as $category) {
            Category::query()->firstOrCreate(['slug' => $category['slug']], [...$category, 'parent_id' => $women->id, 'is_active' => true]);
        }

        foreach (['XS', 'S', 'M', 'L', 'XL'] as $position => $size) {
            ProductSize::query()->firstOrCreate(['code' => $size], ['name' => $size, 'sort_order' => ($position + 1) * 10, 'is_active' => true]);
        }

        $colours = ['Black' => '#000000', 'White' => '#FFFFFF', 'Beige' => '#D6C5A8', 'Cream' => '#F8F4EF', 'Burgundy' => '#5B1E2D', 'Olive' => '#6B7042', 'Grey' => '#808080'];
        foreach ($colours as $position => $hex) {
            ProductColour::query()->firstOrCreate(['slug' => str($position)->slug()->toString()], ['name' => $position, 'hex_code' => $hex, 'sort_order' => (array_search($position, array_keys($colours), true) + 1) * 10, 'is_active' => true]);
        }

        foreach (['New Arrival', 'Best Seller', 'Limited Edition', 'Sale'] as $position => $tag) {
            ProductTag::query()->firstOrCreate(['slug' => str($tag)->slug()->toString()], ['name' => $tag, 'sort_order' => ($position + 1) * 10, 'is_active' => true]);
        }
    }
}
