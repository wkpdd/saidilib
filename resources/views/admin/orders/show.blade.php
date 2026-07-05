@extends('admin.layout')
@section('title', 'Commande ' . $order->reference)
@section('heading', 'Commande ' . $order->reference)

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        {{-- Items --}}
        <div class="card overflow-hidden">
            <h2 class="border-b border-slate-100 p-5 font-semibold">Articles</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($order->items as $it)
                        <tr>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($it->image)<img src="{{ $it->image }}" class="h-12 w-12 rounded-lg object-cover ring-1 ring-slate-100">@endif
                                    <div>
                                        <p class="font-medium">{{ $it->name }}</p>
                                        @if ($it->variant_label)<p class="text-xs text-slate-400">{{ $it->variant_label }}</p>@endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-center text-slate-500">{{ $it->quantity }} × @money($it->unit_price)</td>
                            <td class="px-5 py-3 text-end font-semibold">@money($it->line_total)</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-slate-100 text-sm">
                    <tr><td colspan="2" class="px-5 py-2 text-end text-slate-500">Sous-total</td><td class="px-5 py-2 text-end">@money($order->subtotal)</td></tr>
                    <tr><td colspan="2" class="px-5 py-2 text-end text-slate-500">Livraison</td><td class="px-5 py-2 text-end">@money($order->delivery_fee)</td></tr>
                    <tr class="text-base font-bold"><td colspan="2" class="px-5 py-3 text-end">Total</td><td class="px-5 py-3 text-end text-brand-700">@money($order->total)</td></tr>
                    @if ($order->is_refunded)
                        <tr class="text-sm text-green-700"><td colspan="2" class="px-5 py-2 text-end">Remboursé ({{ \App\Models\Order::REFUND_METHODS[$order->refund_method] ?? $order->refund_method }})</td><td class="px-5 py-2 text-end">−@money($order->refund_amount)</td></tr>
                    @endif
                </tfoot>
            </table>
        </div>

        {{-- Customer --}}
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">Client & livraison</h2>
            <div class="grid gap-3 text-sm sm:grid-cols-2">
                <div><span class="text-slate-400">Nom</span><p class="font-medium">{{ $order->customer_name }}
                    @if ($order->client)<a href="{{ route('admin.clients.show', $order->client) }}" class="ms-1 text-xs text-brand-700 hover:underline">(fiche client)</a>@endif
                </p></div>
                <div><span class="text-slate-400">Téléphone</span><p class="font-medium">{{ $order->phone }} @if($order->phone2) / {{ $order->phone2 }}@endif</p></div>
                <div><span class="text-slate-400">Wilaya</span><p class="font-medium">{{ optional($order->wilaya)->name }}</p></div>
                <div><span class="text-slate-400">Commune</span><p class="font-medium">{{ $order->commune ?: '—' }}</p></div>
                <div class="sm:col-span-2"><span class="text-slate-400">Adresse</span><p class="font-medium">{{ $order->address ?: '—' }} ({{ $order->delivery_type === 'home' ? 'À domicile' : 'Stop desk' }})</p></div>
                @if ($order->notes)<div class="sm:col-span-2"><span class="text-slate-400">Remarques</span><p class="font-medium">{{ $order->notes }}</p></div>@endif
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="space-y-6">
        {{-- Delivery slip --}}
        <a href="{{ route('admin.orders.slip', $order) }}" target="_blank"
           class="btn-primary w-full justify-center">🖨️ Bordereau de livraison (Noest)</a>

        {{-- Status --}}
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">Statut</h2>
            <form action="{{ route('admin.orders.status', $order) }}" method="post" class="flex gap-2">
                @csrf @method('PATCH')
                <select name="status" class="input">
                    @foreach (\App\Models\Order::STATUS_LABELS as $st => $lbl)
                        <option value="{{ $st }}" @selected($order->status===$st)>{{ $lbl }}</option>
                    @endforeach
                </select>
                <button class="btn-primary">OK</button>
            </form>
        </div>

        {{-- Dispatch to delivery provider --}}
        <div class="card p-5">
            <h2 class="mb-1 font-semibold">Expédition</h2>
            <p class="mb-3 text-xs text-slate-400">Envoyer la commande à un service de livraison.</p>

            @if ($order->dispatched_at)
                <div class="mb-3 rounded-xl bg-green-50 p-3 text-sm text-green-800">
                    Expédié via <b>{{ ucfirst($order->delivery_provider) }}</b>
                    @if ($order->tracking_number)<br>Suivi : <b>{{ $order->tracking_number }}</b>@endif
                    <br><span class="text-xs">{{ $order->dispatched_at->format('d/m/Y H:i') }}</span>
                    @if (($order->provider_payload['validated'] ?? false))
                        <br><span class="badge bg-green-100 text-green-700">✓ Validée</span>
                    @endif
                </div>

                {{-- Noest carrier actions --}}
                @if ($order->delivery_provider === 'noest' && $order->tracking_number)
                    <div class="mb-3 flex flex-wrap gap-2">
                        @if (! ($order->provider_payload['validated'] ?? false))
                            <form action="{{ route('admin.orders.validate', $order) }}" method="post"
                                  onsubmit="return confirm('Valider chez Noest ? La commande ne pourra plus être modifiée.')">
                                @csrf
                                <button class="btn bg-green-600 text-white hover:bg-green-700">✓ Valider chez Noest</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.orders.noest.label', $order) }}" target="_blank"
                           class="btn-ghost">🏷️ Étiquette Noest (PDF)</a>
                    </div>
                @endif
            @endif

            <form action="{{ route('admin.orders.dispatch', $order) }}" method="post" class="space-y-3">
                @csrf
                <div>
                    <label class="label">Service</label>
                    <select name="provider" class="input" id="provider">
                        @foreach ($providers as $key => $driver)
                            <option value="{{ $key }}" data-enabled="{{ $driver->isEnabled() ? 1 : 0 }}">
                                {{ $driver->label() }}{{ $driver->isEnabled() ? '' : ' (API non configurée — manuel)' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">N° de suivi (si manuel)</label>
                    <input name="tracking" value="{{ $order->tracking_number }}" class="input" placeholder="Ex : yal-XXXX / saisi manuellement">
                    <p class="mt-1 text-xs text-slate-400">Pour Yalidine (mode manuel), collez ici le tracking généré dans votre tableau de bord Yalidine.</p>
                </div>
                <button class="btn-accent w-full">🚚 Expédier</button>
            </form>
        </div>

        {{-- Refund --}}
        <div class="card p-5">
            <h2 class="mb-1 font-semibold">Remboursement</h2>
            @if ($order->is_refunded)
                <div class="mb-3 rounded-xl bg-green-50 p-3 text-sm text-green-800">
                    Remboursé : <b>@money($order->refund_amount)</b><br>
                    {{ \App\Models\Order::REFUND_METHODS[$order->refund_method] ?? $order->refund_method }}
                    · {{ $order->refunded_at->format('d/m/Y') }}
                    @if ($order->refund_reason)<br><span class="text-xs">{{ $order->refund_reason }}</span>@endif
                </div>
            @endif
            <form action="{{ route('admin.orders.refund', $order) }}" method="post" class="space-y-3"
                  onsubmit="return confirm('Enregistrer ce remboursement ?')">
                @csrf
                <input name="amount" type="number" step="0.01" min="0.01" max="{{ $order->total }}" required
                       placeholder="Montant à rembourser (DA)" class="input">
                <select name="method" class="input">
                    @foreach (\App\Models\Order::REFUND_METHODS as $val => $lbl)
                        <option value="{{ $val }}">{{ $lbl }}</option>
                    @endforeach
                </select>
                <input name="reason" maxlength="255" placeholder="Motif (optionnel)" class="input">
                <button class="btn w-full bg-rose-50 text-rose-700 hover:bg-rose-100">↩️ Rembourser</button>
            </form>
        </div>

        <div class="card p-5 text-sm">
            <h2 class="mb-2 font-semibold">Infos</h2>
            <div class="space-y-1 text-slate-500">
                <p>Paiement : <b class="text-ink-700">{{ strtoupper($order->payment_method) }}</b></p>
                <p>Créée : {{ $order->created_at->format('d/m/Y H:i') }}</p>
                @if ($order->utm_source)<p>Source : {{ $order->utm_source }}</p>@endif
            </div>
        </div>
    </div>
</div>
@endsection
