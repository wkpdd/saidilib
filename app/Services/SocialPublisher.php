<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Publishes a product as a REAL post (image + caption) to social platforms
 * via their APIs — Facebook Page (Graph), Instagram Business (Content
 * Publishing), and a Telegram channel. Tokens are set in admin Settings.
 *
 * Instagram/Facebook require the product image to be reachable at a PUBLIC URL,
 * so this only works once the site is deployed on a real domain (APP_URL).
 */
class SocialPublisher
{
    private string $graph;

    public function __construct()
    {
        $this->graph = 'https://graph.facebook.com/' . (Setting::get('fb_graph_version') ?: 'v19.0');
    }

    /** Platform keys that are currently configured. */
    public function availablePlatforms(): array
    {
        return array_keys(array_filter([
            'facebook'  => $this->facebookReady(),
            'instagram' => $this->instagramReady(),
            'telegram'  => $this->telegramReady(),
        ]));
    }

    public function facebookReady(): bool
    {
        return Setting::get('fb_page_id') && Setting::get('fb_page_token');
    }

    public function instagramReady(): bool
    {
        return Setting::get('ig_user_id') && (Setting::get('ig_token') || Setting::get('fb_page_token'));
    }

    public function telegramReady(): bool
    {
        return Setting::get('telegram_bot_token') && Setting::get('telegram_channel_id');
    }

    /**
     * Publish a product to the given platforms. Returns a list of per-platform
     * results and records each attempt in social_posts.
     */
    public function publish(Product $product, array $platforms): array
    {
        $caption = $this->caption($product);
        $image = $product->main_image_url;
        $results = [];

        foreach ($platforms as $platform) {
            $res = match ($platform) {
                'facebook'  => $this->postFacebook($caption, $image),
                'instagram' => $this->postInstagram($caption, $image),
                'telegram'  => $this->postTelegram($caption, $image),
                default     => ['ok' => false, 'error' => 'Plateforme inconnue.'],
            };

            SocialPost::create([
                'product_id'  => $product->id,
                'platform'    => $platform,
                'status'      => $res['ok'] ? 'success' : 'failed',
                'external_id' => $res['id'] ?? null,
                'permalink'   => $res['permalink'] ?? null,
                'message'     => $res['ok'] ? ($res['message'] ?? null) : ($res['error'] ?? 'Échec.'),
                'created_by'  => Auth::id(),
            ]);

            $results[$platform] = $res;
        }

        return $results;
    }

    public function caption(Product $product): string
    {
        $price = number_format((float) $product->price, 2, ',', ' ') . ' ' . Setting::get('currency', 'DA');
        $lines = [
            trim(implode(' ', array_filter([$product->name_fr, $product->sku, $product->brand]))),
            '💰 ' . $price . ' — 💵 Paiement à la livraison',
            $product->short_desc_fr,
            '🛒 ' . route('product', $product->slug),
        ];

        return trim(implode("\n", array_filter($lines)));
    }

    // ── Facebook Page ───────────────────────────────────────────────────────
    private function postFacebook(string $caption, string $imageUrl): array
    {
        if (! $this->facebookReady()) {
            return ['ok' => false, 'error' => 'Facebook non configuré.'];
        }

        try {
            $res = Http::timeout(30)->asForm()->post($this->graph . '/' . Setting::get('fb_page_id') . '/photos', [
                'url'          => $imageUrl,
                'caption'      => $caption,
                'access_token' => Setting::get('fb_page_token'),
            ]);
            $body = $res->json() ?? [];

            if ($res->successful() && ($body['id'] ?? $body['post_id'] ?? false)) {
                $id = $body['post_id'] ?? $body['id'];

                return ['ok' => true, 'id' => $id, 'permalink' => 'https://facebook.com/' . $id];
            }

            return ['ok' => false, 'error' => $body['error']['message'] ?? 'Réponse Facebook invalide.'];
        } catch (\Throwable $e) {
            Log::error('FB publish failed', ['e' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Instagram Business (two-step content publishing) ─────────────────────
    private function postInstagram(string $caption, string $imageUrl): array
    {
        if (! $this->instagramReady()) {
            return ['ok' => false, 'error' => 'Instagram non configuré.'];
        }

        $token = Setting::get('ig_token') ?: Setting::get('fb_page_token');
        $igUser = Setting::get('ig_user_id');

        try {
            $create = Http::timeout(30)->asForm()->post($this->graph . '/' . $igUser . '/media', [
                'image_url'    => $imageUrl,
                'caption'      => $caption,
                'access_token' => $token,
            ]);
            $creationId = $create->json('id');
            if (! $creationId) {
                return ['ok' => false, 'error' => $create->json('error.message') ?? 'Création média IG échouée.'];
            }

            $publish = Http::timeout(30)->asForm()->post($this->graph . '/' . $igUser . '/media_publish', [
                'creation_id'  => $creationId,
                'access_token' => $token,
            ]);
            $mediaId = $publish->json('id');

            return $mediaId
                ? ['ok' => true, 'id' => $mediaId]
                : ['ok' => false, 'error' => $publish->json('error.message') ?? 'Publication IG échouée.'];
        } catch (\Throwable $e) {
            Log::error('IG publish failed', ['e' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Telegram channel ─────────────────────────────────────────────────────
    private function postTelegram(string $caption, string $imageUrl): array
    {
        if (! $this->telegramReady()) {
            return ['ok' => false, 'error' => 'Telegram non configuré.'];
        }

        try {
            $res = Http::timeout(30)->asForm()->post(
                'https://api.telegram.org/bot' . Setting::get('telegram_bot_token') . '/sendPhoto',
                [
                    'chat_id' => Setting::get('telegram_channel_id'),
                    'photo'   => $imageUrl,
                    'caption' => Str::limit($caption, 1024),
                ]
            );
            $body = $res->json() ?? [];

            return ($body['ok'] ?? false)
                ? ['ok' => true, 'id' => (string) ($body['result']['message_id'] ?? '')]
                : ['ok' => false, 'error' => $body['description'] ?? 'Réponse Telegram invalide.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
