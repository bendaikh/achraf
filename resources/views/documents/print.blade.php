@extends('layouts.print')

@section('print_title', ($doc['title'] ?? 'Document') . ' ' . ($doc['number'] ?? ''))

@section('print_actions')
    @yield('document_print_actions')
@endsection

@push('print_styles')
    @include('documents.partials.commercial-styles')
@endpush

@section('print_content')
    @include('documents.partials.commercial-document', [
        'logoSrc' => \App\Support\CompanyInfo::logoAssetUrl(),
        'forPdf' => false,
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
