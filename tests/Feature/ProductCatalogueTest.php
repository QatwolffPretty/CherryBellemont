<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductColour;
use App\Models\ProductSize;
use App\Models\ProductTag;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\ProductCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductCatalogueSeeder::class);
    }

    public function test_admin_can_create_a_category_but_customers_cannot_manage_catalogue(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.categories.index'))->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Loungewear', 'slug' => 'loungewear', 'is_active' => '1', 'sort_order' => 50,
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Loungewear', 'slug' => 'loungewear', 'is_active' => 1]);
    }

    public function test_catalogue_seed_creates_editable_starter_categories_attributes_and_tags(): void
    {
        $this->assertDatabaseHas('categories', ['slug' => 'women']);
        $this->assertDatabaseHas('categories', ['slug' => 'pilates']);
        $this->assertDatabaseHas('categories', ['slug' => 'sportswear']);
        $this->assertDatabaseHas('product_sizes', ['code' => 'XS']);
        $this->assertDatabaseHas('product_colours', ['slug' => 'burgundy']);
        $this->assertDatabaseHas('product_tags', ['slug' => 'new-arrival']);
    }

    public function test_admin_can_manage_reusable_sizes_colours_and_collection_tags(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.product-sizes.store'), ['name' => 'XXL', 'code' => 'xxl', 'sort_order' => 60, 'is_active' => '1'])
            ->assertRedirect(route('admin.product-sizes.index'));
        $this->actingAs($admin)->post(route('admin.product-colours.store'), ['name' => 'Rose', 'slug' => 'rose', 'hex_code' => '#B76E79', 'sort_order' => 80, 'is_active' => '1'])
            ->assertRedirect(route('admin.product-colours.index'));
        $this->actingAs($admin)->post(route('admin.product-tags.store'), ['name' => 'Studio Edit', 'slug' => 'studio-edit', 'sort_order' => 50, 'is_active' => '1'])
            ->assertRedirect(route('admin.product-tags.index'));

        $this->assertDatabaseHas('product_sizes', ['name' => 'XXL', 'code' => 'XXL']);
        $this->assertDatabaseHas('product_colours', ['name' => 'Rose', 'hex_code' => '#B76E79']);
        $this->assertDatabaseHas('product_tags', ['name' => 'Studio Edit']);
    }

    public function test_inactive_category_is_hidden_and_cannot_be_browsed_publicly(): void
    {
        $category = Category::query()->where('slug', 'pilates')->firstOrFail();
        $category->update(['is_active' => false]);
        $product = $this->product(['name' => 'Private Assigned Piece']);
        $product->categories()->attach($category->id, ['is_primary' => true]);

        $this->get(route('collection'))->assertOk()->assertDontSee('Pilates');
        $this->get(route('collection.category', ['slug' => 'pilates']))->assertNotFound();
    }

    public function test_category_page_shows_matching_products_only_and_old_uncategorised_products_remain_available(): void
    {
        $pilates = Category::query()->where('slug', 'pilates')->firstOrFail();
        $sportswear = Category::query()->where('slug', 'sportswear')->firstOrFail();
        $matched = $this->product(['name' => 'Pilates Set']);
        $other = $this->product(['name' => 'Sports Set']);
        $old = $this->product(['name' => 'Original Uncategorised Piece']);
        $matched->categories()->attach($pilates->id, ['is_primary' => true]);
        $other->categories()->attach($sportswear->id, ['is_primary' => true]);

        $this->get(route('collection.category', ['slug' => 'pilates']))->assertOk()->assertSee('Pilates Set')->assertDontSee('Sports Set');
        $this->get(route('products.show', $old))->assertOk()->assertSee('Original Uncategorised Piece');
    }

    public function test_search_is_case_and_whitespace_normalized_across_category_and_colour(): void
    {
        $category = Category::query()->where('slug', 'pilates')->firstOrFail();
        $colour = ProductColour::query()->where('slug', 'burgundy')->firstOrFail();
        $product = $this->product(['name' => 'Studio Essential']);
        $product->categories()->attach($category->id, ['is_primary' => true]);
        $product->colours()->attach($colour->id);

        $this->get(route('collection', ['search' => '  PILATES  ']))->assertOk()->assertSee('Studio Essential');
        $this->get(route('collection', ['search' => ' burgundy ']))->assertOk()->assertSee('Studio Essential');
        $this->get(route('collection', ['search' => 'not-a-piece']))->assertOk()->assertSee('No pieces match your selection.');
    }

    public function test_multi_value_filters_use_or_inside_a_group_and_and_between_groups(): void
    {
        $pilates = Category::query()->where('slug', 'pilates')->firstOrFail();
        $sports = Category::query()->where('slug', 'sportswear')->firstOrFail();
        $small = ProductSize::query()->where('code', 'S')->firstOrFail();
        $medium = ProductSize::query()->where('code', 'M')->firstOrFail();
        $black = ProductColour::query()->where('slug', 'black')->firstOrFail();
        $cream = ProductColour::query()->where('slug', 'cream')->firstOrFail();
        $wanted = $this->product(['name' => 'Black Pilates M']);
        $wrongColour = $this->product(['name' => 'Cream Pilates S']);
        $wrongCategory = $this->product(['name' => 'Black Sports S']);
        $wanted->categories()->attach($pilates->id, ['is_primary' => true]); $wanted->sizes()->attach($medium->id); $wanted->colours()->attach($black->id);
        $wrongColour->categories()->attach($pilates->id, ['is_primary' => true]); $wrongColour->sizes()->attach($small->id); $wrongColour->colours()->attach($cream->id);
        $wrongCategory->categories()->attach($sports->id, ['is_primary' => true]); $wrongCategory->sizes()->attach($small->id); $wrongCategory->colours()->attach($black->id);

        $this->get(route('collection', ['category' => ['pilates'], 'size' => ['s', 'm'], 'colour' => ['black']]))
            ->assertOk()->assertSee('Black Pilates M')->assertDontSee('Cream Pilates S')->assertDontSee('Black Sports S');
    }

    public function test_price_availability_and_tag_filters_are_applied_server_side(): void
    {
        $tag = ProductTag::query()->where('slug', 'sale')->firstOrFail();
        $inStock = $this->product(['name' => 'Sale In Stock', 'price' => '150.00', 'stock' => 2]);
        $soldOut = $this->product(['name' => 'Sale Sold Out', 'price' => '175.00', 'stock' => 0]);
        $outside = $this->product(['name' => 'Outside Range', 'price' => '280.00', 'stock' => 4]);
        $inStock->tags()->attach($tag->id); $soldOut->tags()->attach($tag->id); $outside->tags()->attach($tag->id);

        $this->get(route('collection', ['tag' => ['sale'], 'min_price' => 100, 'max_price' => 200, 'availability' => 'in_stock']))
            ->assertOk()->assertSee('Sale In Stock')->assertDontSee('Sale Sold Out')->assertDontSee('Outside Range');
    }

    public function test_filters_persist_through_pagination_and_clear_all_returns_to_clean_collection(): void
    {
        $category = Category::query()->where('slug', 'pilates')->firstOrFail();
        foreach (range(1, 13) as $number) {
            $product = $this->product(['name' => 'Paginated Pilates '.$number]);
            $product->categories()->attach($category->id, ['is_primary' => true]);
        }

        $response = $this->get(route('collection', ['category' => ['pilates'], 'search' => 'Paginated Pilates']));
        $response->assertOk()->assertSee('page=2', false)->assertSee(route('collection'), false);
    }

    public function test_sorting_supports_price_featured_best_selling_and_approved_rating_only(): void
    {
        $low = $this->product(['name' => 'Low Price Piece', 'price' => '50.00']);
        $high = $this->product(['name' => 'High Price Piece', 'price' => '250.00', 'featured' => true]);
        $unpaid = $this->product(['name' => 'Unpaid Best Seller Candidate']);
        $this->orderItem($high, 'paid', 5);
        $this->orderItem($unpaid, 'pending', 20);
        $review = $this->orderItem($low, 'paid', 1);
        Review::query()->create(['product_id' => $low->id, 'order_id' => $review->order_id, 'order_item_id' => $review->id, 'customer_name' => 'Client', 'customer_email' => 'client@example.com', 'rating' => 5, 'title' => 'Excellent', 'review' => 'Beautiful.', 'is_verified_purchase' => true, 'is_approved' => true, 'status' => 'approved']);

        $this->get(route('collection', ['sort' => 'price_asc']))->assertSeeInOrder(['Low Price Piece', 'High Price Piece']);
        $this->get(route('collection', ['sort' => 'featured']))->assertSeeInOrder(['High Price Piece', 'Low Price Piece']);
        $this->get(route('collection', ['sort' => 'best_selling']))->assertSeeInOrder(['High Price Piece', 'Unpaid Best Seller Candidate']);
        $this->get(route('collection', ['sort' => 'highest_rated']))->assertSeeInOrder(['Low Price Piece', 'High Price Piece']);
        $this->get(route('collection', ['sort' => 'not-a-sort']))->assertOk();
    }

    public function test_admin_can_assign_primary_and_additional_catalogue_options_to_product(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::query()->where('slug', 'pilates')->firstOrFail();
        $additional = Category::query()->where('slug', 'sportswear')->firstOrFail();
        $size = ProductSize::query()->where('code', 'M')->firstOrFail();
        $colour = ProductColour::query()->where('slug', 'black')->firstOrFail();
        $tag = ProductTag::query()->where('slug', 'new-arrival')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Assigned Product', 'description' => 'Details', 'price' => 130, 'stock' => 6, 'status' => 'active',
            'primary_category_id' => $category->id, 'category_ids' => [$additional->id], 'size_ids' => [$size->id], 'colour_ids' => [$colour->id], 'tag_ids' => [$tag->id],
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::query()->where('name', 'Assigned Product')->firstOrFail();
        $this->assertDatabaseHas('category_product', ['product_id' => $product->id, 'category_id' => $category->id, 'is_primary' => 1]);
        $this->assertDatabaseHas('category_product', ['product_id' => $product->id, 'category_id' => $additional->id, 'is_primary' => 0]);
        $this->assertDatabaseHas('product_product_size', ['product_id' => $product->id, 'product_size_id' => $size->id]);
    }

    public function test_inactive_attributes_do_not_appear_on_public_product_pages(): void
    {
        $colour = ProductColour::query()->where('slug', 'olive')->firstOrFail();
        $colour->update(['is_active' => false]);
        $product = $this->product(['name' => 'Private Olive Option']);
        $product->colours()->attach($colour->id);

        $this->get(route('products.show', $product))->assertOk()->assertDontSee('Available colours');
    }

    private function product(array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Catalogue Piece '.Str::random(8), 'description' => 'A considered piece.', 'price' => '100.00', 'status' => 'active', 'stock' => 8,
        ], $attributes));
    }

    private function orderItem(Product $product, string $paymentStatus, int $quantity): OrderItem
    {
        $user = User::factory()->create();
        $order = Order::query()->create([
            'user_id' => $user->id, 'number' => 'CB-'.Str::upper(Str::random(10)), 'order_number' => 'CB-'.Str::upper(Str::random(10)),
            'status' => $paymentStatus === 'paid' ? 'paid' : 'pending', 'order_status' => 'delivered', 'payment_method' => 'duitnow', 'payment_provider' => 'duitnow', 'payment_status' => $paymentStatus,
            'subtotal' => $product->price, 'total' => $product->price, 'shipping_address' => [], 'shipping_fee' => '0.00', 'customer_name' => 'Client', 'customer_email' => Str::lower(Str::random(8)).'@example.com',
        ]);

        return OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name, 'quantity' => $quantity, 'unit_price' => $product->price, 'total' => $product->price * $quantity, 'line_total' => $product->price * $quantity]);
    }
}
