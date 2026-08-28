@php
    $employee = $employee ?? null;
    $field = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm';
@endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Matricule</label>
        <input type="text" value="{{ $employee->matricule ?? $matricule }}" disabled class="{{ $field }} bg-gray-50">
        <p class="text-xs text-gray-500 mt-1">Attribué automatiquement (EMP-0001, EMP-0002…).</p>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Date réelle d’entrée <span class="text-red-500">*</span></label>
        <input type="date" name="hire_date" value="{{ old('hire_date', $employee?->hire_date?->format('Y-m-d')) }}" required class="{{ $field }}">
        <p class="text-xs text-gray-500 mt-1">Indépendante de la date de création de la fiche dans Libromart.</p>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
        <input type="text" name="last_name" value="{{ old('last_name', $employee?->last_name) }}" required class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
        <input type="text" name="first_name" value="{{ old('first_name', $employee?->first_name) }}" required class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Date de naissance</label>
        <input type="date" name="birth_date" value="{{ old('birth_date', $employee?->birth_date?->format('Y-m-d')) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">CIN</label>
        <input type="text" name="cin" value="{{ old('cin', $employee?->cin) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
        <input type="text" name="phone" value="{{ old('phone', $employee?->phone) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email', $employee?->email) }}" class="{{ $field }}">
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
        <input type="text" name="address" value="{{ old('address', $employee?->address) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
        <input type="text" name="city" value="{{ old('city', $employee?->city) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nationalité</label>
        <input type="text" name="nationality" value="{{ old('nationality', $employee?->nationality) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Genre</label>
        <select name="gender" class="{{ $field }}">
            <option value="">—</option>
            @foreach(\App\Models\Employee::GENDERS as $key => $label)
                <option value="{{ $key }}" @selected(old('gender', $employee?->gender) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Situation familiale</label>
        <select name="marital_status" class="{{ $field }}">
            <option value="">—</option>
            @foreach(\App\Models\Employee::MARITAL_STATUSES as $key => $label)
                <option value="{{ $key }}" @selected(old('marital_status', $employee?->marital_status) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">N° CNSS</label>
        <input type="text" name="cnss_number" value="{{ old('cnss_number', $employee?->cnss_number) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">N° AMO</label>
        <input type="text" name="amo_number" value="{{ old('amo_number', $employee?->amo_number) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">RIB</label>
        <input type="text" name="rib" value="{{ old('rib', $employee?->rib) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Banque</label>
        <input type="text" name="bank_name" value="{{ old('bank_name', $employee?->bank_name) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Fonction / Poste</label>
        <input type="text" name="job_title" value="{{ old('job_title', $employee?->job_title) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Service</label>
        <select name="department_id" class="{{ $field }}">
            <option value="">—</option>
            @foreach($departments as $dep)
                <option value="{{ $dep->id }}" @selected((string) old('department_id', $employee?->department_id) === (string) $dep->id)>{{ $dep->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Responsable hiérarchique</label>
        <select name="manager_id" class="{{ $field }}">
            <option value="">—</option>
            @foreach($managers as $manager)
                <option value="{{ $manager->id }}" @selected((string) old('manager_id', $employee?->manager_id) === (string) $manager->id)>{{ $manager->fullName() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Lieu de travail</label>
        <input type="text" name="workplace" value="{{ old('workplace', $employee?->workplace) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
        <select name="status" class="{{ $field }}">
            @foreach(\App\Models\Employee::STATUSES as $key => $label)
                <option value="{{ $key }}" @selected(old('status', $employee?->status ?? 'actif') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">ID pointeuse / matricule externe</label>
        <input type="text" name="timeclock_external_id" value="{{ old('timeclock_external_id', $employee?->timeclock_external_id) }}" class="{{ $field }}">
        <p class="text-xs text-gray-500 mt-1">Pour rattacher ultérieurement une pointeuse physique.</p>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Solde de congés repris</label>
        <input type="number" step="0.5" name="initial_leave_balance" value="{{ old('initial_leave_balance', $employee?->initial_leave_balance ?? 0) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Photo</label>
        <input type="file" name="photo" accept="image/*" class="{{ $field }}">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Compte utilisateur Libromart (optionnel)</label>
        <select name="user_id" class="{{ $field }}">
            <option value="">Non rattaché — le salarié n’est pas un utilisateur</option>
            @foreach(($users ?? []) as $user)
                <option value="{{ $user->id }}" @selected((string) old('user_id', $employee?->user_id) === (string) $user->id)>{{ $user->name }} ({{ $user->email }})</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-center gap-2 mt-6">
        <input type="checkbox" name="commission_eligible" value="1" id="commission_eligible" @checked(old('commission_eligible', $employee?->commission_eligible))>
        <label for="commission_eligible" class="text-sm text-gray-700">Éligible aux commissions (module commissions ultérieur)</label>
    </div>
    @if(! $employee)
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Salaire de base (historique initial)</label>
            <input type="number" step="0.01" name="base_salary" value="{{ old('base_salary') }}" class="{{ $field }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Négocié en</label>
            <select name="negotiated_as" class="{{ $field }}">
                <option value="brut">Brut</option>
                <option value="net">Net à payer</option>
            </select>
        </div>
    @endif
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
        <textarea name="notes" rows="3" class="{{ $field }}">{{ old('notes', $employee?->notes) }}</textarea>
    </div>
</div>
