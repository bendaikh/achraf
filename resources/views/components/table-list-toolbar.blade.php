@props([
    'tableId' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-end gap-2 mb-4']) }}>
    @if($tableId)
        <x-table-column-picker :table-id="$tableId" />
    @endif
    {{ $slot }}
</div>
