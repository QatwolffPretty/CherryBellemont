<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Cart $cart): View
    {
        $lines = $cart->lines();

        return view('cart.index', compact('lines') + $cart->totals($lines));
    }

    public function store(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        abort_unless($product->status === 'active', 404);
        $quantity = $request->validate(['quantity' => ['nullable', 'integer', 'min:1']])['quantity'] ?? 1;
        $newQuantity = ($cart->contents()[$product->id] ?? 0) + $quantity;

        if ($newQuantity > $product->stock) {
            return back()->withErrors(['quantity' => 'Only '.$product->stock.' of this piece are available.'])->withInput();
        }

        $cart->put($product->id, $newQuantity);

        return to_route('cart.index')->with('success', 'Piece added to your bag.');
    }

    public function update(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        $quantity = $request->validate(['quantity' => ['required', 'integer', 'min:1']])['quantity'];
        abort_unless($product->status === 'active', 404);

        if ($quantity > $product->stock) {
            return back()->withErrors(['quantity' => 'Only '.$product->stock.' of this piece are available.']);
        }

        $cart->put($product->id, $quantity);

        return to_route('cart.index')->with('success', 'Bag updated.');
    }

    public function destroy(Product $product, Cart $cart): RedirectResponse
    {
        $cart->forget($product->id);

        return to_route('cart.index')->with('success', 'Piece removed from your bag.');
    }

    public function clear(Cart $cart): RedirectResponse
    {
        $cart->clear();

        return to_route('cart.index')->with('success', 'Your bag has been cleared.');
    }
}
