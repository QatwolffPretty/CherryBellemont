<?php

use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DeliveryMethodController;
use App\Http\Controllers\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentReceiptController as AdminPaymentReceiptController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ReportsController as AdminReportsController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\ShippingZoneController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShippingQuoteController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StripeCheckoutController;
use App\Http\Controllers\StripeWebhookController;
use App\Mail\AdminOperationalPreviewMail;
use App\Mail\TransactionalPreviewMail;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
Route::get('/faq', [FaqController::class, 'index'])->name('faq.index');
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:6,1')->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
Route::view('/contact', 'storefront.information', ['title' => 'Contact', 'heading' => 'Contact Cherry Bellemont', 'message' => 'Our client care details are being prepared. Please return soon for assistance and contact information.'])->name('contact');
Route::view('/shipping-policy', 'policies.shipping')->name('shipping.policy');
Route::view('/refund-policy', 'policies.refund')->name('refund.policy');
Route::view('/privacy-policy', 'policies.privacy')->name('privacy.policy');
Route::view('/terms-and-conditions', 'policies.terms')->name('terms.policy');
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
Route::get('/orders/{order:number}/access/{token}/invoice', [OrderController::class, 'guestInvoice'])->name('orders.guest.invoice');
Route::post('/orders/{order:number}/access/{token}/payment-receipt', [PaymentReceiptController::class, 'store'])->middleware('throttle:6,1')->name('orders.payment-receipts.store');
Route::get('/orders/{order:number}/access/{token}/stripe/checkout', [StripeCheckoutController::class, 'start'])->middleware('throttle:6,1')->name('stripe.checkout.start');
Route::post('/orders/{order:number}/access/{token}/stripe/retry', [StripeCheckoutController::class, 'retry'])->middleware('throttle:6,1')->name('stripe.retry');
Route::get('/orders/{order:number}/access/{token}/stripe/cancel', [StripeCheckoutController::class, 'cancel'])->name('stripe.cancel');
Route::get('/stripe/success', [StripeCheckoutController::class, 'success'])->name('stripe.success');
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->withoutMiddleware(VerifyCsrfToken::class)
    ->name('stripe.webhook');

if (app()->environment('local')) {
    Route::prefix('dev/email')->as('dev.email.')->group(function (): void {
        Route::get('order-received', fn () => new TransactionalPreviewMail('order-received'))->name('order-received');
        Route::get('receipt-submitted', fn () => new TransactionalPreviewMail('receipt-submitted'))->name('receipt-submitted');
        Route::get('payment-approved', fn () => new TransactionalPreviewMail('payment-approved'))->name('payment-approved');
        Route::get('receipt-rejected', fn () => new TransactionalPreviewMail('receipt-rejected'))->name('receipt-rejected');
        Route::get('stripe-payment-confirmed', fn () => new TransactionalPreviewMail('stripe-payment-confirmed'))->name('stripe-payment-confirmed');
        Route::get('processing', fn () => new TransactionalPreviewMail('processing'))->name('processing');
        Route::get('packed', fn () => new TransactionalPreviewMail('packed'))->name('packed');
        Route::get('shipped', fn () => new TransactionalPreviewMail('shipped'))->name('shipped');
        Route::get('delivered', fn () => new TransactionalPreviewMail('delivered'))->name('delivered');
        Route::get('cancelled', fn () => new TransactionalPreviewMail('cancelled'))->name('cancelled');
        Route::get('admin/new-order', fn () => new AdminOperationalPreviewMail('new-order'))->name('admin.new-order');
        Route::get('admin/new-duitnow-receipt', fn () => new AdminOperationalPreviewMail('new-duitnow-receipt'))->name('admin.new-duitnow-receipt');
        Route::get('admin/stripe-paid', fn () => new AdminOperationalPreviewMail('stripe-paid'))->name('admin.stripe-paid');
        Route::get('admin/low-stock', fn () => new AdminOperationalPreviewMail('low-stock'))->name('admin.low-stock');
        Route::get('admin/out-of-stock', fn () => new AdminOperationalPreviewMail('out-of-stock'))->name('admin.out-of-stock');
        Route::get('admin/new-review', fn () => new AdminOperationalPreviewMail('new-review'))->name('admin.new-review');
        Route::get('admin/new-newsletter-subscriber', fn () => new AdminOperationalPreviewMail('new-newsletter-subscriber'))->name('admin.new-newsletter-subscriber');
        Route::get('admin/cancelled-order', fn () => new AdminOperationalPreviewMail('cancelled-order'))->name('admin.cancelled-order');
        Route::get('admin/payment-attention', fn () => new AdminOperationalPreviewMail('payment-attention'))->name('admin.payment-attention');
    });
}

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::get('/orders/{order}/confirmation', [OrderController::class, 'confirmation'])->name('orders.confirmation');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->as('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::redirect('dashboard', '/admin');
    Route::resource('products', AdminProductController::class)->except('show');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}/invoice', [AdminOrderController::class, 'invoice'])->name('orders.invoice');
    Route::get('orders/{order}/packing-slip', [AdminOrderController::class, 'packingSlip'])->name('orders.packing-slip');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::resource('shipping-zones', ShippingZoneController::class)->except('show');
    Route::resource('delivery-methods', DeliveryMethodController::class)->except('show');
    Route::resource('coupons', AdminCouponController::class)->except('show');
    Route::get('newsletter', [NewsletterSubscriberController::class, 'index'])->name('newsletter.index');
    Route::patch('newsletter/{newsletterSubscriber}/unsubscribe', [NewsletterSubscriberController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
    Route::get('newsletter/export', [NewsletterSubscriberController::class, 'export'])->name('newsletter.export');
    Route::delete('newsletter/{newsletterSubscriber}', [NewsletterSubscriberController::class, 'destroy'])->name('newsletter.destroy');
    Route::resource('faqs', AdminFaqController::class)->except('show');
    Route::patch('reviews/bulk', [AdminReviewController::class, 'bulk'])->name('reviews.bulk');
    Route::get('reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::get('reviews/{review}', [AdminReviewController::class, 'show'])->name('reviews.show');
    Route::patch('reviews/{review}/approve', [AdminReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('reviews/{review}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
    Route::patch('reviews/{review}/hide', [AdminReviewController::class, 'hide'])->name('reviews.hide');
    Route::patch('reviews/{review}/reply', [AdminReviewController::class, 'reply'])->name('reviews.reply');
    Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::get('customers/export', [AdminCustomerController::class, 'export'])->name('customers.export');
    Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{email}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::post('customers/{email}/notes', [AdminCustomerController::class, 'storeNote'])->name('customers.notes.store');
    Route::get('reports/export/{report}', [AdminReportsController::class, 'export'])->name('reports.export');
    Route::get('reports', [AdminReportsController::class, 'index'])->name('reports.index');
    Route::get('payment-receipts', [AdminPaymentReceiptController::class, 'index'])->name('payment-receipts.index');
    Route::get('payment-receipts/{paymentReceipt}', [AdminPaymentReceiptController::class, 'show'])->name('payment-receipts.show');
    Route::patch('payment-receipts/{paymentReceipt}/approve', [AdminPaymentReceiptController::class, 'approve'])->name('payment-receipts.approve');
    Route::patch('payment-receipts/{paymentReceipt}/reject', [AdminPaymentReceiptController::class, 'reject'])->name('payment-receipts.reject');
    Route::get('payment-receipts/{paymentReceipt}/download', [AdminPaymentReceiptController::class, 'download'])->name('payment-receipts.download');
});

require __DIR__.'/auth.php';
