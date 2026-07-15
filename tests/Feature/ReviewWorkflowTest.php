<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_with_a_paid_delivered_order_can_submit_one_verified_review(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        [$product, $order, $item] = $this->purchasedItem();

        $this->get(route('reviews.create', $this->reviewContext($product, $order)))
            ->assertOk()
            ->assertSee('Share your experience');

        $this->post(route('reviews.store', $product), $this->reviewData($order))
            ->assertRedirect(route('orders.guest.show', ['order' => $order->order_number, 'token' => $order->guest_access_token]));

        $review = Review::query()->firstOrFail();
        $this->assertSame($item->id, $review->order_item_id);
        $this->assertTrue($review->is_verified_purchase);
        $this->assertFalse($review->is_approved);
        $this->assertSame('pending', $review->status);
        Notification::assertSentTo($admin, ReviewSubmittedNotification::class);
    }

    public function test_guests_cannot_review_with_another_orders_token_or_a_product_not_in_the_order(): void
    {
        [$product, $order] = $this->purchasedItem();
        $otherProduct = $this->product();

        $this->post(route('reviews.store', $product), $this->reviewData($order, ['guest_access_token' => Str::random(64)]))
            ->assertForbidden();
        $this->post(route('reviews.store', $otherProduct), $this->reviewData($order))
            ->assertNotFound();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_unpaid_or_undelivered_orders_cannot_be_reviewed(): void
    {
        [$product, $order] = $this->purchasedItem(['payment_status' => 'pending', 'order_status' => 'pending']);

        $this->post(route('reviews.store', $product), $this->reviewData($order))
            ->assertSessionHasErrors(['review' => 'Reviews are available only after payment and delivery are complete.']);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_images_are_validated_stored_and_limited(): void
    {
        Storage::fake('public');
        [$product, $order] = $this->purchasedItem();

        $this->post(route('reviews.store', $product), $this->reviewData($order, [
            'images' => [UploadedFile::fake()->image('review.jpg')],
        ]))->assertRedirect();

        $review = Review::query()->with('images')->firstOrFail();
        $this->assertCount(1, $review->images);
        Storage::disk('public')->assertExists($review->images->first()->image_path);

        [$secondProduct, $secondOrder] = $this->purchasedItem();
        $this->post(route('reviews.store', $secondProduct), $this->reviewData($secondOrder, [
            'images' => array_fill(0, 6, UploadedFile::fake()->image('too-many.jpg')),
        ]))->assertSessionHasErrors('images');
    }

    public function test_duplicate_review_is_prevented_and_existing_review_can_be_edited(): void
    {
        [$product, $order] = $this->purchasedItem();
        $review = $this->review($product, $order);

        $this->post(route('reviews.store', $product), $this->reviewData($order))
            ->assertSessionHasErrors('review');

        $this->patch(route('reviews.update', ['product' => $product, 'review' => $review]), $this->reviewData($order, [
            'rating' => 4,
            'title' => 'Even better with time',
            'review' => 'A thoughtful update after more wear.',
        ]))->assertRedirect();

        $review->refresh();
        $this->assertSame(4, $review->rating);
        $this->assertSame('pending', $review->status);
        $this->assertFalse($review->is_approved);
    }

    public function test_admin_can_approve_and_reply_to_a_review(): void
    {
        [$product, $order] = $this->purchasedItem();
        $review = $this->review($product, $order);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.reviews.index'))->assertOk()->assertSee('Reviews');
        $this->actingAs($admin)->get(route('admin.reviews.show', $review))->assertOk()->assertSee('Review moderation');
        $this->actingAs($admin)->patch(route('admin.reviews.approve', $review))->assertRedirect();
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => 'approved', 'is_approved' => true]);

        $this->actingAs($admin)->patch(route('admin.reviews.reply', $review), [
            'admin_reply' => 'Thank you for choosing Cherry Bellemont.',
        ])->assertRedirect();
        $this->assertSame('Thank you for choosing Cherry Bellemont.', $review->fresh()->admin_reply);
    }

    public function test_helpful_voting_uses_the_session_to_prevent_repeat_votes(): void
    {
        [$product, $order] = $this->purchasedItem();
        $review = $this->review($product, $order, ['status' => 'approved', 'is_approved' => true, 'approved_at' => now()]);

        $this->withSession(['review_helpful_votes' => []])->post(route('reviews.helpful', $review))->assertRedirect();
        $this->post(route('reviews.helpful', $review))->assertRedirect();

        $this->assertSame(1, $review->fresh()->helpful_count);
    }

    public function test_product_average_rating_uses_only_approved_reviews(): void
    {
        [$product, $firstOrder] = $this->purchasedItem();
        $this->review($product, $firstOrder, ['rating' => 5, 'status' => 'approved', 'is_approved' => true]);
        [, $secondOrder] = $this->purchasedItem([], $product);
        $this->review($product, $secondOrder, ['rating' => 3, 'status' => 'approved', 'is_approved' => true]);
        [, $pendingOrder] = $this->purchasedItem([], $product);
        $this->review($product, $pendingOrder, ['rating' => 1, 'status' => 'pending', 'is_approved' => false]);

        $ratedProduct = Product::query()->withCount('approvedReviews')->withAvg('approvedReviews as approved_reviews_avg_rating', 'rating')->findOrFail($product->id);
        $this->assertSame(2, $ratedProduct->approved_reviews_count);
        $this->assertSame(4.0, (float) $ratedProduct->approved_reviews_avg_rating);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('4.0 (2 Reviews)')
            ->assertSee('Verified Purchase');
    }

    public function test_non_admins_cannot_moderate_reviews(): void
    {
        [$product, $order] = $this->purchasedItem();
        $review = $this->review($product, $order);
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.reviews.index'))->assertForbidden();
        $this->actingAs($customer)->patch(route('admin.reviews.approve', $review))->assertForbidden();
    }

    private function purchasedItem(array $orderAttributes = [], ?Product $product = null): array
    {
        $product ??= $this->product();
        $number = 'CB-REVIEW-'.Str::upper(Str::random(8));
        $order = Order::create(array_merge([
            'number' => $number, 'order_number' => $number, 'guest_access_token' => Str::random(64),
            'customer_name' => 'Review Guest', 'customer_email' => 'review@example.test', 'customer_phone' => '0123456789',
            'address_line_1' => '1 Review Street', 'city' => 'Ampang', 'state' => 'Selangor', 'postcode' => '68000', 'country' => 'Malaysia',
            'shipping_address' => ['address_line_1' => '1 Review Street'], 'shipping_method_name' => 'Standard Delivery',
            'subtotal' => '100.00', 'shipping_fee' => '0.00', 'total' => '100.00', 'payment_method' => 'duitnow', 'payment_provider' => 'duitnow',
            'payment_status' => 'paid', 'order_status' => 'delivered', 'status' => 'pending', 'delivered_at' => now(),
        ], $orderAttributes));
        $item = $order->items()->create([
            'product_id' => $product->id, 'name' => $product->name, 'product_name' => $product->name,
            'quantity' => 3, 'unit_price' => '100.00', 'total' => '300.00', 'line_total' => '300.00',
        ]);

        return [$product, $order, $item];
    }

    private function review(Product $product, Order $order, array $attributes = []): Review
    {
        $item = $order->items()->where('product_id', $product->id)->firstOrFail();

        return Review::create(array_merge([
            'product_id' => $product->id, 'order_id' => $order->id, 'order_item_id' => $item->id,
            'customer_name' => $order->customer_name, 'customer_email' => $order->customer_email,
            'rating' => 5, 'title' => 'A beautiful piece', 'review' => 'Elegant, considered, and beautifully made.',
            'is_verified_purchase' => true, 'is_approved' => false, 'status' => 'pending', 'helpful_count' => 0,
        ], $attributes));
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Review Piece '.Str::random(6), 'description' => 'A considered piece.', 'price' => '100.00', 'stock' => 10, 'status' => 'active',
        ]);
    }

    private function reviewContext(Product $product, Order $order): array
    {
        return ['product' => $product, 'order_number' => $order->order_number, 'guest_access_token' => $order->guest_access_token, 'customer_email' => $order->customer_email];
    }

    private function reviewData(Order $order, array $overrides = []): array
    {
        return array_merge([
            'order_number' => $order->order_number,
            'guest_access_token' => $order->guest_access_token,
            'customer_email' => $order->customer_email,
            'rating' => 5,
            'title' => 'A beautiful piece',
            'review' => 'Elegant, considered, and beautifully made.',
        ], $overrides);
    }
}
