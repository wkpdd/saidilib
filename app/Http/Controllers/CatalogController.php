<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\PixelService;
use App\Support\ProductFilter;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $filter = new ProductFilter($request);

        $products = $filter->apply(Product::active()->with('category', 'images'))
            ->paginate(12)->withQueryString();

        // Top-level categories with a count spanning their WHOLE subtree
        // (a category like "Scolaire" holds most of its products in nested
        // sub-categories, not directly).
        $categories = Category::active()->whereNull('parent_id')->orderBy('sort_order')->get();
        $countsByCat = Product::active()->selectRaw('category_id, COUNT(*) as c')
            ->groupBy('category_id')->pluck('c', 'category_id');
        foreach ($categories as $cat) {
            $cat->products_count = collect($cat->descendantIds())
                ->sum(fn ($id) => (int) ($countsByCat[$id] ?? 0));
        }
        $activeCategory = $request->query('category')
            ? Category::where('slug', $request->query('category'))->first()
            : null;

        // Remember this exact view (filters, sort, page) so "continue shopping" returns here.
        \App\Support\ShopTrail::rememberListing($request->fullUrl());

        return view('storefront.catalog', compact('products', 'categories', 'activeCategory', 'filter'));
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

        // Product "files" directly inside this category — same filter panel as
        // the catalogue, scoped to this folder.
        $filter = new ProductFilter($request, $category);
        $products = $filter->apply($category->products()->active()->with('images'))
            ->paginate(12)->withQueryString();

        // Total across the WHOLE subtree, at any depth (shown in the header).
        $totalCount = Product::active()->whereIn('category_id', $category->descendantIds())->count();

        \App\Support\ShopTrail::rememberListing($request->fullUrl());

        return view('storefront.category', compact('category', 'ancestors', 'products', 'totalCount', 'filter'));
    }

    public function show(string $slug, PixelService $pixels)
    {
        $product = Product::active()->with(['images', 'variants.image', 'category', 'pixels', 'quantityTiers'])
            ->where('slug', $slug)->firstOrFail();

        $product->increment('views');

        // Mark this product so the listing can flag it "vous étiez ici" on return.
        \App\Support\ShopTrail::rememberProduct($product->id);

        $related = Product::active()->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)->take(4)->get();

        $pagePixels = $pixels->forPage($product);

        return view('storefront.product', compact('product', 'related', 'pagePixels'));
    }
}
