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
                    <p class="text-gray-900 font-medium">{{ $supplierInvoice->stock_location }}</p>
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
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remise</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($supplierInvoice->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->ref ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-gray-900">{{ $item->designation }}</div>
                                @if($item->description)
                                <div class="text-sm text-gray-500">{{ $item->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->tax_rate }}%</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($item->discount, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right">{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($supplierInvoice->remarks)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Remarques</h3>
            <p class="text-gray-700 whitespace-pre-wrap">{{ $supplierInvoice->remarks }}</p>
        </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="max-w-md ml-auto">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Sous-total</span>
                    <span class="text-lg font-semibold">{{ number_format($supplierInvoice->subtotal, 2) }}</span>
                </div>
                @if($supplierInvoice->discount > 0)
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Remise</span>
                    <span class="text-lg font-semibold">{{ number_format($supplierInvoice->discount, 2) }}</span>
                </div>
                @endif
                @if($supplierInvoice->adjustment != 0)
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600">Ajustement</span>
                    <span class="text-lg font-semibold">{{ number_format($supplierInvoice->adjustment, 2) }}</span>
                </div>
                @endif
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <div class="flex justify-between items-center">
                        <span class="text-base font-semibold text-gray-900">Total TTC</span>
                        <span class="text-2xl font-bold text-blue-600">{{ number_format($supplierInvoice->total, 2) }} {{ $supplierInvoice->currency }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
