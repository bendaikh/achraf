@php
    $expense = $expense ?? null;
    $showSupplier = $showSupplier ?? false;
    $showInvoiceFile = $showInvoiceFile ?? false;
    $canManageRecurrence = ! $expense || $expense->recurrence_parent_id === null;
    $recurringValue = (bool) old('is_recurring', $expense?->is_recurring ?? false);
    $frequencyValue = old('recurrence_frequency', $expense?->recurrence_frequency ?? 'monthly');
    $noEndValue = (bool) old('recurrence_no_end', $expense ? ! $expense->recurrence_end_date : true);
    $startDateValue = old(
        'recurrence_start_date',
        $expense?->isRecurrenceTemplate()
            ? $expense->next_due_date?->format('Y-m-d')
            : $expense?->recurrence_start_date?->format('Y-m-d')
    );
@endphp

@if($errors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        <ul class="list-disc pl-5 space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div
    x-data="{ recurring: @js($recurringValue), frequency: @js($frequencyValue), noEnd: @js($noEndValue) }"
>
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
    <div class="min-w-0">
        <label class="block text-sm font-medium text-gray-700 mb-2">Fournisseur</label>
        <x-supplier-select-with-create
            :suppliers="$suppliers"
            :selected-id="old('supplier_id', $expense?->supplier_id)"
            :required="false"
        />
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
        <label class="block text-sm font-medium text-gray-700 mb-2">Documents</label>
        @if($expense)
            <x-managed-document-actions
                :type="$expense->expense_type === 'without_invoice' ? 'expenses-without-invoice' : 'expenses-with-invoice'"
                :id="$expense->id"
            />
            <p class="mt-2 text-xs text-gray-500">Vous pouvez aussi joindre un fichier supplémentaire ci-dessous.</p>
        @endif
        <input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
        <p class="mt-1 text-xs text-gray-500">⬆️ Téléverser un fichier (PDF, JPG, PNG). Après enregistrement, 🖨️ Scanner en PDF est disponible dans la liste.</p>
    </div>
    @endif
</div>

@if($canManageRecurrence)
    <div class="mt-6 pt-6 border-t border-gray-200">
        <input type="hidden" name="is_recurring" value="0">
        <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 bg-gray-50 cursor-pointer">
            <span>
                <span class="block text-sm font-semibold text-gray-900">Cette dépense est récurrente</span>
                <span class="block text-xs text-gray-500 mt-1">Les prochaines échéances seront préparées automatiquement, sans être marquées comme payées.</span>
            </span>
            <input type="checkbox" name="is_recurring" value="1" x-model="recurring" class="h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500">
        </label>

        <div x-show="recurring" x-cloak class="mt-4 p-5 rounded-xl border border-green-200 bg-green-50/50">
            <h3 class="text-sm font-semibold text-green-900 mb-4">Récurrence</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fréquence *</label>
                    <select name="recurrence_frequency" x-model="frequency" :required="recurring" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                        @foreach(\App\Models\Expense::FREQUENCIES as $value => $label)
                            <option value="{{ $value }}" @selected($frequencyValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tous les X cycles *</label>
                    <input type="number" min="1" max="999" name="recurrence_interval" value="{{ old('recurrence_interval', $expense?->recurrence_interval ?? 1) }}" :required="recurring" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>

                <div x-show="frequency === 'custom'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Unité *</label>
                    <select name="recurrence_interval_unit" :required="recurring && frequency === 'custom'" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                        <option value="day" @selected(old('recurrence_interval_unit', $expense?->recurrence_interval_unit) === 'day')>Jour(s)</option>
                        <option value="month" @selected(old('recurrence_interval_unit', $expense?->recurrence_interval_unit ?? 'month') === 'month')>Mois</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date de début *</label>
                    <input type="date" name="recurrence_start_date" value="{{ $startDateValue }}" :required="recurring" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    @if($expense?->isRecurrenceTemplate())
                        <p class="text-xs text-gray-500 mt-1">Date d’effet de la modification future.</p>
                    @endif
                </div>

                <div x-show="!noEnd">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date de fin *</label>
                    <input type="date" name="recurrence_end_date" value="{{ old('recurrence_end_date', $expense?->recurrence_end_date?->format('Y-m-d')) }}" :required="recurring && !noEnd" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>

                <div class="flex items-end pb-2">
                    <input type="hidden" name="recurrence_no_end" value="0">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" name="recurrence_no_end" value="1" x-model="noEnd" class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                        Sans date de fin
                    </label>
                </div>
            </div>
        </div>
    </div>
@elseif($expense?->is_recurring)
    <div class="mt-6 p-4 rounded-xl border border-blue-200 bg-blue-50 text-sm text-blue-800">
        Cette dépense est une occurrence de la récurrence « {{ $expense->recurrenceLabel() }} ». Sa modification ne change pas les autres échéances.
    </div>
@endif
</div>
