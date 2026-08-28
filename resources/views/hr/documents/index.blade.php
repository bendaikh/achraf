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
    <x-table-list-toolbar table-id="hr-documents" />
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table data-lm-table="hr-documents" class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-4 py-3 text-left lm-col lm-col-salarie column-salarie" data-lm-col="salarie">Salarié</th><th class="px-4 py-3 text-left lm-col lm-col-type column-type" data-lm-col="type">Type</th><th class="px-4 py-3 text-left lm-col lm-col-titre column-titre" data-lm-col="titre">Fichier</th><th class="px-4 py-3 text-left lm-col lm-col-date column-date" data-lm-col="date">Expiration</th><th class="px-4 py-3 text-left lm-col lm-col-expiration column-expiration" data-lm-col="expiration">Actions</th>
            </tr></thead>
            <tbody>
                @forelse($documents as $document)
                    <tr class="border-t">
                        <td class="px-4 py-3 lm-col lm-col-salarie column-salarie" data-lm-col="salarie">{{ $document->documentable instanceof \App\Models\Employee ? $document->documentable->fullName() : '—' }}</td>
                        <td class="px-4 py-3 lm-col lm-col-type column-type" data-lm-col="type">{{ $document->document_type_label }}</td>
                        <td class="px-4 py-3 lm-col lm-col-titre column-titre" data-lm-col="titre">{{ $document->display_name }}</td>
                        <td class="px-4 py-3 lm-col lm-col-date column-date" data-lm-col="date">{{ $document->expires_at?->format('d/m/Y') ?: '—' }}</td>
                        <td class="px-4 py-3 lm-col lm-col-expiration column-expiration" data-lm-col="expiration"><x-managed-document-actions type="hr-employees" :id="$document->documentable_id" :document="$document" :show-add="false" /></td>
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
