@extends('layouts.with-sidebar')

@section('title', 'Tableau de bord')

@section('sidebar_page_title', 'Tableau de bord')

@section('main')
@include('dashboard.panel', [
    'dataUrl' => $dataUrl,
    'bootstrap' => $bootstrap ?? null,
])
@endsection

@push('scripts')
@php
    $dashboardPageScript = public_path('js/dashboard-page.js');
    $dashboardPageVersion = is_readable($dashboardPageScript) ? filemtime($dashboardPageScript) : time();
@endphp
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/dashboard-page.js') }}?v={{ $dashboardPageVersion }}"></script>
@endpush
