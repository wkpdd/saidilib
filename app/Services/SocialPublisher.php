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
            $this->footer(),
        ];

        return trim(implode("\n", array_filter($lines)));
    }

    /**
     * Signature appended to every published post. Editable in Settings so the
     * shop can change its wording without a deploy; the default carries the
     * store name and the developer credit.
     */
    public function footer(): string
    {
        $default = "— " . Setting::get('store_name', 'Saidi Papetrie')
            . "\n🌐 " . rtrim(config('app.url'), '/')
            . "\n⚙️ Développé par h47.io";

        // Saving the settings form stores '' for an untouched field, so an
        // empty value must mean "use the default", not "no footer at all".
        $custom = trim((string) Setting::get('social_footer', ''));

        return $custom !== '' ? $custom : $default;
    }

    /**
     * Caption for a grouped Telegram post: a numbered price list of everything
     * in the album. Telegram caps captions at 1024 chars, so the list is cut
     * before the footer rather than letting the API reject the whole post.
     */
    public function albumCaption($products, ?string $intro = null): string
    {
        $currency = Setting::get('currency', 'DA');
        $footer = "\n\n💵 Paiement à la livraison — 58 wilayas\n" . $this->footer();
        $head = trim($intro ?: ('🆕 Nouveautés — ' . Setting::get('store_name', 'Saidi Papetrie')));

        $body = '';
        $i = 0;
        $omitted = 0;
        foreach ($products as $product) {
            $i++;
            $line = $i . '. ' . trim(implode(' ', array_filter([$product->name_fr, $product->sku])))
                . ' — ' . number_format((float) $product->price, 2, ',', ' ') . ' ' . $currency . "\n";

            // Keep room for the head, the footer and a possible "+N autres" line.
            if (mb_strlen($head . $body . $line . $footer) > 980) {
                $omitted++;
                continue;
            }
            $body .= $line;
        }

        if ($omitted > 0) {
            $body .= "… +{$omitted} autre(s) sur le site\n";
        }

        return $head . "\n\n" . rtrim($body) . $footer;
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

    /**
     * Publish SEVERAL products to the Telegram channel as one album post
     * (sendMediaGroup), so subscribers get a single clean update instead of
     * one notification per product.
     *
     * Telegram allows 2–10 photos per album, so bigger selections are split
     * into consecutive albums. Products with no usable image are skipped and
     * reported back — an album with a broken URL fails as a whole.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    public function publishTelegramAlbum($products, ?string $intro = null): array
    {
        if (! $this->telegramReady()) {
            return ['ok' => false, 'error' => 'Telegram non configuré.', 'sent' => 0, 'skipped' => 0];
        }

        [$withImage, $skipped] = [collect(), 0];
        foreach ($products as $product) {
            $product->main_image_url ? $withImage->push($product) : $skipped++;
        }

        if ($withImage->isEmpty()) {
            return ['ok' => false, 'error' => 'Aucun produit sélectionné ne possède de photo.', 'sent' => 0, 'skipped' => $skipped];
        }

        // A single product has no album to build — fall back to a normal photo post.
        if ($withImage->count() === 1) {
            $only = $withImage->first();
            $res = $this->postTelegram($this->caption($only), $only->main_image_url);
            $this->recordTelegram($only, $res);

            return $res + ['sent' => $res['ok'] ? 1 : 0, 'skipped' => $skipped];
        }

        $sent = 0;
        $errors = [];

        foreach ($withImage->chunk(10) as $chunk) {
            $caption = $this->albumCaption($chunk, $intro);
            $media = [];

            foreach ($chunk->values() as $i => $product) {
                $media[] = array_filter([
                    'type'    => 'photo',
                    'media'   => $product->main_image_url,
                    // Only the first item may carry the album's caption.
                    'caption' => $i === 0 ? Str::limit($caption, 1024, '') : null,
                ]);
            }

            try {
                $res = Http::timeout(60)->asForm()->post(
                    'https://api.telegram.org/bot' . Setting::get('telegram_bot_token') . '/sendMediaGroup',
                    [
                        'chat_id' => Setting::get('telegram_channel_id'),
                        'media'   => json_encode($media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]
                );
                $body = $res->json() ?? [];
                $ok = $body['ok'] ?? false;
                $messageId = $ok ? (string) ($body['result'][0]['message_id'] ?? '') : null;

                if (! $ok) {
                    $errors[] = $body['description'] ?? 'Réponse Telegram invalide.';
                }

                // One social_posts row per product, sharing the album's message id.
                foreach ($chunk as $product) {
                    $this->recordTelegram($product, $ok
                        ? ['ok' => true, 'id' => $messageId, 'message' => 'Publié dans un album']
                        : ['ok' => false, 'error' => end($errors) ?: 'Échec.']);
                    $ok && $sent++;
                }
            } catch (\Throwable $e) {
                Log::error('Telegram album failed', ['e' => $e->getMessage()]);
                $errors[] = $e->getMessage();
                foreach ($chunk as $product) {
                    $this->recordTelegram($product, ['ok' => false, 'error' => $e->getMessage()]);
                }
            }
        }

        return [
            'ok'      => $sent > 0,
            'sent'    => $sent,
            'skipped' => $skipped,
            'error'   => $errors ? implode(' · ', array_unique($errors)) : null,
        ];
    }

    private function recordTelegram(Product $product, array $res): void
    {
        SocialPost::create([
            'product_id'  => $product->id,
            'platform'    => 'telegram',
            'status'      => $res['ok'] ? 'success' : 'failed',
            'external_id' => $res['id'] ?? null,
            'message'     => $res['ok'] ? ($res['message'] ?? null) : ($res['error'] ?? 'Échec.'),
            'created_by'  => Auth::id(),
        ]);
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
