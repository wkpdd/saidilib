@extends('admin.layout')
@section('title', 'Commande ' . $order->reference)
@section('heading', 'Commande ' . $order->reference)

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        {{-- Stock shortage: the order was accepted anyway, but someone has to
             call the customer before it can be prepared. --}}
        @if ($order->has_stock_issue)
            <div class="card border-s-4 {{ $order->has_open_stock_issue ? 'border-red-500 bg-red-50/60' : 'border-green-500 bg-green-50/50' }} p-5">
                <h2 class="font-semibold {{ $order->has_open_stock_issue ? 'text-red-700' : 'text-green-700' }}">
                    {{ $order->has_open_stock_issue ? '⚠️ Stock insuffisant — à régler avec le client' : '✅ Stock insuffisant — réglé' }}
                </h2>
                <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-ink-700">
                    @foreach (preg_split('/\r\n|\r|\n/', (string) $order->stock_issue) as $row)
                        @if (trim($row) !== '')<li>{{ $row }}</li>@endif
                    @endforeach
                </ul>
                @if ($order->has_open_stock_issue)
                    <p class="mt-2 text-xs text-slate-500">
                        La commande a bien été enregistrée. Contactez le client pour ajuster la quantité,
                        proposer un délai ou annuler la ligne, puis marquez le problème comme réglé.
                    </p>
                    <form action="{{ route('admin.orders.stock.resolve', $order) }}" method="post" class="mt-3">
                        @csrf
                        <button class="btn-ghost text-sm">✅ Marquer comme réglé</button>
                    </form>
                @else
                    <p class="mt-2 text-xs text-slate-500">Réglé le {{ $order->stock_issue_resolved_at->format('d/m/Y H:i') }}.</p>
                @endif
            </div>
        @endif

        {{-- Items (prices editable before the deal is confirmed) --}}
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 p-5">
                <h2 class="font-semibold">Articles</h2>
                @if ($order->is_editable)
                    <span class="text-xs font-medium text-brand-700">✏️ Prix modifiables avant confirmation</span>
                @endif
            </div>
            <form action="{{ route('admin.orders.prices', $order) }}" method="post">
                @csrf
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase text-slate-400">
                        <tr><th class="px-5 py-2 text-start">Article</th><th class="px-3 py-2 text-center">Qté</th><th class="px-3 py-2 text-end">Prix unit.</th><th class="px-5 py-2 text-end">Total</th></tr>
                    </thead>
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
                                <td class="px-3 py-3 text-center text-slate-500">{{ $it->quantity }}</td>
                                <td class="px-3 py-3 text-end">
                                    @if ($order->is_editable)
                                        <input name="items[{{ $it->id }}][unit_price]" type="number" step="any" min="0"
                                               value="{{ (float) $it->unit_price }}" class="input w-24 py-1 text-end text-sm">
                                    @else
                                        @money($it->unit_price)
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-end font-semibold">@money($it->line_total)</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-slate-100 text-sm">
                        <tr><td colspan="3" class="px-5 py-2 text-end text-slate-500">Sous-total</td><td class="px-5 py-2 text-end">@money($order->subtotal)</td></tr>
                        <tr><td colspan="3" class="px-5 py-2 text-end text-slate-500">Livraison</td><td class="px-5 py-2 text-end">@money($order->delivery_fee)</td></tr>
                        <tr class="text-base font-bold"><td colspan="3" class="px-5 py-3 text-end">Total</td><td class="px-5 py-3 text-end text-brand-700">@money($order->total)</td></tr>
                        @if ($order->is_refunded)
                            <tr class="text-sm text-green-700"><td colspan="3" class="px-5 py-2 text-end">Remboursé ({{ \App\Models\Order::REFUND_METHODS[$order->refund_method] ?? $order->refund_method }})</td><td class="px-5 py-2 text-end">−@money($order->refund_amount)</td></tr>
                        @endif
                    </tfoot>
                </table>
                @if ($order->is_editable)
                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 p-4">
                        <input name="reason" maxlength="190" placeholder="Motif (optionnel) — ex : remise négociée" class="input flex-1 text-sm">
                        <button class="btn-primary">💾 Enregistrer les prix</button>
                    </div>
                @endif
            </form>
        </div>

        {{-- Price change audit log --}}
        @if ($order->adjustments->isNotEmpty())
            <div class="card p-5">
                <h2 class="mb-3 font-semibold">📝 Historique des modifications de prix</h2>
                <div class="space-y-2 text-sm">
                    @foreach ($order->adjustments as $adj)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                            <span>{{ $adj->label }} : <span class="text-slate-400 line-through">@money($adj->old_price)</span> → <b class="text-ink-900">@money($adj->new_price)</b></span>
                            <span class="text-xs text-slate-400">{{ optional($adj->author)->name ?: '—' }} · {{ $adj->created_at->format('d/m/Y H:i') }}@if($adj->reason) · « {{ $adj->reason }} »@endif</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

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
        {{-- Printing --}}
        <div class="grid gap-2">
            <a href="{{ route('admin.orders.print.one', $order) }}" target="_blank"
               class="btn-primary w-full justify-center">🖨️ Fiche de préparation (A4)</a>
            <a href="{{ route('admin.orders.slip', $order) }}" target="_blank"
               class="btn-ghost w-full justify-center">🧾 Bordereau de livraison</a>
        </div>

        {{-- Ready → carrier --}}
        <div class="card p-5">
            <h2 class="mb-1 font-semibold">Préparation</h2>
            @if ($order->is_ready)
                <div class="mb-3 rounded-xl bg-teal-50 p-3 text-sm text-teal-800">
                    ✅ Prête depuis {{ $order->ready_at?->format('d/m/Y H:i') ?: 'aujourd\'hui' }}
                    @if ($order->is_carrier_validated)<br><span class="badge bg-green-100 text-green-700">✓ validée chez Noest</span>@endif
                </div>
            @else
                <p class="mb-3 text-xs text-slate-400">
                    Quand l'équipe a fini de préparer le colis : un clic le crée <b>et</b> le valide chez Noest
                    (leur logistique peut alors venir le récupérer).
                </p>
            @endif
            <form action="{{ route('admin.orders.ready', $order) }}" method="post"
                  onsubmit="return confirm('Marquer cette commande comme prête et l\'envoyer à Noest ?')">
                @csrf
                <button class="btn w-full bg-teal-600 text-white hover:bg-teal-700">
                    ✅ {{ $order->is_ready ? 'Renvoyer à Noest' : 'Commande prête → Noest' }}
                </button>
            </form>

            {{-- Dry run: no parcel is created, it only reports what Noest would say. --}}
            <form action="{{ route('admin.orders.noest.check', $order) }}" method="post" class="mt-2">
                @csrf
                <button class="btn-ghost w-full justify-center text-sm">🔍 Vérifier les données Noest</button>
            </form>

            @if (session('noest_payload'))
                <div class="mt-3 overflow-x-auto rounded-xl bg-slate-900 p-3 text-xs text-slate-100">
                    <div class="mb-1 font-semibold text-slate-300">Données envoyées à Noest :</div>
                    @foreach (session('noest_payload') as $k => $v)
                        <div><span class="text-slate-400">{{ $k }}</span> : {{ is_scalar($v) ? $v : json_encode($v) }}</div>
                    @endforeach
                </div>
            @endif
        </div>

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
                        @if (! $order->is_carrier_validated)
                            <form action="{{ route('admin.orders.validate', $order) }}" method="post"
                                  onsubmit="return confirm('Valider chez Noest ? La commande ne pourra plus être modifiée.')">
                                @csrf
                                <button class="btn bg-green-600 text-white hover:bg-green-700">✓ Valider chez Noest</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.orders.noest.label', $order) }}" target="_blank"
                           class="btn-ghost">🏷️ Étiquette Noest (PDF)</a>
                        <form action="{{ route('admin.orders.tracking.one', $order) }}" method="post">
                            @csrf
                            <button class="btn-ghost">🔄 Vérifier le suivi</button>
                        </form>
                    </div>

                    {{-- Live Noest timeline (refreshed on demand) --}}
                    <div class="rounded-xl bg-slate-50 p-3 text-sm">
                        <div class="flex items-center justify-between">
                            <b>Suivi Noest</b>
                            <span class="text-xs text-slate-400">
                                {{ $order->noest_checked_at ? 'vérifié ' . $order->noest_checked_at->diffForHumans() : 'jamais vérifié' }}
                            </span>
                        </div>
                        @if ($order->noest_status)
                            <p class="mt-1 font-medium text-cyan-800">{{ $order->noest_status }}</p>
                            @if ($order->noest_driver)<p class="text-xs text-slate-500">Livreur : {{ $order->noest_driver }}</p>@endif
                            @if (! empty($order->noest_activity))
                                <ol class="mt-2 space-y-1 border-s border-slate-200 ps-3 text-xs text-slate-500">
                                    @foreach (array_reverse($order->noest_activity) as $ev)
                                        <li>
                                            <span class="font-medium text-ink-700">{{ $ev['event'] ?? '—' }}</span>
                                            <span class="text-slate-400">· {{ $ev['date'] ?? '' }}</span>
                                            @if (! empty($ev['causer'])) <span class="text-slate-400">({{ $ev['causer'] }})</span>@endif
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        @else
                            <p class="mt-1 text-xs text-slate-400">Cliquez sur « Vérifier le suivi » pour interroger Noest.</p>
                        @endif
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
