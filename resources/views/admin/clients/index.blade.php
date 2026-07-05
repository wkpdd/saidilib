@extends('admin.layout')
@section('title', 'Clients')
@section('heading', 'Clients')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form method="get" class="flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Nom, téléphone, email…" class="input w-64">
        <select name="type" class="input w-40" onchange="this.form.submit()">
            <option value="">Tous types</option>
            @foreach (\App\Models\Client::TYPES as $val => $lbl)
                <option value="{{ $val }}" @selected(request('type')===$val)>{{ $lbl }}</option>
            @endforeach
        </select>
        <button class="btn-ghost">Filtrer</button>
    </form>
    <div class="flex gap-2">
        <a href="{{ route('admin.clients.pricelist') }}" class="btn-ghost">📄 Liste des prix (PDF)</a>
        <a href="{{ route('admin.clients.create') }}" class="btn-primary">+ Nouveau client</a>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-start">Client</th>
                    <th class="px-4 py-3 text-start">Type</th>
                    <th class="px-4 py-3 text-start">Commandes</th>
                    <th class="px-4 py-3 text-end">Solde dû</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($clients as $c)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.clients.show', $c) }}" class="font-medium text-brand-700 hover:underline">{{ $c->name }}</a>
                            <p class="text-xs text-slate-400">{{ $c->phone ?: $c->email ?: '—' }}</p>
                        </td>
                        <td class="px-4 py-3"><span class="badge {{ $c->type==='wholesale' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">{{ $c->type_label }}</span></td>
                        <td class="px-4 py-3">{{ $c->orders_count }}</td>
                        <td class="px-4 py-3 text-end font-semibold {{ $c->balance > 0 ? 'text-red-600' : 'text-slate-500' }}">
                            @money($c->balance)
                            @if ($c->is_overdue)<span class="ms-1 badge bg-red-50 text-red-700">⚠ dépassé</span>@endif
                        </td>
                        <td class="px-4 py-3 text-end">
                            <a href="{{ route('admin.clients.edit', $c) }}" class="text-brand-700 hover:underline">Modifier</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aucun client.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $clients->links() }}</div>
@endsection
