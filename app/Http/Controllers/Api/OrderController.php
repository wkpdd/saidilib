<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Services\Delivery\DeliveryManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private DeliveryManager $delivery) {}

    public function index(Request $request)
    {
        $query = Order::with('wilaya')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate(20);

        return response()->json([
            'orders'        => collect($page->items())->map(fn ($o) => self::brief($o)),
            'has_more'      => $page->hasMorePages(),
            'page'          => $page->currentPage(),
            'status_counts' => Order::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(Order $order)
    {
        return response()->json(['order' => self::full($order)]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate(['status' => 'required|in:' . implode(',', Order::STATUSES)]);
        $order->update(['status' => $request->status]);

        return response()->json(['ok' => true, 'order' => self::full($order->fresh())]);
    }

    /** Same logic/logging as the web admin: edit line prices, recompute totals. */
    public function editPrices(Request $request, Order $order)
    {
        if (! $order->is_editable) {
            return response()->json(['message' => 'Cette commande ne peut plus être modifiée (déjà expédiée).'], 403);
        }

        $data = $request->validate([
            'items'              => 'required|array',
            'items.*.unit_price' => 'nullable|numeric|min:0|max:99999999',
            'reason'             => 'nullable|string|max:190',
        ]);

        $order->load('items');
        $changed = 0;

        DB::transaction(function () use ($order, $data, $request, &$changed) {
            foreach ($order->items as $item) {
                $raw = $data['items'][$item->id]['unit_price'] ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }
                $new = (float) $raw;
                $old = (float) $item->unit_price;
                if (abs($new - $old) < 0.001) {
                    continue;
                }

                $oldTotal = (float) $item->line_total;
                $newTotal = $new * $item->quantity;
                $item->update(['unit_price' => $new, 'line_total' => $newTotal]);

                OrderAdjustment::create([
                    'order_id'      => $order->id,
                    'order_item_id' => $item->id,
                    'label'         => $item->name,
                    'old_price'     => $old,
                    'new_price'     => $new,
                    'old_total'     => $oldTotal,
                    'new_total'     => $newTotal,
                    'reason'        => $data['reason'] ?? null,
                    'created_by'    => $request->user()->id,
                ]);
                $changed++;
            }

            if ($changed) {
                $subtotal = (float) $order->items()->sum('line_total');
                $order->update([
                    'subtotal' => $subtotal,
                    'total'    => $subtotal + (float) $order->delivery_fee - (float) $order->discount,
                ]);
            }
        });

        return response()->json(['ok' => true, 'changed' => $changed, 'order' => self::full($order->fresh())]);
    }

    public function dispatchOrder(Request $request, Order $order)
    {
        $data = $request->validate([
            'provider' => 'required|string',
            'tracking' => 'nullable|string|max:120',
        ]);

        $result = $this->delivery->dispatch($order, $data['provider'], $data['tracking'] ?? null);

        return response()->json([
            'ok'      => $result->success,
            'message' => $result->message ?? ($result->success ? 'Commande expédiée.' : "Échec de l'expédition."),
            'order'   => self::full($order->fresh()),
        ], $result->success ? 200 : 422);
    }

    public function refund(Request $request, Order $order)
    {
        $maxRefund = (float) $order->total - (float) ($order->refund_amount ?? 0);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max(0.01, $maxRefund),
            'method' => 'required|in:' . implode(',', array_keys(Order::REFUND_METHODS)),
            'reason' => 'nullable|string|max:255',
        ]);

        $order->update([
            'refund_amount' => (float) ($order->refund_amount ?? 0) + $data['amount'],
            'refund_method' => $data['method'],
            'refund_reason' => $data['reason'] ?? null,
            'refunded_at'   => now(),
            'status'        => 'returned',
        ]);

        if ($data['method'] === 'store_credit' && $order->client_id) {
            $order->client->transactions()->create([
                'type'        => 'payment',
                'amount'      => $data['amount'],
                'description' => "Avoir remboursement {$order->reference}",
                'order_id'    => $order->id,
                'created_by'  => $request->user()->id,
            ]);
        }

        AdminNotification::raise(
            'order',
            "Remboursement {$order->reference}",
            number_format($data['amount'], 2, ',', ' ') . ' DA · ' . Order::REFUND_METHODS[$data['method']],
            route('admin.orders.show', $order),
            '↩️'
        );

        return response()->json(['ok' => true, 'order' => self::full($order->fresh())]);
    }

    // -----------------------------------------------------------------

    /** Compact order payload for lists. */
    public static function brief(Order $order): array
    {
        return [
            'id'            => $order->id,
            'reference'     => $order->reference,
            'customer_name' => $order->customer_name,
            'phone'         => $order->phone,
            'wilaya'        => $order->wilaya?->name_fr,
            'total'         => (float) $order->total,
            'status'        => $order->status,
            'status_label'  => $order->status_label,
            'created_at'    => $order->created_at->toIso8601String(),
        ];
    }

    /** Full order payload for the detail screen. */
    public static function full(Order $order): array
    {
        $order->load('items', 'wilaya', 'client', 'adjustments.author');

        return self::brief($order) + [
            'commune'       => $order->commune,
            'address'       => $order->address,
            'delivery_type' => $order->delivery_type,
            'notes'         => $order->notes,
            'subtotal'      => (float) $order->subtotal,
            'delivery_fee'  => (float) $order->delivery_fee,
            'discount'      => (float) $order->discount,
            'is_editable'   => $order->is_editable,
            'tracking'      => $order->tracking_number,
            'provider'      => $order->delivery_provider,
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'refund'        => $order->refunded_at ? [
                'amount' => (float) $order->refund_amount,
                'method' => $order->refund_method,
                'reason' => $order->refund_reason,
                'at'     => $order->refunded_at->toIso8601String(),
            ] : null,
            'client'        => $order->client ? [
                'id'   => $order->client->id,
                'name' => $order->client->name,
                'type' => $order->client->type,
            ] : null,
            'items'         => $order->items->map(fn ($i) => [
                'id'         => $i->id,
                'name'       => $i->name,
                'variant'    => $i->variant_label,
                'quantity'   => (int) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'line_total' => (float) $i->line_total,
            ]),
            'adjustments'   => $order->adjustments->map(fn ($a) => [
                'label'     => $a->label,
                'old_price' => (float) $a->old_price,
                'new_price' => (float) $a->new_price,
                'reason'    => $a->reason,
                'author'    => $a->author?->name,
                'at'        => $a->created_at->toIso8601String(),
            ]),
        ];
    }
}
