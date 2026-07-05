<?php

namespace App\Services\Delivery;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Noest Express driver — implements the official NOEST Public API (v2.1).
 *
 * Auth: `Authorization: Bearer {api_token}` header + `user_guid` in the JSON body.
 * Credentials come from admin Settings (noest_token / noest_guid), falling back
 * to config/.env.
 */
class NoestDriver implements ShippingDriver
{
    private array $cfg;

    public function __construct()
    {
        $c = config('saidi.delivery.providers.noest', []);

        $this->cfg = [
            'base_url'     => rtrim($c['base_url'] ?? 'https://app.noest-dz.com', '/'),
            'api_token'    => Setting::get('noest_token') ?: ($c['api_token'] ?? null),
            'guid'         => Setting::get('noest_guid') ?: ($c['guid'] ?? null),
            'enabled'      => Setting::get('noest_enabled') === '1' || ! empty($c['enabled']),
            'station_code' => Setting::get('noest_station_code') ?: null,
        ];
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
        return $this->cfg['enabled']
            && ! empty($this->cfg['api_token'])
            && ! empty($this->cfg['guid']);
    }

    public function createShipment(Order $order): ShipmentResult
    {
        if (! $this->isEnabled()) {
            return ShipmentResult::fail("Noest n'est pas configuré (token/GUID manquants).");
        }

        $isStopDesk = $order->delivery_type === 'stopdesk';

        $payload = array_filter([
            'user_guid'    => $this->cfg['guid'],
            'reference'    => $order->reference,
            'client'       => $order->customer_name,
            'phone'        => $order->phone,
            'phone_2'      => $order->phone2,
            'adresse'      => $order->address ?: (trim((optional($order->wilaya)->name ?? '') . ' ' . ($order->commune ?? '')) ?: 'Adresse à confirmer'),
            'wilaya_id'    => optional($order->wilaya)->code,
            'commune'      => $order->commune,
            'montant'      => (float) $order->total,
            'remarque'     => $order->notes,
            'produit'      => $order->items->pluck('name')->implode(', ') ?: 'Commande',
            'type_id'      => 1,                                   // 1 = Livraison
            'poids'        => 1,
            'stop_desk'    => $isStopDesk ? 1 : 0,
            'station_code' => $isStopDesk ? $this->cfg['station_code'] : null,
            'stock'        => 0,
            'can_open'     => 1,
            'shop_name'    => Setting::get('store_name', 'Saidi Papetrie'),
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $res = Http::withToken($this->cfg['api_token'])
                ->acceptJson()->asJson()->timeout(30)
                ->post($this->cfg['base_url'] . '/api/public/create/order', $payload);

            $body = $res->json() ?? [];

            if ($res->successful() && ($body['success'] ?? false)) {
                return ShipmentResult::ok($body['tracking'] ?? null, $body, 'Créé chez Noest.');
            }

            return ShipmentResult::fail($this->firstError($body) ?? 'Réponse Noest invalide.', $body);
        } catch (\Throwable $e) {
            Log::error('Noest createShipment failed', ['error' => $e->getMessage()]);

            return ShipmentResult::fail('Erreur de connexion à Noest : ' . $e->getMessage());
        }
    }

    public function validateShipment(Order $order): ShipmentResult
    {
        if (! $this->isEnabled()) {
            return ShipmentResult::fail("Noest n'est pas configuré.");
        }
        if (! $order->tracking_number) {
            return ShipmentResult::fail("Aucun tracking Noest à valider (expédiez d'abord).");
        }

        try {
            $res = Http::withToken($this->cfg['api_token'])
                ->acceptJson()->asJson()->timeout(30)
                ->post($this->cfg['base_url'] . '/api/public/valid/order', [
                    'user_guid' => $this->cfg['guid'],
                    'tracking'  => $order->tracking_number,
                ]);

            $body = $res->json() ?? [];

            return ($res->successful() && ($body['success'] ?? false))
                ? ShipmentResult::ok($order->tracking_number, $body, 'Commande validée chez Noest.')
                : ShipmentResult::fail($this->firstError($body) ?? 'Validation Noest échouée.', $body);
        } catch (\Throwable $e) {
            return ShipmentResult::fail('Erreur Noest : ' . $e->getMessage());
        }
    }

    /** Download the official Noest label PDF for a tracking (raw bytes). */
    public function labelPdf(string $tracking): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $res = Http::withToken($this->cfg['api_token'])->timeout(30)
                ->get($this->cfg['base_url'] . '/api/public/get/order/label', ['tracking' => $tracking]);

            if ($res->successful() && str_contains(strtolower($res->header('Content-Type', '')), 'pdf')) {
                return $res->body();
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('Noest label failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function track(string $tracking): ShipmentResult
    {
        if (! $this->isEnabled()) {
            return ShipmentResult::fail("Noest n'est pas configuré.");
        }

        try {
            $res = Http::withToken($this->cfg['api_token'])
                ->acceptJson()->asJson()->timeout(30)
                ->post($this->cfg['base_url'] . '/api/public/get/trackings/info', [
                    'trackings' => [$tracking],
                ]);

            return ShipmentResult::ok($tracking, $res->json() ?? []);
        } catch (\Throwable $e) {
            return ShipmentResult::fail($e->getMessage());
        }
    }

    /** Pull the first human-readable error from a Noest error response. */
    private function firstError(array $body): ?string
    {
        if (! empty($body['message']) && is_string($body['message'])) {
            return $body['message'];
        }
        foreach (($body['errors'] ?? []) as $msgs) {
            return is_array($msgs) ? reset($msgs) : (string) $msgs;
        }

        return null;
    }
}
