<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Storefront product filtering in one place, so the catalogue page, a category
 * page and the "active filters" chips can never drift apart.
 *
 * Every filter is a plain query string key — no JS, no XHR: one GET request
 * applies the whole panel at once, which is what keeps this usable on a weak
 * mobile connection.
 */
class ProductFilter
{
    /** Query keys this filter owns. Anything else (page…) is left untouched. */
    public const KEYS = ['q', 'category', 'brand', 'min', 'max', 'stock', 'promo', 'new', 'shipping', 'sort'];

    /** Boolean "quick toggle" keys → translation key for the chip label. */
    public const TOGGLES = [
        'stock'    => 'shop.filter_in_stock',
        'promo'    => 'shop.filter_promo',
        'new'      => 'shop.filter_new',
        'shipping' => 'shop.filter_free_shipping',
    ];

    /** Sort values → translation key, in menu order. */
    public const SORTS = [
        'featured'   => 'shop.sort_featured',
        'newest'     => 'shop.sort_newest',
        'price_asc'  => 'shop.sort_price_asc',
        'price_desc' => 'shop.sort_price_desc',
        'popular'    => 'shop.sort_popular',
        'name'       => 'shop.sort_name',
    ];

    /**
     * @param  Category|null  $scope  Category page context: products are already
     *                                limited to it, so the `category` key is ignored.
     */
    public function __construct(private Request $request, private ?Category $scope = null)
    {
    }

    /** Facets are asked for twice per page (sidebar + mobile drawer) — memoise. */
    private ?Collection $brands = null;

    private ?array $bounds = null;

    // -----------------------------------------------------------------------
    // Query building
    // -----------------------------------------------------------------------

    /** Accepts a relation too ($category->products()), which forwards to the builder. */
    public function apply(Builder|Relation $query): Builder|Relation
    {
        $request = $this->request;

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name_fr', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($ids = $this->categoryIds()) {
            $query->whereIn('category_id', $ids);
        }

        if ($brands = $this->selectedBrands()) {
            $query->whereIn('brand', $brands);
        }

        if ($request->filled('min')) {
            $query->where('price', '>=', (float) $request->query('min'));
        }
        if ($request->filled('max')) {
            $query->where('price', '<=', (float) $request->query('max'));
        }

        if ($request->boolean('stock')) {
            $query->where(fn (Builder $q) => $q->where('track_stock', false)->orWhere('stock', '>', 0));
        }
        if ($request->boolean('promo')) {
            $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price');
        }
        if ($request->boolean('new')) {
            $query->where('is_new', true);
        }
        if ($request->boolean('shipping')) {
            $query->where('free_shipping', true)->where(fn (Builder $q) => $q
                ->whereNull('free_shipping_until')
                ->orWhereDate('free_shipping_until', '>=', now()->toDateString()));
        }

        return $this->sort($query);
    }

    private function sort(Builder|Relation $query): Builder|Relation
    {
        $nameColumn = app()->getLocale() === 'ar' ? 'name_ar' : 'name_fr';

        return match ($this->request->query('sort')) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest'     => $query->latest(),
            'popular'    => $query->orderByDesc('views'),
            'name'       => $query->orderBy($nameColumn),
            default      => $query->orderByDesc('is_featured')->latest(),
        };
    }

    /** Category + ALL descendants (any depth), or null when browsing everything. */
    private function categoryIds(): ?Collection
    {
        if ($this->scope) {
            return null; // the page already scoped the query
        }

        $slug = $this->request->query('category');
        if (! $slug) {
            return null;
        }

        $category = Category::where('slug', $slug)->first();

        return $category ? collect($category->descendantIds()) : null;
    }

    /** @return array<int, string> */
    public function selectedBrands(): array
    {
        return array_values(array_filter(
            array_map('strval', (array) $this->request->query('brand', [])),
            fn ($b) => $b !== ''
        ));
    }

    // -----------------------------------------------------------------------
    // Facets — what the panel offers, counted against the current context
    // -----------------------------------------------------------------------

    /**
     * Brands available in the current category/search context, with counts.
     * The brand selection itself is ignored so the list stays multi-selectable.
     *
     * @return Collection<int, object{brand: string, total: int}>
     */
    public function brands(): Collection
    {
        return $this->brands ??= $this->contextQuery()
            ->whereNotNull('brand')->where('brand', '!=', '')
            ->selectRaw('brand, COUNT(*) as total')
            ->groupBy('brand')
            ->orderBy('brand')
            ->get();
    }

    /** Cheapest / dearest product in context, for the price input placeholders. */
    public function priceBounds(): array
    {
        if ($this->bounds === null) {
            $row = $this->contextQuery()->selectRaw('MIN(price) as low, MAX(price) as high')->first();
            $this->bounds = [
                'low'  => (int) floor((float) ($row->low ?? 0)),
                'high' => (int) ceil((float) ($row->high ?? 0)),
            ];
        }

        return $this->bounds;
    }

    /** Base query for facet counts: category + search only, no refinements. */
    private function contextQuery(): Builder
    {
        $query = Product::active();

        if ($this->scope) {
            $query->where('category_id', $this->scope->id);
        } elseif ($ids = $this->categoryIds()) {
            $query->whereIn('category_id', $ids);
        }

        if ($search = trim((string) $this->request->query('q'))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name_fr', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    // -----------------------------------------------------------------------
    // Active state — chips, counts and reset links
    // -----------------------------------------------------------------------

    /** True when at least one refinement (sort aside) is on. */
    public function isActive(): bool
    {
        return $this->activeCount() > 0;
    }

    public function activeCount(): int
    {
        return count($this->chips());
    }

    /**
     * One entry per removable refinement: the label to show and the URL that
     * drops just that one.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public function chips(): array
    {
        $request = $this->request;
        $chips = [];

        if ($search = trim((string) $request->query('q'))) {
            $chips[] = ['label' => '“' . $search . '”', 'url' => $this->urlWithout('q')];
        }

        if (! $this->scope && ($slug = $request->query('category'))) {
            $category = Category::where('slug', $slug)->first();
            if ($category) {
                $chips[] = [
                    'label' => trim($category->icon . ' ' . $category->name),
                    'url'   => $this->urlWithout('category'),
                ];
            }
        }

        foreach ($this->selectedBrands() as $brand) {
            $chips[] = [
                'label' => __('shop.brand') . ' : ' . $brand,
                'url'   => $this->urlWithoutBrand($brand),
            ];
        }

        if ($request->filled('min') || $request->filled('max')) {
            $min = $request->filled('min') ? (int) $request->query('min') : null;
            $max = $request->filled('max') ? (int) $request->query('max') : null;
            $label = match (true) {
                $min !== null && $max !== null => "{$min} – {$max} DA",
                $min !== null                  => '≥ ' . $min . ' DA',
                default                        => '≤ ' . $max . ' DA',
            };
            $chips[] = ['label' => $label, 'url' => $this->urlWithout('min', 'max')];
        }

        foreach (self::TOGGLES as $key => $label) {
            if ($request->boolean($key)) {
                $chips[] = ['label' => __($label), 'url' => $this->urlWithout($key)];
            }
        }

        return $chips;
    }

    /** Current URL with every filter cleared (sort and other keys kept). */
    public function resetUrl(): string
    {
        return $this->urlWithout(...array_diff(self::KEYS, ['sort']));
    }

    /** Current URL with some filters set/replaced (null or '' drops a key). */
    public function urlWith(array $params): string
    {
        $query = array_filter(
            array_merge($this->request->query(), $params),
            fn ($value) => $value !== null && $value !== ''
        );
        unset($query['page']);

        return $this->request->url() . ($query ? '?' . http_build_query($query) : '');
    }

    public function urlWithout(string ...$keys): string
    {
        $query = $this->request->query();
        foreach ([...$keys, 'page'] as $key) {
            unset($query[$key]);
        }

        return $this->request->url() . ($query ? '?' . http_build_query($query) : '');
    }

    private function urlWithoutBrand(string $brand): string
    {
        $query = $this->request->query();
        unset($query['page']);
        $remaining = array_values(array_filter($this->selectedBrands(), fn ($b) => $b !== $brand));

        if ($remaining) {
            $query['brand'] = $remaining;
        } else {
            unset($query['brand']);
        }

        return $this->request->url() . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * Flat name/value pairs to carry the *other* filters through a small form
     * (the sort dropdown, the price box) without losing them.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function hiddenExcept(array $keys): array
    {
        $keys = [...$keys, 'page'];
        $fields = [];

        foreach ($this->request->query() as $name => $value) {
            if (in_array($name, $keys, true)) {
                continue;
            }
            foreach ((array) $value as $item) {
                if (is_scalar($item)) {
                    $fields[] = ['name' => is_array($value) ? $name . '[]' : $name, 'value' => (string) $item];
                }
            }
        }

        return $fields;
    }
}
