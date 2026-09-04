@php
    $u = $user ?? null;
    $selectedPermissionIds = $selectedPermissionIds ?? [];
@endphp

<div class="space-y-8">
    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom affiché *</label>
            <input type="text" name="name" value="{{ old('name', $u?->name) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email / identifiant *</label>
            <input type="email" name="email" value="{{ old('email', $u?->email) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe {{ $u ? '(laisser vide pour ne pas changer)' : '*' }}</label>
            <input type="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg" {{ $u ? '' : 'required' }} autocomplete="new-password">
            <p class="mt-1 text-xs text-gray-500">L’invitation / mot de passe oublié / 2FA seront ajoutés dans une phase dédiée.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmation</label>
            <input type="password" name="password_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded-lg" autocomplete="new-password">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Collaborateur lié</label>
            <select name="collaborator_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                <option value="">— Aucun —</option>
                @foreach($availableCollaborators as $collaborator)
                    <option value="{{ $collaborator->id }}" @selected((string) old('collaborator_id', $u?->collaborator_id ?? ($prefillCollaboratorId ?? null)) === (string) $collaborator->id)>
                        {{ $collaborator->fullName() }} ({{ $collaborator->typeLabel() }})
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rôle (modèle) *</label>
            <select name="primary_role_id" id="primary_role_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                <option value="">— Choisir —</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('primary_role_id', $u?->primary_role_id) === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">Le rôle initialise les droits. Vous pouvez ensuite personnaliser ci-dessous.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Statut *</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                @foreach(\App\Models\User::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(old('status', $u?->status ?? 'actif') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Périmètre des données *</label>
            <select name="data_scope" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                @foreach($dataScopes as $key => $label)
                    <option value="{{ $key }}" @selected(old('data_scope', $u?->data_scope ?? 'own') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Dépôts attribués</h2>
        <p class="text-sm text-gray-500 mb-3">Utilisé lorsque le périmètre est « Dépôts attribués ».</p>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($warehouses as $warehouse)
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="checkbox" name="warehouses[]" value="{{ $warehouse->id }}" class="rounded border-gray-300"
                        @checked(collect(old('warehouses', $u?->warehouses?->pluck('id')->all() ?? []))->contains($warehouse->id))>
                    {{ $warehouse->name }}
                </label>
            @empty
                <p class="text-sm text-gray-500">Aucun dépôt configuré.</p>
            @endforelse
        </div>
    </div>

    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Permissions</h2>
        <p class="text-sm text-gray-500 mb-4">Personnalisation par utilisateur. Deux commerciaux peuvent avoir des droits différents. Changer le rôle recharge le modèle (vous pourrez ensuite ajuster).</p>
        @include('access.partials.permissions-matrix', ['selectedPermissionIds' => old('permissions', $selectedPermissionIds)])
    </div>
</div>

<script type="application/json" id="role-permission-map">@json($rolePermissionMap ?? [])</script>
<script>
(() => {
    const select = document.getElementById('primary_role_id');
    const mapEl = document.getElementById('role-permission-map');
    if (!select || !mapEl) return;
    let map = {};
    try { map = JSON.parse(mapEl.textContent || '{}'); } catch (e) { return; }

    select.addEventListener('change', () => {
        const ids = new Set((map[select.value] || []).map(String));
        document.querySelectorAll('[data-permission-matrix] input[name="permissions[]"]').forEach((el) => {
            el.checked = ids.has(String(el.value));
        });
    });
})();
</script>
