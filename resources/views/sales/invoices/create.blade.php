@extends('layouts.with-sidebar')

@section('title', 'Créer une facture')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Créer une facture</h2>
                    <p class="text-sm text-gray-600 mt-1">Créer une nouvelle facture client</p>
                </div>
                <a href="{{ route('invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
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

            <form action="{{ route('invoices.store') }}" method="POST" id="invoiceForm">
                @csrf
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client *</label>
                            <x-client-select-with-create />
                        </div>

                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Numéro de facture</label>
                            <input type="text" value="{{ $invoiceNumber }}" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Devise</label>
                            <input type="text" name="currency" value="dh - MAD" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date de facture *</label>
                            <input type="date" name="invoice_date" value="{{ date('Y-m-d') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date d'échéance</label>
                            <input type="date" name="due_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Emplacement du stock</label>
                            <input type="text" name="stock_location" value="DEPOT" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact commercial</label>
                            <input type="text" name="commercial_contact" placeholder="Achraf Qassoudi" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Modèle</label>
                            <input type="text" name="model" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Matricule</label>
                            <input type="text" name="matricule" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unit. HT</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taxe (%)</th>
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
</div>

<script>
window.commercialDocConfig = {
    pricesAreTtc: @json($pricesAreTtc ?? false),
    priceMode: 'sale',
    products: @json($products),
};
</script>
@include('partials.commercial-document-form-script')
<script>
$(document).ready(function() {
    initClientSelect2('#client_id');
});

var itemIndex = 0;
var products = commercialDocConfig.products;

function addItem() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-200';
    row.innerHTML = `
        <td class="px-4 py-3">
            <select name="items[${itemIndex}][product_id]" onchange="fillCommercialProductDetails(this, ${itemIndex})" class="product-select w-full px-2 py-1 border border-gray-300 rounded text-sm" id="product_select_${itemIndex}">
                <option value="">Rechercher un produit...</option>
                ${products.map(p => `<option value="${p.id}" data-ref="${p.ref || ''}" data-name="${p.name}" data-vat="${p.vat_category || ''}" data-price-ht="${p.sale_price_ht || 0}" data-price-ttc="${p.sale_price || 0}" data-cost-ht="${p.cost_price_ht || 0}">${p.name} ${p.ref ? '(' + p.ref + ')' : ''}</option>`).join('')}
            </select>
        </td>
        <td class="px-4 py-3">
            <input type="text" name="items[${itemIndex}][ref]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" id="ref_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="text" name="items[${itemIndex}][designation]" required class="w-full px-2 py-1 border border-gray-300 rounded text-sm" id="designation_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="items[${itemIndex}][quantity]" value="1" required class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateCommercialTotal()">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="items[${itemIndex}][unit_price]" value="0" required class="w-24 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateCommercialTotal()" id="price_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="items[${itemIndex}][tax_rate]" value="20.00" required class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateCommercialTotal()">
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
    tbody.appendChild(row);
    
    // Initialize Select2 on the newly added dropdown (if jQuery is loaded)
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#product_select_' + itemIndex).select2({
            placeholder: 'Rechercher un produit...',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() {
                    return "Aucun produit trouvé";
                },
                searching: function() {
                    return "Recherche...";
                }
            }
        });
    }
    
    itemIndex++;
}

function removeItem(button) {
    button.closest('tr').remove();
    calculateCommercialTotal();
}
</script>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
