@php
    $expense = $expense ?? null;
    $showSupplier = $showSupplier ?? false;
    $showInvoiceFile = $showInvoiceFile ?? false;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Désignation *</label>
        <input type="text" name="designation" value="{{ old('designation', $expense?->designation) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Catégorie de dépenses</label>
        <select name="expense_category" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
            <option value="">Sélectionner</option>
            @foreach($expenseCategories as $category)
                <option value="{{ $category }}" @selected(old('expense_category', $expense?->expense_category) === $category)>{{ $category }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Date *</label>
        <input type="date" name="expense_date" value="{{ old('expense_date', $expense?->expense_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Montant *</label>
        <input type="number" step="0.01" name="amount" value="{{ old('amount', $expense?->amount) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Devise</label>
        <input type="text" name="currency" value="{{ old('currency', $expense?->currency ?? 'dh - MAD') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Référence</label>
        <input type="text" name="reference" value="{{ old('reference', $expense?->reference) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>

    @if($showSupplier)
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur</label>
        <select name="supplier_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
            <option value="">Sélectionner</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected(old('supplier_id', $expense?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
    </div>
    @endif

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Mode de règlement</label>
        <select name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
            <option value="">Sélectionner</option>
            @foreach($paymentMethods as $method)
                <option value="{{ $method }}" @selected(old('payment_method', $expense?->payment_method) === $method)>{{ $method }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Compte</label>
        <select name="account" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
            <option value="">Sélectionner</option>
            @foreach($accounts as $account)
                <option value="{{ $account }}" @selected(old('account', $expense?->account) === $account)>{{ $account }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Taxe</label>
        <select name="tax_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
            <option value="NO TAXE" @selected(old('tax_type', $expense?->tax_type ?? 'NO TAXE') === 'NO TAXE')>NO TAXE</option>
            <option value="TVA 20%" @selected(old('tax_type', $expense?->tax_type) === 'TVA 20%')>TVA 20%</option>
        </select>
    </div>

    @if($showInvoiceFile)
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-2">Facture (PDF, JPG, PNG)</label>
        <input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
        @if($expense?->invoice_file_path)
            <p class="text-xs text-gray-500 mt-1">Fichier actuel : {{ basename($expense->invoice_file_path) }}</p>
        @endif
    </div>
    @endif
</div>
