@extends('layouts.with-sidebar')

@section('title', 'Modifier le produit / service')

@section('sidebar_page_title', 'Modifier produit')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen bg-slate-100/70">
    <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data" class="min-h-screen flex flex-col">
        @csrf
        @method('PUT')

        <div class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                        <a href="{{ route('products.index') }}" class="hover:text-[#c48a00]">Produits</a>
                        <span>/</span>
                        <span class="text-slate-800">Modifier</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">Modifier — {{ $product->name }}</h1>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('products.index') }}"
                       class="px-4 py-2.5 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition">
                        Annuler
                    </a>
                    <button type="submit"
                            class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-[#fdb819] hover:bg-[#e5a617] shadow-sm transition">
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>

        <div class="flex-1 p-4 sm:p-6 lg:p-8">
            @if($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 p-4 rounded-xl">
                    <p class="text-red-700 font-medium text-sm">Il y a des erreurs dans le formulaire :</p>
                    <ul class="list-disc list-inside text-red-600 text-sm mt-2 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @include('products._form')

            <div class="mt-6 flex items-center justify-end gap-2 xl:hidden">
                <a href="{{ route('products.index') }}" class="px-4 py-2.5 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 bg-white">Annuler</a>
                <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-[#fdb819] hover:bg-[#e5a617]">Enregistrer</button>
            </div>
        </div>
    </form>
</main>

<script>
function getTVARate() {
    const vatSelect = document.getElementById('vat_category');
    if (!vatSelect || !vatSelect.value) return 20;
    const match = vatSelect.value.match(/(\d+(?:[.,]\d+)?)\s*%/);
    return match ? parseFloat(match[1].replace(',', '.')) : 20;
}

function calculateTTC() {
    const htInput = document.getElementById('sale_price_ht');
    const ttcInput = document.getElementById('sale_price');
    if (htInput && htInput.value) {
        const ht = parseFloat(htInput.value) || 0;
        ttcInput.value = (ht * (1 + getTVARate() / 100)).toFixed(2);
    }
}

function calculateHT() {
    const htInput = document.getElementById('sale_price_ht');
    const ttcInput = document.getElementById('sale_price');
    if (ttcInput && ttcInput.value) {
        const ttc = parseFloat(ttcInput.value) || 0;
        htInput.value = (ttc / (1 + getTVARate() / 100)).toFixed(2);
    }
}

function calculatePrices() {
    const costEl = document.getElementById('cost_price_ht');
    const costPriceHT = parseFloat(costEl && !costEl.disabled ? costEl.value : 0) || 0;
    const marginPercent = parseFloat(document.getElementById('product_margin')?.value) || 0;
    if (costPriceHT > 0 && marginPercent > 0) {
        document.getElementById('sale_price_ht').value = (costPriceHT * (1 + marginPercent / 100)).toFixed(2);
        calculateTTC();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const vatSelect = document.getElementById('vat_category');
    if (vatSelect) {
        vatSelect.addEventListener('change', function () {
            if (document.getElementById('sale_price_ht')?.value) calculateTTC();
        });
    }
});
</script>
@endsection
