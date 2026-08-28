@props([
    'exportType',
    'itemLabel' => 'élément(s)',
    'zipExport' => false,
    'canDelete' => null,
    'canPrint' => null,
    'printRoute' => null,
])

@php
    $showZip = $zipExport || in_array($exportType, [
        'invoices', 'quotes', 'purchase-orders', 'credit-notes', 'delivery-notes',
        'supplier-invoices', 'supplier-delivery-notes', 'receptions', 'supplier-purchase-orders',
        'supplier-credit-notes', 'expenses', 'expenses-with-invoice', 'expenses-without-invoice',
    ], true);

    $deletableTypes = [
        'invoices', 'quotes', 'purchase-orders', 'delivery-notes', 'credit-notes',
        'supplier-invoices', 'supplier-delivery-notes', 'receptions', 'supplier-purchase-orders',
        'supplier-credit-notes', 'expenses', 'expenses-with-invoice', 'expenses-without-invoice',
        'orders', 'pos-sales', 'products', 'clients', 'suppliers',
    ];
    $showDelete = $canDelete ?? in_array($exportType, $deletableTypes, true);

    $printRoutes = [
        'invoices' => 'invoices.print',
        'quotes' => 'quotes.print',
        'purchase-orders' => 'purchase-orders.print',
        'delivery-notes' => 'delivery-notes.print',
        'credit-notes' => 'credit-notes.print',
        'supplier-invoices' => 'supplier-invoices.print',
        'supplier-delivery-notes' => 'supplier-delivery-notes.print',
        'receptions' => 'receptions.print',
        'supplier-purchase-orders' => 'supplier-purchase-orders.print',
        'supplier-credit-notes' => 'supplier-credit-notes.print',
        'expenses' => 'expenses.print',
        'expenses-with-invoice' => 'expenses.print',
        'expenses-without-invoice' => 'expenses.print',
    ];
    $printPattern = null;
    $resolvedPrintRoute = $printRoute ?? ($printRoutes[$exportType] ?? null);
    if ($canPrint !== false && is_string($resolvedPrintRoute) && \Illuminate\Support\Facades\Route::has($resolvedPrintRoute)) {
        $printPattern = str_replace('999999001', '__ID__', route($resolvedPrintRoute, 999999001));
    }
    $showPrint = $canPrint ?? ($printPattern !== null);
@endphp

<div
    id="bulkActionsBar-{{ $exportType }}"
    class="hidden bg-[#0a5d8a]/10 border border-[#0a5d8a]/30 rounded-lg p-4 mb-4"
    data-export-type="{{ $exportType }}"
    @if($printPattern) data-print-pattern="{{ $printPattern }}" @endif
>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <span class="text-sm font-medium text-gray-700">
            <span id="selectedCount-{{ $exportType }}">0</span> éléments sélectionnés
        </span>
        <div class="flex flex-wrap items-center gap-2">
            @if($showDelete)
            <button type="button" onclick="deleteSelectedTable('{{ $exportType }}')"
                class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm font-medium">
                Supprimer
            </button>
            @endif
            <button type="button" onclick="exportSelectedToExcel('{{ $exportType }}')"
                class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-medium">
                Exporter
            </button>
            @if($showZip)
            <button type="button" onclick="exportSelectedToZip('{{ $exportType }}')"
                class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition text-sm font-medium">
                Télécharger
            </button>
            @endif
            @if($showPrint && $printPattern)
            <button type="button" onclick="printSelectedTable('{{ $exportType }}')"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                Imprimer
            </button>
            @endif
            @if($exportType === 'receptions')
            <button type="button" onclick="openReceptionConvertModal()"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium">
                Convertir
            </button>
            @endif
            @if($exportType === 'supplier-delivery-notes')
            <button type="button" onclick="openSupplierDeliveryNoteConvertModal()"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium">
                Convertir
            </button>
            @endif
            {{ $slot }}
            <button type="button" onclick="clearTableSelection('{{ $exportType }}')"
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm font-medium">
                Annuler
            </button>
        </div>
    </div>
</div>
