<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ImageOptimizer;
use App\Support\Thumbnailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->latest();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name_fr', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }
        if ($request->boolean('low_stock')) {
            $query->where('track_stock', true)->where('stock', '<=', 3);
        }

        $page = $query->paginate(20);

        return response()->json([
            'products' => collect($page->items())->map(fn ($p) => self::brief($p)),
            'has_more' => $page->hasMorePages(),
            'page'     => $page->currentPage(),
        ]);
    }

    public function show(Product $product)
    {
        return response()->json(['product' => self::full($product)]);
    }

    /** Create a complete product from the app. */
    public function store(Request $request)
    {
        $data = $this->validateFull($request);
        $data['slug'] = $this->uniqueSlug($data['name_fr']);

        $product = Product::create(collect($data)->except('variants')->all());
        $this->syncVariants($product, $data['variants'] ?? []);

        return response()->json(['ok' => true, 'product' => self::full($product->fresh())], 201);
    }

    /** Full edit (all fields + variants) — distinct from the quick PATCH. */
    public function fullUpdate(Request $request, Product $product)
    {
        $data = $this->validateFull($request);
        if ($data['name_fr'] !== $product->name_fr) {
            $data['slug'] = $this->uniqueSlug($data['name_fr'], $product->id);
        }

        $product->update(collect($data)->except('variants')->all());
        if ($request->has('variants')) {
            $this->syncVariants($product, $data['variants'] ?? []);
        }

        return response()->json(['ok' => true, 'product' => self::full($product->fresh())]);
    }

    /** Upload one product photo (camera/gallery) — same optimize+thumbs pipeline as the web. */
    public function storeImage(Request $request, Product $product)
    {
        $request->validate(['image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:10240']);

        $optimized = ImageOptimizer::optimize(file_get_contents($request->file('image')->getRealPath()));
        $path = 'products/' . Str::random(32) . '.jpg';
        Storage::disk('public')->put($path, $optimized);
        Thumbnailer::generateAll($path);

        $image = $product->images()->create(['path' => $path, 'sort_order' => $product->images()->count()]);
        if (! $product->main_image) {
            $product->update(['main_image' => $path]);
        }

        return response()->json(['ok' => true, 'product' => self::full($product->fresh())], 201);
    }

    public function deleteImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);

        $image->delete();
        // Keep main_image coherent when it pointed at the removed photo.
        if ($product->main_image === $image->path) {
            $product->update(['main_image' => $product->images()->orderBy('sort_order')->first()?->path]);
        }

        return response()->json(['ok' => true, 'product' => self::full($product->fresh())]);
    }

    public function setMainImage(Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        $product->update(['main_image' => $image->path]);

        return response()->json(['ok' => true, 'product' => self::full($product->fresh())]);
    }

    /** Exact-SKU lookup for the barcode scanner. */
    public function lookup(Request $request)
    {
        $sku = trim((string) $request->query('sku'));
        if ($sku === '') {
            return response()->json(['found' => false], 422);
        }

        $product = Product::where('sku', $sku)->first();

        return $product
            ? response()->json(['found' => true, 'product' => self::brief($product)])
            : response()->json(['found' => false]);
    }

    /** Quick edits from the app: price, stock, active flag. */
    public function quickUpdate(Request $request, Product $product)
    {
        $data = $request->validate([
            'price'           => 'nullable|numeric|min:0|max:99999999',
            'wholesale_price' => 'nullable|numeric|min:0|max:99999999',
            'stock'           => 'nullable|integer|min:0',
            'track_stock'     => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
        ]);

        $product->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['ok' => true, 'product' => self::brief($product->fresh())]);
    }

    // -----------------------------------------------------------------

    private function validateFull(Request $request): array
    {
        return $request->validate([
            'name_fr'               => 'required|string|max:200',
            'name_ar'               => 'nullable|string|max:200',
            'category_id'           => 'nullable|exists:categories,id',
            'brand'                 => 'nullable|string|max:120',
            'sku'                   => 'nullable|string|max:80',
            'short_desc_fr'         => 'nullable|string|max:500',
            'description_fr'        => 'nullable|string',
            'price'                 => 'required|numeric|min:0|max:99999999',
            'compare_at_price'      => 'nullable|numeric|min:0|max:99999999',
            'wholesale_price'       => 'nullable|numeric|min:0|max:99999999',
            'super_wholesale_price' => 'nullable|numeric|min:0|max:99999999',
            'stock'                 => 'nullable|integer|min:0',
            'track_stock'           => 'nullable|boolean',
            'is_active'             => 'nullable|boolean',
            'is_featured'           => 'nullable|boolean',
            'is_new'                => 'nullable|boolean',
            'variants'              => 'nullable|array',
            'variants.*.id'         => 'nullable|integer',
            'variants.*.color'      => 'nullable|string|max:60',
            'variants.*.color_hex'  => 'nullable|string|max:9',
            'variants.*.size'       => 'nullable|string|max:60',
            'variants.*.stock'      => 'nullable|integer|min:0',
            'variants.*.price_delta'=> 'nullable|numeric',
        ]);
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

    /** Simplified variant sync for the app (one row = colour+size combo). */
    private function syncVariants(Product $product, array $variants): void
    {
        $keep = [];
        foreach ($variants as $i => $v) {
            $color = trim($v['color'] ?? '');
            $hex   = trim($v['color_hex'] ?? '');
            $size  = trim($v['size'] ?? '');
            if ($color === '' && $hex === '' && $size === '') {
                continue;
            }

            $attrs = [
                'label_fr'     => trim(implode(' · ', array_filter([$color ?: $hex, $size]))),
                'color'        => $color ?: null,
                'color_hex'    => $hex ?: null,
                'size'         => $size ?: null,
                'option_group' => $hex !== '' || $color !== '' ? 'color' : 'size',
                'price_delta'  => (float) ($v['price_delta'] ?? 0),
                'stock'        => (int) ($v['stock'] ?? 0),
                'sort_order'   => $i,
            ];

            if (! empty($v['id']) && $product->variants()->where('id', $v['id'])->exists()) {
                $product->variants()->where('id', $v['id'])->update($attrs);
                $keep[] = (int) $v['id'];
            } else {
                $keep[] = $product->variants()->create($attrs)->id;
            }
        }
        $product->variants()->whereNotIn('id', $keep)->delete();
    }

    /** Detail payload: brief + long fields + images + variants. */
    public static function full(Product $product): array
    {
        $product->load('category', 'variants', 'images');

        return self::brief($product) + [
            'description'           => $product->description_fr,
            'short_desc'            => $product->short_desc_fr,
            'name_ar'               => $product->name_ar,
            'category_id'           => $product->category_id,
            'compare_at_price'      => (float) ($product->compare_at_price ?? 0),
            'wholesale_price'       => (float) ($product->wholesale_price ?? 0),
            'super_wholesale_price' => (float) ($product->super_wholesale_price ?? 0),
            'is_featured'           => (bool) $product->is_featured,
            'is_new'                => (bool) $product->is_new,
            'images'                => $product->images->map(fn ($im) => [
                'id'      => $im->id,
                'url'     => $im->url,
                'thumb'   => $im->thumb_url,
                'is_main' => $im->path === $product->main_image,
            ]),
            'variants'              => $product->variants->map(fn ($v) => [
                'id'          => $v->id,
                'label'       => $v->label_fr,
                'color'       => $v->color,
                'color_hex'   => $v->color_hex,
                'size'        => $v->size,
                'stock'       => (int) $v->stock,
                'price_delta' => (float) $v->price_delta,
            ]),
        ];
    }

    public static function brief(Product $product): array
    {
        return [
            'id'           => $product->id,
            'name'         => $product->name_fr,
            'display_name' => trim(implode(' ', array_filter([$product->name_fr, $product->sku, $product->brand]))),
            'sku'          => $product->sku,
            'brand'        => $product->brand,
            'category'     => $product->category?->name_fr,
            'price'        => (float) $product->price,
            'stock'        => (int) ($product->stock ?? 0),
            'track_stock'  => (bool) $product->track_stock,
            'is_active'    => (bool) $product->is_active,
            'image'        => \App\Support\Thumbnailer::url($product->mainImagePath(), 300),
        ];
    }
}
