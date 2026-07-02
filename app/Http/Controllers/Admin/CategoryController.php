<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', [
            'category'   => new Category(['is_active' => true, 'color' => '#2563eb']),
            'categories' => Category::whereNull('parent_id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['slug'] = Str::slug($data['name_fr']) . '-' . Str::random(4);
        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Catégorie créée.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', [
            'category'   => $category,
            'categories' => Category::whereNull('parent_id')->where('id', '!=', $category->id)->get(),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validateData($request));

        return redirect()->route('admin.categories.index')->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back()->with('success', 'Catégorie supprimée.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name_fr'        => 'required|string|max:120',
            'name_ar'        => 'nullable|string|max:120',
            'parent_id'      => 'nullable|exists:categories,id',
            'icon'           => 'nullable|string|max:20',
            'color'          => 'nullable|string|max:20',
            'description_fr' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'sort_order'     => 'nullable|integer',
            'is_active'      => 'nullable|boolean',
            'is_featured'    => 'nullable|boolean',
        ]);
    }
}
