@extends('layouts.with-sidebar')

@section('title', 'Contrôle import règlement')

@section('main')
<main class="flex-1 w-full min-w-0" x-data="importReview()">
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

        <div class="mb-6 bg-white rounded-xl border border-gray-200 p-4">
            <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-gray-600">
                <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-800">1. Import fichier</span>
                <span>→</span>
                <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-800">2. Analyse multi-critères</span>
                <span>→</span>
                <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-800">3. Rapprochement intelligent</span>
                <span>→</span>
                <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-800">4. Résultat vert / orange / rouge</span>
                <span>→</span>
                <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-800">5. Rattacher & enregistrer</span>
            </div>
        </div>

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

        <div class="flex flex-col xl:flex-row gap-6 mb-6">
            <div class="flex-1 min-w-0 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Données fichier</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Correspondance</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant brut</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frais</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Net</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Écart réel</th>
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
                                    $raw = $line->file_raw ?? [];
                                    $carrierStatus = $raw['status'] ?? $raw['statut'] ?? null;
                                    $carrierCity = $raw['ville'] ?? $raw['city'] ?? null;
                                    $clientName = $raw['client'] ?? $raw['nom'] ?? $raw['nom_client'] ?? $raw['destinataire'] ?? null;
                                    $clientPhone = $raw['telephone'] ?? $raw['tel'] ?? $raw['phone'] ?? null;
                                    $pickupDate = $raw['date_de_ramassage'] ?? null;
                                    $deliveryDate = $raw['date_de_livraison'] ?? $raw['date'] ?? null;
                                    $carrier = $raw['transporteur'] ?? $raw['carrier'] ?? $raw['marketplace'] ?? null;
                                    $candidates = is_array($line->candidate_matches) ? $line->candidate_matches : [];
                                    $criteriaLabels = collect($line->match_criteria ?? [])
                                        ->map(fn ($c) => \App\Services\PaymentMatchingService::CRITERIA[$c]['label'] ?? $c)
                                        ->all();
                                    $confidencePercent = 0;
                                    if ($line->match_score && $line->match_criteria) {
                                        $confidencePercent = app(\App\Services\PaymentMatchingService::class)->confidencePercent([
                                            'score' => (int) $line->match_score,
                                            'criteria' => $line->match_criteria,
                                        ]);
                                    }
                                    $linePayload = [
                                        'line_number' => $line->line_number,
                                        'match_status' => $line->match_status,
                                        'file_tracking' => $line->file_tracking,
                                        'file_order_ref' => $line->file_order_ref,
                                        'client_name' => $clientName,
                                        'client_phone' => $clientPhone,
                                        'city' => $carrierCity,
                                        'carrier' => $carrier,
                                        'delivery_date' => $deliveryDate,
                                        'file_amount' => $line->file_amount,
                                        'file_delivery_fees' => $line->file_delivery_fees,
                                        'file_net_amount' => $line->file_net_amount,
                                        'expected_amount' => $line->expected_amount,
                                        'amount_variance' => $line->amount_variance,
                                        'amount_status' => $line->amount_status,
                                        'confidence_percent' => $confidencePercent,
                                        'criteria_labels' => $criteriaLabels,
                                        'match_notes' => $line->match_notes,
                                        'invoice_number' => $line->invoice?->invoice_number,
                                        'order_number' => $line->posSale?->ticket_number,
                                        'invoice_client' => $line->invoice?->client?->name,
                                        'candidates' => array_slice($candidates, 0, 5),
                                    ];
                                @endphp
                                <tr class="{{ $rowClass }} {{ $line->exclude ? 'opacity-50' : '' }} cursor-pointer hover:ring-2 hover:ring-inset hover:ring-blue-200"
                                    @click="selectLine(@js($linePayload))">
                                    <td class="px-3 py-3">{{ $line->line_number }}</td>
                                    <td class="px-3 py-3">
                                        <div class="font-mono">{{ $line->file_tracking ?: '—' }}</div>
                                        @if($line->file_order_ref)
                                            <div class="text-xs text-gray-600">Cmd : {{ $line->file_order_ref }}</div>
                                        @endif
                                        @if($clientName)
                                            <div class="text-xs text-gray-600">{{ $clientName }}</div>
                                        @endif
                                        @if($clientPhone)
                                            <div class="text-xs text-gray-500">{{ $clientPhone }}</div>
                                        @endif
                                        @if($carrierCity)
                                            <div class="text-xs text-gray-500">{{ $carrierCity }}</div>
                                        @endif
                                        @if($deliveryDate || $pickupDate)
                                            <div class="text-xs text-gray-500">{{ $pickupDate ?: '—' }} → {{ $deliveryDate ?: '—' }}</div>
                                        @endif
                                        @if($carrierStatus)
                                            <div class="text-xs text-gray-500">{{ $carrierStatus }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        @if($line->invoice)
                                            <div class="font-medium text-green-800">
                                                {{ $line->posSale->ticket_number ?? '—' }} / {{ $line->invoice->invoice_number }}
                                            </div>
                                            <div class="text-xs text-gray-600">{{ $line->invoice->client?->name }}</div>
                                            @if($confidencePercent > 0)
                                                <div class="text-xs text-green-700 mt-1">Confiance : {{ $confidencePercent }}%</div>
                                            @endif
                                            @if($criteriaLabels)
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @foreach($criteriaLabels as $label)
                                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-green-100 text-green-800">{{ $label }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @elseif($candidates !== [])
                                            <div class="text-xs text-amber-800 font-medium mb-1">
                                                @if($line->match_status === 'unmatched')
                                                    Pistes après recherche multi-critères :
                                                @else
                                                    Candidats probables :
                                                @endif
                                            </div>
                                            @foreach(array_slice($candidates, 0, 3) as $candidate)
                                                <div class="text-xs text-gray-700">
                                                    {{ $candidate['invoice_number'] ?? '—' }}
                                                    · {{ $candidate['client_name'] ?? '—' }}
                                                    · {{ isset($candidate['amount']) ? number_format($candidate['amount'], 2).' DH' : '—' }}
                                                    @if(! empty($candidate['confidence_percent']))
                                                        <span class="text-amber-700">({{ $candidate['confidence_percent'] }}%)</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @else
                                            <span class="text-red-700 text-xs">Non trouvée</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 font-medium">{{ $line->file_amount !== null ? number_format($line->file_amount, 2) : '—' }}</td>
                                    <td class="px-3 py-3">{{ $line->file_delivery_fees !== null ? number_format($line->file_delivery_fees, 2) : '—' }}</td>
                                    <td class="px-3 py-3">{{ $line->file_net_amount !== null ? number_format($line->file_net_amount, 2) : '—' }}</td>
                                    <td class="px-3 py-3">
                                        @if($line->amount_status === 'ok')
                                            <span class="text-green-700 font-medium">0,00</span>
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
                                        @if($line->match_notes)
                                            <div class="text-xs text-gray-600 mt-1">{{ $line->match_notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 min-w-[240px]" @click.stop>
                                        @if($import->isDraft())
                                            <form method="POST" action="{{ route('sales.payments.import.line', [$import, $line]) }}" class="space-y-2">
                                                @csrf
                                                @method('PATCH')
                                                @if(in_array($line->match_status, ['unmatched', 'ambiguous'], true) || ! $line->invoice_id)
                                                    <select name="invoice_id" class="w-full text-xs rounded border-gray-300" onchange="this.form.submit()">
                                                        <option value="">
                                                            @if($line->match_status === 'ambiguous' || $candidates !== [])
                                                                Choisir la facture…
                                                            @else
                                                                Rattacher manuellement…
                                                            @endif
                                                        </option>
                                                        @if($candidates !== [])
                                                            @foreach($candidates as $candidate)
                                                                <option value="{{ $candidate['invoice_id'] ?? '' }}" @selected($line->invoice_id === ($candidate['invoice_id'] ?? null))>
                                                                    {{ $candidate['invoice_number'] ?? '—' }}
                                                                    · {{ $candidate['client_name'] ?? '' }}
                                                                    · {{ isset($candidate['amount']) ? number_format($candidate['amount'], 2).' DH' : '' }}
                                                                </option>
                                                            @endforeach
                                                            <option disabled>──────────</option>
                                                        @endif
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

            <div class="xl:w-96 shrink-0">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sticky top-28">
                    <template x-if="!selected">
                        <div class="text-sm text-gray-500">Cliquez sur une ligne pour voir le détail du rapprochement.</div>
                    </template>
                    <template x-if="selected">
                        <div>
                            <div class="mb-4 px-3 py-2 rounded-lg text-sm font-medium"
                                :class="{
                                    'bg-green-100 text-green-800': selected.match_status === 'matched',
                                    'bg-amber-100 text-amber-800': selected.match_status === 'ambiguous',
                                    'bg-red-100 text-red-800': selected.match_status === 'unmatched',
                                    'bg-gray-100 text-gray-700': selected.match_status === 'duplicate',
                                }"
                                x-text="statusLabel(selected.match_status)"></div>

                            <h4 class="text-sm font-semibold text-gray-900 mb-2">Informations paiement (fichier)</h4>
                            <dl class="text-xs text-gray-700 space-y-1 mb-4">
                                <div><dt class="inline text-gray-500">Tracking :</dt> <span x-text="selected.file_tracking || '—'"></span></div>
                                <div><dt class="inline text-gray-500">Client :</dt> <span x-text="selected.client_name || '—'"></span></div>
                                <div><dt class="inline text-gray-500">Téléphone :</dt> <span x-text="selected.client_phone || '—'"></span></div>
                                <div><dt class="inline text-gray-500">Ville :</dt> <span x-text="selected.city || '—'"></span></div>
                                <div><dt class="inline text-gray-500">Transporteur :</dt> <span x-text="selected.carrier || '—'"></span></div>
                                <div><dt class="inline text-gray-500">Montant brut :</dt> <span x-text="formatMoney(selected.file_amount)"></span></div>
                                <div><dt class="inline text-gray-500">Frais livraison :</dt> <span x-text="formatMoney(selected.file_delivery_fees)"></span></div>
                                <div><dt class="inline text-gray-500">Net encaissé :</dt> <span x-text="formatMoney(selected.file_net_amount)"></span></div>
                                <div><dt class="inline text-gray-500">Date :</dt> <span x-text="selected.delivery_date || '—'"></span></div>
                            </dl>

                            <template x-if="selected.invoice_number">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 mb-2">Correspondance trouvée</h4>
                                    <dl class="text-xs text-gray-700 space-y-1 mb-4">
                                        <div><dt class="inline text-gray-500">Facture :</dt> <span x-text="selected.invoice_number"></span></div>
                                        <div><dt class="inline text-gray-500">Commande :</dt> <span x-text="selected.order_number || '—'"></span></div>
                                        <div><dt class="inline text-gray-500">Client :</dt> <span x-text="selected.invoice_client || '—'"></span></div>
                                        <div><dt class="inline text-gray-500">Montant attendu :</dt> <span x-text="formatMoney(selected.expected_amount)"></span></div>
                                        <div><dt class="inline text-gray-500">Écart après frais :</dt>
                                            <span class="font-medium" :class="selected.amount_status === 'ok' ? 'text-green-700' : 'text-amber-700'"
                                                x-text="selected.amount_status === 'ok' ? '0,00 DH' : formatMoney(selected.amount_variance)"></span>
                                        </div>
                                        <div><dt class="inline text-gray-500">Confiance :</dt> <span x-text="(selected.confidence_percent || 0) + '%'"></span></div>
                                    </dl>
                                </div>
                            </template>

                            <template x-if="selected.candidates && selected.candidates.length && !selected.invoice_number">
                                <div class="mb-4">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-2">Candidats</h4>
                                    <template x-for="candidate in selected.candidates" :key="candidate.invoice_id">
                                        <div class="text-xs text-gray-700 mb-1"
                                            x-text="(candidate.invoice_number || '—') + ' · ' + (candidate.client_name || '—') + ' · ' + formatMoney(candidate.amount)"></div>
                                    </template>
                                </div>
                            </template>

                            <template x-if="selected.criteria_labels && selected.criteria_labels.length">
                                <div class="mb-4">
                                    <h4 class="text-sm font-semibold text-gray-900 mb-2">Critères utilisés</h4>
                                    <div class="flex flex-wrap gap-1">
                                        <template x-for="label in selected.criteria_labels" :key="label">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-blue-100 text-blue-800" x-text="label"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-700">
                                <div class="font-medium mb-1">Note automatique (historique facture)</div>
                                <p x-text="autoNote(selected)"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 text-xs text-gray-600">
            <p class="font-medium text-gray-800 mb-2">Ordre de recherche des correspondances</p>
            <ol class="list-decimal pl-5 space-y-1">
                @foreach(\App\Services\PaymentMatchingService::CRITERIA as $key => $criterion)
                    @if($key !== \App\Services\PaymentMatchingService::CRITERION_MEMORY)
                        <li>{{ $criterion['label'] }}</li>
                    @endif
                @endforeach
            </ol>
            <p class="mt-3 text-gray-500">Le tracking absent n'est pas bloquant. L'écart est calculé après prise en compte des frais de livraison.</p>
        </div>

        @if($import->isDraft())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 max-w-2xl">
                <h3 class="text-lg font-semibold mb-2">Valider les paiements</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Seules les lignes <strong>vertes</strong> non exclues seront enregistrées.
                    Les lignes orange demandent un choix entre candidats. Les lignes rouges peuvent être rattachées manuellement ou exclues, uniquement après analyse multi-critères.
                    La trésorerie sera alimentée avec le <strong>net encaissé</strong> lorsque les frais transporteur sont connus.
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

<script>
function importReview() {
    return {
        selected: null,
        selectLine(line) {
            this.selected = line;
        },
        statusLabel(status) {
            return {
                matched: 'TROUVÉE AUTOMATIQUEMENT',
                ambiguous: 'AMBIGUË — CHOIX REQUIS',
                unmatched: 'NON TROUVÉE',
                duplicate: 'DOUBLON',
            }[status] || status;
        },
        formatMoney(value) {
            if (value === null || value === undefined || value === '') return '—';
            return Number(value).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' DH';
        },
        autoNote(line) {
            const gross = this.formatMoney(line.file_amount);
            const fees = this.formatMoney(line.file_delivery_fees);
            const net = this.formatMoney(line.file_net_amount);
            const tracking = line.file_tracking || '—';
            const date = line.delivery_date || '—';
            return `Paiement rapproché via import transporteur — Montant facture : ${gross} — Frais livraison : ${fees} — Net encaissé : ${net} — Tracking : ${tracking} — Date : ${date}.`;
        },
    };
}
</script>
@endsection
