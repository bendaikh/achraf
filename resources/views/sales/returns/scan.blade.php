@extends('layouts.with-sidebar')

@section('title', 'Scan retour')
@section('sidebar_page_title', 'Ventes')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto pb-16">
        <div class="mb-6">
            <a href="{{ route('sales.returns.index') }}" class="text-sm text-[#0a5d8a] hover:underline">← Retours</a>
            <h1 class="text-2xl font-bold text-slate-900 mt-2">Scan / Recherche retour</h1>
            <p class="text-sm text-slate-600 mt-1">Tracking, n° commande métier, référence Shopify / Jumia, téléphone client…</p>
        </div>

        <form method="GET" class="bg-white border border-slate-200 rounded-xl p-4 mb-6">
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Rechercher</label>
            <div class="flex gap-2">
                <input type="text" name="q" value="{{ $query }}" autofocus
                       class="flex-1 rounded-lg border-slate-300 text-sm px-3 py-2.5"
                       placeholder="Ex. tracking, FAST-…, #Shopify, téléphone">
                <button class="px-5 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Rechercher</button>
            </div>
        </form>

        @if($query !== '')
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b bg-slate-50 text-sm font-semibold text-slate-700">
                    {{ count($matches) }} résultat(s) pour « {{ $query }} »
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse($matches as $order)
                        <li class="p-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $order->ticket_number }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ $order->source ?: '—' }}
                                    · {{ $order->client?->name ?: 'Sans client' }}
                                    · {{ optional($order->sold_at)->format('d/m/Y') }}
                                    @if($order->primaryTrackingNumber())
                                        · Tracking {{ $order->primaryTrackingNumber() }}
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('sales.returns.start') }}">
                                @csrf
                                <input type="hidden" name="pos_sale_id" value="{{ $order->id }}">
                                <input type="hidden" name="search_key" value="{{ $query }}">
                                <button class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold">
                                    Ouvrir contrôle
                                </button>
                            </form>
                        </li>
                    @empty
                        <li class="p-8 text-center text-slate-500 text-sm">Aucune commande trouvée. Un statut transporteur « Retourné » seul ne suffit pas — identifiez la commande métier.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>
</main>
@endsection
