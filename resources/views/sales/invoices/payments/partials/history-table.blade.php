@php
    $currency = $invoice->currency ?? 'DH';
    $showActions = $showActions ?? false;
    $compact = $compact ?? false;
@endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Historique des paiements</h3>
        <p class="text-sm text-gray-500 mt-1">Montant facture ≠ net encaissé lorsque des frais de livraison / commission s’appliquent.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    @unless($compact)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant facture</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frais livraison</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net encaissé</th>
                    @else
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frais livraison</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net encaissé</th>
                    @endunless
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Méthode</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Référence</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                    @unless($compact)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilisateur</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Créé le</th>
                    @endunless
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Justificatif</th>
                    @unless($compact)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    @endunless
                    @if($showActions)
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($payments as $payment)
                    @php
                        $gross = $payment->resolvedGrossAmount();
                        $fees = $payment->resolvedDeliveryFees();
                        $net = $payment->resolvedNetReceived();
                        $tracking = $payment->resolvedTrackingNumber($invoice);
                    @endphp
                    <tr class="hover:bg-gray-50 transition duration-150">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $payment->payment_date->format('d/m/Y') }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900">{{ number_format($gross, 2) }} {{ $currency }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $fees !== null ? number_format($fees, 2).' '.$currency : '—' }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm font-semibold text-green-700">{{ $net !== null ? number_format($net, 2).' '.$currency : '—' }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $payment->payment_method }}</div>
                            @if($payment->carrier)
                                <div class="text-xs text-gray-500">{{ $payment->carrier }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $payment->payment_reference ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm font-mono text-gray-900">{{ $tracking ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                {{ $payment->sourceLabel() }}
                                @if(($payment->source ?? '') === 'import' && $payment->paymentImport)
                                    <div class="text-xs text-gray-500">{{ $payment->paymentImport->original_filename ?? $payment->paymentImport->file_name }}</div>
                                @endif
                                @if($payment->payment_batch_id)
                                    <a href="{{ route('sales.payments.batch.show', $payment->payment_batch_id) }}" class="block text-xs text-indigo-600 hover:text-indigo-800 mt-0.5">
                                        Voir le règlement groupé
                                    </a>
                                @endif
                            </div>
                        </td>
                        @unless($compact)
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $payment->user->name ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $payment->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
                            </td>
                        @endunless
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($payment->payment_file_path)
                                <a href="{{ \App\Support\PublicStorage::url($payment->payment_file_path) }}" target="_blank" class="text-blue-600 hover:text-blue-900" title="Télécharger le justificatif">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        @unless($compact)
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-900">{{ $payment->notes ?? '—' }}</div>
                            </td>
                        @endunless
                        @if($showActions)
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                <form action="{{ route('invoices.payments.destroy', [$invoice, $payment]) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce paiement?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $compact ? ($showActions ? 9 : 8) : ($showActions ? 13 : 12) }}" class="px-6 py-12 text-center">
                            <p class="text-sm text-gray-500">Aucun paiement enregistré</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
