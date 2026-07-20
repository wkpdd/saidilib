<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Product-photo search via Google's official Custom Search JSON API.
 *
 * Deliberately does NOT scrape Google Images — that violates Google's Terms
 * of Service and is fragile/unreliable. This uses the documented, sanctioned
 * API, which requires the store owner's own (free-tier available) API key and
 * Programmable Search Engine ID, entered in Settings.
 *
 * By default only images labelled for reuse are returned (Google's "rights"
 * filter), since most web images are NOT cleared for commercial e-commerce
 * use. The admin can disable that filter in Settings, at their own judgement.
 */
class GoogleImageSearch
{
    private const ENDPOINT = 'https://www.googleapis.com/customsearch/v1';

    public function isConfigured(): bool
    {
        return trim((string) Setting::get('google_cse_key', '')) !== ''
            && trim((string) Setting::get('google_cse_cx', '')) !== '';
    }

    /**
     * @return array{ok: bool, results?: array<int, array{title:string, thumbnail:string, image:string, context:string}>, error?: string}
     */
    public function search(string $query, int $count = 10): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => "Recherche d'images non configurée (voir Paramètres)."];
        }

        $params = [
            'key'        => Setting::get('google_cse_key'),
            'cx'         => Setting::get('google_cse_cx'),
            'q'          => $query,
            'searchType' => 'image',
            'num'        => max(1, min(10, $count)),
            'safe'       => 'active',
        ];

        // Reuse-rights filter is ON by default — protects the merchant from
        // accidentally using copyrighted photos they have no licence to use.
        if (Setting::get('google_cse_reuse_only', '1') !== '0') {
            $params['rights'] = 'cc_publicdomain,cc_attribute,cc_sharealike,cc_noncommercial,cc_nonderived';
        }

        try {
            $res = Http::timeout(15)->get(self::ENDPOINT, $params);
            $body = $res->json() ?? [];

            if (! $res->successful()) {
                $msg = $body['error']['message'] ?? 'Erreur inconnue de l\'API Google.';

                return ['ok' => false, 'error' => $msg];
            }

            $results = collect($body['items'] ?? [])->map(fn ($item) => [
                'title'     => $item['title'] ?? '',
                'thumbnail' => $item['image']['thumbnailLink'] ?? ($item['link'] ?? ''),
                'image'     => $item['link'] ?? '',
                'context'   => $item['image']['contextLink'] ?? '',
            ])->filter(fn ($r) => $r['image'] !== '')->values()->all();

            return ['ok' => true, 'results' => $results];
        } catch (\Throwable $e) {
            Log::error('Google image search failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'Erreur de connexion à Google.'];
        }
    }
}
