@extends('layouts.with-sidebar')

@section('title', 'Dépenses — Paramètres')
@section('sidebar_page_title', 'Paramètres')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-6 lg:p-8 max-w-6xl mx-auto pb-16">
        @include('settings.partials.alerts')

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-start gap-3 px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-[#0a5d8a]/10 text-[#0a5d8a]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Dépenses</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Catégories, comptes et modes de règlement disponibles dans les formulaires de dépenses</p>
                </div>
            </div>
            <div class="p-6">
                @include('settings.partials.depenses')
            </div>
        </div>
    </div>
</main>
@endsection
