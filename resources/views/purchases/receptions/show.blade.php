@extends('layouts.with-sidebar')

@section('title', 'Détails du bon de réception')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Bon de réception {{ $reception->reception_number }}</h2>
                <p class="text-sm text-gray-600 mt-1">Détails du bon de réception</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('receptions.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition duration-150">
                    Retour à la liste
                </a>
                <a href="{{ route('receptions.edit', $reception) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                    Modifier
                </a>
                <form action="{{ route('receptions.destroy', $reception) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce bon de réception ?');">
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
                    <p class="text-gray-900 font-medium">{{ $reception->supplier->name ?? 'N/A' }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Numéro de réception</label>
                    <p class="text-gray-900 font-medium">{{ $reception->reception_number }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Devise</label>
                    <p class="text-gray-900 font-medium">{{ $reception->currency }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Date de réception</label>
                    <p class="text-gray-900 font-medium">{{ $reception->reception_date->format('d/m/Y') }}</p>
                </div>

                @if($reception->delivery_date)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Date de livraison</label>
                    <p class="text-gray-900 font-medium">{{ $reception->delivery_date->format('d/m/Y') }}</p>
                </div>
                @endif

                @if($reception->reference)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Référence</label>
                    <p class="text-gray-900 font-medium">{{ $reception->reference }}</p>
                </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Statut</label>
                    <p class="text-gray-900 font-medium">{{ $reception->status }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Emplacement du stock</label>
                    <p class="text-gray-900 font-medium">{{ $reception->stock_location }}</p>
                </div>

                @if($reception->model)
                <div>
                    <label class="block text-sm font-medium text-gray-500 mb-1">Modèle</label>
                    <p class="text-gray-900 font-medium">{{ $reception->model }}</p>
                </div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Articles</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Réf</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Désignation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taxe</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($reception->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->ref ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $item->designation }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->tax_rate }}%</td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($reception->remarks)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Remarques</h3>
            <p class="text-gray-700 whitespace-pre-wrap">{{ $reception->remarks }}</p>
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="max-w-md ml-auto">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Sous-total</span>
                    <span class="text-lg font-semibold">{{ number_format($reception->subtotal, 2) }}</span>
                </div>
                @if($reception->discount > 0)
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Remise</span>
                    <span class="text-lg font-semibold">{{ number_format($reception->discount, 2) }}</span>
                </div>
                @endif
                @if($reception->adjustment != 0)
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Ajustement</span>
                    <span class="text-lg font-semibold">{{ number_format($reception->adjustment, 2) }}</span>
                </div>
                @endif
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <div class="flex justify-between items-center">
                        <span class="text-base font-semibold text-gray-900">Total TTC</span>
                        <span class="text-2xl font-bold text-blue-600">{{ number_format($reception->total, 2) }} {{ $reception->currency }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
