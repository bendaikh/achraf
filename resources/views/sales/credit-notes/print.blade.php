@extends('layouts.print')

@section('print_title', 'Avoir ' . $creditNote->credit_note_number)

@section('print_actions')
    <a href="{{ route('credit-notes.show', $creditNote) }}" class="btn-back no-print">← Retour</a>
    <button type="button" onclick="window.print()" class="btn-print no-print">Imprimer</button>
@endsection

@section('print_content')
    <x-print.company-header document-title="AVOIR" :document-number="$creditNote->credit_note_number">
        <div class="document-date">Date : {{ $creditNote->credit_note_date->format('d/m/Y') }}</div>
        @if($creditNote->invoice)
            <div class="document-date">Facture liée : {{ $creditNote->invoice->invoice_number }}</div>
        @endif
    </x-print.company-header>

    <div class="party-box">
        <h3>Informations client</h3>
        <div class="info-grid">
            <div>
                <div class="info-label">Client</div>
                <div class="info-value">{{ $creditNote->client->name }}</div>
            </div>
            <div>
                <div class="info-label">Devise</div>
                <div class="info-value">{{ $creditNote->currency }}</div>
            </div>
            <div>
                <div class="info-label">Emplacement de stock</div>
                <div class="info-value">{{ $creditNote->stock_location }}</div>
            </div>
        </div>
    </div>

    <x-print.items-table :items="$creditNote->items" :show-description="false" />

    <x-print.tax-totals :taxes="$taxes" :currency="$creditNote->currency" :show-discount="true" :show-adjustment="true" />

    @if($creditNote->remarks || $creditNote->conditions)
        <div class="notes-section">
            <div class="notes-grid">
                @if($creditNote->remarks)
                    <div class="note-block"><h4>Remarques</h4><p>{{ $creditNote->remarks }}</p></div>
                @endif
                @if($creditNote->conditions)
                    <div class="note-block"><h4>Conditions</h4><p>{{ $creditNote->conditions }}</p></div>
                @endif
            </div>
        </div>
    @endif

    <x-print.company-stamp :company="$company" />

    <div class="print-footer">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
@endsection

@push('print_scripts')
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
@endpush
