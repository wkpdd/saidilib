<?php

namespace Tests\Feature;

use App\Models\Pack;
use App\Models\PackItem;
use App\Models\Product;
use App\Services\CartService;
use Tests\TestCase;

/**
 * A pack is sold as a whole. Its per-line prices are discounted, so the cart
 * must never let a customer keep that price on a single article — that is a
 * direct loss on every order.
 */
class PackCartTest extends TestCase
{
    private array $trash = [];

    protected function tearDown(): void
    {
        $this->cart()->clear();
        foreach (array_reverse($this->trash) as $model) {
            $model->forceDelete();
        }
        parent::tearDown();
    }

    private function cart(): CartService
    {
        return app(CartService::class);
    }

    private function product(float $price): Product
    {
        $p = Product::create([
            'name_fr' => 'Cahier test', 'slug' => 'c-' . uniqid(),
            'price' => $price, 'stock' => 500, 'is_active' => true,
        ]);
        $this->trash[] = $p;

        return $p;
    }

    /** Pack of 10 × 100 DA (1000 DA of goods) sold at 500 DA → 50 DA a unit. */
    private function pack(int $qty = 10, float $unitPrice = 100, float $promo = 500): array
    {
        $product = $this->product($unitPrice);

        $pack = Pack::create([
            'name_fr' => 'Pack test', 'slug' => 'pack-' . uniqid(),
            'price' => $promo, 'is_active' => true,
        ]);
        $this->trash[] = $pack;

        $this->trash[] = PackItem::create([
            'pack_id' => $pack->id, 'product_id' => $product->id,
            'quantity' => $qty, 'sort_order' => 0,
        ]);

        return [$pack->fresh(), $product];
    }

    // ── The exploit ───────────────────────────────────────────────────

    public function test_a_pack_line_cannot_be_reduced_to_a_single_cheap_item(): void
    {
        [$pack, $product] = $this->pack();

        $this->get("/pack/{$pack->slug}");
        $this->post("/pack/{$pack->slug}/ajouter")->assertRedirect(route('cart.index'));

        $this->assertSame(500.0, round($this->cart()->subtotal(), 2));

        $key = array_key_first($this->cart()->items());
        $line = $this->cart()->items()[$key];
        $this->assertSame(50.0, (float) $line['price']);          // pack unit price
        $this->assertSame(10, (int) $line['qty']);

        // The attack: keep the 50 DA unit price, take a single unit.
        $this->patch('/panier', ['key' => $key, 'qty' => 1])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(10, (int) $this->cart()->items()[$key]['qty'], 'la ligne du pack a été modifiée');
        $this->assertSame(500.0, round($this->cart()->subtotal(), 2));
    }

    public function test_a_pack_line_cannot_be_inflated_at_the_pack_price(): void
    {
        [$pack] = $this->pack();
        $this->post("/pack/{$pack->slug}/ajouter");

        $key = array_key_first($this->cart()->items());

        $this->patch('/panier', ['key' => $key, 'qty' => 100])->assertSessionHas('error');

        $this->assertSame(10, (int) $this->cart()->items()[$key]['qty']);
        $this->assertSame(500.0, round($this->cart()->subtotal(), 2));
    }

    public function test_buying_the_product_on_its_own_keeps_the_normal_price(): void
    {
        [$pack, $product] = $this->pack();

        $this->post("/pack/{$pack->slug}/ajouter");
        $this->post('/panier/ajouter', ['product_id' => $product->id, 'qty' => 1]);

        // Two separate lines: the pack keeps 50 DA, the loose unit costs 100.
        $lines = $this->cart()->lines();
        $this->assertCount(2, $lines);
        $this->assertSame(600.0, round($this->cart()->subtotal(), 2));   // 500 + 100

        $loose = collect($lines)->firstWhere('pack_id', null);
        $this->assertSame(100.0, (float) $loose['price']);
    }

    public function test_the_loose_line_is_still_freely_editable(): void
    {
        [$pack, $product] = $this->pack();
        $this->post("/pack/{$pack->slug}/ajouter");
        $this->post('/panier/ajouter', ['product_id' => $product->id, 'qty' => 1]);

        $key = collect($this->cart()->items())->search(fn ($l) => empty($l['pack_id']));

        $this->patch('/panier', ['key' => $key, 'qty' => 3])->assertSessionHasNoErrors();

        $this->assertSame(800.0, round($this->cart()->subtotal(), 2));   // 500 + 3×100
    }

    // ── Buying several packs ──────────────────────────────────────────

    public function test_the_customer_can_buy_several_packs(): void
    {
        [$pack] = $this->pack();
        $this->post("/pack/{$pack->slug}/ajouter");

        $this->patch('/panier', ['pack_id' => $pack->id, 'qty' => 3])->assertSessionHasNoErrors();

        $this->assertSame(1500.0, round($this->cart()->subtotal(), 2));  // 3 × 500
        $this->assertSame(30, $this->cart()->count());
    }

    public function test_adding_the_same_pack_twice_stacks_it(): void
    {
        [$pack] = $this->pack();

        $this->post("/pack/{$pack->slug}/ajouter");
        $this->post("/pack/{$pack->slug}/ajouter");

        $this->assertSame(1000.0, round($this->cart()->subtotal(), 2));
        $this->assertCount(1, $this->cart()->items());                   // still one line
        $this->assertSame(2, $this->cart()->groups()[0]['pack']['qty']);
    }

    public function test_removing_one_line_removes_the_whole_pack(): void
    {
        [$pack] = $this->pack();
        $this->post("/pack/{$pack->slug}/ajouter");

        $key = array_key_first($this->cart()->items());
        $this->delete('/panier', ['key' => $key]);

        $this->assertSame([], $this->cart()->items());
    }

    public function test_the_pack_quantity_can_be_zeroed_from_the_cart(): void
    {
        [$pack] = $this->pack();
        $this->post("/pack/{$pack->slug}/ajouter");

        $this->patch('/panier', ['pack_id' => $pack->id, 'qty' => 0]);

        $this->assertSame([], $this->cart()->items());
    }

    // ── No double discount ────────────────────────────────────────────

    public function test_quantity_breaks_do_not_stack_on_top_of_the_pack_price(): void
    {
        [$pack, $product] = $this->pack();

        // A 50% quantity break from 5 units would halve the pack price again.
        $this->trash[] = \App\Models\ProductQuantityTier::create([
            'product_id' => $product->id, 'min_qty' => 5, 'discount_percent' => 50,
        ]);

        $this->post("/pack/{$pack->slug}/ajouter");

        $line = collect($this->cart()->lines())->first();
        $this->assertSame(0.0, (float) $line['discount_percent']);
        $this->assertSame(500.0, round($this->cart()->subtotal(), 2));
    }

    // ── Display ───────────────────────────────────────────────────────

    public function test_the_cart_shows_the_pack_as_one_block(): void
    {
        [$pack] = $this->pack();
        $this->post("/pack/{$pack->slug}/ajouter");

        $res = $this->get('/panier')->assertOk();

        $res->assertSee($pack->name_fr);
        $res->assertSee('×10');                                   // fixed line quantity
        $res->assertSee('name="pack_id"', false);                 // pack-level controls
        $this->assertSame(1, substr_count($res->getContent(), 'name="qty"'));
    }
}
