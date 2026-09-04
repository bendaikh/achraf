@extends('layouts.with-sidebar')

@section('title', 'Commissions')
@section('sidebar_page_title', 'Gestion ventes')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Commissions</h1>
                <p class="text-gray-500 mt-1">Salariés et freelances — même moteur. Historique conservé (régularisations).</p>
            </div>
            <a href="{{ route('access.commissions.rules') }}" class="inline-flex px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-gray-800">Règles de calcul</a>
        </div>

        @include('access.partials.flash')

        <x-table-filters :action="route('access.commissions.index')" search-placeholder="Document, notes..." grid-cols="md:grid-cols-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach(\App\Models\Commission::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Commercial</label>
                <select name="collaborator_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Tous</option>
                    @foreach($collaborators as $c)
                        <option value="{{ $c->id }}" @selected((string) request('collaborator_id') === (string) $c->id)>{{ $c->fullName() }}</option>
                    @endforeach
                </select>
            </div>
        </x-table-filters>

        <form id="commission-bulk" method="POST" class="space-y-4">
            @csrf
            <div class="flex flex-wrap gap-2">
                <button type="submit" formaction="{{ route('access.commissions.validate') }}" class="px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50 font-medium">Valider</button>
                <button type="button" onclick="document.getElementById('pay-panel').classList.toggle('hidden')" class="px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50 font-medium">Marquer payées…</button>
                <button type="button" onclick="document.getElementById('payroll-panel').classList.toggle('hidden')" class="px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50 font-medium">Lier à la paie…</button>
                <button type="button" onclick="document.getElementById('freelance-panel').classList.toggle('hidden')" class="px-3 py-2 text-sm rounded-lg border border-gray-300 bg-white hover:bg-gray-50 font-medium">Règlement freelance…</button>
            </div>

            <div id="pay-panel" class="hidden bg-white border border-gray-200 rounded-lg p-4 grid gap-3 sm:grid-cols-4">
                <input type="date" name="date" value="{{ now()->toDateString() }}" class="px-3 py-2 border rounded-lg text-sm" form="commission-bulk">
                <input type="text" name="payment_method" placeholder="Mode" class="px-3 py-2 border rounded-lg text-sm" form="commission-bulk">
                <input type="text" name="payment_reference" placeholder="Référence" class="px-3 py-2 border rounded-lg text-sm" form="commission-bulk">
                <button type="submit" formaction="{{ route('access.commissions.pay') }}" class="px-3 py-2 bg-[#fdb819] text-white rounded-lg font-semibold text-sm">Confirmer paiement</button>
            </div>

            <div id="payroll-panel" class="hidden bg-white border border-gray-200 rounded-lg p-4 grid gap-3 sm:grid-cols-4">
                <input type="number" name="period_year" value="{{ now()->year }}" class="px-3 py-2 border rounded-lg text-sm" form="commission-bulk">
                <input type="number" name="period_month" value="{{ now()->month }}" min="1" max="12" class="px-3 py-2 border rounded-lg text-sm" form="commission-bulk">
                <input type="text" name="label" placeholder="Libellé paie" class="px-3 py-2 border rounded-lg text-sm" form="commission-bulk">
                <button type="submit" formaction="{{ route('access.commissions.link-payroll') }}" class="px-3 py-2 bg-[#0a5d8a] text-white rounded-lg font-semibold text-sm">Lier paie salarié</button>
            </div>

            <div id="freelance-panel" class="hidden bg-white border border-gray-200 rounded-lg p-4 grid gap-3 sm:grid-cols-4">
                <input type="date" name="date" value="{{ now()->toDateString() }}" class="px-3 py-2 border rounded-lg text-sm">
                <input type="text" name="payment_method" placeholder="Mode" class="px-3 py-2 border rounded-lg text-sm">
                <input type="text" name="payment_reference" placeholder="Référence" class="px-3 py-2 border rounded-lg text-sm">
                <button type="submit" formaction="{{ route('access.commissions.freelance-payout') }}" class="px-3 py-2 bg-emerald-600 text-white rounded-lg font-semibold text-sm">Régler freelance</button>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3"><input type="checkbox" onclick="document.querySelectorAll('.comm-cb').forEach(c=>c.checked=this.checked)"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commercial</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Base</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($commissions as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><input type="checkbox" class="comm-cb" name="ids[]" value="{{ $row->id }}"></td>
                                    <td class="px-4 py-3 text-sm">{{ $row->created_at?->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ $row->collaborator?->fullName() ?? '—' }}
                                        <div class="text-xs text-gray-500">{{ $row->collaborator?->typeLabel() }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ $row->document_ref ?? '—' }}@if($row->parent_id)<span class="text-xs text-amber-700"> (régul.)</span>@endif</td>
                                    <td class="px-4 py-3 text-sm">{{ number_format((float)$row->base_amount, 2, ',', ' ') }}</td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ number_format((float)$row->amount, 2, ',', ' ') }} DH</td>
                                    <td class="px-4 py-3 text-sm">{{ $row->statusLabel() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Aucune commission.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3">{{ $commissions->links() }}</div>
            </div>
        </form>
    </div>
</main>
@endsection
