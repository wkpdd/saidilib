<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockLocation;
use App\Services\StockService;
use Illuminate\Http\Request;

/** Multi-location stock: matrix, transfers, locations manager. */
class StockController extends Controller
{
    public function index(Request $request)
    {
        $locations = StockLocation::orderBy('sort_order')->get();

        $query = Product::with('variants')->orderBy('name_fr');
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name_fr', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }
        $products = $query->paginate(15)->withQueryString();

        // Levels for the page's products, keyed "product:variant|location".
        $ids = collect($products->items())->pluck('id');
        $levels = StockLevel::whereIn('product_id', $ids)->get()
            ->keyBy(fn ($l) => $l->product_id . ':' . ($l->product_variant_id ?? 0) . '|' . $l->stock_location_id);

        $movements = \DB::table('stock_movements as m')
            ->leftJoin('products as p', 'p.id', '=', 'm.product_id')
            ->leftJoin('stock_locations as l', 'l.id', '=', 'm.stock_location_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.created_by')
            ->orderByDesc('m.id')->limit(12)
            ->get(['m.*', 'p.name_fr as product_name', 'l.name as location_name', 'u.name as user_name']);

        return view('admin.stock.index', compact('locations', 'products', 'levels', 'movements'));
    }

    public function transfer(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|integer',
            'from_id'    => 'required|exists:stock_locations,id',
            'to_id'      => 'required|exists:stock_locations,id|different:from_id',
            'qty'        => 'required|integer|min:1|max:100000',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = ! empty($data['variant_id'])
            ? ProductVariant::where('product_id', $product->id)->find($data['variant_id'])
            : null;

        $stock->transfer($product, $variant, (int) $data['from_id'], (int) $data['to_id'], (int) $data['qty'], $request->user()->id);

        return back()->with('success', "Transfert effectué ({$data['qty']} unité(s)).");
    }

    /** Inline edit of one matrix cell. */
    public function adjust(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'variant_id'  => 'nullable|integer',
            'location_id' => 'required|exists:stock_locations,id',
            'quantity'    => 'required|integer|min:0|max:1000000',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = ! empty($data['variant_id'])
            ? ProductVariant::where('product_id', $product->id)->find($data['variant_id'])
            : null;

        $stock->adjust($product, $variant, (int) $data['location_id'], (int) $data['quantity'], $request->user()->id);

        return back()->with('success', 'Quantité ajustée (total recalculé).');
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80']);
        StockLocation::create(['name' => $data['name'], 'sort_order' => StockLocation::count()]);

        return back()->with('success', 'Emplacement créé.');
    }

    public function updateLocation(Request $request, StockLocation $location)
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'is_default' => 'nullable|boolean']);
        if ($request->boolean('is_default')) {
            StockLocation::where('id', '!=', $location->id)->update(['is_default' => false]);
            $location->is_default = true;
        }
        $location->name = $data['name'];
        $location->save();

        return back()->with('success', 'Emplacement mis à jour.');
    }

    public function destroyLocation(StockLocation $location)
    {
        if ($location->is_default) {
            return back()->with('error', "Impossible de supprimer l'emplacement par défaut.");
        }
        if ($location->levels()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', 'Emplacement non vide — transférez son stock avant de le supprimer.');
        }
        $location->delete();

        return back()->with('success', 'Emplacement supprimé.');
    }
}
