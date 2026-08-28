@extends('documents.print')

@section('document_print_actions')
    <a href="{{ route('supplier-credit-notes.show', $supplierCreditNote) }}" class="btn-back no-print">← Retour</a>
    <a href="{{ route('supplier-credit-notes.pdf', $supplierCreditNote) }}" class="btn-print no-print" style="background:#111;color:#fff;">Télécharger PDF</a>
    <button type="button" onclick="window.print()" class="btn-print no-print">Imprimer</button>
@endsection
