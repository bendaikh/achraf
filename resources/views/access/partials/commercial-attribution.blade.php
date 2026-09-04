@php
    $docType = $documentType ?? 'invoice';
    $doc = $document ?? null;
    $commercials = $commercials ?? app(\App\Services\Access\CommercialAttributionService::class)->commercialOptions();
@endphp
@if($doc)
<div class="bg-white rounded-lg shadow p-4 mb-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">Commercial attribué</div>
            <div class="text-sm font-semibold text-gray-900">{{ $doc->commercial?->fullName() ?? '—' }}</div>
            @if($doc->createdByUser)
                <div class="text-xs text-gray-500 mt-1">Créé par : {{ $doc->createdByUser->name }}</div>
            @endif
        </div>
        @if(auth()->user()?->isSuperAdmin() || auth()->user()?->canAccess('sensible.reattribuer_commercial') || auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('responsable-commercial'))
            <form method="POST" action="{{ route('access.commercial.reassign') }}" class="flex flex-wrap gap-2 items-end">
                @csrf
                <input type="hidden" name="document_type" value="{{ $docType }}">
                <input type="hidden" name="document_id" value="{{ $doc->id }}">
                <select name="collaborator_id" class="px-3 py-2 border rounded-lg text-sm" required>
                    <option value="">Réattribuer…</option>
                    @foreach($commercials as $c)
                        <option value="{{ $c->id }}" @selected($doc->collaborator_id == $c->id)>{{ $c->fullName() }}</option>
                    @endforeach
                </select>
                <input type="text" name="reason" placeholder="Motif" class="px-3 py-2 border rounded-lg text-sm">
                <button class="px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50">OK</button>
            </form>
        @endif
    </div>
</div>
@endif
