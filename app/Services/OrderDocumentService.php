<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderDocumentService
{
    public function invoice(Order $order): DompdfDocument
    {
        $order = $this->ensureInvoiceNumber($order);

        return Pdf::loadView('pdf.invoice', $this->documentData($order))
            ->setPaper('a4')
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    }

    public function packingSlip(Order $order): DompdfDocument
    {
        return Pdf::loadView('pdf.packing-slip', $this->documentData($order))
            ->setPaper('a4')
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    }

    public function creditNote(Refund $refund): DompdfDocument
    {
        $refund->loadMissing('order.items.product', 'returnRequest.items');
        $data = $this->documentData($refund->order);
        $data['refund'] = $refund;
        $data['creditNoteNumber'] = 'CN-'.str_replace('RFD-', '', $refund->refund_number);

        return Pdf::loadView('pdf.credit-note', $data)
            ->setPaper('a4')
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    }

    /**
     * Returns the trusted, snapshot-based data supplied to both document views.
     * Keeping this in one place ensures PDFs never use current storefront prices.
     *
     * @return array<string, mixed>
     */
    public function documentData(Order $order): array
    {
        $order = $this->loadOrder($order);
        $items = $order->items->map(fn (OrderItem $item): array => [
            'name' => $item->product_name ?: $item->name ?: 'Cherry Bellemont item',
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'line_total' => (float) ($item->line_total ?? $item->total ?? 0),
            'image' => $this->storedImageDataUri($item->product?->image_path),
        ]);

        $isPickup = filled($order->pickup_location);
        $deliveryLines = $isPickup
            ? [$order->pickup_location]
            : array_values(array_filter([
                $order->address_line_1,
                $order->address_line_2,
                trim(implode(', ', array_filter([$order->city, $order->state]))),
                trim(implode(' ', array_filter([$order->postcode, $order->country]))),
            ]));

        $latestApprovedReceipt = $order->paymentReceipts
            ->where('status', 'approved')
            ->sortByDesc(fn ($receipt) => $receipt->reviewed_at ?? $receipt->created_at)
            ->first();

        return [
            'order' => $order,
            'items' => $items,
            'invoiceNumber' => $order->invoice_number,
            'logo' => $this->logoDataUri(),
            'companyName' => config('store.company_name'),
            'supportEmail' => config('store.support_email'),
            'businessAddress' => config('store.business_address'),
            'deliveryLines' => $deliveryLines,
            'isPickup' => $isPickup,
            'paymentDate' => $order->stripe_paid_at ?? $latestApprovedReceipt?->reviewed_at,
            'totalItemCount' => $items->sum('quantity'),
        ];
    }

    private function ensureInvoiceNumber(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->invoice_number) {
                return $this->loadOrder($lockedOrder);
            }

            $date = ($lockedOrder->created_at ?? now())->format('Ymd');
            $lockedOrder->update([
                'invoice_number' => sprintf('INV-CB-%s-%04d', $date, $lockedOrder->id),
            ]);

            return $this->loadOrder($lockedOrder->fresh());
        });
    }

    private function loadOrder(Order $order): Order
    {
        return $order->loadMissing([
            'items.product',
            'deliveryMethod',
            'shippingZone',
            'coupon',
            'paymentReceipts.reviewer',
        ]);
    }

    private function logoDataUri(): ?string
    {
        $path = config('store.logo_path');

        return is_string($path) && is_file($path) ? $this->dataUri($path) : null;
    }

    private function storedImageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $this->dataUri(Storage::disk('public')->path($path));
    }

    private function dataUri(string $path): ?string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
