@extends('layouts.with-sidebar')

@section('title', 'Mouvements de stock')
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Mouvements de stock</h1>
                <p class="text-sm text-slate-600 mt-0.5">Historique des entrées, sorties et transferts</p>
            </div>
            <a href="{{ route('stock.transfer.create') }}" class="inline-flex items-center px-4 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold hover:bg-[#074866]">
                Transférer du stock
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded text-green-700">{{ session('success') }}</div>
        @endif

        <form method="GET" class="mb-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 bg-white border border-slate-200 rounded-xl p-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Recherche</label>
                <input type="text" name="search" value="{{ request('search') }}" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                <select name="type" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Tous</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Dépôt</label>
                <select name="warehouse_id" class="w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Tous</option>
                    @foreach($warehouses as $id => $name)
                        <option value="{{ $id }}" @selected(request('warehouse_id') == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Du</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Au</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div class="flex items-end">
                <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Filtrer</button>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Produit</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-right">Qté</th>
                            <th class="px-4 py-3 text-left">Dépôt</th>
                            <th class="px-4 py-3 text-left">Emplacement</th>
                            <th class="px-4 py-3 text-left">Document</th>
                            <th class="px-4 py-3 text-left">Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($movements as $movement)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $movement->moved_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $movement->product?->name }}</div>
                                    <div class="text-xs text-slate-500 font-mono">{{ $movement->product?->ref }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $movement->typeLabel() }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $movement->quantity > 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                                </td>
                                <td class="px-4 py-3">{{ $movement->warehouse?->name ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $movement->location?->code ?: '—' }}</td>
                                <td class="px-4 py-3 text-xs">{{ $movement->document_reference ?: ($movement->document_type ?: '—') }}</td>
                                <td class="px-4 py-3">{{ $movement->user?->name ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">Aucun mouvement</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($movements->hasPages())
                <div class="px-4 py-3 border-t">{{ $movements->links() }}</div>
            @endif
        </div>
    </div>
</main>
@endsection
