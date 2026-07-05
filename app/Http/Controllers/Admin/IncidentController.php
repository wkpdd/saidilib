<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\InventoryIncident;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncidentController extends Controller
{
    public function index()
    {
        $incidents = InventoryIncident::with('product', 'reporter')->latest()->paginate(25);

        $stats = [
            'count' => InventoryIncident::count(),
            'units' => (int) InventoryIncident::sum('quantity'),
            'cost'  => (float) InventoryIncident::sum('cost_estimate'),
        ];

        return view('admin.incidents.index', compact('incidents', 'stats'));
    }

    public function create()
    {
        return view('admin.incidents.form', [
            'products' => Product::orderBy('name_fr')->get(['id', 'name_fr', 'stock', 'track_stock']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'    => 'nullable|exists:products,id',
            'product_name'  => 'required_without:product_id|nullable|string|max:190',
            'type'          => 'required|in:' . implode(',', array_keys(InventoryIncident::TYPES)),
            'quantity'      => 'required|integer|min:1|max:100000',
            'cost_estimate' => 'nullable|numeric|min:0|max:99999999',
            'reason'        => 'nullable|string|max:1000',
            'adjust_stock'  => 'nullable|boolean',
        ]);

        $product = $data['product_id'] ? Product::find($data['product_id']) : null;
        $adjust = $request->boolean('adjust_stock') && $product && $product->track_stock;

        $incident = InventoryIncident::create([
            'product_id'     => $product?->id,
            'product_name'   => $product?->name_fr ?? $data['product_name'],
            'type'           => $data['type'],
            'quantity'       => $data['quantity'],
            'cost_estimate'  => $data['cost_estimate'] ?? 0,
            'reason'         => $data['reason'] ?? null,
            'stock_adjusted' => $adjust,
            'reported_by'    => Auth::id(),
        ]);

        if ($adjust) {
            $product->decrement('stock', min($product->stock, $data['quantity']));
        }

        AdminNotification::raise(
            'incident',
            "Incident stock — {$incident->product_name}",
            "{$incident->type_label} · {$incident->quantity} unité(s)",
            route('admin.incidents.index'),
            '🧯'
        );

        return redirect()->route('admin.incidents.index')->with('success', 'Incident enregistré.');
    }

    public function destroy(InventoryIncident $incident)
    {
        $incident->delete();

        return back()->with('success', 'Incident supprimé.');
    }
}
