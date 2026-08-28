@php
    use App\Support\InvoiceCommercialStatus;
    $badge = InvoiceCommercialStatus::badgeClasses()[$situation['commercial_status']] ?? 'bg-gray-100 text-gray-800';
@endphp

<div class="p-6 border-b border-gray-200 bg-slate-50">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Situation de la facture</h3>
            <p class="text-sm text-gray-600 mt-1">Vente nette, avoirs, encaissements et remboursements</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($situation['total_credits'] > 0 && $situation['remaining_to_refund'] > 0)
                <a href="{{ route('sales.refunds.create', ['invoice_id' => $invoice->id]) }}"
                   class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium transition">
                    + Enregistrer un remboursement
                </a>
            @endif
            <a href="{{ route('credit-notes.create', ['invoice_id' => $invoice->id, 'client_id' => $invoice->client_id]) }}"
               class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm font-medium transition">
                + Créer un avoir
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label class="text-sm font-medium text-gray-500">Source</label>
            <p class="text-sm text-gray-900 mt-1">{{ $situation['source_label'] }}</p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">Statut commercial</label>
            <p class="mt-1">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                    {{ strtoupper($situation['commercial_status_label']) }}
                </span>
            </p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">Montant facture initiale</label>
            <p class="text-sm font-semibold text-gray-900 mt-1">{{ number_format($situation['initial_amount'], 2) }} {{ $situation['currency'] }}</p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">Total avoirs</label>
            <p class="text-sm font-semibold text-red-600 mt-1">-{{ number_format($situation['total_credits'], 2) }} {{ $situation['currency'] }}</p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">Vente nette</label>
            <p class="text-sm font-semibold text-gray-900 mt-1">{{ number_format($situation['net_sale'], 2) }} {{ $situation['currency'] }}</p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">Montant encaissé</label>
            <p class="text-sm font-semibold text-green-600 mt-1">{{ number_format($situation['total_collected'], 2) }} {{ $situation['currency'] }}</p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">Montant remboursé</label>
            <p class="text-sm font-semibold text-red-600 mt-1">{{ number_format($situation['total_refunded'], 2) }} {{ $situation['currency'] }}</p>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-500">Reste à rembourser</label>
            <p class="text-sm font-semibold {{ $situation['remaining_to_refund'] > 0 ? 'text-amber-600' : 'text-gray-900' }} mt-1">
                {{ number_format($situation['remaining_to_refund'], 2) }} {{ $situation['currency'] }}
            </p>
        </div>
        @if($situation['order_reference'])
        <div>
            <label class="text-sm font-medium text-gray-500">Commande d'origine</label>
            <p class="text-sm text-gray-900 mt-1">
                @if($invoice->posSale)
                    <a href="{{ route('orders.show', $invoice->posSale) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                        {{ $situation['order_reference'] }}
                    </a>
                    @if($situation['order_external_id'])
                        <span class="text-gray-500">({{ $situation['order_external_id'] }})</span>
                    @endif
                @else
                    {{ $situation['order_reference'] }}
                @endif
            </p>
        </div>
        @endif
        @if($situation['last_return_date'])
        <div>
            <label class="text-sm font-medium text-gray-500">Date du retour / remboursement</label>
            <p class="text-sm text-gray-900 mt-1">{{ $situation['last_return_date']->format('d/m/Y') }}</p>
        </div>
        @endif
    </div>

    @if($situation['credit_notes']->isNotEmpty())
    <div class="mt-6">
        <label class="text-sm font-medium text-gray-500">Avoir(s) associé(s)</label>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach($situation['credit_notes'] as $cn)
                <a href="{{ route('credit-notes.show', $cn) }}" class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-white border border-gray-200 text-gray-800 hover:bg-gray-50">
                    {{ $cn->credit_note_number }} ({{ number_format($cn->computed_total, 2) }} {{ $situation['currency'] }})
                </a>
            @endforeach
        </div>
    </div>
    @endif

    @if($situation['refunds']->isNotEmpty())
    <div class="mt-4">
        <label class="text-sm font-medium text-gray-500">Remboursements financiers</label>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach($situation['refunds'] as $refund)
                <a href="{{ route('sales.refunds.show', $refund) }}" class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-white border border-red-200 text-red-800 hover:bg-red-50">
                    {{ $refund->refund_number }} ({{ number_format($refund->amount, 2) }} {{ $situation['currency'] }})
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
