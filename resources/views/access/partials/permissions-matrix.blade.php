{{--
  Permissions matrix / toggles.
  Expects: $permissionsByModule, $modules, $actions, $selectedPermissionIds (array of ids)
  Optional: $readonly (bool)
--}}
@php
    $selected = collect($selectedPermissionIds ?? []);
    $readonly = $readonly ?? false;
@endphp

<div class="space-y-3" data-permission-matrix>
    @unless($readonly)
    <div class="flex flex-wrap gap-2 mb-4">
        <button type="button" data-perm-action="readonly" class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Lecture seule</button>
        <button type="button" data-perm-action="all" class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Tout autoriser</button>
        <button type="button" data-perm-action="none" class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50">Tout désactiver</button>
    </div>
    @endunless

    @foreach($modules as $moduleKey => $moduleLabel)
        @php $modulePerms = $permissionsByModule->get($moduleKey, collect()); @endphp
        @continue($modulePerms->isEmpty())

        <details class="bg-white border border-gray-200 rounded-lg" @if($loop->first) open @endif>
            <summary class="cursor-pointer select-none px-4 py-3 font-semibold text-gray-900 flex items-center justify-between">
                <span>{{ $moduleLabel }}</span>
                <span class="text-xs font-normal text-gray-500">{{ $modulePerms->count() }} permission(s)</span>
            </summary>
            <div class="px-4 pb-4 overflow-x-auto">
                @if($moduleKey === 'sensible')
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($modulePerms->groupBy('group_label') as $group => $perms)
                            <div class="border border-gray-100 rounded-lg p-3">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">{{ $group }}</div>
                                @foreach($perms as $perm)
                                    <label class="flex items-start gap-2 py-1 text-sm text-gray-800">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="mt-0.5 rounded border-gray-300 text-[#0a5d8a] focus:ring-[#0a5d8a]" data-perm-key="{{ $perm->key }}" data-perm-action-type="sensitive" @checked($selected->contains($perm->id)) @disabled($readonly)>
                                        <span>{{ $perm->label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @else
                    @php
                        $byResource = $modulePerms->groupBy('resource');
                    @endphp
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-2 pr-3">Ressource</th>
                                @foreach($actions as $actionKey => $actionLabel)
                                    <th class="py-2 px-1 text-center">{{ $actionLabel }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($byResource as $resource => $perms)
                                <tr>
                                    <td class="py-2 pr-3 font-medium text-gray-800">{{ $perms->first()->group_label }}</td>
                                    @foreach($actions as $actionKey => $actionLabel)
                                        @php $perm = $perms->firstWhere('action', $actionKey); @endphp
                                        <td class="py-2 px-1 text-center">
                                            @if($perm)
                                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="rounded border-gray-300 text-[#0a5d8a] focus:ring-[#0a5d8a]" data-perm-key="{{ $perm->key }}" data-perm-action-type="{{ $actionKey }}" @checked($selected->contains($perm->id)) @disabled($readonly)>
                                            @else
                                                <span class="text-gray-300">·</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </details>
    @endforeach
</div>

@unless($readonly)
<script>
(() => {
    const root = document.querySelector('[data-permission-matrix]');
    if (!root) return;

    const boxes = () => Array.from(root.querySelectorAll('input[type="checkbox"][name="permissions[]"]'));

    root.querySelectorAll('[data-perm-action]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const mode = btn.getAttribute('data-perm-action');
            boxes().forEach((el) => {
                const action = el.getAttribute('data-perm-action-type');
                if (mode === 'all') el.checked = true;
                else if (mode === 'none') el.checked = false;
                else if (mode === 'readonly') el.checked = action === 'voir';
            });
        });
    });
})();
</script>
@endunless
