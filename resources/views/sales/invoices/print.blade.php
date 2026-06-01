@extends('layouts.print')

@section('print_title', 'Facture ' . $invoice->invoice_number)

@section('print_actions')
    <a href="{{ route('invoices.show', $invoice) }}" class="btn-back no-print">← Retour</a>
    <a href="{{ route('invoices.pdf', $invoice) }}" class="btn-print no-print" style="background:#111;color:#fff;">Télécharger PDF</a>
    <button type="button" onclick="window.print()" class="btn-print no-print">Imprimer</button>
@endsection

@push('print_styles')
    @include('sales.invoices.partials.facture-styles')
@endpush

@section('print_content')
    @include('sales.invoices.partials.facture-document', [
        'logoSrc' => $company['logo_url'] ?? null,
        'cachetSrc' => $company['cachet_url'] ?? null,
    ])
@endsection

@push('print_scripts')
<script>
    window.addEventListener('load', function () {
        if (!new URLSearchParams(window.location.search).has('no_print')) {
            setTimeout(function () { window.print(); }, 300);
        }
    });
</script>
@endpush
