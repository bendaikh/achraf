@props(['converted' => false, 'convertedToInvoice' => null, 'convertedToDeliveryNote' => false])

@php
    $invoiceConverted = $convertedToInvoice ?? $converted;
@endphp

@if($invoiceConverted && ! $convertedToDeliveryNote)
    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
        Converti
    </span>
@elseif($invoiceConverted || $convertedToDeliveryNote)
    <div class="flex flex-col items-start gap-1">
        @if($invoiceConverted)
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                Facture
            </span>
        @endif
        @if($convertedToDeliveryNote)
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                BL
            </span>
        @endif
    </div>
@else
    <span class="px-2.5 py-0.5 inline-flex text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
        Non converti
    </span>
@endif
