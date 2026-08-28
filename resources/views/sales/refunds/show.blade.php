@extends('layouts.with-sidebar')

@section('title', 'Remboursement ' . $refund->refund_number)

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div class="px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Remboursement {{ $refund->refund_number }}</h2>
                <p class="text-sm text-gray-600 mt-1">Opération financière de remboursement client</p>
            </div>
            <a href="{{ route('sales.refunds.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Retour</a>
        </div>
    </header>

    <div class="p-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 max-w-3xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Client</label>
                    <p class="text-sm text-gray-900 mt-1">{{ $refund->client->name }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Date</label>
                    <p class="text-sm text-gray-900 mt-1">{{ $refund->refund_date->format('d/m/Y') }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Montant</label>
                    <p class="text-sm font-semibold text-red-600 mt-1">{{ number_format($refund->amount, 2) }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Mode</label>
                    <p class="text-sm text-gray-900 mt-1">{{ $refund->payment_method }}</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Source</label>
                    <p class="text-sm text-gray-900 mt-1">{{ ucfirst($refund->source ?? 'manuel') }}</p>
                </div>
                @if($refund->payment_reference)
                <div>
                    <label class="text-sm font-medium text-gray-500">Référence</label>
                    <p class="text-sm text-gray-900 mt-1">{{ $refund->payment_reference }}</p>
                </div>
                @endif
                @if($refund->invoice)
                <div>
                    <label class="text-sm font-medium text-gray-500">Facture</label>
                    <p class="text-sm mt-1"><a href="{{ route('invoices.show', $refund->invoice) }}" class="text-blue-600 hover:text-blue-800">{{ $refund->invoice->invoice_number }}</a></p>
                </div>
                @endif
                @if($refund->creditNote)
                <div>
                    <label class="text-sm font-medium text-gray-500">Avoir associé</label>
                    <p class="text-sm mt-1"><a href="{{ route('credit-notes.show', $refund->creditNote) }}" class="text-blue-600 hover:text-blue-800">{{ $refund->creditNote->credit_note_number }}</a></p>
                </div>
                @endif
                @if($refund->posSale)
                <div>
                    <label class="text-sm font-medium text-gray-500">Commande</label>
                    <p class="text-sm mt-1"><a href="{{ route('orders.show', $refund->posSale) }}" class="text-blue-600 hover:text-blue-800">{{ $refund->posSale->ticket_number }}</a></p>
                </div>
                @endif
                @if($refund->creator)
                <div>
                    <label class="text-sm font-medium text-gray-500">Créé par</label>
                    <p class="text-sm text-gray-900 mt-1">{{ $refund->creator->name }}</p>
                </div>
                @endif
            </div>

            @if($refund->notes)
            <div class="mt-6">
                <label class="text-sm font-medium text-gray-500">Notes</label>
                <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $refund->notes }}</p>
            </div>
            @endif
        </div>
    </div>
</main>
@endsection
