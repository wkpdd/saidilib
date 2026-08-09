<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Hands out unique DOM ids for product cards.
 *
 * The same product legitimately appears in several sections of one page
 * (Vedette AND Nouveautés on the home page), and two elements sharing
 * id="p15" is invalid HTML.
 *
 * The FIRST card for a product on the page keeps the canonical `p{id}` —
 * that's the anchor ShopTrail links to (`…?revoir=15#p15`), so a browser with
 * no JavaScript still jumps to the right place. Every later card for the same
 * product is suffixed with its section: `p15--nouveautes`.
 *
 * Registered as a SCOPED binding, so the tally resets on each request.
 */
class CardAnchors
{
    /** product id => how many cards rendered so far this request */
    private array $seen = [];

    public function idFor(int $productId, ?string $section = null): string
    {
        $n = $this->seen[$productId] = ($this->seen[$productId] ?? 0) + 1;

        // First one owns the anchor the return URL points at.
        if ($n === 1) {
            return 'p' . $productId;
        }

        $suffix = $section ? Str::slug($section) : '';

        // The counter stays even with a section name: two cards for the same
        // product inside one section would otherwise collide again.
        return 'p' . $productId . '--' . ($suffix ? $suffix . '-' : '') . $n;
    }
}
