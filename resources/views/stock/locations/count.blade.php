@extends('layouts.with-sidebar')

@section('title', 'Inventaire '.$warehouse->name)
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl">
        <h1 class="text-2xl font-bold text-slate-900 mb-1">Inventaire physique — {{ $warehouse->name }}</h1>
        <p class="text-sm text-slate-600 mb-6">Saisissez la quantité comptée. L’écart génère un mouvement « Ajustement inventaire » sans créer de stock ailleurs.</p>

        <form method="POST" action="{{ route('stock.locations.count.store', $warehouse) }}" class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            @csrf
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Produit</th>
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-right">Théorique</th>
                        <th class="px-4 py-3 text-right">Comptée</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($slots as $i => $slot)
                        <tr>
                            <td class="px-4 py-3">
                                {{ $slot->product?->name }}
                                <input type="hidden" name="counts[{{ $i }}][product_id]" value="{{ $slot->product_id }}">
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $slot->product?->ref }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $slot->quantity }}</td>
                            <td class="px-4 py-3 text-right">
                                <input type="number" min="0" name="counts[{{ $i }}][counted]" value="{{ $slot->quantity }}" class="w-24 text-right rounded-lg border-slate-300 text-sm">
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">Aucun produit avec stock &gt; 0 dans cet emplacement.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($slots->isNotEmpty())
                <div class="p-4 border-t flex gap-3">
                    <button class="px-5 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Valider l’inventaire</button>
                    <a href="{{ route('stock.locations.show', $warehouse) }}" class="px-5 py-2.5 border rounded-lg text-sm">Annuler</a>
                </div>
            @endif
        </form>
    </div>
</main>
@endsection
