@extends('layouts.with-sidebar')

@section('title', 'Détails de l\'avoir')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $creditNote->credit_note_number }}</h2>
                <p class="text-sm text-gray-600 mt-1">Détails de l'avoir</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('credit-notes.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-150">
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
                        <p class="text-sm text-gray-900 mt-1">{{ $creditNote->client->name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Date de l'avoir</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $creditNote->credit_note_date->format('d/m/Y') }}</p>
                    </div>
                    @if($creditNote->invoice)
                    <div>
                        <label class="text-sm font-medium text-gray-500">Facture liée</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $creditNote->invoice->invoice_number }}</p>
                    </div>
                    @endif
                    <div>
                        <label class="text-sm font-medium text-gray-500">Devise</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $creditNote->currency }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Emplacement de stock</label>
                        <p class="text-sm text-gray-900 mt-1">{{ $creditNote->stock_location }}</p>
                    </div>
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
                            @foreach($creditNote->items as $item)
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
                        <span class="font-medium text-gray-900">{{ number_format($creditNote->subtotal, 2) }} {{ $creditNote->currency }}</span>
                    </div>
                    @if($creditNote->discount > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Remise:</span>
                        <span class="font-medium text-gray-900">{{ number_format($creditNote->discount, 2) }} {{ $creditNote->currency }}</span>
                    </div>
                    @endif
                    @if($creditNote->adjustment != 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Ajustement:</span>
                        <span class="font-medium text-gray-900">{{ number_format($creditNote->adjustment, 2) }} {{ $creditNote->currency }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-lg font-semibold border-t pt-2">
                        <span class="text-gray-900">Total:</span>
                        <span class="text-gray-900">{{ number_format($creditNote->total, 2) }} {{ $creditNote->currency }}</span>
                    </div>
                </div>
            </div>

            <!-- Remarks and Conditions -->
            @if($creditNote->remarks || $creditNote->conditions)
            <div class="p-6 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if($creditNote->remarks)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Remarques</h3>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $creditNote->remarks }}</p>
                    </div>
                    @endif
                    @if($creditNote->conditions)
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Conditions</h3>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $creditNote->conditions }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</main>
@endsection
