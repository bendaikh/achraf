@extends('layouts.with-sidebar')

@section('title', 'Ticket ' . $sale->ticket_number)

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Ticket {{ $sale->ticket_number }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $sale->sold_at->format('d/m/Y à H:i') }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" onclick="window.print()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Imprimer</button>
                    <a href="{{ route('pos.sales.index') }}" class="px-4 py-2 text-gray-600 text-sm font-medium hover:text-gray-900">Historique</a>
                    <a href="{{ route('pos.index') }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Caisse</a>
                </div>
            </div>
        </header>

        <div class="p-8">
            @if(session('success'))
                <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-lg text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <div id="ticket-print" class="max-w-lg mx-auto bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden print:shadow-none print:border-0">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-8 py-6 text-white print:bg-emerald-700">
                    <p class="text-sm opacity-90">Point de vente</p>
                    <p class="text-2xl font-bold">{{ $sale->ticket_number }}</p>
                </div>
                <div class="px-8 py-6 space-y-4 text-sm">
                    <div class="grid grid-cols-2 gap-2 text-gray-600">
                        <span>Client</span>
                        <span class="text-gray-900 font-medium text-right">{{ $sale->client?->name ?? 'Client comptoir' }}</span>
                        <span>Caissier</span>
                        <span class="text-gray-900 text-right">{{ $sale->user?->name ?? '—' }}</span>
                        <span>Paiement</span>
                        <span class="text-gray-900 font-medium text-right">{{ $paymentMethods[$sale->payment_method] ?? $sale->payment_method }}</span>
                    </div>

                    @if($sale->payment_method === \App\Models\PosSale::PAYMENT_CASH && $sale->amount_received !== null)
                        <div class="rounded-lg bg-gray-50 p-3 grid grid-cols-2 gap-1 text-gray-700">
                            <span>Reçu</span>
                            <span class="text-right font-mono">{{ number_format($sale->amount_received, 2) }} DH</span>
                            <span>Monnaie</span>
                            <span class="text-right font-mono text-emerald-700 font-semibold">{{ number_format($sale->change_amount, 2) }} DH</span>
                        </div>
                    @endif

                    <div class="border-t border-gray-200 pt-4">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase border-b border-gray-100">
                                    <th class="pb-2">Article</th>
                                    <th class="pb-2 text-center">Qté</th>
                                    <th class="pb-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($sale->items as $item)
                                    <tr>
                                        <td class="py-2 pr-2">
                                            <span class="font-medium text-gray-900">{{ $item->designation }}</span>
                                            @if($item->ref)
                                                <span class="block text-xs text-gray-500 font-mono">{{ $item->ref }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-center text-gray-600">{{ $item->quantity }}</td>
                                        <td class="py-2 text-right font-mono text-gray-900">{{ number_format($item->line_total, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 pt-4 space-y-1 text-gray-700">
                        <div class="flex justify-between"><span>Total HT</span><span class="font-mono">{{ number_format($sale->subtotal, 2) }} DH</span></div>
                        <div class="flex justify-between"><span>TVA</span><span class="font-mono">{{ number_format($sale->tax_total, 2) }} DH</span></div>
                        @if($sale->discount > 0)
                            <div class="flex justify-between text-amber-700"><span>Remise</span><span class="font-mono">− {{ number_format($sale->discount, 2) }} DH</span></div>
                        @endif
                        <div class="flex justify-between text-lg font-bold text-emerald-700 pt-2 border-t border-gray-100">
                            <span>Total TTC</span>
                            <span class="font-mono">{{ number_format($sale->total, 2) }} DH</span>
                        </div>
                    </div>

                    @if($sale->notes)
                        <p class="text-xs text-gray-500 border-t border-gray-100 pt-4">{{ $sale->notes }}</p>
                    @endif
                </div>
            </div>
        </div>
    </main>
</div>
<style>
@media print {
    aside, header button, header a { display: none !important; }
    main { margin-left: 0 !important; }
}
</style>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
