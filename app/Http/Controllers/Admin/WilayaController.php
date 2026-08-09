<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wilaya;
use App\Services\Delivery\NoestDriver;
use Illuminate\Http\Request;

class WilayaController extends Controller
{
    /**
     * Delivery prices per wilaya. With `?noest=1` the form is PRE-FILLED with
     * Noest's own grid (GET /api/public/fees) instead of the saved values —
     * nothing is written until the admin reviews and saves.
     */
    public function index(Request $request)
    {
        $wilayas = Wilaya::orderBy('code')->get();
        $noest = null;
        $served = null;
        $margin = max(0, (int) $request->query('marge', 0));

        if ($request->boolean('noest')) {
            $driver = new NoestDriver();

            if (! $driver->isEnabled()) {
                session()->now('error', "Noest n'est pas configuré (Paramètres → Livraison Noest).");
            } else {
                $served = $driver->wilayas();          // [code => nom] or null
                $fees = $driver->fees();

                if ($fees === null) {
                    session()->now('error', 'Impossible de récupérer les tarifs Noest (API injoignable).');
                } elseif ($fees === []) {
                    session()->now('error', 'Noest a répondu sans aucun tarif de livraison.');
                } else {
                    $noest = $fees;
                    $missing = $wilayas->reject(fn (Wilaya $w) => isset($noest[$w->code]))->count();

                    $unserved = $served
                        ? $wilayas->reject(fn (Wilaya $w) => isset($served[$w->code]))->count()
                        : 0;

                    session()->now('success', count($noest) . ' tarif(s) Noest chargé(s)'
                        . ($margin ? " avec un supplément de {$margin} DA" : '')
                        . ($missing ? ", {$missing} wilaya(s) sans tarif Noest (valeurs actuelles conservées)" : '')
                        . ($unserved ? ", {$unserved} wilaya(s) non desservie(s) par Noest seront décochées" : '')
                        . ' — vérifiez puis « Enregistrer tout ».');
                }
            }
        }

        return view('admin.wilayas.index', compact('wilayas', 'noest', 'served', 'margin'));
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

        return redirect()->route('admin.wilayas.index')->with('success', 'Tarifs de livraison mis à jour.');
    }
}
