<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pack;
use App\Models\Product;
use App\Models\Setting;
use App\Support\ImageOptimizer;
use App\Support\Thumbnailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** School supply packs (listes scolaires) — CRUD + home-section toggle. */
class PackController extends Controller
{
    public function index()
    {
        $packs = Pack::withCount('items')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.packs.index', [
            'packs'   => $packs,
            'enabled' => Pack::homeEnabled(),
        ]);
    }

    /** Show/hide the packs section on the home page. */
    public function toggle(Request $request)
    {
        Setting::put('packs_enabled', $request->boolean('enabled') ? '1' : '0');

        return back()->with('success', $request->boolean('enabled')
            ? 'Section Packs AFFICHÉE sur la page d\'accueil.'
            : 'Section Packs masquée.');
    }

    public function create()
    {
        return view('admin.packs.form', [
            'pack'     => new Pack(['is_active' => true]),
            'products' => Product::orderBy('name_fr')->get(['id', 'name_fr', 'sku', 'price']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['name_fr']);
        $data['sort_order'] = Pack::count();

        $pack = Pack::create(collect($data)->except(['image', 'items'])->all());
        $this->saveImage($request, $pack);
        $this->syncItems($pack, $data['items'] ?? []);

        return redirect()->route('admin.packs.edit', $pack)->with('success', 'Pack créé.');
    }

    public function edit(Pack $pack)
    {
        $pack->load('items.product', 'items.variant');

        return view('admin.packs.form', [
            'pack'     => $pack,
            'products' => Product::orderBy('name_fr')->get(['id', 'name_fr', 'sku', 'price']),
        ]);
    }

    public function update(Request $request, Pack $pack)
    {
        $data = $this->validateData($request);
        if ($data['name_fr'] !== $pack->name_fr) {
            $data['slug'] = $this->uniqueSlug($data['name_fr'], $pack->id);
        }

        $pack->update(collect($data)->except(['image', 'items'])->all());
        $this->saveImage($request, $pack);
        $this->syncItems($pack, $data['items'] ?? []);

        return back()->with('success', 'Pack mis à jour.');
    }

    public function destroy(Pack $pack)
    {
        $pack->delete();

        return redirect()->route('admin.packs.index')->with('success', 'Pack supprimé.');
    }

    // -----------------------------------------------------------------

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name_fr'             => 'required|string|max:190',
            'name_ar'             => 'nullable|string|max:190',
            'description_fr'      => 'nullable|string|max:2000',
            'description_ar'      => 'nullable|string|max:2000',
            'price'               => 'nullable|numeric|min:0|max:99999999',
            'is_active'           => 'nullable|boolean',
            'image'               => 'nullable|image|mimes:jpeg,jpg,png,webp|max:8192',
            'items'               => 'nullable|array|max:100',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.variant_id'  => 'nullable|integer',
            'items.*.quantity'    => 'required|integer|min:1|max:999',
        ]) + ['is_active' => $request->boolean('is_active'), 'price' => $request->input('price') ?: null];
    }

    private function uniqueSlug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name) ?: 'pack';
        $slug = $base;
        $i = 1;
        while (Pack::where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function saveImage(Request $request, Pack $pack): void
    {
        if (! $request->hasFile('image')) {
            return;
        }
        $optimized = ImageOptimizer::optimize(file_get_contents($request->file('image')->getRealPath()));
        $path = 'packs/' . Str::random(32) . '.jpg';
        Storage::disk('public')->put($path, $optimized);
        Thumbnailer::generateAll($path);
        $pack->update(['image' => $path]);
    }

    private function syncItems(Pack $pack, array $items): void
    {
        $pack->items()->delete();
        foreach (array_values($items) as $i => $row) {
            $product = Product::find($row['product_id']);
            if (! $product) {
                continue;
            }
            $variantId = ! empty($row['variant_id'])
                ? $product->variants()->where('id', $row['variant_id'])->value('id')
                : null;
            $pack->items()->create([
                'product_id'         => $product->id,
                'product_variant_id' => $variantId,
                'quantity'           => (int) $row['quantity'],
                'sort_order'         => $i,
            ]);
        }
    }
}
