@props(['exportType', 'itemLabel' => 'élément(s)', 'zipExport' => false])

@php
    $zipTypes = ['invoices', 'quotes', 'purchase-orders', 'credit-notes', 'supplier-invoices', 'supplier-purchase-orders', 'supplier-credit-notes', 'receptions', 'expenses-with-invoice'];
    $showZip = $zipExport || in_array($exportType, $zipTypes, true);
@endphp

<div id="bulkActionsBar-{{ $exportType }}" class="hidden bg-[#0a5d8a]/10 border border-[#0a5d8a]/30 rounded-lg p-4 mb-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <span class="text-sm font-medium text-gray-700">
            <span id="selectedCount-{{ $exportType }}">0</span> {{ $itemLabel }} sélectionné(s)
        </span>
        <div class="flex items-center gap-2">
            <button type="button" onclick="exportSelectedToExcel('{{ $exportType }}')"
                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-medium">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Exporter vers Excel
            </button>
            @if($showZip && in_array($exportType, ['invoices', 'quotes', 'purchase-orders', 'credit-notes', 'supplier-invoices'], true))
            <button type="button" onclick="exportSelectedToZip('{{ $exportType }}')"
                class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm font-medium">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                Exporter ZIP (PDF)
            </button>
            @endif
            <button type="button" onclick="clearTableSelection('{{ $exportType }}')"
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm font-medium">
                Annuler
            </button>
        </div>
    </div>
</div>
