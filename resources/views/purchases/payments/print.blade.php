@extends('layouts.print')

@section('print_title', 'Règlement ' . ($payment->payment_number ?? ''))

@section('print_actions')
    <a href="{{ route('purchases.payments.show', $payment) }}" class="btn-back no-print">← Retour</a>
    <a href="{{ route('purchases.payments.pdf', $payment) }}" class="btn-print no-print" style="background:#111;color:#fff;">Télécharger PDF</a>
    <button type="button" onclick="window.print()" class="btn-print no-print">Imprimer</button>
@endsection

@section('print_content')
    @include('purchases.payments.partials.fiche-document')
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
