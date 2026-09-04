@extends('layouts.with-sidebar')

@section('title', 'Modifier compte')
@section('sidebar_page_title', 'Administration')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl">
        <div class="mb-6">
            <a href="{{ route('access.users.show', $user) }}" class="text-sm text-blue-600 hover:text-blue-800">← {{ $user->name }}</a>
            <h1 class="text-3xl font-bold text-gray-900 mt-2">Modifier le compte</h1>
        </div>
        @include('access.partials.flash')
        <form method="POST" action="{{ route('access.users.update', $user) }}" class="bg-white rounded-lg shadow p-6">
            @csrf
            @method('PUT')
            @include('access.users._form')
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('access.users.show', $user) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Annuler</a>
                <button type="submit" class="px-6 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Enregistrer</button>
            </div>
        </form>
    </div>
</main>
@endsection
