<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StockReceiptController extends Controller
{
    public function index(Request $request)
    {
        $query = StockReceipt::with('supplier')->latest();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $stats = [
            'draft'    => StockReceipt::where('status', 'draft')->count(),
            'received' => StockReceipt::where('status', 'received')->count(),
            'value'    => (float) StockReceipt::where('status', 'received')->sum('total_cost'),
        ];

        return view('admin.receipts.index', [
            'receipts' => $query->paginate(20)->withQueryString(),
            'stats'    => $stats,
        ]);
    }

    public function create()
    {
        return view('admin.receipts.form', [
            'receipt'   => new StockReceipt(['status' => 'draft']),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'products'  => Product::orderBy('name_fr')->get(['id', 'name_fr']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateReceipt($request);

        $receipt = StockReceipt::create([
            'reference'        => StockReceipt::generateReference(),
            'supplier_id'      => $data['supplier_id'] ?? null,
            'supplier_invoice' => $data['supplier_invoice'] ?? null,
            'document_date'    => $data['document_date'] ?? null,
            'note'             => $data['note'] ?? null,
            'status'           => 'draft',
            'created_by'       => Auth::id(),
            'document_path'    => $request->hasFile('document')
                ? $request->file('document')->store('receipts', 'public')
                : null,
        ]);

        $this->syncItems($request, $receipt);

        return redirect()->route('admin.receipts.show', $receipt)->with('success', 'Bon de réception créé.');
    }

    public function show(StockReceipt $receipt)
    {
        $receipt->load('items.product', 'items.variant', 'supplier', 'creator');

        return view('admin.receipts.show', compact('receipt'));
    }

    public function edit(StockReceipt $receipt)
    {
        abort_if($receipt->is_received, 403, 'Un bon reçu ne peut plus être modifié.');
        $receipt->load('items');

        return view('admin.receipts.form', [
            'receipt'   => $receipt,
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'products'  => Product::orderBy('name_fr')->get(['id', 'name_fr']),
        ]);
    }

    public function update(Request $request, StockReceipt $receipt)
    {
        abort_if($receipt->is_received, 403, 'Un bon reçu ne peut plus être modifié.');
        $data = $this->validateReceipt($request);

        $receipt->update([
            'supplier_id'      => $data['supplier_id'] ?? null,
            'supplier_invoice' => $data['supplier_invoice'] ?? null,
            'document_date'    => $data['document_date'] ?? null,
            'note'             => $data['note'] ?? null,
            'document_path'    => $request->hasFile('document')
                ? $request->file('document')->store('receipts', 'public')
                : $receipt->document_path,
        ]);

        $this->syncItems($request, $receipt);

        return redirect()->route('admin.receipts.show', $receipt)->with('success', 'Bon de réception mis à jour.');
    }

    /** Confirm reception → push quantities into stock (idempotent). */
    public function receive(StockReceipt $receipt)
    {
        if (! $receipt->items()->exists()) {
            return back()->with('error', 'Ajoutez au moins une ligne avant de réceptionner.');
        }

        if ($receipt->receiveInto()) {
            AdminNotification::raise(
                'incident',
                "Stock réceptionné — {$receipt->reference}",
                (int) $receipt->items()->sum('quantity') . ' article(s) ajoutés au stock',
                route('admin.receipts.show', $receipt),
                '📥'
            );

            return back()->with('success', 'Réception confirmée. Le stock a été mis à jour.');
        }

        return back()->with('error', 'Ce bon a déjà été réceptionné.');
    }

    public function destroy(StockReceipt $receipt)
    {
        abort_if($receipt->is_received, 403, 'Un bon reçu ne peut pas être supprimé.');
        if ($receipt->document_path) {
            Storage::disk('public')->delete($receipt->document_path);
        }
        $receipt->delete();

        return redirect()->route('admin.receipts.index')->with('success', 'Bon supprimé.');
    }

    public function document(StockReceipt $receipt)
    {
        abort_unless($receipt->document_path && Storage::disk('public')->exists($receipt->document_path), 404);

        return Storage::disk('public')->download($receipt->document_path);
    }

    private function validateReceipt(Request $request): array
    {
        return $request->validate([
            'supplier_id'          => 'nullable|exists:suppliers,id',
            'supplier_invoice'     => 'nullable|string|max:100',
            'document_date'        => 'nullable|date',
            'note'                 => 'nullable|string|max:1000',
            'document'             => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:8192',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'nullable|exists:products,id',
            'items.*.product_name' => 'nullable|string|max:190',
            'items.*.lot_number'   => 'nullable|string|max:80',
            'items.*.expiry_date'  => 'nullable|date',
            'items.*.quantity'     => 'nullable|integer|min:0|max:1000000',
            'items.*.unit_cost'    => 'nullable|numeric|min:0|max:99999999',
        ]);
    }

    private function syncItems(Request $request, StockReceipt $receipt): void
    {
        $receipt->items()->delete();

        foreach ((array) $request->input('items', []) as $row) {
            $qty = (int) ($row['quantity'] ?? 0);
            $productId = $row['product_id'] ?? null;
            $name = trim($row['product_name'] ?? '');

            if (! $productId && $name === '') {
                continue; // skip empty lines
            }

            if ($productId && $name === '') {
                $name = Product::find($productId)?->name_fr ?? 'Article';
            }

            $unit = (float) ($row['unit_cost'] ?? 0);
            $receipt->items()->create([
                'product_id'   => $productId ?: null,
                'product_name' => $name,
                'lot_number'   => $row['lot_number'] ?? null,
                'expiry_date'  => $row['expiry_date'] ?? null,
                'quantity'     => $qty,
                'unit_cost'    => $unit,
                'line_total'   => $unit * $qty,
            ]);
        }

        $receipt->recomputeTotal();
    }
}
