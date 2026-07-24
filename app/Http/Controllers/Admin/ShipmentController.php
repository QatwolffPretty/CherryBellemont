<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShipmentEventRequest;
use App\Http\Requests\ShipShipmentRequest;
use App\Http\Requests\StoreShipmentRequest;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\SettingsService;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Shipment::query()->with(['order:id,order_number,number,customer_name,customer_email,order_status', 'courier'])->latest();
        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($matches) use ($search): void {
                $matches->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($orders) => $orders->where('order_number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_email', 'like', "%{$search}%"));
            });
        }
        if ($request->filled('status')) $query->where('shipment_status', $request->string('status')->toString());
        if ($request->filled('courier_id')) $query->where('courier_id', $request->integer('courier_id'));

        return view('admin.shipments.index', [
            'shipments' => $query->paginate(20)->withQueryString(),
            'couriers' => Courier::query()->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => Shipment::STATUSES,
        ]);
    }

    public function create(Order $order, SettingsService $settings): View
    {
        $order->load('latestShipment');
        abort_if($order->latestShipment?->shipment_status && ! in_array($order->latestShipment->shipment_status, ['delivered', 'returned', 'cancelled'], true), 422, 'This order already has an active shipment.');

        return view('admin.shipments.create', [
            'order' => $order,
            'couriers' => Courier::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'defaultCourierId' => (int) $settings->get('shipment.default_courier_id', 0) ?: null,
            'defaultEstimatedDelivery' => now()->addDays(max(0, (int) $settings->get('shipment.default_processing_days', 2)))->toDateString(),
        ]);
    }

    public function store(StoreShipmentRequest $request, Order $order, ShipmentService $shipments): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('label')) $data['label_path'] = $request->file('label')->store('shipment-labels', 'local');
        $shipment = $shipments->create($order, $data, $request->user());

        return to_route('admin.shipments.show', $shipment)->with('success', 'Shipment created. Add courier and tracking details, then confirm dispatch.');
    }

    public function show(Shipment $shipment): View
    {
        return view('admin.shipments.show', [
            'shipment' => $shipment->load(['order.items.product', 'courier', 'events', 'auditLogs.admin']),
            'couriers' => Courier::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => Shipment::STATUSES,
        ]);
    }

    public function ship(ShipShipmentRequest $request, Shipment $shipment, ShipmentService $shipments): RedirectResponse
    {
        $data = $request->validated();
        $previousLabel = $shipment->label_path;
        if ($request->hasFile('label')) $data['label_path'] = $request->file('label')->store('shipment-labels', 'local');
        try {
            $shipments->markShipped($shipment, $data, $request->user());
        } catch (\Throwable $exception) {
            if (isset($data['label_path'])) Storage::disk('local')->delete($data['label_path']);
            throw $exception;
        }
        if (isset($data['label_path']) && $previousLabel && $previousLabel !== $data['label_path']) {
            Storage::disk('local')->delete($previousLabel);
        }

        return back()->with('success', 'Shipment marked as shipped and the customer tracking information is ready.');
    }

    public function storeEvent(ShipmentEventRequest $request, Shipment $shipment, ShipmentService $shipments): RedirectResponse
    {
        $shipments->addEvent($shipment, $request->validated(), $request->user());

        return back()->with('success', 'Shipment event added.');
    }

    public function downloadLabel(Shipment $shipment)
    {
        abort_unless($shipment->label_path, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($shipment->label_path), 404);

        return $disk->download($shipment->label_path, 'shipment-label-'.$shipment->shipment_number.'.'.pathinfo($shipment->label_path, PATHINFO_EXTENSION));
    }
}
