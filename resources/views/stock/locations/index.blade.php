@extends('layouts.with-sidebar')

@section('title', 'Stock par emplacement')
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Stock par emplacement</h1>
            <p class="text-sm text-slate-600 mt-0.5">Un catalogue produits, des quantités distinctes par magasin, dépôt ou stock en ligne. Les rapports n’affichent que les produits réellement présents (quantité &gt; 0).</p>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded text-green-700">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($warehouses as $warehouse)
                @php $stat = $stats[$warehouse->id]; @endphp
                <a href="{{ route('stock.locations.show', $warehouse) }}" class="block bg-white border border-slate-200 rounded-xl p-5 hover:border-[#0a5d8a] hover:shadow-sm transition">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-semibold text-slate-900">{{ $warehouse->name }}</h2>
                        <span class="text-[10px] uppercase font-semibold px-2 py-0.5 rounded-full {{ $warehouse->isOnline() ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700' }}">
                            {{ $warehouse->isOnline() ? 'En ligne' : 'Physique' }}
                        </span>
                    </div>
                    @if($warehouse->is_fulfillment_default)
                        <p class="text-xs text-amber-700 mt-1">Préparation des commandes (défaut)</p>
                    @endif
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-slate-500 text-xs">Références en stock</dt>
                            <dd class="font-bold text-slate-900 tabular-nums">{{ number_format($stat['references']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs">Quantité totale</dt>
                            <dd class="font-bold text-slate-900 tabular-nums">{{ number_format($stat['quantity']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs">Valeur HT</dt>
                            <dd class="font-semibold tabular-nums">{{ number_format($stat['value_ht'], 2) }} DH</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs">Valeur TTC</dt>
                            <dd class="font-semibold tabular-nums">{{ number_format($stat['value_ttc'], 2) }} DH</dd>
                        </div>
                    </dl>
                </a>
            @endforeach
        </div>
    </div>
</main>
@endsection
