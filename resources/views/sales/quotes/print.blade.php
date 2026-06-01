@extends('layouts.print')

@section('print_title', 'Devis ' . $quote->quote_number)

@section('print_actions')
    <a href="{{ route('quotes.show', $quote) }}" class="btn-back no-print">← Retour</a>
    <button type="button" onclick="window.print()" class="btn-print no-print">Imprimer</button>
@endsection

@section('print_content')
    <x-print.company-header document-title="DEVIS" :document-number="$quote->quote_number">
        <div class="document-date">Date : {{ $quote->quote_date->format('d/m/Y') }}</div>
        @if($quote->expiry_date)
            <div class="document-date">Validité : {{ $quote->expiry_date->format('d/m/Y') }}</div>
        @endif
    </x-print.company-header>

    <div class="party-box">
        <h3>Informations client</h3>
        <div class="info-grid">
            <div>
                <div class="info-label">Client</div>
                <div class="info-value">{{ $quote->client->name }}</div>
            </div>
            <div>
                <div class="info-label">Devise</div>
                <div class="info-value">{{ $quote->currency }}</div>
            </div>
            <div>
                <div class="info-label">Emplacement de stock</div>
                <div class="info-value">{{ $quote->stock_location }}</div>
            </div>
            <div>
                <div class="info-label">Statut</div>
                <div class="info-value">{{ $quote->status }}</div>
            </div>
            @if($quote->model)
                <div>
                    <div class="info-label">Modèle</div>
                    <div class="info-value">{{ $quote->model }}</div>
                </div>
            @endif
            @if($quote->matricule)
                <div>
                    <div class="info-label">Matricule</div>
                    <div class="info-value">{{ $quote->matricule }}</div>
                </div>
            @endif
        </div>
    </div>

    <x-print.items-table :items="$quote->items" :show-description="false" />

    <x-print.tax-totals :taxes="$taxes" :currency="$quote->currency" :show-discount="true" :show-adjustment="true" />

    @if($quote->remarks || $quote->conditions)
        <div class="notes-section">
            <div class="notes-grid">
                @if($quote->remarks)
                    <div class="note-block"><h4>Remarques</h4><p>{{ $quote->remarks }}</p></div>
                @endif
                @if($quote->conditions)
                    <div class="note-block"><h4>Conditions</h4><p>{{ $quote->conditions }}</p></div>
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
