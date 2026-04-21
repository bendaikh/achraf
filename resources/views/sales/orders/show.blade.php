@extends('layouts.with-sidebar')

@section('title', 'Commande ' . $order->ticket_number)

@section('sidebar_page_title', 'Détails Commande')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Commande {{ $order->ticket_number }}</h2>
                <div class="flex items-center gap-3 mt-2">
                    <p class="text-sm text-gray-600">{{ $order->sold_at->format('d/m/Y à H:i') }}</p>
                    @if($order->source === 'shopify')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Shopify
                    </span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Point de Vente
                    </span>
                    @endif
                </div>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="window.print()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Imprimer
                </button>
                <a href="{{ route('orders.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Retour à la liste
                </a>
            </div>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:px-8 max-w-4xl mx-auto">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <!-- Order Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Commande</p>
                        <p class="text-2xl font-bold">{{ $order->ticket_number }}</p>
                        @if($order->external_id)
                        <p class="text-xs opacity-75 mt-1">ID externe: {{ $order->external_id }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        @if($order->status === 'completed')
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-white/20 backdrop-blur-sm">
                            ✓ Complétée
                        </span>
                        @elseif($order->status === 'cancelled')
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-white/20 backdrop-blur-sm">
                            ✗ Annulée
                        </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Order Details -->
            <div class="px-6 py-6 space-y-6">
                <!-- Customer & Payment Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Informations Client</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Nom:</span>
                                <span class="text-gray-900 font-medium">{{ $order->client?->name ?? 'Client anonyme' }}</span>
                            </div>
                            @if($order->client?->email)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Email:</span>
                                <span class="text-gray-900">{{ $order->client->email }}</span>
                            </div>
                            @endif
                            @if($order->client?->phone)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Téléphone:</span>
                                <span class="text-gray-900">{{ $order->client->phone }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Informations Paiement</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Méthode:</span>
                                <span class="text-gray-900 font-medium">{{ $order->paymentLabel() }}</span>
                            </div>
                            @if($order->user)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Vendeur:</span>
                                <span class="text-gray-900">{{ $order->user->name }}</span>
                            </div>
                            @endif
                            @if($order->payment_method === 'cash' && $order->amount_received)
                            <div class="rounded-lg bg-gray-50 p-3 mt-3 space-y-1">
                                <div class="flex justify-between text-gray-700">
                                    <span>Reçu:</span>
                                    <span class="font-mono">{{ number_format($order->amount_received, 2) }} DH</span>
                                </div>
                                <div class="flex justify-between text-green-700 font-semibold">
                                    <span>Monnaie:</span>
                                    <span class="font-mono">{{ number_format($order->change_amount, 2) }} DH</span>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Articles</h3>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Article</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Prix U.</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qté</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($order->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $item->designation }}</div>
                                        @if($item->ref)
                                        <div class="text-xs text-gray-500 font-mono">Réf: {{ $item->ref }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-900 font-mono">
                                        {{ number_format($item->unit_price, 2) }} DH
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-600">
                                        {{ $item->quantity }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 font-mono">
                                        {{ number_format($item->line_total, 2) }} DH
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Order Totals -->
                <div class="border-t border-gray-200 pt-4">
                    <div class="max-w-md ml-auto space-y-2 text-sm">
                        <div class="flex justify-between text-gray-700">
                            <span>Sous-total HT:</span>
                            <span class="font-mono">{{ number_format($order->subtotal, 2) }} DH</span>
                        </div>
                        <div class="flex justify-between text-gray-700">
                            <span>TVA:</span>
                            <span class="font-mono">{{ number_format($order->tax_total, 2) }} DH</span>
                        </div>
                        @if($order->discount > 0)
                        <div class="flex justify-between text-amber-700">
                            <span>Remise:</span>
                            <span class="font-mono">− {{ number_format($order->discount, 2) }} DH</span>
                        </div>
                        @endif
                        <div class="flex justify-between text-lg font-bold text-gray-900 pt-2 border-t border-gray-200">
                            <span>Total TTC:</span>
                            <span class="font-mono text-blue-600">{{ number_format($order->total, 2) }} {{ $order->currency ?? 'DH' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                @if($order->notes)
                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Notes</h3>
                    <p class="text-sm text-gray-600 bg-gray-50 rounded-lg p-3">{{ $order->notes }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <footer class="mt-auto border-t border-gray-200 bg-white py-6 px-4 text-center text-sm text-gray-500">
        <p>© {{ date('Y') }}. Tous droits réservés.</p>
    </footer>
</main>

<style>
@media print {
    aside, header button, header a { display: none !important; }
    main { margin-left: 0 !important; }
}
</style>
@endsection
