@extends('layouts.app')

@section('title', 'Historique point de vente')

@section('content')
<div class="min-h-screen bg-gray-50 flex">
    <aside class="w-64 bg-white shadow-lg fixed h-full overflow-y-auto">
        @include('layouts.sidebar')
    </aside>

    <main class="flex-1 ml-64">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Historique des ventes (PDV)</h2>
                    <p class="text-sm text-gray-600 mt-1">Tickets caisse et modes de paiement</p>
                </div>
                <a href="{{ route('pos.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nouvelle vente
                </a>
            </div>
        </header>

        <div class="p-8">
            @if(session('success'))
                <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-lg text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <form method="get" class="mb-6 flex flex-wrap gap-4 items-end bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Du</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Au</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Paiement</label>
                    <select name="payment_method" class="rounded-lg border-gray-300 text-sm min-w-[160px]">
                        <option value="">Tous</option>
                        @foreach($paymentMethods as $val => $label)
                            <option value="{{ $val }}" @selected(request('payment_method') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900">Filtrer</button>
            </form>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paiement</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total TTC</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Caissier</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($sales as $sale)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $sale->ticket_number }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $sale->client?->name ?? 'Comptoir' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $sale->paymentLabel() }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-emerald-700 text-right">{{ number_format($sale->total, 2) }} DH</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ $sale->user?->name ?? '—' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('pos.sales.show', $sale) }}" class="text-emerald-600 hover:text-emerald-800 text-sm font-medium">Ticket</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">Aucune vente enregistrée.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($sales->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">{{ $sales->links() }}</div>
                @endif
            </div>
        </div>
    </main>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
