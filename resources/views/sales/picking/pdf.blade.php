<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Préparation picking</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 16px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        h3 { font-size: 12px; margin: 10px 0 4px; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 5px 6px; text-align: left; }
        th { background: #f3f4f6; }
        .qty { text-align: center; font-weight: bold; width: 60px; }
    </style>
</head>
<body>
    <h1>Préparation des commandes / Picking</h1>
    <div class="meta">Généré le {{ $generatedAt->format('d/m/Y H:i') }} — document opérationnel (aucune sortie de stock)</div>

    @forelse($groups as $warehouse => $locations)
        <h2>Dépôt : {{ $warehouse }}</h2>
        @foreach($locations as $location => $lines)
            <h3>Emplacement : {{ $location }}</h3>
            <table>
                <thead>
                    <tr>
                        <th>SKU / Réf.</th>
                        <th>Désignation</th>
                        <th>Variante</th>
                        <th class="qty">Qté</th>
                        <th>Commandes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $line)
                        <tr>
                            <td>{{ $line['sku'] }}</td>
                            <td>{{ $line['name'] }}</td>
                            <td>{{ $line['variant'] ?: '—' }}</td>
                            <td class="qty">{{ $line['quantity'] }}</td>
                            <td>{{ implode(', ', array_values($line['orders'])) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @empty
        <p>Aucune réservation active pour les commandes sélectionnées. Allouez d’abord le stock depuis la fiche commande.</p>
    @endforelse
</body>
</html>
