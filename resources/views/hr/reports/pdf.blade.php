<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0a5d8a; color: #fff; text-align: left; padding: 5px; }
        td { border: 1px solid #e5e7eb; padding: 4px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>Généré le {{ now()->format('d/m/Y H:i') }}</p>
    <table>
        <thead>
            <tr>@foreach($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
