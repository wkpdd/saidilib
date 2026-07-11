<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Pixel;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->latest();
        if ($search = $request->query('q')) {
            $query->where('name_fr', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
        }
        $products = $query->paginate(20)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product'    => new Product(['is_active' => true]),
            'categories' => Category::orderBy('name_fr')->get(),
            'pixels'     => Pixel::all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['name_fr']);

        $product = Product::create($data);
        $this->syncImages($request, $product);
        $this->syncVariants($request, $product);
        $product->pixels()->sync($request->input('pixels', []));

        return redirect()->route('admin.products.edit', $product)->with('success', 'Produit créé.');
    }

    public function edit(Product $product)
    {
        $product->load('images', 'variants');

        return view('admin.products.form', [
            'product'    => $product,
            'categories' => Category::orderBy('name_fr')->get(),
            'pixels'     => Pixel::all(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateData($request);
        if ($data['name_fr'] !== $product->name_fr) {
            $data['slug'] = $this->uniqueSlug($data['name_fr'], $product->id);
        }

        $product->update($data);
        $this->syncImages($request, $product);
        $this->syncVariants($request, $product);
        $product->pixels()->sync($request->input('pixels', []));

        return back()->with('success', 'Produit mis à jour.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Produit supprimé.');
    }

    // ---------------------------------------------------------------

    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'name_fr'          => 'required|string|max:200',
            'name_ar'          => 'nullable|string|max:200',
            'category_id'      => 'nullable|exists:categories,id',
            'brand'            => 'nullable|string|max:120',
            'sku'              => 'nullable|string|max:80',
            'short_desc_fr'    => 'nullable|string|max:500',
            'short_desc_ar'    => 'nullable|string|max:500',
            'description_fr'   => 'nullable|string',
            'description_ar'   => 'nullable|string',
            'price'                 => 'required|numeric|min:0',
            'compare_at_price'      => 'nullable|numeric|min:0',
            'wholesale_price'       => 'nullable|numeric|min:0',
            'super_wholesale_price' => 'nullable|numeric|min:0',
            'stock'            => 'nullable|integer|min:0',
            'track_stock'      => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
            'is_featured'      => 'nullable|boolean',
            'is_new'           => 'nullable|boolean',
            // Uploaded gallery images: real images only, capped in size/count.
            'images'           => 'nullable|array|max:12',
            'images.*'         => 'image|mimes:jpeg,jpg,png,webp,gif|max:5120',
            'image_urls'       => 'nullable|string|max:5000',
        ]);

        // `images` / `image_urls` are NOT product columns — they're handled by
        // syncImages(). Strip them so they don't leak into Product::update().
        unset($validated['images'], $validated['image_urls']);

        return $validated;
    }

    private function uniqueSlug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (Product::where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function syncImages(Request $request, Product $product): void
    {
        // Delete removed images
        foreach ((array) $request->input('delete_images', []) as $id) {
            $product->images()->where('id', $id)->delete();
        }

        // External image URLs (one per line)
        if ($urls = $request->input('image_urls')) {
            foreach (preg_split('/\r\n|\r|\n/', $urls) as $url) {
                $url = trim($url);
                if ($url !== '') {
                    $product->images()->create(['path' => $url, 'sort_order' => $product->images()->count()]);
                }
            }
        }

        // Uploaded files
        foreach ((array) $request->file('images', []) as $file) {
            $path = $file->store('products', 'public');
            \App\Support\Thumbnailer::generateAll($path);
            $product->images()->create(['path' => $path, 'sort_order' => $product->images()->count()]);
        }

        // Main image = first image if none set
        $first = $product->images()->orderBy('sort_order')->first();
        if ($first && ! $product->main_image) {
            $product->update(['main_image' => $first->path]);
        }
    }

    private function syncVariants(Request $request, Product $product): void
    {
        $variants = $request->input('variants', []);
        $keepIds = [];

        // Valid image ids for this product (so a colour can only map to its own photo).
        $imageIds = $product->images()->pluck('id')->all();

        foreach ($variants as $i => $v) {
            $color = trim($v['color'] ?? '');
            $size  = trim($v['size'] ?? '');
            $label = trim($v['label_fr'] ?? '');
            $colorHex = trim($v['color_hex'] ?? '');
            $stock = trim((string) ($v['stock'] ?? ''));
            $priceDelta = trim((string) ($v['price_delta'] ?? ''));

            // The colour NAME is optional: picking a swatch is enough. The form
            // sets a hidden "has_color" flag via JS the moment the admin touches
            // the colour picker (or it's pre-set to 1 when editing a variant that
            // already has a colour), so we don't rely on the name being filled.
            $isColorRow = $color !== '' || ! empty($v['has_color']);

            // Derive a display label from colour + size when none was provided.
            if ($label === '') {
                $label = trim(implode(' · ', array_filter([$color, $size])));
            }
            // Skip genuinely empty rows (no name/colour/size and nothing else set).
            if ($label === '' && $color === '' && $size === '' && ! $isColorRow
                && $stock === '' && ($priceDelta === '' || (float) $priceDelta === 0.0)) {
                continue;
            }

            $imageId = ! empty($v['image_id']) && in_array((int) $v['image_id'], $imageIds, true)
                ? (int) $v['image_id']
                : null;

            $displayColor = $color ?: ($isColorRow ? $this->colorNameFromHex($colorHex) : null);

            $attrs = [
                'label_fr'    => $label ?: ($displayColor ?: $size),
                'label_ar'    => $v['label_ar'] ?? null,
                'color'       => $displayColor,
                'color_hex'   => $isColorRow ? ($colorHex ?: null) : null,
                'size'        => $size ?: null,
                'image_id'    => $imageId,
                'option_group'=> $isColorRow ? 'color' : 'size',
                'price_delta' => (float) ($v['price_delta'] ?? 0),
                'stock'       => (int) ($v['stock'] ?? 0),
                'is_default'  => isset($v['is_default']) && $v['is_default'],
                'sort_order'  => $i,
            ];

            if (! empty($v['id'])) {
                $product->variants()->where('id', $v['id'])->update($attrs);
                $keepIds[] = $v['id'];
            } else {
                $new = $product->variants()->create($attrs);
                $keepIds[] = $new->id;
            }
        }

        $product->variants()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * Friendly French name for a swatch when the admin picked a colour but
     * left the name field blank. Falls back to the hex code when no close
     * match is found, so the customer never sees a blank label.
     */
    private function colorNameFromHex(string $hex): ?string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return null;
        }

        $palette = [
            // This app's own palette first, so its buttons/brand shades match exactly.
            'Rouge' => 'dc2626', 'Bleu' => '2563eb', 'Orange' => 'e07d00',
            'Noir' => '000000', 'Blanc' => 'ffffff', 'Gris' => '808080',
            'Bordeaux' => '800000', 'Rose' => 'ffc0cb',
            'Marron' => 'a52a2a', 'Beige' => 'f5f5dc',
            'Jaune' => 'ffff00', 'Doré' => 'ffd700',
            'Vert' => '008000', 'Vert clair' => '90ee90',
            'Bleu clair' => 'add8e6', 'Bleu marine' => '000080',
            'Violet' => '800080', 'Turquoise' => '40e0d0', 'Argenté' => 'c0c0c0',
        ];

        [$r, $g, $b] = sscanf($hex, '%02x%02x%02x');
        $best = null;
        $bestDist = null;
        foreach ($palette as $name => $swatch) {
            [$pr, $pg, $pb] = sscanf($swatch, '%02x%02x%02x');
            $dist = ($r - $pr) ** 2 + ($g - $pg) ** 2 + ($b - $pb) ** 2;
            if ($bestDist === null || $dist < $bestDist) {
                $bestDist = $dist;
                $best = $name;
            }
        }

        // A close-enough match reads as a real colour name; otherwise show the hex.
        return $bestDist !== null && $bestDist < 9000 ? $best : ('#' . strtoupper($hex));
    }
}
