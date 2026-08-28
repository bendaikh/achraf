@extends('layouts.with-sidebar')

@section('title', 'Liste des factures')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Liste des factures</h2>
                    <p class="text-sm text-gray-600 mt-1">Gérer toutes vos factures clients</p>
                </div>
                <a href="{{ route('invoices.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                    + Créer une facture
                </a>
            </div>
        </header>

        <div class="p-8">
            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-lg">
                    <p class="text-sm text-amber-800">{{ session('warning') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            @endif

            <x-bulk-import-panel
                label="Factures"
                :template-route="route('invoices.import.template')"
                :import-route="route('invoices.import')"
            />

            <x-table-filters
                :action="route('invoices.index')"
                search-placeholder="N° facture, client, commande..."
                grid-cols="md:grid-cols-5 lg:grid-cols-7"
            >
                <div>
                    <label for="commercial_status" class="block text-sm font-medium text-gray-700 mb-1">Statut commercial</label>
                    <select name="commercial_status" id="commercial_status" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                        <option value="">Tous</option>
                        @foreach($commercialStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('commercial_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="source" class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                    <select name="source" id="source" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]">
                        <option value="">Toutes</option>
                        <option value="shopify" @selected(request('source') === 'shopify')>Shopify</option>
                        <option value="jumia" @selected(request('source') === 'jumia')>Jumia</option>
                        <option value="libromart" @selected(request('source') === 'libromart')>Vente directe</option>
                    </select>
                </div>
            </x-table-filters>

            <x-table-list-toolbar table-id="invoices" />



            <x-table-bulk-bar export-type="invoices" item-label="facture(s)" />

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table data-lm-table="invoices" class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <x-table-checkbox-header export-type="invoices" />
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-numero column-numero" data-lm-col="numero">
                                    Numéro
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-client column-client" data-lm-col="client">
                                    Client
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-commande column-commande" data-lm-col="commande">
                                    N° commande
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-origine column-origine" data-lm-col="origine">
                                    Source
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-statut-commercial column-statut-commercial" data-lm-col="statut_commercial">
                                    Statut commercial
                                </th>
                                <x-table-sort-header column="invoice_date" colKey="date" label="Date" :default="true" default-direction="desc" />
                                <x-table-sort-header column="due_date" colKey="echeance" label="Échéance" default-direction="desc" />
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-devise column-devise" data-lm-col="devise">
                                    Devise
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-total column-total" data-lm-col="total">
                                    Montant initial
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-avoirs column-avoirs" data-lm-col="avoirs">
                                    Avoirs
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-net column-net" data-lm-col="net">
                                    Net
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-document column-document" data-lm-col="document">
                                    Document généré
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider lm-col lm-col-actions column-actions" data-lm-col="actions">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($invoices as $invoice)
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <x-table-checkbox-cell export-type="invoices" :id="$invoice->id" />
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-numero column-numero" data-lm-col="numero">
                                        <x-table-show-link :href="route('invoices.show', $invoice)" :label="$invoice->invoice_number" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-client column-client" data-lm-col="client">
                                        <div class="text-sm text-gray-900">{{ $invoice->client->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-commande column-commande" data-lm-col="commande">
                                        @if($invoice->posSale)
                                            <a href="{{ route('orders.show', $invoice->posSale) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                {{ $invoice->posSale->ticket_number }}
                                            </a>
                                        @else
                                            <span class="text-sm text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-origine column-origine" data-lm-col="origine">
                                        @php
                                            $src = $invoice->source ?? $invoice->posSale?->source;
                                        @endphp
                                        @if($src === 'shopify')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Shopify</span>
                                        @elseif($src === 'jumia')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Jumia</span>
                                        @elseif($invoice->is_auto_generated)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Auto</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Directe</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-statut-commercial column-statut-commercial" data-lm-col="statut_commercial">
                                        @php
                                            $status = $invoice->commercial_status ?? 'normal';
                                            $badge = \App\Support\InvoiceCommercialStatus::badgeClasses()[$status] ?? 'bg-gray-100 text-gray-800';
                                            $label = \App\Support\InvoiceCommercialStatus::labels()[$status] ?? $status;
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ strtoupper($label) }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-date column-date" data-lm-col="date">
                                        <div class="text-sm text-gray-900">{{ $invoice->invoice_date->format('d/m/Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-echeance column-echeance" data-lm-col="echeance">
                                        <div class="text-sm text-gray-900">{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-devise column-devise" data-lm-col="devise">
                                        <div class="text-sm text-gray-900">{{ $invoice->currency }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-total column-total" data-lm-col="total">
                                        <div class="text-sm font-semibold text-gray-900">{{ number_format($invoice->computed_total, 2) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-avoirs column-avoirs" data-lm-col="avoirs">
                                        @if($invoice->total_credits > 0)
                                            <div class="text-sm font-semibold text-red-600">-{{ number_format($invoice->total_credits, 2) }}</div>
                                        @else
                                            <span class="text-sm text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-net column-net" data-lm-col="net">
                                        <div class="text-sm font-semibold text-gray-900">{{ number_format($invoice->net_sale, 2) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap lm-col lm-col-document column-document" data-lm-col="document">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-50 text-blue-800">
                                            PDF Libromart
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium lm-col lm-col-actions column-actions" data-lm-col="actions">
                                        <div class="flex items-center space-x-3">
                                            <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-900" title="Voir">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </a>
                                            <a href="{{ route('invoices.pdf', $invoice) }}" class="text-gray-800 hover:text-gray-950" title="Télécharger PDF">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            </a>
                                            <a href="{{ route('invoices.print', $invoice) }}?no_print=1" target="_blank" class="text-green-600 hover:text-green-900" title="Imprimer">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                                </svg>
                                            </a>
                                            <a href="{{ route('invoices.payments.index', $invoice) }}" class="text-indigo-600 hover:text-indigo-900" title="Règlement de paiement">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                            </a>
                                            <a href="{{ route('invoices.edit', $invoice) }}" class="text-yellow-600 hover:text-yellow-900" title="Modifier">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                            <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette facture?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Supprimer">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <p class="mt-2 text-sm text-gray-500">Aucune facture trouvée</p>
                                            <a href="{{ route('invoices.create') }}" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                                                Créer votre première facture
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                <x-table-pagination :paginator="$invoices" :bordered="false" item-label="factures" />
            </div>
        </div>
    </main>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection
