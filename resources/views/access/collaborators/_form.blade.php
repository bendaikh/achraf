@php
    $isEdit = isset($collaborator);
    $c = $collaborator ?? null;
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Type de collaborateur *</label>
        <select name="type" id="collaborator_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
            @foreach(\App\Models\Collaborator::TYPES as $key => $label)
                <option value="{{ $key }}" @selected(old('type', $c?->type) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div id="employee_link_block" class="{{ old('type', $c?->type ?? 'salarie') === 'salarie' ? '' : 'hidden' }}">
        <label class="block text-sm font-medium text-gray-700 mb-1">Lier à un salarié RH existant</label>
        <select name="employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            <option value="">— Aucune fiche RH —</option>
            @foreach($availableEmployees as $employee)
                <option value="{{ $employee->id }}" @selected((string) old('employee_id', $c?->employee_id) === (string) $employee->id)>
                    {{ $employee->fullName() }} ({{ $employee->matricule }}){{ $employee->job_title ? ' — '.$employee->job_title : '' }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Ne crée pas une deuxième fiche. Les salariés RH existants sont conservés.</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
        <input type="text" name="last_name" value="{{ old('last_name', $c?->last_name) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Prénom *</label>
        <input type="text" name="first_name" value="{{ old('first_name', $c?->first_name) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
        <input type="text" name="phone" value="{{ old('phone', $c?->phone) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email', $c?->email) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Fonction</label>
        <input type="text" name="job_title" value="{{ old('job_title', $c?->job_title) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Département / service</label>
        <input type="text" name="department" value="{{ old('department', $c?->department) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Équipe</label>
        <input type="text" name="team" value="{{ old('team', $c?->team) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Responsable</label>
        <select name="manager_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            <option value="">—</option>
            @foreach($managers as $manager)
                <option value="{{ $manager->id }}" @selected((string) old('manager_id', $c?->manager_id) === (string) $manager->id)>{{ $manager->fullName() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
        <input type="date" name="start_date" value="{{ old('start_date', $c?->start_date?->format('Y-m-d')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
        <input type="date" name="end_date" value="{{ old('end_date', $c?->end_date?->format('Y-m-d')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Statut *</label>
        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
            @foreach(\App\Models\Collaborator::STATUSES as $key => $label)
                <option value="{{ $key }}" @selected(old('status', $c?->status ?? 'actif') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
        <input type="file" name="photo" accept="image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div class="lg:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm text-gray-800">
            <input type="checkbox" name="is_commercial" value="1" class="rounded border-gray-300" @checked(old('is_commercial', $c?->is_commercial))>
            Commercial (accès au moteur ventes / commissions)
        </label>
    </div>
    <div class="lg:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
        <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg">{{ old('notes', $c?->notes) }}</textarea>
    </div>
</div>

<script>
(() => {
    const typeSelect = document.getElementById('collaborator_type');
    const block = document.getElementById('employee_link_block');
    if (!typeSelect || !block) return;
    const sync = () => {
        block.classList.toggle('hidden', typeSelect.value !== 'salarie');
    };
    typeSelect.addEventListener('change', sync);
    sync();
})();
</script>
