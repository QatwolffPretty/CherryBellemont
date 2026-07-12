<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentReceiptController extends Controller
{
    public function store(Request $request, Order $order, string $token): RedirectResponse
    {
        abort_unless($order->guest_access_token && hash_equals($order->guest_access_token, $token) && $order->payment_method === 'duitnow', 403);
        abort_if($order->payment_status === 'paid', 422, 'This order is already paid.');
        abort_if($order->paymentReceipts()->where('status','pending')->exists(), 422, 'A receipt is already awaiting review.');
        $data = $request->validate(['receipt' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]);
        $file=$data['receipt']; $path=$file->store('payment-receipts','public');
        $order->paymentReceipts()->create(['path'=>$path,'original_filename'=>basename($file->getClientOriginalName()),'mime_type'=>$file->getMimeType(),'file_size'=>$file->getSize(),'status'=>'pending','submitted_at'=>now()]);
        return to_route('orders.guest.show', ['order' => $order->order_number, 'token' => $token])->with('success', 'Receipt uploaded for administrator review.');
    }
}
