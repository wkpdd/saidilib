<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    private function client()
    {
        return Auth::guard('client')->user();
    }

    public function index()
    {
        $client = $this->client();
        $orders = $client->orders()->with('wilaya')->take(20)->get();

        return view('storefront.account.index', [
            'client'  => $client,
            'orders'  => $orders,
            'balance' => $client->balance,
            'ledger'  => $client->transactions()->with('order')->take(20)->get(),
        ]);
    }

    public function order(Order $order)
    {
        // Ownership check — a client may only view their own orders.
        abort_unless($order->client_id === $this->client()->id, 403);

        $order->load('items', 'wilaya');

        return view('storefront.account.order', compact('order'));
    }

    /** B2B (wholesale) clients can download the catalogue price list as PDF. */
    public function priceList()
    {
        $client = $this->client();
        abort_unless($client->type === 'wholesale', 403, 'Réservé aux comptes grossistes.');

        return \App\Support\PriceList::pdf($client->name)
            ->download(\App\Support\PriceList::filename());
    }
}
