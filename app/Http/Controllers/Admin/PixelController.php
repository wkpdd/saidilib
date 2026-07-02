<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pixel;
use Illuminate\Http\Request;

class PixelController extends Controller
{
    public function index()
    {
        $pixels = Pixel::withCount('products')->latest()->get();

        return view('admin.pixels.index', compact('pixels'));
    }

    public function create()
    {
        return view('admin.pixels.form', ['pixel' => new Pixel(['is_active' => true, 'is_global' => true])]);
    }

    public function store(Request $request)
    {
        Pixel::create($this->validateData($request));

        return redirect()->route('admin.pixels.index')->with('success', 'Pixel ajouté.');
    }

    public function edit(Pixel $pixel)
    {
        return view('admin.pixels.form', compact('pixel'));
    }

    public function update(Request $request, Pixel $pixel)
    {
        $pixel->update($this->validateData($request));

        return redirect()->route('admin.pixels.index')->with('success', 'Pixel mis à jour.');
    }

    public function destroy(Pixel $pixel)
    {
        $pixel->delete();

        return back()->with('success', 'Pixel supprimé.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name'         => 'required|string|max:120',
            'provider'     => 'required|in:facebook,tiktok,google,snapchat',
            'pixel_id'     => 'required|string|max:120',
            'access_token' => 'nullable|string|max:500',
            'is_active'    => 'nullable|boolean',
            'is_global'    => 'nullable|boolean',
        ]);
    }
}
