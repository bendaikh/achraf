@extends('layouts.with-sidebar')

@section('title', 'Détails du fournisseur')

@section('sidebar_page_title', 'Fournisseur')

@section('main')
@php
    $dash = fn ($value) => filled($value) ? $value : 'N/A';
@endphp
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('suppliers.index') }}" class="hover:text-blue-600">Fournisseurs</a>
                <span>/</span>
                <span class="text-gray-900">{{ $supplier->name }}</span>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $supplier->name }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="text-sm text-gray-500">{{ $supplier->code ?? 'Sans code' }}</span>
                        @php
                            $statusColors = [
                                'actif' => 'bg-green-100 text-green-800',
                                'inactif' => 'bg-gray-100 text-gray-800',
                                'bloque' => 'bg-red-100 text-red-800',
                            ];
                        @endphp
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$supplier->status] ?? 'bg-gray-100 text-gray-800' }}">
                            {{ $supplier->statusLabel() }}
                        </span>
                    </div>
                </div>
                <a href="{{ route('suppliers.edit', $supplier) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150 inline-flex items-center space-x-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <span>Modifier</span>
                </a>
            </div>
        </div>

        <div class="space-y-6">
            @isset($statement)
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Situation financière</h2>
                        <a href="{{ route('purchases.payments.settle', $supplier) }}" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Payer</a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                        <div class="rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Total achats / factures</p><p class="text-lg font-bold">{{ number_format($statement['total_invoices'], 2) }} DH</p></div>
                        <div class="rounded-lg bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Total avoirs</p><p class="text-lg font-bold text-emerald-700">- {{ number_format($statement['total_credits'], 2) }} DH</p></div>
                        <div class="rounded-lg bg-gray-50 p-3"><p class="text-xs text-gray-500">Total règlements</p><p class="text-lg font-bold">{{ number_format($statement['total_payments'], 2) }} DH</p></div>
                        <div class="rounded-lg bg-sky-50 p-3"><p class="text-xs text-sky-700">Avances</p><p class="text-lg font-bold text-sky-700">- {{ number_format($statement['total_advances'], 2) }} DH</p></div>
                        <div class="rounded-lg bg-[#0a5d8a] p-3 text-white"><p class="text-xs opacity-80">Solde fournisseur</p><p class="text-lg font-bold">{{ number_format($statement['balance'], 2) }} DH</p></div>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800 mb-2">Historique du compte</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Type</th>
                                    <th class="px-3 py-2 text-left">Référence</th>
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-right">Débit (+)</th>
                                    <th class="px-3 py-2 text-right">Crédit (-)</th>
                                    <th class="px-3 py-2 text-right">Solde</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse($statement['ledger'] as $entry)
                                    <tr>
                                        <td class="px-3 py-2">{{ $entry['date'] ? \Carbon\Carbon::parse($entry['date'])->format('d/m/Y') : '—' }}</td>
                                        <td class="px-3 py-2">{{ $entry['type_label'] }}</td>
                                        <td class="px-3 py-2">
                                            @if($entry['url'])
                                                <a href="{{ $entry['url'] }}" class="text-[#0a5d8a]">{{ $entry['reference'] }}</a>
                                            @else
                                                {{ $entry['reference'] }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-gray-600">{{ $entry['description'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '—' }}</td>
                                        <td class="px-3 py-2 text-right text-emerald-700">{{ $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '—' }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($entry['balance'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Aucun mouvement</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">Règle : Factures – Avoirs – Paiements – Avances = solde réel.</p>
                </div>
            </div>
            @isset($paymentHistory)
            @include('purchases.payments.partials.history-table', ['rows' => $paymentHistory, 'supplier' => $supplier])
            @endisset
            @endisset
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations générales</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><p class="text-sm text-gray-500">Nom du fournisseur</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->name) }}</p></div>
                        <div><p class="text-sm text-gray-500">Code</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->code) }}</p></div>
                        <div><p class="text-sm text-gray-500">Raison sociale</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->legal_name) }}</p></div>
                        <div><p class="text-sm text-gray-500">Nom commercial</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->trade_name) }}</p></div>
                        <div class="md:col-span-2"><p class="text-sm text-gray-500">Adresse</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->address) }}</p></div>
                        <div><p class="text-sm text-gray-500">Ville</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->ville ?? $supplier->city) }}</p></div>
                        <div><p class="text-sm text-gray-500">Région</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->region) }}</p></div>
                        <div><p class="text-sm text-gray-500">Code postal</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->postal_code) }}</p></div>
                        <div><p class="text-sm text-gray-500">Pays</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->country) }}</p></div>
                        <div><p class="text-sm text-gray-500">Téléphone</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->phone) }}</p></div>
                        <div><p class="text-sm text-gray-500">WhatsApp</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->whatsapp) }}</p></div>
                        <div><p class="text-sm text-gray-500">Email</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->email) }}</p></div>
                        <div><p class="text-sm text-gray-500">Site web</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->website) }}</p></div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Informations juridiques</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><p class="text-sm text-gray-500">RC</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->rc) }}</p></div>
                        <div><p class="text-sm text-gray-500">Ville du RC</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->rc_city) }}</p></div>
                        <div><p class="text-sm text-gray-500">ICE</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->ice) }}</p></div>
                        <div><p class="text-sm text-gray-500">IF</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->fiscal_identifier) }}</p></div>
                        <div><p class="text-sm text-gray-500">TP / Patente</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->tp) }}</p></div>
                        <div><p class="text-sm text-gray-500">Forme juridique</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->legal_form) }}</p></div>
                        <div><p class="text-sm text-gray-500">Date de création</p><p class="text-base font-medium text-gray-900">{{ $supplier->company_created_at?->format('d/m/Y') ?? 'N/A' }}</p></div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Contact principal</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><p class="text-sm text-gray-500">Nom</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->contact_name) }}</p></div>
                        <div><p class="text-sm text-gray-500">Fonction</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->contact_role) }}</p></div>
                        <div><p class="text-sm text-gray-500">Téléphone</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->contact_phone) }}</p></div>
                        <div><p class="text-sm text-gray-500">Mobile</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->contact_mobile) }}</p></div>
                        <div><p class="text-sm text-gray-500">Email</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->contact_email) }}</p></div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Coordonnées bancaires</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><p class="text-sm text-gray-500">Banque</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->bank_name) }}</p></div>
                        <div><p class="text-sm text-gray-500">Titulaire</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->bank_account_holder) }}</p></div>
                        <div><p class="text-sm text-gray-500">RIB</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->rib) }}</p></div>
                        <div><p class="text-sm text-gray-500">IBAN</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->iban) }}</p></div>
                        <div><p class="text-sm text-gray-500">SWIFT / BIC</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->swift_bic) }}</p></div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Conditions commerciales</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><p class="text-sm text-gray-500">Mode de paiement</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->paymentMethodLabel()) }}</p></div>
                        <div><p class="text-sm text-gray-500">Délai de paiement</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->paymentTermsLabel()) }}</p></div>
                        <div><p class="text-sm text-gray-500">Devise</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->currency) }}</p></div>
                        <div><p class="text-sm text-gray-500">Remise (%)</p><p class="text-base font-medium text-gray-900">{{ $supplier->discount_percent !== null ? $supplier->discount_percent . ' %' : 'N/A' }}</p></div>
                        <div><p class="text-sm text-gray-500">Montant min. commande</p><p class="text-base font-medium text-gray-900">{{ $supplier->min_order_amount !== null ? number_format((float) $supplier->min_order_amount, 2, ',', ' ') : 'N/A' }}</p></div>
                        <div><p class="text-sm text-gray-500">Délai de livraison</p><p class="text-base font-medium text-gray-900">{{ $supplier->delivery_lead_days !== null ? $supplier->delivery_lead_days . ' jours' : 'N/A' }}</p></div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Gestion interne</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><p class="text-sm text-gray-500">Statut</p><p class="text-base font-medium text-gray-900">{{ $supplier->statusLabel() }}</p></div>
                        <div><p class="text-sm text-gray-500">Catégorie</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->category) }}</p></div>
                        <div><p class="text-sm text-gray-500">Responsable interne</p><p class="text-base font-medium text-gray-900">{{ $dash($supplier->internalOwner?->name) }}</p></div>
                        <div class="md:col-span-2"><p class="text-sm text-gray-500">Notes</p><p class="text-base font-medium text-gray-900 whitespace-pre-wrap">{{ $dash($supplier->notes) }}</p></div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Documents joints</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach(\App\Models\Supplier::DOCUMENT_FIELDS as $field => $label)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <p class="text-sm font-medium text-gray-800 mb-2">{{ $label }}</p>
                                @if($supplier->{$field})
                                    <a href="{{ $supplier->documentUrl($field) }}" target="_blank" class="text-sm text-blue-600 hover:underline">Ouvrir le document</a>
                                @else
                                    <p class="text-sm text-gray-400">Aucun fichier</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
