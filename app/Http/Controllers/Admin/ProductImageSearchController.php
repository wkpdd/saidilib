<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\GoogleImageSearch;
use App\Support\Thumbnailer;
use App\Support\Watermarker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageSearchController extends Controller
{
    /** Hard cap on a fetched image's size (bytes) — 8MB. */
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private GoogleImageSearch $search) {}

    /** JSON search results for the modal's results grid. */
    public function search(Request $request)
    {
        $data = $request->validate(['q' => 'required|string|max:150']);

        return response()->json($this->search->search($data['q']));
    }

    /**
     * Download the chosen image, stamp the store logo on it, and attach it to
     * the product — reusing the same storage + thumbnail pipeline as a normal
     * upload. The URL is server-fetched (not user-uploaded), so it gets the
     * same SSRF guards as any other "fetch a remote URL" feature: scheme
     * allowlist, private/loopback IP rejection, and a hard size cap.
     */
    public function attach(Request $request, Product $product)
    {
        $data = $request->validate(['image_url' => 'required|url|max:2000']);
        $url = $data['image_url'];

        if ($error = $this->guardUrl($url)) {
            return response()->json(['ok' => false, 'error' => $error], 422);
        }

        try {
            $res = Http::timeout(15)->withOptions(['stream' => false])->get($url);
        } catch (\Throwable $e) {
            Log::warning('Image search fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'error' => "Impossible de télécharger cette image."], 422);
        }

        if (! $res->successful()) {
            return response()->json(['ok' => false, 'error' => "L'image n'a pas pu être téléchargée (HTTP {$res->status()})."], 422);
        }

        $bytes = $res->body();
        if (strlen($bytes) > self::MAX_BYTES) {
            return response()->json(['ok' => false, 'error' => 'Image trop volumineuse (max 8 Mo).'], 422);
        }

        // Must decode as a real image — refuses HTML error pages, SVGs, etc.
        if (@getimagesizefromstring($bytes) === false) {
            return response()->json(['ok' => false, 'error' => "Le fichier téléchargé n'est pas une image valide."], 422);
        }

        $watermarked = Watermarker::apply($bytes, public_path('logov2.jpeg'));

        $path = 'products/' . $product->slug . '-google-' . Str::random(8) . '.jpg';
        Storage::disk('public')->put($path, $watermarked);
        Thumbnailer::generateAll($path);

        $image = $product->images()->create([
            'path'       => $path,
            'sort_order' => $product->images()->count(),
        ]);
        if (! $product->main_image) {
            $product->update(['main_image' => $path]);
        }

        return response()->json(['ok' => true, 'image' => ['id' => $image->id, 'url' => $image->url]]);
    }

    /**
     * Reject anything that isn't a plain public http(s) URL. Resolves the
     * hostname and blocks loopback/private/link-local ranges to prevent this
     * server-side fetch from being used to probe internal services.
     */
    private function guardUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return 'URL invalide.';
        }

        $host = $parts['host'];
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return "Impossible de résoudre l'hôte de cette image.";
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Cette adresse image est refusée pour des raisons de sécurité.';
        }

        return null;
    }
}
