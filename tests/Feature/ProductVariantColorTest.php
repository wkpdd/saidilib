<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

/**
 * Adding a colour variant from the admin form. The colour input defaults to
 * #000000, so picking BLACK fires no change event in the browser — the row
 * must still be saved as a colour, and the product must stay buyable.
 */
class ProductVariantColorTest extends TestCase
{
    private array $trash = [];

    protected function tearDown(): void
    {
        app(\App\Services\CartService::class)->clear();
        foreach (array_reverse($this->trash) as $model) {
            $model->forceDelete();
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::where('is_admin', true)->first();

        if (! $admin) {
            $admin = User::create([
                'name' => 'T', 'email' => 't-' . uniqid() . '@x.test', 'password' => bcrypt('xx'),
                'role' => 'admin', 'is_admin' => true, 'is_active' => true,
            ]);
            $this->trash[] = $admin;
        }

        return $admin;
    }

    private function product(): Product
    {
        $p = Product::create([
            'name_fr' => 'Cadre A4', 'slug' => 'cadre-' . uniqid(),
            'price' => 275, 'stock' => 20, 'is_active' => true, 'track_stock' => false,
        ]);
        $this->trash[] = $p;

        return $p;
    }

    /** The admin form's payload for one variant row. */
    private function save(Product $product, array $variant): void
    {
        $this->actingAs($this->admin())->put("/admin/products/{$product->id}", [
            'name_fr'  => $product->name_fr,
            'price'    => $product->price,
            'stock'    => $product->stock,
            'variants' => [$variant],
        ])->assertRedirect();
    }

    /**
     * The browser now posts has_color=1 as soon as the picker is opened (see
     * the jsdom test in tests/Feature/AdminVariantFormJsTest.php — that is
     * where the black-specific bug lived).
     */
    public function test_black_with_no_name_is_saved_as_the_colour_noir(): void
    {
        $product = $this->product();

        $this->save($product, [
            'color'     => '',            // no name typed
            'color_hex' => '#000000',     // black picked
            'has_color' => '1',
            'size'      => '',
            'stock'     => '5',
            'price_delta' => '0',
        ]);

        $variant = $product->fresh()->variants->first();

        $this->assertNotNull($variant, 'la variante n’a pas été enregistrée');
        $this->assertSame('#000000', $variant->color_hex, 'le noir doit rester une couleur');
        $this->assertSame('Noir', $variant->color);
        $this->assertSame('color', $variant->option_group);
    }

    public function test_the_black_swatch_shows_on_the_product_page(): void
    {
        $product = $this->product();
        $this->save($product, [
            'color' => '', 'color_hex' => '#000000', 'has_color' => '1',
            'size' => '', 'stock' => '5', 'price_delta' => '0',
        ]);

        $this->get("/produit/{$product->slug}")
            ->assertOk()
            ->assertSee('data-color="#000000"', false)
            ->assertSee('Noir');
    }

    /**
     * The ambiguous payload an older page could still post (colour untouched,
     * nothing else identifying the row): it must be dropped, not saved as a
     * nameless variant that blocks "Ajouter au panier".
     */
    public function test_an_unidentifiable_row_is_dropped_rather_than_saved_broken(): void
    {
        $product = $this->product();

        $this->save($product, [
            'color' => '', 'color_hex' => '#000000', 'has_color' => '0',
            'size' => '', 'stock' => '5', 'price_delta' => '0',
        ]);

        $this->assertCount(0, $product->fresh()->variants);

        // …and the product stays buyable with no variant at all.
        $this->post('/panier/ajouter', ['product_id' => $product->id, 'qty' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, app(\App\Services\CartService::class)->count());
    }

    public function test_a_variant_with_no_colour_and_no_size_never_blocks_the_sale(): void
    {
        $product = $this->product();

        // A row saved before this fix: no colour, no size, just stock.
        $variant = $product->variants()->create([
            'label_fr' => 'Standard', 'stock' => 5, 'price_delta' => 0, 'option_group' => 'size',
        ]);

        $res = $this->get("/produit/{$product->slug}")->assertOk();

        // Something selectable must be rendered, otherwise "Ajouter au panier"
        // answers "choisissez une option" and nothing can ever be bought.
        $res->assertSee('data-variant="' . $variant->id . '"', false);

        $this->post('/panier/ajouter', ['product_id' => $product->id, 'qty' => 1, 'variant_id' => $variant->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, app(\App\Services\CartService::class)->count());
    }

    public function test_an_entirely_empty_row_is_not_saved(): void
    {
        $product = $this->product();

        $this->save($product, [
            'color' => '', 'color_hex' => '#000000', 'has_color' => '0',
            'size' => '', 'stock' => '', 'price_delta' => '0', 'label_fr' => '',
        ]);

        $this->assertCount(0, $product->fresh()->variants);
    }

    public function test_a_size_row_stays_a_size_row(): void
    {
        $product = $this->product();

        $this->save($product, [
            'color' => '', 'color_hex' => '#000000', 'has_color' => '0',
            'size' => 'A4', 'stock' => '3', 'price_delta' => '0',
        ]);

        $variant = $product->fresh()->variants->first();

        $this->assertSame('A4', $variant->size);
        $this->assertNull($variant->color_hex, 'une taille ne doit pas devenir une couleur noire');
        $this->assertSame('size', $variant->option_group);
    }

    public function test_a_named_colour_still_works(): void
    {
        $product = $this->product();

        $this->save($product, [
            'color' => 'Rouge', 'color_hex' => '#dc2626', 'has_color' => '1',
            'size' => '', 'stock' => '7', 'price_delta' => '0',
        ]);

        $variant = $product->fresh()->variants->first();

        $this->assertSame('Rouge', $variant->color);
        $this->assertSame('#dc2626', $variant->color_hex);
    }

    // ── Stock (the reason cadr-a4 had no selectable colour) ───────────

    public function test_a_variant_with_no_stock_typed_is_not_treated_as_sold_out(): void
    {
        $product = $this->product();
        $product->update(['track_stock' => true, 'stock' => 20]);

        // Stock field left empty in the form → "vide = illimité".
        $this->save($product, [
            'color' => 'Noir', 'color_hex' => '#000000', 'has_color' => '1',
            'size' => '', 'stock' => '', 'price_delta' => '0',
        ]);

        $variant = $product->fresh()->variants->first();
        $this->assertNull($variant->stock, 'un stock laissé vide ne doit pas devenir 0');

        // The picker must see it as available (JSON feeding the swatches)…
        $this->get("/produit/{$product->slug}")->assertOk()->assertSee('"stock":null', false);

        // …and the sale must go through.
        $this->post('/panier/ajouter', ['product_id' => $product->id, 'qty' => 1, 'variant_id' => $variant->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, app(\App\Services\CartService::class)->count());
    }

    public function test_a_stock_of_zero_typed_on_purpose_still_blocks_the_sale(): void
    {
        $product = $this->product();
        $product->update(['track_stock' => true, 'stock' => 20]);

        $this->save($product, [
            'color' => 'Noir', 'color_hex' => '#000000', 'has_color' => '1',
            'size' => '', 'stock' => '0', 'price_delta' => '0',
        ]);

        $variant = $product->fresh()->variants->first();
        $this->assertSame(0, (int) $variant->stock);

        $this->post('/panier/ajouter', ['product_id' => $product->id, 'qty' => 1, 'variant_id' => $variant->id])
            ->assertSessionHas('error');
        $this->assertSame(0, app(\App\Services\CartService::class)->count());
    }

    public function test_availability_falls_back_to_the_product_stock(): void
    {
        $product = $this->product();
        $product->update(['track_stock' => true, 'stock' => 7]);

        $uncounted = $product->variants()->create(['label_fr' => 'Noir', 'color' => 'Noir', 'color_hex' => '#000000', 'stock' => null]);
        $counted   = $product->variants()->create(['label_fr' => 'Blanc', 'color' => 'Blanc', 'color_hex' => '#ffffff', 'stock' => 2]);

        $this->assertSame(7, $product->availableFor($uncounted));   // product's own stock
        $this->assertSame(2, $product->availableFor($counted));     // the smaller of the two
    }
}
