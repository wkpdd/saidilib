<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query()->withCount('orders')->latest();

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($request->query('type')) {
            $query->where('type', $request->query('type'));
        }

        $page = $query->paginate(20);

        return response()->json([
            'clients'  => collect($page->items())->map(fn ($c) => self::brief($c)),
            'has_more' => $page->hasMorePages(),
            'page'     => $page->currentPage(),
        ]);
    }

    public function show(Client $client)
    {
        $client->load(['transactions.author', 'transactions.order']);

        return response()->json(['client' => self::brief($client) + [
            'email'        => $client->email,
            'commune'      => $client->commune,
            'address'      => $client->address,
            'notes'        => $client->notes,
            'credit_limit' => (float) $client->credit_limit,
            'transactions' => $client->transactions->map(fn ($t) => [
                'id'          => $t->id,
                'type'        => $t->type,
                'amount'      => (float) $t->amount,
                'description' => $t->description,
                'order_ref'   => $t->order?->reference,
                'author'      => $t->author?->name,
                'at'          => $t->created_at->toIso8601String(),
            ]),
        ]]);
    }

    /** Same ledger logic as the web admin, incl. over-limit alert. */
    public function addTransaction(Request $request, Client $client)
    {
        $data = $request->validate([
            'type'        => 'required|in:debt,payment,adjustment',
            'amount'      => 'required|numeric|min:0.01|max:99999999',
            'description' => 'nullable|string|max:190',
        ]);

        $client->transactions()->create([
            'type'        => $data['type'],
            'amount'      => $data['amount'],
            'description' => $data['description'] ?? null,
            'created_by'  => $request->user()->id,
        ]);

        if ($client->fresh()->is_overdue) {
            AdminNotification::raise(
                'debt',
                "Solde dépassé — {$client->name}",
                'Dette ' . number_format($client->balance, 2, ',', ' ') . ' DA > limite ' . number_format((float) $client->credit_limit, 2, ',', ' ') . ' DA',
                route('admin.clients.show', $client),
                '💳'
            );
        }

        return response()->json(['ok' => true, 'client' => self::brief($client->fresh())]);
    }

    public static function brief(Client $client): array
    {
        return [
            'id'           => $client->id,
            'name'         => $client->name,
            'phone'        => $client->phone,
            'type'         => $client->type,
            'balance'      => (float) $client->balance,
            'is_overdue'   => (bool) $client->is_overdue,
            'orders_count' => (int) ($client->orders_count ?? $client->orders()->count()),
            'is_active'    => (bool) $client->is_active,
        ];
    }
}
