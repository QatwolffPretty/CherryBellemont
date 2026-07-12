<?php

use App\Http\Controllers\Admin\PaymentReceiptController as AdminPaymentReceiptController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ShippingZoneController;
use App\Http\Controllers\Admin\DeliveryMethodController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShippingQuoteController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/collection', [StorefrontController::class, 'collection'])->name('collection');
Route::get('/collection/{product:slug}', [StorefrontController::class, 'show'])->name('products.show');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
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
    Route::resource('products', AdminProductController::class)->except('show');
    Route::resource('shipping-zones', ShippingZoneController::class)->except('show');
    Route::resource('delivery-methods', DeliveryMethodController::class)->except('show');
    Route::get('payment-receipts', [AdminPaymentReceiptController::class, 'index'])->name('receipts.index');
    Route::patch('payment-receipts/{receipt}/approve', [AdminPaymentReceiptController::class, 'approve'])->name('receipts.approve');
    Route::patch('payment-receipts/{receipt}/reject', [AdminPaymentReceiptController::class, 'reject'])->name('receipts.reject');
});

require __DIR__.'/auth.php';
