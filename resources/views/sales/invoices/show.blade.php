@extends('layouts.with-sidebar')

@section('title', 'Détails de la facture')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Facture {{ $invoice->invoice_number }}</h2>
                <p class="text-sm text-gray-600 mt-1">Détails de la facture</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('invoices.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-150">
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
                        <p class="text-sm text-gray-900 mt-1">{{ $invoice->client->name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Date de la facture</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $invoice->invoice_date->format('d/m/Y') }}</p>
                    </div>
                    @if($invoice->due_date)
                    <div>
                        <label class="text-sm font-medium text-gray-500">Date d'échéance</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $invoice->due_date->format('d/m/Y') }}</p>
                    </div>
                    @endif
                    <div>
                        <label class="text-sm font-medium text-gray-500">Devise</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $invoice->currency }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Emplacement de stock</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $invoice->stock_location }}</p>
                    </div>
                    @if($invoice->commercial_contact)
                    <div>
                        <label class="text-sm font-medium text-gray-500">Contact commercial</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $invoice->commercial_contact }}</p>
                    </div>
                    @endif
                    @if($invoice->model)
                    <div>
                        <label class="text-sm font-medium text-gray-500">Modèle</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $invoice->model }}</p>
                    </div>
                    @endif
                    @if($invoice->matricule)
                    <div>
                        <label class="text-sm font-medium text-gray-500">Matricule</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $invoice->matricule }}</p>
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
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Prix unitaire</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">TVA (%)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Remise</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($invoice->items as $item)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item->ref ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item->designation }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $item->description ?? '-' }}</td>
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
                <div class="max-w-md ml-auto space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Sous-total:</span>
                        <span class="font-medium text-gray-900">{{ number_format($invoice->subtotal, 2) }} {{ $invoice->currency }}</span>
                    </div>
                    @if($invoice->discount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Remise:</span>
                        <span class="font-medium text-gray-900">{{ number_format($invoice->discount, 2) }} {{ $invoice->currency }}</span>
                    </div>
                    @endif
                    @if($invoice->adjustment != 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Ajustement:</span>
                        <span class="font-medium text-gray-900">{{ number_format($invoice->adjustment, 2) }} {{ $invoice->currency }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-lg font-semibold border-t pt-2">
                        <span class="text-gray-900">Total:</span>
                        <span class="text-gray-900">{{ number_format($invoice->total, 2) }} {{ $invoice->currency }}</span>
                    </div>
                </div>
            </div>

            <!-- Remarks and Conditions -->
            @if($invoice->remarks || $invoice->conditions)
            <div class="p-6 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($invoice->remarks)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Remarques</h3>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $invoice->remarks }}</p>
                    </div>
                    @endif
                    @if($invoice->conditions)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Conditions</h3>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $invoice->conditions }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</main>
@endsection
