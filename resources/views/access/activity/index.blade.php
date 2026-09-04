@extends('layouts.with-sidebar')

@section('title', 'Journal d\'activité')
@section('sidebar_page_title', 'Administration')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Journal d’activité</h1>
            <p class="text-gray-500 mt-1">Traçabilité des actions sensibles (créations, modifications, permissions, etc.).</p>
        </div>

        @include('access.partials.flash')

        <x-table-filters :action="route('access.activity.index')" search-placeholder="Action, résumé, document..." grid-cols="md:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                <input type="text" name="action" value="{{ request('action') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="ex. modification">
            </div>
        </x-table-filters>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date / heure</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Détail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $log->user?->name ?? 'Système' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $log->action }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $log->document_ref ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div>{{ $log->summary ?? '—' }}</div>
                                    @if($log->old_values || $log->new_values)
                                        <div class="mt-1 text-xs text-gray-500">
                                            @if($log->old_values)<span>Avant: {{ \Illuminate\Support\Str::limit(json_encode($log->old_values, JSON_UNESCAPED_UNICODE), 80) }}</span>@endif
                                            @if($log->new_values)<span class="ml-1">→ {{ \Illuminate\Support\Str::limit(json_encode($log->new_values, JSON_UNESCAPED_UNICODE), 80) }}</span>@endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Aucune activité enregistrée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $logs->links() }}</div>
        </div>
    </div>
</main>
@endsection
