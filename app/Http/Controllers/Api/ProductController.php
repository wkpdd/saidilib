<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

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
        $product->load('category', 'variants');

        return response()->json(['product' => self::brief($product) + [
            'description'           => $product->description_fr,
            'short_desc'            => $product->short_desc_fr,
            'compare_at_price'      => (float) ($product->compare_at_price ?? 0),
            'wholesale_price'       => (float) ($product->wholesale_price ?? 0),
            'super_wholesale_price' => (float) ($product->super_wholesale_price ?? 0),
            'variants'              => $product->variants->map(fn ($v) => [
                'id'    => $v->id,
                'label' => $v->label_fr,
                'color' => $v->color,
                'size'  => $v->size,
                'stock' => (int) $v->stock,
            ]),
        ]]);
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
