@props(['existing' => []])

@php
    $existingAdjustments = collect($existing)->map(function ($row) {
        return [
            'label' => is_array($row) ? ($row['label'] ?? '') : ($row->label ?? ''),
            'type' => is_array($row) ? ($row['type'] ?? 'add') : ($row->type ?? 'add'),
            'amount' => (float) (is_array($row) ? ($row['amount'] ?? 0) : $row->amount),
            'is_taxable' => (bool) (is_array($row) ? ($row['is_taxable'] ?? false) : $row->is_taxable),
            'tax_rate' => (float) (is_array($row) ? ($row['tax_rate'] ?? 20) : ($row->tax_rate ?? 20)),
        ];
    })->values();
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6" id="adjustmentsSection">
    <div class="flex items-center justify-between mb-2">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Frais / Ajustements</h3>
            <p class="text-xs text-gray-500 mt-1">Modifient le montant de la facture. Ce ne sont pas des paiements.</p>
        </div>
        <button type="button" id="addAdjustmentBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
            + Ajouter
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full" id="adjustmentsTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Libellé</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type d’ajustement</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TVA applicable</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody id="adjustmentsBody"></tbody>
        </table>
    </div>
</div>
<script>
window.existingInvoiceAdjustments = @json($existingAdjustments);
</script>
