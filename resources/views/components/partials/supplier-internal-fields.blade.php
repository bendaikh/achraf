@php
    $idPrefix = $idPrefix ?? '';
@endphp

<div>
    <label for="{{ $idPrefix }}status" class="{{ $label }}">Statut</label>
    <select name="status" id="{{ $idPrefix }}status" class="{{ $input }}">
        @foreach(\App\Models\Supplier::STATUSES as $key => $lab)
            <option value="{{ $key }}" {{ $val('status', 'actif') == $key ? 'selected' : '' }}>{{ $lab }}</option>
        @endforeach
    </select>
</div>
<div>
    <label for="{{ $idPrefix }}category" class="{{ $label }}">Catégorie fournisseur</label>
    <input type="text" name="category" id="{{ $idPrefix }}category" value="{{ $val('category') }}" class="{{ $input }}" placeholder="Ex: Matières premières">
</div>
<div>
    <label for="{{ $idPrefix }}internal_owner_id" class="{{ $label }}">Responsable interne</label>
    <select name="internal_owner_id" id="{{ $idPrefix }}internal_owner_id" class="{{ $input }}">
        <option value="">Sélectionner</option>
        @foreach($users as $user)
            <option value="{{ $user->id }}" {{ (string) $val('internal_owner_id') === (string) $user->id ? 'selected' : '' }}>
                {{ $user->name }}
            </option>
        @endforeach
    </select>
</div>
<div class="md:col-span-2">
    <label for="{{ $idPrefix }}notes" class="{{ $label }}">Notes</label>
    <textarea name="notes" id="{{ $idPrefix }}notes" rows="3" class="{{ $input }}" placeholder="Notes internes…">{{ $val('notes') }}</textarea>
</div>
