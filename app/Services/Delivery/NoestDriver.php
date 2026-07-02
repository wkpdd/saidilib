<?php

namespace App\Services\Delivery;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Noest Express driver.
 *
 * This implements the commonly documented Noest API shape (token + GUID auth,
 * JSON "create order" call). The exact endpoint paths and field names are kept
 * in one place below so they can be adjusted in minutes once you provide the
 * official Noest documentation. Set NOEST_ENABLED=true + token/GUID in .env.
 */
class NoestDriver implements ShippingDriver
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('saidi.delivery.providers.noest');
    }

    public function key(): string
    {
        return 'noest';
    }

    public function label(): string
    {
        return 'Noest Express';
    }

    public function isEnabled(): bool
    {
        return ! empty($this->cfg['enabled'])
            && ! empty($this->cfg['api_token'])
            && ! empty($this->cfg['guid']);
    }

    public function createShipment(Order $order): ShipmentResult
    {
        if (! $this->isEnabled()) {
            return ShipmentResult::fail("Noest n'est pas configuré (token/GUID manquants).");
        }

        // --- Map our order to Noest's expected payload ----------------------
        // Adjust these field names to match the official documentation.
        $payload = [
            'api_token'    => $this->cfg['api_token'],
            'user_guid'    => $this->cfg['guid'],
            'reference'    => $order->reference,
            'client'       => $order->customer_name,
            'phone'        => $order->phone,
            'phone_2'      => $order->phone2,
            'adresse'      => $order->address,
            'wilaya_id'    => optional($order->wilaya)->code,
            'commune'      => $order->commune,
            'montant'      => (float) $order->total,
            'remarque'     => $order->notes,
            'produit'      => $order->items->pluck('name')->implode(', '),
            'type_id'      => 1,                                   // 1 = livraison
            'poids'        => 1,
            'stop_desk'    => $order->delivery_type === 'stopdesk' ? 1 : 0,
            'stock'        => 0,
        ];

        try {
            $res = Http::timeout(30)
                ->acceptJson()
                ->asJson()
                ->post(rtrim($this->cfg['base_url'], '/') . '/api/public/create/order', $payload);

            $body = $res->json() ?? [];

            if ($res->successful() && ($body['success'] ?? $res->successful())) {
                $tracking = $body['tracking']
                    ?? $body['data']['tracking']
                    ?? $body['order_id']
                    ?? null;

                return ShipmentResult::ok($tracking, $body, 'Expédié via Noest.');
            }

            return ShipmentResult::fail($body['message'] ?? 'Réponse Noest invalide.', $body);
        } catch (\Throwable $e) {
            Log::error('Noest createShipment failed', ['error' => $e->getMessage()]);

            return ShipmentResult::fail('Erreur de connexion à Noest : ' . $e->getMessage());
        }
    }

    public function track(string $tracking): ShipmentResult
    {
        if (! $this->isEnabled()) {
            return ShipmentResult::fail("Noest n'est pas configuré.");
        }

        try {
            $res = Http::timeout(30)->acceptJson()->asJson()
                ->post(rtrim($this->cfg['base_url'], '/') . '/api/public/get/trackings/info', [
                    'api_token' => $this->cfg['api_token'],
                    'user_guid' => $this->cfg['guid'],
                    'trackings' => [$tracking],
                ]);

            return ShipmentResult::ok($tracking, $res->json() ?? []);
        } catch (\Throwable $e) {
            return ShipmentResult::fail($e->getMessage());
        }
    }
}
