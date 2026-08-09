<?php

namespace App\Services\Delivery;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
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

        [$payload, $problem] = $this->payloadFor($order);

        // Refuse locally rather than trading a precise reason for Noest's
        // generic "données invalides".
        if ($problem !== null) {
            return ShipmentResult::fail($problem, ['payload' => $payload]);
        }

        try {
            $res = Http::withToken($this->cfg['api_token'])
                ->acceptJson()->asJson()->timeout(30)
                ->post($this->cfg['base_url'] . '/api/public/create/order', $payload);

            $body = $res->json() ?? [];

            if ($res->successful() && ($body['success'] ?? false)) {
                return ShipmentResult::ok($body['tracking'] ?? null, $body, 'Créé chez Noest.');
            }

            // Log what we actually sent: without it a rejection is unfixable.
            Log::warning('Noest createShipment refused', [
                'order'    => $order->reference,
                'status'   => $res->status(),
                'payload'  => Arr::except($payload, ['user_guid']),
                'response' => $body,
            ]);

            return ShipmentResult::fail(
                $this->firstError($body) ?? 'Noest a refusé la commande (réponse : ' . $res->status() . ').',
                $body
            );
        } catch (\Throwable $e) {
            Log::error('Noest createShipment failed', ['error' => $e->getMessage()]);

            return ShipmentResult::fail('Erreur de connexion à Noest : ' . $e->getMessage());
        }
    }

    /**
     * Build the create/order body for an order and check it against the API's
     * own rules (v2.1 §1). Returns [payload, problem] — `problem` is a French
     * message naming exactly what is wrong, or null when the payload is good.
     *
     * Exposed so the admin can dry-run an order without creating anything.
     *
     * @return array{0: array, 1: ?string}
     */
    public function payloadFor(Order $order): array
    {
        $isStopDesk = $order->delivery_type === 'stopdesk';
        $wilayaCode = (int) (optional($order->wilaya)->code ?? 0);
        $phone      = self::normalizePhone($order->phone);
        $phone2     = self::normalizePhone($order->phone2);

        // Match the customer's free-text commune against Noest's own list, so
        // "bab ezzouar" / "BAB-EZZOUAR" reach them as "Bab Ezzouar".
        $commune = $order->commune;
        if (! $isStopDesk && $wilayaCode) {
            $commune = $this->resolveCommune($wilayaCode, $order->commune) ?? $order->commune;
        }

        $address = trim((string) $order->address) ?: trim(implode(' ', array_filter([
            $commune, optional($order->wilaya)->name,
        ])));

        $payload = array_filter([
            'user_guid'    => $this->cfg['guid'],
            'reference'    => $order->reference,
            'client'       => self::clamp($order->customer_name, 255),
            'phone'        => $phone,
            'phone_2'      => $phone2,
            'adresse'      => self::clamp($address ?: 'Adresse à confirmer', 255),
            'wilaya_id'    => $wilayaCode ?: null,
            'commune'      => self::clamp($commune, 255),
            'montant'      => round((float) $order->total, 2),
            'remarque'     => self::clamp($order->notes, 255),
            'produit'      => self::clamp($order->items->pluck('name')->implode(', ') ?: 'Commande', 255),
            'type_id'      => 1,                                   // 1 = Livraison
            'poids'        => 1,
            'stop_desk'    => $isStopDesk ? 1 : 0,
            'station_code' => $isStopDesk ? $this->cfg['station_code'] : null,
            'stock'        => 0,
            'can_open'     => 1,
            'shop_name'    => self::clamp(Setting::get('store_name', 'Saidi Papetrie'), 255),
        ], fn ($v) => $v !== null && $v !== '');

        return [$payload, $this->problemWith($payload, $order)];
    }

    /** The first rule the payload breaks, in plain French — or null. */
    private function problemWith(array $p, Order $order): ?string
    {
        if (empty($p['client'])) {
            return 'Nom du client manquant.';
        }
        if (empty($p['phone'])) {
            return "Téléphone invalide (« {$order->phone} ») : Noest attend 9 ou 10 chiffres.";
        }
        if (empty($p['wilaya_id'])) {
            return 'Wilaya manquante sur la commande.';
        }
        if (empty($p['adresse'])) {
            return 'Adresse manquante.';
        }
        if (($p['stop_desk'] ?? 0) === 1 && empty($p['station_code'])) {
            return 'Livraison stop desk : renseignez le « code station » dans Paramètres → Livraison Noest.';
        }
        if (($p['stop_desk'] ?? 0) === 0 && empty($p['commune'])) {
            return 'Commune manquante : Noest en a besoin pour une livraison à domicile.';
        }
        if (($p['montant'] ?? 0) <= 0) {
            return 'Montant de la commande à 0 — Noest le refuse.';
        }
        if (mb_strlen((string) ($p['reference'] ?? '')) < 5) {
            return 'Référence trop courte (5 caractères minimum).';
        }

        // Known commune but not one Noest serves → their "commune inexistante".
        $known = $this->communes((int) $p['wilaya_id']);
        if (($p['stop_desk'] ?? 0) === 0 && $known !== null && $known !== []
            && $this->resolveCommune((int) $p['wilaya_id'], $p['commune']) === null) {
            return "Commune « {$p['commune']} » inconnue chez Noest pour cette wilaya. "
                . 'Corrigez-la sur la commande (exemples : ' . implode(', ', array_slice($known, 0, 3)) . '…).';
        }

        return null;
    }

    /**
     * Communes Noest serves in a wilaya (cached 24 h — the list barely moves
     * and every dispatch would otherwise pay for the round-trip).
     *
     * @return string[]|null  null when the API could not be reached
     */
    public function communes(int $wilayaCode): ?array
    {
        if (! $this->isEnabled() || $wilayaCode < 1) {
            return null;
        }

        return Cache::remember("noest.communes.{$wilayaCode}", now()->addDay(), function () use ($wilayaCode) {
            try {
                $res = Http::withToken($this->cfg['api_token'])->acceptJson()->timeout(20)
                    ->get($this->cfg['base_url'] . '/api/public/get/communes/' . $wilayaCode);

                if (! $res->successful() || ! is_array($res->json())) {
                    return null;
                }

                return collect($res->json())
                    ->filter(fn ($c) => is_array($c) && ($c['is_active'] ?? 1))
                    ->pluck('nom')->filter()->values()->all();
            } catch (\Throwable $e) {
                Log::error('Noest communes failed', ['wilaya' => $wilayaCode, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * Wilayas Noest actually serves, [code => nom] (cached 24 h).
     * Null when the API could not be reached — callers must then assume
     * everything is servable rather than block the shop.
     *
     * @return array<int, string>|null
     */
    public function wilayas(): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return Cache::remember('noest.wilayas', now()->addDay(), function () {
            try {
                $res = Http::withToken($this->cfg['api_token'])->acceptJson()->timeout(20)
                    ->get($this->cfg['base_url'] . '/api/public/get/wilayas');

                if (! $res->successful() || ! is_array($res->json())) {
                    return null;
                }

                $out = [];
                foreach ($res->json() as $row) {
                    if (is_array($row) && ($row['is_active'] ?? 1) && ! empty($row['code'])) {
                        $out[(int) $row['code']] = (string) ($row['nom'] ?? '');
                    }
                }

                return $out ?: null;
            } catch (\Throwable $e) {
                Log::error('Noest wilayas failed', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /** The commune spelled the way Noest expects it, or null if unknown. */
    public function resolveCommune(int $wilayaCode, ?string $commune): ?string
    {
        $commune = trim((string) $commune);
        $known = $this->communes($wilayaCode);

        if ($commune === '' || $known === null || $known === []) {
            return null;
        }

        $wanted = self::fold($commune);

        foreach ($known as $name) {                       // exact, accents/case aside
            if (self::fold($name) === $wanted) {
                return $name;
            }
        }
        foreach ($known as $name) {                       // "ezzouar" → "Bab Ezzouar"
            $f = self::fold($name);
            if ($wanted !== '' && (str_contains($f, $wanted) || str_contains($wanted, $f))) {
                return $name;
            }
        }

        return null;
    }

    /** "+213 550 50 50 50" / "0550-50-50-50" → "0550505050"; null if unusable. */
    public static function normalizePhone(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if (str_starts_with($digits, '00213')) {
            $digits = substr($digits, 5);
        } elseif (strlen($digits) > 10 && str_starts_with($digits, '213')) {
            $digits = substr($digits, 3);
        }

        // A national number keeps its leading 0 (021 12 34 56); only a number
        // that lost it to an international prefix gets it back.
        if (strlen($digits) === 9 && ! str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }

        return (strlen($digits) >= 9 && strlen($digits) <= 10) ? $digits : null;
    }

    /** Accent/case/punctuation-insensitive key for comparing commune names. */
    private static function fold(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);

        return preg_replace('/[^a-z0-9]+/', '', $s) ?? '';
    }

    private static function clamp(?string $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
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
        $info = $this->trackingsInfo([$tracking]);

        if ($info === null) {
            return ShipmentResult::fail('Suivi Noest indisponible (API injoignable ou non configurée).');
        }

        return isset($info[$tracking])
            ? ShipmentResult::ok($tracking, $info[$tracking])
            : ShipmentResult::fail("Colis {$tracking} introuvable chez Noest.");
    }

    /**
     * Batch tracking lookup (POST get/trackings/info). Returns the raw
     * per-tracking map, or null when the call itself failed. Noest caps a
     * request at 100 trackings, so callers must chunk.
     *
     * @param  string[]  $trackings
     * @return array<string, array>|null
     */
    public function trackingsInfo(array $trackings): ?array
    {
        $trackings = array_values(array_filter(array_unique($trackings)));

        if (! $this->isEnabled() || ! $trackings) {
            return null;
        }

        try {
            $res = Http::withToken($this->cfg['api_token'])
                ->acceptJson()->asJson()->timeout(30)
                ->post($this->cfg['base_url'] . '/api/public/get/trackings/info', [
                    'trackings' => array_slice($trackings, 0, 100),
                ]);

            $body = $res->json();

            // "Trackings non trouvés" comes back as {"message": ...} — not a map.
            if (! $res->successful() || ! is_array($body) || isset($body['message'])) {
                return [];
            }

            return array_filter($body, 'is_array');
        } catch (\Throwable $e) {
            Log::error('Noest trackings/info failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Flatten one entry of trackings/info into the handful of fields the admin
     * panel shows: latest event, its key/date, the driver, and the timeline.
     *
     * @return array{status: ?string, key: ?string, date: ?string, driver: ?string, activity: array, attempts: int}
     */
    public static function summarize(array $info): array
    {
        $order    = $info['OrderInfo'] ?? [];
        $activity = array_values(array_filter($info['activity'] ?? [], 'is_array'));

        // The API returns the timeline chronologically; sort anyway so "last"
        // really is the latest event whatever the order we get it in.
        usort($activity, fn ($a, $b) => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));
        $last = $activity ? end($activity) : [];

        $driver = trim(implode(' · ', array_filter([
            $order['driver_name'] ?? null,
            $order['driver_tel'] ?? null,
        ])));

        return [
            'status'   => $last['event'] ?? null,
            'key'      => $last['event_key'] ?? null,
            'date'     => $last['date'] ?? null,
            'driver'   => $driver ?: null,
            'activity' => $activity,
            'attempts' => count(array_filter($info['deliveryAttempts'] ?? [], 'is_array')),
        ];
    }

    /**
     * The partner's own delivery price grid (GET /api/public/fees), keyed by
     * wilaya code: [16 => ['home' => 700.0, 'stopdesk' => 300.0], ...].
     * Null when the call fails, so callers can tell "no answer" from "empty".
     *
     * @return array<int, array{home: float, stopdesk: float}>|null
     */
    public function fees(): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $res = Http::withToken($this->cfg['api_token'])->acceptJson()->timeout(30)
                ->get($this->cfg['base_url'] . '/api/public/fees');

            $delivery = $res->json('tarifs.delivery');

            if (! $res->successful() || ! is_array($delivery)) {
                return null;
            }

            $out = [];
            foreach ($delivery as $key => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $code = (int) ($row['wilaya_id'] ?? $key);
                if ($code < 1 || $code > 58) {
                    continue;
                }
                $out[$code] = [
                    'home'     => (float) ($row['tarif'] ?? 0),
                    'stopdesk' => (float) ($row['tarif_stopdesk'] ?? 0),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('Noest fees failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Map a Noest event key to one of our order statuses — only for events
     * that are unambiguous end states. Anything else returns null and leaves
     * the local status alone (the admin stays in charge).
     */
    public static function statusForEvent(?string $eventKey): ?string
    {
        return [
            'livre'                        => 'delivered',
            'livred'                       => 'delivered',
            'validation_reception'         => 'shipped',   // picked up by the driver
            'fdr_activated'                => 'shipped',   // out for delivery
            'livraison_echoue_recu'        => 'returned',  // return received by us
            'return_validated_by_partener' => 'returned',
        ][$eventKey] ?? null;
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
