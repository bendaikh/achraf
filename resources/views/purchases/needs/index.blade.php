@extends('layouts.with-sidebar')

@section('title', 'Besoins d\'achat')
@section('sidebar_page_title', 'Achats')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        <h1 class="text-2xl font-bold text-slate-900 mb-1">Besoins d'achat</h1>
        <p class="text-sm text-slate-600 mb-2">
            Même besoin métier que <a href="{{ route('stock.replenishment.index') }}" class="text-[#0a5d8a] font-semibold hover:underline">Produits → À approvisionner</a>.
            Ici : couvrir le manque et choisir le fournisseur.
        </p>
        <p class="text-xs text-slate-500 mb-4">Aucun BC automatique sans validation. Affecter un fournisseur ne crée pas encore de BC.</p>
        <form method="POST" action="{{ route('purchases.needs.recalculate') }}" class="mb-6"
              onsubmit="return confirm('Recalculer les besoins d’achat ?\n\nLes besoins non traités seront annulés puis recréés à partir du stock physique actuel (Magasin Belvédère / emplacements). Les réservations des commandes en attente sont refaites. Les commandes, le stock physique et les besoins déjà commandés (BC) ne sont pas modifiés.')">
            @csrf
            <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-semibold hover:bg-amber-700">
                Réinitialiser / Recalculer les besoins d’achat
            </button>
            <span class="ml-2 text-xs text-slate-500">Besoin = Qté commandée − stock physique disponible − déjà réservé. Si ≤ 0 → aucun besoin.</span>
        </form>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded text-green-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded text-red-700">{{ session('error') }}</div>
        @endif

        @forelse($groups as $supplierKey => $groupNeeds)
            @php
                $sid = (int) $supplierKey;
                $label = $sid
                    ? ($groupNeeds->first()->supplier?->name ?: $groupNeeds->first()->suggestedSupplier?->name ?: 'Fournisseur #'.$sid)
                    : 'Aucun fournisseur connu';
            @endphp
            <div class="mb-6 bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-slate-50 border-b flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-semibold text-slate-900">{{ $label }}</h2>
                    @if($sid)
                        <form method="POST" action="{{ route('stock.replenishment.generate-po') }}">
                            @csrf
                            <input type="hidden" name="supplier_id" value="{{ $sid }}">
                            @foreach($groupNeeds as $need)
                                <input type="hidden" name="need_ids[]" value="{{ $need->id }}">
                            @endforeach
                            <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Générer BC fournisseur</button>
                        </form>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase text-slate-500 bg-white">
                            <tr>
                                <th class="px-4 py-2 text-left">Produit</th>
                                <th class="px-4 py-2 text-right">Besoin</th>
                                <th class="px-4 py-2 text-right">Physique</th>
                                <th class="px-4 py-2 text-right">Réservé</th>
                                <th class="px-4 py-2 text-right">Disponible</th>
                                <th class="px-4 py-2 text-left">Commande</th>
                                <th class="px-4 py-2 text-left">Fournisseur</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($groupNeeds as $need)
                                @php
                                    $product = $need->product;
                                    // Même source que Stock par emplacement / Magasin Belvédère (hors Shopify, hors SAV).
                                    $ps = $physicalStocks[$need->product_id] ?? ['physical' => 0, 'reserved' => 0, 'available' => 0];
                                    $phys = $ps['physical'];
                                    $res = $ps['reserved'];
                                    $avail = $ps['available'];
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $product?->name }}</div>
                                        <div class="text-xs font-mono text-slate-500">{{ $product?->ref }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-amber-800">{{ $need->quantity_needed }}</td>
                                    <td class="px-4 py-3 text-right">{{ $phys }}</td>
                                    <td class="px-4 py-3 text-right">{{ $res }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ $avail }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $need->posSale?->ticket_number ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('stock.replenishment.supplier', $need) }}" class="flex gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="supplier_id" class="rounded-lg border-slate-300 text-sm" onchange="this.form.submit()">
                                                <option value="">Aucun fournisseur connu</option>
                                                @foreach($suppliers as $supplier)
                                                    <option value="{{ $supplier->id }}" @selected((int) ($need->supplier_id ?: $need->suggested_supplier_id) === (int) $supplier->id)>
                                                        {{ $supplier->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="bg-white border rounded-xl p-10 text-center text-slate-500">Aucun besoin d’achat ouvert.</div>
        @endforelse
    </div>
</main>
@endsection
