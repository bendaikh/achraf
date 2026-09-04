@extends('layouts.with-sidebar')

@section('title', 'Tableau de bord commercial')
@section('sidebar_page_title', 'Gestion ventes')

@section('main')
<main class="flex-1 w-full min-w-0 overflow-y-auto min-h-screen">
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tableau de bord commercial</h1>
                <p class="text-gray-500 mt-1">{{ $collaborator?->fullName() ?? 'Aucun commercial lié à votre compte' }}</p>
            </div>
            <form method="GET" class="flex flex-wrap gap-2 items-end">
                @if(!empty($commercials) && (auth()->user()->isSuperAdmin() || auth()->user()->hasRole('responsable-commercial') || auth()->user()->hasRole('admin')))
                    <select name="collaborator_id" class="px-3 py-2 border rounded-lg text-sm">
                        @foreach($commercials as $c)
                            <option value="{{ $c->id }}" @selected(($collaborator?->id) === $c->id)>{{ $c->fullName() }}</option>
                        @endforeach
                    </select>
                @endif
                <input type="date" name="date_from" value="{{ $from }}" class="px-3 py-2 border rounded-lg text-sm">
                <input type="date" name="date_to" value="{{ $to }}" class="px-3 py-2 border rounded-lg text-sm">
                <button class="px-4 py-2 bg-[#fdb819] text-white rounded-lg font-semibold text-sm">Filtrer</button>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            @foreach([
                'CA aujourd\'hui' => $stats['ca_aujourdhui'],
                'CA semaine' => $stats['ca_semaine'],
                'CA période' => $stats['ca_mois'],
                'Encaissé' => $stats['encaissees_amount'],
            ] as $label => $value)
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format((float)$value, 2, ',', ' ') }} <span class="text-sm font-medium">DH</span></div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
            @foreach([
                'Devis' => $stats['devis'],
                'Commandes' => $stats['commandes'],
                'Livrées' => $stats['livrees'],
                'Facturées' => $stats['facturees'],
            ] as $label => $value)
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                'Commission à venir' => $stats['commission_a_venir'],
                'Acquise' => $stats['commission_acquise'],
                'Validée' => $stats['commission_validee'],
                'Payée' => $stats['commission_payee'],
            ] as $label => $value)
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format((float)$value, 2, ',', ' ') }} <span class="text-sm font-medium">DH</span></div>
                </div>
            @endforeach
        </div>
    </div>
</main>
@endsection
