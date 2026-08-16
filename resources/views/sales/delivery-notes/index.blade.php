@extends('layouts.with-sidebar')

@section('title', 'Liste des bons de livraison')

@section('main')
<main class="flex-1 w-full min-w-0">
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
            <div class="px-8 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Liste des bons de livraison</h2>
                    <p class="text-sm text-gray-600 mt-1">Gérer tous vos bons de livraison clients</p>
                </div>
                <a href="{{ route('delivery-notes.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                    + Créer un bon de livraison
                </a>
            </div>
        </header>

        <div class="p-8">
            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            @endif

            <x-bulk-import-panel
                label="Bons de livraison"
                :template-route="route('delivery-notes.import.template')"
                :import-route="route('delivery-notes.import')"
            />

            <x-table-filters
                :action="route('delivery-notes.index')"
                search-placeholder="N° bon de livraison, référence, client..."
                grid-cols="md:grid-cols-6"
            >
                <x-table-filter-select
                    name="status"
                    label="Statut"
                    :options="['brouillon' => 'Brouillon', 'confirmé' => 'Confirmé', 'livré' => 'Livré', 'annulé' => 'Annulé']"
                />
            </x-table-filters>

            <x-table-bulk-bar export-type="delivery-notes" item-label="bon(s) de livraison" />

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <x-table-checkbox-header export-type="delivery-notes" />
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                <x-table-sort-header
                                    column="delivery_date"
                                    label="Date de livraison"
                                    :default="true"
                                    default-direction="desc"
                                />
                                <x-table-sort-header
                                    column="shipping_date"
                                    label="Date d'expédition"
                                    default-direction="desc"
                                />
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BL généré</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BL signé</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($deliveryNotes as $deliveryNote)
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <x-table-checkbox-cell export-type="delivery-notes" :id="$deliveryNote->id" />
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <x-table-show-link :href="route('delivery-notes.show', $deliveryNote)" :label="$deliveryNote->delivery_number" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $deliveryNote->client->name ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $deliveryNote->delivery_date->format('d/m/Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $deliveryNote->shipping_date ? $deliveryNote->shipping_date->format('d/m/Y') : '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $deliveryNote->status }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">{{ number_format($deliveryNote->total, 2) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('delivery-notes.pdf', $deliveryNote) }}" class="text-gray-800 hover:text-gray-950" title="PDF généré">⬇️</a>
                                            <a href="{{ route('delivery-notes.print', $deliveryNote) }}?no_print=1" target="_blank" class="text-green-600 hover:text-green-900" title="Imprimer">🖨️</a>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <x-managed-document-actions type="delivery-notes" :id="$deliveryNote->id" category="signed" label="BL signé" />
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <a href="{{ route('delivery-notes.show', $deliveryNote) }}" class="text-blue-600 hover:text-blue-900" title="Voir">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </a>
                                            <a href="{{ route('delivery-notes.edit', $deliveryNote) }}" class="text-yellow-600 hover:text-yellow-900" title="Modifier">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                            <form action="{{ route('delivery-notes.destroy', $deliveryNote) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce bon de livraison?')">
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
                                            <p class="mt-2 text-sm text-gray-500">Aucun bon de livraison trouvé</p>
                                            <a href="{{ route('delivery-notes.create') }}" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-150">
                                                Créer votre premier bon de livraison
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <x-table-pagination :paginator="$deliveryNotes" :bordered="false" item-label="bons de livraison" />
        </div>
    </main>
@endsection
