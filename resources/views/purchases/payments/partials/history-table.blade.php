@php
    $fmt = $fmt ?? fn ($n) => number_format((float) $n, 2, ',', ' ');
@endphp
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h3 class="font-semibold text-gray-900">Historique des règlements</h3>
            <p class="text-xs text-gray-500 mt-0.5">Conservé même lorsque les factures sont totalement soldées.</p>
        </div>
        @isset($supplier)
            <a href="{{ route('purchases.payments.history', ['supplier_id' => $supplier->id]) }}" class="text-sm text-[#0a5d8a]">Filtres &amp; recherche</a>
        @endisset
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">N° règlement</th>
                    @if(!empty($showSupplier))
                        <th class="px-4 py-3 text-left">Fournisseur</th>
                    @endif
                    <th class="px-4 py-3 text-left">Facture(s)</th>
                    <th class="px-4 py-3 text-left">Mode</th>
                    <th class="px-4 py-3 text-left">Référence</th>
                    <th class="px-4 py-3 text-right">Montant</th>
                    <th class="px-4 py-3 text-center">Justificatif</th>
                    <th class="px-4 py-3 text-left">Statut</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($rows as $row)
                    @php $p = $row['payment']; @endphp
                    <tr class="{{ $row['cancelled'] ? 'bg-gray-50 text-gray-500' : 'hover:bg-gray-50' }}">
                        <td class="px-4 py-3">{{ $p->payment_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('purchases.payments.show', $p) }}" class="text-[#0a5d8a]">{{ $p->payment_number ?: 'REG-'.$p->id }}</a>
                        </td>
                        @if(!empty($showSupplier))
                            <td class="px-4 py-3">{{ $p->supplier?->name }}</td>
                        @endif
                        <td class="px-4 py-3">{{ $row['invoices'] }}</td>
                        <td class="px-4 py-3">{{ $p->payment_method }}</td>
                        <td class="px-4 py-3">{{ $p->payment_reference ?: '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $fmt($p->amount) }} DH</td>
                        <td class="px-4 py-3 text-center">{{ $row['has_justificatif'] ? '📎' : '—' }}</td>
                        <td class="px-4 py-3">
                            @if($row['cancelled'])
                                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-700">Annulé</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Validé</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('purchases.payments.show', $p) }}" class="text-[#0a5d8a]" title="Voir">👁️</a>
                            @unless($row['cancelled'])
                                <a href="{{ route('purchases.payments.edit', $p) }}" class="ml-2 text-amber-700" title="Modifier">✏️</a>
                                <button type="button" class="ml-2" title="Annuler" onclick="document.getElementById('cancel-{{ $p->id }}').classList.remove('hidden')">🗑️</button>
                            @endunless
                        </td>
                    </tr>
                    @unless($row['cancelled'])
                    <tr id="cancel-{{ $p->id }}" class="hidden">
                        <td colspan="{{ !empty($showSupplier) ? 10 : 9 }}" class="px-4 py-4 bg-red-50">
                            <form method="POST" action="{{ route('purchases.payments.cancel', $p) }}" class="space-y-2">
                                @csrf
                                <p class="text-sm text-red-800">Voulez-vous vraiment annuler ce règlement ? Son affectation aux factures sera annulée et les soldes seront automatiquement recalculés. Le règlement restera conservé dans l’historique pour la traçabilité.</p>
                                <label class="block text-sm font-medium">Motif de l’annulation *</label>
                                <input type="text" name="cancellation_reason" required class="w-full rounded-lg border-gray-300" placeholder="Ex. : règlement saisi en double">
                                <div class="flex gap-2">
                                    <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded-lg text-sm">Confirmer l’annulation</button>
                                    <button type="button" class="px-3 py-1.5 border rounded-lg text-sm" onclick="document.getElementById('cancel-{{ $p->id }}').classList.add('hidden')">Fermer</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endunless
                @empty
                    <tr><td colspan="{{ !empty($showSupplier) ? 10 : 9 }}" class="px-4 py-8 text-center text-gray-500">Aucun règlement enregistré</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
