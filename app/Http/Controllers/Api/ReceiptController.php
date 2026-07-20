<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Scanner-based stock receiving from the staff app. */
class ReceiptController extends Controller
{
    public function index()
    {
        $page = StockReceipt::with('supplier')->latest()->paginate(20);

        return response()->json([
            'receipts' => collect($page->items())->map(fn ($r) => [
                'id'           => $r->id,
                'reference'    => $r->reference,
                'supplier'     => $r->supplier?->name,
                'status'       => $r->status,
                'status_label' => $r->status_label,
                'total_cost'   => (float) $r->total_cost,
                'items_count'  => $r->items()->count(),
                'at'           => $r->created_at->toIso8601String(),
            ]),
            'has_more' => $page->hasMorePages(),
            'page'     => $page->currentPage(),
        ]);
    }

    /**
     * Create a bon de réception from scanned lines and (by default) receive it
     * immediately — every quantity lands in stock in one step.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id'        => 'nullable|exists:suppliers,id',
            'note'               => 'nullable|string|max:1000',
            'receive_now'        => 'nullable|boolean',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|integer',
            'items.*.qty'        => 'required|integer|min:1|max:99999',
            'items.*.unit_cost'  => 'nullable|numeric|min:0|max:99999999',
        ]);

        $receipt = DB::transaction(function () use ($data, $request) {
            $receipt = StockReceipt::create([
                'reference'     => StockReceipt::generateReference(),
                'supplier_id'   => $data['supplier_id'] ?? null,
                'status'        => 'draft',
                'document_date' => now(),
                'note'          => $data['note'] ?? null,
                'created_by'    => $request->user()->id,
            ]);

            foreach ($data['items'] as $line) {
                $product = Product::findOrFail($line['product_id']);
                $variant = ! empty($line['variant_id'])
                    ? $product->variants()->where('id', $line['variant_id'])->first()
                    : null;
                $cost = (float) ($line['unit_cost'] ?? 0);

                $receipt->items()->create([
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant?->id,
                    'product_name'       => $product->display_name . ($variant ? ' — ' . $variant->label_fr : ''),
                    'quantity'           => $line['qty'],
                    'unit_cost'          => $cost,
                    'line_total'         => $cost * $line['qty'],
                ]);
            }
            $receipt->recomputeTotal();

            return $receipt;
        });

        if ($data['receive_now'] ?? true) {
            $receipt->receiveInto();
        }

        return response()->json([
            'ok'      => true,
            'receipt' => [
                'id'        => $receipt->id,
                'reference' => $receipt->reference,
                'status'    => $receipt->fresh()->status,
            ],
        ], 201);
    }
}
