<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $categories = Category::active()->whereNull('parent_id')
            ->withCount('products')->orderBy('sort_order')->get();

        $featured = Product::active()->where('is_featured', true)
            ->with('category', 'images')->latest()->take(8)->get();

        $newArrivals = Product::active()->where('is_new', true)
            ->with('category', 'images')->latest()->take(8)->get();

        $onSale = Product::active()->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->with('category', 'images')->take(8)->get();

        // Special "Games for Kids" showcase.
        $kidsCategory = Category::where('slug', 'jeux-pour-enfants')->first();
        $kidsProducts = $kidsCategory
            ? Product::active()->where('category_id', $kidsCategory->id)
                ->with('category', 'images')->latest()->take(8)->get()
            : collect();

        return view('storefront.home', compact(
            'categories', 'featured', 'newArrivals', 'onSale', 'kidsCategory', 'kidsProducts'
        ));
    }

    public function about()
    {
        return view('storefront.about');
    }

    public function contact()
    {
        return view('storefront.contact');
    }
}
