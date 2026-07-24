@props([
    'column',
    'label',
    'default' => false,
    'defaultDirection' => 'desc',
])

@php
    $currentSort = request('sort');
    $currentDirection = strtolower((string) request('direction', $defaultDirection));
    if (! in_array($currentDirection, ['asc', 'desc'], true)) {
        $currentDirection = $defaultDirection;
    }

    $isActive = $currentSort === $column || ($default && blank($currentSort));
    $activeDirection = $isActive
        ? (blank($currentSort) ? $defaultDirection : $currentDirection)
        : null;

    $nextDirection = $isActive
        ? ($activeDirection === 'asc' ? 'desc' : 'asc')
        : $defaultDirection;

    $url = request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => $nextDirection,
        'page' => null,
    ]);

    $ariaSort = match ($activeDirection) {
        'asc' => 'ascending',
        'desc' => 'descending',
        default => 'none',
    };

    $hint = $isActive
        ? ($activeDirection === 'desc'
            ? 'Cliquer pour afficher les plus anciens en premier'
            : 'Cliquer pour afficher les plus récents en premier')
        : 'Cliquer pour trier';
@endphp

<th
    class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider"
    aria-sort="{{ $ariaSort }}"
>
    <a
        href="{{ $url }}"
        class="group inline-flex items-center gap-1.5 {{ $isActive ? 'text-gray-900' : 'text-gray-500 hover:text-gray-800' }} transition-colors"
        title="{{ $hint }}"
    >
        <span>{{ $label }}</span>
        <span class="inline-flex flex-col leading-none" aria-hidden="true">
            <svg
                class="h-3 w-3 {{ $activeDirection === 'asc' ? 'text-[#fdb819]' : 'text-gray-300 group-hover:text-gray-400' }}"
                fill="currentColor"
                viewBox="0 0 20 20"
            >
                <path d="M10 6l4 5H6l4-5z" />
            </svg>
            <svg
                class="-mt-0.5 h-3 w-3 {{ $activeDirection === 'desc' ? 'text-[#fdb819]' : 'text-gray-300 group-hover:text-gray-400' }}"
                fill="currentColor"
                viewBox="0 0 20 20"
            >
                <path d="M10 14l-4-5h8l-4 5z" />
            </svg>
        </span>
        <span class="sr-only">
            @if($isActive)
                Tri {{ $activeDirection === 'asc' ? 'croissant' : 'décroissant' }}. {{ $hint }}.
            @else
                Non trié. {{ $hint }}.
            @endif
        </span>
    </a>
</th>
