@php use App\Models\Setting; use App\Support\Barcode; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiches de préparation ({{ $orders->count() }})</title>
    <style>
        /* A4 picking sheet — one order per page, black on white, no colour ink. */
        @page { size: A4; margin: 10mm 12mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 12pt; background: #f1f5f9; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 8px; padding: 10px; background: #fff; border-bottom: 1px solid #ddd; }
        .btn { padding: 10px 16px; border: 0; border-radius: 6px; font-weight: 700; cursor: pointer; text-decoration: none; font-size: 13px; }
        .btn-print { background: #e07d00; color: #fff; }
        .btn-back { background: #eee; color: #111; }
        .sheet { width: 190mm; min-height: 270mm; margin: 12px auto; padding: 8mm; background: #fff; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #000; padding-bottom: 6px; }
        .title { font-size: 20pt; font-weight: 800; letter-spacing: -.5px; }
        .sub { font-size: 10pt; color: #444; }
        .ref { font-size: 22pt; font-weight: 800; letter-spacing: 1px; text-align: right; }
        .barcode svg { height: 42px; }
        .grid { display: flex; gap: 8px; margin-top: 8px; }
        .box { flex: 1; border: 1px solid #000; padding: 6px 8px; }
        .label { font-size: 8pt; text-transform: uppercase; letter-spacing: .5px; color: #444; }
        .val { font-size: 12pt; font-weight: 700; }
        .flag { display: inline-block; border: 2px solid #000; padding: 2px 8px; font-weight: 800; font-size: 10pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #000; color: #fff; font-size: 9pt; text-transform: uppercase; padding: 5px 6px; text-align: left; }
        td { border-bottom: 1px solid #999; padding: 7px 6px; vertical-align: top; }
        .tick { width: 34px; text-align: center; }
        .tick div { width: 18px; height: 18px; border: 2px solid #000; margin: 0 auto; }
        .qty { width: 60px; text-align: center; font-size: 18pt; font-weight: 800; }
        .art { font-size: 12pt; font-weight: 700; }
        .meta { font-size: 9pt; color: #444; }
        .loc { width: 130px; font-size: 9pt; }
        .totals { display: flex; justify-content: space-between; margin-top: 10px; border-top: 3px solid #000; padding-top: 6px; font-size: 11pt; }
        .cod { border: 2px dashed #000; padding: 4px 10px; font-weight: 800; font-size: 14pt; }
        .note { border: 1px solid #000; padding: 6px 8px; margin-top: 8px; font-size: 11pt; }
        .warn { border: 2px solid #000; padding: 6px 8px; margin-top: 8px; font-weight: 700; }
        .sign { display: flex; gap: 10px; margin-top: 14px; font-size: 10pt; }
        .sign div { flex: 1; border-top: 1px solid #000; padding-top: 4px; }
        .foot { margin-top: 10px; font-size: 8pt; color: #666; text-align: center; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="#" class="btn btn-print" onclick="window.print();return false;">🖨️ Imprimer ({{ $orders->count() }} fiche{{ $orders->count() > 1 ? 's' : '' }})</a>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-back">← Retour aux commandes</a>
    </div>

    @foreach ($orders as $order)
        @php
            $units = $order->items->sum('quantity');
        @endphp
        <div class="sheet">
            <div class="head">
                <div>
                    <div class="title">FICHE DE PRÉPARATION</div>
                    <div class="sub">{{ Setting::get('store_name', 'Saidi Papetrie') }} · commande du {{ $order->created_at->format('d/m/Y à H:i') }}</div>
                    <div class="sub">Imprimée le {{ now()->format('d/m/Y H:i') }}</div>
                </div>
                <div>
                    <div class="ref">{{ $order->reference }}</div>
                    <div class="barcode" style="text-align:right">{!! Barcode::svg($order->reference) !!}</div>
                </div>
            </div>

            <div class="grid">
                <div class="box">
                    <div class="label">Client</div>
                    <div class="val">{{ $order->customer_name }}</div>
                    <div>{{ $order->phone }}@if($order->phone2) / {{ $order->phone2 }}@endif</div>
                </div>
                <div class="box">
                    <div class="label">Livraison</div>
                    <div class="val">{{ $order->delivery_type === 'stopdesk' ? 'STOP DESK' : 'À DOMICILE' }}</div>
                    <div>{{ trim(implode(' — ', array_filter([optional($order->wilaya)->name, $order->commune]))) ?: '—' }}</div>
                </div>
                <div class="box">
                    <div class="label">Articles</div>
                    <div class="val">{{ $order->items->count() }} ligne(s) · {{ $units }} pièce(s)</div>
                    <div><span class="flag">{{ $order->status_label }}</span></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="tick">✓</th>
                        <th>Article</th>
                        <th class="loc">Emplacement</th>
                        <th class="qty">Qté</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        @php
                            $key = $item->product_id . '-' . ((int) $item->product_variant_id);
                            $spots = ($locations[$key] ?? collect())
                                ->map(fn ($l) => optional($l->location)->name . ' (' . $l->quantity . ')')
                                ->filter()->implode(' · ');
                        @endphp
                        <tr>
                            <td class="tick"><div></div></td>
                            <td>
                                <div class="art">{{ $item->name }}</div>
                                <div class="meta">
                                    @if ($item->variant_label){{ $item->variant_label }} · @endif
                                    @if (optional($item->product)->sku)Réf. {{ $item->product->sku }} · @endif
                                    @if (optional($item->product)->brand){{ $item->product->brand }} · @endif
                                    {{ number_format((float) $item->unit_price, 0, ',', ' ') }} DA
                                </div>
                            </td>
                            <td class="loc">{{ $spots ?: '—' }}</td>
                            <td class="qty">{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totals">
                <div>
                    Sous-total {{ number_format((float) $order->subtotal, 0, ',', ' ') }} DA
                    · Livraison {{ number_format((float) $order->delivery_fee, 0, ',', ' ') }} DA
                    @if ((float) $order->discount > 0) · Remise −{{ number_format((float) $order->discount, 0, ',', ' ') }} DA @endif
                </div>
                <div class="cod">À ENCAISSER : {{ number_format((float) $order->total, 0, ',', ' ') }} DA</div>
            </div>

            @if ($order->has_open_stock_issue)
                <div class="warn">⚠️ STOCK INSUFFISANT signalé sur cette commande — voir avec le responsable avant de préparer.</div>
            @endif

            @if ($order->notes)
                <div class="note"><b>Remarque client :</b> {{ $order->notes }}</div>
            @endif

            @if ($order->address)
                <div class="note"><b>Adresse :</b> {{ $order->address }}</div>
            @endif

            <div class="sign">
                <div>Préparée par : ____________________</div>
                <div>Heure : ____ h ____</div>
                <div>Vérifiée par : ____________________</div>
            </div>

            <div class="foot">
                {{ $order->reference }} · {{ $loop->iteration }}/{{ $orders->count() }} ·
                Cochez chaque ligne au fur et à mesure, puis remettez la fiche avec le colis.
            </div>
        </div>
    @endforeach
</body>
</html>
