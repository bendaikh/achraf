@php
    $r = $role ?? null;
    $selectedPermissionIds = $selectedPermissionIds ?? [];
@endphp

<div class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
            <input type="text" name="name" value="{{ old('name', $r?->name) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
        </div>
        <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg">{{ old('description', $r?->description) }}</textarea>
        </div>
    </div>

    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Matrice des permissions</h2>
        <p class="text-sm text-gray-500 mb-4">Ce modèle sera appliqué aux nouveaux utilisateurs de ce rôle, puis personnalisable.</p>
        @include('access.partials.permissions-matrix', ['selectedPermissionIds' => old('permissions', $selectedPermissionIds)])
    </div>
</div>
