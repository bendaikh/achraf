@extends('layouts.with-sidebar')

@section('title', 'Détails de la facture fournisseur')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Facture Fournisseur {{ $supplierInvoice->invoice_number }}</h2>
                <p class="text-sm text-gray-600 mt-1">Détails de la facture fournisseur</p>
            </div>
            <div class="flex gap-2">
                <x-libromart-pdf-actions
                    :print-route="route('supplier-invoices.print', $supplierInvoice)"
                    :pdf-route="route('supplier-invoices.pdf', $supplierInvoice)"
                />
                <a href="{{ route('supplier-invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Retour à la liste
                </a>
                <form action="{{ route('supplier-invoices.destroy', $supplierInvoice) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette facture ?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-150">
                        Supprimer
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="p-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Fournisseur</label>
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->supplier->name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Numéro de facture</label>
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->invoice_number }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Devise</label>
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->currency }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Date de facture</label>
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->invoice_date->format('d/m/Y') }}</p>
                </div>

                @if($supplierInvoice->due_date)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Date d'échéance</label>
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->due_date->format('d/m/Y') }}</p>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Emplacement du stock</label>
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->warehouse?->name ?: $supplierInvoice->stock_location }}</p>
                    @if($supplierInvoice->stock_applied_at)
                        <span class="mt-1 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Stock réceptionné ✓</span>
                        <p class="text-xs text-gray-500 mt-1">{{ $supplierInvoice->stock_applied_at->format('d/m/Y H:i') }}</p>
                    @else
                        <p class="mt-1 text-xs text-amber-700">Stock non encore réceptionné</p>
                    @endif
                </div>

                @if($supplierInvoice->commercial_contact)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Contact commercial</label>
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->commercial_contact }}</p>
                </div>
                @endif

                @if($supplierInvoice->model)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Modèle</label>
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->model }}</p>
                </div>
                @endif

                @if($supplierInvoice->matricule)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Matricule</label>
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->matricule }}</p>
                </div>
                @endif
            </div>

            <div class="mt-6 pt-6 border-t border-gray-200">
                <label class="block text-sm font-medium text-gray-500 mb-2">Documents</label>
                <x-managed-document-actions type="supplier-invoices" :id="$supplierInvoice->id" />
            </div>
        </div>

        @include('purchases.partials.document-chain')

        @include('purchases.partials.reception-progress', [
            'documentLabel' => 'facture',
            'receiveRoute' => ($canReceive ?? false) ? route('receptions.create', ['from' => 'invoice', 'id' => $supplierInvoice->id]) : null,
        ])

        @if($canReceive ?? false)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-6">
            <h3 class="text-lg font-semibold text-amber-900 mb-2">Réception rapide depuis la facture</h3>
            <p class="text-sm text-amber-800 mb-4">Crée automatiquement un bon de réception (BR) — seule entrée physique autorisée en stock.</p>
            <form method="POST" action="{{ route('supplier-invoices.receive-stock', $supplierInvoice) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dépôt défaut</label>
                    <select name="warehouse_id" class="w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        @foreach($warehouses ?? [] as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(($supplierInvoice->warehouse_id ?: optional($warehouses->firstWhere('is_fulfillment_default', true))->id) == $warehouse->id)>
                                {{ $warehouse->isOnline() ? '🟢 ' : '' }}{{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-white/70">
                            <tr>
                                <th class="px-3 py-2 text-left">Produit</th>
                                <th class="px-3 py-2 text-left">Qté à recevoir</th>
                                <th class="px-3 py-2 text-left">Dépôt destination</th>
                                <th class="px-3 py-2 text-left">Emplacement</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receiptProgress ?? [] as $idx => $row)
                                @if(($row['remaining'] ?? 0) <= 0) @continue @endif
                                @php $item = $supplierInvoice->items->first(fn ($i) => (int)($i->product_id ?? 0) === (int)($row['product_id'] ?? 0) && (int)($i->product_variant_id ?? 0) === (int)($row['product_variant_id'] ?? 0)); @endphp
                                <tr class="border-t border-amber-100">
                                    <td class="px-3 py-2">
                                        {{ $row['designation'] }}
                                        <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $row['product_id'] }}">
                                        <input type="hidden" name="items[{{ $idx }}][product_variant_id]" value="{{ $row['product_variant_id'] }}">
                                        <input type="hidden" name="items[{{ $idx }}][quantity]" value="{{ $row['remaining'] }}">
                                    </td>
                                    <td class="px-3 py-2">{{ $row['remaining'] }}</td>
                                    <td class="px-3 py-2">
                                        <select name="items[{{ $idx }}][warehouse_id]" class="w-full px-2 py-1 border rounded invoice-recv-wh" data-idx="{{ $idx }}">
                                            @foreach($warehouses ?? [] as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <select name="items[{{ $idx }}][warehouse_location_id]" id="invoice_recv_loc_{{ $idx }}" class="w-full px-2 py-1 border rounded">
                                            <option value="">—</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700">
                    📦 Créer le BR et réceptionner
                </button>
            </form>
        </div>
        @push('scripts')
        <script>
        (function () {
            var warehouses = @json(($warehouses ?? collect())->map(fn ($w) => [
                'id' => $w->id,
                'locations' => $w->locations->map(fn ($l) => ['id' => $l->id, 'label' => $l->displayLabel()])->values(),
            ])->values());
            function fillLoc(idx, warehouseId) {
                var select = document.getElementById('invoice_recv_loc_' + idx);
                if (!select) return;
                var wh = warehouses.find(function (w) { return String(w.id) === String(warehouseId); });
                select.innerHTML = '<option value="">—</option>';
                (wh && wh.locations || []).forEach(function (loc) {
                    var opt = document.createElement('option');
                    opt.value = loc.id;
                    opt.textContent = loc.label;
                    select.appendChild(opt);
                });
            }
            document.querySelectorAll('.invoice-recv-wh').forEach(function (el) {
                fillLoc(el.dataset.idx, el.value);
                el.addEventListener('change', function () { fillLoc(this.dataset.idx, this.value); });
            });
        })();
        </script>
        @endpush
        @endif

        @isset($trace)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Situation de règlement</h3>
                <a href="{{ route('purchases.payments.settle', ['supplier' => $supplierInvoice->supplier_id, 'invoices' => $supplierInvoice->id]) }}" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Payer</a>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mb-4">
                <div><p class="text-gray-500">Montant facture</p><p class="font-semibold">{{ number_format($trace['total'], 2) }} DH</p></div>
                <div><p class="text-gray-500">Avoir utilisé</p><p class="font-semibold text-emerald-700">- {{ number_format($trace['credits_applied'], 2) }} DH</p></div>
                <div><p class="text-gray-500">Déjà payé</p><p class="font-semibold">{{ number_format($trace['paid'], 2) }} DH</p></div>
                <div><p class="text-gray-500">Solde</p><p class="font-bold text-red-600">{{ number_format($trace['remaining'], 2) }} DH</p></div>
            </div>
            @if($trace['credit_allocations']->isNotEmpty() || $supplierInvoice->payments->isNotEmpty())
                <ul class="text-sm space-y-1 text-gray-700">
                    @foreach($trace['credit_allocations'] as $allocation)
                        <li>→ Avoir {{ $allocation->creditNote?->credit_note_number }} utilisé : {{ number_format($allocation->amount, 2) }} DH</li>
                    @endforeach
                    @foreach($supplierInvoice->payments as $payment)
                        <li>→ {{ $payment->payment_method === 'Chèque' ? 'Chèque CHQ-'.($payment->cheque_number ?: $payment->id) : $payment->payment_method }} : {{ number_format($payment->amount, 2) }} DH</li>
                    @endforeach
                    <li class="font-semibold">→ Solde : {{ number_format($trace['remaining'], 2) }} DH</li>
                </ul>
            @endif
        </div>
        @endisset

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Articles</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Réf</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origine BR/BC</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire (TTC)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taxe (%)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remise</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($supplierInvoice->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->ref ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->source_document_reference ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $item->designation }}</div>
                                @if($item->description)
                                <div class="text-sm text-gray-500">{{ $item->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($item->display_unit_price_ttc, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->tax_rate }}%</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($item->discount, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($supplierInvoice->adjustments->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Frais / Ajustements</h3>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Libellé</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TVA</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($supplierInvoice->adjustments as $adjustment)
                    <tr>
                        <td class="px-4 py-3 text-sm">{{ $adjustment->label }}</td>
                        <td class="px-4 py-3 text-sm">{{ $adjustment->type === 'deduct' ? '− Déduire du total' : '+ Ajouter au total' }}</td>
                        <td class="px-4 py-3 text-sm text-right">{{ number_format($adjustment->amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm">{{ $adjustment->is_taxable ? 'Oui ('.$adjustment->tax_rate.'%)' : 'Non' }}</td>
                        <td class="px-4 py-3 text-sm text-right {{ $adjustment->type === 'deduct' ? 'text-red-600' : 'text-emerald-700' }}">
                            {{ $adjustment->type === 'deduct' ? '-' : '+' }}{{ number_format($adjustment->line_total, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($supplierInvoice->remarks)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Remarques</h3>
            <p class="text-gray-700 whitespace-pre-wrap">{{ $supplierInvoice->remarks }}</p>
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <x-document-tax-totals :document="$supplierInvoice" :items="$supplierInvoice->items" />
        </div>
    </div>
</main>
@endsection
