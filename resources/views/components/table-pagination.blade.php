@props([
    'paginator',
    'itemLabel' => 'lignes',
    'perPageOptions' => [10, 25, 50, 100],
    'defaultPerPage' => 15,
    'bordered' => true,
])

@php
    $currentPerPage = (int) request('per_page', $defaultPerPage);

    if (! in_array($currentPerPage, $perPageOptions, true)) {
        $currentPerPage = $defaultPerPage;
    }

    $wrapperClass = $bordered
        ? 'px-6 py-4 border-t border-gray-200'
        : 'mt-6';
@endphp

<div {{ $attributes->merge(['class' => $wrapperClass]) }}>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <label for="table-per-page-{{ $paginator->getPageName() }}" class="text-sm text-gray-600">Afficher</label>
            <select
                id="table-per-page-{{ $paginator->getPageName() }}"
                onchange="changeTablePerPage(this.value)"
                class="rounded-lg border-gray-300 shadow-sm focus:border-[#fdb819] focus:ring-[#fdb819] text-sm"
            >
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}" @selected($currentPerPage === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <span class="text-sm text-gray-600">{{ $itemLabel }} par page</span>
        </div>

        @if ($paginator->hasPages())
            <div>
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</div>
