<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentReceiptController extends Controller
{
    public function index(): View { return view('admin.receipts.index', ['receipts' => PaymentReceipt::with('order.user')->latest()->paginate(20)]); }
    public function approve(PaymentReceipt $receipt): RedirectResponse { $receipt->update(['status' => 'approved', 'reviewed_at' => now()]); $receipt->order->update(['status' => 'paid']); return back()->with('success', 'Receipt approved and order marked paid.'); }
    public function reject(PaymentReceipt $receipt): RedirectResponse { $receipt->update(['status' => 'rejected', 'reviewed_at' => now()]); return back()->with('success', 'Receipt rejected.'); }
}
