@extends('admin.layout')
@section('title', $client->name)
@section('heading', 'Client · ' . $client->name)

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('admin.clients.index') }}" class="text-sm text-slate-500 hover:underline">← Tous les clients</a>
    <a href="{{ route('admin.clients.edit', $client) }}" class="btn-ghost">Modifier la fiche</a>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    {{-- Left: profile + balance --}}
    <div class="space-y-6">
        <div class="card p-6">
            <div class="flex items-center gap-3">
                <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-100 text-lg font-bold text-brand-700">{{ strtoupper(substr($client->name,0,1)) }}</span>
                <div>
                    <p class="font-semibold">{{ $client->name }}</p>
                    <span class="badge {{ $client->type==='wholesale' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">{{ $client->type_label }}</span>
                </div>
            </div>
            <dl class="mt-4 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-400">Téléphone</dt><dd>{{ $client->phone ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Email</dt><dd>{{ $client->email ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Wilaya</dt><dd>{{ optional($client->wilaya)->name ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Limite crédit</dt><dd>@money($client->credit_limit)</dd></div>
            </dl>
        </div>

        <div class="card p-6 text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Solde dû</p>
            <p class="mt-1 text-3xl font-extrabold {{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}">@money($balance)</p>
            @if ($client->is_overdue)
                <p class="mt-1 text-xs font-semibold text-red-500">⚠ Limite de crédit dépassée</p>
            @endif
        </div>

        {{-- Record a transaction --}}
        <div class="card p-6">
            <h3 class="mb-3 font-semibold">Nouvelle écriture</h3>
            <form action="{{ route('admin.clients.transactions.store', $client) }}" method="post" class="space-y-3">
                @csrf
                <select name="type" class="input">
                    <option value="payment">💵 Paiement reçu (réduit la dette)</option>
                    <option value="debt">🧾 Dette / crédit accordé</option>
                    <option value="adjustment">⚙️ Ajustement</option>
                </select>
                <input name="amount" type="number" step="0.01" min="0.01" required placeholder="Montant (DA)" class="input">
                <input name="description" maxlength="190" placeholder="Description (optionnel)" class="input">
                <button class="btn-primary w-full">Enregistrer l'écriture</button>
            </form>
        </div>
    </div>

    {{-- Right: ledger + orders --}}
    <div class="space-y-6 lg:col-span-2">
        <div class="card overflow-hidden">
            <h3 class="border-b border-slate-100 px-5 py-3 font-semibold">Historique du compte (dette / paiements)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 text-start">Date</th>
                            <th class="px-4 py-2.5 text-start">Type</th>
                            <th class="px-4 py-2.5 text-start">Description</th>
                            <th class="px-4 py-2.5 text-start">Par</th>
                            <th class="px-4 py-2.5 text-end">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($client->transactions as $tx)
                            <tr>
                                <td class="px-4 py-2.5 text-slate-500">{{ $tx->created_at->format('d/m/Y') }}</td>
                                <td class="px-4 py-2.5">{{ \App\Models\ClientTransaction::TYPES[$tx->type] }}</td>
                                <td class="px-4 py-2.5">{{ $tx->description ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-slate-400">{{ optional($tx->author)->name ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-end font-semibold {{ $tx->type==='payment' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $tx->type==='payment' ? '−' : '+' }}@money($tx->amount)
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Aucune écriture.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card overflow-hidden">
            <h3 class="border-b border-slate-100 px-5 py-3 font-semibold">Commandes</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($client->orders as $o)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2.5"><a href="{{ route('admin.orders.show', $o) }}" class="font-medium text-brand-700 hover:underline">{{ $o->reference }}</a></td>
                                <td class="px-4 py-2.5 text-slate-500">{{ $o->created_at->format('d/m/Y') }}</td>
                                <td class="px-4 py-2.5"><span class="badge bg-{{ $o->status_color }}-50 text-{{ $o->status_color }}-700">{{ $o->status_label }}</span></td>
                                <td class="px-4 py-2.5 text-end font-semibold">@money($o->total)</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-8 text-center text-slate-400">Aucune commande.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
