@extends('layouts.with-sidebar')

@section('title', 'Fiscalité — Paramètres')
@section('sidebar_page_title', 'Paramètres')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-6 lg:p-8 max-w-6xl mx-auto pb-16">
        @include('settings.partials.alerts')

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-start gap-3 px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-[#0a5d8a]/10 text-[#0a5d8a]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Fiscalité</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Catégories TVA utilisées dans la gestion produit</p>
                </div>
            </div>
            <div class="p-6">
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_type" value="vat_categories">
                    <p class="text-sm text-gray-500 mb-3">Une catégorie par ligne (ex: TVA (20%)).</p>
                    <textarea name="vat_categories" rows="8" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0a5d8a] focus:border-transparent">{{ $settings['vat_categories'] ?? '' }}</textarea>
                    <div class="sticky bottom-0 flex justify-end mt-4 pt-3 border-t border-gray-100">
                        <button type="submit" class="inline-flex items-center px-6 py-2.5 bg-[#0a5d8a] text-white rounded-lg hover:bg-[#084a6e] transition duration-150 text-sm font-medium shadow-sm">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
@endsection
