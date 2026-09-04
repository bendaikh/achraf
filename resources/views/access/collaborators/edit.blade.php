@extends('layouts.with-sidebar')

@section('title', 'Modifier collaborateur')
@section('sidebar_page_title', 'Administration')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl">
        <div class="mb-6">
            <a href="{{ route('access.collaborators.show', $collaborator) }}" class="text-sm text-blue-600 hover:text-blue-800">← {{ $collaborator->fullName() }}</a>
            <h1 class="text-3xl font-bold text-gray-900 mt-2">Modifier le collaborateur</h1>
        </div>

        @include('access.partials.flash')

        <form method="POST" action="{{ route('access.collaborators.update', $collaborator) }}" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6">
            @csrf
            @method('PUT')
            @include('access.collaborators._form')
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('access.collaborators.show', $collaborator) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Annuler</a>
                <button type="submit" class="px-6 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Enregistrer</button>
            </div>
        </form>
    </div>
</main>
@endsection
