@extends('layouts.with-sidebar')

@section('title', 'Modifier mouvement')
@section('sidebar_page_title', 'Gestion Financière')

@section('main')
<main class="flex-1 w-full min-w-0" x-data="{
    type: '{{ old('type', $mouvement->type) }}',
    account: '{{ old('account', $mouvement->account) }}',
    origin: '{{ old('origin', $mouvement->origin) }}'
}">
    <header class="bg-white border-b border-slate-200">
        <div class="px-4 sm:px-6 lg:px-8 py-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[#0a5d8a]">Gestion financière / Mouvements</p>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5">Modifier {{ $mouvement->reference }}</h2>
            </div>
            <a href="{{ route('financial.mouvements.index') }}" class="text-sm font-medium text-[#0a5d8a] hover:underline">← Retour au journal</a>
        </div>
    </header>

    <div class="p-4 sm:p-6 lg:p-8 bg-slate-50/80">
        <div class="max-w-3xl mx-auto">
            <form method="POST" action="{{ route('financial.mouvements.update', $mouvement) }}" enctype="multipart/form-data"
                  class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                @csrf
                @method('PUT')
                <input type="hidden" name="type" :value="type">
                <input type="hidden" name="account" :value="account">
                <input type="hidden" name="origin" :value="origin">

                <div class="p-5 sm:p-6 space-y-6">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 mb-2">Type</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" @click="type = 'entree'"
                                    :class="type === 'entree' ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-200' : 'border-slate-200 bg-white'"
                                    class="rounded-xl border p-3 text-left">
                                <span class="text-xs font-semibold uppercase text-emerald-700">Entrée</span>
                            </button>
                            <button type="button" @click="type = 'sortie'"
                                    :class="type === 'sortie' ? 'border-rose-500 bg-rose-50 ring-2 ring-rose-200' : 'border-slate-200 bg-white'"
                                    class="rounded-xl border p-3 text-left">
                                <span class="text-xs font-semibold uppercase text-rose-700">Sortie</span>
                            </button>
                            <button type="button" @click="type = 'virement'"
                                    :class="type === 'virement' ? 'border-sky-500 bg-sky-50 ring-2 ring-sky-200' : 'border-slate-200 bg-white'"
                                    class="rounded-xl border p-3 text-left">
                                <span class="text-xs font-semibold uppercase text-sky-700">Virement</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-900 mb-2">Compte</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($accountLabels as $value => $label)
                                <button type="button" @click="account = '{{ $value }}'"
                                        :class="account === '{{ $value }}' ? 'bg-[#0a5d8a] text-white border-[#0a5d8a]' : 'bg-white text-slate-700 border-slate-300'"
                                        class="px-4 py-2 rounded-full border text-sm font-medium">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-900 mb-2">Catégorie</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($originLabels as $value => $label)
                                <button type="button" @click="origin = '{{ $value }}'"
                                        :class="origin === '{{ $value }}' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-300'"
                                        class="px-3 py-1.5 rounded-lg border text-xs font-medium">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                            <input type="date" name="movement_date" value="{{ old('movement_date', $mouvement->movement_date->toDateString()) }}" required
                                   class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Montant (DH)</label>
                            <input type="number" step="0.01" min="0.01" name="amount"
                                   value="{{ old('amount', max((float) $mouvement->amount_in, (float) $mouvement->amount_out)) }}" required
                                   class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm font-semibold tabular-nums">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Libellé</label>
                        <input type="text" name="label" value="{{ old('label', $mouvement->label) }}" required
                               class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Justificatif</label>
                            <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                            <input type="text" name="notes" value="{{ old('notes', $mouvement->notes) }}"
                                   class="w-full px-3 py-2.5 border border-slate-300 rounded-xl text-sm">
                        </div>
                    </div>
                </div>

                <div class="px-5 sm:px-6 py-4 border-t border-slate-100 bg-slate-50 flex flex-wrap gap-3 justify-end">
                    <a href="{{ route('financial.mouvements.index') }}" class="px-4 py-2.5 border border-slate-300 rounded-xl text-sm font-medium bg-white">Annuler</a>
                    <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#0a5d8a] hover:bg-[#084a6e]">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</main>
@endsection
