@extends('layouts.with-sidebar')

@section('title', 'Importer un relevé')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Importer un relevé bancaire</h2>
                <p class="text-sm text-slate-500 mt-1">CSV, TXT, XLS ou XLSX. Colonnes détectées : date, libellé, débit/crédit.</p>
            </div>
            <a href="{{ route('financial.bank-imports.index') }}" class="text-sm text-[#0a5d8a] hover:underline">← Retour</a>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 max-w-xl">
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('financial.bank-imports.store') }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Compte *</label>
                <select name="account" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white" required>
                    @foreach($accountLabels as $key => $label)
                        <option value="{{ $key }}" @selected(old('account', 'banque') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Fichier *</label>
                <input type="file" name="statement_file" accept=".csv,.txt,.xls,.xlsx" required class="w-full text-sm">
            </div>
            <button type="submit" class="px-5 py-2.5 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Importer</button>
        </form>
    </div>
</main>
@endsection
