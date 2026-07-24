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
            'product_ids'    => 'required|array|min:1',
            'product_ids.*'  => 'integer|exists:products,id',
            'platforms'      => 'required|array|min:1',
            'platforms.*'    => 'in:facebook,instagram,telegram',
            'telegram_mode'  => 'nullable|in:single,album',
            'telegram_intro' => 'nullable|string|max:200',
        ]);

        $available = $this->publisher->availablePlatforms();
        $platforms = array_values(array_intersect($data['platforms'], $available));

        if (empty($platforms)) {
            return back()->with('error', "Aucune plateforme sélectionnée n'est configurée (voir Paramètres).");
        }

        $products = Product::whereIn('id', $data['product_ids'])->get();

        // Telegram album: ONE grouped post for the whole selection. Handled
        // apart from the per-product loop, which stays for the other networks.
        $albumMode = ($data['telegram_mode'] ?? 'single') === 'album' && in_array('telegram', $platforms, true);
        $notes = [];

        if ($albumMode) {
            $platforms = array_values(array_diff($platforms, ['telegram']));
            $res = $this->publisher->publishTelegramAlbum($products, $data['telegram_intro'] ?? null);

            $notes[] = $res['ok']
                ? "Telegram : {$res['sent']} produit(s) publié(s) en album."
                : 'Telegram : échec — ' . ($res['error'] ?? 'erreur inconnue') . '.';

            if (($res['skipped'] ?? 0) > 0) {
                $notes[] = "{$res['skipped']} produit(s) sans photo ignoré(s).";
            }
        }

        $ok = 0;
        $fail = 0;
        if ($platforms) {
            foreach ($products as $product) {
                foreach ($this->publisher->publish($product, $platforms) as $res) {
                    $res['ok'] ? $ok++ : $fail++;
                }
            }
            $notes[] = "Autres plateformes : {$ok} réussie(s), {$fail} échec(s).";
        }

        $failedOnly = $albumMode ? ! str_contains($notes[0], 'publié') && ! $ok : ($fail && ! $ok);

        return back()->with($failedOnly ? 'error' : 'success', implode(' ', $notes));
    }
}
