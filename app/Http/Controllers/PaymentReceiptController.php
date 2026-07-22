<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\OrderNotifier;
use App\Services\AdminNotificationService;
use Throwable;

class PaymentReceiptController extends Controller
{
    public function store(Request $request, Order $order, string $token, OrderNotifier $notifier, AdminNotificationService $adminNotifier): RedirectResponse
    {
        abort_unless($order->guest_access_token && hash_equals($order->guest_access_token, $token) && $order->payment_method === 'duitnow', 403);
        $data = $request->validate(['receipt' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120']]);
        $file = $data['receipt'];
        $path = null;
        $receipt = null;

        try {
            $order = DB::transaction(function () use ($order, $file, &$path, &$receipt): Order {
                $order = Order::lockForUpdate()->findOrFail($order->id);
                abort_if($order->payment_status === 'paid', 422, 'This order is already paid.');
                abort_if($order->paymentReceipts()->where('status', 'pending')->exists(), 422, 'A receipt is already awaiting review.');

                $path = $file->store('payment-receipts', 'local');
                $receipt = $order->paymentReceipts()->create([
                    'path' => $path,
                    'storage_disk' => 'local',
                    'original_filename' => basename($file->getClientOriginalName()),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]);

                return $order;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $notifier->send($order, 'receipt_submitted');
        $adminNotifier->send('new_duitnow_receipt', ['order' => $order, 'receipt' => $receipt]);
        return to_route('orders.guest.show', ['order' => $order->order_number, 'token' => $token])->with('success', 'Receipt uploaded for administrator review.');
    }
}
