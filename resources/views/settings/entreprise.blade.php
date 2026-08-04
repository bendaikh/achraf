@extends('layouts.with-sidebar')

@section('title', 'Mon Entreprise — Paramètres')
@section('sidebar_page_title', 'Paramètres')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-6 lg:p-8 max-w-6xl mx-auto pb-16">
        @include('settings.partials.alerts')

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-start gap-3 px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-[#0a5d8a]/10 text-[#0a5d8a]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </span>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Mon Entreprise</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Identité, logo, cachet et informations légales affichées sur vos documents</p>
                </div>
            </div>
            <div class="p-6">
                @include('settings.partials.mon-entreprise')
            </div>
        </div>
    </div>
</main>
@endsection
