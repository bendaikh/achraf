@extends('layouts.with-sidebar')

@section('title', 'Utilisateurs')
@section('sidebar_page_title', 'Administration')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Comptes utilisateurs</h1>
                <p class="text-gray-500 mt-1">Accès Libromart : identifiant, rôle (modèle), permissions personnalisées, périmètre.</p>
            </div>
            <a href="{{ route('access.users.create') }}" class="inline-flex items-center justify-center px-6 py-3 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Nouveau compte</a>
        </div>

        @include('access.partials.flash')

        <x-table-filters :action="route('access.users.index')" search-placeholder="Nom, email..." :date-from="false" :date-to="false" grid-cols="md:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach(\App\Models\User::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rôle</label>
                <select name="primary_role_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected((string) request('primary_role_id') === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
        </x-table-filters>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Collaborateur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rôle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Périmètre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dernière connexion</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($user->collaborator)
                                        <a href="{{ route('access.collaborators.show', $user->collaborator) }}" class="text-blue-600 hover:text-blue-900">{{ $user->collaborator->fullName() }}</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $user->primaryRole?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $user->dataScopeLabel() }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $user->statusLabel() }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('access.users.show', $user) }}" class="text-blue-600 hover:text-blue-900 text-sm font-medium">Ouvrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Aucun compte utilisateur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $users->links() }}</div>
        </div>
    </div>
</main>
@endsection
