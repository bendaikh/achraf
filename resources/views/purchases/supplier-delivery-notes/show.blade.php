@extends('layouts.with-sidebar')

@section('title', 'Détails du bon de livraison')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Bon de livraison {{ $supplierDeliveryNote->delivery_number }}</h2>
                <p class="text-sm text-gray-600 mt-1">Détails du bon de livraison fournisseur</p>
            </div>
            <div class="flex gap-2">
                @if($canReceive ?? false)
                    <a href="{{ route('receptions.create', ['from' => 'bl', 'id' => $supplierDeliveryNote->id]) }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition duration-150">
                        📦 Réceptionner ce BL
                    </a>
                @endif
                <x-libromart-pdf-actions
                    :print-route="route('supplier-delivery-notes.print', $supplierDeliveryNote)"
                    :pdf-route="route('supplier-delivery-notes.pdf', $supplierDeliveryNote)"
                />
                <a href="{{ route('supplier-delivery-notes.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Retour à la liste
                </a>
                <a href="{{ route('supplier-delivery-notes.edit', $supplierDeliveryNote) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                    Modifier
                </a>
                <form action="{{ route('supplier-delivery-notes.destroy', $supplierDeliveryNote) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce bon de livraison ?');">
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
        @include('purchases.partials.document-chain')

        @include('purchases.partials.reception-progress', [
            'documentLabel' => 'BL',
            'receiveRoute' => ($canReceive ?? false) ? route('receptions.create', ['from' => 'bl', 'id' => $supplierDeliveryNote->id]) : null,
        ])

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Fournisseur</label>
                    <p class="text-gray-900 font-medium">{{ $supplierDeliveryNote->supplier->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Numéro</label>
                    <p class="text-gray-900 font-medium">{{ $supplierDeliveryNote->delivery_number }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Devise</label>
                    <p class="text-gray-900 font-medium">{{ $supplierDeliveryNote->currency }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Date de livraison</label>
                    <p class="text-gray-900 font-medium">{{ $supplierDeliveryNote->delivery_date->format('d/m/Y') }}</p>
                </div>
                @if($supplierDeliveryNote->expected_reception_date)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Réception prévue</label>
                    <p class="text-gray-900 font-medium">{{ $supplierDeliveryNote->expected_reception_date->format('d/m/Y') }}</p>
                </div>
                @endif
                @if($supplierDeliveryNote->reference)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Référence</label>
                    <p class="text-gray-900 font-medium">{{ $supplierDeliveryNote->reference }}</p>
                </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Statut</label>
                    <p class="text-gray-900 font-medium">{{ $supplierDeliveryNote->status }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Emplacement du stock</label>
                    <p class="text-gray-900 font-medium">{{ $supplierDeliveryNote->stock_location }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Articles du BL</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Réf / SKU</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qté BL</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Réceptionné</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Reste</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dépôt</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($supplierDeliveryNote->items as $item)
                        @php $progress = ($receiptProgress ?? collect())->first(fn ($row) => (int)($row['product_id'] ?? 0) === (int)($item->product_id ?? 0) && (int)($row['product_variant_id'] ?? 0) === (int)($item->product_variant_id ?? 0)); @endphp
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->ref ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $item->designation }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ $progress['received'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-right {{ ($progress['remaining'] ?? $item->quantity) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ $progress['remaining'] ?? $item->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $progress['warehouse'] ?? ($supplierDeliveryNote->warehouse?->name ?: $supplierDeliveryNote->stock_location) }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ ($progress['status'] ?? 'non_receptionne') === 'receptionne' ? 'bg-emerald-100 text-emerald-800' : (($progress['status'] ?? '') === 'partiel' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">{{ $progress['status_label'] ?? 'Non réceptionné' }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($supplierDeliveryNote->remarks)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Remarques</h3>
            <p class="text-gray-700 whitespace-pre-wrap">{{ $supplierDeliveryNote->remarks }}</p>
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Document importé / pièce jointe</h3>
            <x-managed-document-actions type="supplier-delivery-notes" :id="$supplierDeliveryNote->id" />
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <x-document-tax-totals :document="$supplierDeliveryNote" :items="$supplierDeliveryNote->items" />
        </div>
    </div>
</main>
@endsection
