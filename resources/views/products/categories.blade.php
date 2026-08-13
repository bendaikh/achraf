@extends('layouts.with-sidebar')

@section('title', 'Catégories produits')
@section('sidebar_page_title', 'Produits')

@section('main')
<main class="flex-1 overflow-y-auto bg-slate-50/80">
    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold text-slate-900 mb-1">Catégories</h1>
        <p class="text-sm text-slate-600 mb-6">Gérez les catégories utilisées dans la création et le filtrage des produits / services.</p>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded text-green-700">{{ session('success') }}</div>
        @endif

        <form action="{{ route('products.categories.update') }}" method="POST" class="bg-white rounded-xl border border-slate-200 p-6 space-y-6">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Catégories produits (une par ligne)</label>
                <textarea name="product_type_categories" rows="8" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">{{ old('product_type_categories', $productTypeCategories) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Catégories services (une par ligne)</label>
                <textarea name="service_categories" rows="6" class="w-full rounded-lg border-slate-300 text-sm focus:border-[#fdb819] focus:ring-[#fdb819]">{{ old('service_categories', $serviceCategories) }}</textarea>
            </div>
            <button type="submit" class="px-5 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-semibold">Enregistrer</button>
        </form>
    </div>
</main>
@endsection
