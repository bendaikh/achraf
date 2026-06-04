<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #6b7280; margin-bottom: 14px; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #fdb819; text-align: left; padding: 6px 5px; font-size: 8px; text-transform: uppercase; border: 1px solid #e5a617; }
        td { padding: 5px; border: 1px solid #e5e7eb; font-size: 9px; }
        tr.total td { font-weight: bold; background: #f9fafb; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Généré le {{ now()->format('d/m/Y à H:i') }} · {{ $filters }} · {{ $products->count() }} produit(s)
    </div>
    <table>
        <thead>
            <tr>
                <th>Référence</th>
                <th>Produit</th>
                <th class="text-right">{{ $stockLabel }}</th>
                <th class="text-right">Seuil alerte</th>
                <th>État</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
                @php $qty = (int) ($product->{$stockField} ?? 0); @endphp
                <tr>
                    <td>{{ $product->ref }}</td>
                    <td>{{ $product->name }}</td>
                    <td class="text-right">{{ $qty }}</td>
                    <td class="text-right">{{ $product->minimum_alert_stock ?? '—' }}</td>
                    <td>
                        @if($qty <= 0) Rupture
                        @elseif($product->minimum_alert_stock !== null && $qty <= $product->minimum_alert_stock) Sous seuil
                        @else OK
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Total quantités</td>
                <td class="text-right">{{ $totalStock }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
