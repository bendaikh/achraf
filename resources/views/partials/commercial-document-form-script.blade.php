<style>
    /* Keep product select compact so Réf / Désignation stay readable */
    #itemsTable {
        width: 100%;
        min-width: 72rem;
    }
    #itemsTable td:has(select.product-select) {
        width: 15rem;
        max-width: 15rem;
    }
    #itemsTable td:has(select.product-select) .select2-container {
        width: 100% !important;
        max-width: 15rem !important;
    }
    #itemsTable .select2-selection__rendered {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #itemsTable td:has(> input[name*="[ref]"]) {
        width: 10.5rem;
        min-width: 10.5rem;
        white-space: nowrap;
    }
    #itemsTable input[name*="[ref]"] {
        width: 10.5rem;
        min-width: 10.5rem;
        max-width: 10.5rem;
        box-sizing: border-box;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.8125rem;
    }
    #itemsTable td:has(> input[name*="[designation]"]) {
        min-width: 14rem;
    }
    #itemsTable input[name*="[designation]"] {
        width: 100%;
        min-width: 14rem;
        box-sizing: border-box;
    }
</style>
@php
    $commercialDocumentFormScript = public_path('js/commercial-document-form.js');
@endphp
@if (is_readable($commercialDocumentFormScript))
<script>{!! file_get_contents($commercialDocumentFormScript) !!}</script>
@else
<script src="{{ asset('js/commercial-document-form.js') }}"></script>
@endif
