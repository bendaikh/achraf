@extends('layouts.with-sidebar')

@section('title', 'Contrôle import - Achats')

@section('main')
<main class="flex-1 w-full min-w-0" x-data="purchaseImportReview(@js($import->statusPayload()), @js($statusUrl ?? route('purchases.payments.import.status', $import)))">
    <header class="bg-white shadow-sm border-b sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Contrôle avant validation</h2>
                <p class="text-sm text-gray-600">
                    {{ $import->original_filename }}
                    · <span x-text="statusLabelText()"></span>
                    · <span x-text="(status.total_rows || 0) + ' ligne(s)'"></span>
                </p>
            </div>
            <a href="{{ route('purchases.payments.index') }}" class="px-4 py-2 bg-gray-100 rounded-lg text-sm">Retour</a>
        </div>
    </header>
    <div class="p-8">
        @if(session('success'))
            <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <div x-show="isAnalyzing" x-cloak class="mb-6 bg-white rounded-xl border border-blue-200 p-6">
            <div class="flex items-start gap-3">
                <div class="mt-1 h-5 w-5 animate-spin rounded-full border-2 border-blue-600 border-t-transparent"></div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-gray-900">Analyse en cours en arrière-plan</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Le fichier est traité hors de la requête HTTP pour éviter les erreurs 504.
                        Cette page se rafraîchira automatiquement dès que le rapprochement sera terminé.
                    </p>
                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                            <span x-text="progressLabel()"></span>
                            <span x-text="(status.progress || 0) + '%'"></span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-[#0a5d8a] transition-all duration-500" :style="'width:' + (status.progress || 0) + '%'"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="status.failed" x-cloak class="mb-6 bg-red-50 border border-red-200 rounded-xl p-6">
            <h3 class="text-lg font-semibold text-red-800">Échec de l’analyse</h3>
            <p class="text-sm text-red-700 mt-2" x-text="status.error_message || 'Une erreur est survenue pendant le traitement du fichier.'"></p>
            <a href="{{ route('purchases.payments.import') }}" class="inline-block mt-4 px-4 py-2 bg-red-600 text-white rounded-lg text-sm">Réessayer</a>
        </div>

        <div x-show="!isAnalyzing && !status.failed">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-green-50 border border-green-200 rounded-xl p-4"><div class="text-xs text-green-700">Trouvée</div><div class="text-2xl font-bold">{{ $import->matched_count }}</div></div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4"><div class="text-xs text-amber-700">Ambiguë</div><div class="text-2xl font-bold">{{ $import->ambiguous_count }}</div></div>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4"><div class="text-xs text-red-700">Non trouvée</div><div class="text-2xl font-bold">{{ $import->unmatched_count }}</div></div>
            <div class="bg-gray-50 border rounded-xl p-4"><div class="text-xs text-gray-600">Doublons</div><div class="text-2xl font-bold">{{ $import->duplicate_count }}</div></div>
        </div>

        <div class="bg-white rounded-xl border overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">#</th>
                        <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Réf. fichier</th>
                        <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Facture</th>
                        <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Fournisseur</th>
                        <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Montant</th>
                        <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Attendu</th>
                        <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Écart</th>
                        <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Statut</th>
                        <th class="px-3 py-3 text-left text-xs uppercase text-gray-500">Action</th>
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
                        <tr class="{{ $rowClass }}">
                            <td class="px-3 py-3">{{ $line->line_number }}</td>
                            <td class="px-3 py-3">{{ $line->file_reference ?? $line->file_order_ref ?? '—' }}</td>
                            <td class="px-3 py-3">{{ $line->supplierInvoice->invoice_number ?? '—' }}</td>
                            <td class="px-3 py-3">{{ $line->supplierInvoice?->supplier?->name ?? '—' }}</td>
                            <td class="px-3 py-3">{{ $line->file_amount !== null ? number_format($line->file_amount, 2) : '—' }}</td>
                            <td class="px-3 py-3">{{ $line->expected_amount !== null ? number_format($line->expected_amount, 2) : '—' }}</td>
                            <td class="px-3 py-3">{{ $line->amount_difference !== null ? number_format($line->amount_difference, 2) : ($line->amount_status === 'ok' ? 'OK' : '—') }}</td>
                            <td class="px-3 py-3">{{ $line->match_status }}</td>
                            <td class="px-3 py-3">
                                @if($import->isDraft())
                                    <form method="POST" action="{{ route('purchases.payments.import.line', [$import, $line]) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="exclude" value="0">
                                        <select name="supplier_invoice_id" class="w-full text-xs rounded border-gray-300 mb-1" onchange="this.form.submit()">
                                            <option value="">Rattacher…</option>
                                            @foreach($searchableInvoices as $inv)
                                                <option value="{{ $inv->id }}" @selected($line->supplier_invoice_id === $inv->id)>{{ $inv->invoice_number }} · {{ $inv->supplier->name ?? '' }}</option>
                                            @endforeach
                                        </select>
                                        <label class="text-xs flex gap-1"><input type="checkbox" name="allow_overpayment" value="1" @checked($line->allow_overpayment) onchange="this.form.submit()"> Trop-perçu</label>
                                        <label class="text-xs flex gap-1"><input type="checkbox" name="exclude" value="1" @checked($line->exclude) onchange="this.form.submit()"> Exclure</label>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($import->isDraft())
            <div class="bg-white rounded-xl border p-6 max-w-2xl">
                <form method="POST" action="{{ route('purchases.payments.import.validate', $import) }}">
                    @csrf
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Date *</label>
                            <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required class="w-full rounded-lg border-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Mode *</label>
                            <select name="payment_method" required class="w-full rounded-lg border-gray-300">
                                <option value="Virement bancaire">Virement bancaire</option>
                                <option value="Espèces">Espèces</option>
                                <option value="Chèque">Chèque</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Référence</label>
                            <input type="text" name="payment_reference" class="w-full rounded-lg border-gray-300" value="Import {{ $import->original_filename }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Commentaire</label>
                            <input type="text" name="notes" class="w-full rounded-lg border-gray-300">
                        </div>
                    </div>
                    <button class="mt-6 px-4 py-2 bg-green-600 text-white rounded-lg text-sm" onclick="return confirm('Valider ?')">Valider les paiements</button>
                </form>
            </div>
        @endif
        </div>
    </div>
</main>

<script>
function purchaseImportReview(initialStatus, statusUrl) {
    return {
        status: initialStatus || {},
        statusUrl: statusUrl,
        pollTimer: null,
        get isAnalyzing() {
            return this.status.status === 'pending' || this.status.status === 'processing';
        },
        init() {
            if (this.isAnalyzing) {
                this.startPolling();
            }
        },
        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => this.pollStatus(), 2500);
            this.pollStatus();
        },
        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },
        async pollStatus() {
            try {
                const response = await fetch(this.statusUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) return;
                const data = await response.json();
                this.status = data;
                if (data.ready || data.failed) {
                    this.stopPolling();
                    if (data.ready) {
                        window.location.reload();
                    }
                }
            } catch (e) {}
        },
        statusLabelText() {
            return {
                pending: 'En file d’attente',
                processing: 'Analyse en cours',
                draft: 'Brouillon',
                validated: 'Validé',
                cancelled: 'Annulé',
                failed: 'Échec',
            }[this.status.status] || this.status.status;
        },
        progressLabel() {
            const processed = this.status.processed_rows || 0;
            const total = this.status.total_rows || 0;
            if (total > 0) {
                return processed + ' / ' + total + ' lignes traitées';
            }
            return this.status.status === 'pending'
                ? 'En attente du démarrage…'
                : 'Lecture et rapprochement du fichier…';
        },
    };
}
</script>
@endsection
