@php
    $commercialDocumentFormScript = public_path('js/commercial-document-form.js');
@endphp
@if (is_readable($commercialDocumentFormScript))
<script>{!! file_get_contents($commercialDocumentFormScript) !!}</script>
@else
<script src="{{ asset('js/commercial-document-form.js') }}"></script>
@endif
