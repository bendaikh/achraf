@extends('layouts.with-sidebar')
@section('title', 'Documents RH')
@section('sidebar_page_title', 'RH')
@section('main')
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Documents RH</h1>
    <p class="text-gray-500 mb-6">Même moteur documentaire que les Achats : téléverser, scanner, voir, télécharger, remplacer, historique.</p>
    <x-table-filters :action="route('hr.documents.index')" :search="false" :date-from="false" :date-to="false" grid-cols="md:grid-cols-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Salarié</label>
            <select name="employee_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Tous</option>
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" @selected((string) request('employee_id') === (string) $emp->id)>{{ $emp->fullName() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
            <select name="category" class="w-full px-3 py-2 border rounded-lg text-sm">
                <option value="">Toutes</option>
                @foreach($categories as $k => $l)
                    <option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
    </x-table-filters>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left">Salarié</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Fichier</th><th class="px-4 py-3 text-left">Expiration</th><th class="px-4 py-3 text-left">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($documents as $document)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $document->documentable instanceof \App\Models\Employee ? $document->documentable->fullName() : '—' }}</td>
                        <td class="px-4 py-3">{{ $document->document_type_label }}</td>
                        <td class="px-4 py-3">{{ $document->display_name }}</td>
                        <td class="px-4 py-3">{{ $document->expires_at?->format('d/m/Y') ?: '—' }}</td>
                        <td class="px-4 py-3"><x-managed-document-actions type="hr-employees" :id="$document->documentable_id" :document="$document" :show-add="false" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Aucun document.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3">{{ $documents->links() }}</div>
    </div>
</main>
@endsection
