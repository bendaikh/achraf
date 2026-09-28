@extends('layouts.with-sidebar')

@section('title', 'Imports relevés bancaires')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">Relevés bancaires</h2>
                <p class="text-sm text-slate-500 mt-1">Import CSV/XLSX · rapprochement vers mouvements existants (jamais de double création).</p>
            </div>
            <a href="{{ route('financial.bank-imports.create') }}" class="inline-flex px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium hover:bg-[#084a6e]">+ Importer un relevé</a>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 bg-slate-50/80">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs uppercase text-slate-500">Date</th>
                        <th class="px-5 py-3 text-left text-xs uppercase text-slate-500">Fichier</th>
                        <th class="px-5 py-3 text-left text-xs uppercase text-slate-500">Compte</th>
                        <th class="px-5 py-3 text-right text-xs uppercase text-slate-500">Lignes</th>
                        <th class="px-5 py-3 text-left text-xs uppercase text-slate-500">Statut</th>
                        <th class="px-5 py-3 text-right text-xs uppercase text-slate-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($imports as $import)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3">{{ $import->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-3">{{ $import->original_filename }}</td>
                            <td class="px-5 py-3">{{ $accountLabels[$import->account] ?? $import->account }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $import->lines_count }}</td>
                            <td class="px-5 py-3">{{ $import->status }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('financial.bank-imports.show', $import) }}" class="text-[#0a5d8a] font-medium hover:underline">Ouvrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Aucun import.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $imports->links() }}</div>
    </div>
</main>
@endsection
