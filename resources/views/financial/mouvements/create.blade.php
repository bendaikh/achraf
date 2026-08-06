@extends('layouts.with-sidebar')

@section('title', 'Nouveau mouvement')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0" x-data="{
    type: '{{ old('type', 'sortie') }}',
    account: '{{ old('account', 'banque') }}',
    origin: '{{ old('origin', 'manuel') }}'
}">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière / Mouvements</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">Nouveau mouvement</h2>
                <p class="text-sm text-slate-500 mt-1">Uniquement pour les cas exceptionnels (frais bancaires, virement, ajustement…).</p>
            </div>
            <a href="{{ route('financial.mouvements.index') }}" class="text-sm font-medium text-[#0a5d8a] hover:underline">← Retour au journal</a>
        </div>
        @include('financial.partials.finance-tabs')
    </header>

    <div class="p-4 sm:p-6 lg:p-8 bg-slate-50/80">
        <div class="max-w-3xl mx-auto">
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong>Astuce :</strong> paiements clients, fournisseurs, POS et dépenses créent déjà un mouvement automatiquement. Utilisez ce formulaire seulement si rien d’autre ne l’a généré.
            </div>

            <form method="POST" action="{{ route('financial.mouvements.store') }}" enctype="multipart/form-data"
                  class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                @csrf
                <input type="hidden" name="type" :value="type">
                <input type="hidden" name="account" :value="account">
                <input type="hidden" name="origin" :value="origin">

                <div class="px-5 sm:px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <span class="text-sm text-slate-600">Référence</span>
                    <span class="font-mono text-sm font-bold text-slate-900">{{ $previewReference }}</span>
                </div>

                <div class="p-5 sm:p-6 space-y-6">
                    {{-- Step 1: Type --}}
                    <div>
                        <p class="text-sm font-semibold text-slate-900 mb-2">1. Type de mouvement</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" @click="type = 'entree'"
                                    :class="type === 'entree' ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200' : 'border-slate-200 bg-white hover:border-slate-300'"
                                    class="rounded-xl border p-3 text-left transition">
                                <span class="block text-xs font-semibold uppercase tracking-wide text-emerald-700">Entrée</span>
                                <span class="block text-[11px] text-slate-500 mt-1">Argent qui rentre</span>
                            </button>
                            <button type="button" @click="type = 'sortie'"
                                    :class="type === 'sortie' ? 'border-rose-500 bg-rose-50 ring-2 ring-rose-200' : 'border-slate-200 bg-white hover:border-slate-300'"
                                    class="rounded-xl border p-3 text-left transition">
                                <span class="block text-xs font-semibold uppercase tracking-wide text-rose-700">Sortie</span>
                                <span class="block text-[11px] text-slate-500 mt-1">Argent qui sort</span>
                            </button>
                            <button type="button" @click="type = 'virement'"
                                    :class="type === 'virement' ? 'border-sky-500 bg-sky-50 ring-2 ring-sky-200' : 'border-slate-200 bg-white hover:border-slate-300'"
                                    class="rounded-xl border p-3 text-left transition">
                                <span class="block text-xs font-semibold uppercase tracking-wide text-sky-700">Virement</span>
                                <span class="block text-[11px] text-slate-500 mt-1">Banque ↔ Caisse</span>
                            </button>
                        </div>
                    </div>

                    {{-- Step 2: Account --}}
                    <div>
                        <p class="text-sm font-semibold text-slate-900 mb-2">2. Compte</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($accountLabels as $value => $label)
                                <button type="button" @click="account = '{{ $value }}'"
                                        :class="account === '{{ $value }}' ? 'bg-[#0a5d8a] text-white border-[#0a5d8a]' : 'bg-white text-slate-700 border-slate-300 hover:border-slate-400'"
                                        class="px-4 py-2 rounded-full border text-sm font-medium transition">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Step 3: Origin shortcuts --}}
                    <div>
                        <p class="text-sm font-semibold text-slate-900 mb-2">3. Catégorie</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach([
                                'manuel' => 'Manuel',
                                'banque' => 'Frais bancaires',
                                'salaire' => 'Salaire',
                                'loyer' => 'Loyer',
                                'utilities' => 'Eau / Élec. / Internet',
                                'divers' => 'Divers',
                            ] as $value => $label)
                                <button type="button" @click="origin = '{{ $value }}'"
                                        :class="origin === '{{ $value }}' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300'"
                                        class="px-3 py-1.5 rounded-lg border text-xs font-medium transition">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Step 4: Details --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                            <input type="date" name="movement_date" value="{{ old('movement_date', now()->toDateString()) }}" required
                                   class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#0a5d8a]/40 focus:border-[#0a5d8a]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Montant (DH)</label>
                            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required placeholder="0.00"
                                   class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm font-semibold tabular-nums focus:ring-2 focus:ring-[#0a5d8a]/40 focus:border-[#0a5d8a]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Libellé</label>
                        <input type="text" name="label" value="{{ old('label') }}" required placeholder="Ex. Frais bancaires mars 2026"
                               class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-[#0a5d8a]/40 focus:border-[#0a5d8a]">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Justificatif <span class="text-slate-400 font-normal">(optionnel)</span></label>
                            <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-700">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                            <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Optionnel"
                                   class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm">
                        </div>
                    </div>
                </div>

                <div class="px-5 sm:px-6 py-4 border-t border-slate-100 bg-slate-50 flex flex-wrap gap-3 justify-end">
                    <a href="{{ route('financial.mouvements.index') }}" class="px-4 py-2.5 border border-slate-300 rounded-xl text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">Annuler</a>
                    <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#0a5d8a] hover:bg-[#084a6e]">
                        Enregistrer le mouvement
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>
@endsection
