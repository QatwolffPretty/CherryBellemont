<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentAuditLog;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function __construct(
        private readonly OrderNotifier $notifier,
        private readonly SettingsService $settings,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Order $order, array $data, User $admin): Shipment
    {
        return DB::transaction(function () use ($order, $data, $admin): Shipment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertCanCreate($order);

            if ($order->shipments()->active()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['shipment' => 'This order already has an active shipment.']);
            }

            $courier = $this->courier($data['courier_id'] ?? null);
            if (filled($data['courier_id'] ?? null) && ! $courier) {
                throw ValidationException::withMessages(['courier_id' => 'Select an active courier or leave this field empty until dispatch.']);
            }
            $shipment = Shipment::create([
                'shipment_number' => $this->shipmentNumber(),
                'order_id' => $order->id,
                'courier_id' => $courier?->id,
                'courier_name_snapshot' => $courier?->name,
                'service_name' => $data['service_name'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'tracking_url' => $courier?->trackingUrl($data['tracking_number'] ?? null),
                'shipment_status' => ($courier && filled($data['tracking_number'] ?? null)) ? 'ready' : 'draft',
                'shipment_type' => 'outbound',
                'label_path' => $data['label_path'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
                'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null,
                'created_by' => $admin->id,
            ]);

            $this->event($shipment, 'draft', 'Shipment Created', null, null, now(), 'system');
            $this->audit($shipment, 'created', null, $shipment->only(['courier_id', 'service_name', 'tracking_number', 'shipment_status']), $admin);

            return $shipment->fresh(['courier', 'order', 'events']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function markShipped(Shipment $shipment, array $data, User $admin): Shipment
    {
        $statusChanged = false;
        $shipment = DB::transaction(function () use ($shipment, $data, $admin, &$statusChanged): Shipment {
            $shipment = Shipment::query()->lockForUpdate()->with('order')->findOrFail($shipment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($shipment->order_id);
            $this->assertCanShip($order, $shipment);

            $courier = Courier::query()->active()->find($data['courier_id'] ?? null);
            if (! $courier) {
                throw ValidationException::withMessages(['courier_id' => 'Select an active courier before marking an order shipped.']);
            }
            if (blank($data['tracking_number'] ?? null)) {
                throw ValidationException::withMessages(['tracking_number' => 'A tracking number is required before shipping.']);
            }

            $old = $shipment->only(['courier_id', 'service_name', 'tracking_number', 'shipment_status', 'tracking_url']);
            $trackingNumber = trim((string) $data['tracking_number']);
            $trackingUrl = $courier->trackingUrl($trackingNumber);
            $shippedAt = $shipment->shipped_at ?: now();
            $shipment->update([
                'courier_id' => $courier->id,
                'courier_name_snapshot' => $courier->name,
                'service_name' => $data['service_name'] ?? $shipment->service_name,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'shipment_status' => 'shipped',
                'label_path' => $data['label_path'] ?? $shipment->label_path,
                'admin_note' => $data['admin_note'] ?? $shipment->admin_note,
                'estimated_delivery_at' => $data['estimated_delivery_at'] ?? $shipment->estimated_delivery_at,
                'shipped_at' => $shippedAt,
            ]);

            $statusChanged = $order->order_status !== 'shipped';
            $order->update([
                'order_status' => 'shipped',
                'courier_name' => $courier->name,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'shipped_at' => $order->shipped_at ?: $shippedAt,
            ]);
            $this->event($shipment, 'shipped', 'Order Shipped', null, null, $shippedAt, 'admin');
            $this->audit($shipment, 'marked_shipped', $old, $shipment->only(['courier_id', 'service_name', 'tracking_number', 'shipment_status', 'tracking_url']), $admin);

            return $shipment->fresh(['order', 'courier', 'events']);
        }, 3);

        if ($statusChanged && (bool) $this->settings->get('shipment.delivery_email_enabled', true)) {
            $this->notifier->send($shipment->order, 'status_updated', ['tracking_url' => $shipment->tracking_url]);
        }

        return $shipment;
    }

    /** @param array<string, mixed> $data */
    public function addEvent(Shipment $shipment, array $data, User $admin): Shipment
    {
        if (! (bool) $this->settings->get('shipment.manual_events_enabled', true)) {
            throw ValidationException::withMessages(['status' => 'Manual shipment events are disabled in Settings.']);
        }
        $orderStatusChanged = false;
        $shipmentStatusChanged = false;
        $shipmentEventStatus = $data['status'];
        $shipment = DB::transaction(function () use ($shipment, $data, $admin, &$orderStatusChanged, &$shipmentStatusChanged): Shipment {
            $shipment = Shipment::query()->lockForUpdate()->with('order')->findOrFail($shipment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($shipment->order_id);
            if (in_array($shipment->shipment_status, ['cancelled', 'returned'], true)) {
                throw ValidationException::withMessages(['status' => 'Cancelled or returned shipments cannot receive further tracking events.']);
            }
            if (in_array($data['status'], ['draft', 'ready', 'shipped'], true)) {
                throw ValidationException::withMessages(['status' => 'Use the shipment dispatch workflow to create or ship a shipment.']);
            }
            if (! in_array($shipment->shipment_status, ['shipped', 'in_transit', 'out_for_delivery', 'delivery_failed'], true) || blank($shipment->tracking_number)) {
                throw ValidationException::withMessages(['status' => 'Confirm dispatch with a courier and tracking number before adding delivery events.']);
            }

            $old = $shipment->only(['shipment_status', 'delivered_at', 'cancelled_at']);
            $shipmentStatusChanged = $shipment->shipment_status !== $data['status'];
            $event = $this->event($shipment, $data['status'], $data['title'], $data['description'] ?? null, $data['location'] ?? null, $data['event_time'], 'admin');
            $updates = ['shipment_status' => $data['status']];
            if ($data['status'] === 'delivered') {
                if ($order->order_status !== 'shipped' && $order->order_status !== 'delivered') {
                    throw ValidationException::withMessages(['status' => 'Only a shipped order can be marked delivered.']);
                }
                $updates['delivered_at'] = $shipment->delivered_at ?: $event->event_time;
                $orderStatusChanged = $order->order_status !== 'delivered';
                $order->update(['order_status' => 'delivered', 'delivered_at' => $order->delivered_at ?: $event->event_time]);
            }
            if ($data['status'] === 'cancelled') {
                $updates['cancelled_at'] = $shipment->cancelled_at ?: $event->event_time;
            }
            $shipment->update($updates);
            $this->audit($shipment, 'event_added', $old, $shipment->only(['shipment_status', 'delivered_at', 'cancelled_at']), $admin);

            return $shipment->fresh(['order', 'courier', 'events']);
        }, 3);

        if ((bool) $this->settings->get('shipment.delivery_email_enabled', true)) {
            if ($orderStatusChanged) {
                $this->notifier->send($shipment->order, 'status_updated', ['tracking_url' => $shipment->tracking_url]);
            } elseif ($shipmentStatusChanged && in_array($shipmentEventStatus, ['out_for_delivery', 'delivery_failed', 'returned'], true)) {
                $this->notifier->send($shipment->order, 'shipment_updated', [
                    'tracking_url' => $shipment->tracking_url,
                    'shipment_status' => $shipmentEventStatus,
                    'estimated_delivery_at' => optional($shipment->estimated_delivery_at)->toDateString(),
                ]);
            }
        }

        return $shipment;
    }

    private function assertCanCreate(Order $order): void
    {
        if ($order->payment_status !== 'paid') {
            throw ValidationException::withMessages(['shipment' => 'Payment must be approved before a shipment can be created.']);
        }
        $fullyRefunded = (float) ($order->refunded_amount ?? 0) > 0
            && (float) ($order->refunded_amount ?? 0) >= (float) $order->total;
        if ($order->order_status === 'cancelled' || $fullyRefunded) {
            throw ValidationException::withMessages(['shipment' => 'Cancelled or fully refunded orders cannot be shipped.']);
        }
        if ($order->order_status !== 'packed') {
            throw ValidationException::withMessages(['shipment' => 'Pack the order before creating its shipment.']);
        }
    }

    private function assertCanShip(Order $order, Shipment $shipment): void
    {
        $this->assertCanCreate($order);
        if ($shipment->shipment_type !== 'outbound') {
            throw ValidationException::withMessages(['shipment' => 'Only outbound shipments can update order fulfilment.']);
        }
    }

    private function courier(?int $id): ?Courier
    {
        return $id ? Courier::query()->active()->find($id) : null;
    }

    private function shipmentNumber(): string
    {
        do {
            $number = 'SHP-CB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Shipment::query()->where('shipment_number', $number)->exists());

        return $number;
    }

    private function event(Shipment $shipment, string $status, string $title, ?string $description, ?string $location, mixed $eventTime, string $source): ShipmentEvent
    {
        return ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'status' => $status,
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'event_time' => $eventTime,
            'source' => $source,
        ]);
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed>|null $new */
    private function audit(Shipment $shipment, string $action, ?array $old, ?array $new, User $admin): void
    {
        ShipmentAuditLog::create([
            'shipment_id' => $shipment->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'admin_id' => $admin->id,
            'created_at' => now(),
        ]);
    }
}
