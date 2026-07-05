<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Services\Delivery\DeliveryManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $orders = $query->paginate(20)->withQueryString();
        $counts = Order::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return view('admin.orders.index', compact('orders', 'counts'));
    }

    public function show(Order $order)
    {
        $order->load('items', 'wilaya', 'client');
        $providers = $this->delivery->all();

        return view('admin.orders.show', compact('order', 'providers'));
    }

    /** Printable Noest delivery slip (bordereau). */
    public function slip(Order $order)
    {
        $order->load('items', 'wilaya');

        return view('admin.orders.slip', compact('order'));
    }

    /** Record a full or partial refund on an order. */
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

        // Store credit → credit the client's ledger (reduces what they owe).
        if ($data['method'] === 'store_credit' && $order->client_id) {
            $order->client->transactions()->create([
                'type'        => 'payment',
                'amount'      => $data['amount'],
                'description' => "Avoir remboursement {$order->reference}",
                'order_id'    => $order->id,
                'created_by'  => Auth::id(),
            ]);
        }

        AdminNotification::raise(
            'order',
            "Remboursement {$order->reference}",
            number_format($data['amount'], 2, ',', ' ') . ' DA · ' . Order::REFUND_METHODS[$data['method']],
            route('admin.orders.show', $order),
            '↩️'
        );

        return back()->with('success', 'Remboursement enregistré.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate(['status' => 'required|in:' . implode(',', Order::STATUSES)]);
        $order->update(['status' => $request->status]);

        return back()->with('success', 'Statut mis à jour.');
    }

    public function dispatch(Request $request, Order $order)
    {
        $data = $request->validate([
            'provider' => 'required|string',
            'tracking' => 'nullable|string|max:120',
        ]);

        $result = $this->delivery->dispatch($order, $data['provider'], $data['tracking'] ?? null);

        return back()->with($result->success ? 'success' : 'error', $result->message
            ?? ($result->success ? 'Commande expédiée.' : 'Échec de l\'expédition.'));
    }

    /** Validate a dispatched order with its carrier (Noest valid/order). */
    public function validateShipment(Order $order)
    {
        $result = $this->delivery->validate($order);

        return back()->with($result->success ? 'success' : 'error', $result->message);
    }

    /** Stream the official carrier label PDF (Noest get/order/label). */
    public function noestLabel(Order $order)
    {
        $pdf = $this->delivery->labelPdf($order);

        abort_if($pdf === null, 404, "Étiquette indisponible (commande non expédiée chez un transporteur, ou API indisponible).");

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="noest-' . $order->reference . '.pdf"',
        ]);
    }
}
