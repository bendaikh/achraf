@extends('layouts.with-sidebar')

@section('title', 'Ajouter un client')

@section('sidebar_page_title', 'Nouveau client')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('clients.index') }}" class="hover:text-blue-600">Clients</a>
                <span>/</span>
                <span class="text-gray-900">Ajouter un client</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">Ajouter un client</h1>
        </div>

        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-red-700 font-medium">Il y a des erreurs dans le formulaire:</p>
                        <ul class="list-disc list-inside text-red-600 mt-2">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form action="{{ route('clients.store') }}" method="POST" class="bg-white rounded-lg shadow" x-data="{ clientType: '{{ old('client_type', 'entreprise') }}' }">
            @csrf

            <div class="p-6">
                <x-client-form-fields :client-code="$clientCode ?? ''" />
            </div>

            <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3 rounded-b-lg">
                <a href="{{ route('clients.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150">
                    Annuler
                </a>
                <button type="submit" class="px-6 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] transition duration-150 text-sm font-medium">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</main>

@endsection
