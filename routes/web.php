<?php

use App\Http\Controllers\Admin\PaymentReceiptController as AdminPaymentReceiptController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ShippingZoneController;
use App\Http\Controllers\Admin\DeliveryMethodController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShippingQuoteController;
use App\Http\Controllers\StripeCheckoutController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/collection', [StorefrontController::class, 'collection'])->name('collection');
Route::redirect('/shop', '/collection');
Route::get('/collection/{product:slug}', [StorefrontController::class, 'show'])->name('products.show');
Route::get('/collection/{product:slug}/review', [ReviewController::class, 'create'])->name('reviews.create');
Route::post('/collection/{product:slug}/review', [ReviewController::class, 'store'])->middleware('throttle:6,1')->name('reviews.store');
Route::patch('/collection/{product:slug}/reviews/{review}', [ReviewController::class, 'update'])->middleware('throttle:6,1')->name('reviews.update');
Route::post('/reviews/{review}/helpful', [ReviewController::class, 'helpful'])->middleware('throttle:20,1')->name('reviews.helpful');
Route::view('/about', 'storefront.about')->name('about');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:10,1')->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
Route::post('/cart/{product}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:6,1')->name('checkout.store');
Route::post('/shipping/quote', ShippingQuoteController::class)->middleware('throttle:20,1')->name('shipping.quote');
Route::get('/orders/{order:number}/access/{token}', [OrderController::class, 'guestShow'])->name('orders.guest.show');
Route::get('/orders/{order:number}/access/{token}/confirmation', [OrderController::class, 'guestConfirmation'])->name('orders.guest.confirmation');
Route::get('/orders/{order:number}/access/{token}/duitnow', [OrderController::class, 'guestDuitNowInstructions'])->name('orders.guest.duitnow');
Route::post('/orders/{order:number}/access/{token}/payment-receipt', [PaymentReceiptController::class, 'store'])->middleware('throttle:6,1')->name('orders.payment-receipts.store');
Route::get('/orders/{order:number}/access/{token}/stripe/checkout', [StripeCheckoutController::class, 'start'])->middleware('throttle:6,1')->name('stripe.checkout.start');
Route::post('/orders/{order:number}/access/{token}/stripe/retry', [StripeCheckoutController::class, 'retry'])->middleware('throttle:6,1')->name('stripe.retry');
Route::get('/orders/{order:number}/access/{token}/stripe/cancel', [StripeCheckoutController::class, 'cancel'])->name('stripe.cancel');
Route::get('/stripe/success', [StripeCheckoutController::class, 'success'])->name('stripe.success');
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('stripe.webhook');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/confirmation', [OrderController::class, 'confirmation'])->name('orders.confirmation');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->as('admin.')->group(function (): void {
    Route::view('/', 'admin.dashboard')->name('dashboard');
    Route::redirect('dashboard', '/admin');
    Route::resource('products', AdminProductController::class)->except('show');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::resource('shipping-zones', ShippingZoneController::class)->except('show');
    Route::resource('delivery-methods', DeliveryMethodController::class)->except('show');
    Route::resource('coupons', AdminCouponController::class)->except('show');
    Route::patch('reviews/bulk', [AdminReviewController::class, 'bulk'])->name('reviews.bulk');
    Route::get('reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::get('reviews/{review}', [AdminReviewController::class, 'show'])->name('reviews.show');
    Route::patch('reviews/{review}/approve', [AdminReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('reviews/{review}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
    Route::patch('reviews/{review}/hide', [AdminReviewController::class, 'hide'])->name('reviews.hide');
    Route::patch('reviews/{review}/reply', [AdminReviewController::class, 'reply'])->name('reviews.reply');
    Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::view('customers', 'admin.customers')->name('customers.index');
    Route::view('reports', 'admin.reports')->name('reports.index');
    Route::get('payment-receipts', [AdminPaymentReceiptController::class, 'index'])->name('payment-receipts.index');
    Route::get('payment-receipts/{paymentReceipt}', [AdminPaymentReceiptController::class, 'show'])->name('payment-receipts.show');
    Route::patch('payment-receipts/{paymentReceipt}/approve', [AdminPaymentReceiptController::class, 'approve'])->name('payment-receipts.approve');
    Route::patch('payment-receipts/{paymentReceipt}/reject', [AdminPaymentReceiptController::class, 'reject'])->name('payment-receipts.reject');
    Route::get('payment-receipts/{paymentReceipt}/download', [AdminPaymentReceiptController::class, 'download'])->name('payment-receipts.download');
});

require __DIR__.'/auth.php';
