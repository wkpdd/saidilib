@extends('admin.layout')
@section('title', 'Pertes & casses')
@section('heading', 'Pertes, casses & articles perdus')

@section('content')
<div class="mb-5 grid gap-4 sm:grid-cols-3">
    @php $cards = [['Incidents', $stats['count'], '🧯'], ['Unités touchées', $stats['units'], '📦'], ['Perte estimée', number_format($stats['cost'],2,',',' ').' DA', '💸']]; @endphp
    @foreach ($cards as [$label, $value, $icon])
        <div class="card p-5">
            <div class="flex items-center justify-between"><span class="text-sm text-slate-500">{{ $label }}</span><span class="text-2xl">{{ $icon }}</span></div>
            <p class="mt-2 text-2xl font-bold">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="mb-4 flex items-center justify-between">
    <p class="text-sm text-slate-500">Journal des articles perdus, cassés, périmés ou volés.</p>
    <a href="{{ route('admin.incidents.create') }}" class="btn-primary">+ Déclarer un incident</a>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-start">Date</th>
                    <th class="px-4 py-3 text-start">Article</th>
                    <th class="px-4 py-3 text-start">Type</th>
                    <th class="px-4 py-3 text-end">Qté</th>
                    <th class="px-4 py-3 text-end">Coût</th>
                    <th class="px-4 py-3 text-start">Motif</th>
                    <th class="px-4 py-3 text-start">Par</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($incidents as $inc)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-500">{{ $inc->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $inc->product_name }}</td>
                        <td class="px-4 py-3"><span class="badge bg-amber-50 text-amber-700">{{ $inc->type_label }}</span></td>
                        <td class="px-4 py-3 text-end">{{ $inc->quantity }}</td>
                        <td class="px-4 py-3 text-end">@money($inc->cost_estimate)</td>
                        <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Str::limit($inc->reason, 40) ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ optional($inc->reporter)->name ?: '—' }}</td>
                        <td class="px-4 py-3 text-end">
                            <form action="{{ route('admin.incidents.destroy', $inc) }}" method="post" onsubmit="return confirm('Supprimer cet incident ?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Suppr.</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucun incident enregistré. 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $incidents->links() }}</div>
@endsection
