@extends('layouts.print')

@section('print_title', 'Facture fournisseur ' . $supplierInvoice->invoice_number)

@section('print_actions')
    <a href="{{ route('supplier-invoices.show', $supplierInvoice) }}" class="btn-back no-print">← Retour</a>
    <button type="button" onclick="window.print()" class="btn-print no-print">Imprimer</button>
@endsection

@section('print_content')
    <x-print.company-header document-title="FACTURE FOURNISSEUR" :document-number="$supplierInvoice->invoice_number">
        <div class="document-date">Date : {{ $supplierInvoice->invoice_date->format('d/m/Y') }}</div>
        @if($supplierInvoice->due_date)
            <div class="document-date">Échéance : {{ $supplierInvoice->due_date->format('d/m/Y') }}</div>
        @endif
    </x-print.company-header>

    <div class="party-box">
        <h3>Informations fournisseur</h3>
        <div class="info-grid">
            <div>
                <div class="info-label">Fournisseur</div>
                <div class="info-value">{{ $supplierInvoice->supplier->name }}</div>
            </div>
            <div>
                <div class="info-label">Devise</div>
                <div class="info-value">{{ $supplierInvoice->currency }}</div>
            </div>
            <div>
                <div class="info-label">Emplacement de stock</div>
                <div class="info-value">{{ $supplierInvoice->stock_location }}</div>
            </div>
            @if($supplierInvoice->commercial_contact)
                <div>
                    <div class="info-label">Contact commercial</div>
                    <div class="info-value">{{ $supplierInvoice->commercial_contact }}</div>
                </div>
            @endif
        </div>
    </div>

    <x-print.items-table :items="$supplierInvoice->items" :show-description="false" />

    <x-print.tax-totals :taxes="$taxes" :currency="$supplierInvoice->currency" :show-discount="true" :show-adjustment="true" />

    @if($supplierInvoice->remarks || $supplierInvoice->conditions)
        <div class="notes-section">
            <div class="notes-grid">
                @if($supplierInvoice->remarks)
                    <div class="note-block"><h4>Remarques</h4><p>{{ $supplierInvoice->remarks }}</p></div>
                @endif
                @if($supplierInvoice->conditions)
                    <div class="note-block"><h4>Conditions</h4><p>{{ $supplierInvoice->conditions }}</p></div>
                @endif
            </div>
        </div>
    @endif

    <div class="print-footer">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
@endsection

@push('print_scripts')
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
@endpush
