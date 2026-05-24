@extends('layouts.print')

@section('print_title', 'Facture ' . $invoice->invoice_number)

@section('print_actions')
    <a href="{{ route('invoices.show', $invoice) }}" class="btn-back no-print">← Retour</a>
    <button type="button" onclick="window.print()" class="btn-print no-print">Imprimer</button>
@endsection

@section('print_content')
    <x-print.company-header
        document-title="FACTURE"
        :document-number="$invoice->invoice_number"
    >
        <div class="document-date">Date : {{ $invoice->invoice_date->format('d/m/Y') }}</div>
        @if($invoice->due_date)
            <div class="document-date">Échéance : {{ $invoice->due_date->format('d/m/Y') }}</div>
        @endif
    </x-print.company-header>

    <div class="party-box">
        <h3>Informations client</h3>
        <div class="info-grid">
            <div>
                <div class="info-label">Client</div>
                <div class="info-value">{{ $invoice->client->name }}</div>
            </div>
            <div>
                <div class="info-label">Devise</div>
                <div class="info-value">{{ $invoice->currency }}</div>
            </div>
            <div>
                <div class="info-label">Emplacement de stock</div>
                <div class="info-value">{{ $invoice->stock_location }}</div>
            </div>
            @if($invoice->commercial_contact)
                <div>
                    <div class="info-label">Contact commercial</div>
                    <div class="info-value">{{ $invoice->commercial_contact }}</div>
                </div>
            @endif
            @if($invoice->model)
                <div>
                    <div class="info-label">Modèle</div>
                    <div class="info-value">{{ $invoice->model }}</div>
                </div>
            @endif
            @if($invoice->matricule)
                <div>
                    <div class="info-label">Matricule</div>
                    <div class="info-value">{{ $invoice->matricule }}</div>
                </div>
            @endif
        </div>
    </div>

    <x-print.items-table :items="$invoice->items" />

    <x-print.tax-totals
        :taxes="$taxes"
        :currency="$invoice->currency"
        :show-discount="true"
        :show-adjustment="true"
    />

    @if($invoice->remarks || $invoice->conditions)
        <div class="notes-section">
            <div class="notes-grid">
                @if($invoice->remarks)
                    <div class="note-block">
                        <h4>Remarques</h4>
                        <p>{{ $invoice->remarks }}</p>
                    </div>
                @endif
                @if($invoice->conditions)
                    <div class="note-block">
                        <h4>Conditions</h4>
                        <p>{{ $invoice->conditions }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="print-footer">
        Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
@endsection

@push('print_scripts')
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
    });
</script>
@endpush
