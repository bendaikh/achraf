@props([
    'type',
    'id',
    'document' => null,
    'hasDocument' => null,
    'category' => 'primary',
    'label' => 'Ajouter un document',
    'showAdd' => true,
])

@php
    $resolvedDocument = $document;
    if (! $resolvedDocument && $hasDocument === null) {
        $resolvedDocument = \App\Models\ManagedDocument::query()
            ->where('section_key', $type)
            ->where('documentable_id', $id)
            ->where('category', $category)
            ->where('is_active', true)
            ->with('currentVersion')
            ->latest('id')
            ->first();
    }
    $imported = $hasDocument ?? (bool) $resolvedDocument;
@endphp

<div class="flex flex-col gap-2 min-w-[180px]" x-data="{ open: false }">
    @if($imported && $resolvedDocument)
        <div class="flex items-center gap-2">
            <a href="{{ route('managed-documents.show', $resolvedDocument) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900" title="Voir">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span class="truncate max-w-[120px]">{{ $resolvedDocument->display_name }}</span>
            </a>
        </div>
        <div class="flex items-center gap-2 text-gray-600">
            <a href="{{ route('managed-documents.show', $resolvedDocument) }}" target="_blank" class="hover:text-blue-700" title="Voir">👁️</a>
            <a href="{{ route('managed-documents.download', $resolvedDocument) }}" class="hover:text-blue-700" title="Télécharger">⬇️</a>
            <div class="relative">
                <button type="button" @click="open = !open" class="hover:text-blue-700" title="Remplacer">🔄</button>
                <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 z-20 mt-2 w-56 rounded-lg border border-gray-200 bg-white p-3 shadow-lg">
                    <form action="{{ route('managed-documents.replace', $resolvedDocument) }}" method="POST" enctype="multipart/form-data" class="space-y-2">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                        <label class="block text-xs font-medium text-gray-700">Remplacer le fichier</label>
                        <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full text-xs">
                        <button type="submit" class="w-full rounded bg-blue-600 px-2 py-1.5 text-xs font-medium text-white hover:bg-blue-700">Enregistrer</button>
                    </form>
                </div>
            </div>
        </div>
    @else
        <span class="inline-flex w-fit rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">Non importé</span>
    @endif

    @if($showAdd)
        <div class="relative">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                📎 {{ $label }}
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 z-20 mt-2 w-64 rounded-lg border border-gray-200 bg-white p-2 shadow-xl">
                <form action="{{ route('document-files.store', ['type' => $type, 'id' => $id]) }}" method="POST" enctype="multipart/form-data" class="block">
                    @csrf
                    <input type="hidden" name="category" value="{{ $category }}">
                    <input type="hidden" name="source" value="upload">
                    <label class="flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        ⬆️ Téléverser un fichier
                        <input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" class="hidden" onchange="this.form.submit()">
                    </label>
                </form>
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                    data-managed-scan
                    data-scan-type="{{ $type }}"
                    data-scan-id="{{ $id }}"
                    data-scan-category="{{ $category }}"
                    data-scan-url="{{ route('document-files.store', ['type' => $type, 'id' => $id]) }}"
                    data-scan-bridge="{{ config('managed_documents.scanner_bridge_url') }}"
                >
                    🖨️ Scanner en PDF
                </button>
            </div>
        </div>
    @endif
</div>
