@extends('layouts.with-sidebar')

@section('title', 'Nouveau salarié')
@section('sidebar_page_title', 'RH')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Nouveau salarié</h1>
        <p class="text-gray-500 mb-6">Vous pouvez saisir une date d’entrée antérieure (ex. 01/09/2025) même si la fiche est créée aujourd’hui.</p>
        @include('hr.partials.flash')
        <form method="POST" action="{{ route('hr.employees.store') }}" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            @csrf
            @include('hr.employees._form')
            <div class="flex justify-end gap-3">
                <a href="{{ route('hr.employees.index') }}" class="px-4 py-2 border rounded-lg text-sm">Annuler</a>
                <button class="px-5 py-2 bg-[#fdb819] text-white rounded-lg font-semibold">Enregistrer</button>
            </div>
        </form>
    </div>
</main>
@endsection
