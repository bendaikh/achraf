@php
    $doc = $doc ?? [];
    $company = $company ?? \App\Support\CompanyInfo::all();
    $address = \App\Support\CompanyInfo::formattedAddress();
    $legal = [
        'ICE' => $company['ice'] ?? null,
        'RC' => $company['rc'] ?? null,
        'IF' => $company['if'] ?? null,
        'TP' => $company['patente'] ?? null,
        'CNSS' => $company['cnss'] ?? null,
    ];
    $items = $doc['items'] ?? collect();
    $taxes = $doc['taxes'] ?? [];
    $showSourceReference = $items->contains(fn ($item) => !empty($item->source_document_reference));
    $sourceDocumentDates = $doc['source_document_dates'] ?? [];
    $itemGroups = $showSourceReference
        ? $items->groupBy(fn ($item) => $item->source_document_reference ?: '—')
        : collect(['__flat__' => $items]);
    $generatedBy = $generatedBy ?? auth()->user()?->name ?? '—';
    $logoSrc = $logoSrc ?? ($company['logo_url'] ?? null);
    $cachet = $cachet ?? \App\Support\CompanyInfo::cachetForPrint($forPdf ?? false);
    $currencyLabel = $doc['currency_label'] ?? 'MAD';
    $priceMode = $doc['price_mode'] ?? 'sale';
    $settlement = $doc['settlement'] ?? null;
    $settlementPayments = $settlement['payments'] ?? [];
    $hasMultiplePayments = count($settlementPayments) > 1;
    // Réf stays wide enough for a SKU. Numeric columns are only as wide as their
    // headers and amounts, so long designations stay on fewer lines.
    $itemColumns = [
        ['class' => 'col-ref', 'label' => 'Réf', 'align' => 'text-left', 'width' => '17%'],
        ['class' => 'col-designation', 'label' => 'Désignation', 'align' => 'text-left', 'width' => '43%'],
        ['class' => 'col-qty', 'label' => 'Qté', 'align' => 'text-right', 'width' => '5%'],
        ['class' => 'col-price', 'label' => 'Prix unit. HT', 'align' => 'text-right', 'width' => '12%'],
        ['class' => 'col-tax', 'label' => 'TVA', 'align' => 'text-center', 'width' => '7%'],
        ['class' => 'col-discount', 'label' => 'Remise', 'align' => 'text-right', 'width' => '7%'],
        ['class' => 'col-total', 'label' => 'Total TTC', 'align' => 'text-right', 'width' => '9%'],
    ];

    $renderRows = [];
    foreach ($itemGroups as $originLabel => $groupItems) {
        if ($showSourceReference) {
            $renderRows[] = [
                'type' => 'origin',
                'label' => $originLabel,
                'date' => $sourceDocumentDates[$originLabel] ?? null,
            ];
        }
        foreach ($groupItems as $item) {
            $renderRows[] = [
                'type' => 'item',
                'item' => $item,
            ];
        }
    }

@endphp

<div class="facture-doc">
    <table class="facture-header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="facture-logo-cell">
                <div class="facture-logo">
                    @if($logoSrc)
                        <img src="{{ $logoSrc }}" alt="{{ $company['name'] }}" width="140">
                    @else
                        <span class="facture-logo-placeholder">LOGO</span>
                    @endif
                </div>
            </td>
            <td>
                <div class="facture-company-name">{{ $company['name'] }}</div>
                @if(!empty($company['subtitle']))
                    <div class="facture-company-subtitle">{{ $company['subtitle'] }}</div>
                @endif
                @if($address)
                    <div class="facture-contact-line"><strong>ADRESSE :</strong> {{ strtoupper($address) }}</div>
                @endif
                @if($company['phone'])
                    <div class="facture-contact-line"><strong>TÉL :</strong> {{ $company['phone'] }}</div>
                @endif
                @if($company['email'])
                    <div class="facture-contact-line"><strong>EMAIL :</strong> {{ strtoupper($company['email']) }}</div>
                @endif
                <div>
                    @foreach($legal as $label => $value)
                        @if($value)
                            <span class="facture-legal-item"><strong>{{ $label }} :</strong> {{ $value }}</span>
                        @endif
                    @endforeach
                </div>
            </td>
            <td width="220" class="facture-meta">
                <div class="facture-title">{{ $doc['title'] ?? 'DOCUMENT' }}</div>
                <div class="facture-number-badge">{{ $doc['number'] ?? '—' }}</div>
                @foreach($doc['dates'] ?? [] as $dateLine)
                    <div class="facture-date-line"><strong>{{ $dateLine['label'] }} :</strong> {{ $dateLine['value'] }}</div>
                @endforeach
            </td>
        </tr>
    </table>

    <div style="margin-bottom: 12px;">
        <div class="facture-client-tab">{{ $doc['party_tab'] ?? 'Informations' }}</div>
        <div class="facture-client-box">
            <div class="facture-client-name">{{ $doc['party_name'] ?? '—' }}</div>
            @foreach($doc['party_lines'] ?? [] as $line)
                <div class="facture-client-line"><strong>{{ $line['label'] }} :</strong> {{ $line['value'] }}</div>
            @endforeach
            @if(!empty($doc['party_legal']))
                <div>
                    @foreach($doc['party_legal'] as $label => $value)
                        @if($value)
                            <span class="facture-legal-item"><strong>{{ $label }} :</strong> {{ $value }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <table class="facture-items" width="100%" cellpadding="0" cellspacing="0">
        @include('documents.partials.commercial-items-colgroup')
        <thead>
            @include('documents.partials.commercial-items-thead')
        </thead>
        <tbody>
            @foreach($renderRows as $row)
                @include('documents.partials.commercial-item-row', ['row' => $row, 'priceMode' => $priceMode])
            @endforeach
        </tbody>
    </table>

    <div class="facture-totals-block">
        <table class="facture-totals-wrap" cellpadding="0" cellspacing="0">
            <tr>
                <td class="facture-totals-spacer">&nbsp;</td>
                <td class="facture-totals-cell" width="42%">
                    <table class="facture-totals" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>Sous-total HT</td>
                            <td class="text-right">{{ number_format($taxes['subtotal_ht'] ?? 0, 2) }} {{ $currencyLabel }}</td>
                        </tr>
                        <tr>
                            <td>TVA</td>
                            <td class="text-right">{{ number_format($taxes['tax_total'] ?? 0, 2) }} {{ $currencyLabel }}</td>
                        </tr>
                        @if(($taxes['document_discount'] ?? 0) > 0)
                            <tr>
                                <td>Remise</td>
                                <td class="text-right">-{{ number_format($taxes['document_discount'], 2) }} {{ $currencyLabel }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td>Sous-total TTC</td>
                            <td class="text-right">{{ number_format($taxes['items_ttc'] ?? $taxes['total_ttc'] ?? 0, 2) }} {{ $currencyLabel }}</td>
                        </tr>
                        @foreach(($taxes['adjustment_lines'] ?? []) as $line)
                            <tr>
                                <td>
                                    {{ $line['signed_total'] >= 0 ? '+' : '−' }} {{ $line['label'] }}
                                    @if($line['is_taxable'])
                                        <span style="font-size:9px;">(TVA {{ number_format($line['tax_rate'], 2) }}%)</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $line['signed_total'] >= 0 ? '+' : '-' }}{{ number_format($line['line_total'], 2) }} {{ $currencyLabel }}</td>
                            </tr>
                        @endforeach
                        @if(empty($taxes['adjustment_lines']) && ($taxes['adjustment'] ?? 0) != 0)
                            <tr>
                                <td>Ajustement</td>
                                <td class="text-right">{{ number_format($taxes['adjustment'], 2) }} {{ $currencyLabel }}</td>
                            </tr>
                        @endif
                        <tr class="grand">
                            <td>TOTAL FACTURE TTC</td>
                            <td class="text-right">{{ number_format($taxes['total_ttc'] ?? 0, 2) }} {{ $currencyLabel }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="facture-notes-box">
        <div class="facture-notes-title">Notes / Commentaires</div>
        <div class="facture-notes-body">{{ $doc['remarks'] ?: '—' }}</div>
    </div>
    @if($doc['show_amount_in_words'] ?? false)
        <p class="facture-amount-words">
            Arrêtée à la somme de : <strong>{{ \App\Support\AmountInWords::dirhams((float) ($taxes['total_ttc'] ?? 0)) }}</strong>
        </p>
    @endif
</div>

<div class="facture-page-footer">
    <table class="facture-closing-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="facture-closing-left" width="58%">
                @if($settlement)
                    @php
                        $settlementCurrency = $settlement['currency_label'] ?? $currencyLabel;
                    @endphp
                    <div class="facture-settlement-box">
                        <div class="facture-settlement-title">Règlement / Paiement</div>
                        <div class="facture-settlement-line"><strong>Statut :</strong> {{ $settlement['status_label'] }}</div>

                        @if(count($settlementPayments) === 0)
                            <div class="facture-settlement-line"><strong>Montant payé :</strong> {{ number_format($settlement['total_paid'], 2, ',', ' ') }} {{ $settlementCurrency }}</div>
                        @elseif(!$hasMultiplePayments)
                            @php
                                $payment = $settlementPayments[0];
                            @endphp
                            <div class="facture-settlement-line"><strong>Montant payé :</strong> {{ number_format($payment['amount'], 2, ',', ' ') }} {{ $settlementCurrency }}</div>
                            <div class="facture-settlement-line"><strong>Mode de règlement :</strong> {{ $payment['method'] }}</div>
                            <div class="facture-settlement-line"><strong>Date du règlement :</strong> {{ $payment['date'] }}</div>
                            @if(!empty($payment['reference']))
                                <div class="facture-settlement-line"><strong>Référence :</strong> {{ $payment['reference'] }}</div>
                            @endif
                        @else
                            @foreach($settlementPayments as $index => $payment)
                                <div class="facture-settlement-payment">
                                    <div class="facture-settlement-payment-title">Règlement {{ $index + 1 }}</div>
                                    <div class="facture-settlement-line"><strong>Montant payé :</strong> {{ number_format($payment['amount'], 2, ',', ' ') }} {{ $settlementCurrency }}</div>
                                    <div class="facture-settlement-line"><strong>Mode de règlement :</strong> {{ $payment['method'] }}</div>
                                    <div class="facture-settlement-line"><strong>Date du règlement :</strong> {{ $payment['date'] }}</div>
                                    @if(!empty($payment['reference']))
                                        <div class="facture-settlement-line"><strong>Référence :</strong> {{ $payment['reference'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                            <div class="facture-settlement-line facture-settlement-total"><strong>Total payé :</strong> {{ number_format($settlement['total_paid'], 2, ',', ' ') }} {{ $settlementCurrency }}</div>
                        @endif

                        <div class="facture-settlement-line facture-settlement-remaining">
                            <strong>Reste à payer :</strong> {{ number_format($settlement['remaining'], 2, ',', ' ') }} {{ $settlementCurrency }}
                        </div>
                    </div>
                @endif
            </td>
            <td class="facture-closing-right" width="42%">
                <div class="facture-signature-label">Cachet de la société &amp; signature</div>
                <div class="facture-signature-box">
                    @if($cachet)
                        <table class="facture-signature-box-table" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <img
                                        src="{{ $cachet['src'] }}"
                                        alt="Cachet {{ $company['name'] }}"
                                        class="facture-cachet-img"
                                        width="{{ $cachet['width'] }}"
                                        height="{{ $cachet['height'] }}"
                                    >
                                </td>
                            </tr>
                        </table>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="facture-footer-meta">
        Document généré le : <strong>{{ now()->format('d/m/Y à H:i') }}</strong>
        — Par : <strong>{{ $generatedBy }}</strong>
    </div>
    <div class="facture-accent-bar"></div>
</div>
