<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Client;
use App\Models\ClientTransaction;
use App\Models\Wilaya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $clients = $query->paginate(20)->withQueryString();

        return view('admin.clients.index', compact('clients'));
    }

    public function create()
    {
        return view('admin.clients.form', [
            'client'  => new Client(['type' => 'retail', 'is_active' => true]),
            'wilayas' => Wilaya::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $client = Client::create($data);

        return redirect()->route('admin.clients.show', $client)->with('success', 'Client créé.');
    }

    public function show(Client $client)
    {
        $client->load(['transactions.author', 'transactions.order', 'orders.wilaya']);

        return view('admin.clients.show', [
            'client'  => $client,
            'balance' => $client->balance,
        ]);
    }

    public function edit(Client $client)
    {
        return view('admin.clients.form', [
            'client'  => $client,
            'wilayas' => Wilaya::orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $data = $this->validateData($request, $client);

        // Don't overwrite the password with an empty value.
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $client->update($data);

        return redirect()->route('admin.clients.show', $client)->with('success', 'Client mis à jour.');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('admin.clients.index')->with('success', 'Client supprimé.');
    }

    /** Staff download of the B2B price-list PDF (to send to clients). */
    public function priceList()
    {
        return \App\Support\PriceList::pdf()->download(\App\Support\PriceList::filename());
    }

    /** Record a debt, payment, or adjustment against the client's ledger. */
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
            'created_by'  => Auth::id(),
        ]);

        // Raise an alert if the client is now over their credit limit.
        if ($client->fresh()->is_overdue) {
            AdminNotification::raise(
                'debt',
                "Solde dépassé — {$client->name}",
                'Dette ' . number_format($client->balance, 2, ',', ' ') . ' DA > limite ' . number_format((float) $client->credit_limit, 2, ',', ' ') . ' DA',
                route('admin.clients.show', $client),
                '💳'
            );
        }

        return back()->with('success', 'Écriture enregistrée.');
    }

    private function validateData(Request $request, ?Client $client = null): array
    {
        $emailRule = 'nullable|email|max:190|unique:clients,email' . ($client ? ',' . $client->id : '');

        return $request->validate([
            'name'         => 'required|string|max:120',
            'email'        => $emailRule,
            'phone'        => 'nullable|string|max:30',
            'type'         => 'required|in:retail,wholesale',
            'wilaya_id'    => 'nullable|exists:wilayas,id',
            'commune'      => 'nullable|string|max:120',
            'address'      => 'nullable|string|max:500',
            'credit_limit' => 'required|numeric|min:0|max:99999999',
            'notes'        => 'nullable|string|max:1000',
            'password'     => 'nullable|string|min:6|max:190',
            'is_active'    => 'nullable|boolean',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
