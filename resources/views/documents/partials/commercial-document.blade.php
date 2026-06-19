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
    $minRows = 6;
    $items = $doc['items'] ?? collect();
    $taxes = $doc['taxes'] ?? [];
    $emptyRows = max(0, $minRows - $items->count());
    $generatedBy = $generatedBy ?? auth()->user()?->name ?? '—';
    $logoSrc = $logoSrc ?? ($company['logo_url'] ?? null);
    $cachet = $cachet ?? \App\Support\CompanyInfo::cachetForPrint($forPdf ?? false);
    $currencyLabel = $doc['currency_label'] ?? 'MAD';
    $priceMode = $doc['price_mode'] ?? 'sale';
@endphp

<div class="facture-footer-fixed">
    <table class="facture-footer-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="facture-footer-meta">
                    Document généré le : <strong>{{ now()->format('d/m/Y à H:i') }}</strong><br>
                    Par : <strong>{{ $generatedBy }}</strong>
                </div>
            </td>
            <td width="240">
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
    <div class="facture-accent-bar"></div>
</div>

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

    <div style="margin-bottom: 16px;">
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

    <table class="facture-items" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th width="20%">Réf</th>
                <th width="32%">Désignation</th>
                <th class="text-right" width="7%">Qté</th>
                <th class="text-right" width="13%">Prix unit. HT</th>
                <th class="text-center" width="8%">TVA</th>
                <th class="text-right" width="8%">Remise</th>
                <th class="text-right" width="12%">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                @php($line = \App\Support\LineItemCalculator::forDisplay($item, $priceMode))
                <tr>
                    <td>{{ $item->ref ?? '-' }}</td>
                    <td>{{ $item->designation }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($line['unit_price_ht'], 2) }}</td>
                    <td class="text-center">{{ number_format($item->tax_rate, 2) }}%</td>
                    <td class="text-right">{{ number_format($item->discount ?? 0, 2) }}</td>
                    <td class="text-right"><strong>{{ number_format($line['line_total'], 2) }}</strong></td>
                </tr>
            @endforeach
            @for($i = 0; $i < $emptyRows; $i++)
                <tr class="empty-row">
                    <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <table class="facture-bottom-table" cellpadding="0" cellspacing="0">
        <tr>
            <td width="58%">
                <div class="facture-notes-box">
                    <div class="facture-notes-title">Notes / Commentaires</div>
                    <div class="facture-notes-body">{{ $doc['remarks'] ?: '—' }}</div>
                </div>
                @if($doc['show_amount_in_words'] ?? false)
                    <p class="facture-amount-words">
                        Arrêtée à la somme de : <strong>{{ \App\Support\AmountInWords::dirhams((float) ($taxes['total_ttc'] ?? 0)) }}</strong>
                    </p>
                @endif
            </td>
            <td width="42%">
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
                    @if(($taxes['adjustment'] ?? 0) != 0)
                        <tr>
                            <td>Ajustement</td>
                            <td class="text-right">{{ number_format($taxes['adjustment'], 2) }} {{ $currencyLabel }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td>Total TTC</td>
                        <td class="text-right">{{ number_format($taxes['total_ttc'] ?? 0, 2) }} {{ $currencyLabel }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="facture-footer-spacer"></div>
</div>
