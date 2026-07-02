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
        $query = Product::active()->with('category');

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

    public function category(string $slug, Request $request)
    {
        $category = Category::active()->where('slug', $slug)->firstOrFail();
        $request->merge(['category' => $slug]);

        return $this->index($request)->with('activeCategory', $category);
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
