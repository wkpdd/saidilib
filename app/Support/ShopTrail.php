<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;

/**
 * Breadcrumb of where the shopper was, so "continue shopping" puts them back
 * on the exact listing page they left — same filters, same page number,
 * scrolled to the product they opened — instead of the top of /boutique.
 *
 * Two values in the session, nothing more:
 *   shop.listing — full URL of the last catalogue/category/home page seen
 *   shop.product — id of the last product opened from it
 *
 * The resume URL carries BOTH an anchor (#p12) and a query flag (?revoir=12):
 * the anchor alone gets a no-JS browser roughly there, the flag lets the page
 * centre the card and mark it "vous étiez ici".
 */
class ShopTrail
{
    private const LISTING = 'shop.listing';
    private const PRODUCT = 'shop.product';

    /** Called from the listing pages (home, catalogue, category). */
    public static function rememberListing(string $url): void
    {
        Session::put(self::LISTING, $url);
    }

    /** Called from the product page. */
    public static function rememberProduct(int $productId): void
    {
        Session::put(self::PRODUCT, $productId);
    }

    public static function lastProductId(): ?int
    {
        return Session::get(self::PRODUCT);
    }

    /**
     * Where "Continuer mes achats" / "Retour" should go.
     * Falls back to the catalogue for a shopper who landed straight on a
     * product page (search engine, shared link) and has no trail yet.
     */
    public static function resumeUrl(): string
    {
        $url = (string) Session::get(self::LISTING);

        // Only ever follow a URL we stored ourselves.
        if ($url === '' || ! str_starts_with($url, url('/'))) {
            $url = route('catalog');
        }

        $id = self::lastProductId();
        if (! $id) {
            return $url;
        }

        // Drop any fragment already on the stored URL before adding ours.
        $url = explode('#', $url, 2)[0];

        // route('home') has no path ("https://site.dz"); keep the query readable.
        if (parse_url($url, PHP_URL_PATH) === null) {
            $url .= '/';
        }

        $url .= (str_contains($url, '?') ? '&' : '?') . 'revoir=' . $id;

        return $url . '#p' . $id;
    }
}
