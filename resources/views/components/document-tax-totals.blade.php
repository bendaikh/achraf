@props(['document', 'items', 'currency' => null])

@php
    $taxes = \App\Support\DocumentTaxBreakdown::fromDocument($document, collect($items));
    $currency = $currency ?? ($document->currency ?? 'DH');
@endphp

<div class="max-w-md ml-auto space-y-2">
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Sous-total hors taxe</span>
        <span class="font-medium text-gray-900">{{ number_format($taxes['subtotal_ht'], 2) }} {{ $currency }}</span>
    </div>
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Taxe (TVA)</span>
        <span class="font-medium text-gray-900">{{ number_format($taxes['tax_total'], 2) }} {{ $currency }}</span>
    </div>
    @if(($taxes['document_discount'] ?? 0) > 0)
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Remise</span>
            <span class="font-medium text-gray-900">-{{ number_format($taxes['document_discount'], 2) }} {{ $currency }}</span>
        </div>
    @endif
    @if(($taxes['adjustment'] ?? 0) != 0)
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Ajustement</span>
            <span class="font-medium text-gray-900">{{ number_format($taxes['adjustment'], 2) }} {{ $currency }}</span>
        </div>
    @endif
    <div class="flex justify-between text-lg font-semibold border-t border-gray-200 pt-2">
        <span class="text-gray-900">Total TTC</span>
        <span class="text-[#e5a617]">{{ number_format($taxes['total_ttc'], 2) }} {{ $currency }}</span>
    </div>
</div>
