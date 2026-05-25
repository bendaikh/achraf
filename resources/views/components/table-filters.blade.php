@props([
    'action',
    'search' => true,
    'searchPlaceholder' => 'Rechercher...',
    'dateFrom' => true,
    'dateTo' => true,
    'resetUrl' => null,
    'gridCols' => 'md:grid-cols-6',
])

@php
    $resetUrl = $resetUrl ?? $action;
    $hasActiveFilters = count(request()->except(['page', 'per_page'])) > 0;
@endphp

<div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
    <form method="GET" action="{{ $action }}" class="grid grid-cols-1 {{ $gridCols }} gap-4">
        @if($search)
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Rechercher</label>
                <input
                    type="text"
                    name="search"
                    id="search"
                    value="{{ request('search') }}"
                    placeholder="{{ $searchPlaceholder }}"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]"
                >
            </div>
        @endif

        {{ $slot }}

        @if($dateFrom)
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
                <input
                    type="date"
                    name="date_from"
                    id="date_from"
                    value="{{ request('date_from') }}"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]"
                >
            </div>
        @endif

        @if($dateTo)
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
                <input
                    type="date"
                    name="date_to"
                    id="date_to"
                    value="{{ request('date_to') }}"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819]"
                >
            </div>
        @endif

        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 px-4 py-2 bg-[#fdb819] text-white rounded-lg hover:bg-[#e5a617] transition font-medium">
                Filtrer
            </button>
            @if($hasActiveFilters)
                <a href="{{ $resetUrl }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm whitespace-nowrap">
                    Réinitialiser
                </a>
            @endif
        </div>
    </form>
</div>
