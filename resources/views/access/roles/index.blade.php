@extends('layouts.with-sidebar')

@section('title', 'Rôles')
@section('sidebar_page_title', 'Administration')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Rôles & permissions</h1>
                <p class="text-gray-500 mt-1">Les rôles sont des modèles. L’Admin personnalise ensuite chaque utilisateur.</p>
            </div>
            <a href="{{ route('access.roles.create') }}" class="inline-flex items-center justify-center px-6 py-3 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Nouveau rôle</a>
        </div>

        @include('access.partials.flash')

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rôle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permissions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateurs</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($roles as $role)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $role->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $role->slug }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $role->description ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $role->permissions_count }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $role->primary_users_count }}</td>
                                <td class="px-6 py-4 text-sm space-x-3">
                                    <a href="{{ route('access.roles.show', $role) }}" class="text-blue-600 hover:text-blue-900 font-medium">Ouvrir</a>
                                    <a href="{{ route('access.roles.edit', $role) }}" class="text-blue-600 hover:text-blue-900 font-medium">Modifier</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Aucun rôle.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $roles->links() }}</div>
        </div>
    </div>
</main>
@endsection
