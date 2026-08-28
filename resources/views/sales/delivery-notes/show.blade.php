@extends('layouts.with-sidebar')

@section('title', 'Détails du bon de livraison')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Bon de livraison {{ $deliveryNote->delivery_number }}</h2>
                <p class="text-sm text-gray-600 mt-1">Détails du bon de livraison</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-libromart-pdf-actions
                    :print-route="route('delivery-notes.print', $deliveryNote)"
                    :pdf-route="route('delivery-notes.pdf', $deliveryNote)"
                />
                <a href="{{ route('delivery-notes.edit', $deliveryNote) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                    Modifier
                </a>
                <a href="{{ route('delivery-notes.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-150">
                    Retour à la liste
                </a>
            </div>
        </div>
    </header>

    <div class="p-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <!-- Client Information -->
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations Client</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Client</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $deliveryNote->client->name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Date de livraison</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $deliveryNote->delivery_date->format('d/m/Y') }}</p>
                    </div>
                    @if($deliveryNote->shipping_date)
                    <div>
                        <label class="text-sm font-medium text-gray-500">Date d'expédition</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $deliveryNote->shipping_date->format('d/m/Y') }}</p>
                    </div>
                    @endif
                    <div>
                        <label class="text-sm font-medium text-gray-500">Devise</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $deliveryNote->currency }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Statut</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $deliveryNote->status }}</p>
                    </div>
                    @if($deliveryNote->model)
                    <div>
                        <label class="text-sm font-medium text-gray-500">Modèle</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $deliveryNote->model }}</p>
                    </div>
                    @endif
                    @if($deliveryNote->matricule)
                    <div>
                        <label class="text-sm font-medium text-gray-500">Matricule</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $deliveryNote->matricule }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Items Table -->
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Articles</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Réf</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">TVA (%)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Remise</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($deliveryNote->items as $item)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item->ref ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item->designation }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ $item->quantity }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ $item->tax_rate }}%</td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ number_format($item->discount, 2) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 text-right">{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Totals -->
            <div class="p-6 bg-gray-50">
                <x-document-tax-totals :document="$deliveryNote" :items="$deliveryNote->items" />
            </div>

            <!-- Remarks and Conditions -->
            @if($deliveryNote->remarks || $deliveryNote->conditions)
            <div class="p-6 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($deliveryNote->remarks)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Remarques</h3>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $deliveryNote->remarks }}</p>
                    </div>
                    @endif
                    @if($deliveryNote->conditions)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Conditions</h3>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $deliveryNote->conditions }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <div class="p-6 border-t border-gray-200">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Document importé / pièce jointe</h3>
                <x-managed-document-actions type="delivery-notes" :id="$deliveryNote->id" category="signed" label="BL signé" />
            </div>
        </div>
    </div>
</main>
@endsection
