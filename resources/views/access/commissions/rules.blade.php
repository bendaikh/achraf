@extends('layouts.with-sidebar')

@section('title', 'Règles de commission')
@section('sidebar_page_title', 'Gestion ventes')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl">
        <div class="mb-6">
            <a href="{{ route('access.commissions.index') }}" class="text-sm text-blue-600 hover:text-blue-800">← Commissions</a>
            <h1 class="text-3xl font-bold text-gray-900 mt-2">Règles de commission</h1>
            <p class="text-gray-500 mt-1">Base et déclencheur configurables — pas de formule unique codée en dur.</p>
        </div>

        @include('access.partials.flash')

        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="font-semibold text-gray-900 mb-4">Nouvelle règle</h2>
            <form method="POST" action="{{ route('access.commissions.rules.store') }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">Nom</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Type</label>
                    <select name="type" class="w-full px-3 py-2 border rounded-lg">
                        <option value="percent_ca">% du CA</option>
                        <option value="fixed">Montant fixe</option>
                        <option value="percent_margin">% de marge</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Base</label>
                    <select name="base" class="w-full px-3 py-2 border rounded-lg">
                        <option value="ca_ht">CA HT</option>
                        <option value="ca_ttc">CA TTC</option>
                        <option value="collected">Montant encaissé</option>
                        <option value="margin">Marge</option>
                        <option value="fixed">Fixe</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Taux %</label>
                    <input type="number" step="0.01" name="rate" value="3" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Montant fixe</label>
                    <input type="number" step="0.01" name="fixed_amount" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Déclencheur</label>
                    <select name="trigger" class="w-full px-3 py-2 border rounded-lg">
                        <option value="delivered_paid">Livrée + payée</option>
                        <option value="invoice_validated">Facture validée</option>
                        <option value="delivered">Commande livrée</option>
                        <option value="paid">Facture payée</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="rounded"> Active</label>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="px-5 py-2 bg-[#fdb819] text-white rounded-lg font-semibold">Créer</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Nom</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Type / Base</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Taux / Fixe</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Déclencheur</th>
                        <th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Actif</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($rules as $rule)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium">{{ $rule->name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $rule->type }} / {{ $rule->base }}</td>
                            <td class="px-4 py-3 text-sm">{{ $rule->rate ? $rule->rate.'%' : number_format((float)$rule->fixed_amount, 2).' DH' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $rule->trigger }}</td>
                            <td class="px-4 py-3 text-sm">{{ $rule->is_active ? 'Oui' : 'Non' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
