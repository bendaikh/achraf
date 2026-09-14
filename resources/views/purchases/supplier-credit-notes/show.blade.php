@extends('layouts.with-sidebar')

@section('title', 'Détails de l\'avoir fournisseur')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Avoir Fournisseur {{ $supplierCreditNote->credit_note_number }}</h2>
                <p class="text-sm text-gray-600 mt-1 flex items-center gap-2 flex-wrap">
                    <span>Détails de l'avoir fournisseur</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $supplierCreditNote->consumptionStatusBadgeClass() }}">
                        {{ $supplierCreditNote->consumptionStatusLabel() }}
                    </span>
                </p>
            </div>
            <div class="flex gap-2">
                <x-libromart-pdf-actions
                    :print-route="route('supplier-credit-notes.print', $supplierCreditNote)"
                    :pdf-route="route('supplier-credit-notes.pdf', $supplierCreditNote)"
                />
                <a href="{{ route('supplier-credit-notes.edit', $supplierCreditNote) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150">
                    Modifier
                </a>
                <a href="{{ route('supplier-credit-notes.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Retour à la liste
                </a>
                <form action="{{ route('supplier-credit-notes.destroy', $supplierCreditNote) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet avoir ?');">
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
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <p class="text-sm text-red-700">{{ session('error') }}</p>
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <ul class="text-sm text-red-700 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Fournisseur</label>
                    <p class="text-gray-900 font-medium">{{ $supplierCreditNote->supplier->name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Numéro d'avoir</label>
                    <p class="text-gray-900 font-medium">{{ $supplierCreditNote->credit_note_number }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Devise</label>
                    <p class="text-gray-900 font-medium">{{ $supplierCreditNote->currency }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Date d'avoir</label>
                    <p class="text-gray-900 font-medium">{{ $supplierCreditNote->credit_note_date->format('d/m/Y') }}</p>
                </div>

                @if($supplierCreditNote->invoice)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Facture liée</label>
                    <p class="text-gray-900 font-medium">{{ $supplierCreditNote->invoice }}</p>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Emplacement du stock</label>
                    <p class="text-gray-900 font-medium">{{ $supplierCreditNote->stock_location }}</p>
                </div>

                @if($supplierCreditNote->model)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Modèle</label>
                    <p class="text-gray-900 font-medium">{{ $supplierCreditNote->model }}</p>
                </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Affectation de l'avoir</h3>
            <div class="grid sm:grid-cols-3 gap-4 text-sm mb-4">
                <div><p class="text-gray-500">Montant avoir</p><p class="font-semibold">{{ number_format($supplierCreditNote->total, 2) }} DH</p></div>
                <div><p class="text-gray-500">Affecté</p><p class="font-semibold">{{ number_format($supplierCreditNote->amount_applied, 2) }} DH</p></div>
                <div><p class="text-gray-500">Disponible</p><p class="font-semibold text-emerald-700">{{ number_format($supplierCreditNote->amount_available, 2) }} DH</p></div>
            </div>
            @if($supplierCreditNote->isManuallyConsumed())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm space-y-2 mb-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <p class="font-semibold text-red-800">Marqué comme déjà consommé (reprise historique)</p>
                        <form action="{{ route('supplier-credit-notes.unmark-consumed', $supplierCreditNote) }}" method="POST" onsubmit="return confirm('Remettre cet avoir comme disponible au paiement ?');">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-red-700 underline">Annuler le marquage</button>
                        </form>
                    </div>
                    <dl class="grid sm:grid-cols-2 gap-2 text-red-900">
                        <div>
                            <dt class="text-red-700">Date de consommation</dt>
                            <dd class="font-medium">{{ $supplierCreditNote->manually_consumed_date?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-red-700">Utilisé par</dt>
                            <dd class="font-medium">{{ $supplierCreditNote->manuallyConsumedBy?->name ?? '—' }}</dd>
                        </div>
                        @if($supplierCreditNote->manually_consumed_note)
                            <div class="sm:col-span-2">
                                <dt class="text-red-700">Motif</dt>
                                <dd class="font-medium whitespace-pre-wrap">{{ $supplierCreditNote->manually_consumed_note }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
            @if($supplierCreditNote->allocations->isNotEmpty())
                <ul class="text-sm space-y-1">
                    @foreach($supplierCreditNote->allocations as $allocation)
                        <li>→ Affecté à facture
                            <a class="text-[#0a5d8a]" href="{{ route('supplier-invoices.show', $allocation->supplier_invoice_id) }}">{{ $allocation->invoice?->invoice_number }}</a>
                            : {{ number_format($allocation->amount, 2) }} DH
                        </li>
                    @endforeach
                </ul>
            @elseif(! $supplierCreditNote->isManuallyConsumed())
                <p class="text-sm text-gray-500">Cet avoir est disponible sur le compte fournisseur et pourra être sélectionné lors d’un règlement.</p>
            @endif
        </div>

        @if(! $supplierCreditNote->isManuallyConsumed() && $supplierCreditNote->amount_available > 0.009)
            <div id="mark-consumed" class="bg-white rounded-xl shadow-sm border border-amber-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Marquer comme déjà consommé</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Pour la reprise d’historique : si cet avoir a déjà été utilisé sur une ancienne facture absente de Libromart,
                    marquez-le ici. Il restera dans l’historique mais ne sera plus proposé au paiement.
                </p>
                <form method="POST" action="{{ route('supplier-credit-notes.mark-consumed', $supplierCreditNote) }}" class="grid sm:grid-cols-2 gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium mb-1">Date de consommation *</label>
                        <input type="date" name="manually_consumed_date" value="{{ old('manually_consumed_date', date('Y-m-d')) }}" required class="w-full rounded-lg border-gray-300">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium mb-1">Motif / note</label>
                        <textarea name="manually_consumed_note" rows="2" class="w-full rounded-lg border-gray-300" placeholder="Ex. : Utilisé sur ancienne facture non saisie dans Libromart">{{ old('manually_consumed_note') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700"
                                onclick="return confirm('Confirmer : cet avoir ne pourra plus être déduit d’un nouveau règlement ?');">
                            Marquer comme déjà consommé
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Réf</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire (TTC)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taxe (%)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remise</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($supplierCreditNote->items as $item)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $item->ref ?? '-' }}</td>
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

        @if($supplierCreditNote->remarks)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Remarques</h3>
            <p class="text-gray-700 whitespace-pre-wrap">{{ $supplierCreditNote->remarks }}</p>
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="max-w-md ml-auto">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Sous-total</span>
                    <span class="text-lg font-semibold">{{ number_format($supplierCreditNote->subtotal, 2) }}</span>
                </div>
                @if($supplierCreditNote->discount > 0)
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Remise</span>
                    <span class="text-lg font-semibold">{{ number_format($supplierCreditNote->discount, 2) }}</span>
                </div>
                @endif
                @if($supplierCreditNote->adjustment != 0)
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Ajustement</span>
                    <span class="text-lg font-semibold">{{ number_format($supplierCreditNote->adjustment, 2) }}</span>
                </div>
                @endif
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <div class="flex justify-between items-center">
                        <span class="text-base font-semibold text-gray-900">Total TTC</span>
                        <span class="text-2xl font-bold text-blue-600">{{ number_format($supplierCreditNote->total, 2) }} {{ $supplierCreditNote->currency }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Document importé / pièce jointe</h3>
            <x-managed-document-actions type="supplier-credit-notes" :id="$supplierCreditNote->id" />
        </div>
    </div>
</main>
@endsection
