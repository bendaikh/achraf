<tr class="border-t hover:bg-gray-50" x-data="{ open: false, edit: false, statusOpen: false }">
    <td class="px-4 py-3">{{ $item->employee?->fullName() }}</td>
    <td class="px-4 py-3">{{ $item->kindLabel() }}</td>
    <td class="px-4 py-3">{{ $fmt($item->amount) }} MAD</td>
    <td class="px-4 py-3">{{ $item->recurrenceLabel() }}</td>
    <td class="px-4 py-3">{{ $item->start_date?->format('d/m/Y') }}{{ $item->end_date ? ' → '.$item->end_date->format('d/m/Y') : ' → ∞' }}</td>
    <td class="px-4 py-3">{{ $item->statusLabel() }}</td>
    <td class="px-4 py-3 text-right relative">
        <button type="button" @click="open = !open" class="h-8 w-8 rounded border">⋯</button>
        <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-4 z-20 mt-1 w-44 rounded-lg border bg-white shadow text-left">
            <button type="button" @click="edit = !edit; statusOpen = false; open = false" class="block w-full px-3 py-2 text-sm hover:bg-gray-50 text-left">Modifier</button>
            <button type="button" @click="statusOpen = !statusOpen; edit = false; open = false" class="block w-full px-3 py-2 text-sm hover:bg-gray-50 text-left">Suspendre / Arrêter</button>
        </div>
        <div x-show="edit" x-cloak class="mt-3 text-left bg-slate-50 border rounded-lg p-3">
            <form method="POST" action="{{ route('hr.compensations.update', $item) }}" class="grid grid-cols-1 gap-2">
                @csrf
                @method('PUT')
                <select name="kind" class="px-3 py-2 border rounded-lg text-sm">
                    @foreach(\App\Models\CompensationItem::KINDS as $k => $l)
                        <option value="{{ $k }}" @selected($item->kind === $k)>{{ $l }}</option>
                    @endforeach
                </select>
                <select name="recurrence" class="px-3 py-2 border rounded-lg text-sm">
                    @foreach(\App\Models\CompensationItem::RECURRENCES as $k => $l)
                        <option value="{{ $k }}" @selected($item->recurrence === $k)>{{ $l }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" name="amount" value="{{ $item->amount }}" class="px-3 py-2 border rounded-lg text-sm" required>
                <input type="date" name="start_date" value="{{ $item->start_date?->format('Y-m-d') }}" class="px-3 py-2 border rounded-lg text-sm" required>
                <input type="date" name="end_date" value="{{ $item->end_date?->format('Y-m-d') }}" class="px-3 py-2 border rounded-lg text-sm">
                <input type="text" name="change_reason" class="px-3 py-2 border rounded-lg text-sm" value="Modification">
                <button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Enregistrer</button>
            </form>
        </div>
        <div x-show="statusOpen" x-cloak class="mt-3 text-left bg-amber-50 border rounded-lg p-3">
            <form method="POST" action="{{ route('hr.compensations.status', $item) }}" class="grid grid-cols-1 gap-2">
                @csrf
                <select name="status" class="px-3 py-2 border rounded-lg text-sm">
                    <option value="suspendu">Suspendre</option>
                    <option value="arrete">Arrêter</option>
                    <option value="actif">Réactiver</option>
                </select>
                <input type="date" name="effective_date" value="{{ now()->toDateString() }}" class="px-3 py-2 border rounded-lg text-sm" required>
                <input type="text" name="reason" class="px-3 py-2 border rounded-lg text-sm" placeholder="Motif">
                <button class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm">Appliquer</button>
            </form>
        </div>
    </td>
</tr>
