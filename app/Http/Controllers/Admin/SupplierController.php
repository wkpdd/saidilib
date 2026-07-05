<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query()->withCount('receipts')->orderBy('name');
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%");
            });
        }

        return view('admin.suppliers.index', ['suppliers' => $query->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        return view('admin.suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        Supplier::create($this->validated($request));

        return redirect()->route('admin.suppliers.index')->with('success', 'Fournisseur ajouté.');
    }

    public function edit(Supplier $supplier)
    {
        $supplier->load('receipts');

        return view('admin.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));

        return redirect()->route('admin.suppliers.index')->with('success', 'Fournisseur mis à jour.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('success', 'Fournisseur supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'         => 'required|string|max:150',
            'contact_name' => 'nullable|string|max:150',
            'phone'        => 'nullable|string|max:40',
            'email'        => 'nullable|email|max:150',
            'rc'           => 'nullable|string|max:60',
            'nif'          => 'nullable|string|max:60',
            'address'      => 'nullable|string|max:500',
            'notes'        => 'nullable|string|max:1000',
            'is_active'    => 'nullable|boolean',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
