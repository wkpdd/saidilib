@php use App\Models\Setting; use App\Support\Barcode; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bordereau {{ $order->reference }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; padding: 12px; font-size: 13px; }
        .slip { width: 100%; max-width: 480px; margin: 0 auto; border: 2px solid #111; border-radius: 8px; padding: 14px; }
        .row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
        .brand { font-size: 18px; font-weight: 800; }
        .muted { color: #555; font-size: 11px; }
        .barcode { text-align: center; margin: 10px 0 4px; }
        .barcode svg { max-width: 100%; height: 56px; }
        .ref { text-align: center; font-weight: 700; letter-spacing: 1px; font-size: 14px; }
        .box { border: 1px solid #ccc; border-radius: 6px; padding: 8px 10px; margin-top: 10px; }
        .label { font-size: 10px; text-transform: uppercase; color: #777; letter-spacing: .5px; }
        .big { font-size: 15px; font-weight: 700; }
        .cod { text-align: center; border: 2px dashed #111; border-radius: 6px; padding: 8px; margin-top: 10px; }
        .cod .amount { font-size: 22px; font-weight: 800; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12px; }
        th, td { text-align: left; padding: 4px 2px; border-bottom: 1px solid #eee; }
        td.r, th.r { text-align: right; }
        .toolbar { max-width: 480px; margin: 0 auto 12px; display: flex; gap: 8px; }
        .btn { flex: 1; padding: 10px; border: 0; border-radius: 6px; font-weight: 700; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-print { background: #2563eb; color: #fff; }
        .btn-back { background: #eee; color: #111; }
        @media print { .toolbar { display: none; } body { padding: 0; } .slip { border-width: 1px; } }
    </style>
</head>
<body onload="if(!window.location.hash){/* stay */}">
    <div class="toolbar">
        <a href="#" class="btn btn-print" onclick="window.print();return false;">🖨️ Imprimer</a>
        <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-back">← Retour</a>
    </div>

    <div class="slip">
        <div class="row">
            <div>
                <div class="brand">{{ Setting::get('store_name', 'Saidi Papetrie') }}</div>
                <div class="muted">{{ Setting::get('phone') }}</div>
            </div>
            <div style="text-align:right">
                <div class="label">Livraison</div>
                <div class="big">{{ $order->delivery_type === 'stopdesk' ? 'Stop Desk' : 'À domicile' }}</div>
                <div class="muted">Noest Express</div>
            </div>
        </div>

        <div class="barcode">{!! Barcode::svg($order->reference) !!}</div>
        <div class="ref">{{ $order->reference }}</div>

        <div class="box">
            <div class="label">Destinataire</div>
            <div class="big">{{ $order->customer_name }}</div>
            <div>{{ $order->phone }}@if($order->phone2) / {{ $order->phone2 }}@endif</div>
            <div class="muted">
                {{ $order->address }}@if($order->commune), {{ $order->commune }}@endif
                @if($order->wilaya) — {{ $order->wilaya->label }}@endif
            </div>
        </div>

        <div class="cod">
            <div class="label">Montant à encaisser (COD)</div>
            <div class="amount">{{ number_format((float) $order->total, 2, ',', ' ') }} DA</div>
        </div>

        <table>
            <thead><tr><th>Article</th><th class="r">Qté</th></tr></thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->name }}@if($item->variant_label) · {{ $item->variant_label }}@endif</td>
                        <td class="r">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($order->notes)
            <div class="box"><div class="label">Remarque</div><div>{{ $order->notes }}</div></div>
        @endif

        <div class="muted" style="margin-top:10px;text-align:center">
            Émis le {{ now()->format('d/m/Y H:i') }} · Paiement à la livraison
        </div>
    </div>
</body>
</html>
