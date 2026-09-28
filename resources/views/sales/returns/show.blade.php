@extends('layouts.with-sidebar')

@section('title', 'Contrôle retour '.$return->reference)
@section('sidebar_page_title', 'Ventes')

@section('main')
<main class="flex-1 overflow-y-auto bg-gray-100">
    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto pb-16">
        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-800 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 text-sm">{{ session('error') }}</div>
        @endif

        <div class="mb-4">
            <a href="{{ route('sales.returns.index') }}" class="text-sm text-[#0a5d8a] hover:underline">← Retours</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">{{ $return->reference }}</h1>
                @php
                    $badge = match($return->status) {
                        'validated' => 'bg-emerald-100 text-emerald-800',
                        'cancelled' => 'bg-slate-100 text-slate-600',
                        default => 'bg-amber-100 text-amber-900',
                    };
                @endphp
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $badge }}">{{ $return->status }}</span>
            </div>
            <p class="text-sm text-slate-600 mt-1">Le scan seul n’augmente pas le stock, ne crée pas d’avoir et ne synchronise pas Shopify.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <h2 class="text-xs font-semibold uppercase text-slate-500 mb-2">Commande</h2>
                <p class="font-semibold">{{ $context['order']->ticket_number }}</p>
                <p class="text-xs text-slate-500 mt-1">Canal : {{ $context['channel'] ?: '—' }}</p>
                <p class="text-xs text-slate-500">Tracking : {{ $context['tracking'] ?: '—' }}</p>
                <a href="{{ route('orders.show', $context['order']) }}" class="text-xs text-[#0a5d8a] font-semibold hover:underline mt-2 inline-block">Voir commande</a>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <h2 class="text-xs font-semibold uppercase text-slate-500 mb-2">Client / Facture</h2>
                <p class="font-semibold">{{ $context['client']?->name ?: '—' }}</p>
                <p class="text-xs text-slate-500 mt-1">Facture : {{ $context['invoice']?->invoice_number ?: 'Aucune' }}</p>
                <p class="text-xs text-slate-500">Paiement : {{ $context['paid'] ? 'Encaissé' : 'Non encaissé' }}</p>
                @if($context['cod_uncollected'])
                    <p class="mt-2 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1">
                        COD non encaissé — ne pas inventer de remboursement.
                    </p>
                @endif
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <h2 class="text-xs font-semibold uppercase text-slate-500 mb-2">Documents</h2>
                <p class="text-xs text-slate-600">BL liés : {{ $context['delivery_notes']->count() ?: '—' }}</p>
                @foreach($context['delivery_notes'] as $dn)
                    <div class="text-xs">{{ $dn->delivery_note_number }}</div>
                @endforeach
                <p class="text-xs text-slate-600 mt-2">Paiements facture : {{ $context['payments']->count() }}</p>
                @if($return->creditNote)
                    <a href="{{ route('credit-notes.show', $return->creditNote) }}" class="text-xs text-[#0a5d8a] font-semibold hover:underline mt-2 inline-block">
                        Avoir {{ $return->creditNote->credit_note_number }}
                    </a>
                @endif
            </div>
        </div>

        @if($return->isDraft())
            <form method="POST" action="{{ route('sales.returns.update', $return) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Article</th>
                                <th class="px-3 py-2 text-right">Attendu</th>
                                <th class="px-3 py-2 text-right">Reçu</th>
                                <th class="px-3 py-2">État</th>
                                <th class="px-3 py-2">Dépôt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($return->lines as $line)
                                <tr>
                                    <td class="px-3 py-3">
                                        <div class="font-medium">{{ $line->designation }}</div>
                                        <div class="text-xs font-mono text-slate-500">{{ $line->sku }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-right">{{ $line->quantity_expected }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <input type="number" min="0" name="lines[{{ $line->id }}][quantity_received]"
                                               value="{{ $line->quantity_received }}"
                                               class="w-20 rounded-lg border-slate-300 text-sm text-right">
                                    </td>
                                    <td class="px-3 py-3">
                                        <select name="lines[{{ $line->id }}][condition]" class="rounded-lg border-slate-300 text-sm condition-select" data-line="{{ $line->id }}">
                                            @foreach($conditions as $value => $label)
                                                <option value="{{ $value }}" @selected($line->condition === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <p class="text-[10px] text-slate-500 mt-1 condition-hint" data-line="{{ $line->id }}">
                                            @if($line->condition === 'vendable') → stock disponible après validation
                                            @elseif(in_array($line->condition, ['damaged','to_check'])) → SAV / quarantaine (non vendable)
                                            @else → aucun stock
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <select name="lines[{{ $line->id }}][warehouse_id]" class="rounded-lg border-slate-300 text-sm wh-select" data-line="{{ $line->id }}">
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}" @selected((int)$line->warehouse_id === (int)$wh->id)>
                                                    {{ $wh->name }}@unless($wh->isSellable()) (non vendable)@endunless
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($sav)
                                            <input type="hidden" id="sav-id" value="{{ $sav->id }}">
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-4 space-y-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="create_credit_note" value="0">
                        <input type="checkbox" name="create_credit_note" value="1" class="rounded text-[#0a5d8a]" @checked($return->create_credit_note)>
                        Créer un avoir si facture validée (jamais supprimer la facture)
                    </label>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 text-sm">{{ $return->notes }}</textarea>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="px-4 py-2.5 bg-white border border-slate-300 rounded-lg text-sm font-semibold">Enregistrer brouillon</button>
                </div>
            </form>

            <div class="mt-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('sales.returns.validate', $return) }}" onsubmit="return confirm('Valider ce retour ? Stock et avoir éventuel seront créés.')">
                    @csrf
                    <button class="px-4 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-semibold">Valider retour</button>
                </form>
                <form method="POST" action="{{ route('sales.returns.cancel', $return) }}" onsubmit="return confirm('Annuler ce brouillon ?')">
                    @csrf
                    <button class="px-4 py-2.5 text-red-700 text-sm font-semibold">Annuler brouillon</button>
                </form>
            </div>

            <script>
                document.querySelectorAll('.condition-select').forEach(function (sel) {
                    sel.addEventListener('change', function () {
                        var line = this.getAttribute('data-line');
                        var hint = document.querySelector('.condition-hint[data-line="' + line + '"]');
                        var wh = document.querySelector('.wh-select[data-line="' + line + '"]');
                        var sav = document.getElementById('sav-id');
                        var v = this.value;
                        if (hint) {
                            if (v === 'vendable') hint.textContent = '→ stock disponible après validation';
                            else if (v === 'damaged' || v === 'to_check') hint.textContent = '→ SAV / quarantaine (non vendable)';
                            else hint.textContent = '→ aucun stock';
                        }
                        if ((v === 'damaged' || v === 'to_check') && sav && wh) {
                            wh.value = sav.value;
                        }
                    });
                });
            </script>
        @else
            <div class="bg-white border border-slate-200 rounded-xl overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Article</th>
                            <th class="px-3 py-2 text-right">Reçu</th>
                            <th class="px-3 py-2">État</th>
                            <th class="px-3 py-2">Dépôt</th>
                            <th class="px-3 py-2">Stock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($return->lines as $line)
                            <tr>
                                <td class="px-3 py-3">
                                    <div class="font-medium">{{ $line->designation }}</div>
                                    <div class="text-xs font-mono text-slate-500">{{ $line->sku }}</div>
                                </td>
                                <td class="px-3 py-3 text-right">{{ $line->quantity_received }}</td>
                                <td class="px-3 py-3">{{ $line->conditionLabel() }}</td>
                                <td class="px-3 py-3">{{ $line->warehouse?->name ?: '—' }}</td>
                                <td class="px-3 py-3">{{ $line->stock_applied ? 'Appliqué' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($return->notes)
                <p class="mt-4 text-sm text-slate-600">{{ $return->notes }}</p>
            @endif
        @endif
    </div>
</main>
@endsection
