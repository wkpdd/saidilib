<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Pixel;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->latest();
        if ($search = $request->query('q')) {
            $query->where('name_fr', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
        }
        $products = $query->paginate(20)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product'    => new Product(['is_active' => true]),
            'categories' => Category::orderBy('name_fr')->get(),
            'pixels'     => Pixel::all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['name_fr']);

        $product = Product::create($data);
        $this->syncImages($request, $product);
        $this->syncVariants($request, $product);
        $product->pixels()->sync($request->input('pixels', []));

        return redirect()->route('admin.products.edit', $product)->with('success', 'Produit créé.');
    }

    public function edit(Product $product)
    {
        $product->load('images', 'variants');

        return view('admin.products.form', [
            'product'    => $product,
            'categories' => Category::orderBy('name_fr')->get(),
            'pixels'     => Pixel::all(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateData($request);
        if ($data['name_fr'] !== $product->name_fr) {
            $data['slug'] = $this->uniqueSlug($data['name_fr'], $product->id);
        }

        $product->update($data);
        $this->syncImages($request, $product);
        $this->syncVariants($request, $product);
        $product->pixels()->sync($request->input('pixels', []));

        return back()->with('success', 'Produit mis à jour.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Produit supprimé.');
    }

    // ---------------------------------------------------------------

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name_fr'          => 'required|string|max:200',
            'name_ar'          => 'nullable|string|max:200',
            'category_id'      => 'nullable|exists:categories,id',
            'brand'            => 'nullable|string|max:120',
            'sku'              => 'nullable|string|max:80',
            'short_desc_fr'    => 'nullable|string|max:500',
            'short_desc_ar'    => 'nullable|string|max:500',
            'description_fr'   => 'nullable|string',
            'description_ar'   => 'nullable|string',
            'price'            => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock'            => 'nullable|integer|min:0',
            'track_stock'      => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
            'is_featured'      => 'nullable|boolean',
            'is_new'           => 'nullable|boolean',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (Product::where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function syncImages(Request $request, Product $product): void
    {
        // Delete removed images
        foreach ((array) $request->input('delete_images', []) as $id) {
            $product->images()->where('id', $id)->delete();
        }

        // External image URLs (one per line)
        if ($urls = $request->input('image_urls')) {
            foreach (preg_split('/\r\n|\r|\n/', $urls) as $url) {
                $url = trim($url);
                if ($url !== '') {
                    $product->images()->create(['path' => $url, 'sort_order' => $product->images()->count()]);
                }
            }
        }

        // Uploaded files
        foreach ((array) $request->file('images', []) as $file) {
            $path = $file->store('products', 'public');
            $product->images()->create(['path' => $path, 'sort_order' => $product->images()->count()]);
        }

        // Main image = first image if none set
        $first = $product->images()->orderBy('sort_order')->first();
        if ($first && ! $product->main_image) {
            $product->update(['main_image' => $first->path]);
        }
    }

    private function syncVariants(Request $request, Product $product): void
    {
        $variants = $request->input('variants', []);
        $keepIds = [];

        foreach ($variants as $i => $v) {
            $label = trim($v['label_fr'] ?? '');
            if ($label === '') {
                continue;
            }
            $attrs = [
                'label_fr'    => $label,
                'label_ar'    => $v['label_ar'] ?? null,
                'option_group'=> $v['option_group'] ?? 'size',
                'price_delta' => (float) ($v['price_delta'] ?? 0),
                'stock'       => (int) ($v['stock'] ?? 0),
                'is_default'  => isset($v['is_default']) && $v['is_default'],
                'sort_order'  => $i,
            ];

            if (! empty($v['id'])) {
                $product->variants()->where('id', $v['id'])->update($attrs);
                $keepIds[] = $v['id'];
            } else {
                $new = $product->variants()->create($attrs);
                $keepIds[] = $new->id;
            }
        }

        $product->variants()->whereNotIn('id', $keepIds)->delete();
    }
}
