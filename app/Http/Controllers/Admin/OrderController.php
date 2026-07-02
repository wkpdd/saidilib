<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Delivery\DeliveryManager;
use Illuminate\Http\Request;

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
        $order->load('items', 'wilaya');
        $providers = $this->delivery->all();

        return view('admin.orders.show', compact('order', 'providers'));
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
}
