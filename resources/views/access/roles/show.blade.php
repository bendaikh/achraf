@extends('layouts.with-sidebar')

@section('title', $role->name)
@section('sidebar_page_title', 'Administration')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-start">
            <div>
                <a href="{{ route('access.roles.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Rôles</a>
                <h1 class="text-3xl font-bold text-gray-900 mt-2">{{ $role->name }}</h1>
                <p class="text-gray-500 mt-1">{{ $role->description ?? 'Modèle de permissions' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('access.roles.duplicate', $role) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-gray-800">Dupliquer</button>
                </form>
                <a href="{{ route('access.roles.edit', $role) }}" class="px-4 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Modifier</a>
            </div>
        </div>

        @include('access.partials.flash')

        <div class="bg-white rounded-lg shadow p-6">
            @include('access.partials.permissions-matrix', ['selectedPermissionIds' => $selectedPermissionIds, 'readonly' => true])
            <p class="mt-4 text-xs text-gray-500">Aperçu en lecture seule — utilisez Modifier pour changer le modèle.</p>
        </div>
    </div>
</main>
@endsection
