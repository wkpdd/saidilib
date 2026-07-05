<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\PixelService;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::active()->with('category', 'images');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name_fr', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($catSlug = $request->query('category')) {
            $category = Category::where('slug', $catSlug)->first();
            if ($category) {
                $ids = $category->children->pluck('id')->push($category->id);
                $query->whereIn('category_id', $ids);
            }
        }

        if ($request->filled('min')) {
            $query->where('price', '>=', (float) $request->query('min'));
        }
        if ($request->filled('max')) {
            $query->where('price', '<=', (float) $request->query('max'));
        }

        match ($request->query('sort')) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest'     => $query->latest(),
            default      => $query->orderByDesc('is_featured')->latest(),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::active()->whereNull('parent_id')->withCount('products')->orderBy('sort_order')->get();

        return view('storefront.catalog', compact('products', 'categories'));
    }

    /**
     * Windows-Explorer-style category browse: breadcrumb path, subcategory
     * "folders" on top, then this category's product "files" below.
     */
    public function category(string $slug, Request $request)
    {
        $category = Category::active()->where('slug', $slug)
            ->with(['children' => fn ($q) => $q->where('is_active', true)
                ->withCount(['products' => fn ($p) => $p->where('is_active', true)])
                ->orderBy('sort_order')])
            ->firstOrFail();

        // Breadcrumb: walk parent chain up to the root.
        $ancestors = collect();
        $node = $category->parent;
        while ($node) {
            $ancestors->prepend($node);
            $node = $node->parent;
        }

        // Product "files" directly inside this category.
        $sort = $request->query('sort');
        $query = $category->products()->active()->with('images');
        match ($sort) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest'     => $query->latest(),
            default      => $query->orderByDesc('is_featured')->latest(),
        };
        $products = $query->paginate(12)->withQueryString();

        // Total including subcategories (shown in the header).
        $descendantIds = $category->children->pluck('id')->push($category->id);
        $totalCount = Product::active()->whereIn('category_id', $descendantIds)->count();

        return view('storefront.category', compact('category', 'ancestors', 'products', 'totalCount'));
    }

    public function show(string $slug, PixelService $pixels)
    {
        $product = Product::active()->with(['images', 'variants.image', 'category', 'pixels'])
            ->where('slug', $slug)->firstOrFail();

        $product->increment('views');

        $related = Product::active()->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)->take(4)->get();

        $pagePixels = $pixels->forPage($product);

        return view('storefront.product', compact('product', 'related', 'pagePixels'));
    }
}
