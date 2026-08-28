<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Stock {{ $warehouse->name }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { color: #6b7280; margin-bottom: 10px; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0a5d8a; color: #fff; text-align: left; padding: 4px 3px; font-size: 7px; text-transform: uppercase; }
        td { padding: 3px; border: 1px solid #e5e7eb; font-size: 7px; }
        tr.total td { font-weight: bold; background: #f1f5f9; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h1>STOCK {{ $warehouse->name }}</h1>
    <div class="meta">
        État au {{ $as_of->format('d/m/Y') }} · Généré le {{ now()->format('d/m/Y H:i') }}
        · {{ $references }} références · Qté {{ $quantity }}
        · HT {{ number_format($value_ht, 2) }} · TVA {{ number_format($value_vat ?? ($value_ttc - $value_ht), 2) }} · TTC {{ number_format($value_ttc, 2) }}
    </div>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Produit</th>
                <th>Empl.</th>
                <th>Fournisseur</th>
                <th class="text-right">Qté</th>
                <th class="text-right">Rés.</th>
                <th class="text-right">Dispo.</th>
                <th class="text-right">PA HT</th>
                <th class="text-right">PA TTC</th>
                <th class="text-right">Val. HT</th>
                <th class="text-right">Val. TTC</th>
                <th class="text-right">PV HT</th>
                <th class="text-right">PV TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row->sku }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->location ?? '—' }}</td>
                    <td>{{ $row->supplier ?? '' }}</td>
                    <td class="text-right">{{ $row->quantity }}</td>
                    <td class="text-right">{{ $row->reserved ?? 0 }}</td>
                    <td class="text-right">{{ $row->available ?? $row->quantity }}</td>
                    <td class="text-right">{{ number_format($row->price_ht, 2) }}</td>
                    <td class="text-right">{{ number_format($row->price_ttc, 2) }}</td>
                    <td class="text-right">{{ number_format($row->value_ht, 2) }}</td>
                    <td class="text-right">{{ number_format($row->value_ttc, 2) }}</td>
                    <td class="text-right">{{ number_format($row->sale_price_ht ?? 0, 2) }}</td>
                    <td class="text-right">{{ number_format($row->sale_price_ttc ?? 0, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">TOTAL · {{ $references }} réf.</td>
                <td class="text-right">{{ $quantity }}</td>
                <td colspan="4"></td>
                <td class="text-right">{{ number_format($value_ht, 2) }}</td>
                <td class="text-right">{{ number_format($value_ttc, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
