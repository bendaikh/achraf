@extends('layouts.with-sidebar')

@section('title', 'Préparation déclaration TVA')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière / TVA</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">Déclaration TVA préparée</h2>
                <p class="text-sm text-slate-500 mt-1">Période {{ \Carbon\Carbon::parse($payload['period_from'])->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($payload['period_to'])->format('d/m/Y') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('financial.export', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="px-4 py-2 border border-slate-300 rounded-lg text-sm bg-white">Exporter</a>
                <a href="{{ route('financial.declarations', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm font-medium">Déclarations</a>
            </div>
        </div>
    </header>

    <div class="p-8 space-y-6 max-w-4xl">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">Base HT</p><p class="text-lg font-bold">{{ number_format($payload['base_ht'], 2) }} DH</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">TVA collectée</p><p class="text-lg font-bold text-emerald-700">{{ number_format($payload['vat_collected'], 2) }} DH</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">TVA déductible</p><p class="text-lg font-bold text-blue-700">{{ number_format($payload['vat_deductible'], 2) }} DH</p></div>
            <div class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">TVA nette</p><p class="text-lg font-bold text-[#0a5d8a]">{{ number_format($payload['vat_net'], 2) }} DH</p></div>
        </div>

        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b font-semibold">Détail automatique</div>
            <dl class="divide-y text-sm">
                <div class="px-5 py-3 flex justify-between"><dt>CA période</dt><dd class="font-medium">{{ number_format($payload['revenue'], 2) }} DH</dd></div>
                <div class="px-5 py-3 flex justify-between"><dt>TVA collectée (factures)</dt><dd>{{ number_format($payload['collected_invoices'], 2) }} DH</dd></div>
                <div class="px-5 py-3 flex justify-between"><dt>TVA collectée (POS)</dt><dd>{{ number_format($payload['collected_pos'], 2) }} DH</dd></div>
                <div class="px-5 py-3 flex justify-between"><dt>TVA avoirs clients (−)</dt><dd>{{ number_format($payload['collected_credit_notes'], 2) }} DH</dd></div>
                <div class="px-5 py-3 flex justify-between"><dt>TVA déductible achats</dt><dd>{{ number_format($payload['deductible_purchases'], 2) }} DH</dd></div>
                <div class="px-5 py-3 flex justify-between"><dt>TVA déductible dépenses</dt><dd>{{ number_format($payload['deductible_expenses'], 2) }} DH</dd></div>
                <div class="px-5 py-3 flex justify-between"><dt>TVA avoirs fournisseurs (−)</dt><dd>{{ number_format($payload['deductible_credit_notes'], 2) }} DH</dd></div>
                <div class="px-5 py-3 flex justify-between"><dt>Taux présents</dt><dd>{{ $payload['rates'] !== [] ? implode(' %, ', $payload['rates']).' %' : '—' }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b font-semibold">Contrôles</div>
            <ul class="divide-y">
                @forelse($payload['controls'] as $control)
                    <li class="px-5 py-3 text-sm text-gray-800">{{ $control['label'] }}</li>
                @empty
                    <li class="px-5 py-6 text-sm text-emerald-700">Aucun problème — déclaration prête.</li>
                @endforelse
            </ul>
        </div>
    </div>
</main>
@endsection
