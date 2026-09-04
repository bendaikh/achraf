@php
    $commercials = $commercials ?? app(\App\Services\Access\CommercialAttributionService::class)->commercialOptions();
    $selected = old($name ?? 'collaborator_id', $selected ?? null);
    $name = $name ?? 'collaborator_id';
    $label = $label ?? 'Commercial attribué';
    $required = $required ?? false;
@endphp
<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $label }}@if($required) * @endif</label>
    <select name="{{ $name }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" @if($required) required @endif>
        <option value="">— Automatique (utilisateur connecté) —</option>
        @foreach($commercials as $commercial)
            <option value="{{ $commercial->id }}" @selected((string) $selected === (string) $commercial->id)>
                {{ $commercial->fullName() }} ({{ $commercial->typeLabel() }})
            </option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-gray-500">Distinct de « créé par ». Le commercial suit Devis → BC → BL → Facture → Commission.</p>
</div>
