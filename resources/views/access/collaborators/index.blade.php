@extends('layouts.with-sidebar')

@section('title', 'Collaborateurs')
@section('sidebar_page_title', 'Administration')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Collaborateurs</h1>
                <p class="text-gray-500 mt-1">Personnes liées à l’entreprise. Distinctes des comptes Libromart et des fiches RH salariés.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('access.collaborators.sync-hr') }}" onsubmit="return confirm('Créer un collaborateur pour chaque salarié RH pas encore lié ? Les fiches RH existantes ne seront pas dupliquées.');">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-gray-800">Lier les salariés RH</button>
                </form>
                <a href="{{ route('access.collaborators.create') }}" class="inline-flex items-center justify-center px-6 py-3 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] font-semibold">Ajouter un collaborateur</a>
            </div>
        </div>

        @include('access.partials.flash')

        <x-table-filters :action="route('access.collaborators.index')" search-placeholder="Nom, email, fonction..." :date-from="false" :date-to="false" grid-cols="md:grid-cols-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach(\App\Models\Collaborator::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach(\App\Models\Collaborator::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end pb-1">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="commercials_only" value="1" class="rounded border-gray-300" @checked(request()->boolean('commercials_only'))>
                    Commerciaux uniquement
                </label>
            </div>
        </x-table-filters>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Collaborateur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fonction</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">RH</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Compte</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($collaborators as $collaborator)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $collaborator->fullName() }}</div>
                                    <div class="text-xs text-gray-500">{{ $collaborator->email ?? $collaborator->phone ?? '—' }}</div>
                                    @if($collaborator->is_commercial)
                                        <span class="mt-1 inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-sky-50 text-sky-800">Commercial</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $collaborator->typeLabel() }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $collaborator->job_title ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    @if($collaborator->employee)
                                        <a href="{{ route('hr.employees.show', $collaborator->employee) }}" class="text-blue-600 hover:text-blue-900">{{ $collaborator->employee->matricule }}</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    @if($collaborator->user)
                                        <a href="{{ route('access.users.show', $collaborator->user) }}" class="text-blue-600 hover:text-blue-900">Oui</a>
                                    @else
                                        <a href="{{ route('access.users.create', ['collaborator_id' => $collaborator->id]) }}" class="text-amber-700 hover:text-amber-900">Créer un compte</a>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $collaborator->statusBadgeClass() }}">{{ $collaborator->statusLabel() }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('access.collaborators.show', $collaborator) }}" class="text-blue-600 hover:text-blue-900 text-sm font-medium">Ouvrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">Aucun collaborateur. Liez les salariés RH existants ou créez un freelance.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $collaborators->links() }}</div>
        </div>
    </div>
</main>
@endsection
