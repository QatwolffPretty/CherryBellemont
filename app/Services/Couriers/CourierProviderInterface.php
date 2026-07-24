<?php

namespace App\Services\Couriers;

use App\Models\Shipment;

interface CourierProviderInterface
{
    /** @param array<string, mixed> $payload */
    public function createShipment(array $payload): array;

    public function cancelShipment(Shipment $shipment): array;

    public function getTracking(Shipment $shipment): array;

    public function downloadLabel(Shipment $shipment): ?string;

    /** @param array<string, mixed> $address */
    public function validateAddress(array $address): array;
}
