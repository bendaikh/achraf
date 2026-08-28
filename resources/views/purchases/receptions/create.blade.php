@extends('layouts.with-sidebar')

@section('title', 'Créer un bon de réception')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Créer un bon de réception</h2>
                    <p class="text-sm text-gray-600 mt-1">Créer un nouveau bon de réception</p>
                </div>
                <a href="{{ route('receptions.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Retour à la liste
                </a>
            </div>
        </header>

        <div class="p-8">
            @if(session('error'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            @endif

            @if(!empty($sourceDocument))
                <div class="mb-6 bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                    <p class="text-sm text-emerald-900">
                        Réception préremplie depuis
                        <strong>{{ class_basename($sourceDocument) }}</strong>
                        — quantités restantes uniquement. L’entrée en stock sera enregistrée via ce bon de réception.
                    </p>
                </div>
            @endif

            <form action="{{ route('receptions.store') }}" method="POST" id="quoteForm">
                @csrf
                @if(!empty($prefill['supplier_purchase_order_id']))
                    <input type="hidden" name="supplier_purchase_order_id" value="{{ $prefill['supplier_purchase_order_id'] }}">
                @endif
                @if(!empty($prefill['supplier_delivery_note_id']))
                    <input type="hidden" name="supplier_delivery_note_id" value="{{ $prefill['supplier_delivery_note_id'] }}">
                @endif
                @if(!empty($prefill['source_supplier_invoice_id']))
                    <input type="hidden" name="source_supplier_invoice_id" value="{{ $prefill['source_supplier_invoice_id'] }}">
                @endif
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur *</label>
                            <x-supplier-select-with-create :suppliers="$suppliers" :selected="old('supplier_id', $prefill['supplier_id'] ?? null)" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Numéro de réception *</label>
                            <input type="text" name="reception_number" value="{{ old('reception_number', $receptionNumber) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Saisir le numéro de réception">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Devise</label>
                            <input type="text" name="currency" value="{{ old('currency', $prefill['currency'] ?? 'dh - MAD') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date de réception *</label>
                            <input type="date" name="reception_date" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date de livraison</label>
                            <input type="date" name="delivery_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dépôt destination (défaut)</label>
                            <select name="warehouse_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">— Choisir —</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $prefill['warehouse_id'] ?? $warehouse->is_fulfillment_default) == $warehouse->id)>
                                        {{ $warehouse->isOnline() ? '🟢 ' : '' }}{{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Surchargeable par ligne. SHOPIFY STOCK EN LIGNE synchronise Shopify.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Référence</label>
                            <input type="text" name="reference" value="{{ old('reference', $prefill['reference'] ?? '') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="brouillon">Brouillon</option>
                                <option value="envoyé">Envoyé</option>
                                <option value="accepté">Accepté</option>
                                <option value="refusé">Refusé</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Modèle</label>
                            <input type="text" name="model" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Ajouter un article</h3>
                        <button type="button" onclick="addItem()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                            + Ajouter
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full" id="itemsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produit</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Réf</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix d'achat</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TVA (%)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dépôt / Emplacement</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remise</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Remarques</label>
                            <textarea name="remarks" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        </div>

                        <div>
                            <div class="bg-blue-50 rounded-lg p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-gray-600">Montant HT</span>
                                    <span class="text-lg font-semibold" id="subtotal">0.00</span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-gray-600">Remise</span>
                                    <span class="text-lg font-semibold" id="discount">0.00</span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-gray-600">TVA</span>
                                    <span class="text-lg font-semibold" id="taxAmount">0.00</span>
                                </div>
                                <div class="border-t border-blue-200 pt-2 mt-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-base font-semibold text-gray-900">Total TTC</span>
                                        <span class="text-2xl font-bold text-blue-600" id="total">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 font-medium">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </main>

@push('scripts')
@include('purchases.partials.line-stock-allocations')
<script>
window.commercialDocConfig = {
    pricesAreTtc: @json($pricesAreTtc ?? false),
    priceMode: 'purchase',
    products: @json($products),
};
</script>
@include('partials.commercial-document-form-script')
<script>
var itemIndex = 0;

function addItem() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-200';
    row.innerHTML = `
        <td class="px-4 py-3">
            ${window.commercialProductSelectHtml(itemIndex)}
        </td>
        <td class="px-4 py-3">
            <input type="text" name="items[${itemIndex}][ref]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" id="ref_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="text" name="items[${itemIndex}][designation]" required class="w-full px-2 py-1 border border-gray-300 rounded text-sm" id="designation_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="items[${itemIndex}][quantity]" value="1" required class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateTotal()">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="items[${itemIndex}][unit_price]" value="0" required class="w-24 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateTotal()" id="price_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="items[${itemIndex}][tax_rate]" value="20.00" required class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateTotal()">
        </td>
        <td class="px-4 py-3">
            ${window.purchaseLineStockHtml(itemIndex)}
        </td>
        <td class="px-4 py-3">
            ${window.discountRowHtml(itemIndex)}
        </td>
        <td class="px-4 py-3">
            <button type="button" onclick="removeItem(this)" class="text-red-600 hover:text-red-800">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </td>
    `;
    tbody.insertBefore(row, tbody.firstChild);

    window.initCommercialProductSelect('#product_select_' + itemIndex, itemIndex);
    var defaultWarehouse = document.querySelector('select[name="warehouse_id"]');
    if (defaultWarehouse && defaultWarehouse.value) {
        var lineWh = row.querySelector('.purchase-line-warehouse');
        if (lineWh) {
            lineWh.value = defaultWarehouse.value;
            window.purchaseLineWarehouseChanged(itemIndex);
        }
    }

    itemIndex++;
    calculateCommercialTotal();
}

function removeItem(button) {
    button.closest('tr').remove();
    calculateCommercialTotal();
}

@if(!empty($prefill['items']))
document.addEventListener('DOMContentLoaded', function () {
    var prefillItems = @json($prefill['items']);
    prefillItems.forEach(function (item) {
        addItem();
        var idx = itemIndex - 1;
        var set = function (sel, val) { var el = document.querySelector(sel); if (el && val != null) el.value = val; };
        set('[name="items[' + idx + '][product_id]"]', item.product_id);
        set('[name="items[' + idx + '][ref]"]', item.ref);
        set('[name="items[' + idx + '][designation]"]', item.designation);
        set('[name="items[' + idx + '][quantity]"]', item.quantity);
        set('[name="items[' + idx + '][unit_price]"]', item.unit_price);
        set('[name="items[' + idx + '][tax_rate]"]', item.tax_rate);
        calculateCommercialTotal();
    });
});
@endif
</script>
@endpush
@endsection
