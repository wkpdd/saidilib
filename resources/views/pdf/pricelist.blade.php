<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1e293b; font-size: 11px; margin: 0; }
        .head { border-bottom: 3px solid #2563eb; padding-bottom: 10px; margin-bottom: 14px; }
        .head h1 { margin: 0; font-size: 22px; color: #2563eb; }
        .head .sub { font-size: 11px; color: #64748b; }
        .meta { font-size: 10px; color: #475569; margin-top: 4px; }
        .cat { background: #eff6ff; color: #1d4ed8; font-weight: bold; font-size: 12px;
               padding: 6px 8px; margin-top: 12px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th { text-align: left; font-size: 9px; text-transform: uppercase; color: #64748b;
             border-bottom: 1px solid #cbd5e1; padding: 5px 8px; }
        td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
        td.price { text-align: right; font-weight: bold; white-space: nowrap; }
        .old { color: #94a3b8; text-decoration: line-through; font-weight: normal; font-size: 9px; }
        .foot { margin-top: 18px; font-size: 9px; color: #94a3b8; text-align: center;
                border-top: 1px solid #e2e8f0; padding-top: 8px; }
        .note { background: #fef3c7; color: #92400e; padding: 6px 8px; border-radius: 4px;
                font-size: 10px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="head">
        <h1>{{ $store }}</h1>
        <div class="sub">Liste des prix — Grossistes &amp; Revendeurs</div>
        <div class="meta">
            @if ($clientName) Client : <b>{{ $clientName }}</b> &nbsp;·&nbsp; @endif
            Éditée le {{ $date->format('d/m/Y') }}
            @if ($phone) &nbsp;·&nbsp; Tél : {{ $phone }} @endif
            @if ($email) &nbsp;·&nbsp; {{ $email }} @endif
        </div>
    </div>

    <div class="note">
        Prix en {{ $currency }}, hors livraison. Tarifs indicatifs susceptibles de modification —
        contactez-nous pour toute commande en gros. Paiement à la livraison (58 wilayas).
    </div>

    @foreach ($groups as $categoryName => $products)
        <div class="cat">{{ $categoryName }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width:15%">Référence</th>
                    <th>Désignation</th>
                    <th style="width:18%">Marque</th>
                    <th style="width:18%; text-align:right">Prix ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $p)
                    <tr>
                        <td>{{ $p->sku ?: '—' }}</td>
                        <td>{{ $p->name_fr }}</td>
                        <td>{{ $p->brand ?: '—' }}</td>
                        <td class="price">
                            {{ number_format((float) $p->price, 2, ',', ' ') }}
                            @if ($p->compare_at_price && $p->compare_at_price > $p->price)
                                <br><span class="old">{{ number_format((float) $p->compare_at_price, 2, ',', ' ') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="foot">
        {{ $store }} — Document généré automatiquement · {{ $date->format('d/m/Y H:i') }}
    </div>
</body>
</html>
