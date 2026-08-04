@extends('layouts.with-sidebar')

@section('title', 'Créances & dettes — Gestion Financière')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    @include('financial.partials.section-hub')
</main>
@endsection
