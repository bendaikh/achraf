@extends('layouts.with-sidebar')

@section('title', 'Contrôle import règlement')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Contrôle avant validation</h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $import->original_filename }}
                    · {{ $import->status === 'draft' ? 'Brouillon' : 'Validé' }}
                    · {{ $import->lines_count }} ligne(s)
                </p>
            </div>
            <a href="{{ route('sales.payments.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Retour</a>
        </div>
    </header>

    <div class="p-8">
        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <ul class="text-sm text-red-700 list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                <div class="text-xs text-green-700 uppercase font-medium">Vert — trouvée</div>
                <div class="text-2xl font-bold text-green-800">{{ $import->matched_count }}</div>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <div class="text-xs text-amber-700 uppercase font-medium">Orange — ambiguë</div>
                <div class="text-2xl font-bold text-amber-800">{{ $import->ambiguous_count }}</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="text-xs text-red-700 uppercase font-medium">Rouge — non trouvée</div>
                <div class="text-2xl font-bold text-red-800">{{ $import->unmatched_count }}</div>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                <div class="text-xs text-gray-600 uppercase font-medium">Doublons</div>
                <div class="text-2xl font-bold text-gray-800">{{ $import->duplicate_count }}</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking / réf. fichier</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commande trouvée</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant fichier</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attendu</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Écart</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($import->lines as $line)
                            @php
                                $rowClass = match($line->match_status) {
                                    'matched' => 'bg-green-50/60',
                                    'ambiguous' => 'bg-amber-50/60',
                                    'duplicate' => 'bg-gray-100',
                                    default => 'bg-red-50/60',
                                };
                            @endphp
                            <tr class="{{ $rowClass }} {{ $line->exclude ? 'opacity-50' : '' }}">
                                <td class="px-3 py-3">{{ $line->line_number }}</td>
                                <td class="px-3 py-3">
                                    <div class="font-mono">{{ $line->file_tracking ?? $line->file_reference ?? '—' }}</div>
                                    @if($line->file_order_ref)
                                        <div class="text-xs text-gray-500">{{ $line->file_order_ref }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if($line->invoice)
                                        <div class="font-medium">{{ $line->posSale->ticket_number ?? $line->invoice->invoice_number }}</div>
                                        <div class="text-xs text-gray-500">{{ $line->invoice->invoice_number }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3">{{ $line->invoice?->client?->name ?? '—' }}</td>
                                <td class="px-3 py-3 font-medium">{{ $line->file_amount !== null ? number_format($line->file_amount, 2) : '—' }}</td>
                                <td class="px-3 py-3">{{ $line->expected_amount !== null ? number_format($line->expected_amount, 2) : '—' }}</td>
                                <td class="px-3 py-3">
                                    @if($line->amount_status === 'ok')
                                        <span class="text-green-700">OK</span>
                                    @elseif($line->amount_difference !== null)
                                        <span class="text-amber-700 font-medium">{{ number_format($line->amount_difference, 2) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if($line->match_status === 'matched')
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800">Trouvée</span>
                                    @elseif($line->match_status === 'ambiguous')
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-800">Ambiguë</span>
                                    @elseif($line->match_status === 'duplicate')
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-700">Doublon</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">Non trouvée</span>
                                    @endif
                                    @if($line->amount_status === 'overpayment')
                                        <div class="text-xs text-amber-700 mt-1">Trop-perçu</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 min-w-[220px]">
                                    @if($import->isDraft())
                                        <form method="POST" action="{{ route('sales.payments.import.line', [$import, $line]) }}" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            @if(in_array($line->match_status, ['unmatched', 'ambiguous'], true) || !$line->invoice_id)
                                                <select name="invoice_id" class="w-full text-xs rounded border-gray-300" onchange="this.form.submit()">
                                                    <option value="">Rattacher une facture…</option>
                                                    @foreach($searchableInvoices as $inv)
                                                        <option value="{{ $inv->id }}" @selected($line->invoice_id === $inv->id)>
                                                            {{ $inv->invoice_number }} · {{ $inv->posSale->ticket_number ?? '' }} · {{ $inv->client->name ?? '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                            @if($line->amount_status === 'overpayment')
                                                <label class="flex items-center gap-1 text-xs text-amber-800">
                                                    <input type="checkbox" name="allow_overpayment" value="1" @checked($line->allow_overpayment) onchange="this.form.submit()">
                                                    Autoriser trop-perçu
                                                </label>
                                            @endif
                                            <label class="flex items-center gap-1 text-xs text-gray-600">
                                                <input type="checkbox" name="exclude" value="1" @checked($line->exclude) onchange="this.form.submit()">
                                                Exclure
                                            </label>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-500">{{ $line->match_notes }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($import->isDraft())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 max-w-2xl">
                <h3 class="text-lg font-semibold mb-2">Valider les paiements</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Seules les lignes <strong>vertes</strong> non exclues seront enregistrées.
                    Corrigez les lignes orange/rouges ou excluez-les. La trésorerie sera mise à jour automatiquement.
                </p>
                <form method="POST" action="{{ route('sales.payments.import.validate', $import) }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                            <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement *</label>
                            <select name="payment_method" required class="w-full rounded-lg border-gray-300">
                                <option value="Virement bancaire">Virement bancaire</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                                <option value="Carte bancaire">Carte bancaire</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Référence du règlement</label>
                            <input type="text" name="payment_reference" class="w-full rounded-lg border-gray-300" value="Import {{ $import->original_filename }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire</label>
                            <input type="text" name="notes" class="w-full rounded-lg border-gray-300">
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700"
                            onclick="return confirm('Valider définitivement les paiements prêts ?')">
                            Valider les paiements
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</main>
@endsection
