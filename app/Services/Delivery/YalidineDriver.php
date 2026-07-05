<?php

namespace App\Services\Delivery;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Yalidine driver — manual-first.
 *
 * For now dispatch is "manual": marking an order as Yalidine records the intent
 * and lets you paste the tracking number you generated in the Yalidine dashboard.
 * The API call below is scaffolded (and used automatically when YALIDINE_ENABLED
 * is true + credentials present) so you can flip to full automation later.
 */
class YalidineDriver implements ShippingDriver
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = config('saidi.delivery.providers.yalidine');
    }

    public function key(): string
    {
        return 'yalidine';
    }

    public function label(): string
    {
        return 'Yalidine';
    }

    /** Always selectable in admin (manual entry); API used only when configured. */
    public function isEnabled(): bool
    {
        return true;
    }

    private function apiReady(): bool
    {
        return ! empty($this->cfg['enabled'])
            && ! empty($this->cfg['api_id'])
            && ! empty($this->cfg['api_token']);
    }

    public function createShipment(Order $order): ShipmentResult
    {
        // Manual mode: no API configured -> caller will ask for a tracking number.
        if (! $this->apiReady()) {
            return ShipmentResult::ok(
                $order->tracking_number,
                ['mode' => 'manual'],
                'Yalidine en mode manuel : saisissez le numéro de suivi.'
            );
        }

        // --- Optional automated path (future) -------------------------------
        $payload = [[
            'order_id'             => $order->reference,
            'firstname'            => $order->customer_name,
            'familyname'           => '',
            'contact_phone'        => $order->phone,
            'address'              => $order->address,
            'to_wilaya_name'       => optional($order->wilaya)->name_fr,
            'to_commune_name'      => $order->commune,
            'product_list'         => $order->items->pluck('name')->implode(', '),
            'price'                => (float) $order->total,
            'is_stopdesk'          => $order->delivery_type === 'stopdesk',
            'do_insurance'         => false,
            'declared_value'       => (float) $order->subtotal,
            'freeshipping'         => false,
        ]];

        try {
            $res = Http::timeout(30)
                ->withHeaders([
                    'X-API-ID'    => $this->cfg['api_id'],
                    'X-API-TOKEN' => $this->cfg['api_token'],
                ])
                ->asJson()
                ->post(rtrim($this->cfg['base_url'], '/') . '/parcels', $payload);

            $body = $res->json() ?? [];
            $first = is_array($body) ? reset($body) : [];
            $tracking = $first['tracking'] ?? $first['label'] ?? null;

            if ($res->successful() && $tracking) {
                return ShipmentResult::ok($tracking, $body, 'Expédié via Yalidine.', $first['label_url'] ?? null);
            }

            return ShipmentResult::fail($first['message'] ?? 'Réponse Yalidine invalide.', $body);
        } catch (\Throwable $e) {
            Log::error('Yalidine createShipment failed', ['error' => $e->getMessage()]);

            return ShipmentResult::fail('Erreur de connexion à Yalidine : ' . $e->getMessage());
        }
    }

    public function validateShipment(Order $order): ShipmentResult
    {
        // Yalidine parcels are created already validated; nothing extra to do.
        return ShipmentResult::ok($order->tracking_number, [], 'Aucune validation séparée requise pour Yalidine.');
    }

    public function labelPdf(string $tracking): ?string
    {
        return null; // Yalidine labels are retrieved from its own dashboard.
    }

    public function track(string $tracking): ShipmentResult
    {
        return ShipmentResult::ok($tracking, ['mode' => $this->apiReady() ? 'api' : 'manual']);
    }
}
