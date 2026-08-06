@extends('layouts.with-sidebar')

@section('title', 'Modifier un client')

@section('sidebar_page_title', 'Modifier client')

@php
    $defaultType = old('client_type', $client->client_type ?? 'entreprise');
@endphp

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen bg-gray-50/80">
    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto">
        <div class="mb-6">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('clients.index') }}" class="hover:text-[#c9920f]">Clients</a>
                <span>/</span>
                <a href="{{ route('clients.show', $client) }}" class="hover:text-[#c9920f]">{{ $client->name }}</a>
                <span>/</span>
                <span class="text-gray-900">Modifier</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('clients.show', $client) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Modifier — {{ $client->name }}</h1>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <p class="text-red-700 font-medium text-sm">Corrigez les erreurs suivantes :</p>
                <ul class="list-disc list-inside text-red-600 mt-2 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('clients.update', $client) }}" method="POST" enctype="multipart/form-data"
              class="space-y-5"
              x-data="{ clientType: '{{ $defaultType }}' }">
            @csrf
            @method('PUT')

            <x-client-form-fields :client="$client" />

            <div class="sticky bottom-0 z-10 -mx-4 sm:-mx-6 lg:-mx-8 mt-2 border-t border-gray-200 bg-white/95 backdrop-blur px-4 sm:px-6 lg:px-8 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-[0_-4px_12px_rgba(0,0,0,0.04)]">
                <a href="{{ route('clients.show', $client) }}" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 text-center">
                    Annuler
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] text-sm font-semibold shadow-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</main>
@endsection
