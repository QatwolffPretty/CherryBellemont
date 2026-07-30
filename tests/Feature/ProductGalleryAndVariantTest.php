<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductColour;
use App\Models\ProductImage;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductGalleryAndVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_multiple_images_and_manage_a_primary_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Gallery Set', 'price' => 120, 'stock' => 5, 'status' => 'active',
            'images' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('detail.png'),
            ],
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->where('name', 'Gallery Set')->firstOrFail();
        $this->assertCount(2, $product->productImages);
        $this->assertSame(1, $product->productImages->where('is_primary', true)->count());
        Storage::disk('public')->assertExists($product->productImages->first()->image_path);

        $newPrimary = $product->productImages->last();
        $this->actingAs($admin)->patch(route('admin.products.images.primary', [$product, $newPrimary]))
            ->assertRedirect();
        $this->assertSame($newPrimary->id, $product->fresh()->primaryImage()->value('id'));

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name, 'price' => 120, 'stock' => 5, 'status' => 'active',
        ])->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseCount('product_images', 2);
    }

    public function test_variants_are_required_and_different_variants_remain_separate_cart_lines(): void
    {
        [$product, $blackSmall, $creamMedium] = $this->variantProduct();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Choose colour')
            ->assertSee('Choose size')
            ->assertSee('Select Colour &amp; Size', false)
            ->assertSee('id="add-to-bag"', false)
            ->assertSee('aria-disabled="true"', false);

        $this->post(route('cart.store', $product), ['quantity' => 1])
            ->assertSessionHasErrors('variant');

        $this->post(route('cart.store', $product), [
            'variant_id' => $blackSmall->id, 'size_id' => $blackSmall->product_size_id,
            'colour_id' => $blackSmall->product_colour_id, 'quantity' => 1,
        ])->assertRedirect(route('cart.index'));
        $this->post(route('cart.store', $product), [
            'variant_id' => $creamMedium->id, 'size_id' => $creamMedium->product_size_id,
            'colour_id' => $creamMedium->product_colour_id, 'quantity' => 2,
        ])->assertRedirect(route('cart.index'));

        $lines = app(Cart::class)->lines();
        $this->assertCount(2, $lines);
        $this->assertSame('Black', $lines->firstWhere('variant.id', $blackSmall->id)['colour_name']);
        $this->get(route('cart.index'))->assertOk()->assertSee('Colour: Black')->assertSee('Size: M');
    }

    public function test_products_with_one_variant_dimension_require_only_that_dimension(): void
    {
        $sizeProduct = Product::query()->create([
            'name' => 'Size Only Piece', 'price' => '80.00', 'stock' => 5, 'status' => 'active',
        ]);
        $size = ProductSize::query()->firstOrCreate(['code' => 'XS'], ['name' => 'XS', 'sort_order' => 1, 'is_active' => true]);
        $sizeProduct->sizes()->sync([$size->id]);
        $sizeVariant = $sizeProduct->variants()->create([
            'product_size_id' => $size->id, 'sku' => 'SIZE-ONLY-XS', 'stock' => 2, 'is_active' => true,
        ]);

        $this->get(route('products.show', $sizeProduct))
            ->assertOk()
            ->assertSee('Choose size')
            ->assertDontSee('Choose colour')
            ->assertSee('Select Size');
        $this->post(route('cart.store', $sizeProduct), ['quantity' => 1])
            ->assertSessionHasErrors(['variant' => 'Please choose a size before adding this piece.']);
        $this->post(route('cart.store', $sizeProduct), ['variant_id' => $sizeVariant->id, 'quantity' => 1])
            ->assertSessionHasErrors('size');

        $colourProduct = Product::query()->create([
            'name' => 'Colour Only Piece', 'price' => '85.00', 'stock' => 5, 'status' => 'active',
        ]);
        $colour = ProductColour::query()->firstOrCreate(['slug' => 'burgundy'], ['name' => 'Burgundy', 'hex_code' => '#4A1023', 'sort_order' => 1, 'is_active' => true]);
        $colourProduct->colours()->sync([$colour->id]);
        $colourVariant = $colourProduct->variants()->create([
            'product_colour_id' => $colour->id, 'sku' => 'COLOUR-ONLY-BUR', 'stock' => 2, 'is_active' => true,
        ]);

        $this->get(route('products.show', $colourProduct))
            ->assertOk()
            ->assertSee('Choose colour')
            ->assertDontSee('Choose size')
            ->assertSee('Select Colour');
        $this->post(route('cart.store', $colourProduct), ['quantity' => 1])
            ->assertSessionHasErrors(['variant' => 'Please choose a colour before adding this piece.']);
        $this->post(route('cart.store', $colourProduct), ['variant_id' => $colourVariant->id, 'quantity' => 1])
            ->assertSessionHasErrors('colour');
    }

    public function test_variant_from_a_different_product_and_out_of_stock_variant_are_rejected(): void
    {
        [$product, $blackSmall] = $this->variantProduct();
        [, $foreignVariant] = $this->variantProduct(['name' => 'Other Piece']);
        $blackSmall->update(['stock' => 0]);

        $this->post(route('cart.store', $product), [
            'variant_id' => $foreignVariant->id, 'size_id' => $foreignVariant->product_size_id,
            'colour_id' => $foreignVariant->product_colour_id, 'quantity' => 1,
        ])->assertSessionHasErrors('variant');

        $this->post(route('cart.store', $product), [
            'variant_id' => $blackSmall->id, 'size_id' => $blackSmall->product_size_id,
            'colour_id' => $blackSmall->product_colour_id, 'quantity' => 1,
        ])->assertSessionHasErrors('quantity');
    }

    public function test_checkout_snapshots_variant_and_deducts_only_the_selected_variant_stock(): void
    {
        [$product, $blackSmall, $creamMedium] = $this->variantProduct();
        $method = DeliveryMethod::query()->create(['name' => 'Self Pickup', 'code' => 'pickup-'.Str::random(5), 'is_pickup' => true, 'is_active' => true]);

        $this->withSession(['cart' => [
            'product-'.$product->id.'-variant-'.$creamMedium->id => [
                'product_id' => $product->id, 'variant_id' => $creamMedium->id, 'quantity' => 2,
            ],
        ]])->post(route('checkout.store'), [
            'customer_name' => 'Guest Customer', 'customer_email' => 'guest@example.test', 'customer_phone' => '0123456789',
            'delivery_method_id' => $method->id, 'payment_method' => 'duitnow',
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertSame($creamMedium->id, $item->product_variant_id);
        $this->assertSame('Cream', $item->colour_name);
        $this->assertSame('M', $item->size_name);
        $this->assertSame(4, $blackSmall->fresh()->stock);
        $this->assertSame(1, $creamMedium->fresh()->stock);
        $this->assertSame('120.00', $order->subtotal);
    }

    public function test_existing_product_without_variants_keeps_legacy_cart_behaviour_and_single_image_visibility(): void
    {
        $product = Product::query()->create([
            'name' => 'Legacy Piece', 'price' => '90.00', 'stock' => 3, 'status' => 'active', 'image_path' => 'products/legacy.jpg',
        ]);

        $this->post(route('cart.store', $product), ['quantity' => 2])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart.'.$product->id, 2);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Legacy Piece')
            ->assertSee('storage/products/legacy.jpg', false);
    }

    /** @return array{0: Product, 1: ProductVariant, 2: ProductVariant} */
    private function variantProduct(array $attributes = []): array
    {
        $product = Product::query()->create(array_merge([
            'name' => 'Studio Set '.Str::random(6), 'price' => '80.00', 'stock' => 7, 'status' => 'active',
        ], $attributes));
        $small = ProductSize::query()->firstOrCreate(['code' => 'S'], ['name' => 'S', 'sort_order' => 10, 'is_active' => true]);
        $medium = ProductSize::query()->firstOrCreate(['code' => 'M'], ['name' => 'M', 'sort_order' => 20, 'is_active' => true]);
        $black = ProductColour::query()->firstOrCreate(['slug' => 'black'], ['name' => 'Black', 'hex_code' => '#000000', 'sort_order' => 10, 'is_active' => true]);
        $cream = ProductColour::query()->firstOrCreate(['slug' => 'cream'], ['name' => 'Cream', 'hex_code' => '#F5F0E8', 'sort_order' => 20, 'is_active' => true]);
        $product->sizes()->sync([$small->id, $medium->id]);
        $product->colours()->sync([$black->id, $cream->id]);

        $blackSmall = $product->variants()->create(['product_size_id' => $small->id, 'product_colour_id' => $black->id, 'sku' => 'SKU-'.Str::upper(Str::random(10)), 'stock' => 4, 'is_active' => true]);
        $creamMedium = $product->variants()->create(['product_size_id' => $medium->id, 'product_colour_id' => $cream->id, 'sku' => 'SKU-'.Str::upper(Str::random(10)), 'price_override' => '60.00', 'stock' => 3, 'is_active' => true]);

        return [$product, $blackSmall, $creamMedium];
    }
}
