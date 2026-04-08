@extends('layouts.with-sidebar')

@section('title', 'Modifier un fournisseur')

@section('sidebar_page_title', 'Modifier fournisseur')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('suppliers.index') }}" class="hover:text-blue-600">Fournisseurs</a>
                <span>/</span>
                <span class="text-gray-900">Modifier un fournisseur</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">Modifier un fournisseur</h1>
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

        <form action="{{ route('suppliers.update', $supplier) }}" method="POST" class="bg-white rounded-lg shadow">
            @csrf
            @method('PUT')

            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations Générales</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                            Fournisseur <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            value="{{ old('name', $supplier->name) }}" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror"
                        >
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">
                            Adresse
                        </label>
                        <input 
                            type="text" 
                            name="address" 
                            id="address" 
                            value="{{ old('address', $supplier->address) }}" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('address') border-red-500 @enderror"
                        >
                        @error('address')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            value="{{ old('email', $supplier->email) }}" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('email') border-red-500 @enderror"
                        >
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 mb-1">
                            Code
                        </label>
                        <input 
                            type="text" 
                            name="code" 
                            id="code" 
                            value="{{ old('code', $supplier->code) }}" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('code') border-red-500 @enderror"
                        >
                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ville" class="block text-sm font-medium text-gray-700 mb-1">
                            Ville
                        </label>
                        <input 
                            type="text" 
                            name="ville" 
                            id="ville" 
                            value="{{ old('ville', $supplier->ville) }}" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('ville') border-red-500 @enderror"
                        >
                        @error('ville')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="fiscal_identifier" class="block text-sm font-medium text-gray-700 mb-1">
                            Identifiant fiscal (IF)
                        </label>
                        <input 
                            type="text" 
                            name="fiscal_identifier" 
                            id="fiscal_identifier" 
                            value="{{ old('fiscal_identifier', $supplier->fiscal_identifier) }}" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('fiscal_identifier') border-red-500 @enderror"
                        >
                        @error('fiscal_identifier')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="region" class="block text-sm font-medium text-gray-700 mb-1">
                            Région
                        </label>
                        <input 
                            type="text" 
                            name="region" 
                            id="region" 
                            value="{{ old('region', $supplier->region) }}" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('region') border-red-500 @enderror"
                        >
                        @error('region')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ice" class="block text-sm font-medium text-gray-700 mb-1">
                            ICE
                        </label>
                        <input 
                            type="text" 
                            name="ice" 
                            id="ice" 
                            value="{{ old('ice', $supplier->ice) }}" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('ice') border-red-500 @enderror"
                        >
                        @error('ice')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-1">
                            Code postal
                        </label>
                        <input 
                            type="text" 
                            name="postal_code" 
                            id="postal_code" 
                            value="{{ old('postal_code', $supplier->postal_code) }}" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('postal_code') border-red-500 @enderror"
                        >
                        @error('postal_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="latitude" class="block text-sm font-medium text-gray-700 mb-1">
                            Latitude
                        </label>
                        <input 
                            type="text" 
                            name="latitude" 
                            id="latitude" 
                            value="{{ old('latitude', $supplier->latitude) }}" 
                            placeholder="33.5731"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('latitude') border-red-500 @enderror"
                        >
                        @error('latitude')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                            Numéro de téléphone
                        </label>
                        <input 
                            type="text" 
                            name="phone" 
                            id="phone" 
                            value="{{ old('phone', $supplier->phone) }}" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('phone') border-red-500 @enderror"
                        >
                        @error('phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="longitude" class="block text-sm font-medium text-gray-700 mb-1">
                            Longitude
                        </label>
                        <input 
                            type="text" 
                            name="longitude" 
                            id="longitude" 
                            value="{{ old('longitude', $supplier->longitude) }}" 
                            placeholder="-7.5898"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('longitude') border-red-500 @enderror"
                        >
                        @error('longitude')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-700 mb-1">
                            Pays
                        </label>
                        <select 
                            name="country" 
                            id="country" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('country') border-red-500 @enderror"
                        >
                            <option value="Maroc" {{ old('country', $supplier->country) == 'Maroc' ? 'selected' : '' }}>Maroc</option>
                        </select>
                        @error('country')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3 rounded-b-lg">
                <a href="{{ route('suppliers.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150">
                    Annuler
                </a>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition duration-150 text-sm font-medium">
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</main>
@endsection
