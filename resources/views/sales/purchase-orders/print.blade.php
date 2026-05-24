@extends('layouts.print')

@section('print_title', 'Bon de commande ' . $purchaseOrder->reference)

@section('print_actions')
    <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn-back no-print">← Retour</a>
    <button type="button" onclick="window.print()" class="btn-print no-print">Imprimer</button>
@endsection

@section('print_content')
    <x-print.company-header document-title="BON DE COMMANDE" :document-number="$purchaseOrder->reference">
        <div class="document-date">Date : {{ $purchaseOrder->order_date->format('d/m/Y') }}</div>
        @if($purchaseOrder->expiry_date)
            <div class="document-date">Validité : {{ $purchaseOrder->expiry_date->format('d/m/Y') }}</div>
        @endif
    </x-print.company-header>

    <div class="party-box">
        <h3>Informations client</h3>
        <div class="info-grid">
            <div>
                <div class="info-label">Client</div>
                <div class="info-value">{{ $purchaseOrder->client->name }}</div>
            </div>
            <div>
                <div class="info-label">Devise</div>
                <div class="info-value">{{ $purchaseOrder->currency }}</div>
            </div>
            <div>
                <div class="info-label">Statut</div>
                <div class="info-value">{{ $purchaseOrder->status }}</div>
            </div>
            @if($purchaseOrder->model)
                <div>
                    <div class="info-label">Modèle</div>
                    <div class="info-value">{{ $purchaseOrder->model }}</div>
                </div>
            @endif
            @if($purchaseOrder->matricule)
                <div>
                    <div class="info-label">Matricule</div>
                    <div class="info-value">{{ $purchaseOrder->matricule }}</div>
                </div>
            @endif
        </div>
    </div>

    <x-print.items-table :items="$purchaseOrder->items" :show-description="false" />

    <x-print.tax-totals :taxes="$taxes" :currency="$purchaseOrder->currency" :show-discount="true" :show-adjustment="true" />

    @if($purchaseOrder->remarks || $purchaseOrder->conditions)
        <div class="notes-section">
            <div class="notes-grid">
                @if($purchaseOrder->remarks)
                    <div class="note-block"><h4>Remarques</h4><p>{{ $purchaseOrder->remarks }}</p></div>
                @endif
                @if($purchaseOrder->conditions)
                    <div class="note-block"><h4>Conditions</h4><p>{{ $purchaseOrder->conditions }}</p></div>
                @endif
            </div>
        </div>
    @endif

    <div class="print-footer">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
@endsection
