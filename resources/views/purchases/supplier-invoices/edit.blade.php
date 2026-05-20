@extends('layouts.with-sidebar')

@section('title', 'Modifier une facture')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Modifier une facture</h2>
                    <p class="text-sm text-gray-600 mt-1">Modifier la facture fournisseur {{ $supplierInvoice->invoice_number }}</p>
                </div>
                <a href="{{ route('supplier-invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
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

            @if($errors->any())
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <h3 class="text-sm font-semibold text-red-700 mb-2">Erreurs de validation:</h3>
                    <ul class="list-disc list-inside text-sm text-red-700">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('supplier-invoices.update', $supplierInvoice) }}" method="POST" id="invoiceForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur *</label>
                            <div class="flex gap-2">
                                <select name="supplier_id" required class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Sélectionner un fournisseur</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ $supplierInvoice->supplier_id == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" onclick="openSupplierModal()" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150" title="Créer un nouveau fournisseur">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Numéro de facture *</label>
                            <input type="text" name="invoice_number" value="{{ old('invoice_number', $supplierInvoice->invoice_number) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Saisir le numéro de facture fournisseur">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Devise *</label>
                            <input type="text" name="currency" value="{{ old('currency', $supplierInvoice->currency) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date de facture *</label>
                            <input type="date" name="invoice_date" value="{{ old('invoice_date', $supplierInvoice->invoice_date->format('Y-m-d')) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date d'échéance</label>
                            <input type="date" name="due_date" value="{{ old('due_date', $supplierInvoice->due_date?->format('Y-m-d')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Emplacement du stock *</label>
                            <select name="stock_location" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="Stock magasin" {{ $supplierInvoice->stock_location == 'Stock magasin' ? 'selected' : '' }}>Stock magasin</option>
                                <option value="Stock en ligne" {{ $supplierInvoice->stock_location == 'Stock en ligne' ? 'selected' : '' }}>Stock en ligne</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact commercial</label>
                            <input type="text" name="commercial_contact" value="{{ old('commercial_contact', $supplierInvoice->commercial_contact) }}" placeholder="Achraf Qassoudi" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Modèle</label>
                            <input type="text" name="model" value="{{ old('model', $supplierInvoice->model) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Importer la facture fournisseur</label>
                            <input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="mt-1 text-xs text-gray-500">PDF, JPG, JPEG ou PNG (Max: 10MB)</p>
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
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
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
                            <textarea name="remarks" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ old('remarks', $supplierInvoice->remarks) }}</textarea>
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
                    <button type="submit" id="submitBtn" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 font-medium">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </main>

@push('scripts')
<script>
function openSupplierModal() {
    if (confirm('Voulez-vous créer un nouveau fournisseur? Vous serez redirigé vers la page de création.')) {
        window.open('{{ route("suppliers.create") }}', '_blank');
    }
}
</script>
<script>
var itemIndex = 0;
var products = @json($products);

function addItemWithData(data) {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-200';
    row.innerHTML = `
        <td class="px-4 py-3">
            <select name="items[${itemIndex}][product_id]" onchange="fillProductDetails(this, ${itemIndex})" class="product-select w-full px-2 py-1 border border-gray-300 rounded text-sm" id="product_select_${itemIndex}">
                <option value="">Rechercher un produit...</option>
                ${products.map(p => `<option value="${p.id}" ${p.id == data.product_id ? 'selected' : ''} data-ref="${p.ref || ''}" data-name="${p.name}" data-price-ht="${p.cost_price_ht || p.sale_price_ht || 0}" data-price-ttc="${p.sale_price || 0}">${p.name} ${p.ref ? '(' + p.ref + ')' : ''}</option>`).join('')}
            </select>
        </td>
        <td class="px-4 py-3">
            <input type="text" name="items[${itemIndex}][ref]" value="${data.ref || ''}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" id="ref_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="text" name="items[${itemIndex}][designation]" value="${data.designation || ''}" required class="w-full px-2 py-1 border border-gray-300 rounded text-sm" id="designation_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="items[${itemIndex}][quantity]" value="${data.quantity || 1}" required class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateTotal()">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="items[${itemIndex}][unit_price]" value="${data.unit_price || 0}" required class="w-24 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateTotal()" id="price_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="items[${itemIndex}][tax_rate]" value="${data.tax_rate || 20}" required class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateTotal()">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="items[${itemIndex}][discount]" value="${data.discount || 0}" class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateTotal()">
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
    
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#product_select_' + itemIndex).select2({
            placeholder: 'Rechercher un produit...',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() { return "Aucun produit trouvé"; },
                searching: function() { return "Recherche..."; }
            }
        });
    }
    
    itemIndex++;
    calculateTotal();
}

function addItem() {
    addItemWithData({});
}

function addItemOld() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-200';
    row.innerHTML = `
        <td class="px-4 py-3">
            <select name="items[${itemIndex}][product_id]" onchange="fillProductDetails(this, ${itemIndex})" class="product-select w-full px-2 py-1 border border-gray-300 rounded text-sm" id="product_select_${itemIndex}">
                <option value="">Rechercher un produit...</option>
                ${products.map(p => `<option value="${p.id}" data-ref="${p.ref || ''}" data-name="${p.name}" data-price-ht="${p.cost_price_ht || p.sale_price_ht || 0}" data-price-ttc="${p.sale_price || 0}">${p.name} ${p.ref ? '(' + p.ref + ')' : ''}</option>`).join('')}
            </select>
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
            <input type="number" step="0.01" name="items[${itemIndex}][discount]" value="0" class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateTotal()">
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

function fillProductDetails(selectElement, index) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    if (selectedOption.value) {
        const ref = selectedOption.getAttribute('data-ref');
        const name = selectedOption.getAttribute('data-name');
        const priceHT = parseFloat(selectedOption.getAttribute('data-price-ht')) || 0;
        const priceTTC = parseFloat(selectedOption.getAttribute('data-price-ttc')) || 0;
        const taxRateInput = document.querySelector(`[name="items[${index}][tax_rate]"]`);
        const taxRate = parseFloat(taxRateInput?.value) || 20;
        
        // Use HT price if available, otherwise calculate from TTC
        let unitPrice = priceHT;
        if (unitPrice === 0 && priceTTC > 0) {
            unitPrice = priceTTC / (1 + taxRate / 100);
        }

        document.getElementById('ref_' + index).value = ref;
        document.getElementById('designation_' + index).value = name;
        document.getElementById('price_' + index).value = unitPrice.toFixed(2);

        calculateTotal();
    }
}

function removeItem(button) {
    button.closest('tr').remove();
    calculateTotal();
}

function calculateTotal() {
    const rows = document.querySelectorAll('#itemsBody tr');
    let totalHT = 0;
    let totalDiscount = 0;
    let totalTax = 0;
    
    rows.forEach(row => {
        const quantity = parseFloat(row.querySelector('[name*="[quantity]"]').value) || 0;
        const unitPrice = parseFloat(row.querySelector('[name*="[unit_price]"]').value) || 0;
        const taxRate = parseFloat(row.querySelector('[name*="[tax_rate]"]').value) || 0;
        const discount = parseFloat(row.querySelector('[name*="[discount]"]').value) || 0;
        
        let lineHT = quantity * unitPrice;
        lineHT -= discount;
        const lineTax = lineHT * (taxRate / 100);
        
        totalHT += lineHT;
        totalDiscount += discount;
        totalTax += lineTax;
    });
    
    const totalTTC = totalHT + totalTax;
    
    document.getElementById('subtotal').textContent = totalHT.toFixed(2);
    document.getElementById('discount').textContent = totalDiscount.toFixed(2);
    document.getElementById('taxAmount').textContent = totalTax.toFixed(2);
    document.getElementById('total').textContent = totalTTC.toFixed(2);
}

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    @if($supplierInvoice->items->count() > 0)
        @foreach($supplierInvoice->items as $item)
            addItemWithData({
                product_id: '{{ $item->product_id }}',
                ref: '{{ $item->ref }}',
                designation: '{{ $item->designation }}',
                quantity: {{ $item->quantity }},
                unit_price: {{ $item->unit_price }},
                tax_rate: {{ $item->tax_rate }},
                discount: {{ $item->discount }}
            });
        @endforeach
    @else
        addItem();
    @endif
    
    document.getElementById('invoiceForm').addEventListener('submit', function(e) {
        const itemRows = document.querySelectorAll('#itemsBody tr');
        if (itemRows.length === 0) {
            e.preventDefault();
            alert('Veuillez ajouter au moins un article à la facture.');
            return false;
        }
    });
});
</script>
@endpush
@endsection
