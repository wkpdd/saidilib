<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SocialPost;
use App\Services\SocialPublisher;
use Illuminate\Http\Request;

class SocialController extends Controller
{
    public function __construct(private SocialPublisher $publisher) {}

    public function index(Request $request)
    {
        $query = Product::query()->with('category')->latest();
        if ($search = $request->query('q')) {
            $query->where('name_fr', 'like', "%{$search}%");
        }
        if ($request->query('filter') === 'new') {
            $query->where('is_new', true);
        }

        $products = $query->paginate(24)->withQueryString();
        $posts = SocialPost::with('product', 'author')->latest()->take(20)->get();

        return view('admin.social.index', [
            'products'  => $products,
            'posts'     => $posts,
            'available' => $this->publisher->availablePlatforms(),
        ]);
    }

    public function publish(Request $request)
    {
        $data = $request->validate([
            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
            'platforms'     => 'required|array|min:1',
            'platforms.*'   => 'in:facebook,instagram,telegram',
        ]);

        $available = $this->publisher->availablePlatforms();
        $platforms = array_values(array_intersect($data['platforms'], $available));

        if (empty($platforms)) {
            return back()->with('error', "Aucune plateforme sélectionnée n'est configurée (voir Paramètres).");
        }

        $ok = 0;
        $fail = 0;
        foreach (Product::whereIn('id', $data['product_ids'])->get() as $product) {
            foreach ($this->publisher->publish($product, $platforms) as $res) {
                $res['ok'] ? $ok++ : $fail++;
            }
        }

        $msg = "Publication terminée : {$ok} réussie(s), {$fail} échec(s).";

        return back()->with($fail && ! $ok ? 'error' : 'success', $msg);
    }
}
