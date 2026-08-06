@extends('layouts.with-sidebar')

@section('title', 'Modifier un fournisseur')

@section('sidebar_page_title', 'Modifier fournisseur')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen bg-gray-50/80">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('suppliers.index') }}" class="hover:text-blue-600">Fournisseurs</a>
                <span>/</span>
                <span class="text-gray-900">Modifier</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Modifier — {{ $supplier->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">Code : {{ $supplier->code ?? '—' }}</p>
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
            :supplier="$supplier"
            :users="$users"
            :form-action="route('suppliers.update', $supplier)"
            form-method="PUT"
            submit-label="Mettre à jour"
        />
    </div>
</main>
@endsection
