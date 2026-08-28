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
    $documents = collect();
    if ($document) {
        $documents = collect([$document]);
    } elseif ($hasDocument === null) {
        $documents = \App\Models\ManagedDocument::query()
            ->where('section_key', $type)
            ->where('documentable_id', $id)
            ->where('category', $category)
            ->where('is_active', true)
            ->with('currentVersion')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();
    }
    $imported = $hasDocument ?? $documents->isNotEmpty();
@endphp

{{--
  No nested <form> tags: when this sits inside an edit/create form, a nested
  </form> would close the parent early and leave "Enregistrer" with nothing to submit.
--}}
<div
    class="flex flex-col gap-2 min-w-[180px]"
    x-data="{
        replaceOpenId: null,
        csrf() {
            return document.querySelector('meta[name=csrf-token]')?.content || '';
        },
        async upload(url, file, extra = {}) {
            if (!file) return;
            const fd = new FormData();
            fd.append('document_file', file);
            Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrf(),
                    'Accept': 'text/html,application/xhtml+xml,application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: fd,
                credentials: 'same-origin',
            });
            if (res.redirected) {
                window.location.href = res.url;
                return;
            }
            if (!res.ok) {
                alert('Échec de l\'envoi du document.');
                return;
            }
            window.location.reload();
        },
        async remove(url, redirectTo) {
            const confirmed = window.confirm('Voulez-vous vraiment supprimer ce document ? Cette action supprimera uniquement la pièce jointe et ne supprimera pas la facture.');
            if (!confirmed) return;
            const fd = new FormData();
            fd.append('_method', 'DELETE');
            if (redirectTo) fd.append('redirect_to', redirectTo);
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrf(),
                    'Accept': 'text/html,application/xhtml+xml,application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-HTTP-Method-Override': 'DELETE',
                },
                body: fd,
                credentials: 'same-origin',
            });
            if (res.redirected) {
                window.location.href = res.url;
                return;
            }
            if (!res.ok) {
                alert('Échec de la suppression du document.');
                return;
            }
            window.location.reload();
        }
    }"
>
    @if($imported && $documents->isNotEmpty())
        @foreach($documents as $resolvedDocument)
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-2" @click.outside="if (replaceOpenId === {{ $resolvedDocument->id }}) replaceOpenId = null">
                <a href="{{ route('managed-documents.show', $resolvedDocument) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900" title="Voir">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <span class="truncate max-w-[140px]">{{ $resolvedDocument->display_name }}</span>
                </a>
                @if($resolvedDocument->currentVersion?->size)
                    <p class="mt-0.5 text-[11px] text-gray-500">{{ strtoupper(pathinfo($resolvedDocument->display_name, PATHINFO_EXTENSION) ?: 'PDF') }} · {{ number_format($resolvedDocument->currentVersion->size / 1024, 0) }} Ko</p>
                @endif
                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600">
                    <a href="{{ route('managed-documents.show', $resolvedDocument) }}" target="_blank" class="hover:text-blue-700" title="Voir">👁️ Voir</a>
                    <a href="{{ route('managed-documents.download', $resolvedDocument) }}" class="hover:text-blue-700" title="Télécharger">⬇️ Télécharger</a>
                    <div class="relative">
                        <button type="button" @click="replaceOpenId = replaceOpenId === {{ $resolvedDocument->id }} ? null : {{ $resolvedDocument->id }}" class="hover:text-blue-700" title="Remplacer">🔄 Remplacer</button>
                        <div x-show="replaceOpenId === {{ $resolvedDocument->id }}" x-cloak class="absolute left-0 z-20 mt-2 w-56 rounded-lg border border-gray-200 bg-white p-3 shadow-lg">
                            <div class="space-y-2">
                                <label class="block text-xs font-medium text-gray-700">Remplacer le fichier</label>
                                <input
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="block w-full text-xs"
                                    @change="upload(@js(route('managed-documents.replace', $resolvedDocument)), $event.target.files?.[0], { redirect_to: @js(url()->full()) })"
                                >
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('managed-documents.history', $resolvedDocument) }}" class="hover:text-blue-700" title="Historique">🕘 Historique</a>
                    <button
                        type="button"
                        class="text-red-600 hover:text-red-700"
                        title="Supprimer la pièce jointe uniquement"
                        @click="remove(@js(route('managed-documents.destroy', $resolvedDocument)), @js(url()->full()))"
                    >🗑️ Supprimer</button>
                </div>
            </div>
        @endforeach
    @else
        <span class="inline-flex w-fit rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">Non importé</span>
    @endif

    @if($showAdd)
        <div class="lm-scan-mobile-only hidden w-full flex-col gap-2">
            <button
                type="button"
                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
                data-managed-scan
                data-scan-type="{{ $type }}"
                data-scan-id="{{ $id }}"
                data-scan-category="{{ $category }}"
                data-scan-url="{{ route('document-files.store', ['type' => $type, 'id' => $id]) }}"
            >
                Scanner un document
            </button>
            <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Importer un fichier
                <input
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    class="hidden"
                    @change="upload(@js(route('document-files.store', ['type' => $type, 'id' => $id])), $event.target.files?.[0], { category: @js($category), source: 'upload' })"
                >
            </label>
        </div>

        <div class="lm-scan-desktop-only relative">
            <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                📎 Importer un fichier
                <input
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    class="hidden"
                    @change="upload(@js(route('document-files.store', ['type' => $type, 'id' => $id])), $event.target.files?.[0], { category: @js($category), source: 'upload' })"
                >
            </label>
        </div>
    @endif
</div>
