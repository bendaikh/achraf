@extends('layouts.with-sidebar')

@section('title')
Ajuster le stock magasin — {{ $product->ref }}
@endsection

@section('sidebar_page_title', 'Ajuster stock magasin')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
            <div class="p-4 sm:p-6 lg:p-8 max-w-2xl">
                <div class="mb-6">
                    <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                        <a href="{{ route('stock.magasin.index') }}" class="hover:text-emerald-600">Stock Magasin</a>
                        <span>/</span>
                        <span class="text-gray-900">Ajuster</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Ajuster le stock magasin</h1>
                    <p class="text-gray-500 mt-1">{{ $product->ref }} — {{ $product->name }}</p>
                    <div class="flex items-center mt-2 text-sm text-gray-500">
                        <svg class="h-4 w-4 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        Produit Magasin (POS)
                    </div>
                </div>

                @if($errors->any())
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <ul class="list-disc list-inside text-red-600 text-sm">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="bg-white rounded-lg shadow p-6 mb-6 border border-gray-100">
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-gray-500">Stock actuel</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ $product->stock_quantity }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Seuils</dt>
                            <dd class="text-gray-900">Alerte : {{ $product->minimum_alert_stock ?? '—' }} · Sécurité : {{ $product->minimum_safety_stock ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <form action="{{ route('stock.magasin.update', $product) }}" method="POST" class="bg-white rounded-lg shadow p-6">
                    @csrf
                    @method('PATCH')
                    <div class="mb-6">
                        <label for="stock_quantity" class="block text-sm font-medium text-gray-700 mb-1">Nouvelle quantité en stock</label>
                        <input type="number" name="stock_quantity" id="stock_quantity" min="0" required
                            value="{{ old('stock_quantity', $product->stock_quantity) }}"
                            class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <p class="mt-2 text-xs text-gray-500">Saisissez la quantité physique après inventaire ou réception.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-lg font-medium hover:from-emerald-700 hover:to-teal-700">
                            Enregistrer
                        </button>
                        <a href="{{ route('stock.magasin.index') }}" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </main>
@endsection
