@extends('layouts.with-sidebar')

@section('title', 'Modifier le salarié')
@section('sidebar_page_title', 'RH')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Modifier {{ $employee->fullName() }}</h1>
        @include('hr.partials.flash')
        <form method="POST" action="{{ route('hr.employees.update', $employee) }}" enctype="multipart/form-data" class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            @csrf
            @method('PUT')
            @include('hr.employees._form', ['employee' => $employee])
            <div class="flex justify-end gap-3">
                <a href="{{ route('hr.employees.show', $employee) }}" class="px-4 py-2 border rounded-lg text-sm">Annuler</a>
                <button class="px-5 py-2 bg-[#fdb819] text-white rounded-lg font-semibold">Enregistrer</button>
            </div>
        </form>
    </div>
</main>
@endsection
