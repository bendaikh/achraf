@extends('layouts.with-sidebar')
@section('title', 'Paie '.$run->periodLabel())
@section('sidebar_page_title', 'RH')
@section('main')
@php $fmt = fn ($n) => number_format((float) $n, 2, ',', ' '); @endphp
<main class="flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Paie {{ $run->periodLabel() }}</h1>
            <p class="text-gray-500 mt-1">Statut : <strong>{{ $run->statusLabel() }}</strong></p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($run->canRecalculate())
                <form method="POST" action="{{ route('hr.payroll.calculate', $run) }}">@csrf<button class="px-4 py-2 bg-[#0a5d8a] text-white rounded-lg text-sm">Calculer</button></form>
            @endif
            @if($run->status === 'calculee')
                <form method="POST" action="{{ route('hr.payroll.transition', $run) }}">@csrf<input type="hidden" name="status" value="verifiee"><button class="px-4 py-2 border rounded-lg text-sm">Marquer vérifiée</button></form>
            @endif
            @if($run->status === 'verifiee')
                <form method="POST" action="{{ route('hr.payroll.transition', $run) }}">@csrf<input type="hidden" name="status" value="validee"><button class="px-4 py-2 bg-emerald-700 text-white rounded-lg text-sm">Valider (verrouiller)</button></form>
            @endif
        </div>
    </div>
    @include('hr.partials.flash')
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                <th class="px-3 py-3 text-left">Salarié</th>
                <th class="px-3 py-3 text-left">Base</th>
                <th class="px-3 py-3 text-left">Primes</th>
                <th class="px-3 py-3 text-left">Indemnités</th>
                <th class="px-3 py-3 text-left">HS</th>
                <th class="px-3 py-3 text-left">Absences</th>
                <th class="px-3 py-3 text-left">Brut</th>
                <th class="px-3 py-3 text-left">Cotis. sal.</th>
                <th class="px-3 py-3 text-left">IR</th>
                <th class="px-3 py-3 text-left">Net</th>
                <th class="px-3 py-3 text-left">Charges pat.</th>
                <th class="px-3 py-3 text-left">Coût employeur</th>
                <th class="px-3 py-3 text-left">Paiement</th>
            </tr></thead>
            <tbody>
                @foreach($run->slips as $slip)
                    <tr class="border-t">
                        <td class="px-3 py-3">{{ $slip->employee?->matricule }} {{ $slip->employee?->fullName() }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->base_salary) }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->primes) }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->indemnites) }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->overtime_amount) }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->absence_deduction) }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->gross) }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->employee_contributions) }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->income_tax) }}</td>
                        <td class="px-3 py-3 font-semibold">{{ $fmt($slip->net) }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->employer_contributions) }}</td>
                        <td class="px-3 py-3">{{ $fmt($slip->employer_cost) }}</td>
                        <td class="px-3 py-3">
                            @if($slip->payments->isNotEmpty())
                                Payé le {{ $slip->payments->first()->paid_at?->format('d/m/Y') }}
                                <a class="block text-xs text-[#0a5d8a]" href="{{ route('hr.payroll.slip.pdf', $slip) }}">Bulletin PDF</a>
                            @elseif(in_array($run->status, ['validee', 'payee'], true))
                                <form method="POST" action="{{ route('hr.payroll.pay', $slip) }}" class="space-y-1" enctype="multipart/form-data">
                                    @csrf
                                    <input type="date" name="paid_at" value="{{ now()->toDateString() }}" class="px-2 py-1 border rounded text-xs w-full">
                                    <input type="number" step="0.01" name="amount" value="{{ $slip->net }}" class="px-2 py-1 border rounded text-xs w-full" placeholder="Montant">
                                    <select name="method" class="px-2 py-1 border rounded text-xs w-full">
                                        <option value="virement">Virement</option>
                                        <option value="especes">Espèces</option>
                                        <option value="cheque">Chèque</option>
                                    </select>
                                    <select name="account" class="px-2 py-1 border rounded text-xs w-full">
                                        <option value="banque">Banque</option>
                                        <option value="caisse">Caisse</option>
                                    </select>
                                    <input type="text" name="reference" class="px-2 py-1 border rounded text-xs w-full" placeholder="Référence">
                                    <input type="file" name="proof" class="text-xs w-full">
                                    <div class="flex gap-2">
                                        <a class="text-xs text-[#0a5d8a]" href="{{ route('hr.payroll.slip.pdf', $slip) }}">PDF</a>
                                        <button class="text-xs text-[#0a5d8a] font-semibold">Payer → Trésorerie</button>
                                    </div>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-500 mt-4">Le mouvement de trésorerie correspond au net réellement payé. Les cotisations, l’IR et le coût employeur restent distingués sur le bulletin pour une future comptabilisation.</p>
</main>
@endsection
