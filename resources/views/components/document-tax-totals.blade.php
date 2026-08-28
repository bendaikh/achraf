@props(['document', 'items', 'currency' => null])

@php
    $taxes = \App\Support\DocumentTaxBreakdown::fromDocument($document, collect($items));
    $currency = $currency ?? ($document->currency ?? 'DH');
@endphp

<div class="max-w-md ml-auto space-y-2">
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Total HT articles</span>
        <span class="font-medium text-gray-900">{{ number_format($taxes['items_subtotal_ht'] ?? $taxes['subtotal_ht'], 2) }} {{ $currency }}</span>
    </div>
    @if(($taxes['document_discount'] ?? 0) > 0)
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Remise</span>
            <span class="font-medium text-gray-900">-{{ number_format($taxes['document_discount'], 2) }} {{ $currency }}</span>
        </div>
    @endif
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">TVA</span>
        <span class="font-medium text-gray-900">{{ number_format($taxes['items_tax_total'] ?? $taxes['tax_total'], 2) }} {{ $currency }}</span>
    </div>
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Sous-total TTC articles</span>
        <span class="font-medium text-gray-900">{{ number_format($taxes['items_ttc'] ?? $taxes['total_ttc'], 2) }} {{ $currency }}</span>
    </div>
    @if(($taxes['adjustments_positive'] ?? 0) != 0)
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Total ajustements (+)</span>
            <span class="font-medium text-emerald-700">+{{ number_format($taxes['adjustments_positive'], 2) }} {{ $currency }}</span>
        </div>
    @endif
    @if(($taxes['adjustments_negative'] ?? 0) != 0)
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Total ajustements (−)</span>
            <span class="font-medium text-red-600">-{{ number_format($taxes['adjustments_negative'], 2) }} {{ $currency }}</span>
        </div>
    @elseif(($taxes['adjustment'] ?? 0) != 0 && empty($taxes['adjustment_lines']))
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Ajustement</span>
            <span class="font-medium text-gray-900">{{ number_format($taxes['adjustment'], 2) }} {{ $currency }}</span>
        </div>
    @endif
    @foreach(($taxes['adjustment_lines'] ?? []) as $line)
        <div class="flex justify-between text-xs text-gray-500 pl-2">
            <span>{{ $line['signed_total'] >= 0 ? '+' : '−' }} {{ $line['label'] }}@if($line['is_taxable']) (TVA {{ number_format($line['tax_rate'], 2) }}%)@endif</span>
            <span>{{ $line['signed_total'] >= 0 ? '+' : '-' }}{{ number_format($line['line_total'], 2) }} {{ $currency }}</span>
        </div>
    @endforeach
    <div class="flex justify-between text-lg font-semibold border-t border-gray-200 pt-2">
        <span class="text-gray-900">TOTAL FACTURE TTC</span>
        <span class="text-[#e5a617]">{{ number_format($taxes['total_ttc'], 2) }} {{ $currency }}</span>
    </div>
</div>
