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

    /**
     * "The order is ready" — one call that puts it in the carrier's hands:
     * create it if it isn't there yet, then validate it so their logistics
     * can see and collect it. Safe to call twice: an already-created order
     * keeps its tracking, an already-validated one is left alone.
     */
    public function sendReady(Order $order, ?string $providerKey = null): ShipmentResult
    {
        $providerKey = $providerKey ?: ($order->delivery_provider ?: 'noest');
        $driver = $this->driver($providerKey);

        if (! $driver) {
            return ShipmentResult::fail("Fournisseur de livraison inconnu : {$providerKey}.");
        }
        if (! $driver->isEnabled()) {
            return ShipmentResult::fail($driver->label() . " n'est pas configuré (token/GUID manquants).");
        }

        if (! $order->dispatched_at || ! $order->tracking_number) {
            $created = $this->dispatch($order, $providerKey);
            if (! $created->success) {
                return $created;
            }
            $order->refresh();
        }

        if ($order->is_carrier_validated) {
            return ShipmentResult::ok($order->tracking_number, [], 'Déjà validée chez ' . $driver->label() . '.');
        }

        $validated = $this->validate($order);

        return $validated->success
            ? ShipmentResult::ok(
                $order->tracking_number,
                $validated->payload,
                $driver->label() . ' : colis ' . $order->tracking_number . ' créé et validé.'
            )
            : $validated;
    }

    /**
     * Refresh the locally stored Noest tracking snapshot for a set of orders.
     * One API call per 100 trackings. Returns [updated, advanced] counts —
     * "advanced" being orders whose status the carrier moved for us.
     *
     * @param  iterable<Order>  $orders
     * @return array{updated: int, advanced: int, failed: bool}
     */
    public function refreshTracking(iterable $orders): array
    {
        $noest = $this->driver('noest');
        $orders = collect($orders)->filter(
            fn (Order $o) => $o->delivery_provider === 'noest' && $o->tracking_number
        );

        if (! $noest instanceof NoestDriver || $orders->isEmpty()) {
            return ['updated' => 0, 'advanced' => 0, 'failed' => false];
        }

        $updated = $advanced = 0;
        $failed = false;

        foreach ($orders->chunk(100) as $chunk) {
            $info = $noest->trackingsInfo($chunk->pluck('tracking_number')->all());

            if ($info === null) {
                $failed = true;
                continue;
            }

            foreach ($chunk as $order) {
                $entry = $info[$order->tracking_number] ?? null;
                if (! $entry) {
                    continue;
                }

                $sum = NoestDriver::summarize($entry);
                $fields = [
                    'noest_status'     => $sum['status'],
                    'noest_status_key' => $sum['key'],
                    'noest_driver'     => $sum['driver'],
                    'noest_activity'   => $sum['activity'],
                    'noest_checked_at' => Carbon::now(),
                ];

                $mapped = NoestDriver::statusForEvent($sum['key']);
                if ($mapped && $mapped !== $order->status && ! in_array($order->status, ['cancelled', 'returned'], true)) {
                    $fields['status'] = $mapped;
                    $advanced++;
                }

                $order->update($fields);
                $updated++;
            }
        }

        return ['updated' => $updated, 'advanced' => $advanced, 'failed' => $failed];
    }
}
