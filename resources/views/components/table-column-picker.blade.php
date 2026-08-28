@props([
    'tableId',
    'class' => '',
])

@php
    $service = app(\App\Services\TableColumnPreferenceService::class);
    $payload = $service->payloadForPage(auth()->user(), $tableId);
    $panelId = 'lm-col-panel-' . $tableId;
    $btnId = 'lm-col-btn-' . $tableId;
@endphp

@if(! empty($payload['columns']))
<div
    class="lm-table-column-picker relative inline-flex {{ $class }}"
    data-lm-column-picker="{{ $tableId }}"
>
    <button
        type="button"
        id="{{ $btnId }}"
        class="lm-col-picker-btn inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="{{ $panelId }}"
        title="Afficher / masquer les colonnes"
    >
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span class="hidden sm:inline">Colonnes</span>
    </button>

    <div
        id="{{ $panelId }}"
        class="lm-col-picker-panel hidden absolute right-0 top-full mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white rounded-xl shadow-xl border border-gray-200 z-50"
        role="dialog"
        aria-label="Configuration des colonnes"
    >
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2">
            <div>
                <p class="text-sm font-semibold text-gray-900">Colonnes affichées</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $payload['label'] }}</p>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" class="lm-col-viewport-tab px-2 py-1 text-xs rounded-md bg-[#0a5d8a] text-white" data-viewport="desktop">Ordinateur</button>
                <button type="button" class="lm-col-viewport-tab px-2 py-1 text-xs rounded-md text-gray-600 hover:bg-gray-100" data-viewport="mobile">Mobile</button>
            </div>
        </div>

        <div class="lm-col-picker-list max-h-72 overflow-y-auto p-2 space-y-0.5" data-sortable-list></div>

        <div class="px-4 py-3 border-t border-gray-100 flex flex-wrap gap-2">
            <button type="button" class="lm-col-show-all text-xs px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50">Tout afficher</button>
            <button type="button" class="lm-col-hide-optional text-xs px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50">Masquer optionnelles</button>
            <button type="button" class="lm-col-reset text-xs px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50">Réinitialiser</button>
            @if($payload['can_edit_defaults'])
            <button type="button" class="lm-col-save-default text-xs px-2.5 py-1.5 rounded-md border border-amber-300 text-amber-800 bg-amber-50 hover:bg-amber-100">Définir par défaut</button>
            @endif
        </div>
    </div>

    <script type="application/json" data-lm-table-config="{{ $tableId }}">@json($payload)</script>
</div>
@endif
