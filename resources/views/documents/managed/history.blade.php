@extends('layouts.with-sidebar')

@section('title', 'Historique document')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Historique — {{ $document->display_name }}</h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $document->document_type_label }} · {{ $document->reference }} · {{ $document->document_date?->format('d/m/Y') }}
                </p>
                @if(! $document->is_active)
                    <p class="mt-2 text-sm text-red-700">
                        Pièce jointe désactivée le {{ $document->deleted_at?->format('d/m/Y H:i') }}
                        par {{ $document->deletedBy?->name ?? '—' }}. L’enregistrement métier n’a pas été supprimé.
                    </p>
                @endif
            </div>
            <a href="{{ url()->previous() }}" class="rounded-lg border px-4 py-2 text-sm">Retour</a>
        </div>
    </header>

    <div class="p-8">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fichier d’origine</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateur</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taille</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fichier</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($document->versions as $version)
                        <tr>
                            <td class="px-6 py-3 text-sm">v{{ $version->version_number }} @if($document->current_version_id === $version->id)<span class="ml-2 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Courante</span>@endif</td>
                            <td class="px-6 py-3 text-sm">{{ $version->original_name }}</td>
                            <td class="px-6 py-3 text-sm">
                                @if($version->source === 'deletion')
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">Suppression</span>
                                @elseif($version->source === 'scan')
                                    Scan
                                @elseif($version->source === 'upload')
                                    Import
                                @else
                                    {{ $version->source }}
                                @endif
                            </td>
                            <td class="px-6 py-3 text-sm">{{ $version->uploader?->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm">{{ $version->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-3 text-sm">{{ $version->size ? number_format($version->size / 1024, 1).' KB' : '—' }}</td>
                            <td class="px-6 py-3 text-sm">
                                <a href="{{ route('managed-documents.download', $document) }}" class="text-blue-700 hover:underline">Télécharger</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
