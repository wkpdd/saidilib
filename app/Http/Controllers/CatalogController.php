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

        $categories = Category::active()->whereNull('parent_id')->withCount('products')->orderBy('sort_order')->get();
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

        // Total including subcategories (shown in the header).
        $descendantIds = $category->children->pluck('id')->push($category->id);
        $totalCount = Product::active()->whereIn('category_id', $descendantIds)->count();

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
