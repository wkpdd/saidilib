<?php

namespace App\Services\Delivery;

use App\Models\Order;
use Illuminate\Support\Carbon;

class DeliveryManager
{
    /** @var array<string, ShippingDriver> */
    private array $drivers;

    public function __construct()
    {
        $this->drivers = [
            'noest'    => new NoestDriver(),
            'yalidine' => new YalidineDriver(),
            'manual'   => new ManualDriver(),
        ];
    }

    /** @return ShippingDriver[] */
    public function all(): array
    {
        return $this->drivers;
    }

    public function driver(string $key): ?ShippingDriver
    {
        return $this->drivers[$key] ?? null;
    }

    /** Drivers offered in the admin dispatch dropdown. */
    public function available(): array
    {
        return array_filter($this->drivers, fn (ShippingDriver $d) => $d->isEnabled());
    }

    /**
     * Dispatch an order through a provider. For manual providers, a tracking
     * number can be supplied by the admin.
     */
    public function dispatch(Order $order, string $providerKey, ?string $manualTracking = null): ShipmentResult
    {
        $driver = $this->driver($providerKey);
        if (! $driver) {
            return ShipmentResult::fail("Fournisseur de livraison inconnu : {$providerKey}.");
        }

        if ($manualTracking) {
            $order->tracking_number = $manualTracking;
        }

        $result = $driver->createShipment($order);

        $tracking = $result->tracking ?: $manualTracking;

        if ($result->success) {
            $order->update([
                'delivery_provider' => $providerKey,
                'tracking_number'   => $tracking,
                'provider_payload'  => $result->payload,
                'dispatched_at'     => Carbon::now(),
                'status'            => $order->status === 'pending' ? 'confirmed' : $order->status,
            ]);
        }

        return $result;
    }

    /** Validate an already-dispatched order with its carrier. */
    public function validate(Order $order): ShipmentResult
    {
        $driver = $this->driver((string) $order->delivery_provider);
        if (! $driver) {
            return ShipmentResult::fail('Aucun fournisseur associé à cette commande.');
        }

        $result = $driver->validateShipment($order);

        if ($result->success) {
            $order->update([
                'provider_payload' => array_merge($order->provider_payload ?? [], ['validated' => true]),
            ]);
        }

        return $result;
    }

    /** Official carrier label PDF bytes for an order, or null. */
    public function labelPdf(Order $order): ?string
    {
        $driver = $this->driver((string) $order->delivery_provider);

        return ($driver && $order->tracking_number) ? $driver->labelPdf($order->tracking_number) : null;
    }
}
