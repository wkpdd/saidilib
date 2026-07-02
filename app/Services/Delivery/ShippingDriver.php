<?php

namespace App\Services\Delivery;

use App\Models\Order;

/**
 * Contract every delivery provider implements. Adding a new carrier
 * (ZR Express, Maystro, etc.) is just a new class implementing this.
 */
interface ShippingDriver
{
    public function key(): string;

    public function label(): string;

    /** Whether the provider is configured/enabled. */
    public function isEnabled(): bool;

    /** Push an order to the carrier and return a normalized result. */
    public function createShipment(Order $order): ShipmentResult;

    /** Optional: query tracking status. */
    public function track(string $tracking): ShipmentResult;
}
