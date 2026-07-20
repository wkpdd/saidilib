<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Wilaya;

/** Small reference lists for the app's pickers. */
class MetaController extends Controller
{
    public function categories()
    {
        return response()->json(['categories' => Category::orderBy('name_fr')
            ->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name_fr])]);
    }

    public function wilayas()
    {
        return response()->json(['wilayas' => Wilaya::active()->orderBy('code')
            ->get()->map(fn ($w) => [
                'id'           => $w->id,
                'label'        => $w->label,
                'home_fee'     => (float) $w->home_fee,
                'stopdesk_fee' => (float) $w->stopdesk_fee,
            ])]);
    }

    public function suppliers()
    {
        return response()->json(['suppliers' => Supplier::orderBy('name')
            ->get()->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])]);
    }
}
