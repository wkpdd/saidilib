@extends('admin.layout')
@section('title', 'Livraison')
@section('heading', 'Tarifs de livraison (58 wilayas)')

@section('content')
<form action="{{ route('admin.wilayas.update') }}" method="post">
    @csrf @method('PATCH')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Définissez les frais « à domicile » et « stop desk » par wilaya.</p>
        <button class="btn-primary">💾 Enregistrer tout</button>
    </div>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">#</th>
                        <th class="px-4 py-3 text-start">Wilaya</th>
                        <th class="px-4 py-3 text-start">À domicile (DA)</th>
                        <th class="px-4 py-3 text-start">Stop desk (DA)</th>
                        <th class="px-4 py-3 text-start">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($wilayas as $w)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2 text-slate-400">{{ str_pad($w->code, 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-4 py-2 font-medium">{{ $w->name_fr }} <span class="text-slate-400" dir="rtl">{{ $w->name_ar }}</span></td>
                            <td class="px-4 py-2"><input name="wilayas[{{ $w->id }}][home_fee]" value="{{ (int) $w->home_fee }}" type="number" class="input w-28 py-1.5"></td>
                            <td class="px-4 py-2"><input name="wilayas[{{ $w->id }}][stopdesk_fee]" value="{{ (int) $w->stopdesk_fee }}" type="number" class="input w-28 py-1.5"></td>
                            <td class="px-4 py-2"><input type="checkbox" name="wilayas[{{ $w->id }}][is_active]" value="1" @checked($w->is_active) class="rounded"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4"><button class="btn-primary">💾 Enregistrer tout</button></div>
</form>
@endsection
