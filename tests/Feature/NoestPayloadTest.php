<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Wilaya;
use App\Services\Delivery\NoestDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What we send to Noest's create/order, checked against the rules in their
 * v2.1 documentation — this is where "données invalides" comes from.
 */
class NoestPayloadTest extends TestCase
{
    private array $trash = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        Setting::put('noest_token', 'test-token', 'noest');
        Setting::put('noest_guid', 'test-guid', 'noest');
        Setting::put('noest_enabled', '1', 'noest');
        Setting::flush();

        // Communes lookup used by the resolver.
        Http::fake([
            '*/api/public/get/communes/16' => Http::response([
                ['nom' => 'Bab Ezzouar', 'wilaya_id' => 16, 'code_postal' => '16027', 'is_active' => 1],
                ['nom' => 'Dar El Beida', 'wilaya_id' => 16, 'code_postal' => '16028', 'is_active' => 1],
                ['nom' => 'Chéraga',      'wilaya_id' => 16, 'code_postal' => '16029', 'is_active' => 1],
            ], 200),
            '*/api/public/get/communes/*' => Http::response([], 200),
        ]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->trash) as $model) {
            $model->forceDelete();
        }
        Cache::flush();
        parent::tearDown();
    }

    private function wilaya(): Wilaya
    {
        $w = Wilaya::firstOrCreate(['code' => 16], ['name_fr' => 'Alger', 'name_ar' => 'الجزائر', 'is_active' => true]);
        if ($w->wasRecentlyCreated) {
            $this->trash[] = $w;
        }

        return $w;
    }

    private function order(array $attrs = []): Order
    {
        $order = Order::create(array_merge([
            'reference'     => 'TEST-' . strtoupper(substr(uniqid(), -6)),
            'customer_name' => 'Ahmed Benali',
            'phone'         => '0550505050',
            'wilaya_id'     => $this->wilaya()->id,
            'commune'       => 'Bab Ezzouar',
            'address'       => 'Rue des Martyrs',
            'delivery_type' => 'home',
            'total'         => 3500,
            'status'        => 'confirmed',
        ], $attrs));
        $this->trash[] = $order;

        $this->trash[] = OrderItem::create([
            'order_id' => $order->id, 'name' => 'Cahier 96 pages',
            'unit_price' => 120, 'quantity' => 2, 'line_total' => 240,
        ]);

        return $order->fresh()->load('items', 'wilaya');
    }

    private function payload(Order $order): array
    {
        return (new NoestDriver())->payloadFor($order);
    }

    // ── Phone numbers ─────────────────────────────────────────────────

    public static function phones(): array
    {
        return [
            'déjà propre'      => ['0550505050', '0550505050'],
            'avec espaces'     => ['0550 50 50 50', '0550505050'],
            'avec tirets'      => ['0550-50-50-50', '0550505050'],
            'indicatif +213'   => ['+213550505050', '0550505050'],
            'indicatif 00213'  => ['00213550505050', '0550505050'],
            'sans zéro initial'=> ['550505050', '0550505050'],
            'fixe 9 chiffres'  => ['021123456', '021123456'],
            'trop court'       => ['0550', null],
            'texte'            => ['pas de numéro', null],
            'vide'             => ['', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('phones')]
    public function test_phone_numbers_are_normalised_the_way_noest_wants(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, NoestDriver::normalizePhone($raw));
    }

    public function test_a_phone_typed_with_spaces_no_longer_breaks_the_dispatch(): void
    {
        [$payload, $problem] = $this->payload($this->order(['phone' => '0550 50 50 50']));

        $this->assertNull($problem);
        $this->assertSame('0550505050', $payload['phone']);
    }

    public function test_an_unusable_phone_is_reported_before_calling_noest(): void
    {
        [, $problem] = $this->payload($this->order(['phone' => '12']));

        $this->assertStringContainsString('Téléphone invalide', (string) $problem);
    }

    // ── Commune (the usual culprit) ────────────────────────────────────

    public function test_a_missing_commune_is_caught_locally(): void
    {
        [, $problem] = $this->payload($this->order(['commune' => null]));

        $this->assertStringContainsString('Commune manquante', (string) $problem);
    }

    public function test_the_customers_spelling_is_matched_to_noests(): void
    {
        foreach (['bab ezzouar', 'BAB-EZZOUAR', 'Bab  Ezzouar ', 'ezzouar'] as $typed) {
            [$payload, $problem] = $this->payload($this->order(['commune' => $typed]));

            $this->assertNull($problem, "refusé pour « {$typed} »");
            $this->assertSame('Bab Ezzouar', $payload['commune'], "mal résolu pour « {$typed} »");
        }
    }

    public function test_accents_do_not_matter(): void
    {
        [$payload, $problem] = $this->payload($this->order(['commune' => 'cheraga']));

        $this->assertNull($problem);
        $this->assertSame('Chéraga', $payload['commune']);
    }

    public function test_an_unknown_commune_names_real_alternatives(): void
    {
        [, $problem] = $this->payload($this->order(['commune' => 'Zeralda']));

        $this->assertStringContainsString('inconnue chez Noest', (string) $problem);
        $this->assertStringContainsString('Bab Ezzouar', (string) $problem);
    }

    // ── Length limits & required fields ───────────────────────────────

    public function test_long_text_is_trimmed_to_the_api_limits(): void
    {
        [$payload, $problem] = $this->payload($this->order([
            'address' => str_repeat('Rue très longue, ', 40),   // ~680 chars
            'notes'   => str_repeat('remarque ', 60),           // ~540 chars
        ]));

        $this->assertNull($problem);
        $this->assertLessThanOrEqual(255, mb_strlen($payload['adresse']));
        $this->assertLessThanOrEqual(255, mb_strlen($payload['remarque']));
    }

    public function test_a_missing_wilaya_is_caught(): void
    {
        [, $problem] = $this->payload($this->order(['wilaya_id' => null]));

        $this->assertStringContainsString('Wilaya manquante', (string) $problem);
    }

    public function test_a_zero_amount_is_caught(): void
    {
        [, $problem] = $this->payload($this->order(['total' => 0]));

        $this->assertStringContainsString('Montant', (string) $problem);
    }

    public function test_stopdesk_without_a_station_code_is_caught(): void
    {
        Setting::put('noest_station_code', '', 'noest');
        Setting::flush();

        [, $problem] = $this->payload($this->order(['delivery_type' => 'stopdesk']));

        $this->assertStringContainsString('code station', (string) $problem);
    }

    public function test_stopdesk_with_a_station_code_passes_and_needs_no_commune(): void
    {
        Setting::put('noest_station_code', '16A', 'noest');
        Setting::flush();

        [$payload, $problem] = $this->payload($this->order(['delivery_type' => 'stopdesk', 'commune' => null]));

        $this->assertNull($problem);
        $this->assertSame(1, $payload['stop_desk']);
        $this->assertSame('16A', $payload['station_code']);
    }

    public function test_a_valid_order_matches_the_documented_shape(): void
    {
        [$payload, $problem] = $this->payload($this->order());

        $this->assertNull($problem);

        foreach (['user_guid', 'reference', 'client', 'phone', 'adresse', 'wilaya_id',
                  'commune', 'montant', 'produit', 'type_id', 'stop_desk'] as $required) {
            $this->assertArrayHasKey($required, $payload, "champ requis manquant : {$required}");
        }

        $this->assertSame(16, $payload['wilaya_id']);          // 1..58
        $this->assertSame(1, $payload['type_id']);             // 1 = Livraison
        $this->assertSame(0, $payload['stop_desk']);           // 0 = domicile
        $this->assertSame(3500.0, $payload['montant']);
        $this->assertGreaterThanOrEqual(5, mb_strlen($payload['reference']));
        $this->assertMatchesRegularExpression('/^\d{9,10}$/', $payload['phone']);
    }

    // ── End to end ────────────────────────────────────────────────────

    public function test_a_broken_order_never_reaches_the_api(): void
    {
        $order = $this->order(['commune' => null]);

        $result = (new NoestDriver())->createShipment($order);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Commune manquante', (string) $result->message);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'create/order'));
    }

    public function test_the_dry_run_button_reports_the_problem_without_creating_anything(): void
    {
        $admin = \App\Models\User::where('is_admin', true)->first();
        if (! $admin) {
            $admin = \App\Models\User::create([
                'name' => 'T', 'email' => 't-' . uniqid() . '@x.test', 'password' => bcrypt('xx'),
                'role' => 'admin', 'is_admin' => true, 'is_active' => true,
            ]);
            $this->trash[] = $admin;
        }

        $order = $this->order(['commune' => 'Zeralda']);

        $this->actingAs($admin)->post("/admin/orders/{$order->id}/noest-check")
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionHas('noest_payload');

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'create/order'));

        $this->assertNull($order->fresh()->tracking_number);
    }

    public function test_a_refusal_from_noest_surfaces_their_own_message(): void
    {
        Http::fake([
            '*/api/public/get/communes/16' => Http::response([['nom' => 'Bab Ezzouar', 'wilaya_id' => 16, 'is_active' => 1]], 200),
            '*/api/public/create/order'    => Http::response(['success' => false, 'message' => 'montant doit être inferieur à 150000'], 422),
        ]);

        $result = (new NoestDriver())->createShipment($this->order());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('montant doit être inferieur', (string) $result->message);
    }

    // ── Delivery prices ───────────────────────────────────────────────

    public function test_fees_are_read_from_the_noest_grid(): void
    {
        Http::fake([
            '*/api/public/fees' => Http::response(['tarifs' => [
                'delivery' => [
                    '16' => ['tarif_id' => 399, 'wilaya_id' => 16, 'tarif' => '700', 'tarif_stopdesk' => '300'],
                    '9'  => ['tarif_id' => 399, 'wilaya_id' => 9,  'tarif' => '800', 'tarif_stopdesk' => '350'],
                ],
                'return' => ['16' => ['wilaya_id' => 16, 'tarif' => '300', 'tarif_stopdesk' => '300']],
            ]], 200),
        ]);

        $fees = (new NoestDriver())->fees();

        $this->assertSame(700.0, $fees[16]['home']);
        $this->assertSame(300.0, $fees[16]['stopdesk']);
        $this->assertSame(800.0, $fees[9]['home']);
        $this->assertArrayNotHasKey(0, $fees);
    }

    public function test_fees_return_null_when_noest_is_unreachable(): void
    {
        Http::fake(['*/api/public/fees' => Http::response('', 500)]);

        $this->assertNull((new NoestDriver())->fees());
    }

    public function test_the_wilaya_page_previews_the_noest_grid_without_saving(): void
    {
        Http::fake([
            '*/api/public/fees' => Http::response(['tarifs' => ['delivery' => [
                '16' => ['wilaya_id' => 16, 'tarif' => '700', 'tarif_stopdesk' => '300'],
            ]]], 200),
        ]);

        $w = $this->wilaya();
        $w->update(['home_fee' => 400, 'stopdesk_fee' => 200]);

        $admin = \App\Models\User::where('is_admin', true)->first();
        if (! $admin) {
            $admin = \App\Models\User::create([
                'name' => 'T', 'email' => 't-' . uniqid() . '@x.test', 'password' => bcrypt('xx'),
                'role' => 'admin', 'is_admin' => true, 'is_active' => true,
            ]);
            $this->trash[] = $admin;
        }

        // Preview: the field shows Noest's price + the margin...
        $this->actingAs($admin)->get('/admin/wilayas?noest=1&marge=100')
            ->assertOk()
            ->assertSee('value="800"', false)      // 700 + 100 (domicile)
            ->assertSee('value="400"', false)      // 300 + 100 (stop desk)
            ->assertSee('aperçu', false);

        // ...but nothing is written until the admin saves.
        $this->assertSame(400.0, (float) $w->fresh()->home_fee);

        $this->actingAs($admin)->patch('/admin/wilayas', [
            'wilayas' => [$w->id => ['home_fee' => 800, 'stopdesk_fee' => 400, 'is_active' => '1']],
        ])->assertRedirect();

        $this->assertSame(800.0, (float) $w->fresh()->home_fee);
        $this->assertSame(400.0, (float) $w->fresh()->stopdesk_fee);
    }

    public function test_the_wilaya_page_still_works_when_noest_is_down(): void
    {
        Http::fake(['*/api/public/fees' => Http::response('', 500)]);

        $admin = \App\Models\User::where('is_admin', true)->first() ?: null;
        if (! $admin) {
            $this->markTestSkipped('no admin');
        }

        $this->actingAs($admin)->get('/admin/wilayas?noest=1')
            ->assertOk()
            ->assertSee('Impossible de récupérer les tarifs Noest', false);
    }

    // ── Checkout guards (stop bad data at the source) ──────────────────

    public function test_checkout_refuses_a_phone_noest_cannot_use(): void
    {
        $product = \App\Models\Product::create([
            'name_fr' => 'Article test', 'slug' => 'art-' . uniqid(), 'price' => 500,
            'stock' => 10, 'is_active' => true,
        ]);
        $this->trash[] = $product;

        $this->post('/panier/ajouter', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/commande', [
            'customer_name' => 'Test', 'phone' => '123', 'wilaya_id' => $this->wilaya()->id,
            'commune' => 'Bab Ezzouar', 'address' => 'Rue X', 'delivery_type' => 'home',
        ])->assertSessionHasErrors('phone');
    }

    public function test_checkout_requires_a_commune_for_home_delivery(): void
    {
        $product = \App\Models\Product::create([
            'name_fr' => 'Article test', 'slug' => 'art-' . uniqid(), 'price' => 500,
            'stock' => 10, 'is_active' => true,
        ]);
        $this->trash[] = $product;

        $this->post('/panier/ajouter', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/commande', [
            'customer_name' => 'Test', 'phone' => '0550505050', 'wilaya_id' => $this->wilaya()->id,
            'commune' => '', 'address' => 'Rue X', 'delivery_type' => 'home',
        ])->assertSessionHasErrors('commune');
    }

    public function test_checkout_suggests_the_carriers_communes(): void
    {
        $res = $this->getJson('/commande/communes?wilaya_id=' . $this->wilaya()->id)->assertOk();

        $this->assertContains('Bab Ezzouar', $res->json('communes'));
    }

    public function test_checkout_refuses_a_commune_noest_does_not_serve(): void
    {
        $product = \App\Models\Product::create([
            'name_fr' => 'Article test', 'slug' => 'art-' . uniqid(), 'price' => 500,
            'stock' => 10, 'is_active' => true,
        ]);
        $this->trash[] = $product;
        $this->post('/panier/ajouter', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/commande', [
            'customer_name' => 'Test', 'phone' => '0550505050', 'wilaya_id' => $this->wilaya()->id,
            'commune' => 'Une commune qui nexiste pas', 'address' => 'Rue X', 'delivery_type' => 'home',
        ])->assertSessionHasErrors('commune');

        $this->assertSame(0, \App\Models\Order::where('customer_name', 'Test')->count());
    }

    public function test_checkout_stores_the_commune_with_noests_spelling(): void
    {
        $product = \App\Models\Product::create([
            'name_fr' => 'Article test', 'slug' => 'art-' . uniqid(), 'price' => 500,
            'stock' => 10, 'is_active' => true,
        ]);
        $this->trash[] = $product;
        $this->post('/panier/ajouter', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/commande', [
            'customer_name' => 'Client Orthographe', 'phone' => '0550 50 50 50',
            'wilaya_id' => $this->wilaya()->id, 'commune' => 'bab ezzouar',
            'address' => 'Rue X', 'delivery_type' => 'home',
        ])->assertRedirect();

        $order = \App\Models\Order::where('customer_name', 'Client Orthographe')->latest('id')->first();
        $this->assertNotNull($order);
        $this->trash[] = $order;

        $this->assertSame('Bab Ezzouar', $order->commune);
    }

    public function test_checkout_still_accepts_orders_when_noest_is_unreachable(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response('', 500)]);      // carrier down

        $product = \App\Models\Product::create([
            'name_fr' => 'Article test', 'slug' => 'art-' . uniqid(), 'price' => 500,
            'stock' => 10, 'is_active' => true,
        ]);
        $this->trash[] = $product;
        $this->post('/panier/ajouter', ['product_id' => $product->id, 'quantity' => 1]);

        $this->post('/commande', [
            'customer_name' => 'Client Hors Ligne', 'phone' => '0550505050',
            'wilaya_id' => $this->wilaya()->id, 'commune' => 'Bab Ezzouar',
            'address' => 'Rue X', 'delivery_type' => 'home',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $order = \App\Models\Order::where('customer_name', 'Client Hors Ligne')->latest('id')->first();
        $this->assertNotNull($order, 'la boutique doit continuer à prendre des commandes');
        $this->trash[] = $order;
    }

    public function test_the_served_wilayas_come_from_noest(): void
    {
        Http::fake([
            '*/api/public/get/wilayas' => Http::response([
                ['code' => 16, 'nom' => 'Alger', 'is_active' => 1],
                ['code' => 9,  'nom' => 'Blida', 'is_active' => 0],   // not served
            ], 200),
        ]);

        $served = (new NoestDriver())->wilayas();

        $this->assertArrayHasKey(16, $served);
        $this->assertArrayNotHasKey(9, $served);
    }
}
