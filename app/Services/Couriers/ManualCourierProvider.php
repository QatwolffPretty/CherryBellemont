<?php

namespace App\Services\Couriers;

use App\Models\Shipment;

class ManualCourierProvider implements CourierProviderInterface
{
    public function createShipment(array $payload): array
    {
        return ['mode' => 'manual', 'message' => 'No courier API call was made.'];
    }

    public function cancelShipment(Shipment $shipment): array
    {
        return ['mode' => 'manual', 'message' => 'Cancellation must be completed manually with the courier.'];
    }

    public function getTracking(Shipment $shipment): array
    {
        return ['mode' => 'manual', 'events' => []];
    }

    public function downloadLabel(Shipment $shipment): ?string
    {
        return $shipment->label_path;
    }

    public function validateAddress(array $address): array
    {
        return ['mode' => 'manual', 'valid' => null, 'message' => 'Address validation is not connected in manual mode.'];
    }
}
