<?php

namespace App\Services\Delivery;

use App\Models\Order;

/**
 * Manual / in-house delivery. Records a tracking reference entered by the admin.
 * Useful for own drivers or any carrier not yet integrated.
 */
class ManualDriver implements ShippingDriver
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Manuel / Propre livraison';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function createShipment(Order $order): ShipmentResult
    {
        return ShipmentResult::ok(
            $order->tracking_number,
            ['mode' => 'manual'],
            'Marqué comme expédié (livraison manuelle).'
        );
    }

    public function validateShipment(Order $order): ShipmentResult
    {
        return ShipmentResult::ok($order->tracking_number, [], 'Livraison manuelle — pas de validation externe.');
    }

    public function labelPdf(string $tracking): ?string
    {
        return null;
    }

    public function track(string $tracking): ShipmentResult
    {
        return ShipmentResult::ok($tracking, ['mode' => 'manual']);
    }
}
