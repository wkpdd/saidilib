<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wilaya;
use Illuminate\Http\Request;

class WilayaController extends Controller
{
    public function index()
    {
        $wilayas = Wilaya::orderBy('code')->get();

        return view('admin.wilayas.index', compact('wilayas'));
    }

    public function update(Request $request)
    {
        foreach ($request->input('wilayas', []) as $id => $row) {
            Wilaya::where('id', $id)->update([
                'home_fee'     => (float) ($row['home_fee'] ?? 0),
                'stopdesk_fee' => (float) ($row['stopdesk_fee'] ?? 0),
                'is_active'    => isset($row['is_active']),
            ]);
        }

        return back()->with('success', 'Tarifs de livraison mis à jour.');
    }
}
