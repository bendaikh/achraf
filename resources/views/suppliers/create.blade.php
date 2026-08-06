@extends('layouts.with-sidebar')

@section('title', 'Nouveau fournisseur')

@section('sidebar_page_title', 'Nouveau fournisseur')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen bg-gray-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('suppliers.index') }}" class="hover:text-blue-600">Fournisseurs</a>
                <span>/</span>
                <span class="text-gray-900">Nouveau</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Nouveau fournisseur</h1>
            <p class="text-sm text-gray-500 mt-1">Remplissez les sections ci-dessous. Seul le nom est obligatoire pour commencer.</p>
        </div>

        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 p-4 rounded-xl">
                <p class="text-red-700 font-medium mb-2">Corrigez les erreurs suivantes :</p>
                <ul class="list-disc list-inside text-red-600 text-sm space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-supplier-form-fields
            :supplier-code="$supplierCode"
            :users="$users"
            :form-action="route('suppliers.store')"
            submit-label="Enregistrer le fournisseur"
        />
    </div>
</main>
@endsection
