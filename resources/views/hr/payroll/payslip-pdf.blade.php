<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletin {{ $employee->matricule }} — {{ $run->periodLabel() }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .muted { color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        th { background: #0a5d8a; color: #fff; text-align: left; font-size: 10px; }
        .right { text-align: right; }
        .total td { font-weight: bold; background: #f3f4f6; }
        .grid { width: 100%; margin-bottom: 12px; }
        .grid td { border: none; padding: 2px 0; }
    </style>
</head>
<body>
    <h1>Bulletin de paie — {{ $run->periodLabel() }}</h1>
    <div class="muted">Libromart · Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
    <table class="grid">
        <tr><td><strong>{{ $employee->fullName() }}</strong> ({{ $employee->matricule }})</td><td class="right">{{ $employee->job_title }}</td></tr>
        <tr><td>CNSS {{ $employee->cnss_number ?: '—' }} · AMO {{ $employee->amo_number ?: '—' }}</td><td class="right">Entrée {{ $employee->hire_date?->format('d/m/Y') }}</td></tr>
    </table>
    <table>
        <thead><tr><th>Élément</th><th class="right">Montant (MAD)</th></tr></thead>
        <tbody>
            <tr><td>Salaire de base</td><td class="right">{{ number_format((float) $slip->base_salary, 2, ',', ' ') }}</td></tr>
            <tr><td>Primes</td><td class="right">{{ number_format((float) $slip->primes, 2, ',', ' ') }}</td></tr>
            <tr><td>Indemnités</td><td class="right">{{ number_format((float) $slip->indemnites, 2, ',', ' ') }}</td></tr>
            <tr><td>Heures supplémentaires</td><td class="right">{{ number_format((float) $slip->overtime_amount, 2, ',', ' ') }}</td></tr>
            <tr><td>Absences / congés sans solde</td><td class="right">- {{ number_format((float) $slip->absence_deduction, 2, ',', ' ') }}</td></tr>
            <tr><td>Salaire brut</td><td class="right">{{ number_format((float) $slip->gross, 2, ',', ' ') }}</td></tr>
            <tr><td>Cotisations salariales (CNSS / AMO)</td><td class="right">- {{ number_format((float) $slip->employee_contributions, 2, ',', ' ') }}</td></tr>
            <tr><td>IR</td><td class="right">- {{ number_format((float) $slip->income_tax, 2, ',', ' ') }}</td></tr>
            <tr><td>Retenues</td><td class="right">- {{ number_format((float) $slip->retenues, 2, ',', ' ') }}</td></tr>
            <tr><td>Avances / acomptes</td><td class="right">- {{ number_format((float) $slip->avances, 2, ',', ' ') }}</td></tr>
            <tr class="total"><td>Net à payer</td><td class="right">{{ number_format((float) $slip->net, 2, ',', ' ') }}</td></tr>
            <tr><td>Charges patronales</td><td class="right">{{ number_format((float) $slip->employer_contributions, 2, ',', ' ') }}</td></tr>
            <tr><td>Coût employeur</td><td class="right">{{ number_format((float) $slip->employer_cost, 2, ',', ' ') }}</td></tr>
        </tbody>
    </table>
    <p class="muted" style="margin-top:16px">Les anciennes paies conservent le salaire et les règles (CNSS / AMO / IR) applicables à leur période.</p>
</body>
</html>
