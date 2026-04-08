@extends('layouts.with-sidebar')

@section('title', 'Détails du client')

@section('sidebar_page_title', 'Client')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('clients.index') }}" class="hover:text-blue-600">Clients</a>
                <span>/</span>
                <span class="text-gray-900">{{ $client->name }}</span>
            </div>
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-900">{{ $client->name }}</h1>
                <div class="flex space-x-2">
                    <a href="{{ route('clients.edit', $client) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150 flex items-center space-x-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        <span>Modifier</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations Générales</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-500">Code client</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->code ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Entreprise</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->email }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Téléphone</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Adresse</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->address ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Ville</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->ville ?? $client->city ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Code postal</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->postal_code ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Région</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->region ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Pays</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->country ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ICE</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->ice ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Identifiant fiscal (IF)</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->fiscal_identifier ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Latitude</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->latitude ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Longitude</p>
                        <p class="text-base font-medium text-gray-900">{{ $client->longitude ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
