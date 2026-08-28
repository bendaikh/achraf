@props([
    'taxes',
    'currency' => 'DH',
    'showDiscount' => false,
    'showAdjustment' => false,
])

<div class="totals-wrap">
    <div class="total-row subtle">
        <span>Sous-total hors taxe</span>
        <span>{{ number_format($taxes['subtotal_ht'], 2) }} {{ $currency }}</span>
    </div>
    <div class="total-row subtle">
        <span>Taxe (TVA)</span>
        <span>{{ number_format($taxes['tax_total'], 2) }} {{ $currency }}</span>
    </div>
    @if($showDiscount && ($taxes['document_discount'] ?? 0) > 0)
        <div class="total-row">
            <span>Remise</span>
            <span>-{{ number_format($taxes['document_discount'], 2) }} {{ $currency }}</span>
        </div>
    @endif
    @if($showAdjustment && ! empty($taxes['adjustment_lines']))
        @foreach($taxes['adjustment_lines'] as $line)
            <div class="total-row">
                <span>{{ $line['signed_total'] >= 0 ? '+' : '−' }} {{ $line['label'] }}</span>
                <span>{{ $line['signed_total'] >= 0 ? '+' : '-' }}{{ number_format($line['line_total'], 2) }} {{ $currency }}</span>
            </div>
        @endforeach
    @elseif($showAdjustment && ($taxes['adjustment'] ?? 0) != 0)
        <div class="total-row">
            <span>Ajustement</span>
            <span>{{ number_format($taxes['adjustment'], 2) }} {{ $currency }}</span>
        </div>
    @endif
    <div class="total-row grand">
        <span>TOTAL FACTURE TTC</span>
        <span>{{ number_format($taxes['total_ttc'], 2) }} {{ $currency }}</span>
    </div>
</div>
