@php
    $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
    $snapshot = $snapshot ?? ['invoices' => [], 'credits' => [], 'advances' => []];
@endphp
<div>
    <p class="muted">{{ $company['name'] ?? 'Libromart' }} — Fiche de règlement (document généré, distinct du justificatif importé)</p>
    <h1>{{ $payment->payment_number }}</h1>
    <p>Fournisseur : <strong>{{ $payment->supplier?->name }}</strong></p>
    <p>Date : {{ $payment->payment_date?->format('d/m/Y') }} · Mode : {{ $payment->payment_method }} · Montant : <strong>{{ $fmt($payment->amount) }} DH</strong></p>
    <p>Référence : {{ $payment->payment_reference ?: '—' }} · Notes : {{ $payment->notes ?: '—' }}</p>
    <p>Créé par : {{ $payment->user?->name ?: '—' }} le {{ $payment->created_at?->format('d/m/Y H:i') }} · Statut : {{ $payment->statusLabel() }}</p>

    <h2 style="margin-top:18px;font-size:14px;">Affectation du règlement</h2>
    <table>
        <thead>
            <tr>
                <th>Facture</th>
                <th class="right">Montant facture</th>
                <th class="right">Déjà payé avant</th>
                <th class="right">Avoir imputé</th>
                <th class="right">Avance imputée</th>
                <th class="right">Ce règlement</th>
                <th class="right">Reste après</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($snapshot['invoices'] ?? []) as $line)
                <tr>
                    <td>{{ $line['invoice_number'] }}</td>
                    <td class="right">{{ $fmt($line['invoice_amount'] ?? 0) }}</td>
                    <td class="right">{{ isset($line['paid_before']) ? $fmt($line['paid_before']) : '—' }}</td>
                    <td class="right">{{ $fmt($line['credit_applied'] ?? 0) }}</td>
                    <td class="right">{{ $fmt($line['advance_applied'] ?? 0) }}</td>
                    <td class="right">{{ $fmt($line['cash_applied'] ?? 0) }}</td>
                    <td class="right">{{ $fmt($line['remaining_after'] ?? 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Aucune affectation sur facture</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(!empty($snapshot['credits']))
        <p style="margin-top:12px;"><strong>Avoirs utilisés</strong></p>
        <ul>
            @foreach($snapshot['credits'] as $credit)
                <li>{{ $credit['number'] ?? '' }} → {{ $credit['invoice'] ?? '' }} : {{ $fmt($credit['amount'] ?? 0) }} DH</li>
            @endforeach
        </ul>
    @endif
    @if(!empty($snapshot['advances']))
        <p><strong>Avances utilisées</strong></p>
        <ul>
            @foreach($snapshot['advances'] as $adv)
                <li>{{ $adv['source'] ?? '' }} → {{ $adv['invoice'] ?? '' }} : {{ $fmt($adv['amount'] ?? 0) }} DH</li>
            @endforeach
        </ul>
    @endif
    @if(!empty($generatedBy))
        <p class="muted" style="margin-top:16px;">Édité par {{ $generatedBy }}</p>
    @endif
</div>
