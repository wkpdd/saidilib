<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Picking sheets (A4 + bulk) and the Noest workflow: ready → create+validate,
 * tracking refresh, labels. Noest is faked — no request ever leaves the box.
 *
 * Runs against the configured database (no RefreshDatabase); every row it
 * creates is removed in tearDown, like the other feature tests here.
 */
class OrderPrintAndNoestTest extends TestCase
{
    private array $trash = [];

    protected function setUp(): void
    {
        parent::setUp();

        Setting::put('noest_token', 'test-token', 'noest');
        Setting::put('noest_guid', 'test-guid', 'noest');
        Setting::put('noest_enabled', '1', 'noest');
        Setting::put('noest_auto_ready', '1', 'noest');
        Setting::flush();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->trash) as $model) {
            $model->forceDelete();
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::where('role', 'admin')->where('is_admin', true)->first();

        if (! $admin) {
            $admin = User::create([
                'name'      => 'Test Admin',
                'email'     => 'test-admin-' . uniqid() . '@saidi.test',
                'password'  => bcrypt('secret-secret'),
                'role'      => 'admin',
                'is_admin'  => true,
                'is_active' => true,
            ]);
            $this->trash[] = $admin;
        }

        return $admin;
    }

    private function order(array $attrs = []): Order
    {
        $product = Product::create([
            'name_fr'  => 'Cahier de test',
            'slug'     => 'cahier-test-' . uniqid(),
            'sku'      => 'TST-' . strtoupper(substr(uniqid(), -5)),
            'brand'    => 'Hilal',
            'price'    => 120,
            'stock'    => 50,
            'is_active'=> true,
        ]);
        $this->trash[] = $product;

        // A dispatchable order: Noest refuses anything without a wilaya.
        $wilaya = \App\Models\Wilaya::firstOrCreate(
            ['code' => 16],
            ['name_fr' => 'Alger', 'name_ar' => 'الجزائر', 'is_active' => true]
        );
        if ($wilaya->wasRecentlyCreated) {
            $this->trash[] = $wilaya;
        }

        $order = Order::create(array_merge([
            'reference'      => 'TEST-' . strtoupper(substr(uniqid(), -6)),
            'customer_name'  => 'Client Test',
            'phone'          => '0550505050',
            'wilaya_id'      => $wilaya->id,
            'commune'        => 'Bab Ezzouar',
            'address'        => 'Rue des Martyrs',
            'delivery_type'  => 'home',
            'subtotal'       => 240,
            'delivery_fee'   => 400,
            'total'          => 640,
            'status'         => 'confirmed',
            'notes'          => 'Appeler avant livraison',
        ], $attrs));
        $this->trash[] = $order;

        $item = OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'name'       => $product->name_fr,
            'unit_price' => 120,
            'quantity'   => 2,
            'line_total' => 240,
        ]);
        $this->trash[] = $item;

        return $order->fresh();
    }

    // ── Picking sheets ────────────────────────────────────────────────

    public function test_single_picking_sheet_renders_with_the_pickers_data(): void
    {
        $order = $this->order();

        $res = $this->actingAs($this->admin())->get("/admin/orders/{$order->id}/print")->assertOk();

        $res->assertSee('FICHE DE PRÉPARATION');
        $res->assertSee($order->reference);
        $res->assertSee('Cahier de test');
        $res->assertSee('Client Test');
        $res->assertSee('Appeler avant livraison');
        $res->assertSee('640 DA');                       // amount to collect
        $res->assertSee('Préparée par', false);
    }

    public function test_bulk_print_returns_one_sheet_per_selected_order(): void
    {
        $a = $this->order();
        $b = $this->order();

        $res = $this->actingAs($this->admin())
            ->get('/admin/orders/print?ids=' . $a->id . ',' . $b->id)
            ->assertOk();

        $res->assertSee($a->reference);
        $res->assertSee($b->reference);
        $this->assertSame(2, substr_count($res->getContent(), 'FICHE DE PRÉPARATION'));
    }

    public function test_bulk_print_falls_back_to_the_current_filter(): void
    {
        $order = $this->order(['status' => 'preparing']);

        $this->actingAs($this->admin())
            ->get('/admin/orders/print?status=preparing')
            ->assertOk()
            ->assertSee($order->reference);
    }

    public function test_print_can_move_the_batch_to_preparing(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $this->actingAs($this->admin())
            ->get('/admin/orders/print?ids=' . $order->id . '&mark_preparing=1')
            ->assertOk();

        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_picking_sheet_shows_where_the_stock_sits(): void
    {
        $order = $this->order();
        $item = $order->items()->first();

        $location = \App\Models\StockLocation::firstOrCreate(['name' => 'Rayon A'], ['sort_order' => 9]);
        $this->trash[] = $level = \App\Models\StockLevel::create([
            'stock_location_id'  => $location->id,
            'product_id'         => $item->product_id,
            'product_variant_id' => null,
            'quantity'           => 7,
        ]);

        $this->actingAs($this->admin())->get("/admin/orders/{$order->id}/print")
            ->assertOk()
            ->assertSee('Rayon A')
            ->assertSee('Emplacement');

        $this->assertSame(7, $level->quantity);
    }

    public function test_order_page_offers_ready_print_and_tracking(): void
    {
        $order = $this->order([
            'delivery_provider' => 'noest',
            'tracking_number'   => 'TRK-SHOW',
            'dispatched_at'     => now(),
            'noest_status'      => 'En livraison',
            'noest_driver'      => 'Livreur 001 · 0550000000',
            'noest_activity'    => [['event' => 'Validé', 'date' => '2026-08-02 10:00:00', 'causer' => 'PARTENAIRE']],
            'noest_checked_at'  => now(),
        ]);

        $this->actingAs($this->admin())->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Fiche de préparation (A4)')
            ->assertSee('Commande prête → Noest', false)
            ->assertSee('Vérifier le suivi')
            ->assertSee('En livraison')
            ->assertSee('Livreur 001')
            ->assertSee('Étiquette Noest (PDF)', false);
    }

    public function test_orders_list_offers_the_bulk_toolbar(): void
    {
        $this->order();

        $this->actingAs($this->admin())->get('/admin/orders')->assertOk()
            ->assertSee('Fiches de préparation')
            ->assertSee('Prêtes → Noest', false)
            ->assertSee('Étiquettes Noest', false);
    }

    // ── Noest: ready → created + validated ────────────────────────────

    public function test_marking_ready_creates_and_validates_the_parcel_at_noest(): void
    {
        Http::fake([
            '*/api/public/create/order' => Http::response(['success' => true, 'tracking' => 'TRK-TEST-1'], 200),
            '*/api/public/valid/order'  => Http::response(['success' => true], 200),
        ]);

        $order = $this->order();

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/ready")->assertRedirect();

        $order->refresh();
        $this->assertSame('ready', $order->status);
        $this->assertNotNull($order->ready_at);
        $this->assertSame('noest', $order->delivery_provider);
        $this->assertSame('TRK-TEST-1', $order->tracking_number);
        $this->assertTrue($order->is_carrier_validated);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'create/order') && $r['user_guid'] === 'test-guid');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'valid/order') && $r['tracking'] === 'TRK-TEST-1');
    }

    public function test_ready_does_not_call_noest_when_auto_send_is_off(): void
    {
        Setting::put('noest_auto_ready', '0', 'noest');
        Setting::flush();
        Http::fake();

        $order = $this->order();
        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/ready")->assertRedirect();

        $this->assertSame('ready', $order->fresh()->status);
        $this->assertNull($order->fresh()->tracking_number);
        Http::assertNothingSent();
    }

    public function test_an_already_created_parcel_is_only_validated_once(): void
    {
        Http::fake([
            '*/api/public/valid/order' => Http::response(['success' => true], 200),
        ]);

        $order = $this->order([
            'delivery_provider' => 'noest',
            'tracking_number'   => 'TRK-EXISTING',
            'dispatched_at'     => now(),
        ]);

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/ready")->assertRedirect();

        $this->assertTrue($order->fresh()->is_carrier_validated);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'create/order'));
    }

    public function test_bulk_ready_pushes_every_selected_order(): void
    {
        Http::fake([
            '*/api/public/create/order' => Http::response(['success' => true, 'tracking' => 'TRK-BULK'], 200),
            '*/api/public/valid/order'  => Http::response(['success' => true], 200),
        ]);

        $a = $this->order();
        $b = $this->order();

        $this->actingAs($this->admin())
            ->post('/admin/orders/bulk-ready', ['ids' => $a->id . ',' . $b->id])
            ->assertRedirect();

        $this->assertSame('ready', $a->fresh()->status);
        $this->assertSame('ready', $b->fresh()->status);
        $this->assertSame('TRK-BULK', $b->fresh()->tracking_number);
    }

    public function test_a_bulk_action_with_nothing_ticked_touches_nothing(): void
    {
        Http::fake();
        $order = $this->order(['status' => 'confirmed']);

        $this->actingAs($this->admin())->post('/admin/orders/bulk-ready', ['ids' => ''])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame('confirmed', $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_noest_error_is_reported_and_does_not_fake_success(): void
    {
        Http::fake([
            '*/api/public/create/order' => Http::response(['success' => false, 'message' => 'commune inexistante ou non activée'], 422),
        ]);

        $order = $this->order();

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/ready")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($order->fresh()->tracking_number);
    }

    // ── Noest: tracking ───────────────────────────────────────────────

    private function fakeTracking(string $tracking, string $event, string $key): void
    {
        Http::fake([
            '*/api/public/get/trackings/info' => Http::response([
                $tracking => [
                    'OrderInfo' => ['tracking' => $tracking, 'driver_name' => 'Livreur 001', 'driver_tel' => '0550000000'],
                    'activity'  => [
                        ['event' => 'Uploadé sur le système', 'event_key' => 'upload', 'causer' => 'PARTENAIRE', 'date' => '2026-08-01 09:00:00'],
                        ['event' => $event, 'event_key' => $key, 'causer' => 'NOEST', 'date' => '2026-08-02 11:30:00'],
                    ],
                    'deliveryAttempts' => [],
                ],
            ], 200),
        ]);
    }

    public function test_refreshing_tracking_stores_the_timeline_and_driver(): void
    {
        $this->fakeTracking('TRK-9', 'En livraison', 'fdr_activated');

        $order = $this->order([
            'delivery_provider' => 'noest',
            'tracking_number'   => 'TRK-9',
            'dispatched_at'     => now(),
            'status'            => 'ready',
        ]);

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/tracking")
            ->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('En livraison', $order->noest_status);
        $this->assertSame('fdr_activated', $order->noest_status_key);
        $this->assertStringContainsString('Livreur 001', (string) $order->noest_driver);
        $this->assertCount(2, $order->noest_activity);
        $this->assertNotNull($order->noest_checked_at);
        $this->assertSame('shipped', $order->status);          // carrier moved it on
    }

    public function test_a_delivered_parcel_closes_the_order(): void
    {
        $this->fakeTracking('TRK-10', 'Livré', 'livre');

        $order = $this->order([
            'delivery_provider' => 'noest',
            'tracking_number'   => 'TRK-10',
            'dispatched_at'     => now(),
            'status'            => 'shipped',
        ]);

        $this->actingAs($this->admin())->post("/admin/orders/{$order->id}/tracking")->assertRedirect();

        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_tracking_board_lists_parcels_and_refreshes_in_batch(): void
    {
        $this->fakeTracking('TRK-11', 'Colis Ramassé', 'validation_collect_colis');

        $order = $this->order([
            'delivery_provider' => 'noest',
            'tracking_number'   => 'TRK-11',
            'dispatched_at'     => now(),
            'status'            => 'ready',
        ]);

        $this->actingAs($this->admin())->get('/admin/orders/tracking')
            ->assertOk()->assertSee('TRK-11');

        $this->actingAs($this->admin())
            ->post('/admin/orders/tracking/refresh', ['ids' => (string) $order->id])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Colis Ramassé', $order->fresh()->noest_status);
        // Not an end state: the local status stays where the admin left it.
        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_the_staff_app_marking_ready_also_pushes_to_noest(): void
    {
        Http::fake([
            '*/api/public/create/order' => Http::response(['success' => true, 'tracking' => 'TRK-APP'], 200),
            '*/api/public/valid/order'  => Http::response(['success' => true], 200),
        ]);

        $order = $this->order();
        $token = \App\Models\ApiToken::issue($this->admin(), 'test-device');

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'ready'])
            ->assertOk();

        $res->assertJsonPath('carrier.ok', true);

        $order->refresh();
        $this->assertSame('ready', $order->status);
        $this->assertNotNull($order->ready_at);
        $this->assertSame('TRK-APP', $order->tracking_number);
        $this->assertTrue($order->is_carrier_validated);

        \App\Models\ApiToken::where('user_id', $this->admin()->id)->delete();
    }

    // ── Noest: labels ─────────────────────────────────────────────────

    public function test_bulk_labels_download_a_zip_of_the_noest_pdfs(): void
    {
        Http::fake([
            '*/api/public/get/order/label*' => Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $a = $this->order(['delivery_provider' => 'noest', 'tracking_number' => 'TRK-A', 'dispatched_at' => now()]);
        $b = $this->order(['delivery_provider' => 'noest', 'tracking_number' => 'TRK-B', 'dispatched_at' => now()]);

        $res = $this->actingAs($this->admin())
            ->post('/admin/orders/labels', ['ids' => $a->id . ',' . $b->id])
            ->assertOk();

        $this->assertStringContainsString('attachment', strtolower($res->headers->get('content-disposition') ?? ''));
        $this->assertStringContainsString('etiquettes-noest', strtolower($res->headers->get('content-disposition') ?? ''));

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($res->baseResponse->getFile()->getPathname()) === true);
        $this->assertSame(2, $zip->numFiles);
        $this->assertStringContainsString('TRK-A', $zip->getNameIndex(0) . $zip->getNameIndex(1));
        $this->assertStringStartsWith('%PDF', $zip->getFromIndex(0));
        $zip->close();
    }

    public function test_labels_reports_when_nothing_was_dispatched(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin())
            ->post('/admin/orders/labels', ['ids' => (string) $order->id])
            ->assertRedirect()->assertSessionHas('error');
    }
}
