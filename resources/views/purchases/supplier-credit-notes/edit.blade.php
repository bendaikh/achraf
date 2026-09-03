@extends('layouts.with-sidebar')

@section('title', 'Modifier un avoir fournisseur')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Modifier un avoir fournisseur</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $supplierCreditNote->credit_note_number }}</p>
                </div>
                <a href="{{ route('supplier-credit-notes.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
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

            <form action="{{ route('supplier-credit-notes.update', $supplierCreditNote) }}" method="POST" id="creditNoteForm">
                @csrf
                @method('PUT')

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur *</label>
                            <x-supplier-select-with-create :suppliers="$suppliers" :selected-id="$supplierCreditNote->supplier_id" />
                        </div>

                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Avoir Numéro *</label>
                            <input type="text" name="credit_note_number" value="{{ old('credit_note_number', $supplierCreditNote->credit_note_number) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Saisir le numéro d'avoir fournisseur">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Devise</label>
                            <input type="text" name="currency" value="{{ old('currency', $supplierCreditNote->currency) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Facture</label>
                            <select name="invoice" id="supplier_invoice_select" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">AUCUNE SELECTION</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                            <input type="date" name="credit_note_date" value="{{ old('credit_note_date', $supplierCreditNote->credit_note_date->format('Y-m-d')) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Emplacement du stock</label>
                            <input type="text" name="stock_location" value="{{ old('stock_location', $supplierCreditNote->stock_location) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Articles</h3>
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
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire (TTC)</th>
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
                            <textarea name="remarks" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ old('remarks', $supplierCreditNote->remarks) }}</textarea>
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
var existingItems = @json($existingItems);
var currentInvoiceLabel = @json(old('invoice', $supplierCreditNote->invoice));

function addItemWithData(data) {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-200';
    row.innerHTML = `
        <td class="px-4 py-3">
            ${window.commercialProductSelectHtml(itemIndex, window.selectedCommercialProduct(data))}
        </td>
        <td class="px-4 py-3">
            <input type="text" name="items[${itemIndex}][ref]" value="${data.ref || ''}" class="w-full px-2 py-1 border border-gray-300 rounded text-sm" id="ref_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="text" name="items[${itemIndex}][designation]" value="${data.designation || ''}" required class="w-full px-2 py-1 border border-gray-300 rounded text-sm" id="designation_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="number" name="items[${itemIndex}][quantity]" value="${data.quantity || 1}" required class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateCommercialTotal()">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="items[${itemIndex}][unit_price]" value="${data.unit_price || 0}" required class="w-24 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateCommercialTotal()" id="price_${itemIndex}">
        </td>
        <td class="px-4 py-3">
            <input type="number" step="0.01" name="items[${itemIndex}][tax_rate]" value="${data.tax_rate ?? 20}" required class="w-20 px-2 py-1 border border-gray-300 rounded text-sm" onchange="calculateCommercialTotal()">
        </td>
        <td class="px-4 py-3">
            ${window.discountRowHtmlWithData(itemIndex, data)}
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
    window.initCommercialProductSelect('#product_select_' + itemIndex, itemIndex, window.selectedCommercialProduct(data));
    itemIndex++;
    calculateCommercialTotal();
}

function addItem() {
    addItemWithData({});
}

function removeItem(button) {
    button.closest('tr').remove();
    calculateCommercialTotal();
}

function loadSupplierInvoices(supplierId, selectedLabel) {
    const select = document.getElementById('supplier_invoice_select');
    select.innerHTML = '<option value="">AUCUNE SELECTION</option>';
    if (!supplierId) {
        return;
    }

    fetch(@json(route('supplier-invoices.by-supplier', ['supplier' => '__PARTY__'])).replace('__PARTY__', supplierId))
        .then(r => r.json())
        .then(data => {
            (data.invoices || []).forEach(inv => {
                const opt = document.createElement('option');
                opt.value = inv.label;
                opt.textContent = inv.label;
                if (selectedLabel && selectedLabel === inv.label) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });

            if (selectedLabel && !Array.from(select.options).some(o => o.value === selectedLabel)) {
                const opt = document.createElement('option');
                opt.value = selectedLabel;
                opt.textContent = selectedLabel;
                opt.selected = true;
                select.appendChild(opt);
            }
        });
}

SoftNav.whenReady(function() {
    if (typeof window.initSupplierSelect2 === 'function') {
        window.initSupplierSelect2('#supplier_id');
    }

    if (window.jQuery) {
        $('#supplier_id').on('change', function() {
            loadSupplierInvoices(this.value, null);
        });
    }

    const supplierSelect = document.getElementById('supplier_id');
    if (supplierSelect && supplierSelect.value) {
        loadSupplierInvoices(supplierSelect.value, currentInvoiceLabel);
    }

    if (existingItems.length > 0) {
        existingItems.forEach(function (item) {
            addItemWithData(item);
        });
    } else {
        addItem();
    }

    document.getElementById('creditNoteForm').addEventListener('submit', function (e) {
        const itemRows = document.querySelectorAll('#itemsBody tr');
        if (itemRows.length === 0) {
            e.preventDefault();
            alert('Veuillez ajouter au moins un article à l\'avoir.');
        }
    });
});
</script>
@endpush
@endsection
